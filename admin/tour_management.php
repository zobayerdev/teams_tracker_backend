<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/header.php';

$currentUserId = $_SESSION['user_id'] ?? 0;
$currentUserRole = $_SESSION['role'] ?? 'admin';

$success = '';
$error = '';

// Handle Tour Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review_tour') {
    $tourId         = (int)($_POST['tour_id'] ?? 0);
    $statusAction   = trim($_POST['status_action'] ?? '');
    $approvedBudget = (float)($_POST['approved_budget'] ?? 0);
    $remarks        = trim($_POST['admin_remarks'] ?? '');

    if ($tourId > 0 && in_array($statusAction, ['approved', 'rejected'])) {
        $finalApprovedAmount = ($statusAction === 'approved') ? $approvedBudget : 0.00;
        
        $stmt = $pdo->prepare("
            UPDATE tour_plans 
            SET status = ?, approved_budget = ?, action_by = ?, action_at = NOW(), admin_remarks = ?
            WHERE id = ?
        ");
        if ($stmt->execute([$statusAction, $finalApprovedAmount, $currentUserId, $remarks, $tourId])) {
            $success = "Tour plan successfully " . ($statusAction === 'approved' ? 'Approved' : 'Rejected') . ".";
        } else {
            $error = "Failed to update tour status.";
        }
    } else {
        $error = "Invalid request parameters.";
    }
}

// Handle Direct Tour Plan Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tour'])) {
    $empId        = (int)($_POST['employee_id'] ?? 0);
    $title        = trim($_POST['title'] ?? '');
    $destination  = trim($_POST['destination'] ?? '');
    $startDate    = trim($_POST['start_date'] ?? '');
    $endDate      = trim($_POST['end_date'] ?? '');
    $purpose      = trim($_POST['purpose'] ?? '');
    $transport    = (float)($_POST['est_transport'] ?? 0);
    $da           = (float)($_POST['est_da'] ?? 0);
    $hotel        = (float)($_POST['est_hotel'] ?? 0);
    $other        = (float)($_POST['est_other'] ?? 0);

    $totalBudget = $transport + $da + $hotel + $other;

    if ($empId <= 0 || empty($title) || empty($destination) || empty($startDate) || empty($endDate)) {
        $error = "All marked (*) fields are required.";
    } else {
        $days = (strtotime($endDate) >= strtotime($startDate)) 
            ? ((strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24)) + 1 
            : 0;

        if ($days <= 0) {
            $error = "End date cannot be earlier than start date.";
        } else {
            $ins = $pdo->prepare("
                INSERT INTO tour_plans (
                    employee_id, title, destination, start_date, end_date, total_days, 
                    purpose, est_transport, est_da, est_hotel, est_other, total_estimated_budget, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            if ($ins->execute([
                $empId, $title, $destination, $startDate, $endDate, $days, 
                $purpose, $transport, $da, $hotel, $other, $totalBudget
            ])) {
                $success = "New tour plan submitted successfully!";
            } else {
                $error = "Failed to save tour plan.";
            }
        }
    }
}

// Fetch Staff List
$empList = $pdo->query("SELECT id, name, phone, role FROM users WHERE role IN ('employee', 'supervisor') ORDER BY name ASC")->fetchAll();

// Main Query
$query = "
    SELECT 
        tp.*, 
        e.name AS employee_name, e.phone AS employee_phone, e.role AS employee_role,
        a.name AS reviewer_name
    FROM tour_plans tp
    JOIN users e ON tp.employee_id = e.id
    LEFT JOIN users a ON tp.action_by = a.id
    ORDER BY tp.id DESC
";
$tours = $pdo->query($query)->fetchAll();

// Summary Counts
$pendingCount = 0;
$approvedBudgetTotal = 0;
$totalTours = count($tours);

foreach ($tours as $t) {
    if ($t['status'] === 'pending') {
        $pendingCount++;
    } elseif ($t['status'] === 'approved') {
        $approvedBudgetTotal += (float)$t['approved_budget'];
    }
}
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

    /* Status Badges */
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
    }
    .status-approved {
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
    .status-approved .status-dot { background: #10b981; }
    .status-pending .status-dot { background: #f59e0b; }
    .status-rejected .status-dot { background: #ef4444; }

    .expense-pill {
        font-size: 0.74rem;
        padding: 0.18rem 0.5rem;
        border-radius: 6px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #475569;
        display: inline-block;
        margin-bottom: 0.2rem;
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

    .tour-card-mobile {
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
            <h4 class="fw-bold mb-1 text-dark tracking-tight">Official Tour Management</h4>
            <p class="text-muted small mb-0">Outstation visits, itinerary tracking, and TA/DA allowance approvals</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#createTourModal" style="font-weight: 500;">
                <i class="bi bi-plus-lg"></i>
                <span>New Tour Plan</span>
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
        <div class="col-sm-6 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-primary-subtle text-primary">
                    <i class="bi bi-briefcase-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Total Tour Plans</div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalTours ?></div>
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
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Approved Budget Total</div>
                    <div class="fs-4 fw-bold text-dark">৳<?= number_format($approvedBudgetTotal, 2) ?></div>
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
                        <input type="text" id="liveSearchInput" class="form-control border-start-0" placeholder="Search by officer, title, destination or purpose..." style="border-radius: 0 0.65rem 0.65rem 0; border-color: #e2e8f0;">
                    </div>
                </div>
                <div class="col-md-3 col-6 ms-auto">
                    <select id="statusFilterSelect" class="form-select rounded-3">
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending Only</option>
                        <option value="approved">Approved Only</option>
                        <option value="rejected">Rejected Only</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 1. Desktop Table View -->
        <div class="desktop-table-view table-responsive mb-0">
            <table class="table align-middle mb-0" id="tourTable">
                <thead>
                    <tr>
                        <th class="ps-4">Officer & Title</th>
                        <th>Destination & Dates</th>
                        <th>Expense Breakdown</th>
                        <th>Budget Estimate</th>
                        <th>Review Status</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tours)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-airplane fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                No tour plans found in the system.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tours as $row): 
                            $initial = strtoupper(substr($row['employee_name'], 0, 1));
                            $status = $row['status'];
                            $badgeClass = ($status === 'approved') ? 'status-approved' : (($status === 'rejected') ? 'status-rejected' : 'status-pending');
                        ?>
                            <tr class="tour-row"
                                data-name="<?= strtolower(htmlspecialchars($row['employee_name'])) ?>"
                                data-title="<?= strtolower(htmlspecialchars($row['title'])) ?>"
                                data-destination="<?= strtolower(htmlspecialchars($row['destination'])) ?>"
                                data-purpose="<?= strtolower(htmlspecialchars($row['purpose'])) ?>"
                                data-status="<?= $status ?>">
                                
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-sm bg-primary-subtle text-primary">
                                            <?= $initial ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($row['title']) ?></div>
                                            <small class="text-primary fw-medium"><?= htmlspecialchars($row['employee_name']) ?></small>
                                            <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['employee_phone']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark small">
                                        <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($row['destination']) ?>
                                    </div>
                                    <small class="text-secondary d-block">
                                        <?= date('d M', strtotime($row['start_date'])) ?> — <?= date('d M, Y', strtotime($row['end_date'])) ?>
                                    </small>
                                    <span class="badge bg-light text-secondary border mt-1" style="font-size: 0.72rem;"><?= $row['total_days'] ?> Day(s)</span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1" style="max-width: 200px;">
                                        <span class="expense-pill">TA: ৳<?= number_format($row['est_transport'], 2) ?></span>
                                        <span class="expense-pill">DA: ৳<?= number_format($row['est_da'], 2) ?></span>
                                        <span class="expense-pill">Hotel: ৳<?= number_format($row['est_hotel'], 2) ?></span>
                                        <?php if ($row['est_other'] > 0): ?>
                                            <span class="expense-pill">Other: ৳<?= number_format($row['est_other'], 2) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark d-block">৳<?= number_format($row['total_estimated_budget'], 2) ?></span>
                                    <?php if ($status === 'approved'): ?>
                                        <small class="text-success fw-semibold" style="font-size: 0.75rem;">
                                            Approved: ৳<?= number_format($row['approved_budget'], 2) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= $badgeClass ?>">
                                        <span class="status-dot"></span>
                                        <?= ucfirst($status) ?>
                                    </span>
                                    <?php if (!empty($row['reviewer_name'])): ?>
                                        <small class="text-muted d-block mt-0.5" style="font-size: 0.72rem;">by <?= htmlspecialchars($row['reviewer_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary rounded-2 px-3 py-1"
                                            onclick="openReviewModal(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['title'])) ?>', '<?= addslashes(htmlspecialchars($row['employee_name'])) ?>', <?= $row['total_estimated_budget'] ?>, '<?= $status ?>', '<?= addslashes(htmlspecialchars($row['purpose'])) ?>', <?= (float)$row['approved_budget'] ?>, '<?= addslashes(htmlspecialchars($row['admin_remarks'] ?? '')) ?>')">
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
            <?php if (empty($tours)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-airplane fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No tour plans found.
                </div>
            <?php else: ?>
                <?php foreach ($tours as $row): 
                    $initial = strtoupper(substr($row['employee_name'], 0, 1));
                    $status = $row['status'];
                    $badgeClass = ($status === 'approved') ? 'status-approved' : (($status === 'rejected') ? 'status-rejected' : 'status-pending');
                ?>
                    <div class="tour-card-mobile tour-card-item"
                         data-name="<?= strtolower(htmlspecialchars($row['employee_name'])) ?>"
                         data-title="<?= strtolower(htmlspecialchars($row['title'])) ?>"
                         data-destination="<?= strtolower(htmlspecialchars($row['destination'])) ?>"
                         data-purpose="<?= strtolower(htmlspecialchars($row['purpose'])) ?>"
                         data-status="<?= $status ?>">
                        
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm bg-primary-subtle text-primary">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($row['title']) ?></h6>
                                    <small class="text-primary fw-medium"><?= htmlspecialchars($row['employee_name']) ?></small>
                                </div>
                            </div>
                            <span class="badge-status <?= $badgeClass ?>">
                                <span class="status-dot"></span>
                                <?= ucfirst($status) ?>
                            </span>
                        </div>

                        <!-- Schedule & Destination -->
                        <div class="p-2.5 rounded-3 bg-light my-2.5 small">
                            <div class="fw-bold text-dark mb-1">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($row['destination']) ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 0.8rem;">
                                <span><?= date('d M', strtotime($row['start_date'])) ?> — <?= date('d M, Y', strtotime($row['end_date'])) ?></span>
                                <span class="badge bg-white text-secondary border"><?= $row['total_days'] ?> Days</span>
                            </div>
                        </div>

                        <!-- Expenses & Budgets -->
                        <div class="d-flex justify-content-between align-items-end pt-1 mb-2">
                            <div>
                                <small class="text-muted d-block" style="font-size: 0.72rem;">Estimated Budget</small>
                                <strong class="fs-5 text-dark">৳<?= number_format($row['total_estimated_budget'], 2) ?></strong>
                                <?php if ($status === 'approved'): ?>
                                    <small class="text-success fw-bold d-block" style="font-size: 0.75rem;">
                                        Approved: ৳<?= number_format($row['approved_budget'], 2) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-primary rounded-2 px-3 py-1"
                                    onclick="openReviewModal(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['title'])) ?>', '<?= addslashes(htmlspecialchars($row['employee_name'])) ?>', <?= $row['total_estimated_budget'] ?>, '<?= $status ?>', '<?= addslashes(htmlspecialchars($row['purpose'])) ?>', <?= (float)$row['approved_budget'] ?>, '<?= addslashes(htmlspecialchars($row['admin_remarks'] ?? '')) ?>')">
                                <i class="bi bi-pencil-square me-1"></i> Review
                            </button>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Modal: Review / Approval -->
<div class="modal fade" id="reviewTourModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-sm bg-primary-subtle text-primary" style="width: 34px; height: 34px; font-size: 1rem; border-radius: 8px;">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Review Tour Plan</h6>
                        <small class="text-muted" style="font-size: 0.78rem;">Authorize or reject travel & budget allocation</small>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="review_tour">
                <input type="hidden" name="tour_id" id="modalTourId">
                <div class="modal-body p-4">
                    
                    <div class="p-3 bg-light rounded-3 mb-3 small">
                        <div class="mb-1">
                            <span class="text-muted">Tour:</span>
                            <strong id="modalTourTitle" class="text-dark ms-1"></strong>
                        </div>
                        <div class="mb-2">
                            <span class="text-muted">Officer:</span>
                            <strong id="modalTourEmp" class="text-primary ms-1"></strong>
                        </div>
                        <div class="border-top border-light-subtle pt-2">
                            <strong class="text-dark d-block mb-0.5">Purpose:</strong>
                            <span id="modalTourPurpose" class="text-secondary"></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Review Decision <span class="text-danger">*</span></label>
                        <select name="status_action" id="statusActionSelect" class="form-select" required onchange="toggleBudgetInput(this.value)">
                            <option value="approved">Approve Tour & Budget</option>
                            <option value="rejected">Reject Tour Plan</option>
                        </select>
                    </div>

                    <div class="mb-3" id="approvedBudgetContainer">
                        <label class="form-label">Approved Budget (৳) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="approved_budget" id="modalApprovedBudget" class="form-control" required>
                        <small class="text-muted" style="font-size: 0.75rem;">Estimated budget was: ৳<span id="modalEstBudgetLabel"></span></small>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Remarks / Instructions</label>
                        <input type="text" name="admin_remarks" id="modalAdminRemarks" class="form-control" placeholder="Optional notes for officer">
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

<!-- Modal: New Tour Submission Form -->
<!-- Modal: New Tour Submission Form -->
<div class="modal fade" id="createTourModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1.25rem; overflow: hidden;">
            
            <!-- Modal Header -->
            <div class="modal-header border-bottom border-light-subtle px-4 py-3" style="background: #ffffff;">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-icon-circle" style="width: 44px; height: 44px; background: #e0f2fe; color: #0284c7; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="bi bi-airplane-fill"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" style="font-size: 1.15rem; letter-spacing: -0.02em;">Create New Tour Plan</h5>
                        <p class="text-muted small mb-0" style="font-size: 0.82rem;">Submit itinerary details, journey schedule & TA/DA budget</p>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Form -->
            <form method="POST">
                <input type="hidden" name="create_tour" value="1">
                
                <div class="modal-body p-4" style="background: #fafafa;">

                    <!-- Section 1: Basic Information -->
                    <div class="card border border-light-subtle shadow-none rounded-3 p-3 mb-3 bg-white">
                        <span class="text-uppercase text-muted fw-bold mb-3 d-flex align-items-center gap-2" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                            <i class="bi bi-person-lines-fill text-primary"></i> Basic Information
                        </span>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Field Officer <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                    <select name="employee_id" class="form-select border-start-0" required>
                                        <option value="">-- Choose Employee --</option>
                                        <?php foreach ($empList as $e): ?>
                                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?> (<?= ucfirst($e['role']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tour Title <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-bookmark"></i></span>
                                    <input type="text" name="title" class="form-control border-start-0" placeholder="e.g. Chittagong Territory Expansion" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Destination Area / City <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-geo-alt"></i></span>
                                    <input type="text" name="destination" class="form-control border-start-0" placeholder="e.g. Agrabad, Chattogram" required>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" id="tourStartDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label">End Date <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" id="tourEndDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Estimated TA/DA Expenses -->
                    <div class="card border border-light-subtle shadow-none rounded-3 p-3 mb-3 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-uppercase text-muted fw-bold d-flex align-items-center gap-2" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                                <i class="bi bi-cash-stack text-success"></i> Estimated Expenses Breakdown (TA / DA)
                            </span>
                            <span class="badge bg-light text-secondary border fw-normal" id="tourDaysBadge">1 Day Journey</span>
                        </div>

                        <div class="row g-2.5">
                            <div class="col-sm-6 col-lg-3">
                                <div class="p-2.5 rounded-3 bg-light border border-light-subtle">
                                    <label class="form-label small mb-1 text-muted" style="font-size: 0.75rem;">Travel (TA) ৳</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-end-0 text-muted">৳</span>
                                        <input type="number" step="0.01" name="est_transport" class="form-control calc-cost text-end fw-semibold" value="0.00">
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="p-2.5 rounded-3 bg-light border border-light-subtle">
                                    <label class="form-label small mb-1 text-muted" style="font-size: 0.75rem;">Daily Allowance (DA) ৳</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-end-0 text-muted">৳</span>
                                        <input type="number" step="0.01" name="est_da" class="form-control calc-cost text-end fw-semibold" value="0.00">
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="p-2.5 rounded-3 bg-light border border-light-subtle">
                                    <label class="form-label small mb-1 text-muted" style="font-size: 0.75rem;">Hotel / Stay ৳</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-end-0 text-muted">৳</span>
                                        <input type="number" step="0.01" name="est_hotel" class="form-control calc-cost text-end fw-semibold" value="0.00">
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="p-2.5 rounded-3 bg-light border border-light-subtle">
                                    <label class="form-label small mb-1 text-muted" style="font-size: 0.75rem;">Other Expenses ৳</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-end-0 text-muted">৳</span>
                                        <input type="number" step="0.01" name="est_other" class="form-control calc-cost text-end fw-semibold" value="0.00">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Highlighted Total Budget Bar -->
                        <div class="mt-3 p-2.5 rounded-3 d-flex justify-content-between align-items-center" style="background: #f0fdf4; border: 1px dashed #86efac;">
                            <span class="text-success-emphasis small fw-semibold">
                                <i class="bi bi-wallet2 me-1"></i> Total Estimated Budget:
                            </span>
                            <span class="fs-5 fw-bold text-success">
                                ৳<span id="formTotalEst">0.00</span>
                            </span>
                        </div>
                    </div>

                    <!-- Section 3: Purpose / Objective -->
                    <div class="card border border-light-subtle shadow-none rounded-3 p-3 bg-white">
                        <label class="form-label">Tour Purpose / Objective <span class="text-danger">*</span></label>
                        <textarea name="purpose" class="form-control" rows="2" placeholder="Describe client meetings, visit objectives, or target stores..." required style="resize: none;"></textarea>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer border-top border-light-subtle px-4 py-3 bg-white d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light rounded-3 px-3.5 border fw-medium" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 shadow-sm fw-semibold d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-check2-circle"></i>
                        <span>Submit Tour Plan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openReviewModal(tourId, title, empName, estBudget, currentStatus, purpose, approvedBudget, remarks) {
    document.getElementById('modalTourId').value = tourId;
    document.getElementById('modalTourTitle').innerText = title;
    document.getElementById('modalTourEmp').innerText = empName;
    document.getElementById('modalTourPurpose').innerText = purpose || 'No details provided';
    document.getElementById('modalApprovedBudget').value = approvedBudget > 0 ? approvedBudget : estBudget;
    document.getElementById('modalEstBudgetLabel').innerText = Number(estBudget).toFixed(2);
    document.getElementById('modalAdminRemarks').value = remarks || '';
    
    const statusSelect = document.getElementById('statusActionSelect');
    statusSelect.value = (currentStatus === 'rejected') ? 'rejected' : 'approved';
    toggleBudgetInput(statusSelect.value);
    
    const modal = new bootstrap.Modal(document.getElementById('reviewTourModal'));
    modal.show();
}

function toggleBudgetInput(status) {
    const container = document.getElementById('approvedBudgetContainer');
    const budgetInput = document.getElementById('modalApprovedBudget');
    if (status === 'rejected') {
        container.style.display = 'none';
        budgetInput.required = false;
    } else {
        container.style.display = 'block';
        budgetInput.required = true;
    }
}

// Live calculation in creation modal
document.querySelectorAll('.calc-cost').forEach(input => {
    input.addEventListener('input', function() {
        let total = 0;
        document.querySelectorAll('.calc-cost').forEach(el => {
            total += parseFloat(el.value) || 0;
        });
        document.getElementById('formTotalEst').innerText = total.toFixed(2);
    });
});

// Live Search & Filter Logic
const searchInput = document.getElementById('liveSearchInput');
const statusFilter = document.getElementById('statusFilterSelect');
const tableRows = document.querySelectorAll('.tour-row');
const mobileCards = document.querySelectorAll('.tour-card-item');

function filterTours() {
    const query = searchInput.value.toLowerCase().trim();
    const filterStatus = statusFilter.value;

    // Desktop Table
    tableRows.forEach(row => {
        const name = row.getAttribute('data-name');
        const title = row.getAttribute('data-title');
        const destination = row.getAttribute('data-destination');
        const purpose = row.getAttribute('data-purpose');
        const status = row.getAttribute('data-status');

        const matchesSearch = name.includes(query) || title.includes(query) || destination.includes(query) || purpose.includes(query);
        const matchesStatus = (filterStatus === 'all') || (status === filterStatus);

        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });

    // Mobile Cards
    mobileCards.forEach(card => {
        const name = card.getAttribute('data-name');
        const title = card.getAttribute('data-title');
        const destination = card.getAttribute('data-destination');
        const purpose = card.getAttribute('data-purpose');
        const status = card.getAttribute('data-status');

        const matchesSearch = name.includes(query) || title.includes(query) || destination.includes(query) || purpose.includes(query);
        const matchesStatus = (filterStatus === 'all') || (status === filterStatus);

        card.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
}

// Dynamic Days Calculate & Live Budget Sum
const startDateInput = document.getElementById('tourStartDate');
const endDateInput = document.getElementById('tourEndDate');
const daysBadge = document.getElementById('tourDaysBadge');

function updateTourDays() {
    if (startDateInput && endDateInput && daysBadge) {
        const start = new Date(startDateInput.value);
        const end = new Date(endDateInput.value);
        
        if (!isNaN(start) && !isNaN(end) && end >= start) {
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            daysBadge.innerText = diffDays + ' Day(s) Journey';
        } else {
            daysBadge.innerText = 'Invalid Dates';
        }
    }
}

if (startDateInput && endDateInput) {
    startDateInput.addEventListener('change', updateTourDays);
    endDateInput.addEventListener('change', updateTourDays);
}

// Live calculation in creation modal
document.querySelectorAll('.calc-cost').forEach(input => {
    input.addEventListener('input', function() {
        let total = 0;
        document.querySelectorAll('.calc-cost').forEach(el => {
            total += parseFloat(el.value) || 0;
        });
        document.getElementById('formTotalEst').innerText = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    });
});

searchInput.addEventListener('input', filterTours);
statusFilter.addEventListener('change', filterTours);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>