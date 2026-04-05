<?php
// CLI-only seed script to populate demo companies, jobs, and assignments
// Usage: php scripts/seed_demo_data.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function out($msg) { echo $msg . "\n"; }

out("== AI Job System: Demo Data Seeding ==");

try {

    // Helper: create user
    $createUser = function($name, $email, $password, $type) use ($db) {
        // Exists? return ID
        $existing = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing && isset($existing['id'])) { return (int)$existing['id']; }
        $hash = hashPassword($password);
        $db->execute(
            "INSERT INTO users (name, email, password_hash, user_type, email_verified, status, created_at) VALUES (?, ?, ?, ?, 1, 'active', NOW())",
            [$name, $email, $hash, $type]
        );
        return (int)$db->lastInsertId();
    };

    // Helper: create company profile for a user
    $createCompany = function($userId, $companyName, $industry, $size, $website, $description = null) use ($db) {
        $existing = $db->fetch("SELECT id FROM companies WHERE user_id = ?", [$userId]);
        if ($existing && isset($existing['id'])) { return (int)$existing['id']; }
        $db->execute(
            "INSERT INTO companies (user_id, company_name, industry, company_size, website, description) VALUES (?, ?, ?, ?, ?, ?)",
            [$userId, $companyName, $industry, $size, $website, $description]
        );
        return (int)$db->lastInsertId();
    };

    // Helper: create job posting
    $createJob = function($companyId, $title, $desc, $categoryId, $jobType, $expLevel, $location, $workType, $status = 'active', $salaryMin = null, $salaryMax = null, $deadline = null, $requirements = null) use ($db) {
        $existing = $db->fetch("SELECT id FROM jobs WHERE company_id = ? AND title = ?", [$companyId, $title]);
        if ($existing && isset($existing['id'])) { return (int)$existing['id']; }
        $db->execute(
            "INSERT INTO jobs (company_id, title, description, requirements, responsibilities, benefits, category_id, job_type, experience_level, salary_min, salary_max, currency, location, work_type, status, application_deadline, created_at) 
             VALUES (?, ?, ?, '', '', '', ?, ?, ?, ?, ?, 'USD', ?, ?, ?, ?, NOW())",
            [$companyId, $title, $desc, $categoryId, $jobType, $expLevel, $salaryMin, $salaryMax, $location, $workType, $status, $deadline]
        );
        $jobId = (int)$db->lastInsertId();
        if ($requirements !== null && $requirements !== '') {
            $db->execute("UPDATE jobs SET requirements = ? WHERE id = ?", [$requirements, $jobId]);
        }
        return $jobId;
    };

    // Helper: create an assessment with questions
    $createAssessment = function($companyUserId, $title, $description, $skillType, $timeLimit, $passingScore, $questions) use ($db) {
        // Get or create category
        $category = $db->fetch("SELECT id FROM assessment_categories WHERE skill_type = ?", [$skillType]);
        if (!$category) {
            $db->execute("INSERT INTO assessment_categories (name, skill_type) VALUES (?, ?)", [ucfirst($skillType) . ' Skills', $skillType]);
            $categoryId = (int)$db->lastInsertId();
        } else {
            $categoryId = (int)$category['id'];
        }

        // Create assessment
        $db->execute(
            "INSERT INTO assessments (title, description, category_id, total_questions, time_limit, passing_score, is_proctored, status, created_by, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, 0, 'active', ?, NOW())",
            [$title, $description, $categoryId, count($questions), $timeLimit, $passingScore, $companyUserId]
        );
        $assessmentId = (int)$db->lastInsertId();

        // Create questions and map them
        foreach ($questions as $qData) {
            $optionsJson = json_encode($qData['options']);
            $db->execute(
                "INSERT INTO assessment_questions (category_id, question_text, question_type, options, correct_answer, difficulty_level, created_at)
                 VALUES (?, ?, 'multiple_choice', ?, ?, 'medium', NOW())",
                [$categoryId, $qData['text'], $optionsJson, $qData['correct']]
            );
            $questionId = (int)$db->lastInsertId();

            $db->execute(
                "INSERT INTO assessment_questions_mapping (assessment_id, question_id, order_index) VALUES (?, ?, ?)",
                [$assessmentId, $questionId, $qData['order']]
            );
        }
        return $assessmentId;
    };

    // Helper: check if table exists (for job_assignments which may be missing in schema)
    $tableExists = function($table) use ($db) {
        $row = $db->fetch("SHOW TABLES LIKE '" . $table . "'");
        return $row !== false;
    };

    // Optionally create job_assignments table if missing (based on usage in APIs)
    if (!$tableExists('job_assignments')) {
        out("job_assignments table not found; creating minimal structure...");
        $db->execute(
            "CREATE TABLE job_assignments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                job_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                due_date DATE,
                created_by INT NOT NULL,
                assigned_to INT NULL,
                status ENUM('pending','submitted','approved','rejected') DEFAULT 'pending',
                submission TEXT NULL,
                submission_date TIMESTAMP NULL,
                feedback TEXT NULL,
                reviewed_at TIMESTAMP NULL,
                reviewed_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_job_id (job_id),
                FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
            )"
        );
    }

    // Begin transaction after any DDL operations
    $db->beginTransaction();

    // Create one demo job seeker (to assign tasks to)
    $jobSeekerUserId = $createUser('Alex Candidate', 'alex.candidate@example.com', 'Password123!', 'job_seeker');
    $db->execute(
        "INSERT INTO job_seekers (user_id, location, skills, experience_years) VALUES (?, 'Remote', JSON_ARRAY('PHP','JavaScript','SQL'), 3)",
        [$jobSeekerUserId]
    );

    // Create three companies and two jobs each
    $companies = [
        [
            'user' => ['Acme AI', 'acme.hr@example.com', 'Password123!', 'company'],
            'profile' => ['Acme AI', 'Technology', '51-200', 'https://acme.example.com', 'Building AI-powered solutions'],
            'jobs' => [
                ['Senior PHP Engineer', 'Own backend services powering our AI platform', 1, 'full_time', 'senior', 'San Francisco, CA', 'hybrid', 'active', 120000, 150000, date('Y-m-d', strtotime('+45 days')), 'PHP, Laravel, MySQL'],
                ['Frontend React Developer', 'Build delightful UI for job seekers and companies', 7, 'full_time', 'mid', 'Remote', 'remote', 'active', 90000, 120000, date('Y-m-d', strtotime('+30 days')), 'JavaScript, React, HTML, CSS']
            ]
        ],
        [
            'user' => ['HealthTech Labs', 'talent@healthtech.example.com', 'Password123!', 'company'],
            'profile' => ['HealthTech Labs', 'Healthcare', '201-500', 'https://healthtech.example.com', 'Digital health products at scale'],
            'jobs' => [
                ['Data Analyst', 'Analyze clinical data to derive actionable insights', 2, 'full_time', 'mid', 'Boston, MA', 'onsite', 'active', 80000, 105000, date('Y-m-d', strtotime('+40 days')), 'Python, SQL, Pandas'],
                ['Machine Learning Engineer', 'Develop models for patient risk prediction', 8, 'full_time', 'senior', 'Remote', 'remote', 'active', 130000, 160000, date('Y-m-d', strtotime('+50 days')), 'Python, TensorFlow/PyTorch, ML']
            ]
        ],
        [
            'user' => ['FinServe Co.', 'careers@finserve.example.com', 'Password123!', 'company'],
            'profile' => ['FinServe Co.', 'Finance', '1000+', 'https://finserve.example.com', 'Enterprise financial services'],
            'jobs' => [
                ['Product Manager', 'Lead cross-functional teams to build fintech products', 3, 'full_time', 'senior', 'New York, NY', 'hybrid', 'active', 140000, 170000, date('Y-m-d', strtotime('+60 days')), 'Agile, Roadmapping, Stakeholder Management'],
                ['DevOps Engineer', 'Scale and secure CI/CD for cloud workloads', 10, 'full_time', 'mid', 'Remote', 'remote', 'active', 110000, 140000, date('Y-m-d', strtotime('+35 days')), 'Docker, Kubernetes, CI/CD, AWS']
            ]
        ]
    ];

    // Add a dedicated company with Java and Python roles
    $companies[] = [
        'user' => ['CodeFactory Ltd', 'hr@codefactory.example.com', 'Password123!', 'company'],
        'profile' => ['CodeFactory Ltd', 'Technology', '51-200', 'https://codefactory.example.com', 'Product engineering and platform development'],
        'jobs' => [
            ['Java Developer', 'Build microservices and APIs in Java', 1, 'full_time', 'mid', 'Remote', 'remote', 'active', 100000, 130000, date('Y-m-d', strtotime('+40 days')), 'Java, Spring Boot, REST APIs, SQL'],
            ['Python Backend Developer', 'Develop backend services and data pipelines', 1, 'full_time', 'mid', 'Remote', 'remote', 'active', 95000, 125000, date('Y-m-d', strtotime('+40 days')), 'Python, Django/Flask, REST APIs, SQL']
        ]
    ];

    $createdCompanies = [];
    foreach ($companies as $c) {
        [$name, $email, $pwd, $type] = $c['user'];
        $companyUserId = $createUser($name . ' HR', $email, $pwd, $type);
        [$companyName, $industry, $size, $website, $desc] = $c['profile'];
        $companyId = $createCompany($companyUserId, $companyName, $industry, $size, $website, $desc);

        out("Created company: {$companyName} ({$email})");

        $jobIds = [];
        foreach ($c['jobs'] as $j) {
            [$title, $descJ, $catId, $jobType, $expLevel, $location, $workType, $status, $salMin, $salMax, $deadline, $requirements] = $j;
            $jobId = $createJob($companyId, $title, $descJ, $catId, $jobType, $expLevel, $location, $workType, $status, $salMin, $salMax, $deadline, $requirements);
            $jobIds[] = $jobId;
            out("  - Job: {$title} (#{$jobId})");

            // Create one demo assignment per job
            if ($tableExists('job_assignments')) {
                $due = date('Y-m-d', strtotime('+7 days'));
                $db->execute(
                    "INSERT INTO job_assignments (job_id, title, description, due_date, created_by, assigned_to, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
                    [$jobId, 'Complete a small task for ' . $title, 'Please deliver a concise solution and brief write-up.', $due, $companyUserId, $jobSeekerUserId]
                );
                $assignId = (int)$db->lastInsertId();
                out("    * Assignment seeded (#{$assignId}) assigned to job seeker");
            }
        }

        // Seed two assessments for each company (one soft skill, one technical)
        $softSkillQuestions = [
            [
                'text' => 'How do you handle tight deadlines?',
                'options' => [
                    ['text' => 'Prioritize tasks and communicate proactively', 'is_correct' => true],
                    ['text' => 'Work longer hours', 'is_correct' => false],
                    ['text' => 'Delegate to others', 'is_correct' => false]
                ],
                'correct' => 'Prioritize tasks and communicate proactively',
                'order' => 1
            ],
            [
                'text' => 'Describe a time you resolved a team conflict.\n','options' => [
                    ['text' => 'Facilitated a discussion to find a compromise', 'is_correct' => true],
                    ['text' => 'Escalated to a manager', 'is_correct' => false],
                    ['text' => 'Ignored it', 'is_correct' => false]
                ],
                'correct' => 'Facilitated a discussion to find a compromise',
                'order' => 2
            ]
        ];
        $techSkillQuestions = [
            [
                'text' => 'What is the difference between `let`, `const`, and `var` in JavaScript?',
                'options' => [
                    ['text' => '`var` is function-scoped, while `let` and `const` are block-scoped', 'is_correct' => true],
                    ['text' => 'There is no difference', 'is_correct' => false],
                    ['text' => '`const` can be reassigned', 'is_correct' => false]
                ],
                'correct' => '`var` is function-scoped, while `let` and `const` are block-scoped',
                'order' => 1
            ],
            [
                'text' => 'What is a primary key in a database?',
                'options' => [
                    ['text' => 'A unique identifier for a record', 'is_correct' => true],
                    ['text' => 'A foreign key from another table', 'is_correct' => false],
                    ['text' => 'An index to speed up queries', 'is_correct' => false]
                ],
                'correct' => 'A unique identifier for a record',
                'order' => 2
            ]
        ];

        $assess1Id = $createAssessment($companyUserId, 'Communication & Teamwork', 'Evaluates key soft skills for collaboration.', 'soft', 1200, 80, $softSkillQuestions);
        out("    - Assessment seeded: Communication & Teamwork (#{$assess1Id})");

        $assess2Id = $createAssessment($companyUserId, 'Basic Technical Screening', 'A brief screening for fundamental technical knowledge.', 'technical', 1800, 75, $techSkillQuestions);
        out("    - Assessment seeded: Basic Technical Screening (#{$assess2Id})");

        $createdCompanies[] = [
            'name' => $companyName,
            'email' => $email,
            'password' => $pwd,
            'user_id' => $companyUserId,
            'company_id' => $companyId,
            'jobs' => $jobIds
        ];
    }

    $db->commit();

    out("\nSeeding complete!");
    out("\nLogin with these demo accounts:");
    foreach ($createdCompanies as $info) {
        out("- Company: {$info['name']} | Email: {$info['email']} | Password: {$info['password']}");
    }
    out("- Job Seeker: Alex Candidate | Email: alex.candidate@example.com | Password: Password123!");

} catch (Exception $e) {
    try {
        if ($db->getConnection()->inTransaction()) { $db->rollBack(); }
    } catch (Throwable $t) {}
    out("ERROR: " . $e->getMessage());
    exit(1);
}

?>
