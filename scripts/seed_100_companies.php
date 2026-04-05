<?php
/**
 * Seed 500 Companies with Jobs and Assignments (Camera Access Enabled)
 * Usage: php scripts/seed_100_companies.php
 * 
 * Creates:
 * - 500 companies
 * - 3-6 jobs per company (1,500-3,000 total jobs)
 * - 1 assignment per job (all with camera access enabled)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function out($msg) { 
    echo $msg . "\n"; 
    flush();
}

out("==========================================");
out("Seeding 500 Companies with Jobs & Assignments");
out("==========================================\n");

try {
    // Helper: create user
    $createUser = function($name, $email, $password, $type) use ($db) {
        $existing = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing && isset($existing['id'])) { 
            return (int)$existing['id']; 
        }
        $hash = hashPassword($password);
        $db->execute(
            "INSERT INTO users (name, email, password_hash, user_type, email_verified, status, created_at) 
             VALUES (?, ?, ?, ?, 1, 'active', NOW())",
            [$name, $email, $hash, $type]
        );
        return (int)$db->lastInsertId();
    };

    // Helper: create company profile
    $createCompany = function($userId, $companyName, $industry, $size, $website, $description = null) use ($db) {
        $existing = $db->fetch("SELECT id FROM companies WHERE user_id = ?", [$userId]);
        if ($existing && isset($existing['id'])) { 
            return (int)$existing['id']; 
        }
        $db->execute(
            "INSERT INTO companies (user_id, company_name, industry, company_size, website, description, company_type) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$userId, $companyName, $industry, $size, $website, $description, 'medium_business']
        );
        return (int)$db->lastInsertId();
    };

    // Helper: create job posting
    $createJob = function($companyId, $title, $desc, $requirements, $responsibilities, $benefits, 
                          $categoryId, $jobType, $expLevel, $location, $workType, $salaryMin, $salaryMax, $deadline) use ($db) {
        $db->execute(
            "INSERT INTO jobs (company_id, title, description, requirements, responsibilities, benefits, 
                              category_id, job_type, experience_level, salary_min, salary_max, currency, 
                              location, work_type, status, application_deadline, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'USD', ?, ?, 'active', ?, NOW())",
            [$companyId, $title, $desc, $requirements, $responsibilities, $benefits, $categoryId, 
             $jobType, $expLevel, $salaryMin, $salaryMax, $location, $workType, $deadline]
        );
        return (int)$db->lastInsertId();
    };

    // Helper: create assignment with camera access
    $createAssignment = function($jobId, $title, $description, $dueDate, $createdBy, $requiresCamera = true) use ($db) {
        $db->execute(
            "INSERT INTO assignments (job_id, title, description, due_date, created_by, requires_camera, status, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
            [$jobId, $title, $description, $dueDate, $createdBy, $requiresCamera ? 1 : 0]
        );
        $assignmentId = (int)$db->lastInsertId();
        
        // Add some questions to the assignment
        $questions = [
            ['text' => 'Please explain your approach to solving this problem.', 'type' => 'text'],
            ['text' => 'What challenges do you anticipate?', 'type' => 'text'],
            ['text' => 'Upload your solution file.', 'type' => 'file_upload']
        ];
        
        foreach ($questions as $index => $q) {
            $db->execute(
                "INSERT INTO assignment_questions (assignment_id, question_text, question_type, order_index) 
                 VALUES (?, ?, ?, ?)",
                [$assignmentId, $q['text'], $q['type'], $index]
            );
        }
        
        return $assignmentId;
    };

    // Get or create job categories
    $categories = [];
    $categoryNames = ['Technology', 'Healthcare', 'Finance', 'Education', 'Marketing', 'Sales', 'Engineering', 'Design'];
    foreach ($categoryNames as $catName) {
        $cat = $db->fetch("SELECT id FROM job_categories WHERE name = ?", [$catName]);
        if ($cat) {
            $categories[] = (int)$cat['id'];
        } else {
            $db->execute("INSERT INTO job_categories (name, description) VALUES (?, ?)", 
                        [$catName, "$catName related positions"]);
            $categories[] = (int)$db->lastInsertId();
        }
    }

    // Industries and company data
    $industries = ['Technology', 'Healthcare', 'Finance', 'Education', 'Marketing', 'E-commerce', 'Manufacturing', 'Consulting', 'Real Estate', 'Media'];
    $companySizes = ['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'];
    $jobTypes = ['full_time', 'part_time', 'contract', 'internship'];
    $experienceLevels = ['entry', 'mid', 'senior'];
    $workTypes = ['remote', 'onsite', 'hybrid'];
    $locations = ['San Francisco, CA', 'New York, NY', 'Austin, TX', 'Seattle, WA', 'Boston, MA', 'Chicago, IL', 'Los Angeles, CA', 'Remote'];

    // Job titles and descriptions - expanded list
    $jobTemplates = [
        ['Software Engineer', 'Develop and maintain software applications', '3+ years experience in software development', 'Write clean, maintainable code', 'Health insurance, 401k, flexible hours'],
        ['Senior Software Engineer', 'Lead development of complex software systems', '5+ years experience, strong technical leadership', 'Architect solutions, mentor junior developers', 'Top tier salary, equity, unlimited PTO'],
        ['Full Stack Developer', 'Build end-to-end web applications', '3+ years full stack development', 'Develop frontend and backend features', 'Remote work, learning stipend'],
        ['Backend Developer', 'Design and implement server-side solutions', '3+ years backend development experience', 'Build APIs and microservices', 'Competitive salary, health benefits'],
        ['Frontend Developer', 'Create engaging user interfaces', '2+ years frontend development', 'Build responsive web applications', 'Flexible hours, creative freedom'],
        ['Data Analyst', 'Analyze data to provide business insights', '2+ years in data analysis', 'Create reports and dashboards', 'Competitive salary, remote work'],
        ['Data Scientist', 'Build predictive models and ML solutions', '4+ years data science experience', 'Develop ML models, analyze patterns', 'High salary, research opportunities'],
        ['Data Engineer', 'Build and maintain data pipelines', '3+ years data engineering', 'Design ETL processes, optimize databases', 'Remote work, cutting-edge tech'],
        ['Product Manager', 'Lead product development initiatives', '5+ years product management experience', 'Define product roadmap', 'Stock options, great benefits'],
        ['Senior Product Manager', 'Drive strategic product decisions', '7+ years product management', 'Lead cross-functional teams', 'Executive compensation, equity'],
        ['UX Designer', 'Design user-friendly interfaces', '3+ years UX/UI design experience', 'Create wireframes and prototypes', 'Creative environment, flexible schedule'],
        ['UI Designer', 'Create beautiful visual designs', '2+ years UI design experience', 'Design interfaces and components', 'Design-focused culture'],
        ['DevOps Engineer', 'Manage infrastructure and deployments', '4+ years DevOps experience', 'Automate CI/CD pipelines', 'Remote work, learning budget'],
        ['Site Reliability Engineer', 'Ensure system reliability and performance', '5+ years SRE experience', 'Monitor systems, prevent outages', 'High salary, on-call compensation'],
        ['Cloud Architect', 'Design cloud infrastructure solutions', '6+ years cloud architecture', 'Architect scalable cloud systems', 'Top compensation, remote'],
        ['Marketing Manager', 'Develop and execute marketing strategies', '3+ years marketing experience', 'Manage campaigns and budgets', 'Performance bonuses'],
        ['Digital Marketing Specialist', 'Execute digital marketing campaigns', '2+ years digital marketing', 'Run ads, optimize campaigns', 'Growth opportunities'],
        ['Content Marketing Manager', 'Create and manage content strategy', '3+ years content marketing', 'Write content, manage editorial calendar', 'Creative freedom'],
        ['Sales Representative', 'Build relationships with clients', '2+ years sales experience', 'Meet sales targets', 'Commission based, uncapped earnings'],
        ['Sales Manager', 'Lead sales team and strategy', '5+ years sales experience', 'Manage team, develop strategy', 'High commission, leadership role'],
        ['Account Executive', 'Manage key client relationships', '4+ years account management', 'Grow accounts, build relationships', 'Base + commission'],
        ['Business Analyst', 'Analyze business processes and requirements', '3+ years business analysis', 'Document requirements and processes', 'Career growth opportunities'],
        ['Senior Business Analyst', 'Lead business analysis initiatives', '5+ years business analysis', 'Lead requirements gathering', 'Senior role, high impact'],
        ['Project Manager', 'Manage projects from start to finish', '4+ years project management', 'Plan, execute, and deliver projects', 'PMP preferred, great benefits'],
        ['Scrum Master', 'Facilitate agile development processes', '3+ years agile experience', 'Facilitate ceremonies, remove blockers', 'Agile environment'],
        ['QA Engineer', 'Ensure software quality', '2+ years QA experience', 'Write tests, find bugs', 'Quality-focused culture'],
        ['QA Automation Engineer', 'Automate testing processes', '3+ years automation experience', 'Build test automation frameworks', 'Technical growth'],
        ['Security Engineer', 'Protect systems and data', '4+ years security experience', 'Implement security measures', 'High demand, competitive salary'],
        ['Network Engineer', 'Design and maintain networks', '3+ years networking experience', 'Configure networks, troubleshoot', 'Stable role, good benefits'],
        ['Database Administrator', 'Manage database systems', '4+ years DBA experience', 'Optimize databases, ensure availability', 'Critical role, good pay'],
        ['System Administrator', 'Maintain IT infrastructure', '3+ years sysadmin experience', 'Manage servers and systems', 'Stable career path'],
        ['Technical Writer', 'Create technical documentation', '2+ years technical writing', 'Write docs, create tutorials', 'Remote-friendly role'],
        ['Customer Success Manager', 'Ensure customer satisfaction', '3+ years customer success', 'Onboard customers, drive adoption', 'Relationship-focused'],
        ['Support Engineer', 'Help customers with technical issues', '2+ years support experience', 'Troubleshoot issues, provide solutions', 'Entry-level friendly'],
        ['HR Manager', 'Manage human resources', '5+ years HR experience', 'Recruit, develop talent', 'People-focused role'],
        ['Recruiter', 'Find and attract talent', '2+ years recruiting experience', 'Source candidates, conduct interviews', 'Commission opportunities'],
        ['Financial Analyst', 'Analyze financial data', '3+ years finance experience', 'Create financial models, reports', 'Analytical role'],
        ['Operations Manager', 'Optimize business operations', '5+ years operations experience', 'Improve processes, manage teams', 'Leadership role'],
        ['Business Development Manager', 'Develop new business opportunities', '4+ years BD experience', 'Identify opportunities, build partnerships', 'Growth-focused'],
        ['Customer Support Specialist', 'Help customers resolve issues', '1+ years support experience', 'Answer questions, solve problems', 'Entry-level friendly']
    ];

    $db->beginTransaction();

    $created = 0;
    $totalJobs = 0;
    $totalAssignments = 0;

    for ($i = 1; $i <= 500; $i++) {
        // Generate company data
        $industry = $industries[array_rand($industries)];
        $companyName = $industry . ' Corp ' . $i;
        $email = 'company' . $i . '@example.com';
        $password = 'Company' . $i . '!2024';
        $size = $companySizes[array_rand($companySizes)];
        $website = 'https://' . strtolower(str_replace(' ', '', $companyName)) . '.com';
        $description = "Leading $industry company providing innovative solutions.";

        // Create user and company
        $companyUserId = $createUser($companyName . ' HR', $email, $password, 'company');
        $companyId = $createCompany($companyUserId, $companyName, $industry, $size, $website, $description);

        // Create 3-6 jobs per company
        $numJobs = rand(3, 6);
        $jobIds = [];

        for ($j = 0; $j < $numJobs; $j++) {
            $template = $jobTemplates[array_rand($jobTemplates)];
            $jobTitle = $template[0] . ' - ' . $industry;
            $jobDesc = $template[1] . ' in the ' . $industry . ' sector.';
            $requirements = $template[2];
            $responsibilities = $template[3];
            $benefits = $template[4];
            $categoryId = $categories[array_rand($categories)];
            $jobType = $jobTypes[array_rand($jobTypes)];
            $expLevel = $experienceLevels[array_rand($experienceLevels)];
            $location = $locations[array_rand($locations)];
            $workType = $workTypes[array_rand($workTypes)];
            $salaryMin = rand(50000, 100000);
            $salaryMax = $salaryMin + rand(20000, 50000);
            $deadline = date('Y-m-d', strtotime('+' . rand(30, 90) . ' days'));

            $jobId = $createJob($companyId, $jobTitle, $jobDesc, $requirements, $responsibilities, 
                               $benefits, $categoryId, $jobType, $expLevel, $location, $workType, 
                               $salaryMin, $salaryMax, $deadline);
            $jobIds[] = $jobId;
            $totalJobs++;

            // Create assignment with camera access for each job
            $assignmentTitle = 'Technical Assessment - ' . $jobTitle;
            $assignmentDesc = 'Complete this proctored assignment to demonstrate your skills. Camera access is required for monitoring purposes.';
            $dueDate = date('Y-m-d', strtotime('+' . rand(7, 14) . ' days'));

            $assignmentId = $createAssignment($jobId, $assignmentTitle, $assignmentDesc, 
                                             $dueDate, $companyUserId, true); // requires_camera = true
            $totalAssignments++;
        }

        $created++;
        
        if ($created % 10 == 0) {
            out("Progress: Created $created companies, $totalJobs jobs, $totalAssignments assignments...");
        }
    }

    $db->commit();

    out("\n==========================================");
    out("Seeding Complete!");
    out("==========================================");
    out("Created: $created companies");
    out("Created: $totalJobs jobs");
    out("Created: $totalAssignments assignments (all with camera access enabled)");
    out("\nAll companies use password format: Company{N}!2024");
    out("Example: Company1 uses password: Company1!2024");
    out("Email format: company{N}@example.com");
    out("Example: company1@example.com");
    out("\nAverage: " . round($totalJobs / $created, 2) . " jobs per company");
    out("Average: " . round($totalAssignments / $totalJobs, 2) . " assignments per job\n");

} catch (Exception $e) {
    try {
        if ($db->getConnection()->inTransaction()) { 
            $db->rollBack(); 
        }
    } catch (Throwable $t) {}
    
    out("ERROR: " . $e->getMessage());
    out("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

?>

