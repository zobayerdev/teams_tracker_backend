<?php
require_once __DIR__ . '/includes/header.php';

$error = '';
$success = '';

// Active reporting officers fetch (Super Admin, Admin, Supervisor)
$officersStmt = $pdo->query("
    SELECT id, name, phone, role, designation 
    FROM users 
    WHERE role IN ('super_admin', 'admin', 'supervisor') AND status = 'active' 
    ORDER BY FIELD(role, 'super_admin', 'admin', 'supervisor'), name ASC
");
$officersList = $officersStmt->fetchAll();

// Group officers by role for optgroups
$officersByRole = [
    'super_admin' => [],
    'admin' => [],
    'supervisor' => []
];
foreach ($officersList as $off) {
    if (isset($officersByRole[$off['role']])) {
        $officersByRole[$off['role']][] = $off;
    }
}

// Variables for form sticky values
$name = '';
$phone = '';
$email = '';
$age = '';
$nid = '';
$dob = '';
$father_name = '';
$mother_name = '';
$religion = '';
$blood_group = '';
$education = '';
$present_address = '';
$permanent_address = '';
$designation = '';
$salary = '';
$role_type = 'employee';
$supervisor_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name              = trim($_POST['name'] ?? '');
    $phone             = trim($_POST['phone'] ?? '');
    $email             = trim($_POST['email'] ?? '');
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
    $designation       = trim($_POST['designation'] ?? '');
    $salary            = !empty($_POST['salary']) ? (float)$_POST['salary'] : 0.00;
    $role_type         = trim($_POST['role_type'] ?? 'employee');
    $supervisor_id     = !empty($_POST['supervisor_id']) ? (int)$_POST['supervisor_id'] : null;

    // Normalizing role
    if ($role_type === 'sales_representative') {
        $role_type = 'employee';
    }

    if (empty($name) || empty($phone) || empty($password) || empty($role_type)) {
        $error = 'Full Name, Mobile Number, Password, and Role are mandatory.';
    } elseif (!in_array($role_type, ['super_admin', 'admin', 'supervisor', 'employee'])) {
        $error = 'Invalid role type selected.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address format.';
    } else {
        // Phone duplicate check
        $checkPhone = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
        $checkPhone->execute([$phone]);
        if ($checkPhone->fetch()) {
            $error = 'An account with this Mobile Number already exists.';
        } elseif (!empty($email)) {
            // Email duplicate check
            $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetch()) {
                $error = 'An account with this Email address already exists.';
            }
        }

        if (empty($error)) {
            // Super Admin / Director is at the top of the hierarchy (cannot be assigned to anyone)
            if ($role_type === 'super_admin') {
                $supervisor_id = null;
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $finalEmail = !empty($email) ? $email : null;

            $stmt = $pdo->prepare("
                INSERT INTO users (
                    name, email, phone, password, role, designation,
                    age, nid, dob, father_name, mother_name, religion, blood_group, education,
                    present_address, permanent_address, salary, supervisor_id,
                    status, created_by, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    'active', ?, NOW()
                )
            ");

            if ($stmt->execute([
                $name, $finalEmail, $phone, $hashedPassword, $role_type, $designation,
                $age, $nid, $dob, $father_name, $mother_name, $religion, $blood_group, $education,
                $present_address, $permanent_address, $salary, $supervisor_id,
                $_SESSION['user_id'] ?? null
            ])) {
                $roleLabels = [
                    'super_admin' => 'Super Admin (Director)',
                    'admin'       => 'Admin',
                    'supervisor'  => 'Supervisor',
                    'employee'    => 'Sales Representative (SR)'
                ];
                $success = ($roleLabels[$role_type] ?? ucfirst($role_type)) . ' account has been successfully created with all profile details!';
                // Reset form values
                $name = $phone = $email = $age = $nid = $dob = '';
                $father_name = $mother_name = $religion = $blood_group = $education = '';
                $present_address = $permanent_address = $designation = '';
                $salary = '';
                $role_type = 'employee';
                $supervisor_id = '';
            } else {
                $error = 'Failed to create account. Please check your inputs and try again.';
            }
        }
    }
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
        --input-focus-border: #0ea5e9;
        --section-bg: #f8fafc;
        --border-color: #e2e8f0;
    }

    body {
        background-color: #f1f5f9;
    }

    .form-container-card {
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 1.25rem;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05), 0 8px 10px -6px rgba(15, 23, 42, 0.02);
        background: #ffffff;
        overflow: hidden;
    }

    .form-section-title {
        font-size: 0.95rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #f1f5f9;
        margin-bottom: 1.25rem;
    }

    .form-section-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: #475569;
        margin-bottom: 0.35rem;
    }

    .input-group-text {
        background-color: #f8fafc;
        border-color: #e2e8f0;
        color: #64748b;
        border-radius: 0.65rem 0 0 0.65rem;
        font-size: 0.95rem;
    }

    .form-control, .form-select {
        border-color: #e2e8f0;
        border-radius: 0 0.65rem 0.65rem 0;
        padding: 0.6rem 0.85rem;
        font-size: 0.92rem;
        color: #1e293b;
        transition: all 0.2s ease-in-out;
    }

    .form-control-single {
        border-radius: 0.65rem !important;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--input-focus-border);
        box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.15);
    }

    .btn-gradient {
        background: var(--primary-gradient);
        border: none;
        color: #ffffff;
        font-weight: 600;
        border-radius: 0.75rem;
        padding: 0.75rem 1.75rem;
        box-shadow: 0 4px 12px rgba(14, 165, 233, 0.25);
        transition: all 0.2s ease;
    }

    .btn-gradient:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(14, 165, 233, 0.35);
        color: #ffffff;
    }

    .btn-light-secondary {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        font-weight: 600;
        border-radius: 0.75rem;
        padding: 0.75rem 1.5rem;
        transition: all 0.2s ease;
    }

    .btn-light-secondary:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    .role-badge-preview {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.25rem 0.65rem;
        border-radius: 6px;
    }

    .hierarchy-hint-box {
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        font-size: 0.82rem;
        color: #475569;
    }
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-xl-10 col-lg-11 col-12">

            <!-- Card Start -->
            <div class="card form-container-card">
                
                <!-- Card Header -->
                <div class="p-4 p-md-4 border-bottom bg-white d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-section-icon bg-primary-subtle text-primary" style="width: 48px; height: 48px; font-size: 1.4rem;">
                            <i class="bi bi-person-vcard-fill"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-0 text-dark">Staff Registration & Enrollment</h4>
                            <p class="text-muted small mb-0">Create new staff profiles with complete biographical, organizational & payroll records</p>
                        </div>
                    </div>
                    <a href="employee_list.php" class="btn btn-outline-secondary btn-sm rounded-3 px-3 py-2 d-inline-flex align-items-center gap-1.5 align-self-start align-self-md-center">
                        <i class="bi bi-people"></i>
                        <span>View Staff Directory</span>
                    </a>
                </div>

                <!-- Card Body -->
                <div class="card-body p-4 p-md-5">

                    <!-- Notifications -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 border-0 py-3 mb-4 rounded-3 shadow-sm" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
                            <div class="fw-medium"><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success d-flex align-items-center gap-2 border-0 py-3 mb-4 rounded-3 shadow-sm" role="alert">
                            <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                            <div class="fw-medium"><?= htmlspecialchars($success) ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Registration Form -->
                    <form method="POST" id="createStaffForm" autocomplete="off">

                        <!-- ============================================== -->
                        <!-- 1. ROLE & REPORTING STRUCTURE (HIERARCHY)      -->
                        <!-- ============================================== -->
                        <div class="mb-5">
                            <div class="form-section-title">
                                <span class="form-section-icon bg-info-subtle text-info"><i class="bi bi-diagram-3-fill"></i></span>
                                <span>1. Role Type & Reporting Officer</span>
                            </div>

                            <div class="row g-3">
                                <!-- Role Selector Dropdown -->
                                <div class="col-md-6">
                                    <label class="form-label">Role Type <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                                        <select name="role_type" id="roleTypeSelect" class="form-select" required>
                                            <option value="employee" <?= ($role_type === 'employee') ? 'selected' : '' ?>>
                                                Sales Representative (Field Staff / SR)
                                            </option>
                                            <option value="supervisor" <?= ($role_type === 'supervisor') ? 'selected' : '' ?>>
                                                Supervisor
                                            </option>
                                            <option value="admin" <?= ($role_type === 'admin') ? 'selected' : '' ?>>
                                                Admin
                                            </option>
                                            <option value="super_admin" <?= ($role_type === 'super_admin') ? 'selected' : '' ?>>
                                                Super Admin (Director / Head of Org)
                                            </option>
                                        </select>
                                    </div>
                                    <small class="text-muted d-block mt-1">Select the operational role permissions for this user.</small>
                                </div>

                                <!-- Assign Officer / Reporting Structure -->
                                <div class="col-md-6" id="assignOfficerWrapper">
                                    <label class="form-label">Assign Reporting Officer <span class="text-muted fw-normal" id="officerOptionalLabel">(Reporting To)</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person-up"></i></span>
                                        <select name="supervisor_id" id="supervisorSelect" class="form-select">
                                            <option value="">-- No Direct Officer (Direct to Head) --</option>
                                            
                                            <!-- Super Admins Group -->
                                            <?php if (!empty($officersByRole['super_admin'])): ?>
                                                <optgroup label="👑 Super Admins / Directors">
                                                    <?php foreach ($officersByRole['super_admin'] as $off): ?>
                                                        <option value="<?= $off['id'] ?>" <?= ($supervisor_id == $off['id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($off['name']) ?> (<?= htmlspecialchars($off['phone']) ?>) - Super Admin
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>

                                            <!-- Admins Group -->
                                            <?php if (!empty($officersByRole['admin'])): ?>
                                                <optgroup label="⭐ Admins / Management">
                                                    <?php foreach ($officersByRole['admin'] as $off): ?>
                                                        <option value="<?= $off['id'] ?>" <?= ($supervisor_id == $off['id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($off['name']) ?> (<?= htmlspecialchars($off['phone']) ?>) - Admin
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>

                                            <!-- Supervisors Group -->
                                            <?php if (!empty($officersByRole['supervisor'])): ?>
                                                <optgroup label="🛡️ Field Supervisors">
                                                    <?php foreach ($officersByRole['supervisor'] as $off): ?>
                                                        <option value="<?= $off['id'] ?>" <?= ($supervisor_id == $off['id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($off['name']) ?> (<?= htmlspecialchars($off['phone']) ?>) - Supervisor
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <small class="text-muted d-block mt-1" id="officerHelpText">
                                        Sales Representatives report to Supervisors or Admins.
                                    </small>
                                </div>
                            </div>

                            <!-- Organizational Hierarchy Quick Guide -->
                            <div class="hierarchy-hint-box mt-3 d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                                <div>
                                    <strong>Hierarchy Structure:</strong> 
                                    <span class="text-primary fw-semibold">Super Admin (Director)</span> ➔ 
                                    <span class="text-indigo fw-semibold">Admin</span> ➔ 
                                    <span class="text-info fw-semibold">Supervisor</span> ➔ 
                                    <span class="text-success fw-semibold">Sales Representative</span>.
                                </div>
                            </div>
                        </div>

                        <!-- ============================================== -->
                        <!-- 2. PERSONAL BIODATA & IDENTITY                 -->
                        <!-- ============================================== -->
                        <div class="mb-5">
                            <div class="form-section-title">
                                <span class="form-section-icon bg-primary-subtle text-primary"><i class="bi bi-person-lines-fill"></i></span>
                                <span>2. Personal Biodata & National Identity</span>
                            </div>

                            <div class="row g-3 mb-3">
                                <!-- Full Name -->
                                <div class="col-md-6">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" name="name" class="form-control" placeholder="e.g. Mohammad Rahim" value="<?= htmlspecialchars($name) ?>" required>
                                    </div>
                                </div>

                                <!-- NID Number -->
                                <div class="col-md-6">
                                    <label class="form-label">National ID (NID) Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-card-heading"></i></span>
                                        <input type="text" name="nid" class="form-control" placeholder="e.g. 19902692512345678" value="<?= htmlspecialchars($nid) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <!-- Age -->
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label">Age</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-calendar2-heart"></i></span>
                                        <input type="number" name="age" class="form-control" placeholder="e.g. 28" min="16" max="90" value="<?= htmlspecialchars($age) ?>">
                                    </div>
                                </div>

                                <!-- Date of Birth (DOB) -->
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label">Date of Birth (DOB)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-calendar-date"></i></span>
                                        <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($dob) ?>">
                                    </div>
                                </div>

                                <!-- Blood Group -->
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label">Blood Group</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-droplet-half text-danger"></i></span>
                                        <select name="blood_group" class="form-select">
                                            <option value="">-- Select --</option>
                                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $bg): ?>
                                                <option value="<?= $bg ?>" <?= ($blood_group === $bg) ? 'selected' : '' ?>><?= $bg ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Religion -->
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label">Religion</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-moon-stars"></i></span>
                                        <select name="religion" class="form-select">
                                            <option value="">-- Select --</option>
                                            <?php foreach (['Islam', 'Hinduism', 'Christianity', 'Buddhism', 'Other'] as $rel): ?>
                                                <option value="<?= $rel ?>" <?= ($religion === $rel) ? 'selected' : '' ?>><?= $rel ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <!-- Father's Name -->
                                <div class="col-md-6">
                                    <label class="form-label">Father's Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" name="father_name" class="form-control" placeholder="Father's full name" value="<?= htmlspecialchars($father_name) ?>">
                                    </div>
                                </div>

                                <!-- Mother's Name -->
                                <div class="col-md-6">
                                    <label class="form-label">Mother's Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" name="mother_name" class="form-control" placeholder="Mother's full name" value="<?= htmlspecialchars($mother_name) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================== -->
                        <!-- 3. CONTACT & RESIDENTIAL ADDRESSES             -->
                        <!-- ============================================== -->
                        <div class="mb-5">
                            <div class="form-section-title">
                                <span class="form-section-icon bg-success-subtle text-success"><i class="bi bi-geo-alt-fill"></i></span>
                                <span>3. Contact & Residential Address</span>
                            </div>

                            <div class="row g-3 mb-3">
                                <!-- Mobile Number -->
                                <div class="col-md-6">
                                    <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                        <input type="tel" name="phone" class="form-control" placeholder="e.g. 017xxxxxxxx" value="<?= htmlspecialchars($phone) ?>" required>
                                    </div>
                                    <small class="text-muted d-block mt-1">Used as primary login identifier across app & portal.</small>
                                </div>

                                <!-- Email Address -->
                                <div class="col-md-6">
                                    <label class="form-label">Email Address <span class="text-muted fw-normal">(Optional)</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" name="email" class="form-control" placeholder="employee@company.com" value="<?= htmlspecialchars($email) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <!-- Present Address -->
                                <div class="col-md-6">
                                    <label class="form-label">Present Address</label>
                                    <textarea name="present_address" id="presentAddress" class="form-control form-control-single" rows="3" placeholder="House, Road, Area, Thana, District"><?= htmlspecialchars($present_address) ?></textarea>
                                </div>

                                <!-- Permanent Address -->
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label mb-0">Permanent Address</label>
                                        <div class="form-check form-check-inline m-0">
                                            <input class="form-check-input" type="checkbox" id="sameAddressCheck">
                                            <label class="form-check-label small text-muted" for="sameAddressCheck" style="font-size: 0.78rem;">Same as Present</label>
                                        </div>
                                    </div>
                                    <textarea name="permanent_address" id="permanentAddress" class="form-control form-control-single" rows="3" placeholder="Permanent village, Thana, District"><?= htmlspecialchars($permanent_address) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================== -->
                        <!-- 4. JOB, SALARY & SECURITY CREDENTIALS          -->
                        <!-- ============================================== -->
                        <div class="mb-4">
                            <div class="form-section-title">
                                <span class="form-section-icon bg-warning-subtle text-warning"><i class="bi bi-briefcase-fill"></i></span>
                                <span>4. Employment, Payroll & Security Password</span>
                            </div>

                            <div class="row g-3 mb-3">
                                <!-- Designation -->
                                <div class="col-md-4">
                                    <label class="form-label">Designation / Title</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-award"></i></span>
                                        <input type="text" name="designation" class="form-control" placeholder="e.g. Territory Sales Officer" value="<?= htmlspecialchars($designation) ?>">
                                    </div>
                                </div>

                                <!-- Education -->
                                <div class="col-md-4">
                                    <label class="form-label">Education Qualification</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-mortarboard"></i></span>
                                        <input type="text" name="education" class="form-control" placeholder="e.g. BBA / HSC / B.Sc" value="<?= htmlspecialchars($education) ?>">
                                    </div>
                                </div>

                                <!-- Monthly Salary -->
                                <div class="col-md-4">
                                    <label class="form-label">Monthly Salary (BDT)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-cash-stack"></i></span>
                                        <input type="number" step="0.01" min="0" name="salary" class="form-control" placeholder="e.g. 25000" value="<?= htmlspecialchars($salary) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <!-- Password -->
                                <div class="col-md-6">
                                    <label class="form-label">Account Initial Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                        <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Min. 6 characters" required minlength="6" style="border-radius: 0;">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn" style="border-radius: 0 0.65rem 0.65rem 0;">
                                            <i class="bi bi-eye" id="toggleIcon"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-1">Staff will use this password to sign into the mobile app and portal.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <hr class="text-muted opacity-25 my-4">
                        <div class="d-flex flex-wrap align-items-center gap-3 pt-2">
                            <button type="submit" class="btn btn-gradient d-inline-flex align-items-center gap-2" id="submitBtn">
                                <i class="bi bi-check2-circle fs-5"></i>
                                <span>Create Staff Member</span>
                            </button>
                            <a href="employee_list.php" class="btn btn-light-secondary d-inline-flex align-items-center gap-2">
                                <i class="bi bi-x-circle"></i>
                                <span>Cancel & Return</span>
                            </a>
                        </div>

                    </form>
                </div>
            </div>
            <!-- Card End -->

        </div>
    </div>
</div>

<script>
    // 1. Password Visibility Toggle
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');
    const passwordInput = document.getElementById('passwordInput');
    const toggleIcon = document.getElementById('toggleIcon');

    if (togglePasswordBtn) {
        togglePasswordBtn.addEventListener('click', function () {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleIcon.classList.toggle('bi-eye', !isPassword);
            toggleIcon.classList.toggle('bi-eye-slash', isPassword);
        });
    }

    // 2. Same Address Checkbox Copy
    const sameAddressCheck = document.getElementById('sameAddressCheck');
    const presentAddress = document.getElementById('presentAddress');
    const permanentAddress = document.getElementById('permanentAddress');

    if (sameAddressCheck && presentAddress && permanentAddress) {
        sameAddressCheck.addEventListener('change', function() {
            if (this.checked) {
                permanentAddress.value = presentAddress.value;
            }
        });

        presentAddress.addEventListener('input', function() {
            if (sameAddressCheck.checked) {
                permanentAddress.value = this.value;
            }
        });
    }

    // 3. Dynamic Hierarchy Adaptation Based on Role
    const roleTypeSelect = document.getElementById('roleTypeSelect');
    const assignOfficerWrapper = document.getElementById('assignOfficerWrapper');
    const supervisorSelect = document.getElementById('supervisorSelect');
    const officerHelpText = document.getElementById('officerHelpText');

    function updateHierarchyUI() {
        const selectedRole = roleTypeSelect.value;

        if (selectedRole === 'super_admin') {
            supervisorSelect.value = '';
            supervisorSelect.disabled = true;
            officerHelpText.innerHTML = '<span class="text-primary fw-medium"><i class="bi bi-shield-lock-fill"></i> Super Admin / Director is at the top of the organization and has no reporting officer.</span>';
        } else {
            supervisorSelect.disabled = false;
            if (selectedRole === 'admin') {
                officerHelpText.innerHTML = 'Admins typically report to Super Admins / Directors.';
            } else if (selectedRole === 'supervisor') {
                officerHelpText.innerHTML = 'Supervisors report to Admins or Super Admins.';
            } else {
                officerHelpText.innerHTML = 'Sales Representatives report to Field Supervisors or Admins.';
            }
        }
    }

    roleTypeSelect.addEventListener('change', updateHierarchyUI);
    updateHierarchyUI();

    // 4. Form Submit Loading Indicator
    const form = document.getElementById('createStaffForm');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating Staff...';
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
