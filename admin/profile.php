<?php
require_once __DIR__ . '/includes/header.php';

$error = '';
$success = '';

$userId = $_SESSION['user_id'] ?? 0;

// বর্তমান অ্যাডমিনের তথ্য লোড করা
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$admin = $stmt->fetch();

if (!$admin) {
    header("Location: ../auth/login.php");
    exit;
}

// প্রোফাইল তথ্য আপডেট (ইমেইল বাদে নাম এবং ফোন নম্বর)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($name) || empty($phone)) {
        $error = 'Name and mobile number cannot be empty.';
    } else {
        // ফোন নম্বর অন্য কারো অ্যাকাউন্টে ব্যবহৃত হচ্ছে কিনা চেক
        $checkPhone = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
        $checkPhone->execute([$phone, $userId]);
        if ($checkPhone->fetch()) {
            $error = 'This mobile number is already assigned to another account.';
        } else {
            $updateStmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
            if ($updateStmt->execute([$name, $phone, $userId])) {
                $_SESSION['user_name'] = $name; // সেশনের নাম আপডেট
                $admin['name'] = $name;
                $admin['phone'] = $phone;
                $success = 'Profile personal details updated successfully.';
            } else {
                $error = 'Failed to update profile details.';
            }
        }
    }
}

// পাসওয়ার্ড পরিবর্তনের হ্যান্ডলার
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required.';
    } elseif (!password_verify($current_password, $admin['password'])) {
        $error = 'Current password does not match our records.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation password do not match.';
    } else {
        $hashed = password_hash($new_password, PASSWORD_BCRYPT);
        $pwdStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($pwdStmt->execute([$hashed, $userId])) {
            $admin['password'] = $hashed;
            $success = 'Security password updated successfully!';
        } else {
            $error = 'Unable to change password. Please try again.';
        }
    }
}
?>

<style>
    :root {
        --saas-border: #e2e8f0;
        --saas-bg-muted: #f8fafc;
        --saas-text-dark: #0f172a;
        --saas-text-muted: #64748b;
        --accent-gradient: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
    }

    .saas-card {
        background: #ffffff;
        border: 1px solid var(--saas-border);
        border-radius: 1.15rem;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        overflow: hidden;
    }

    .profile-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-radius: 1.15rem;
        padding: 2rem;
        color: #ffffff;
        position: relative;
        overflow: hidden;
    }
    .profile-hero::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(56, 189, 248, 0.15) 0%, transparent 70%);
        pointer-events: none;
    }

    .profile-avatar-large {
        width: 72px;
        height: 72px;
        border-radius: 20px;
        background: var(--accent-gradient);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 700;
        box-shadow: 0 8px 16px rgba(14, 165, 233, 0.3);
    }

    .form-label {
        font-size: 0.825rem;
        font-weight: 600;
        letter-spacing: 0.025em;
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
        border-radius: 0 0.75rem 0.75rem 0;
        padding: 0.65rem 0.9rem;
        font-size: 0.95rem;
        color: #1e293b;
    }

    .form-control:focus {
        border-color: #0284c7;
        box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.12);
    }

    .form-control:disabled, .form-control[readonly] {
        background-color: #f1f5f9;
        color: #64748b;
        cursor: not-allowed;
    }

    .btn-update {
        background: #0f172a;
        color: #ffffff;
        border: none;
        font-weight: 600;
        border-radius: 0.75rem;
        padding: 0.65rem 1.4rem;
        transition: all 0.2s ease;
    }
    .btn-update:hover {
        background: #1e293b;
        color: #ffffff;
        transform: translateY(-1px);
    }

    .btn-save-pwd {
        background: #0284c7;
        color: #ffffff;
        border: none;
        font-weight: 600;
        border-radius: 0.75rem;
        padding: 0.65rem 1.4rem;
        transition: all 0.2s ease;
    }
    .btn-save-pwd:hover {
        background: #0369a1;
        color: #ffffff;
        transform: translateY(-1px);
    }

    .badge-lock {
        font-size: 0.75rem;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #64748b;
        border-radius: 6px;
        padding: 2px 6px;
    }
</style>

<div class="container-fluid px-lg-4 py-3">

    <!-- Top Notifications -->
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 border-0 py-3 mb-4 rounded-3 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-2 border-0 py-3 mb-4 rounded-3 shadow-sm">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= htmlspecialchars($success) ?></div>
        </div>
    <?php endif; ?>

    <!-- Profile Hero Card -->
    <div class="profile-hero mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center gap-3 position-relative" style="z-index: 1;">
            <div class="profile-avatar-large">
                <?= strtoupper(substr($admin['name'], 0, 1)) ?>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h3 class="fw-bold mb-0 text-white"><?= htmlspecialchars($admin['name']) ?></h3>
                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3 py-1 text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                        <?= htmlspecialchars(strtoupper($admin['role'])) ?>
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-3 text-light opacity-75 small">
                    <span><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($admin['email']) ?></span>
                    <span><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($admin['phone']) ?></span>
                    <span><i class="bi bi-calendar3 me-1"></i>Joined <?= date('M Y', strtotime($admin['created_at'])) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Forms Row -->
    <div class="row g-4">
        
        <!-- Left: General Profile Info (No Email Change) -->
        <div class="col-lg-6">
            <div class="saas-card h-100">
                <div class="p-3 border-bottom border-light-subtle bg-white d-flex align-items-center gap-2">
                    <i class="bi bi-person-lines-fill text-primary fs-5"></i>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Personal Information</h6>
                        <small class="text-muted">Manage your profile display details</small>
                    </div>
                </div>

                <div class="p-4">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">

                        <!-- Full Name -->
                        <div class="mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($admin['name']) ?>" required>
                            </div>
                        </div>

                        <!-- Mobile Number -->
                        <div class="mb-3">
                            <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($admin['phone']) ?>" required>
                            </div>
                        </div>

                        <!-- Email (Disabled / Readonly) -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0">Email Address</label>
                                <span class="badge-lock"><i class="bi bi-lock-fill me-1"></i>Non-editable</span>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope-at"></i></span>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($admin['email']) ?>" readonly>
                            </div>
                            <small class="text-muted" style="font-size: 0.75rem;">Account email address is locked for system security identification.</small>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button type="submit" class="btn btn-update d-inline-flex align-items-center gap-2">
                                <i class="bi bi-check2"></i>
                                <span>Save Changes</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Security / Change Password -->
        <div class="col-lg-6">
            <div class="saas-card h-100">
                <div class="p-3 border-bottom border-light-subtle bg-white d-flex align-items-center gap-2">
                    <i class="bi bi-shield-lock text-info fs-5"></i>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Change Password</h6>
                        <small class="text-muted">Ensure your account uses a strong, unique password</small>
                    </div>
                </div>

                <div class="p-4">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <!-- Current Password -->
                        <div class="mb-3">
                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" name="current_password" id="curPwd" class="form-control" placeholder="••••••••" required>
                                <button class="btn btn-outline-secondary border-start-0" type="button" onclick="toggleField('curPwd', this)" style="border-radius: 0 0.75rem 0.75rem 0; border-color: #e2e8f0;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- New Password -->
                        <div class="mb-3">
                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-shield-plus"></i></span>
                                <input type="password" name="new_password" id="newPwd" class="form-control" placeholder="Min. 6 characters" minlength="6" required>
                                <button class="btn btn-outline-secondary border-start-0" type="button" onclick="toggleField('newPwd', this)" style="border-radius: 0 0.75rem 0.75rem 0; border-color: #e2e8f0;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Confirm New Password -->
                        <div class="mb-4">
                            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                                <input type="password" name="confirm_password" id="confPwd" class="form-control" placeholder="Repeat new password" minlength="6" required>
                                <button class="btn btn-outline-secondary border-start-0" type="button" onclick="toggleField('confPwd', this)" style="border-radius: 0 0.75rem 0.75rem 0; border-color: #e2e8f0;">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-2">
                            <button type="submit" class="btn btn-save-pwd d-inline-flex align-items-center gap-2">
                                <i class="bi bi-arrow-repeat"></i>
                                <span>Update Password</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
    // Password View Toggle Function
    function toggleField(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>