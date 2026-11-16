<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/header.php';

$selectedDate = isset($_GET['date']) && !empty($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$methodFilter = isset($_GET['payment_method']) ? trim($_GET['payment_method']) : '';
$employeeFilter = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

$success = '';
$error = '';

// Handle Direct Collection Entry from Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_collection') {
    $empId   = (int)($_POST['employee_id'] ?? 0);
    $shop    = trim($_POST['shop_name'] ?? '');
    $phone   = trim($_POST['shop_phone'] ?? '');
    $amount  = (float)($_POST['amount'] ?? 0);
    $method  = trim($_POST['payment_method'] ?? 'cash');
    $refNo   = trim($_POST['reference_no'] ?? '');
    $colDate = trim($_POST['collection_date'] ?? date('Y-m-d'));
    $note    = trim($_POST['note'] ?? '');

    if ($empId <= 0 || empty($shop) || $amount <= 0) {
        $error = 'Employee, Shop Name and a valid Amount are required.';
    } else {
        $insStmt = $pdo->prepare("
            INSERT INTO collections (employee_id, shop_name, shop_phone, amount, payment_method, reference_no, collection_date, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($insStmt->execute([$empId, $shop, $phone, $amount, $method, $refNo, $colDate, $note])) {
            $success = 'Collection record added successfully!';
        } else {
            $error = 'Failed to insert collection record.';
        }
    }
}

// Fetch field staff for dropdowns
$empList = $pdo->query("SELECT id, name FROM users WHERE role IN ('employee', 'supervisor') ORDER BY name ASC")->fetchAll();

// Main Query for Collections List
$query = "
    SELECT c.*, u.name AS employee_name, u.phone AS employee_phone
    FROM collections c
    JOIN users u ON c.employee_id = u.id
    WHERE c.collection_date = :selected_date
";
$params = [':selected_date' => $selectedDate];

if (!empty($methodFilter)) {
    $query .= " AND c.payment_method = :method";
    $params[':method'] = $methodFilter;
}

if ($employeeFilter > 0) {
    $query .= " AND c.employee_id = :employee_id";
    $params[':employee_id'] = $employeeFilter;
}

$query .= " ORDER BY c.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$collections = $stmt->fetchAll();

// Financial Summaries for the selected day
$totalCollection = 0;
$cashCollection = 0;
$digitalCollection = 0;

foreach ($collections as $col) {
    $val = (float)$col['amount'];
    $totalCollection += $val;
    if ($col['payment_method'] === 'cash') {
        $cashCollection += $val;
    } else {
        $digitalCollection += $val;
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

    /* Method Badges */
    .badge-method-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .method-cash   { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
    .method-bkash  { background: #fdf2f8; color: #db2777; border: 1px solid #fce7f3; }
    .method-nagad  { background: #fffbeb; color: #d97706; border: 1px solid #fef3c7; }
    .method-bank   { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
    .method-cheque { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }

    /* Modal Styling */
    .modal-content {
        border-radius: 1.15rem;
        border: 1px solid var(--saas-border);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
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

    /* Mobile Responsive Setup */
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

    .collection-card-mobile {
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
            <h4 class="fw-bold mb-1 text-dark tracking-tight">Collection Monitoring</h4>
            <p class="text-muted small mb-0">Track field collections, cash reconciliations, and digital payment receipts</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCollectionModal" style="font-weight: 500;">
                <i class="bi bi-plus-lg"></i>
                <span>Add Collection</span>
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
                <div class="metric-icon-box bg-success-subtle text-success">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Total Collected</div>
                    <div class="fs-4 fw-bold text-dark">৳<?= number_format($totalCollection, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-primary-subtle text-primary">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Cash Received</div>
                    <div class="fs-4 fw-bold text-dark">৳<?= number_format($cashCollection, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-12 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-info-subtle text-info">
                    <i class="bi bi-phone"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Digital / Bank / Cheque</div>
                    <div class="fs-4 fw-bold text-dark">৳<?= number_format($digitalCollection, 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Control Card -->
    <div class="saas-card p-3 mb-4">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <span class="text-muted small fw-bold text-uppercase d-flex align-items-center gap-1">
                    <i class="bi bi-calendar3"></i> Date:
                </span>
            </div>
            <div class="col-auto">
                <input type="date" name="date" class="form-control form-control-sm rounded-3 border-light-subtle" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
            </div>

            <div class="col-auto ms-sm-2">
                <span class="text-muted small fw-bold text-uppercase d-flex align-items-center gap-1">
                    <i class="bi bi-credit-card"></i> Method:
                </span>
            </div>
            <div class="col-auto">
                <select name="payment_method" class="form-select form-select-sm rounded-3 border-light-subtle" onchange="this.form.submit()">
                    <option value="">All Methods</option>
                    <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="bkash" <?= $methodFilter === 'bkash' ? 'selected' : '' ?>>bKash</option>
                    <option value="nagad" <?= $methodFilter === 'nagad' ? 'selected' : '' ?>>Nagad</option>
                    <option value="bank" <?= $methodFilter === 'bank' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="cheque" <?= $methodFilter === 'cheque' ? 'selected' : '' ?>>Cheque</option>
                </select>
            </div>

            <div class="col-auto ms-sm-2">
                <span class="text-muted small fw-bold text-uppercase d-flex align-items-center gap-1">
                    <i class="bi bi-person-badge"></i> Staff:
                </span>
            </div>
            <div class="col-auto">
                <select name="employee_id" class="form-select form-select-sm rounded-3 border-light-subtle" onchange="this.form.submit()">
                    <option value="0">All Staff</option>
                    <?php foreach ($empList as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $employeeFilter == $emp['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-auto ms-auto">
                <a href="total_collection.php" class="btn btn-sm btn-light border rounded-3 px-3 text-secondary">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Data Container Card -->
    <div class="saas-card">
        
        <!-- Live Search Bar -->
        <div class="p-3 border-bottom border-light-subtle bg-white">
            <div class="row g-2 justify-content-between align-items-center">
                <div class="col-md-5 col-12">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 0.65rem 0 0 0.65rem; border-color: #e2e8f0;">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="liveSearchInput" class="form-control border-start-0" placeholder="Search by shop, phone, officer or note..." style="border-radius: 0 0.65rem 0.65rem 0; border-color: #e2e8f0;">
                    </div>
                </div>
                <div class="col-auto ms-auto text-muted small">
                    Showing records for <span class="fw-bold text-dark"><?= date('d M, Y', strtotime($selectedDate)) ?></span>
                </div>
            </div>
        </div>

        <!-- 1. Desktop Table View -->
        <div class="desktop-table-view table-responsive mb-0">
            <table class="table align-middle mb-0" id="collectionTable">
                <thead>
                    <tr>
                        <th class="ps-4">Shop Details</th>
                        <th>Collected Amount</th>
                        <th>Payment Method</th>
                        <th>Collected By</th>
                        <th>Time</th>
                        <th class="pe-4">Remarks / Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($collections)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-cash-coin fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                No collection entries found for this date.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($collections as $row): 
                            $initial = strtoupper(substr($row['shop_name'], 0, 1));
                            $method = strtolower($row['payment_method']);
                            $methodClass = 'method-' . $method;
                        ?>
                            <tr class="collection-row"
                                data-shop="<?= strtolower(htmlspecialchars($row['shop_name'])) ?>"
                                data-phone="<?= htmlspecialchars($row['shop_phone'] ?? '') ?>"
                                data-officer="<?= strtolower(htmlspecialchars($row['employee_name'])) ?>"
                                data-note="<?= strtolower(htmlspecialchars($row['note'] ?? '')) ?>"
                                data-ref="<?= strtolower(htmlspecialchars($row['reference_no'] ?? '')) ?>">
                                
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-sm bg-primary-subtle text-primary">
                                            <?= $initial ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($row['shop_name']) ?></div>
                                            <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['shop_phone'] ?? 'N/A') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fs-6 fw-bold text-success">৳<?= number_format($row['amount'], 2) ?></span>
                                </td>
                                <td>
                                    <span class="badge-method-pill <?= $methodClass ?>">
                                        <i class="bi bi-check-circle-fill" style="font-size: 0.65rem;"></i>
                                        <?= htmlspecialchars($row['payment_method']) ?>
                                    </span>
                                    <?php if (!empty($row['reference_no'])): ?>
                                        <small class="text-muted d-block mt-0.5" style="font-size: 0.75rem;">Ref: <?= htmlspecialchars($row['reference_no']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark"><?= htmlspecialchars($row['employee_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($row['employee_phone']) ?></small>
                                </td>
                                <td class="text-muted small">
                                    <i class="bi bi-clock me-1 text-muted"></i><?= date('h:i A', strtotime($row['created_at'])) ?>
                                </td>
                                <td class="pe-4">
                                    <small class="text-secondary"><?= !empty($row['note']) ? htmlspecialchars($row['note']) : '—' ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. Mobile Responsive Card View -->
        <div class="mobile-card-view">
            <?php if (empty($collections)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-cash-coin fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No collection entries found.
                </div>
            <?php else: ?>
                <?php foreach ($collections as $row): 
                    $initial = strtoupper(substr($row['shop_name'], 0, 1));
                    $method = strtolower($row['payment_method']);
                    $methodClass = 'method-' . $method;
                ?>
                    <div class="collection-card-mobile collection-card-item"
                         data-shop="<?= strtolower(htmlspecialchars($row['shop_name'])) ?>"
                         data-phone="<?= htmlspecialchars($row['shop_phone'] ?? '') ?>"
                         data-officer="<?= strtolower(htmlspecialchars($row['employee_name'])) ?>"
                         data-note="<?= strtolower(htmlspecialchars($row['note'] ?? '')) ?>"
                         data-ref="<?= strtolower(htmlspecialchars($row['reference_no'] ?? '')) ?>">
                        
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm bg-primary-subtle text-primary">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($row['shop_name']) ?></h6>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['shop_phone'] ?? 'N/A') ?></small>
                                </div>
                            </div>
                            <span class="badge-method-pill <?= $methodClass ?>">
                                <?= htmlspecialchars($row['payment_method']) ?>
                            </span>
                        </div>

                        <!-- Amount & Ref -->
                        <div class="p-2.5 rounded-3 bg-light my-3 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted d-block" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600;">Amount Received</span>
                                <strong class="fs-5 text-success">৳<?= number_format($row['amount'], 2) ?></strong>
                            </div>
                            <?php if (!empty($row['reference_no'])): ?>
                                <div class="text-end">
                                    <span class="text-muted d-block" style="font-size: 0.72rem;">Reference No.</span>
                                    <span class="badge bg-white text-secondary border fw-normal"><?= htmlspecialchars($row['reference_no']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Officer & Time Footer -->
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top border-light-subtle">
                            <div>
                                <span class="small fw-semibold text-dark d-block"><?= htmlspecialchars($row['employee_name']) ?></span>
                                <small class="text-muted"><?= date('h:i A', strtotime($row['created_at'])) ?></small>
                            </div>
                            <?php if (!empty($row['note'])): ?>
                                <small class="text-muted text-truncate" style="max-width: 140px;" title="<?= htmlspecialchars($row['note']) ?>">
                                    <i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars($row['note']) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Add Collection Modal -->
<div class="modal fade" id="addCollectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-sm bg-primary-subtle text-primary" style="width: 34px; height: 34px; font-size: 1rem; border-radius: 8px;">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Record New Collection</h6>
                        <small class="text-muted" style="font-size: 0.78rem;">Post shop payment or collection entry</small>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_collection">
                <div class="modal-body p-4">
                    
                    <div class="mb-3">
                        <label class="form-label">Field Staff / Rep <span class="text-danger">*</span></label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">-- Choose Field Representative --</option>
                            <?php foreach ($empList as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-7">
                            <label class="form-label">Shop Name <span class="text-danger">*</span></label>
                            <input type="text" name="shop_name" class="form-control" placeholder="e.g. Popular General Store" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Shop Phone</label>
                            <input type="tel" name="shop_phone" class="form-control" placeholder="01xxxxxxxxx">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Amount (৳) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required min="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="bkash">bKash</option>
                                <option value="nagad">Nagad</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Collection Date <span class="text-danger">*</span></label>
                            <input type="date" name="collection_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ref / Trx ID / Voucher</label>
                            <input type="text" name="reference_no" class="form-control" placeholder="Trx ID or Slip #">
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Remarks / Note</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Optional settlement or order reference notes..."></textarea>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 px-3 border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 shadow-sm">Save Collection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Live Search
    const searchInput = document.getElementById('liveSearchInput');
    const tableRows = document.querySelectorAll('.collection-row');
    const mobileCards = document.querySelectorAll('.collection-card-item');

    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();

        // Desktop Table Rows
        tableRows.forEach(row => {
            const shop = row.getAttribute('data-shop');
            const phone = row.getAttribute('data-phone');
            const officer = row.getAttribute('data-officer');
            const note = row.getAttribute('data-note');
            const ref = row.getAttribute('data-ref');

            const matches = shop.includes(query) || phone.includes(query) || officer.includes(query) || note.includes(query) || ref.includes(query);
            row.style.display = matches ? '' : 'none';
        });

        // Mobile Cards
        mobileCards.forEach(card => {
            const shop = card.getAttribute('data-shop');
            const phone = card.getAttribute('data-phone');
            const officer = card.getAttribute('data-officer');
            const note = card.getAttribute('data-note');
            const ref = card.getAttribute('data-ref');

            const matches = shop.includes(query) || phone.includes(query) || officer.includes(query) || note.includes(query) || ref.includes(query);
            card.style.display = matches ? '' : 'none';
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>