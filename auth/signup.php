<?php
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

$name  = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1");
        $stmt->execute([$email, $phone]);
        if ($stmt->fetch()) {
            $error = 'An account with this Email or Phone is already registered.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $insert = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
            if ($insert->execute([$name, $email, $phone, $hashedPassword])) {
                $success = 'Admin account created successfully! You can login now.';
                $name = $email = $phone = '';
            } else {
                $error = 'Failed to register admin account. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration - Teams Locator Management</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --nav-bg: #0f172a;
            --accent-blue: #0ea5e9;
            --saas-border: #e2e8f0;
            --saas-text-dark: #0f172a;
            --saas-text-muted: #64748b;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
        }

        /* Subtle Background Glow */
        body::before {
            content: '';
            position: absolute;
            top: 10%;
            left: 15%;
            width: 340px;
            height: 340px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.15) 0%, transparent 70%);
            pointer-events: none;
        }

        body::after {
            content: '';
            position: absolute;
            bottom: 10%;
            right: 15%;
            width: 360px;
            height: 360px;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.12) 0%, transparent 70%);
            pointer-events: none;
        }

        .auth-card {
            background: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 1.35rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            position: relative;
            z-index: 10;
        }

        .brand-badge {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
            color: #ffffff;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 8px 20px rgba(14, 165, 233, 0.3);
            margin: 0 auto 1.25rem;
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: 0.4rem;
        }

        .input-group-text {
            background-color: #f8fafc;
            border-color: #e2e8f0;
            color: #64748b;
            border-radius: 0.75rem 0 0 0.75rem;
        }

        .form-control {
            border-color: #e2e8f0;
            padding: 0.7rem 0.95rem;
            font-size: 0.95rem;
            color: var(--saas-text-dark);
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.12);
        }

        .input-group > .form-control:not(:first-child) {
            border-radius: 0 0.75rem 0.75rem 0;
        }

        .input-group > .password-input {
            border-radius: 0 !important;
        }

        .toggle-btn {
            border-color: #e2e8f0;
            background-color: #f8fafc;
            color: #64748b;
            border-radius: 0 0.75rem 0.75rem 0;
            transition: all 0.2s ease;
        }

        .toggle-btn:hover {
            background-color: #f1f5f9;
            color: var(--saas-text-dark);
        }

        .btn-register {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            font-weight: 600;
            border: none;
            padding: 0.75rem 1.25rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.25);
            transition: all 0.2s ease;
        }

        .btn-register:hover {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.35);
        }

        .brand-subline {
            font-size: 0.82rem;
            color: var(--saas-text-muted);
        }

        .footer-note {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
        }

        @media (max-width: 575.98px) {
            .auth-card {
                padding: 1.5rem !important;
                border-radius: 1.15rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-5 col-lg-6 col-md-8 col-sm-11">

            <!-- Card Box -->
            <div class="auth-card p-4 p-md-5">
                
                <!-- Logo & Brand Header -->
                <div class="text-center mb-4">
                    <div class="brand-badge">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1" style="letter-spacing: -0.02em;">Teams Locator Management</h4>
                    <p class="brand-subline mb-0">Create Administrator Console Account</p>
                </div>

                <!-- Error Alert -->
                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 border-0 py-2.5 px-3 mb-3 rounded-3 small" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-6 flex-shrink-0"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Success Alert -->
                <?php if ($success): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2 border-0 py-2.5 px-3 mb-3 rounded-3 small" role="alert">
                        <i class="bi bi-check-circle-fill fs-6 flex-shrink-0"></i>
                        <div><?= htmlspecialchars($success) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Registration Form -->
                <form method="POST" autocomplete="off" id="registerForm">
                    
                    <!-- Full Name -->
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" 
                                   name="name" 
                                   class="form-control" 
                                   placeholder="e.g. John Doe" 
                                   value="<?= htmlspecialchars($name) ?>" 
                                   required 
                                   autofocus>
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" 
                                   name="email" 
                                   class="form-control" 
                                   placeholder="admin@company.com" 
                                   value="<?= htmlspecialchars($email) ?>" 
                                   required>
                        </div>
                    </div>

                    <!-- Phone Number -->
                    <div class="mb-3">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="tel" 
                                   name="phone" 
                                   class="form-control" 
                                   placeholder="e.g. 017xxxxxxxx" 
                                   value="<?= htmlspecialchars($phone) ?>" 
                                   required>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0">Password <span class="text-danger">*</span></label>
                            <small class="text-muted" style="font-size: 0.72rem;">Min. 6 chars</small>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                            <input type="password" 
                                   name="password" 
                                   id="passwordField" 
                                   class="form-control password-input" 
                                   placeholder="Set administrative password" 
                                   minlength="6" 
                                   required>
                            <button class="btn toggle-btn border border-start-0" type="button" id="togglePasswordBtn">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-register w-100 d-inline-flex align-items-center justify-content-center gap-2" id="submitBtn">
                        <i class="bi bi-person-check-fill"></i>
                        <span>Create Admin Account</span>
                    </button>
                </form>

                <!-- Footer Link -->
                <div class="text-center mt-4 pt-2 border-top border-light-subtle">
                    <p class="small text-muted mb-0">
                        Already have an admin account? 
                        <a href="login.php" class="text-primary text-decoration-none fw-semibold">Sign In here</a>
                    </p>
                </div>

            </div>

            <!-- Bottom Copyright Tag -->
            <div class="text-center mt-3">
                <span class="footer-note">&copy; <?= date('Y') ?> Teams Locator Management. All rights reserved.</span>
            </div>

        </div>
    </div>
</div>

<script>
    // Password Visibility Toggle
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const pwdInput = document.getElementById('passwordField');
    const toggleIcon = document.getElementById('toggleIcon');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const isPassword = pwdInput.getAttribute('type') === 'password';
            pwdInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleIcon.classList.toggle('bi-eye', !isPassword);
            toggleIcon.classList.toggle('bi-eye-slash', isPassword);
        });
    }

    // Submit Loading Feedback
    const form = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating Account...';
    });
</script>

</body>
</html>