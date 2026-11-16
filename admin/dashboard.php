<?php
require_once __DIR__ . '/includes/header.php';

$todayDate = date('Y-m-d');

// ১. স্টাফ সংখ্যা গণনা
$totalEmployees   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn();
$totalSupervisors = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'supervisor'")->fetchColumn();
$totalAdmins      = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin', 'super_admin')")->fetchColumn();

// ২. প্রোডাক্ট এবং আজকের সেলস সংক্রান্ত পরিসংখ্যান
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

$todaySalesStmt = $pdo->prepare("
    SELECT COUNT(id) as total_orders, COALESCE(SUM(total_amount), 0) as total_revenue 
    FROM orders 
    WHERE order_date = ?
");
$todaySalesStmt->execute([$todayDate]);
$todaySales = $todaySalesStmt->fetch();
$todayOrdersCount = (int)($todaySales['total_orders'] ?? 0);
$todayRevenue     = (float)($todaySales['total_revenue'] ?? 0);
?>

<style>
    :root {
        --saas-border: #e2e8f0;
        --saas-bg-muted: #f8fafc;
        --saas-text-dark: #0f172a;
        --saas-text-muted: #64748b;
        --saas-primary: #0284c7;
    }

    /* Welcome Hero Banner */
    .welcome-banner {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-radius: 1.25rem;
        padding: 2.25rem 2rem;
        color: #ffffff;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15);
    }
    .welcome-banner::after {
        content: '';
        position: absolute;
        top: -40%;
        right: -10%;
        width: 340px;
        height: 340px;
        background: radial-gradient(circle, rgba(56, 189, 248, 0.22) 0%, transparent 70%);
        pointer-events: none;
    }

    /* Metric Stat Cards */
    .metric-stat-card {
        background: #ffffff;
        border: 1px solid var(--saas-border);
        border-radius: 1.15rem;
        padding: 1.35rem 1.4rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        height: 100%;
    }
    .metric-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 22px -4px rgba(0, 0, 0, 0.08);
    }
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.45rem;
        flex-shrink: 0;
    }

    /* Section Header */
    .section-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--saas-text-dark);
        letter-spacing: -0.01em;
    }

    /* Modern Quick Action Grid Tile */
    .shortcut-card {
        background: #ffffff;
        border: 1px solid var(--saas-border);
        border-radius: 1.15rem;
        padding: 1.35rem 1.25rem;
        text-decoration: none;
        color: var(--saas-text-dark);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .shortcut-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.08);
        border-color: #cbd5e1;
        color: var(--saas-text-dark);
    }

    .shortcut-icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        margin-bottom: 1rem;
        transition: transform 0.2s ease;
    }

    .shortcut-card:hover .shortcut-icon-box {
        transform: scale(1.08);
    }

    .shortcut-title {
        font-weight: 700;
        font-size: 0.96rem;
        margin-bottom: 0.25rem;
        color: var(--saas-text-dark);
    }

    .shortcut-desc {
        font-size: 0.78rem;
        color: var(--saas-text-muted);
        line-height: 1.35;
        margin-bottom: 0.85rem;
    }

    .shortcut-arrow {
        font-size: 0.8rem;
        font-weight: 600;
        color: #94a3b8;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        transition: transform 0.2s ease, color 0.2s ease;
    }

    .shortcut-card:hover .shortcut-arrow {
        color: var(--saas-primary);
        transform: translateX(4px);
    }

    /* Live Operational Pulse Indicator */
    .system-pulse {
        width: 8px;
        height: 8px;
        background-color: #10b981;
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
    }

    /* Responsive Grid Setup */
    @media (max-width: 575.98px) {
        .shortcut-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 0.75rem !important;
        }
        .shortcut-card {
            padding: 1rem 0.85rem;
        }
        .shortcut-icon-box {
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
            margin-bottom: 0.75rem;
        }
        .shortcut-title {
            font-size: 0.86rem;
        }
        .shortcut-desc {
            display: none;
        }
    }
</style>

<div class="container-fluid px-lg-4 py-3">

    <!-- Hero / Welcome Banner -->
    <div class="welcome-banner mb-4">
        <div class="row align-items-center position-relative" style="z-index: 1;">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2.5 py-1 text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                        <i class="bi bi-shield-check me-1"></i> Operations Command Hub
                    </span>
                    <span class="badge bg-dark-subtle text-light border border-white border-opacity-10 px-2.5 py-1 fw-normal small d-none d-sm-inline-flex align-items-center gap-1.5">
                        <span class="system-pulse"></span> Live System Active
                    </span>
                </div>
                <h3 class="fw-bold mb-1 text-white">Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>!</h3>
                <p class="text-light opacity-75 mb-0 small">
                    Monitor field representatives, manage retail operations, and streamline daily operational logistics.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <a href="tracking_list.php" class="btn btn-light rounded-3 px-3.5 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2">
                    <i class="bi bi-radar text-primary"></i>
                    <span>Live GPS Tracking</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Row 1: Core Team Counters -->
    <div class="row g-3 mb-3">
        <!-- Employees -->
        <div class="col-md-4 col-sm-6">
            <div class="metric-stat-card">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Field Employees</span>
                    <h2 class="fw-bold text-dark mb-0"><?= number_format($totalEmployees) ?></h2>
                    <small class="text-muted" style="font-size: 0.8rem;">Field officers & reps</small>
                </div>
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
            </div>
        </div>

        <!-- Supervisors -->
        <div class="col-md-4 col-sm-6">
            <div class="metric-stat-card">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Team Supervisors</span>
                    <h2 class="fw-bold text-dark mb-0"><?= number_format($totalSupervisors) ?></h2>
                    <small class="text-muted" style="font-size: 0.8rem;">Area team supervisors</small>
                </div>
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="bi bi-shield-check"></i>
                </div>
            </div>
        </div>

        <!-- Total Admins -->
        <div class="col-md-4 col-sm-12">
            <div class="metric-stat-card">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">System Admins</span>
                    <h2 class="fw-bold text-dark mb-0"><?= number_format($totalAdmins) ?></h2>
                    <small class="text-muted" style="font-size: 0.8rem;">Console controllers</small>
                </div>
                <div class="stat-icon bg-dark-subtle text-dark">
                    <i class="bi bi-person-gear"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Today's Commercial Overview -->
    <div class="row g-3 mb-4">
        <!-- Today Sales Amount -->
        <div class="col-md-4 col-sm-6">
            <div class="metric-stat-card border-primary-subtle">
                <div>
                    <span class="text-primary small fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Today's Booked Sales</span>
                    <h3 class="fw-bold text-primary mb-0">৳<?= number_format($todayRevenue, 2) ?></h3>
                    <small class="text-muted" style="font-size: 0.8rem;">Date: <?= date('d M, Y') ?></small>
                </div>
                <div class="stat-icon bg-primary text-white shadow-sm">
                    <i class="bi bi-currency-exchange"></i>
                </div>
            </div>
        </div>

        <!-- Today Orders Count -->
        <div class="col-md-4 col-sm-6">
            <div class="metric-stat-card">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Today's Orders Count</span>
                    <h3 class="fw-bold text-dark mb-0"><?= number_format($todayOrdersCount) ?> Orders</h3>
                    <small class="text-muted" style="font-size: 0.8rem;">Booked by field officers</small>
                </div>
                <div class="stat-icon bg-info-subtle text-info">
                    <i class="bi bi-cart-check-fill"></i>
                </div>
            </div>
        </div>

        <!-- Product Catalog Items -->
        <div class="col-md-4 col-sm-12">
            <div class="metric-stat-card">
                <div>
                    <span class="text-muted small fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Active Catalog</span>
                    <h3 class="fw-bold text-dark mb-0"><?= number_format($totalProducts) ?> Products</h3>
                    <small class="text-muted" style="font-size: 0.8rem;">Stock & pricing rules</small>
                </div>
                <div class="stat-icon bg-warning-subtle text-warning-emphasis">
                    <i class="bi bi-box-seam-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Shortcuts / Navigation Hub -->
    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="section-title mb-0">Quick Shortcuts & Management Hub</h5>
                <small class="text-muted">Primary administrative modules and operational controls</small>
            </div>
        </div>

        <!-- 12 Balanced Responsive Service Hub Tiles -->
        <div class="row g-3 shortcut-grid">
            
            <!-- 1. Add Staff -->
            <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                <a href="employee_create.php" class="shortcut-card">
                    <div>
                        <div class="shortcut-icon-box bg-primary-subtle text-primary">
                            <i class="bi bi-person-plus-fill"></i>
                        </div>
                        <div class="shortcut-title">Add Staff</div>
                        <p class="shortcut-desc">Register new field employee or supervisor credentials</p>
                    </div>
                    <div class="shortcut-arrow">
                        <span>Open module</span> <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 2. Staff Directory -->
            <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                <a href="employee_list.php" class="shortcut-card">
                    <div>
                        <div class="shortcut-icon-box text-primary" style="background-color: #e0e7ff; color: #4338ca;">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="shortcut-title">Staff Directory</div>
                        <p class="shortcut-desc">View full employee list, check statuses & accounts</p>
                    </div>
                    <div class="shortcut-arrow">
                        <span>Open list</span> <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 3. Assign Supervisor -->
            <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                <a href="assign_supervisor.php" class="shortcut-card">
                    <div>
                        <div class="shortcut-icon-box bg-info-subtle text-info">
                            <i class="bi bi-diagram-3-fill"></i>
                        </div>
                        <div class="shortcut-title">Assign Supervisor</div>
                        <p class="shortcut-desc">Map and link field employees to designated leads</p>
                    </div>
                    <div class="shortcut-arrow">
                        <span>Manage links</span> <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 4. Live GPS Tracking -->
            <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                <a href="tracking_list.php" class="shortcut-card">
                    <div>
                        <div class="shortcut-icon-box bg-danger-subtle text-danger">
                            <i class="bi bi-radar"></i>
                        </div>
                        <div class="shortcut-title">Live Tracking</div>
                        <p class="shortcut-desc">Monitor real-time GPS locations and movement logs</p>
                    </div>
                    <div class="shortcut-arrow">
                        <span>View map</span> <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 5. Add Product -->
            <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                <a href="product_create.php" class="shortcut-card">
                    <div>
                        <div class="shortcut-icon-box bg-success-subtle text-success">
                            <i class="bi bi-plus-circle-fill"></i>
                        </div>
                        <div class="shortcut-title">Add Product</div>
                        <p class="shortcut-desc">Create stock items with carton packaging & rates</p>
                    </div>
                    <div class="shortcut-arrow">
                        <span>New item</span> <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 6. Product Catalog -->
            <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                <a href="product_list.php" class="shortcut-card">
                    <div>
                        <div class="shortcut-icon-box text-success" style="background-color: #d1fae5; color: #059669;">
                            <i class="bi bi-boxes"></i>
                        </div>
                        <div class="shortcut-title">Product Catalog</div>
                        <p class="shortcut-desc">Check carton packaging, stock, depo & shop pricing</p>
                    </div>
                    <div class="shortcut-arrow">
                        <span>Catalog list</span> <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 7. Daily Sales Orders -->
            <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                <a href="daily_sales.php" class="shortcut-card">
                    <div>
                        <div class="shortcut-icon-box bg-warning-subtle text-warning-emphasis">
                            <i class="bi bi-cart-check"></i>
                        </div>
                        <div class="shortcut-title">Daily Sales</div>
                        <p class="shortcut-desc">View shop order invoices, spot payments & GPS</p>
                    </div>
                    <div class="shortcut-arrow">
                        <span>Sales log</span> <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 8. Total Collection -->
            <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                <a href="total_collection.php" class="shortcut-card">
                    <div>
                        <div class="shortcut-icon-box bg-success-subtle text-success">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <div class="shortcut-title">Total Collection</div>
                        <p class="shortcut-desc">Cash reconciliations & digital payment vouchers</p>
                    </div>
                    <div class="shortcut-arrow">
                        <span>Collections</span> <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 9. Leave Requests -->
            <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                <a href="leave_management.php" class="shortcut-card">
                    <div>
                        <div class="shortcut-icon-box text-primary" style="background-color: #fce7f3; color: #be185d;">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div class="shortcut-title">Leave Requests</div>
                        <p class="shortcut-desc">Review two-tier supervisor & admin leave applications</p>
                    </div>
                    <div class="shortcut-arrow">
                        <span>Leave reviews</span> <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 10. Tour Plans -->
            <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                <a href="tour_management.php" class="shortcut-card">
                    <div>
                        <div class="shortcut-icon-box text-info" style="background-color: #e0f2fe; color: #0284c7;">
                            <i class="bi bi-airplane-fill"></i>
                        </div>
                        <div class="shortcut-title">Tour Plans</div>
                        <p class="shortcut-desc">Authorize travel itineraries, TA/DA allowances & budgets</p>
                    </div>
                    <div class="shortcut-arrow">
                        <span>Tour plans</span> <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 11. Reports & Exports -->
            <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                <a href="reports.php" class="shortcut-card">
                    <div>
                        <div class="shortcut-icon-box text-danger" style="background-color: #fee2e2; color: #dc2626;">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                        </div>
                        <div class="shortcut-title">Reports Center</div>
                        <p class="shortcut-desc">Export Excel spreadsheets & PDF financial vouchers</p>
                    </div>
                    <div class="shortcut-arrow">
                        <span>Export data</span> <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 12. Admin Profile & Security -->
            <div class="col-xl-3 col-lg-4 col-md-6 col-6">
                <a href="profile.php" class="shortcut-card">
                    <div>
                        <div class="shortcut-icon-box bg-dark-subtle text-dark">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <div class="shortcut-title">Admin Profile</div>
                        <p class="shortcut-desc">Update credentials, change password & manage profile</p>
                    </div>
                    <div class="shortcut-arrow">
                        <span>Account settings</span> <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>