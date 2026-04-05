// Admin Dashboard Charts and Real-time Monitoring

class AdminChartsManager {
    constructor() {
        this.charts = {};
        this.monitoringInterval = null;
        this.refreshInterval = 10000; // 10 seconds default
        this.init();
    }
    
    init() {
        this.initializeCharts();
        this.startRealTimeUpdates();
    }
    
    initializeCharts() {
        // Initialize user growth chart
        this.initUserGrowthChart();
        
        // Initialize match success chart
        this.initMatchSuccessChart();
        
        // Initialize real-time activity chart
        this.initRealTimeActivityChart();
        
        // Initialize system resources chart
        this.initSystemResourcesChart();
    }
    
    initUserGrowthChart() {
        const ctx = document.getElementById('userGrowthChart');
        if (!ctx) return;
        
        this.charts.userGrowth = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Job Seekers',
                        data: [],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Companies',
                        data: [],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'New Users'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });
        
        this.loadUserGrowthData();
    }
    
    initMatchSuccessChart() {
        const ctx = document.getElementById('matchSuccessChart');
        if (!ctx) return;
        
        this.charts.matchSuccess = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Successful Matches', 'Pending', 'No Response'],
                datasets: [{
                    data: [65, 25, 10],
                    backgroundColor: [
                        '#28a745',
                        '#ffc107',
                        '#dc3545'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    initRealTimeActivityChart() {
        const ctx = document.getElementById('realTimeActivityChart');
        if (!ctx) return;
        
        this.charts.realTimeActivity = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Active Users',
                        data: [],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Page Views',
                        data: [],
                        borderColor: '#17a2b8',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 0
                },
                interaction: {
                    intersect: false
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Count'
                        }
                    },
                    x: {
                        type: 'time',
                        time: {
                            unit: 'minute',
                            displayFormats: {
                                minute: 'HH:mm'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Time'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
        
        // Initialize with some sample data
        this.updateRealTimeChart();
    }
    
    initSystemResourcesChart() {
        const ctx = document.getElementById('systemResourcesChart');
        if (!ctx) return;
        
        this.charts.systemResources = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['CPU', 'Memory', 'Disk', 'Network'],
                datasets: [{
                    label: 'Usage %',
                    data: [45, 62, 78, 23],
                    backgroundColor: [
                        '#007bff',
                        '#28a745', 
                        '#ffc107',
                        '#17a2b8'
                    ],
                    borderColor: [
                        '#0056b3',
                        '#1e7e34',
                        '#e0a800',
                        '#117a8b'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Usage %'
                        }
                    }
                }
            }
        });
    }
    
    async loadUserGrowthData() {
        try {
            const period = document.getElementById('growth-period')?.value || 30;
            const response = await fetch(`../api/admin/analytics.php?period=${period}`);
            const data = await response.json();
            
            if (data.success && data.analytics.user_growth) {
                this.updateUserGrowthChart(data.analytics.user_growth);
            }
        } catch (error) {
            console.error('Error loading user growth data:', error);
        }
    }
    
    updateUserGrowthChart(data) {
        if (!this.charts.userGrowth) return;
        
        const labels = [];
        const jobSeekerData = [];
        const companyData = [];
        
        // Process data by date
        const dateGroups = {};
        data.forEach(item => {
            if (!dateGroups[item.month]) {
                dateGroups[item.month] = { job_seeker: 0, company: 0 };
            }
            dateGroups[item.month][item.user_type] = item.count;
        });
        
        Object.keys(dateGroups).forEach(date => {
            labels.push(date);
            jobSeekerData.push(dateGroups[date].job_seeker || 0);
            companyData.push(dateGroups[date].company || 0);
        });
        
        this.charts.userGrowth.data.labels = labels;
        this.charts.userGrowth.data.datasets[0].data = jobSeekerData;
        this.charts.userGrowth.data.datasets[1].data = companyData;
        this.charts.userGrowth.update();
    }
    
    updateRealTimeChart() {
        if (!this.charts.realTimeActivity) return;
        
        const now = new Date();
        const data = this.charts.realTimeActivity.data;
        
        // Add new data point
        data.labels.push(now);
        data.datasets[0].data.push(Math.floor(Math.random() * 50) + 10); // Simulated active users
        data.datasets[1].data.push(Math.floor(Math.random() * 200) + 50); // Simulated page views
        
        // Keep only last 20 data points
        if (data.labels.length > 20) {
            data.labels.shift();
            data.datasets[0].data.shift();
            data.datasets[1].data.shift();
        }
        
        this.charts.realTimeActivity.update('none');
    }
    
    updateSystemResourcesChart() {
        if (!this.charts.systemResources) return;
        
        // Simulate resource usage updates
        const data = this.charts.systemResources.data.datasets[0].data;
        for (let i = 0; i < data.length; i++) {
            data[i] = Math.max(0, Math.min(100, data[i] + (Math.random() - 0.5) * 10));
        }
        
        this.charts.systemResources.update();
        
        // Update performance metrics in the monitoring section
        this.updatePerformanceMetrics();
    }
    
    updatePerformanceMetrics() {
        const metrics = {
            'active-users-count': Math.floor(Math.random() * 100) + 50,
            'requests-per-sec': (Math.random() * 50 + 10).toFixed(1),
            'avg-response-time': Math.floor(Math.random() * 200) + 50,
            'memory-usage': Math.floor(Math.random() * 30) + 60,
            'cpu-usage': Math.floor(Math.random() * 40) + 20,
            'disk-usage': Math.floor(Math.random() * 20) + 70
        };
        
        Object.keys(metrics).forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = metrics[id] + (id.includes('usage') ? '%' : (id === 'avg-response-time' ? 'ms' : ''));
            }
        });
        
        // Update last update time
        const lastUpdateElement = document.getElementById('last-update');
        if (lastUpdateElement) {
            lastUpdateElement.textContent = `Last updated: ${new Date().toLocaleTimeString()}`;
        }
    }
    
    startRealTimeUpdates() {
        // Update real-time charts periodically
        this.monitoringInterval = setInterval(() => {
            this.updateRealTimeChart();
            this.updateSystemResourcesChart();
            this.updateRealTimeStats();
            this.addLiveLogEntry();
        }, this.refreshInterval);
    }
    
    updateRealTimeStats() {
        // Update the trending stats on the dashboard
        const trendElements = document.querySelectorAll('.stat-trend');
        trendElements.forEach(element => {
            const trend = (Math.random() - 0.5) * 20; // -10% to +10%
            element.textContent = (trend >= 0 ? '+' : '') + trend.toFixed(1) + '%';
            element.className = 'stat-trend ' + (trend >= 0 ? 'positive' : 'negative');
        });
        
        // Update subtitle counters
        const subtitles = {
            'new-job-seekers': Math.floor(Math.random() * 20),
            'new-companies': Math.floor(Math.random() * 5),
            'new-jobs': Math.floor(Math.random() * 15),
            'new-applications': Math.floor(Math.random() * 50),
            'assessment-completion-rate': Math.floor(Math.random() * 30) + 60,
            'recommendation-success-rate': Math.floor(Math.random() * 25) + 65
        };
        
        Object.keys(subtitles).forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = subtitles[id];
            }
        });
    }
    
    addLiveLogEntry() {
        const logsContainer = document.getElementById('live-logs');
        if (!logsContainer) return;
        
        const logTypes = ['INFO', 'WARNING', 'ERROR'];
        const logMessages = [
            'User login successful',
            'Database query executed',
            'Email sent successfully',
            'Cache cleared',
            'Backup completed',
            'Security scan running',
            'Memory usage warning',
            'New user registered'
        ];
        
        const logType = logTypes[Math.floor(Math.random() * logTypes.length)];
        const logMessage = logMessages[Math.floor(Math.random() * logMessages.length)];
        const logTime = new Date().toLocaleTimeString();
        
        const logEntry = document.createElement('div');
        logEntry.className = `log-entry ${logType.toLowerCase()}`;
        logEntry.innerHTML = `
            <span class="log-time">${logTime}</span>
            <span class="log-level">${logType}</span>
            <span class="log-message">${logMessage}</span>
        `;
        
        logsContainer.insertBefore(logEntry, logsContainer.firstChild);
        
        // Keep only last 20 entries
        const entries = logsContainer.querySelectorAll('.log-entry');
        if (entries.length > 20) {
            entries[entries.length - 1].remove();
        }
    }
    
    setRefreshInterval(seconds) {
        this.refreshInterval = seconds * 1000;
        
        if (this.monitoringInterval) {
            clearInterval(this.monitoringInterval);
        }
        
        if (seconds > 0) {
            this.startRealTimeUpdates();
        }
    }
    
    destroy() {
        // Clean up charts and intervals
        Object.values(this.charts).forEach(chart => {
            if (chart) chart.destroy();
        });
        
        if (this.monitoringInterval) {
            clearInterval(this.monitoringInterval);
        }
    }
}

// Global functions for chart interactions
function setRefreshInterval(seconds) {
    if (window.adminChartsManager) {
        window.adminChartsManager.setRefreshInterval(parseInt(seconds));
    }
}

function clearLogs() {
    const logsContainer = document.getElementById('live-logs');
    if (logsContainer) {
        logsContainer.innerHTML = '<div class="log-entry info"><span class="log-time">' + 
            new Date().toLocaleTimeString() + '</span><span class="log-level">INFO</span>' +
            '<span class="log-message">Logs cleared</span></div>';
    }
}

function exportLogs() {
    const logs = document.querySelectorAll('#live-logs .log-entry');
    let logContent = 'Timestamp,Level,Message\n';
    
    logs.forEach(log => {
        const time = log.querySelector('.log-time').textContent;
        const level = log.querySelector('.log-level').textContent;
        const message = log.querySelector('.log-message').textContent;
        logContent += `"${time}","${level}","${message}"\n`;
    });
    
    const blob = new Blob([logContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `system_logs_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Notification functions
function createNotification() {
    const form = document.getElementById('quick-notification-form');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                title: document.getElementById('notification-title').value,
                message: document.getElementById('notification-message').value,
                type: document.getElementById('notification-type').value,
                recipients: Array.from(document.getElementById('notification-recipients').selectedOptions).map(o => o.value)
            };
            
            try {
                const response = await fetch('../api/admin/notifications.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Notification sent successfully to ${result.sent_count} users!`);
                    form.reset();
                } else {
                    alert('Failed to send notification: ' + result.message);
                }
            } catch (error) {
                console.error('Error sending notification:', error);
                alert('Failed to send notification. Please try again.');
            }
        });
    }
}

function createEmailCampaign() {
    alert('Email Campaign feature coming soon!');
}

function viewTemplates() {
    alert('Templates feature coming soon!');
}

function generateReport(reportType) {
    window.open(`../api/admin/reports.php?type=${reportType}&format=pdf`, '_blank');
}

function generateCustomReport() {
    alert('Custom Report Builder coming soon!');
}

function scheduleReport() {
    alert('Report Scheduling feature coming soon!');
}

// Initialize charts when dashboard loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts manager when admin dashboard is active
    if (document.querySelector('.admin-dashboard')) {
        window.adminChartsManager = new AdminChartsManager();
    }
});

// Clean up when leaving the page
window.addEventListener('beforeunload', function() {
    if (window.adminChartsManager) {
        window.adminChartsManager.destroy();
    }
});