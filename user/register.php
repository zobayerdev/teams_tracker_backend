<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

$name              = '';
$phone             = '';
$email             = '';
$designation       = '';
$age               = '';
$nid               = '';
$dob               = '';
$father_name       = '';
$mother_name       = '';
$religion          = '';
$blood_group       = '';
$education         = '';
$present_address   = '';
$permanent_address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name              = trim($_POST['name'] ?? '');
    $phone             = trim($_POST['phone'] ?? '');
    $email             = trim($_POST['email'] ?? '');
    $designation       = trim($_POST['designation'] ?? 'Sales Representative');
    $password          = $_POST['password'] ?? '';
    $age               = !empty($_POST['age']) ? (int)$_POST['age'] : null;
    $nid               = trim($_POST['nid'] ?? '');
    $dob               = !empty($_POST['dob']) ? trim($_POST['dob']) : null;
    $father_name       = trim($_POST['father_name'] ?? '');
    $mother_name       = trim($_POST['mother_name'] ?? '');
    $religion          = trim($_POST['religion'] ?? '');
    $blood_group       = trim($_POST['blood_group'] ?? '');
    $education         = trim($_POST['education'] ?? '');
    $present_address   = trim($_POST['present_address'] ?? '');
    $permanent_address = trim($_POST['permanent_address'] ?? '');

    if (empty($name) || empty($phone) || empty($password)) {
        $error = 'Full Name, Mobile Number ebong Password mandatory.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimum 6 digit hote hobe.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Sothik email address din.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $check->execute([$phone]);
        if ($check->fetch()) {
            $error = 'Ei phone number diye agei account khola hoyeche.';
        } elseif (!empty($email)) {
            $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetch()) {
                $error = 'Ei email address diye agei account khola hoyeche.';
            }
        }

        if (empty($error)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $finalEmail = !empty($email) ? $email : null;

            $stmt = $pdo->prepare("
                INSERT INTO users (
                    name, email, phone, password, role, designation,
                    age, nid, dob, father_name, mother_name, religion, blood_group, education,
                    present_address, permanent_address, status, created_at
                ) VALUES (
                    ?, ?, ?, ?, 'employee', ?,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, 'active', NOW()
                )
            ");

            if ($stmt->execute([
                $name, $finalEmail, $phone, $hashedPassword, $designation,
                $age, $nid, $dob, $father_name, $mother_name, $religion, $blood_group, $education,
                $present_address, $permanent_address
            ])) {
                $success = 'Registration shofol hoyeche! Apnar account active ache, ekhon login korte parben.';
                $name = $phone = $email = $designation = $age = $nid = $dob = '';
                $father_name = $mother_name = $religion = $blood_group = $education = '';
                $present_address = $permanent_address = '';
            } else {
                $error = 'Registration korte somossha hoyeche. Abar cheshta korun.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Representative Registration - Field Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: system-ui, -apple-system, sans-serif; }
        .reg-card {
            border: 1px solid #e2e8f0;
            border-radius: 1.25rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        .form-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #475569;
        }
        .section-header {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 0.4rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="py-5">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card reg-card bg-white p-4 p-md-5">
                <div class="text-center mb-4">
                    <span class="badge bg-primary-subtle text-primary px-3 py-1.5 rounded-pill mb-2 fw-semibold">Team Track Portal</span>
                    <h3 class="fw-bold text-dark mb-1">Field Representative Registration</h3>
                    <p class="text-muted small">Submit your biodata and account details for administrative approval</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2.5 small rounded-3 d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success py-2.5 small rounded-3 d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-check-circle-fill"></i>
                        <div><?= htmlspecialchars($success) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <!-- 1. Account & Contact -->
                    <div class="section-header d-flex align-items-center gap-2">
                        <i class="bi bi-shield-lock-fill text-primary"></i> Account & Contact Details
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Nayem Ahmed" value="<?= htmlspecialchars($name) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" class="form-control" placeholder="01xxxxxxxxx" value="<?= htmlspecialchars($phone) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address <span class="text-muted fw-normal">(Optional)</span></label>
                            <input type="email" name="email" class="form-control" placeholder="name@company.com" value="<?= htmlspecialchars($email) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" minlength="6" required>
                        </div>
                    </div>

                    <!-- 2. Personal Information -->
                    <div class="section-header d-flex align-items-center gap-2">
                        <i class="bi bi-person-lines-fill text-primary"></i> Personal Biodata & Identity
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" class="form-control" placeholder="e.g. 26" value="<?= htmlspecialchars($age) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($dob) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">NID Number</label>
                            <input type="text" name="nid" class="form-control" placeholder="NID number" value="<?= htmlspecialchars($nid) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Father's Name</label>
                            <input type="text" name="father_name" class="form-control" placeholder="Father's full name" value="<?= htmlspecialchars($father_name) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mother's Name</label>
                            <input type="text" name="mother_name" class="form-control" placeholder="Mother's full name" value="<?= htmlspecialchars($mother_name) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Blood Group</label>
                            <select name="blood_group" class="form-select">
                                <option value="">-- Select --</option>
                                <?php foreach (['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $bg): ?>
                                    <option value="<?= $bg ?>" <?= ($blood_group === $bg) ? 'selected' : '' ?>><?= $bg ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Religion</label>
                            <select name="religion" class="form-select">
                                <option value="">-- Select --</option>
                                <?php foreach (['Islam', 'Hinduism', 'Christianity', 'Buddhism', 'Other'] as $rel): ?>
                                    <option value="<?= $rel ?>" <?= ($religion === $rel) ? 'selected' : '' ?>><?= $rel ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Education</label>
                            <input type="text" name="education" class="form-control" placeholder="e.g. BBA / HSC" value="<?= htmlspecialchars($education) ?>">
                        </div>
                    </div>

                    <!-- 3. Address -->
                    <div class="section-header d-flex align-items-center gap-2">
                        <i class="bi bi-geo-alt-fill text-primary"></i> Address Details
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Present Address</label>
                            <textarea name="present_address" class="form-control" rows="2" placeholder="Current living address"><?= htmlspecialchars($present_address) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Permanent Address</label>
                            <textarea name="permanent_address" class="form-control" rows="2" placeholder="Permanent home address"><?= htmlspecialchars($permanent_address) ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm">
                        <i class="bi bi-person-check-fill me-1"></i> Register Account
                    </button>
                </form>

                <div class="text-center mt-4 pt-2 border-top">
                    <small class="text-muted">Already registered and approved? <a href="login.php" class="text-decoration-none fw-semibold">Sign In Here</a></small>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
