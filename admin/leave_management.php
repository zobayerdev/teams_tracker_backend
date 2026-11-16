<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/header.php';

$currentUserId = $_SESSION['user_id'] ?? 0;
$currentUserRole = $_SESSION['role'] ?? 'admin';

$success = '';
$error = '';

// Handle Leave Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review_leave') {
    $leaveId = (int)($_POST['leave_id'] ?? 0);
    $statusAction = trim($_POST['status_action'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($leaveId > 0 && in_array($statusAction, ['approved', 'rejected'])) {
        if ($currentUserRole === 'supervisor') {
            // Supervisor review
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET supervisor_status = ?, supervisor_action_by = ?, supervisor_action_at = NOW(), supervisor_remarks = ?
                WHERE id = ?
            ");
            $stmt->execute([$statusAction, $currentUserId, $remarks, $leaveId]);
            $success = "Supervisor action ($statusAction) successfully saved.";
        } else {
            // Super Admin / Admin review
            $finalStatus = ($statusAction === 'approved') ? 'approved' : 'rejected';
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET admin_status = ?, admin_action_by = ?, admin_action_at = NOW(), admin_remarks = ?, final_status = ?
                WHERE id = ?
            ");
            $stmt->execute([$statusAction, $currentUserId, $remarks, $finalStatus, $leaveId]);
            $success = "Admin final decision ($statusAction) successfully updated.";
        }
    } else {
        $error = 'Invalid request parameters.';
    }
}

// Handle Direct Leave Application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $empId = (int)($_POST['employee_id'] ?? 0);
    $leaveType = trim($_POST['leave_type'] ?? 'casual');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if ($empId <= 0 || empty($startDate) || empty($endDate) || empty($reason)) {
        $error = 'Please fill all mandatory fields.';
    } else {
        $days = (strtotime($endDate) >= strtotime($startDate)) 
            ? ((strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24)) + 1 
            : 0;

        if ($days <= 0) {
            $error = 'End date cannot be earlier than start date.';
        } else {
            $ins = $pdo->prepare("
                INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, total_days, reason, final_status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            if ($ins->execute([$empId, $leaveType, $startDate, $endDate, $days, $reason])) {
                $success = 'Leave request submitted successfully!';
            } else {
                $error = 'Failed to submit leave request.';
            }
        }
    }
}

// Fetch staff for dropdown
$empList = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('employee', 'supervisor') ORDER BY name ASC")->fetchAll();

// Main Query according to role
$query = "
    SELECT 
        lr.*, 
        e.name AS employee_name, e.phone AS employee_phone, e.role AS employee_role,
        s.name AS supervisor_name,
        sa.name AS supervisor_action_name,
        aa.name AS admin_action_name
    FROM leave_requests lr
    JOIN users e ON lr.employee_id = e.id
    LEFT JOIN users s ON e.supervisor_id = s.id
    LEFT JOIN users sa ON lr.supervisor_action_by = sa.id
    LEFT JOIN users aa ON lr.admin_action_by = aa.id
";

if ($currentUserRole === 'supervisor') {
    $query .= " WHERE e.supervisor_id = " . (int)$currentUserId;
}

$query .= " ORDER BY lr.id DESC";
$leaves = $pdo->query($query)->fetchAll();

// Summary Counters
$totalLeaves = count($leaves);
$grantedCount = count(array_filter($leaves, fn($l) => $l['final_status'] === 'approved'));
$pendingCount = count(array_filter($leaves, fn($l) => $l['final_status'] === 'pending' || empty($l['final_status'])));
$rejectedCount = count(array_filter($leaves, fn($l) => $l['final_status'] === 'rejected'));
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

    /* Table Custom Styles */
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

    /* Status Pills */
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
    }
    .status-granted {
        background: #ecfdf5;
        color: #059669;
        border: 1px solid #d1fae5;
    }
    .status-pending {
        background: #fffbeb;
        color: #b45309;
        border: 1px solid #fef3c7;
    }
    .status-rejected {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fee2e2;
    }
    .status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }
    .status-granted .status-dot { background: #10b981; }
    .status-pending .status-dot { background: #f59e0b; }
    .status-rejected .status-dot { background: #ef4444; }

    /* Leave Type Badges */
    .badge-leave-type {
        font-size: 0.75rem;
        padding: 0.25rem 0.6rem;
        border-radius: 6px;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        font-weight: 500;
    }

    /* Modal Styling */
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

    .leave-card-mobile {
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
            <h4 class="fw-bold mb-1 text-dark tracking-tight">Leave Management System</h4>
            <p class="text-muted small mb-0">Two-tier approval workflow: Supervisor Endorsement & Admin Final Decision</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#applyLeaveModal" style="font-weight: 500;">
                <i class="bi bi-calendar-plus"></i>
                <span>Apply For Leave</span>
            </button>
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
        <div class="col-sm-6 col-lg-3">
            <div class="metric-card">
                <div class="metric-icon-box bg-primary-subtle text-primary">
                    <i class="bi bi-calendar-week"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Total Requests</div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalLeaves ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="metric-card">
                <div class="metric-icon-box bg-success-subtle text-success">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Approved / Granted</div>
                    <div class="fs-4 fw-bold text-dark"><?= $grantedCount ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="metric-card">
                <div class="metric-icon-box bg-warning-subtle text-warning-emphasis">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">In Review / Pending</div>
                    <div class="fs-4 fw-bold text-dark"><?= $pendingCount ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="metric-card">
                <div class="metric-icon-box bg-danger-subtle text-danger">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Rejected Requests</div>
                    <div class="fs-4 fw-bold text-dark"><?= $rejectedCount ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Container Card -->
    <div class="saas-card">
        
        <!-- Live Search and Status Filter Bar -->
        <div class="p-3 border-bottom border-light-subtle bg-white">
            <div class="row g-2 justify-content-between align-items-center">
                <div class="col-md-5 col-12">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 0.65rem 0 0 0.65rem; border-color: #e2e8f0;">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="liveSearchInput" class="form-control border-start-0" placeholder="Search by employee, phone or reason..." style="border-radius: 0 0.65rem 0.65rem 0; border-color: #e2e8f0;">
                    </div>
                </div>
                <div class="col-md-3 col-6 ms-auto">
                    <select id="statusFilterSelect" class="form-select rounded-3">
                        <option value="all">All Decision Status</option>
                        <option value="approved">Granted / Approved</option>
                        <option value="pending">In Review / Pending</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 1. Desktop Table View -->
        <div class="desktop-table-view table-responsive mb-0">
            <table class="table align-middle mb-0" id="leaveTable">
                <thead>
                    <tr>
                        <th class="ps-4">Employee Info</th>
                        <th>Duration & Type</th>
                        <th>Reason</th>
                        <th>Supervisor Review</th>
                        <th>Admin Review</th>
                        <th>Final Decision</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaves)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                No leave applications found in the records.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leaves as $row): 
                            $initial = strtoupper(substr($row['employee_name'], 0, 1));
                            $isGranted = $row['final_status'] === 'approved';
                            $isRejected = $row['final_status'] === 'rejected';
                            $isPending = !$isGranted && !$isRejected;

                            $finalBadgeClass = $isGranted ? 'status-granted' : ($isRejected ? 'status-rejected' : 'status-pending');
                            $finalLabel = $isGranted ? 'GRANTED' : ($isRejected ? 'REJECTED' : 'IN REVIEW');
                        ?>
                            <tr class="leave-row"
                                data-name="<?= strtolower(htmlspecialchars($row['employee_name'])) ?>"
                                data-phone="<?= htmlspecialchars($row['employee_phone']) ?>"
                                data-reason="<?= strtolower(htmlspecialchars($row['reason'])) ?>"
                                data-status="<?= $isGranted ? 'approved' : ($isRejected ? 'rejected' : 'pending') ?>">
                                
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-sm bg-primary-subtle text-primary">
                                            <?= $initial ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($row['employee_name']) ?></div>
                                            <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['employee_phone']) ?></small>
                                            <small class="text-primary" style="font-size: 0.78rem;">Sup: <?= htmlspecialchars($row['supervisor_name'] ?? 'Unassigned') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark small">
                                        <?= date('d M', strtotime($row['start_date'])) ?> — <?= date('d M, Y', strtotime($row['end_date'])) ?>
                                    </div>
                                    <div class="d-flex gap-1.5 mt-1">
                                        <span class="badge-leave-type"><i class="bi bi-clock me-1"></i><?= $row['total_days'] ?> Day(s)</span>
                                        <span class="badge-leave-type"><?= ucfirst($row['leave_type']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-secondary small" style="max-width: 220px; line-height: 1.4;">
                                        <?= htmlspecialchars($row['reason']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($row['supervisor_status'] === 'approved'): ?>
                                        <span class="badge-status status-granted"><span class="status-dot"></span> Endorsed</span>
                                    <?php elseif ($row['supervisor_status'] === 'rejected'): ?>
                                        <span class="badge-status status-rejected"><span class="status-dot"></span> Declined</span>
                                    <?php else: ?>
                                        <span class="badge-status status-pending"><span class="status-dot"></span> Pending</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($row['supervisor_action_name'])): ?>
                                        <small class="text-muted d-block mt-0.5" style="font-size: 0.72rem;">by <?= htmlspecialchars($row['supervisor_action_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['admin_status'] === 'approved'): ?>
                                        <span class="badge-status status-granted"><span class="status-dot"></span> Approved</span>
                                    <?php elseif ($row['admin_status'] === 'rejected'): ?>
                                        <span class="badge-status status-rejected"><span class="status-dot"></span> Rejected</span>
                                    <?php else: ?>
                                        <span class="badge-status status-pending"><span class="status-dot"></span> Pending</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($row['admin_action_name'])): ?>
                                        <small class="text-muted d-block mt-0.5" style="font-size: 0.72rem;">by <?= htmlspecialchars($row['admin_action_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= $finalBadgeClass ?>">
                                        <span class="status-dot"></span>
                                        <?= $finalLabel ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary rounded-2 px-3 py-1"
                                            onclick="openApprovalModal(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['employee_name'])) ?>', '<?= $row['supervisor_status'] ?>', '<?= $row['admin_status'] ?>')">
                                        <i class="bi bi-pencil-square me-1"></i> Review
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. Mobile Responsive Card View -->
        <div class="mobile-card-view">
            <?php if (empty($leaves)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No leave applications found.
                </div>
            <?php else: ?>
                <?php foreach ($leaves as $row): 
                    $initial = strtoupper(substr($row['employee_name'], 0, 1));
                    $isGranted = $row['final_status'] === 'approved';
                    $isRejected = $row['final_status'] === 'rejected';
                    $isPending = !$isGranted && !$isRejected;

                    $finalBadgeClass = $isGranted ? 'status-granted' : ($isRejected ? 'status-rejected' : 'status-pending');
                    $finalLabel = $isGranted ? 'GRANTED' : ($isRejected ? 'REJECTED' : 'IN REVIEW');
                ?>
                    <div class="leave-card-mobile leave-card-item"
                         data-name="<?= strtolower(htmlspecialchars($row['employee_name'])) ?>"
                         data-phone="<?= htmlspecialchars($row['employee_phone']) ?>"
                         data-reason="<?= strtolower(htmlspecialchars($row['reason'])) ?>"
                         data-status="<?= $isGranted ? 'approved' : ($isRejected ? 'rejected' : 'pending') ?>">
                        
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm bg-primary-subtle text-primary">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($row['employee_name']) ?></h6>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['employee_phone']) ?></small>
                                </div>
                            </div>
                            <span class="badge-status <?= $finalBadgeClass ?>">
                                <span class="status-dot"></span>
                                <?= $finalLabel ?>
                            </span>
                        </div>

                        <!-- Duration and Types -->
                        <div class="p-2.5 rounded-3 bg-light my-2.5 small">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold text-dark">
                                    <?= date('d M', strtotime($row['start_date'])) ?> — <?= date('d M, Y', strtotime($row['end_date'])) ?>
                                </span>
                                <span class="badge-leave-type bg-white"><?= $row['total_days'] ?> Days</span>
                            </div>
                            <div class="text-muted" style="font-size: 0.8rem;">
                                Type: <strong class="text-dark"><?= ucfirst($row['leave_type']) ?></strong>
                            </div>
                        </div>

                        <!-- Reason -->
                        <div class="small text-secondary mb-3" style="font-size: 0.825rem; line-height: 1.4;">
                            <strong class="text-dark d-block mb-0.5">Reason:</strong>
                            <?= htmlspecialchars($row['reason']) ?>
                        </div>

                        <!-- Two Tier Badges Summary -->
                        <div class="d-flex justify-content-between align-items-center small py-2 border-top border-light-subtle">
                            <div>
                                <small class="text-muted d-block" style="font-size: 0.7rem;">Supervisor</small>
                                <span class="badge-status <?= $row['supervisor_status'] === 'approved' ? 'status-granted' : ($row['supervisor_status'] === 'rejected' ? 'status-rejected' : 'status-pending') ?>" style="padding: 0.2rem 0.5rem; font-size: 0.72rem;">
                                    <?= ucfirst($row['supervisor_status'] ?? 'pending') ?>
                                </span>
                            </div>
                            <div>
                                <small class="text-muted d-block" style="font-size: 0.7rem;">Admin Action</small>
                                <span class="badge-status <?= $row['admin_status'] === 'approved' ? 'status-granted' : ($row['admin_status'] === 'rejected' ? 'status-rejected' : 'status-pending') ?>" style="padding: 0.2rem 0.5rem; font-size: 0.72rem;">
                                    <?= ucfirst($row['admin_status'] ?? 'pending') ?>
                                </span>
                            </div>
                            <div class="text-end">
                                <button type="button" 
                                        class="btn btn-sm btn-outline-primary rounded-2 px-3 py-1"
                                        onclick="openApprovalModal(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['employee_name'])) ?>', '<?= $row['supervisor_status'] ?>', '<?= $row['admin_status'] ?>')">
                                    Review
                                </button>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Modal: Action Approval -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-sm bg-primary-subtle text-primary" style="width: 34px; height: 34px; font-size: 1rem; border-radius: 8px;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Review Leave Request</h6>
                        <small class="text-muted" style="font-size: 0.78rem;">Take decisive approval action</small>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="review_leave">
                <input type="hidden" name="leave_id" id="modalLeaveId">
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Staff Member:</span>
                            <strong id="modalEmpName" class="text-dark"></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Reviewing As:</span>
                            <span class="badge bg-dark-subtle text-dark text-uppercase"><?= strtoupper($currentUserRole) ?></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Decision <span class="text-danger">*</span></label>
                        <select name="status_action" class="form-select" required>
                            <option value="approved">Approve This Leave</option>
                            <option value="rejected">Reject This Leave</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Endorsement / Rejection Remarks</label>
                        <input type="text" name="remarks" class="form-control" placeholder="Optional review note or reason">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 px-3 border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 shadow-sm">Confirm Decision</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Apply Leave Form -->
<div class="modal fade" id="applyLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-sm bg-primary-subtle text-primary" style="width: 34px; height: 34px; font-size: 1rem; border-radius: 8px;">
                        <i class="bi bi-calendar-plus"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Apply For Leave</h6>
                        <small class="text-muted" style="font-size: 0.78rem;">Submit staff leave application</small>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="apply_leave" value="1">
                <div class="modal-body p-4">
                    
                    <div class="mb-3">
                        <label class="form-label">Select Employee <span class="text-danger">*</span></label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">-- Choose Employee --</option>
                            <?php foreach ($empList as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?> (<?= ucfirst($e['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                        <select name="leave_type" class="form-select" required>
                            <option value="casual">Casual Leave</option>
                            <option value="sick">Sick Leave</option>
                            <option value="emergency">Emergency Leave</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Reason for Leave <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="State reason clearly..." required></textarea>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 px-3 border" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 shadow-sm">Submit Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openApprovalModal(leaveId, empName, supStatus, adminStatus) {
    document.getElementById('modalLeaveId').value = leaveId;
    document.getElementById('modalEmpName').innerText = empName;
    const modal = new bootstrap.Modal(document.getElementById('actionModal'));
    modal.show();
}

// Live Search & Filter Logic
const searchInput = document.getElementById('liveSearchInput');
const statusFilter = document.getElementById('statusFilterSelect');
const tableRows = document.querySelectorAll('.leave-row');
const mobileCards = document.querySelectorAll('.leave-card-item');

function filterLeaves() {
    const query = searchInput.value.toLowerCase().trim();
    const filterStatus = statusFilter.value;

    // Desktop Table
    tableRows.forEach(row => {
        const name = row.getAttribute('data-name');
        const phone = row.getAttribute('data-phone');
        const reason = row.getAttribute('data-reason');
        const status = row.getAttribute('data-status');

        const matchesSearch = name.includes(query) || phone.includes(query) || reason.includes(query);
        const matchesStatus = (filterStatus === 'all') || (status === filterStatus);

        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });

    // Mobile Cards
    mobileCards.forEach(card => {
        const name = card.getAttribute('data-name');
        const phone = card.getAttribute('data-phone');
        const reason = card.getAttribute('data-reason');
        const status = card.getAttribute('data-status');

        const matchesSearch = name.includes(query) || phone.includes(query) || reason.includes(query);
        const matchesStatus = (filterStatus === 'all') || (status === filterStatus);

        card.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
}

searchInput.addEventListener('input', filterLeaves);
statusFilter.addEventListener('change', filterLeaves);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>