<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/header.php';

$reportType = isset($_GET['type']) ? trim($_GET['type']) : 'attendance';
$fromDate   = isset($_GET['from_date']) ? trim($_GET['from_date']) : date('Y-m-01');
$toDate     = isset($_GET['to_date']) ? trim($_GET['to_date']) : date('Y-m-d');
$empFilter  = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

$empList = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('employee', 'supervisor') ORDER BY name ASC")->fetchAll();

$reportTitles = [
    'attendance' => 'Daily Attendance & Working Hours Report',
    'movement'   => 'Field Movement & GPS Audit Log',
    'sales'      => 'Daily Sales & Order Statement',
    'collection' => 'Total Collection & Financial Statement'
];
$activeTitle = $reportTitles[$reportType] ?? 'Report';
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

    /* Main Container Card */
    .saas-card {
        background: #ffffff;
        border: 1px solid var(--saas-border);
        border-radius: 1.15rem;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        overflow: hidden;
    }

    /* Modern Report Tabs Container */
    .report-tabs-bar {
        background: #f1f5f9;
        padding: 0.35rem;
        border-radius: 0.85rem;
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.25rem;
    }

    .report-tab-btn {
        padding: 0.55rem 1.15rem;
        border-radius: 0.65rem;
        color: var(--saas-text-muted);
        font-weight: 600;
        font-size: 0.88rem;
        text-decoration: none;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
    }

    .report-tab-btn:hover {
        color: var(--saas-text-dark);
        background: rgba(255, 255, 255, 0.6);
    }

    .report-tab-btn.active {
        background: #ffffff;
        color: var(--saas-primary);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    /* Form Labels & Inputs */
    .form-label {
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.04em;
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

    .form-control, .form-select {
        border-color: #e2e8f0;
        padding: 0.65rem 0.9rem;
        font-size: 0.92rem;
        color: var(--saas-text-dark);
        border-radius: 0 0.75rem 0.75rem 0;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--saas-primary);
        box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.12);
    }

    /* Export Buttons */
    .btn-export-excel {
        background: #10b981;
        color: #ffffff;
        font-weight: 600;
        border: none;
        padding: 0.65rem 1.15rem;
        border-radius: 0.75rem;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        transition: all 0.2s ease;
    }
    .btn-export-excel:hover {
        background: #059669;
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
    }

    .btn-export-pdf {
        background: #ef4444;
        color: #ffffff;
        font-weight: 600;
        border: none;
        padding: 0.65rem 1.15rem;
        border-radius: 0.75rem;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
        transition: all 0.2s ease;
    }
    .btn-export-pdf:hover {
        background: #dc2626;
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(239, 68, 68, 0.35);
    }

    /* Date Preset Pills */
    .preset-pill {
        font-size: 0.75rem;
        padding: 0.2rem 0.6rem;
        border-radius: 6px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #475569;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.15s ease;
        cursor: pointer;
    }
    .preset-pill:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    /* Info Feature Card */
    .feature-info-card {
        background: #ffffff;
        border: 1px solid var(--saas-border);
        border-radius: 1.15rem;
        padding: 1.35rem 1.25rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        height: 100%;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .feature-info-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px -2px rgba(0, 0, 0, 0.06);
    }

    .feature-icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
    }

    @media (max-width: 991.98px) {
        .report-tabs-bar {
            width: 100%;
        }
        .report-tab-btn {
            flex: 1 1 45%;
            justify-content: center;
        }
    }
</style>

<div class="container-fluid px-lg-4 py-3">

    <!-- Header Section -->
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark tracking-tight">Reports & Data Export Center</h4>
            <p class="text-muted small mb-0">Export field attendance, GPS trails, sales invoices, and collection statements</p>
        </div>
    </div>

    <!-- 4-Segmented Navigation Tabs -->
    <div class="mb-4">
        <div class="report-tabs-bar">
            <!-- 1. Attendance -->
            <a class="report-tab-btn <?= $reportType === 'attendance' ? 'active' : '' ?>" href="reports.php?type=attendance">
                <i class="bi bi-clock-history"></i>
                <span>Attendance Log</span>
            </a>
            <!-- 2. Movement -->
            <a class="report-tab-btn <?= $reportType === 'movement' ? 'active' : '' ?>" href="reports.php?type=movement">
                <i class="bi bi-geo-alt"></i>
                <span>Movement Audit</span>
            </a>
            <!-- 3. Sales -->
            <a class="report-tab-btn <?= $reportType === 'sales' ? 'active' : '' ?>" href="reports.php?type=sales">
                <i class="bi bi-cart3"></i>
                <span>Sales Orders</span>
            </a>
            <!-- 4. Collection -->
            <a class="report-tab-btn <?= $reportType === 'collection' ? 'active' : '' ?>" href="reports.php?type=collection">
                <i class="bi bi-wallet2"></i>
                <span>Collections</span>
            </a>
        </div>
    </div>

    <!-- Filter & Export Card -->
    <div class="saas-card mb-4">
        <div class="p-3 border-bottom border-light-subtle bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="feature-icon-box bg-primary-subtle text-primary" style="width: 36px; height: 36px; font-size: 1.1rem; border-radius: 8px;">
                    <i class="bi bi-funnel-fill"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Generate <?= htmlspecialchars($activeTitle) ?></h6>
                    <small class="text-muted" style="font-size: 0.78rem;">Choose reporting dates and staff filter for generation</small>
                </div>
            </div>

            <!-- Date Range Quick Presets -->
            <div class="d-flex align-items-center gap-1.5">
                <span class="text-muted small me-1 d-none d-sm-inline" style="font-size: 0.75rem;">Presets:</span>
                <span class="preset-pill" onclick="setDateRange('today')">Today</span>
                <span class="preset-pill" onclick="setDateRange('this_month')">This Month</span>
                <span class="preset-pill" onclick="setDateRange('last_30')">Last 30 Days</span>
            </div>
        </div>

        <div class="p-4 bg-white">
            <form method="GET" action="export_engine.php" target="_blank" class="row g-3 align-items-end">
                <input type="hidden" name="type" value="<?= htmlspecialchars($reportType) ?>">

                <!-- From Date -->
                <div class="col-lg-3 col-sm-6">
                    <label class="form-label">From Date <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                        <input type="date" name="from_date" id="inputFromDate" class="form-control" value="<?= htmlspecialchars($fromDate) ?>" required>
                    </div>
                </div>

                <!-- To Date -->
                <div class="col-lg-3 col-sm-6">
                    <label class="form-label">To Date <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
                        <input type="date" name="to_date" id="inputToDate" class="form-control" value="<?= htmlspecialchars($toDate) ?>" required>
                    </div>
                </div>

                <!-- Field Staff Selector -->
                <div class="col-lg-3 col-sm-12">
                    <label class="form-label">Field Representative</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                        <select name="employee_id" class="form-select">
                            <option value="0">All Staff Members</option>
                            <?php foreach ($empList as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= $empFilter == $e['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($e['name']) ?> (<?= ucfirst($e['role']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Export Action Buttons -->
                <div class="col-lg-3 col-sm-12">
                    <div class="d-flex gap-2">
                        <button type="submit" name="format" value="excel" class="btn btn-export-excel w-50 d-inline-flex align-items-center justify-content-center gap-1.5">
                            <i class="bi bi-file-earmark-excel"></i>
                            <span>Excel</span>
                        </button>
                        <button type="submit" name="format" value="pdf" class="btn btn-export-pdf w-50 d-inline-flex align-items-center justify-content-center gap-1.5">
                            <i class="bi bi-file-earmark-pdf"></i>
                            <span>PDF / Print</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Instructions / Capability Highlights -->
    <div class="row g-3">
        
        <!-- 1. Excel Format -->
        <div class="col-lg-4 col-md-6 col-12">
            <div class="feature-info-card">
                <div class="feature-icon-box bg-success-subtle text-success">
                    <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">Excel Compatibility</h6>
                    <small class="text-muted d-block" style="line-height: 1.45;">
                        UTF-8 এনকোডেড এক্সেল ফাইল ডাউনলোড হবে যা Microsoft Excel বা Google Sheets-এ বাংলা ও ইংরেজি পরিষ্কারভাবে ওপেন হয়।
                    </small>
                </div>
            </div>
        </div>

        <!-- 2. PDF & Printable View -->
        <div class="col-lg-4 col-md-6 col-12">
            <div class="feature-info-card">
                <div class="feature-icon-box bg-danger-subtle text-danger">
                    <i class="bi bi-printer-fill"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">Print / Save as PDF</h6>
                    <small class="text-muted d-block" style="line-height: 1.45;">
                        PDF অপশনে ক্লিক করলে ব্রাউজার অটোমেটিক প্রিন্ট প্রিভিউ দেখাবে, সেখান থেকে সরাসরি প্রিন্ট বা 'Save as PDF' করতে পারবেন।
                    </small>
                </div>
            </div>
        </div>

        <!-- 3. Flexible Date Filtering -->
        <div class="col-lg-4 col-md-12 col-12">
            <div class="feature-info-card">
                <div class="feature-icon-box bg-primary-subtle text-primary">
                    <i class="bi bi-calendar-range-fill"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">Date Range Filtering</h6>
                    <small class="text-muted d-block" style="line-height: 1.45;">
                        নির্দিষ্ট কোনো দিন, সপ্তাহ কিংবা পুরো মাসের অডিট স্টেটমেন্ট ও ফিল্ড রিপোর্ট আলাদাভাবে সংগ্রহ করা যাবে।
                    </small>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
    // Quick Date Range Preset Selector
    function setDateRange(rangeType) {
        const fromInput = document.getElementById('inputFromDate');
        const toInput = document.getElementById('inputToDate');
        const today = new Date();

        const formatDate = (date) => {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        };

        if (rangeType === 'today') {
            const todayStr = formatDate(today);
            fromInput.value = todayStr;
            toInput.value = todayStr;
        } else if (rangeType === 'this_month') {
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            fromInput.value = formatDate(firstDay);
            toInput.value = formatDate(today);
        } else if (rangeType === 'last_30') {
            const past30 = new Date();
            past30.setDate(today.getDate() - 30);
            fromInput.value = formatDate(past30);
            toInput.value = formatDate(today);
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>