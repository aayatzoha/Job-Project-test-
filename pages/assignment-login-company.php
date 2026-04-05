<?php
session_start();
// If already logged in as company, go to target or company dashboard
if (isset($_SESSION['user_id']) && ($_SESSION['user_type'] ?? '') === 'company') {
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '../dashboard/company.php';
    header('Location: ' . $redirect);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Assignment Login</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .login-container { max-width: 420px; margin: 60px auto; padding: 24px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; }
        .login-title { font-size: 1.25rem; margin-bottom: 16px; }
        .form-group { margin-bottom: 12px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; }
        input { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; }
        .btn { width: 100%; padding: 10px; margin-top: 12px; background: #1f6feb; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
        .error { color: #b91c1c; margin-top: 8px; }
        .hint { font-size: 0.9rem; color: #4b5563; margin-top: 8px; }
    </style>
    <script>
        async function loginCompany(e) {
            e.preventDefault();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const errorBox = document.getElementById('error');
            errorBox.textContent = '';

            if (!email || !password) {
                errorBox.textContent = 'Please enter email and password.';
                return;
            }

            const params = new URLSearchParams(window.location.search);
            const redirect = params.get('redirect') || '../dashboard/company.php';

            try {
                const res = await fetch('../api/auth/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ email, password, user_type: 'company' })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = redirect;
                } else if (data.needs_verification) {
                    window.location.href = data.redirect_url || '../verify_email.php';
                } else {
                    errorBox.textContent = data.message || 'Login failed.';
                }
            } catch (err) {
                console.error(err);
                errorBox.textContent = 'Network error. Please try again.';
            }
        }
    </script>
    </head>
<body>
    <div class="login-container">
        <div class="login-title">Company Login to Create Assignments</div>
        <form onsubmit="loginCompany(event)">
            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" type="email" placeholder="company@example.com" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" type="password" placeholder="••••••••" required>
            </div>
            <button class="btn" type="submit">Sign In</button>
            <div id="error" class="error"></div>
            <div class="hint">You will be redirected back to the assignment creation page after login.</div>
        </form>
    </div>
</body>
</html>

