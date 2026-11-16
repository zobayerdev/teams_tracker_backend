<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/header.php';

$success = '';
$error = '';

// Handle Status & Role Updates by Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_staff') {
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $newStatus    = trim($_POST['status'] ?? '');
    $newRole      = trim($_POST['role'] ?? '');

    if ($targetUserId > 0 && in_array($newStatus, ['active', 'inactive', 'pending']) && in_array($newRole, ['employee', 'supervisor', 'admin', 'super_admin'])) {
        $up = $pdo->prepare("UPDATE users SET status = ?, role = ? WHERE id = ?");
        if ($up->execute([$newStatus, $newRole, $targetUserId])) {
            $success = "Employee status and role updated successfully.";
        } else {
            $error = "Failed to update record. Please try again.";
        }
    }
}

// Fetch Staff
$stmt = $pdo->query("
    SELECT u.*, creator.name as creator_name, sup.name as supervisor_name
    FROM users u 
    LEFT JOIN users creator ON u.created_by = creator.id 
    LEFT JOIN users sup ON u.supervisor_id = sup.id
    WHERE u.role IN ('employee', 'supervisor', 'admin', 'super_admin', 'sales_representative') 
    ORDER BY u.id DESC
");
$staffs = $stmt->fetchAll();

// Summary Counts
$totalStaff   = count($staffs);
$pendingCount = count(array_filter($staffs, fn($s) => $s['status'] === 'pending'));
$activeCount  = count(array_filter($staffs, fn($s) => $s['status'] === 'active'));
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --saas-border: #e2e8f0;
        --saas-bg-muted: #f8fafc;
        --saas-text-dark: #0f172a;
        --saas-text-muted: #64748b;
        --saas-primary: #0284c7;
    }

    /* Metric Stat Cards */
    .metric-card {
        background: #ffffff;
        border: 1px solid var(--saas-border);
        border-radius: 1rem;
        padding: 1.15rem 1.25rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .metric-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px -2px rgba(0, 0, 0, 0.06);
    }
    .metric-icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }

    /* Main Container Card */
    .saas-card {
        background: #ffffff;
        border: 1px solid var(--saas-border);
        border-radius: 1.15rem;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        overflow: hidden;
    }

    /* Table Styles */
    .table thead th {
        background-color: #f8fafc;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid var(--saas-border);
        padding: 1rem 1.25rem;
        white-space: nowrap;
    }

    .table tbody td {
        padding: 1rem 1.25rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.9rem;
        color: #334155;
    }

    .table tbody tr {
        transition: background-color 0.15s ease;
    }
    .table tbody tr:hover {
        background-color: #f8fafc;
    }

    .row-pending {
        background-color: #fffbeb !important;
    }
    .row-pending:hover {
        background-color: #fef3c7 !important;
    }

    /* Avatars */
    .avatar-sm {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    /* Role Pills */
    .badge-pill-role {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
    }
    .role-supervisor {
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #dbeafe;
    }
    .role-employee {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    /* Status Pills */
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
    }
    .status-active {
        background: #ecfdf5;
        color: #059669;
        border: 1px solid #d1fae5;
    }
    .status-active .badge-status-dot { background: #10b981; }

    .status-pending {
        background: #fffbeb;
        color: #b45309;
        border: 1px solid #fef3c7;
    }
    .status-pending .badge-status-dot { background: #f59e0b; }

    .status-inactive {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fee2e2;
    }
    .status-inactive .badge-status-dot { background: #ef4444; }

    .badge-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }

    /* Modal Form Inputs */
    .modal-content {
        border-radius: 1.15rem;
        border: 1px solid var(--saas-border);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    .modal-header {
        border-bottom: 1px solid var(--saas-border);
        padding: 1.25rem 1.5rem;
    }
    .modal-footer {
        border-top: 1px solid var(--saas-border);
        padding: 1rem 1.5rem;
    }
    .form-label {
        font-size: 0.825rem;
        font-weight: 600;
        letter-spacing: 0.025em;
        text-transform: uppercase;
        color: #475569;
        margin-bottom: 0.35rem;
    }
    .form-control, .form-select {
        border-color: #e2e8f0;
        border-radius: 0.65rem;
        padding: 0.6rem 0.85rem;
        font-size: 0.92rem;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--saas-primary);
        box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.12);
    }

    /* Mobile Responsive Card Setup */
    @media (max-width: 991.98px) {
        .desktop-table-view {
            display: none !important;
        }
        .mobile-card-view {
            display: grid !important;
            grid-template-columns: 1fr;
            gap: 1rem;
            padding: 1rem;
        }
    }

    @media (min-width: 992px) {
        .mobile-card-view {
            display: none !important;
        }
    }

    .staff-card-mobile {
        background: #ffffff;
        border: 1px solid var(--saas-border);
        border-radius: 1rem;
        padding: 1.15rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
</style>

<div class="container-fluid px-lg-4 py-3">

    <!-- Header Section -->
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark tracking-tight">Field Staff & Approvals</h4>
            <p class="text-muted small mb-0">Review self-registered representatives, assign roles & activate accounts</p>
        </div>
        <div>
            <a href="employee_create.php" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 shadow-sm" style="font-weight: 500;">
                <i class="bi bi-person-plus-fill"></i>
                <span>Add Staff Manually</span>
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 border-0 py-3 mb-4 rounded-3 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-2 border-0 py-3 mb-4 rounded-3 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= htmlspecialchars($success) ?></div>
        </div>
    <?php endif; ?>

    <!-- KPI / Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-primary-subtle text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Total Field Force</div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalStaff ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-warning-subtle text-warning-emphasis">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Pending Approvals</div>
                    <div class="fs-4 fw-bold text-dark"><?= $pendingCount ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-12 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-success-subtle text-success">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Active Staff Accounts</div>
                    <div class="fs-4 fw-bold text-dark"><?= $activeCount ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Container Card -->
    <div class="saas-card">
        
        <!-- Live Search and Status Filter Bar -->
        <div class="p-3 border-bottom border-light-subtle bg-white">
            <div class="row g-2 justify-content-between align-items-center">
                <div class="col-md-5 col-12">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 0.65rem 0 0 0.65rem; border-color: #e2e8f0;">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="liveSearchInput" class="form-control border-start-0" placeholder="Search by name, phone, email, designation..." style="border-radius: 0 0.65rem 0.65rem 0; border-color: #e2e8f0;">
                    </div>
                </div>
                <div class="col-md-3 col-6 ms-auto">
                    <select id="statusFilterSelect" class="form-select rounded-3">
                        <option value="all" selected>All Statuses (<?= $totalStaff ?>)</option>
                        <option value="active">Active Accounts (<?= $activeCount ?>)</option>
                        <option value="pending">Pending Review (<?= $pendingCount ?>)</option>
                        <option value="inactive">Inactive Accounts</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 1. Desktop Table View -->
        <div class="desktop-table-view table-responsive mb-0">
            <table class="table align-middle mb-0" id="staffTable">
                <thead>
                    <tr>
                        <th class="ps-4">Staff Member</th>
                        <th>Contact Email</th>
                        <th>Designation</th>
                        <th>Role</th>
                        <th>Account Status</th>
                        <th>Registration Date</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffs)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                No staff records found in the database.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staffs as $row): 
                            $initial = strtoupper(substr($row['name'], 0, 1));
                            $isSupervisor = $row['role'] === 'supervisor';
                            $status = $row['status'];
                            $isPending = ($status === 'pending');
                            $avatarBg = $isSupervisor ? 'bg-primary-subtle text-primary' : 'bg-light text-dark border';
                        ?>
                            <tr class="staff-row <?= $isPending ? 'row-pending' : '' ?>"
                                data-name="<?= strtolower(htmlspecialchars($row['name'] ?? '')) ?>"
                                data-phone="<?= htmlspecialchars($row['phone'] ?? '') ?>"
                                data-email="<?= strtolower(htmlspecialchars($row['email'] ?? '')) ?>"
                                data-designation="<?= strtolower(htmlspecialchars($row['designation'] ?? 'field staff')) ?>"
                                data-status="<?= $status ?>">
                                
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-sm <?= $avatarBg ?>">
                                            <?= $initial ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($row['name']) ?></div>
                                            <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['phone']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-dark small"><?= htmlspecialchars($row['email']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border fw-normal py-1 px-2.5 rounded-2">
                                        <?= htmlspecialchars($row['designation'] ?? 'Field Staff') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $roleKey = $row['role'];
                                    $roleDisplay = 'Sales Rep';
                                    $roleBadgeClass = 'bg-success-subtle text-success border border-success-subtle';
                                    $roleIcon = 'bi-person-badge';

                                    if ($roleKey === 'super_admin') {
                                        $roleDisplay = 'Super Admin';
                                        $roleBadgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                                        $roleIcon = 'bi-award-fill';
                                    } elseif ($roleKey === 'admin') {
                                        $roleDisplay = 'Admin';
                                        $roleBadgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
                                        $roleIcon = 'bi-shield-shaded';
                                    } elseif ($roleKey === 'supervisor') {
                                        $roleDisplay = 'Supervisor';
                                        $roleBadgeClass = 'bg-info-subtle text-info border border-info-subtle';
                                        $roleIcon = 'bi-shield-check';
                                    }
                                    ?>
                                    <span class="badge py-1 px-2 rounded-2 fw-semibold <?= $roleBadgeClass ?>">
                                        <i class="bi <?= $roleIcon ?> me-1"></i><?= $roleDisplay ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($status === 'active'): ?>
                                        <span class="badge-status status-active">
                                            <span class="badge-status-dot"></span> Active
                                        </span>
                                    <?php elseif ($status === 'pending'): ?>
                                        <span class="badge-status status-pending">
                                            <span class="badge-status-dot"></span> Pending Approval
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-status status-inactive">
                                            <span class="badge-status-dot"></span> Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?= date('d M, Y', strtotime($row['created_at'])) ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1.5">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-secondary rounded-2 px-2.5 py-1"
                                                onclick='openViewProfileModal(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                                title="View Complete Biodata">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary rounded-2 px-2.5 py-1"
                                                onclick="openManageModal(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['name'])) ?>', '<?= $row['role'] ?>', '<?= $status ?>')">
                                            <i class="bi bi-sliders me-1"></i> Manage
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. Mobile Responsive Card View -->
        <div class="mobile-card-view">
            <?php if (empty($staffs)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No staff records found.
                </div>
            <?php else: ?>
                <?php foreach ($staffs as $row): 
                    $initial = strtoupper(substr($row['name'], 0, 1));
                    $isSupervisor = $row['role'] === 'supervisor';
                    $status = $row['status'];
                    $isPending = ($status === 'pending');
                    $avatarBg = $isSupervisor ? 'bg-primary-subtle text-primary' : 'bg-light text-dark border';
                ?>
                    <div class="staff-card-mobile staff-card-item <?= $isPending ? 'border-warning' : '' ?>"
                         data-name="<?= strtolower(htmlspecialchars($row['name'])) ?>"
                         data-phone="<?= htmlspecialchars($row['phone']) ?>"
                         data-email="<?= strtolower(htmlspecialchars($row['email'])) ?>"
                         data-designation="<?= strtolower(htmlspecialchars($row['designation'] ?? 'field staff')) ?>"
                         data-status="<?= $status ?>">
                        
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm <?= $avatarBg ?>">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($row['name']) ?></h6>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['phone']) ?></small>
                                </div>
                            </div>
                            <span class="badge-pill-role <?= $isSupervisor ? 'role-supervisor' : 'role-employee' ?>">
                                <?= ucfirst($row['role']) ?>
                            </span>
                        </div>

                        <!-- Info Snippet -->
                        <div class="p-2.5 rounded-3 bg-light my-2.5 small">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted">Designation:</span>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($row['designation'] ?? 'Field Staff') ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Email:</span>
                                <span class="text-dark"><?= htmlspecialchars($row['email']) ?></span>
                            </div>
                        </div>

                        <!-- Status & Action Footer -->
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top border-light-subtle">
                            <?php if ($status === 'active'): ?>
                                <span class="badge-status status-active">
                                    <span class="badge-status-dot"></span> Active
                                </span>
                            <?php elseif ($status === 'pending'): ?>
                                <span class="badge-status status-pending">
                                    <span class="badge-status-dot"></span> Pending Approval
                                </span>
                            <?php else: ?>
                                <span class="badge-status status-inactive">
                                    <span class="badge-status-dot"></span> Inactive
                                </span>
                            <?php endif; ?>

                            <div class="d-flex gap-1.5">
                                <button type="button" 
                                        class="btn btn-sm btn-outline-secondary rounded-2 px-2 py-1"
                                        onclick='openViewProfileModal(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-primary rounded-2 px-2.5 py-1"
                                        onclick="openManageModal(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['name'])) ?>', '<?= $row['role'] ?>', '<?= $status ?>')">
                                    <i class="bi bi-sliders me-1"></i> Manage
                                </button>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Modal 1: View Complete Biodata / Profile -->
<div class="modal fade" id="viewProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="avatar-sm bg-primary text-white" id="vpAvatar" style="width: 38px; height: 38px; border-radius: 10px; display:flex; align-items:center; justify-content:center; font-weight:700;">
                        U
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="vpName">Staff Name</h6>
                        <small class="text-muted" id="vpDesignationRole">Designation • Role</small>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    
                    <!-- 1. Personal Biodata Section -->
                    <div class="col-12">
                        <h6 class="fw-bold text-primary mb-2 border-bottom pb-1 small text-uppercase">
                            <i class="bi bi-person-lines-fill me-1"></i> 1. Personal Biodata
                        </h6>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Age</small>
                        <span class="fw-semibold text-dark" id="vpAge">—</span>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Date of Birth (DOB)</small>
                        <span class="fw-semibold text-dark" id="vpDob">—</span>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">National ID (NID)</small>
                        <span class="fw-semibold text-dark" id="vpNid">—</span>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Blood Group</small>
                        <span class="fw-semibold text-danger" id="vpBloodGroup">—</span>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Religion</small>
                        <span class="fw-semibold text-dark" id="vpReligion">—</span>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Education</small>
                        <span class="fw-semibold text-dark" id="vpEducation">—</span>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Father's Name</small>
                        <span class="fw-semibold text-dark" id="vpFatherName">—</span>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Mother's Name</small>
                        <span class="fw-semibold text-dark" id="vpMotherName">—</span>
                    </div>

                    <!-- 2. Contact & Address Section -->
                    <div class="col-12 mt-4">
                        <h6 class="fw-bold text-success mb-2 border-bottom pb-1 small text-uppercase">
                            <i class="bi bi-geo-alt-fill me-1"></i> 2. Contact & Addresses
                        </h6>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Mobile Number</small>
                        <span class="fw-semibold text-dark" id="vpPhone">—</span>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Email Address</small>
                        <span class="fw-semibold text-dark" id="vpEmail">—</span>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Present Address</small>
                        <span class="text-dark" id="vpPresentAddress">—</span>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Permanent Address</small>
                        <span class="text-dark" id="vpPermanentAddress">—</span>
                    </div>

                    <!-- 3. Job & Reporting Section -->
                    <div class="col-12 mt-4">
                        <h6 class="fw-bold text-info mb-2 border-bottom pb-1 small text-uppercase">
                            <i class="bi bi-briefcase-fill me-1"></i> 3. Employment & Hierarchy
                        </h6>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Assigned Reporting Officer</small>
                        <span class="fw-bold text-primary" id="vpSupervisorName">—</span>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Monthly Salary</small>
                        <span class="fw-bold text-success" id="vpSalary">৳ 0.00</span>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Member Since</small>
                        <span class="text-dark" id="vpCreatedAt">—</span>
                    </div>

                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm rounded-3 px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Edit / Approve Staff -->
<div class="modal fade" id="manageStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-sm bg-primary-subtle text-primary" style="width: 34px; height: 34px; font-size: 1rem; border-radius: 8px;">
                        <i class="bi bi-person-gear"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Manage Status & Role</h6>
                        <small class="text-muted" style="font-size: 0.78rem;">Activate or change staff permissions</small>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_staff">
                <input type="hidden" name="target_user_id" id="modalTargetUserId">
                <div class="modal-body p-4">
                    
                    <div class="p-3 bg-light rounded-3 mb-3 small d-flex justify-content-between align-items-center">
                        <span class="text-muted">Staff Member:</span>
                        <strong id="modalTargetName" class="text-primary fs-6"></strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Account Status <span class="text-danger">*</span></label>
                        <select name="status" id="modalTargetStatus" class="form-select" required>
                            <option value="active">Active (Approved)</option>
                            <option value="pending">Pending Review</option>
                            <option value="inactive">Inactive (Deactivated)</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Role Type <span class="text-danger">*</span></label>
                        <select name="role" id="modalTargetRole" class="form-select" required>
                            <option value="employee">Sales Representative (Employee)</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin (Director)</option>
                        </select>
                        <small class="text-muted d-block mt-1" style="font-size: 0.78rem;">
                            Change role hierarchy permissions for this staff member.
                        </small>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 px-3 border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openManageModal(id, name, role, status) {
    document.getElementById('modalTargetUserId').value = id;
    document.getElementById('modalTargetName').innerText = name;
    document.getElementById('modalTargetRole').value = role;
    document.getElementById('modalTargetStatus').value = status;

    const modal = new bootstrap.Modal(document.getElementById('manageStaffModal'));
    modal.show();
}

function openViewProfileModal(data) {
    document.getElementById('vpName').innerText = data.name || '—';
    document.getElementById('vpAvatar').innerText = (data.name ? data.name.charAt(0).toUpperCase() : 'U');
    document.getElementById('vpDesignationRole').innerText = (data.designation || 'Staff') + ' • ' + (data.role ? data.role.toUpperCase() : '');
    
    document.getElementById('vpAge').innerText = data.age ? (data.age + ' Years') : '—';
    document.getElementById('vpDob').innerText = data.dob || '—';
    document.getElementById('vpNid').innerText = data.nid || '—';
    document.getElementById('vpBloodGroup').innerText = data.blood_group || '—';
    document.getElementById('vpReligion').innerText = data.religion || '—';
    document.getElementById('vpEducation').innerText = data.education || '—';
    document.getElementById('vpFatherName').innerText = data.father_name || '—';
    document.getElementById('vpMotherName').innerText = data.mother_name || '—';
    
    document.getElementById('vpPhone').innerText = data.phone || '—';
    document.getElementById('vpEmail').innerText = data.email || '—';
    document.getElementById('vpPresentAddress').innerText = data.present_address || '—';
    document.getElementById('vpPermanentAddress').innerText = data.permanent_address || '—';
    
    document.getElementById('vpSupervisorName').innerText = data.supervisor_name || 'Direct / Head of Org';
    document.getElementById('vpSalary').innerText = data.salary ? ('৳ ' + parseFloat(data.salary).toLocaleString('en-US', {minimumFractionDigits: 2})) : '৳ 0.00';
    document.getElementById('vpCreatedAt').innerText = data.created_at || '—';

    const modal = new bootstrap.Modal(document.getElementById('viewProfileModal'));
    modal.show();
}

// Live Search & Filter Logic
const searchInput = document.getElementById('liveSearchInput');
const statusFilter = document.getElementById('statusFilterSelect');
const tableRows = document.querySelectorAll('.staff-row');
const mobileCards = document.querySelectorAll('.staff-card-item');

function filterStaff() {
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const filterStatus = statusFilter ? statusFilter.value : 'all';

    // Desktop Table Rows
    tableRows.forEach(row => {
        const name = (row.getAttribute('data-name') || '').toLowerCase();
        const phone = (row.getAttribute('data-phone') || '').toLowerCase();
        const email = (row.getAttribute('data-email') || '').toLowerCase();
        const designation = (row.getAttribute('data-designation') || '').toLowerCase();
        const status = row.getAttribute('data-status');

        const matchesSearch = (query === '') || name.includes(query) || phone.includes(query) || email.includes(query) || designation.includes(query);
        const matchesStatus = (filterStatus === 'all') || (status === filterStatus);

        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });

    // Mobile Cards
    mobileCards.forEach(card => {
        const name = (card.getAttribute('data-name') || '').toLowerCase();
        const phone = (card.getAttribute('data-phone') || '').toLowerCase();
        const email = (card.getAttribute('data-email') || '').toLowerCase();
        const designation = (card.getAttribute('data-designation') || '').toLowerCase();
        const status = card.getAttribute('data-status');

        const matchesSearch = (query === '') || name.includes(query) || phone.includes(query) || email.includes(query) || designation.includes(query);
        const matchesStatus = (filterStatus === 'all') || (status === filterStatus);

        card.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
}

searchInput.addEventListener('input', filterStaff);
statusFilter.addEventListener('change', filterStaff);

window.addEventListener('DOMContentLoaded', filterStaff);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>