<?php
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: ../auth/login.php");
    exit;
}

// বর্তমান পেজের ফাইল নেম বের করা
$current_page = basename($_SERVER['PHP_SELF']);
$current_type = $_GET['type'] ?? '';

// গ্রুপ ভ্যালিডেশন (ড্রপডাউন ও অ্যাক্টিভ মেনু হাইলাইট)
$is_product_active  = in_array($current_page, ['product_create.php', 'product_list.php', 'products.php']);
$is_tracking_active = in_array($current_page, ['tracking_list.php', 'employee_history.php']);
$is_report_active   = ($current_page === 'reports.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Admin Panel</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --nav-bg: #0f172a;           /* প্রিমিয়াম স্লেট-ডার্ক */
            --nav-hover: #1e293b;
            --accent-color: #38bdf8;        /* সফট স্কাই ব্লু */
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }

        /* Navbar Base */
        .custom-navbar {
            background-color: var(--nav-bg) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            padding: 0.65rem 0;
        }

        /* Brand Styling */
        .brand-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
            color: #ffffff;
            border-radius: 10px;
            font-size: 1.1rem;
            box-shadow: 0 2px 10px rgba(14, 165, 233, 0.3);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: -0.02em;
            color: #ffffff !important;
        }

        /* Nav Links */
        .nav-link {
            color: var(--text-muted) !important;
            font-weight: 500;
            font-size: 0.86rem;
            padding: 0.45rem 0.68rem !important;
            border-radius: 8px;
            transition: all 0.2s ease-in-out;
            display: inline-flex;
            align-items: center;
            gap: 0.38rem;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .nav-link:hover {
            color: #ffffff !important;
            background-color: var(--nav-hover);
        }

        /* Selected / Active State */
        .nav-link.active {
            color: #ffffff !important;
            background-color: rgba(56, 189, 248, 0.12) !important;
            border: 1px solid rgba(56, 189, 248, 0.3) !important;
        }

        .nav-link.active i {
            color: var(--accent-color) !important;
        }

        /* Dropdowns */
        .dropdown-menu {
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: #1e293b;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 0.5rem;
        }

        .dropdown-item {
            color: var(--text-muted);
            border-radius: 6px;
            font-size: 0.86rem;
            padding: 0.45rem 0.85rem;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dropdown-item:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        .dropdown-item.active, .dropdown-item:active {
            background-color: rgba(56, 189, 248, 0.15) !important;
            color: var(--accent-color) !important;
        }

        .dropdown-divider {
            border-color: rgba(255, 255, 255, 0.08);
        }

        /* User Profile Pill */
        .user-profile-badge {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 0.35rem 0.85rem;
            border-radius: 50px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .user-profile-badge:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(56, 189, 248, 0.4);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: #334155;
            color: #f8fafc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .role-tag {
            font-size: 0.65rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            background: rgba(56, 189, 248, 0.15);
            color: var(--accent-color);
        }

        /* Mobile Adjustments */
        @media (max-width: 1199.98px) {
            .navbar-collapse {
                background: #1e293b;
                padding: 1.25rem;
                border-radius: 14px;
                margin-top: 0.75rem;
                border: 1px solid rgba(255, 255, 255, 0.08);
            }
            .user-profile-badge {
                margin: 0.75rem 0;
                justify-content: flex-start;
                border-radius: 10px;
            }
            .dropdown-menu {
                background: #0f172a;
                border: 1px solid rgba(255, 255, 255, 0.05);
            }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-xl navbar-dark custom-navbar sticky-top">
    <div class="container-fluid px-lg-4">
        
        <!-- Brand Logo & Name -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
            <span class="brand-logo">
                <i class="bi bi-geo-alt-fill"></i>
            </span>
            <span>Teams<span class="text-info">Locator</span></span>
        </a>

        <!-- Mobile Toggler -->
        <button class="navbar-toggler border-0 shadow-none p-2" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <i class="bi bi-list fs-3 text-light"></i>
        </button>

        <!-- Navbar Menu & Actions -->
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-xl-0 gap-xl-1 mt-2 mt-xl-0">
                
                <!-- 1. Dashboard -->
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                
                <!-- 2. Add Staff -->
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'employee_create.php') ? 'active' : '' ?>" href="employee_create.php">
                        <i class="bi bi-person-plus"></i> Add Staff
                    </a>
                </li>
                
                <!-- 3. Staff Directory -->
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'employee_list.php') ? 'active' : '' ?>" href="employee_list.php">
                        <i class="bi bi-people"></i> Staff Directory
                    </a>
                </li>
                
                <!-- 4. Assign Supervisor -->
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'assign_supervisor.php') ? 'active' : '' ?>" href="assign_supervisor.php">
                        <i class="bi bi-diagram-3"></i> Assign Supervisor
                    </a>
                </li>

                <!-- 5. Live Tracking -->
                <li class="nav-item">
                    <a class="nav-link <?= $is_tracking_active ? 'active' : '' ?>" href="tracking_list.php">
                        <i class="bi bi-radar"></i> Live Tracking
                    </a>
                </li>

                <!-- 6. Products Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $is_product_active ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-box-seam"></i> Products
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item <?= ($current_page === 'product_create.php') ? 'active' : '' ?>" href="product_create.php">
                                <i class="bi bi-plus-circle text-primary"></i> Add New Product
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($current_page === 'product_list.php' || $current_page === 'products.php') ? 'active' : '' ?>" href="product_list.php">
                                <i class="bi bi-boxes text-info"></i> Product Catalog
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- 7. Daily Sales -->
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'daily_sales.php') ? 'active' : '' ?>" href="daily_sales.php">
                        <i class="bi bi-graph-up-arrow"></i> Daily Sales
                    </a>
                </li>
                
                <!-- 8. Total Collection -->
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'total_collection.php') ? 'active' : '' ?>" href="total_collection.php">
                        <i class="bi bi-wallet2"></i> Total Collection
                    </a>
                </li>
                
                <!-- 9. Leave Requests -->
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'leave_management.php') ? 'active' : '' ?>" href="leave_management.php">
                        <i class="bi bi-calendar-check"></i> Leaves
                    </a>
                </li>
                
                <!-- 10. Tour Plans -->
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'tour_management.php') ? 'active' : '' ?>" href="tour_management.php">
                        <i class="bi bi-map"></i> Tours
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'attendance_list.php') ? 'active' : '' ?>" href="attendance_list.php">
                    <i class="bi bi-clock-history"></i> Attendance
                    </a>
                </li>
                
<!-- 11. Reports Dropdown -->
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle <?= $is_report_active ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-file-earmark-bar-graph"></i> Reports
    </a>
    <ul class="dropdown-menu">
        <li>
            <a class="dropdown-item <?= ($is_report_active && $current_type === 'attendance') ? 'active' : '' ?>" href="reports.php?type=attendance">
                <i class="bi bi-clock-history text-info me-1"></i> Attendance Report
            </a>
        </li>
        <li>
            <a class="dropdown-item <?= ($is_report_active && $current_type === 'movement') ? 'active' : '' ?>" href="reports.php?type=movement">
                <i class="bi bi-geo-alt text-danger me-1"></i> Field Movement / GPS Audit
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item <?= ($is_report_active && $current_type === 'sales') ? 'active' : '' ?>" href="reports.php?type=sales">
                <i class="bi bi-cart-check text-primary me-1"></i> Daily Sales Report
            </a>
        </li>
        <li>
            <a class="dropdown-item <?= ($is_report_active && $current_type === 'collection') ? 'active' : '' ?>" href="reports.php?type=collection">
                <i class="bi bi-cash-stack text-success me-1"></i> Collection Report
            </a>
        </li>
    </ul>
</li>

            </ul>

            <!-- Right: User Dropdown -->
            <div class="dropdown pt-2 pt-xl-0">
                <button class="user-profile-badge bg-transparent border-0 d-flex align-items-center gap-2 dropdown-toggle text-start" 
                        type="button" 
                        data-bs-toggle="dropdown" 
                        aria-expanded="false">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="d-flex flex-column" style="line-height: 1.2;">
                        <span class="text-white small fw-semibold"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                        <div>
                            <span class="role-tag"><?= htmlspecialchars(strtoupper($_SESSION['role'] ?? 'STAFF')) ?></span>
                        </div>
                    </div>
                </button>
                
                <ul class="dropdown-menu dropdown-menu-end mt-2" style="min-width: 200px;">
                    <li>
                        <h6 class="dropdown-header text-uppercase small text-muted">Account Settings</h6>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 <?= ($current_page === 'profile.php') ? 'active' : '' ?>" href="profile.php">
                            <i class="bi bi-person-gear text-primary"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item py-2 text-danger" href="../auth/logout.php">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </div>

        </div>

    </div>
</nav>

<div class="container my-4">