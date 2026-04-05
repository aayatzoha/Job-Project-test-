// AI Job Recommendation System - Service Worker

const CACHE_NAME = 'ai-job-system-v2.0.0';
const STATIC_CACHE = 'static-v2.0.0';
const DYNAMIC_CACHE = 'dynamic-v2.0.0';
const OFFLINE_CACHE = 'offline-v2.0.0';

// Files to cache for offline functionality
const STATIC_FILES = [
    '/job/',
    '/job/index.php',
    '/job/offline.html',
    '/job/assets/css/style.css',
    '/job/assets/css/responsive.css',
    '/job/assets/css/dashboard.css',
    '/job/assets/js/main.js',
    '/job/assets/js/auth.js',
    '/job/assets/js/dashboard.js',
    '/job/assets/js/proctoring.js',
    '/job/manifest.json',
    '/job/assets/images/icon-192x192.png',
    '/job/assets/images/icon-512x512.png'
];

// API endpoints that should be cached for offline access
const API_CACHE_PATTERNS = [
    '/job/api/recommendations/',
    '/job/api/chatbot/',
    '/job/api/company/jobs.php'
];

// Maximum age for cached content (in milliseconds)
const CACHE_MAX_AGE = 24 * 60 * 60 * 1000; // 24 hours

// Install event - cache static files
self.addEventListener('install', event => {
    console.log('Service Worker installing...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('Caching static files...');
                return cache.addAll(STATIC_FILES);
            })
            .then(() => {
                console.log('Static files cached successfully');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Error caching static files:', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('Service Worker activating...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                            console.log('Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker activated');
                return self.clients.claim();
            })
    );
});

// Fetch event - serve cached content when offline
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip external requests
    if (url.origin !== location.origin) {
        return;
    }
    
    // Handle different types of requests
    if (request.destination === 'document') {
        // Handle page requests
        event.respondWith(handlePageRequest(request));
    } else if (request.destination === 'style' || 
               request.destination === 'script' || 
               request.destination === 'image') {
        // Handle static assets
        event.respondWith(handleStaticRequest(request));
    } else if (url.pathname.startsWith('/job/api/')) {
        // Handle API requests
        event.respondWith(handleApiRequest(request));
    } else {
        // Handle other requests
        event.respondWith(handleOtherRequest(request));
    }
});

// Handle page requests
async function handlePageRequest(request) {
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful responses
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
            return networkResponse;
        }
        
        throw new Error('Network response not ok');
    } catch (error) {
        console.log('Network failed for page request, trying cache...');
        
        // Try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline page for document requests
        if (request.destination === 'document') {
            return caches.match('/job/offline.html');
        }
        
        throw error;
    }
}

// Handle static asset requests
async function handleStaticRequest(request) {
    try {
        // Try cache first for static assets
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Try network
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful responses
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('Failed to fetch static asset:', request.url);
        throw error;
    }
}

// Handle API requests
async function handleApiRequest(request) {
    try {
        // Always try network first for API requests
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache GET requests only
            if (request.method === 'GET') {
                const cache = await caches.open(DYNAMIC_CACHE);
                cache.put(request, networkResponse.clone());
            }
        }
        
        return networkResponse;
    } catch (error) {
        console.log('Network failed for API request, trying cache...');
        
        // Try cache for GET requests only
        if (request.method === 'GET') {
            const cachedResponse = await caches.match(request);
            if (cachedResponse) {
                return cachedResponse;
            }
        }
        
        // Return offline response for API requests
        return new Response(
            JSON.stringify({
                success: false,
                message: 'You are offline. Please check your internet connection.',
                offline: true
            }),
            {
                status: 503,
                statusText: 'Service Unavailable',
                headers: {
                    'Content-Type': 'application/json'
                }
            }
        );
    }
}

// Handle other requests
async function handleOtherRequest(request) {
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful responses
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        // Try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        throw error;
    }
}

// Handle background sync
self.addEventListener('sync', event => {
    console.log('Background sync triggered:', event.tag);
    
    if (event.tag === 'background-sync') {
        event.waitUntil(doBackgroundSync());
    }
});

// Background sync implementation
async function doBackgroundSync() {
    try {
        // Get pending requests from IndexedDB
        const pendingRequests = await getPendingRequests();
        
        for (const request of pendingRequests) {
            try {
                const response = await fetch(request.url, request.options);
                
                if (response.ok) {
                    // Remove from pending requests
                    await removePendingRequest(request.id);
                    console.log('Synced pending request:', request.id);
                }
            } catch (error) {
                console.error('Failed to sync request:', request.id, error);
            }
        }
    } catch (error) {
        console.error('Background sync failed:', error);
    }
}

// Handle push notifications
self.addEventListener('push', event => {
    console.log('Push notification received:', event);
    
    const options = {
        body: 'You have new job recommendations!',
        icon: '/job/assets/images/icon-192x192.png',
        badge: '/job/assets/images/badge-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'View Jobs',
                icon: '/job/assets/images/action-view.png'
            },
            {
                action: 'close',
                title: 'Close',
                icon: '/job/assets/images/action-close.png'
            }
        ]
    };
    
    if (event.data) {
        const data = event.data.json();
        options.body = data.body || options.body;
        options.data = { ...options.data, ...data };
    }
    
    event.waitUntil(
        self.registration.showNotification('AI Job System', options)
    );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
    console.log('Notification clicked:', event);
    
    event.notification.close();
    
    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/job/dashboard/job_seeker.php')
        );
    } else if (event.action === 'close') {
        // Just close the notification
        return;
    } else {
        // Default action - open the app
        event.waitUntil(
            clients.openWindow('/job/')
        );
    }
});

// Handle message from main thread
self.addEventListener('message', event => {
    console.log('Message received in service worker:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'GET_VERSION') {
        event.ports[0].postMessage({ version: CACHE_NAME });
    }
});

// Enhanced IndexedDB operations for offline queue
let db = null;

// Initialize IndexedDB
async function initDB() {
    if (db) return db;
    
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('ai-job-system-offline', 2);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            db = request.result;
            resolve(db);
        };
        
        request.onupgradeneeded = (event) => {
            const database = event.target.result;
            
            // Create stores
            if (!database.objectStoreNames.contains('pending_requests')) {
                const store = database.createObjectStore('pending_requests', { keyPath: 'id', autoIncrement: true });
                store.createIndex('timestamp', 'timestamp', { unique: false });
                store.createIndex('url', 'url', { unique: false });
            }
            
            if (!database.objectStoreNames.contains('offline_data')) {
                const dataStore = database.createObjectStore('offline_data', { keyPath: 'key' });
                dataStore.createIndex('timestamp', 'timestamp', { unique: false });
            }
        };
    });
}

// Get pending requests from IndexedDB
async function getPendingRequests() {
    try {
        const database = await initDB();
        const transaction = database.transaction(['pending_requests'], 'readonly');
        const store = transaction.objectStore('pending_requests');
        
        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    } catch (error) {
        console.error('Error getting pending requests:', error);
        return [];
    }
}

// Add request to offline queue
async function addPendingRequest(url, options, data = null) {
    try {
        const database = await initDB();
        const transaction = database.transaction(['pending_requests'], 'readwrite');
        const store = transaction.objectStore('pending_requests');
        
        const request = {
            url,
            options: {
                method: options.method,
                headers: options.headers ? Object.fromEntries(options.headers.entries()) : {},
                body: options.body
            },
            data,
            timestamp: Date.now(),
            retries: 0
        };
        
        return new Promise((resolve, reject) => {
            const addRequest = store.add(request);
            addRequest.onsuccess = () => resolve(addRequest.result);
            addRequest.onerror = () => reject(addRequest.error);
        });
    } catch (error) {
        console.error('Error adding pending request:', error);
        return null;
    }
}

// Remove pending request
async function removePendingRequest(id) {
    try {
        const database = await initDB();
        const transaction = database.transaction(['pending_requests'], 'readwrite');
        const store = transaction.objectStore('pending_requests');
        
        return new Promise((resolve) => {
            const request = store.delete(id);
            request.onsuccess = () => resolve(true);
            request.onerror = () => resolve(false);
        });
    } catch (error) {
        console.error('Error removing pending request:', error);
        return false;
    }
}

// Store offline data
async function storeOfflineData(key, data) {
    try {
        const database = await initDB();
        const transaction = database.transaction(['offline_data'], 'readwrite');
        const store = transaction.objectStore('offline_data');
        
        const item = {
            key,
            data,
            timestamp: Date.now()
        };
        
        return new Promise((resolve) => {
            const request = store.put(item);
            request.onsuccess = () => resolve(true);
            request.onerror = () => resolve(false);
        });
    } catch (error) {
        console.error('Error storing offline data:', error);
        return false;
    }
}

// Get offline data
async function getOfflineData(key) {
    try {
        const database = await initDB();
        const transaction = database.transaction(['offline_data'], 'readonly');
        const store = transaction.objectStore('offline_data');
        
        return new Promise((resolve) => {
            const request = store.get(key);
            request.onsuccess = () => {
                const result = request.result;
                if (result && (Date.now() - result.timestamp < CACHE_MAX_AGE)) {
                    resolve(result.data);
                } else {
                    resolve(null);
                }
            };
            request.onerror = () => resolve(null);
        });
    } catch (error) {
        console.error('Error getting offline data:', error);
        return null;
    }
}

// Cache management utilities
async function clearOldCaches() {
    const cacheNames = await caches.keys();
    const oldCaches = cacheNames.filter(name => 
        name !== STATIC_CACHE && name !== DYNAMIC_CACHE
    );
    
    return Promise.all(
        oldCaches.map(cacheName => caches.delete(cacheName))
    );
}

async function getCacheSize() {
    const cacheNames = await caches.keys();
    let totalSize = 0;
    
    for (const cacheName of cacheNames) {
        const cache = await caches.open(cacheName);
        const keys = await cache.keys();
        
        for (const request of keys) {
            const response = await cache.match(request);
            if (response) {
                const blob = await response.blob();
                totalSize += blob.size;
            }
        }
    }
    
    return totalSize;
}
