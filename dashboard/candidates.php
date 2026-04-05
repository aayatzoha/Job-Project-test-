<?php
// Include database connection
require_once '../config/database.php';
require_once '../includes/functions.php';
session_start();

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'company') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get company information
$company = $db->fetch("SELECT name FROM companies WHERE user_id = ?", [$user_id]);
$company_name = $company ? $company['name'] : 'Company';

// Get candidates who applied to company jobs
$candidates = $db->fetchAll("SELECT DISTINCT u.id, u.name as first_name, u.name as last_name, u.email, u.profile_image,
                        (SELECT COUNT(*) FROM job_applications ja 
                         JOIN jobs j ON ja.job_id = j.id 
                         WHERE ja.job_seeker_id = u.id AND j.company_id = ?) AS application_count,
                        (SELECT MAX(ja.application_date) FROM job_applications ja 
                         JOIN jobs j ON ja.job_id = j.id 
                         WHERE ja.job_seeker_id = u.id AND j.company_id = ?) AS last_application
                        FROM users u
                        JOIN job_applications ja ON u.id = ja.user_id
                        JOIN jobs j ON ja.job_id = j.id
                        WHERE j.company_id = ?
                        ORDER BY last_application DESC");
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$candidates = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidates | Company Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="dashboard-content">
            <header class="dashboard-header">
                <h1>Candidate Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($company_name); ?></span>
                </div>
            </header>

            <div class="dashboard-section active">
                <div class="section-header">
                    <h2>Your Candidate Pool</h2>
                    <div class="search-container">
                        <input type="text" id="candidateSearch" placeholder="Search candidates...">
                        <button class="btn-search"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                
                <div class="filter-options">
                    <select id="filterStatus">
                        <option value="all">All Candidates</option>
                        <option value="shortlisted">Shortlisted</option>
                        <option value="interviewed">Interviewed</option>
                        <option value="hired">Hired</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <select id="sortBy">
                        <option value="recent">Most Recent</option>
                        <option value="name">Name (A-Z)</option>
                        <option value="applications">Most Applications</option>
                    </select>
                </div>
                
                <div class="candidates-grid" id="candidatesGrid">
                    <?php if ($candidates->num_rows > 0): ?>
                        <?php while ($candidate = $candidates->fetch_assoc()): ?>
                            <div class="candidate-card" data-id="<?php echo $candidate['id']; ?>">
                                <div class="candidate-header">
                                    <div class="candidate-avatar">
                                        <?php if ($candidate['profile_image']): ?>
                                            <img src="../uploads/profile_images/<?php echo htmlspecialchars($candidate['profile_image']); ?>" alt="Profile Image">
                                        <?php else: ?>
                                            <div class="avatar-placeholder">
                                                <?php echo strtoupper(substr($candidate['first_name'], 0, 1) . substr($candidate['last_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="candidate-info">
                                        <h3><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?></h3>
                                        <p class="candidate-email"><?php echo htmlspecialchars($candidate['email']); ?></p>
                                    </div>
                                </div>
                                <div class="candidate-stats">
                                    <div class="stat">
                                        <span class="stat-value"><?php echo $candidate['application_count']; ?></span>
                                        <span class="stat-label">Applications</span>
                                    </div>
                                    <div class="stat">
                                        <span class="stat-value"><?php echo date('M d', strtotime($candidate['last_application'])); ?></span>
                                        <span class="stat-label">Last Applied</span>
                                    </div>
                                </div>
                                <div class="candidate-actions">
                                    <button class="btn btn-outline view-profile" data-id="<?php echo $candidate['id']; ?>">View Profile</button>
                                    <button class="btn btn-primary view-applications" data-id="<?php echo $candidate['id']; ?>">Applications</button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-candidates">
                            <i class="fas fa-user-slash"></i>
                            <p>No candidates have applied to your jobs yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Candidate Profile Modal -->
    <div id="candidateModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div id="candidateProfile">
                <div class="profile-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading candidate profile...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/company-dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            const modal = document.getElementById('candidateModal');
            const closeModal = document.querySelector('.close-modal');
            
            closeModal.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
            
            // View candidate profile
            document.querySelectorAll('.view-profile').forEach(button => {
                button.addEventListener('click', function() {
                    const candidateId = this.getAttribute('data-id');
                    document.getElementById('candidateProfile').innerHTML = `
                        <div class="profile-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading candidate profile...</p>
                        </div>
                    `;
                    modal.style.display = 'block';
                    
                    // Fetch candidate profile
                    fetch(`../api/company/candidates.php?action=profile&id=${candidateId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const candidate = data.candidate;
                                let profileHTML = `
                                    <div class="candidate-profile">
                                        <div class="profile-header">
                                            <div class="profile-avatar">
                                                ${candidate.profile_image ? 
                                                    `<img src="../uploads/profile_images/${candidate.profile_image}" alt="Profile Image">` : 
                                                    `<div class="avatar-placeholder">${candidate.first_name.charAt(0)}${candidate.last_name.charAt(0)}</div>`
                                                }
                                            </div>
                                            <div class="profile-info">
                                                <h2>${candidate.first_name} ${candidate.last_name}</h2>
                                                <p class="profile-title">${candidate.title || 'Job Seeker'}</p>
                                                <p class="profile-location"><i class="fas fa-map-marker-alt"></i> ${candidate.location || 'Location not specified'}</p>
                                            </div>
                                        </div>
                                        
                                        <div class="profile-contact">
                                            <div class="contact-item">
                                                <i class="fas fa-envelope"></i>
                                                <span>${candidate.email}</span>
                                            </div>
                                            ${candidate.phone ? `
                                                <div class="contact-item">
                                                    <i class="fas fa-phone"></i>
                                                    <span>${candidate.phone}</span>
                                                </div>
                                            ` : ''}
                                        </div>
                                        
                                        <div class="profile-section">
                                            <h3>About</h3>
                                            <p>${candidate.bio || 'No information provided.'}</p>
                                        </div>
                                        
                                        <div class="profile-section">
                                            <h3>Skills</h3>
                                            <div class="skills-list">
                                                ${candidate.skills ? candidate.skills.split(',').map(skill => 
                                                    `<span class="skill-tag">${skill.trim()}</span>`
                                                ).join('') : 'No skills listed.'}
                                            </div>
                                        </div>
                                        
                                        <div class="profile-section">
                                            <h3>Experience</h3>
                                            ${candidate.experience ? candidate.experience : '<p>No experience listed.</p>'}
                                        </div>
                                        
                                        <div class="profile-section">
                                            <h3>Education</h3>
                                            ${candidate.education ? candidate.education : '<p>No education listed.</p>'}
                                        </div>
                                        
                                        <div class="profile-actions">
                                            ${candidate.resume ? `
                                                <a href="../uploads/resumes/${candidate.resume}" class="btn btn-primary" target="_blank">
                                                    <i class="fas fa-file-pdf"></i> View Resume
                                                </a>
                                            ` : ''}
                                            <button class="btn btn-outline contact-candidate" data-id="${candidate.id}">
                                                <i class="fas fa-envelope"></i> Contact
                                            </button>
                                        </div>
                                    </div>
                                `;
                                document.getElementById('candidateProfile').innerHTML = profileHTML;
                            } else {
                                document.getElementById('candidateProfile').innerHTML = `
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <p>Error loading candidate profile. Please try again.</p>
                                    </div>
                                `;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            document.getElementById('candidateProfile').innerHTML = `
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <p>An error occurred. Please try again.</p>
                                </div>
                            `;
                        });
                });
            });
            
            // View candidate applications
            document.querySelectorAll('.view-applications').forEach(button => {
                button.addEventListener('click', function() {
                    const candidateId = this.getAttribute('data-id');
                    window.location.href = `candidate_applications.php?id=${candidateId}`;
                });
            });
            
            // Search functionality
            document.getElementById('candidateSearch').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                document.querySelectorAll('.candidate-card').forEach(card => {
                    const name = card.querySelector('.candidate-info h3').textContent.toLowerCase();
                    const email = card.querySelector('.candidate-email').textContent.toLowerCase();
                    
                    if (name.includes(searchTerm) || email.includes(searchTerm)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
            
            // Filter functionality
            document.getElementById('filterStatus').addEventListener('change', function() {
                // This would require additional data attributes on the cards
                // For now, we'll just log the selected value
                console.log('Filter by:', this.value);
            });
            
            // Sort functionality
            document.getElementById('sortBy').addEventListener('change', function() {
                const sortBy = this.value;
                const candidatesGrid = document.getElementById('candidatesGrid');
                const candidateCards = Array.from(document.querySelectorAll('.candidate-card'));
                
                candidateCards.sort((a, b) => {
                    if (sortBy === 'name') {
                        const nameA = a.querySelector('.candidate-info h3').textContent;
                        const nameB = b.querySelector('.candidate-info h3').textContent;
                        return nameA.localeCompare(nameB);
                    } else if (sortBy === 'applications') {
                        const appsA = parseInt(a.querySelector('.stat-value').textContent);
                        const appsB = parseInt(b.querySelector('.stat-value').textContent);
                        return appsB - appsA;
                    }
                    // Default to recent (already sorted by the server)
                    return 0;
                });
                
                // Clear and re-append sorted cards
                candidatesGrid.innerHTML = '';
                candidateCards.forEach(card => {
                    candidatesGrid.appendChild(card);
                });
            });
        });
    </script>
</body>
</html>