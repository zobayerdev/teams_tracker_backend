<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = 'Email/Phone ebong Password mandatory.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR phone = ?) LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'pending') {
                $error = 'Apnar account ti ekhono admin approve kore ni. Opekkha korun.';
            } elseif ($user['status'] === 'inactive') {
                $error = 'Account ti suspend ba deactivate kora hoyeche.';
            } else {
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['user_name']   = $user['name'];
                $_SESSION['role']        = $user['role'];
                $_SESSION['designation'] = $user['designation'];
                
                header("Location: ../index.php");
                exit;
            }
        } else {
            $error = 'Vul identifier ba password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>Employee Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; }
        .login-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
        }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card login-card bg-white p-4">
                <div class="text-center mb-4">
                    <h4 class="fw-bold text-dark">Employee Login</h4>
                    <p class="text-muted small">Sign in to start field tracking</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Email or Phone</label>
                        <input type="text" name="identifier" class="form-control" placeholder="Email / Phone" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 py-2 fw-semibold">Sign In</button>
                </form>

                <div class="text-center mt-3">
                    <small class="text-muted">Don't have an account? <a href="register.php" class="text-decoration-none">Register</a></small>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>