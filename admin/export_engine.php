<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Dhaka');

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    die("Access denied. Please login first.");
}

$type       = $_GET['type'] ?? 'attendance';
$format     = $_GET['format'] ?? 'pdf';
$fromDate   = $_GET['from_date'] ?? date('Y-m-d');
$toDate     = $_GET['to_date'] ?? date('Y-m-d');
$employeeId = (int)($_GET['employee_id'] ?? 0);

$data = [];
$columns = [];
$reportTitle = "";
$filterStaffName = "All Field Staff";

if ($employeeId > 0) {
    $empStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $empStmt->execute([$employeeId]);
    $filterStaffName = $empStmt->fetchColumn() ?: "Staff #{$employeeId}";
}

// ==========================================
// ১. ATTENDANCE REPORT
// ==========================================
if ($type === 'attendance') {
    $reportTitle = "Daily Attendance & Field Hours Statement";

    $query = "
        SELECT 
            a.date AS log_date,
            u.id AS emp_id,
            u.name AS emp_name,
            u.phone AS emp_phone,
            u.designation,
            a.in_time,
            a.out_time,
            a.status,
            a.working_hours
        FROM attendances a
        JOIN users u ON a.user_id = u.id
        WHERE a.date BETWEEN :from_date AND :to_date
    ";
    $params = [':from_date' => $fromDate, ':to_date' => $toDate];

    if ($employeeId > 0) {
        $query .= " AND a.user_id = :emp_id";
        $params[':emp_id'] = $employeeId;
    }

    $query .= " ORDER BY a.date DESC, a.in_time ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $columns = ['Date', 'Officer Name', 'Mobile', 'Designation', 'First In', 'Last Out', 'Duration', 'Status'];

    foreach ($rows as $r) {
        $inTimeFormatted  = !empty($r['in_time']) ? date('h:i A', strtotime($r['in_time'])) : '—';
        $outTimeFormatted = !empty($r['out_time']) ? date('h:i A', strtotime($r['out_time'])) : 'Active';

        $durationText = 'In Progress';
        if (!empty($r['out_time'])) {
            $hours = (float)($r['working_hours'] ?? 0);
            if ($hours > 0) {
                $h = floor($hours);
                $m = round(($hours - $h) * 60);
                $durationText = "{$h}h {$m}m";
            } else {
                $sec = max(0, strtotime($r['out_time']) - strtotime($r['in_time']));
                $h = floor($sec / 3600);
                $m = floor(($sec % 3600) / 60);
                $durationText = "{$h}h {$m}m";
            }
        }

        $data[] = [
            $r['log_date'],
            $r['emp_name'],
            $r['emp_phone'],
            $r['designation'] ?? 'Field Staff',
            $inTimeFormatted,
            $outTimeFormatted,
            $durationText,
            ucfirst($r['status'])
        ];
    }

// ==========================================
// ২. SALES REPORT
// ==========================================
} elseif ($type === 'sales') {
    $reportTitle = "Daily Sales & Retail Booking Statement";

    $query = "
        SELECT 
            o.id AS order_id,
            o.order_date,
            o.shop_name,
            o.shop_phone,
            o.shop_address,
            u.name AS emp_name,
            o.total_amount,
            o.payment_status,
            o.order_status,
            o.created_at
        FROM orders o
        JOIN users u ON o.employee_id = u.id
        WHERE o.order_date BETWEEN :from_date AND :to_date
    ";
    $params = [':from_date' => $fromDate, ':to_date' => $toDate];

    if ($employeeId > 0) {
        $query .= " AND o.employee_id = :emp_id";
        $params[':emp_id'] = $employeeId;
    }

    $query .= " ORDER BY o.order_date DESC, o.id DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    $collectionMap = [];
    if (!empty($orders)) {
        $orderIds = array_column($orders, 'order_id');
        $inClause = implode(',', array_fill(0, count($orderIds), '?'));
        
        $cStmt = $pdo->prepare("SELECT order_id, COALESCE(SUM(amount), 0) AS total_collected FROM collections WHERE order_id IN ($inClause) GROUP BY order_id");
        $cStmt->execute($orderIds);
        $cols = $cStmt->fetchAll();
        foreach ($cols as $c) {
            $collectionMap[$c['order_id']] = (float)$c['total_collected'];
        }
    }

    $columns = ['Order ID', 'Date', 'Shop Name', 'Phone', 'Address', 'Officer Name', 'Total (৳)', 'Paid (৳)', 'Due (৳)', 'Status', 'Time'];

    foreach ($orders as $o) {
        $orderId  = $o['order_id'];
        $totalVal = (float)$o['total_amount'];
        $paidVal  = isset($collectionMap[$orderId]) ? $collectionMap[$orderId] : ($o['payment_status'] === 'paid' ? $totalVal : 0.00);
        $dueVal   = max(0, $totalVal - $paidVal);

        $data[] = [
            '#ORD-' . $orderId,
            $o['order_date'],
            $o['shop_name'],
            $o['shop_phone'] ?? '—',
            $o['shop_address'] ?? '—',
            $o['emp_name'],
            number_format($totalVal, 2),
            number_format($paidVal, 2),
            number_format($dueVal, 2),
            strtoupper($o['payment_status']),
            date('h:i A', strtotime($o['created_at']))
        ];
    }

// ==========================================
// ৩. COLLECTION REPORT
// ==========================================
} elseif ($type === 'collection') {
    $reportTitle = "Total Collection & Cash Inflow Statement";
    $query = "
        SELECT c.collection_date, c.shop_name, c.shop_phone, c.amount, 
               c.payment_method, c.reference_no, u.name AS emp_name, c.note
        FROM collections c
        JOIN users u ON c.employee_id = u.id
        WHERE c.collection_date BETWEEN :from_date AND :to_date
    ";
    $params = [':from_date' => $fromDate, ':to_date' => $toDate];
    if ($employeeId > 0) {
        $query .= " AND c.employee_id = :emp_id";
        $params[':emp_id'] = $employeeId;
    }
    $query .= " ORDER BY c.collection_date DESC, c.id DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $columns = ['Collection Date', 'Shop Name', 'Phone', 'Amount (৳)', 'Method', 'Ref No', 'Collected By', 'Note'];
    foreach ($rows as $r) {
        $data[] = [
            $r['collection_date'],
            $r['shop_name'],
            $r['shop_phone'] ?? '—',
            number_format($r['amount'], 2),
            strtoupper($r['payment_method']),
            $r['reference_no'] ?? '—',
            $r['emp_name'],
            $r['note'] ?? ''
        ];
    }

// ==========================================
// ৪. MOVEMENT & GPS AUDIT
// ==========================================
} elseif ($type === 'movement') {
    $reportTitle = "Movement & GPS Ping Audit Trail";
    $query = "
        SELECT DATE(l.recorded_at) as log_date, u.name AS emp_name, u.phone AS emp_phone,
               l.address, l.latitude, l.longitude, l.battery_level, l.recorded_at
        FROM locations l
        JOIN users u ON l.user_id = u.id
        WHERE DATE(l.recorded_at) BETWEEN :from_date AND :to_date
    ";
    $params = [':from_date' => $fromDate, ':to_date' => $toDate];
    if ($employeeId > 0) {
        $query .= " AND l.user_id = :emp_id";
        $params[':emp_id'] = $employeeId;
    }
    $query .= " ORDER BY l.recorded_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $columns = ['Date', 'Officer Name', 'Officer Phone', 'Address / Area', 'Coordinates', 'Battery', 'Ping Time'];
    foreach ($rows as $r) {
        $data[] = [
            $r['log_date'],
            $r['emp_name'],
            $r['emp_phone'],
            $r['address'] ?? 'Spot Pinpoint',
            $r['latitude'] . ', ' . $r['longitude'],
            $r['battery_level'] ? $r['battery_level'] . '%' : 'N/A',
            date('h:i:s A', strtotime($r['recorded_at']))
        ];
    }
}

// ==========================================
// EXCEL (.CSV) EXPORT
// ==========================================
if ($format === 'excel') {
    $filename = $type . "_report_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, $columns);
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// ==========================================
// PRINT PREVIEW & PDF HTML
// ==========================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($reportTitle) ?> - Teams Locator Management</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --brand-primary: #0284c7;
            --brand-dark: #0f172a;
            --brand-slate: #334155;
            --border-soft: #cbd5e1;
        }

        body {
            background-color: #f1f5f9;
            color: #0f172a;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.8rem;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }

        .report-page-container {
            width: 100%;
            max-width: 1380px;
            margin: 15px auto;
            background: #ffffff;
            border: 1px solid var(--border-soft);
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        }

        /* Corporate Letterhead */
        .company-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }

        .brand-logo-box {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
            color: #ffffff;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
        }

        .company-name {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
            line-height: 1.1;
        }

        .company-address {
            font-size: 0.72rem;
            color: #475569;
            margin-top: 3px;
            line-height: 1.3;
        }

        /* Fixed Grid for Metadata (Prevent wrap breakdown on print) */
        .meta-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 14px;
            margin-bottom: 14px;
        }

        .meta-label {
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
            font-size: 0.64rem;
            letter-spacing: 0.04em;
            display: block;
            margin-bottom: 2px;
        }
        .meta-value {
            color: #0f172a;
            font-weight: 700;
            font-size: 0.76rem;
            word-break: break-word;
        }

        /* Report Table Styling */
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .table-custom th {
            background-color: #0f172a !important;
            color: #ffffff !important;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 6px 7px;
            border: 1px solid #334155 !important;
            text-align: left;
            white-space: nowrap;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .table-custom td {
            font-size: 0.72rem;
            padding: 5px 7px;
            vertical-align: middle;
            border: 1px solid #cbd5e1;
            color: #1e293b;
        }

        .table-custom tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* Numeric Columns Right Align */
        .text-num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .nowrap-col {
            white-space: nowrap;
        }

        .text-address {
            max-width: 160px;
            line-height: 1.25;
            word-break: break-word;
        }

        .badge-print {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border: 1px solid transparent;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .badge-success-p { background-color: #dcfce7; color: #15803d; border-color: #86efac; }
        .badge-danger-p  { background-color: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
        .badge-warning-p { background-color: #fef3c7; color: #b45309; border-color: #fde68a; }

        /* Signatures Section */
        .signature-block {
            margin-top: 3rem;
            page-break-inside: avoid;
        }
        .sig-col {
            text-align: center;
        }
        .sig-line {
            width: 75%;
            border-top: 1px solid #64748b;
            margin: 0 auto 4px;
        }
        .sig-title {
            font-size: 0.72rem;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Print Media Setup */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .report-page-container {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                margin: 0 !important;
            }
            .meta-strip {
                background-color: #f8fafc !important;
                border: 1px solid #cbd5e1 !important;
            }
            @page {
                size: A4 landscape;
                margin: 8mm 10mm;
            }
        }
    </style>
</head>
<body>

    <!-- On-screen Action Navbar (Hidden in Print/PDF) -->
    <div class="no-print sticky-top bg-white border-bottom shadow-sm py-2 px-3 mb-2">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1">
                    <i class="bi bi-printer me-1"></i> Print & PDF Preview
                </span>
                <span class="text-muted small d-none d-md-inline">Paper format: <strong>A4 Landscape</strong>. Use standard browser print/save.</span>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-danger btn-sm rounded-3 px-3.5 d-inline-flex align-items-center gap-1.5 shadow-sm fw-semibold">
                    <i class="bi bi-printer-fill"></i>
                    <span>Print / Save PDF</span>
                </button>
                <button onclick="window.close()" class="btn btn-light border btn-sm rounded-3 px-3 text-secondary">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Main Document Container -->
    <div class="report-page-container">

        <!-- Corporate Letterhead Header -->
        <div class="company-header">
            <div class="d-flex align-items-center gap-2.5">
                <div class="brand-logo-box">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div>
                    <div class="company-name">Teams Locator Management</div>
                    <div class="company-address">
                        Level 7, Software Technology Park, Kawran Bazar, Dhaka-1215 | Helpline: +880 1700-000000 | Email: support@teamslocator.com
                    </div>
                </div>
            </div>
            <div class="text-end">
                <span class="badge bg-dark text-white text-uppercase px-2 py-1 fw-bold" style="letter-spacing: 0.05em; font-size: 0.65rem;">
                    Official Field Audit Report
                </span>
                <div class="text-muted mt-1" style="font-size: 0.7rem;">
                    Generated: <strong><?= date('d M, Y - h:i A') ?></strong>
                </div>
            </div>
        </div>

        <!-- Metadata Info Grid -->
        <div class="meta-strip">
            <div>
                <span class="meta-label">Report Category</span>
                <span class="meta-value text-primary"><?= htmlspecialchars($reportTitle) ?></span>
            </div>
            <div>
                <span class="meta-label">Date Range Period</span>
                <span class="meta-value"><?= date('d M, Y', strtotime($fromDate)) ?> — <?= date('d M, Y', strtotime($toDate)) ?></span>
            </div>
            <div>
                <span class="meta-label">Staff Filter Scope</span>
                <span class="meta-value"><?= htmlspecialchars($filterStaffName) ?></span>
            </div>
            <div class="text-end">
                <span class="meta-label">Generated By</span>
                <span class="meta-value"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?> (<?= ucfirst($_SESSION['role'] ?? 'admin') ?>)</span>
            </div>
        </div>

        <!-- Data Presentation Table -->
        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width: 28px;" class="text-center">#</th>
                    <?php foreach ($columns as $col): ?>
                        <th class="<?= in_array($col, ['Total (৳)', 'Paid (৳)', 'Due (৳)', 'Amount (৳)']) ? 'text-num' : '' ?>">
                            <?= htmlspecialchars($col) ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="<?= count($columns) + 1 ?>" class="text-center py-4 text-muted">
                            No records found within the selected date range.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data as $index => $row): ?>
                        <tr>
                            <td class="text-center fw-bold text-muted"><?= $index + 1 ?></td>
                            <?php foreach ($row as $colIndex => $val): ?>
                                <?php 
                                    $colName = $columns[$colIndex] ?? '';
                                    $cleanVal = strtolower(trim((string)$val));
                                    $isNumCol = in_array($colName, ['Total (৳)', 'Paid (৳)', 'Due (৳)', 'Amount (৳)']);
                                    $isNowrap = in_array($colName, ['Date', 'Order ID', 'Phone', 'Time', 'First In', 'Last Out', 'Duration']);
                                    $isAddress = in_array($colName, ['Address', 'Address / Area']);
                                ?>
                                <td class="<?= $isNumCol ? 'text-num fw-semibold' : '' ?> <?= $isNowrap ? 'nowrap-col' : '' ?> <?= $isAddress ? 'text-address' : '' ?>">
                                    <?php if ($colName === 'Status'): ?>
                                        <?php if (in_array($cleanVal, ['paid', 'present', 'approved', 'active'])): ?>
                                            <span class="badge-print badge-success-p"><?= htmlspecialchars($val) ?></span>
                                        <?php elseif (in_array($cleanVal, ['absent', 'rejected', 'inactive'])): ?>
                                            <span class="badge-print badge-danger-p"><?= htmlspecialchars($val) ?></span>
                                        <?php elseif (in_array($cleanVal, ['due', 'late', 'pending'])): ?>
                                            <span class="badge-print badge-warning-p"><?= htmlspecialchars($val) ?></span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($val) ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($val) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Summary Bar -->
        <div class="d-flex justify-content-between align-items-center mt-2 px-2 py-1.5 bg-light border border-light-subtle rounded text-muted" style="font-size: 0.72rem;">
            <span>System Statement Reference: <strong class="text-dark font-monospace">TLM-REP-<?= strtoupper(substr(md5($reportTitle . time()), 0, 8)) ?></strong></span>
            <span>Total Rows Logged: <strong class="text-dark"><?= count($data) ?> Records</strong></span>
        </div>

        <!-- Authorization & Signature Block -->
        <div class="signature-block">
            <div class="row">
                <div class="col-4 sig-col">
                    <div class="sig-line"></div>
                    <div class="sig-title">Prepared By (Field Auditor)</div>
                </div>
                <div class="col-4 sig-col">
                    <div class="sig-line"></div>
                    <div class="sig-title">Verified By (Operations/HR)</div>
                </div>
                <div class="col-4 sig-col">
                    <div class="sig-line"></div>
                    <div class="sig-title">Managing Authority Approval</div>
                </div>
            </div>
            <div class="text-center mt-3 text-muted" style="font-size: 0.65rem;">
                This document is generated digitally from <strong>Teams Locator Management System</strong>. Any unauthorized alteration or reproduction is strictly prohibited.
            </div>
        </div>

    </div>

    <!-- Auto Print Trigger -->
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 600);
        });
    </script>
</body>
</html>