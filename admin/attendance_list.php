<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/header.php';

// ফিল্টার প্যারামিটার
$selectedDate = isset($_GET['date']) && !empty($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$empFilter    = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

// এমপ্লয়ি লিস্ট লোড (ফিল্টারের জন্য)
$empListStmt = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('employee', 'supervisor') ORDER BY name ASC");
$employees = $empListStmt->fetchAll();

// কুয়েরি প্রস্তুতকরণ
$query = "
    SELECT 
        a.id AS attendance_id,
        a.date,
        a.in_time,
        a.out_time,
        a.in_latitude,
        a.in_longitude,
        a.in_address,
        a.out_latitude,
        a.out_longitude,
        a.out_address,
        a.status,
        a.working_hours,
        u.id AS user_id,
        u.name AS employee_name,
        u.phone AS employee_phone,
        u.designation,
        u.role
    FROM attendances a
    JOIN users u ON a.user_id = u.id
    WHERE a.date = :sel_date
";

$params = [':sel_date' => $selectedDate];

if ($empFilter > 0) {
    $query .= " AND a.user_id = :emp_id";
    $params[':emp_id'] = $empFilter;
}

if (!empty($statusFilter)) {
    $query .= " AND a.status = :status";
    $params[':status'] = $statusFilter;
}

$query .= " ORDER BY a.in_time ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendances = $stmt->fetchAll();

// আজকের সামারি কাউন্ট
$presentCount = 0;
$lateCount    = 0;
$absentCount  = 0;

foreach ($attendances as $row) {
    if ($row['status'] === 'present') {
        $presentCount++;
    } elseif ($row['status'] === 'late') {
        $lateCount++;
    } elseif ($row['status'] === 'absent') {
        $absentCount++;
    }
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

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
        gap: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
    }
    .status-present {
        background: #ecfdf5;
        color: #059669;
        border: 1px solid #d1fae5;
    }
    .status-present .badge-status-dot { background: #10b981; }

    .status-late {
        background: #fffbeb;
        color: #b45309;
        border: 1px solid #fef3c7;
    }
    .status-late .badge-status-dot { background: #f59e0b; }

    .status-absent {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fee2e2;
    }
    .status-absent .badge-status-dot { background: #ef4444; }

    .badge-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }

    /* Form Controls */
    .form-control, .form-select {
        border-color: #e2e8f0;
        border-radius: 0.65rem;
        padding: 0.55rem 0.85rem;
        font-size: 0.9rem;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--saas-primary);
        box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.12);
    }

    /* Responsive Views */
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

    .attendance-card-mobile {
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
            <h4 class="fw-bold mb-1 text-dark tracking-tight">Field Attendance Management</h4>
            <p class="text-muted small mb-0">Live employee check-in & check-out timestamps, GPS location, and duty duration</p>
        </div>
        <div class="d-flex gap-2">
            <a href="reports.php?type=attendance" class="btn btn-outline-danger btn-sm rounded-3 px-3 py-2 d-inline-flex align-items-center gap-2">
                <i class="bi bi-printer"></i>
                <span>Export Attendance</span>
            </a>
        </div>
    </div>

    <!-- Quick Stat Widgets -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="metric-card">
                <div class="metric-icon-box bg-primary-subtle text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Total Logged</div>
                    <div class="fs-4 fw-bold text-dark"><?= count($attendances) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="metric-card">
                <div class="metric-icon-box bg-success-subtle text-success">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">On Time (Present)</div>
                    <div class="fs-4 fw-bold text-dark"><?= $presentCount ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="metric-card">
                <div class="metric-icon-box bg-warning-subtle text-warning-emphasis">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Late Arrivals</div>
                    <div class="fs-4 fw-bold text-dark"><?= $lateCount ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="metric-card">
                <div class="metric-icon-box bg-danger-subtle text-danger">
                    <i class="bi bi-person-x-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Absent / Off Duty</div>
                    <div class="fs-4 fw-bold text-dark"><?= $absentCount ?></div>
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
                <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
            </div>

            <div class="col-auto ms-sm-2">
                <span class="text-muted small fw-bold text-uppercase d-flex align-items-center gap-1">
                    <i class="bi bi-person-badge"></i> Staff:
                </span>
            </div>
            <div class="col-auto">
                <select name="employee_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All Staff Members</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $empFilter == $emp['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['name']) ?> (<?= ucfirst($emp['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-auto ms-sm-2">
                <span class="text-muted small fw-bold text-uppercase d-flex align-items-center gap-1">
                    <i class="bi bi-funnel"></i> Status:
                </span>
            </div>
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present (On Time)</option>
                    <option value="late" <?= $statusFilter === 'late' ? 'selected' : '' ?>>Late Arrival</option>
                    <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                </select>
            </div>

            <div class="col-auto ms-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-3 rounded-3">
                    <i class="bi bi-filter"></i> Apply
                </button>
                <a href="attendance_list.php" class="btn btn-light border btn-sm rounded-3 px-2 text-secondary" title="Reset Filters">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Data Container Card -->
    <div class="saas-card">
        
        <!-- Live Instant Search Bar -->
        <div class="p-3 border-bottom border-light-subtle bg-white">
            <div class="row g-2 justify-content-between align-items-center">
                <div class="col-md-5 col-12">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 0.65rem 0 0 0.65rem; border-color: #e2e8f0;">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="liveSearchInput" class="form-control border-start-0" placeholder="Search by staff name, phone or designation..." style="border-radius: 0 0.65rem 0.65rem 0; border-color: #e2e8f0;">
                    </div>
                </div>
                <div class="col-auto ms-auto text-muted small">
                    Showing logs for <strong class="text-dark"><?= date('d M, Y', strtotime($selectedDate)) ?></strong>
                </div>
            </div>
        </div>

        <!-- 1. Desktop Table View -->
        <div class="desktop-table-view table-responsive mb-0">
            <table class="table align-middle mb-0" id="attendanceTable">
                <thead>
                    <tr>
                        <th class="ps-4">Staff Details</th>
                        <th>Check-In (In Time)</th>
                        <th>Check-Out (Out Time)</th>
                        <th>Duty Duration</th>
                        <th>Status</th>
                        <th>Location Preview</th>
                        <th class="text-end pe-4">GPS Map</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendances)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                <strong><?= htmlspecialchars($selectedDate) ?></strong> তারিখে কোনো অ্যাটেনডেন্স রেকর্ড পাওয়া যায়নি।
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendances as $row): 
                            $initial = strtoupper(substr($row['employee_name'], 0, 1));
                            $isSupervisor = $row['role'] === 'supervisor';
                            $status = $row['status'];
                            $badgeClass = ($status === 'present') ? 'status-present' : (($status === 'late') ? 'status-late' : 'status-absent');
                            
                            $duration = 'Duty Ongoing';
                            if (!empty($row['out_time']) && (float)$row['working_hours'] > 0) {
                                $h = floor((float)$row['working_hours']);
                                $m = round(((float)$row['working_hours'] - $h) * 60);
                                $duration = "{$h}h {$m}m";
                            }
                            $hasGPS = !empty($row['in_latitude']) && !empty($row['in_longitude']);
                        ?>
                            <tr class="att-row"
                                data-name="<?= strtolower(htmlspecialchars($row['employee_name'])) ?>"
                                data-phone="<?= htmlspecialchars($row['employee_phone']) ?>"
                                data-designation="<?= strtolower(htmlspecialchars($row['designation'] ?? '')) ?>">
                                
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-sm <?= $isSupervisor ? 'bg-primary-subtle text-primary' : 'bg-light text-dark border' ?>">
                                            <?= $initial ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($row['employee_name']) ?></div>
                                            <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['employee_phone']) ?></small>
                                            <span class="badge bg-light text-secondary border fw-normal py-0.5 px-2" style="font-size: 0.72rem;">
                                                <?= htmlspecialchars($row['designation'] ?? ucfirst($row['role'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong class="text-primary small d-block">
                                        <i class="bi bi-box-arrow-in-right me-1"></i><?= date('h:i:s A', strtotime($row['in_time'])) ?>
                                    </strong>
                                    <?php if (!empty($row['in_address'])): ?>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 200px;" title="<?= htmlspecialchars($row['in_address']) ?>">
                                            <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($row['in_address']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['out_time'])): ?>
                                        <strong class="text-secondary small d-block">
                                            <i class="bi bi-box-arrow-right me-1"></i><?= date('h:i:s A', strtotime($row['out_time'])) ?>
                                        </strong>
                                        <?php if (!empty($row['out_address'])): ?>
                                            <small class="text-muted text-truncate d-inline-block" style="max-width: 200px;" title="<?= htmlspecialchars($row['out_address']) ?>">
                                                <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($row['out_address']) ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-medium py-1 px-2.5 rounded-pill" style="font-size: 0.75rem;">
                                            <i class="bi bi-clock-history me-1"></i>Active (In Field)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold <?= !empty($row['out_time']) ? 'text-dark' : 'text-primary' ?> small">
                                        <?= $duration ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status <?= $badgeClass ?>">
                                        <span class="badge-status-dot"></span>
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($hasGPS): ?>
                                        <small class="text-muted font-monospace d-block" style="font-size: 0.75rem;">
                                            <?= number_format((float)$row['in_latitude'], 4) ?>, <?= number_format((float)$row['in_longitude'], 4) ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border fw-normal">No GPS</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($hasGPS): ?>
                                        <div class="d-inline-flex gap-1">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger rounded-2 px-2.5 py-1 d-inline-flex align-items-center gap-1"
                                                    onclick="openAttMap('<?= addslashes(htmlspecialchars($row['employee_name'])) ?>', <?= $row['in_latitude'] ?>, <?= $row['in_longitude'] ?>, '<?= addslashes(htmlspecialchars($row['in_address'] ?? 'Check-in location')) ?>')">
                                                <i class="bi bi-geo-alt-fill"></i> Map
                                            </button>
                                            <a href="https://maps.google.com/?q=<?= $row['in_latitude'] ?>,<?= $row['in_longitude'] ?>" 
                                               target="_blank" 
                                               class="btn btn-sm btn-light border rounded-2 px-2 py-1 text-secondary" title="Open Google Maps">
                                                <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">--</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. Mobile Responsive Card View -->
        <div class="mobile-card-view">
            <?php if (empty($attendances)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    কোনো রেকর্ড পাওয়া যায়নি।
                </div>
            <?php else: ?>
                <?php foreach ($attendances as $row): 
                    $initial = strtoupper(substr($row['employee_name'], 0, 1));
                    $isSupervisor = $row['role'] === 'supervisor';
                    $status = $row['status'];
                    $badgeClass = ($status === 'present') ? 'status-present' : (($status === 'late') ? 'status-late' : 'status-absent');
                    
                    $duration = 'Duty Ongoing';
                    if (!empty($row['out_time']) && (float)$row['working_hours'] > 0) {
                        $h = floor((float)$row['working_hours']);
                        $m = round(((float)$row['working_hours'] - $h) * 60);
                        $duration = "{$h}h {$m}m";
                    }
                    $hasGPS = !empty($row['in_latitude']) && !empty($row['in_longitude']);
                ?>
                    <div class="attendance-card-mobile att-card-item"
                         data-name="<?= strtolower(htmlspecialchars($row['employee_name'])) ?>"
                         data-phone="<?= htmlspecialchars($row['employee_phone']) ?>"
                         data-designation="<?= strtolower(htmlspecialchars($row['designation'] ?? '')) ?>">
                        
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm <?= $isSupervisor ? 'bg-primary-subtle text-primary' : 'bg-light text-dark border' ?>">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($row['employee_name']) ?></h6>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['employee_phone']) ?></small>
                                </div>
                            </div>
                            <span class="badge-status <?= $badgeClass ?>">
                                <span class="badge-status-dot"></span>
                                <?= ucfirst($status) ?>
                            </span>
                        </div>

                        <!-- Check-in & Check-out Tiles -->
                        <div class="row g-2 text-center my-2.5">
                            <div class="col-6">
                                <div class="p-2 rounded-3 bg-light border border-light-subtle">
                                    <small class="text-muted d-block" style="font-size: 0.7rem; text-transform: uppercase;">In Time</small>
                                    <strong class="text-primary small">
                                        <?= date('h:i A', strtotime($row['in_time'])) ?>
                                    </strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 rounded-3 bg-light border border-light-subtle">
                                    <small class="text-muted d-block" style="font-size: 0.7rem; text-transform: uppercase;">Out Time</small>
                                    <strong class="<?= !empty($row['out_time']) ? 'text-secondary' : 'text-warning' ?> small">
                                        <?= !empty($row['out_time']) ? date('h:i A', strtotime($row['out_time'])) : 'Active' ?>
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <!-- Location & Duration Details -->
                        <div class="d-flex justify-content-between align-items-center small py-1 text-muted">
                            <span>Duty Duration: <strong class="text-dark"><?= $duration ?></strong></span>
                            <span class="badge bg-light text-secondary border fw-normal"><?= htmlspecialchars($row['designation'] ?? ucfirst($row['role'])) ?></span>
                        </div>

                        <?php if (!empty($row['in_address'])): ?>
                            <p class="text-muted small mb-2 text-truncate" style="font-size: 0.8rem;">
                                <i class="bi bi-geo-alt me-1 text-primary"></i><?= htmlspecialchars($row['in_address']) ?>
                            </p>
                        <?php endif; ?>

                        <!-- Footer Action Buttons -->
                        <div class="d-flex justify-content-end gap-2 pt-2 border-top border-light-subtle">
                            <?php if ($hasGPS): ?>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-danger rounded-2 px-3 py-1 d-inline-flex align-items-center gap-1"
                                        onclick="openAttMap('<?= addslashes(htmlspecialchars($row['employee_name'])) ?>', <?= $row['in_latitude'] ?>, <?= $row['in_longitude'] ?>, '<?= addslashes(htmlspecialchars($row['in_address'] ?? 'Check-in location')) ?>')">
                                    <i class="bi bi-geo-alt-fill"></i> View Map
                                </button>
                                <a href="https://maps.google.com/?q=<?= $row['in_latitude'] ?>,<?= $row['in_longitude'] ?>" 
                                   target="_blank" 
                                   class="btn btn-sm btn-light border rounded-2 px-2 py-1 text-secondary">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Modal: Interactive GPS Map -->
<div class="modal fade" id="attMapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom border-light-subtle py-3">
                <h6 class="modal-title fw-bold text-dark" id="modalEmpTitle">
                    <i class="bi bi-geo-alt-fill text-danger me-1"></i> Check-in GPS Location
                </h6>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="leafletMap" style="height: 420px; width: 100%;"></div>
            </div>
            <div class="modal-footer border-top border-light-subtle py-2 d-flex justify-content-between">
                <small class="text-muted" id="modalEmpAddress"></small>
                <button type="button" class="btn btn-secondary btn-sm rounded-3 px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    let attLeafletMap = null;
    let attMarker = null;

    function openAttMap(name, lat, lng, address) {
        document.getElementById('modalEmpTitle').innerHTML = '<i class="bi bi-geo-alt-fill text-danger me-1"></i> ' + name + ' - Check-in Location';
        document.getElementById('modalEmpAddress').innerText = address ? 'Address: ' + address : 'Coordinates: ' + lat + ', ' + lng;

        const mapModal = new bootstrap.Modal(document.getElementById('attMapModal'));
        mapModal.show();

        document.getElementById('attMapModal').addEventListener('shown.bs.modal', function () {
            if (!attLeafletMap) {
                attLeafletMap = L.map('leafletMap').setView([lat, lng], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(attLeafletMap);
            } else {
                attLeafletMap.setView([lat, lng], 16);
                attLeafletMap.invalidateSize();
            }

            if (attMarker) {
                attLeafletMap.removeLayer(attMarker);
            }

            attMarker = L.marker([lat, lng]).addTo(attLeafletMap)
                .bindPopup('<b>' + name + '</b><br>' + address).openPopup();
        }, { once: true });
    }

    // Live Instant Search Filter
    const searchInput = document.getElementById('liveSearchInput');
    const tableRows = document.querySelectorAll('.att-row');
    const mobileCards = document.querySelectorAll('.att-card-item');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();

            // Desktop Table Rows
            tableRows.forEach(row => {
                const name = row.getAttribute('data-name');
                const phone = row.getAttribute('data-phone');
                const designation = row.getAttribute('data-designation');

                const matches = name.includes(query) || phone.includes(query) || designation.includes(query);
                row.style.display = matches ? '' : 'none';
            });

            // Mobile Cards
            mobileCards.forEach(card => {
                const name = card.getAttribute('data-name');
                const phone = card.getAttribute('data-phone');
                const designation = card.getAttribute('data-designation');

                const matches = name.includes(query) || phone.includes(query) || designation.includes(query);
                card.style.display = matches ? '' : 'none';
            });
        });
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>