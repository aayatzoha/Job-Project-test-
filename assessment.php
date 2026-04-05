<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Require login and job seeker role
requireLogin();
$user_type = $_SESSION['user_type'] ?? $_SESSION['role'] ?? null;
if ($user_type !== 'job_seeker') {
    header('Location: dashboard/index.php');
    exit;
}

// Support two modes:
// - Take mode: /assessment.php?id=<assessment_id>
// - Results mode: /assessment.php?result=<user_assessment_id>
$assessment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$result_user_assessment_id = isset($_GET['result']) ? intval($_GET['result']) : 0;
$isResultsMode = $result_user_assessment_id > 0;

if (!$isResultsMode && $assessment_id <= 0) {
    header('Location: dashboard/job_seeker.php');
    exit;
}

// If results mode, load summary and answers
if ($isResultsMode) {
    try {
        $ua = $db->fetch(
            "SELECT ua.*, a.title, a.description, a.passing_score, a.total_questions, a.time_limit
             FROM user_assessments ua
             JOIN assessments a ON ua.assessment_id = a.id
             WHERE ua.id = ?",
            [$result_user_assessment_id]
        );
        if (!$ua) {
            $_SESSION['error'] = 'Assessment attempt not found';
            header('Location: dashboard/job_seeker.php');
            exit;
        }
        // Ownership check: only allow the owner to view
        if (($ua['user_id'] ?? 0) !== ($_SESSION['user_id'] ?? -1)) {
            $_SESSION['error'] = 'You do not have permission to view this result';
            header('Location: dashboard/job_seeker.php');
            exit;
        }
        $answers = $db->fetchAll(
            "SELECT uaa.*, aq.question_text, aq.question_type, aq.points
             FROM user_assessment_answers uaa
             JOIN assessment_questions aq ON uaa.question_id = aq.id
             WHERE uaa.user_assessment_id = ?
             ORDER BY aq.id",
            [$result_user_assessment_id]
        );
        // Expose for template
        $resultSummary = $ua;
        $resultAnswers = $answers;
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: dashboard/job_seeker.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .container { max-width: 960px; margin: 24px auto; padding: 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-top: 16px; }
        .question { border-bottom: 1px solid #eee; padding: 12px 0; }
        .question:last-child { border-bottom: none; }
        .options { margin-top: 8px; }
        .options label { display:block; margin: 6px 0; }
        .actions { display:flex; gap: 8px; margin-top: 16px; }
        .btn { padding: 10px 14px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .meta { display:flex; gap: 16px; color: #555; font-size: 0.95em; }
        .error { color: #b91c1c; }
        .success { color: #166534; }
        textarea.answer { width: 100%; min-height: 80px; }
        pre.code { background:#0f172a; color:#e2e8f0; padding:12px; border-radius:6px; overflow:auto; }
    </style>
    </head>
<body>
    <div class="container">
        <div class="card">
            <?php if ($isResultsMode): ?>
                <div id="header">
                    <h2>Assessment Result — <?php echo htmlspecialchars($resultSummary['title'] ?? ''); ?></h2>
                    <div class="meta">
                        <span><?php echo htmlspecialchars($resultSummary['description'] ?? ''); ?></span>
                        <span>
                            <?php
                                $minutes = isset($resultSummary['time_limit']) ? floor(((int)$resultSummary['time_limit']) / 60) : 0;
                                echo $minutes . ' minutes • ' . ($resultSummary['total_questions'] ?? 0) . ' questions';
                            ?>
                        </span>
                    </div>
                </div>
            <?php else: ?>
                <div id="header">
                    <h2 id="assessment-title">Loading assessment...</h2>
                    <div class="meta">
                        <span id="assessment-desc"></span>
                        <span id="assessment-meta"></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <?php if ($isResultsMode): ?>
                <?php
                    $passed = false;
                    $percentage = (float)($resultSummary['percentage_score'] ?? 0);
                    $passing = (float)($resultSummary['passing_score'] ?? 0);
                    $passed = $percentage >= $passing;
                ?>
                <div>
                    <h3>Summary</h3>
                    <p>
                        <strong>Score:</strong> <?php echo number_format($percentage, 2); ?>%
                        • <strong>Result:</strong> <?php echo $passed ? 'Passed' : 'Not Passed'; ?>
                        • <strong>Time Taken:</strong> <?php echo intval($resultSummary['time_taken'] ?? 0); ?>s
                    </p>
                </div>
                <div style="margin-top:12px;">
                    <h3>Answers</h3>
                    <?php if (!empty($resultAnswers)): ?>
                        <?php foreach ($resultAnswers as $idx => $ans): ?>
                            <div class="question">
                                <div><strong>Q<?php echo ($idx + 1); ?>.</strong> <?php echo htmlspecialchars($ans['question_text'] ?? ''); ?> <small>(<?php echo htmlspecialchars($ans['question_type'] ?? ''); ?>)</small></div>
                                <div style="margin-top:6px;">
                                    <strong>Your Answer:</strong> <?php echo nl2br(htmlspecialchars($ans['answer'] ?? '')); ?>
                                    • <strong>Points:</strong> <?php echo intval($ans['points'] ?? 0); ?>
                                    • <strong>Correct:</strong> <?php echo !empty($ans['is_correct']) ? 'Yes' : 'No'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No answers recorded.</p>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <button class="btn btn-secondary" onclick="window.location.href='dashboard/job_seeker.php#assessments'">Back to Assessments</button>
                </div>
            <?php else: ?>
                <div id="questions-container">
                    <p>Fetching questions...</p>
                </div>
                <div class="actions">
                    <button id="submit-all" class="btn btn-primary" disabled>Submit Assessment</button>
                    <button id="cancel" class="btn btn-secondary" onclick="history.back()">Cancel</button>
                    <span id="submit-msg"></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        <?php if (!$isResultsMode): ?>
        const assessmentId = <?php echo json_encode($assessment_id, JSON_UNESCAPED_SLASHES); ?>;
        let userAssessment = null;
        let questions = [];

        function safeText(str) {
            const div = document.createElement('div');
            div.textContent = String(str ?? '');
            return div.innerHTML;
        }

        function parseOptions(optionsJson) {
            try {
                if (!optionsJson) return [];
                const parsed = typeof optionsJson === 'string' ? JSON.parse(optionsJson) : optionsJson;
                if (Array.isArray(parsed)) {
                    return parsed.map(o => typeof o === 'string' ? o : (o.option_text || o.text || o.label || JSON.stringify(o)));
                }
                return Object.values(parsed);
            } catch (e) {
                console.warn('Failed to parse options JSON', e);
                return [];
            }
        }

        async function loadAssessment() {
            const qc = document.getElementById('questions-container');
            qc.innerHTML = '<p>Fetching questions...</p>';
            try {
                const res = await fetch(`/job/api/assessments/take.php?assessment_id=${assessmentId}`, { credentials: 'same-origin' });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || `Failed (${res.status})`);

                document.getElementById('assessment-title').innerHTML = safeText(data.assessment.title || 'Assessment');
                document.getElementById('assessment-desc').innerHTML = safeText(data.assessment.description || '');
                const minutes = Math.floor((data.assessment.time_limit || 0) / 60);
                document.getElementById('assessment-meta').textContent = `${minutes} minutes • ${data.assessment.total_questions} questions`;

                userAssessment = data.user_assessment;
                questions = data.questions || [];
                renderQuestions(questions);
                document.getElementById('submit-all').disabled = questions.length === 0;
            } catch (err) {
                console.error('Load assessment error', err);
                qc.innerHTML = `<p class="error">${safeText(err.message)}</p>`;
            }
        }

        function renderQuestions(qs) {
            const qc = document.getElementById('questions-container');
            if (!qs || qs.length === 0) {
                qc.innerHTML = '<p>No questions available.</p>';
                return;
            }

            let html = '';
            qs.forEach((q, idx) => {
                const qId = q.id;
                const type = q.question_type;
                html += `<div class="question" data-qid="${qId}" data-type="${type}">
                    <div><strong>Q${idx + 1}.</strong> ${safeText(q.question_text)}</div>`;

                const options = parseOptions(q.options);
                if (type === 'multiple_choice' || type === 'true_false') {
                    const opts = options.length ? options : (type === 'true_false' ? ['True', 'False'] : []);
                    html += `<div class="options">${opts.map((opt, i) => `
                        <label><input type="radio" name="q_${qId}" value="${safeText(opt)}"> ${safeText(opt)}</label>
                    `).join('')}</div>`;
                } else if (type === 'coding') {
                    html += `<div style="margin-top:8px;">
                        <label>Your code:</label>
                        <textarea class="answer" name="q_${qId}" placeholder="Write your solution..."></textarea>
                    </div>`;
                } else if (type === 'essay') {
                    html += `<div style="margin-top:8px;">
                        <label>Your answer:</label>
                        <textarea class="answer" name="q_${qId}" placeholder="Write your response...	extarea>`;
                    html += `</div>`;
                } else {
                    html += `<div style="margin-top:8px;">
                        <label>Your answer:</label>
                        <input class="answer" name="q_${qId}" type="text" placeholder="Enter your answer" style="width:100%;" />
                    </div>`;
                }

                html += `</div>`;
            });
            qc.innerHTML = html;
        }

        async function submitAll() {
            const msg = document.getElementById('submit-msg');
            msg.className = '';
            msg.textContent = '';
            const uaId = userAssessment?.id;
            if (!uaId) {
                msg.className = 'error';
                msg.textContent = 'Assessment session not initialized.';
                return;
            }

            try {
                const questionDivs = Array.from(document.querySelectorAll('.question'));
                for (const div of questionDivs) {
                    const qid = parseInt(div.getAttribute('data-qid'), 10);
                    const type = div.getAttribute('data-type');
                    let answer = '';
                    if (type === 'multiple_choice' || type === 'true_false') {
                        const selected = div.querySelector('input[type=radio]:checked');
                        answer = selected ? selected.value : '';
                    } else {
                        const field = div.querySelector('.answer');
                        answer = field ? field.value : '';
                    }

                    const payload = { 
                        action: 'submit_answer', 
                        user_assessment_id: uaId, 
                        question_id: qid, 
                        answer: answer, 
                        time_taken: 0 
                    };
                    const res = await fetch('/job/api/assessments/take.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || `Failed to submit answer for Q${qid}`);
                    }
                }

                const completeRes = await fetch('/job/api/assessments/take.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action: 'complete_assessment', user_assessment_id: uaId, proctoring_data: {} })
                });
                const completeData = await completeRes.json();
                if (!completeRes.ok || !completeData.success) {
                    throw new Error(completeData.message || 'Failed to complete assessment');
                }

                msg.className = 'success';
                const r = completeData.results || {};
                msg.textContent = `Completed! Score: ${r.percentage_score ?? '?'}% • ${r.passed ? 'Passed' : 'Not Passed'}`;
                alert(msg.textContent);
            } catch (err) {
                console.error('Submit assessment error', err);
                msg.className = 'error';
                msg.textContent = err.message;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadAssessment();
            document.getElementById('submit-all').addEventListener('click', submitAll);
        });
        <?php endif; ?>
    </script>
</body>
</html>
