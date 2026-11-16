<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/header.php';

$success = '';
$error = '';

// ==========================================
// ১. PRODUCT UPDATE HANDLER (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_product') {
    $productId     = (int)($_POST['product_id'] ?? 0);
    $productName   = trim($_POST['product_name'] ?? '');
    $productCode   = trim($_POST['product_code'] ?? '');
    $piecePerBox   = (int)($_POST['piece_per_box'] ?? 1);
    $stockQuantity = (int)($_POST['stock_quantity'] ?? 0);
    $depoPrice     = (float)($_POST['depo_price'] ?? 0);
    $shopPrice     = (float)($_POST['shop_price'] ?? 0);
    $customerPrice = (float)($_POST['customer_price'] ?? 0);
    $status        = trim($_POST['status'] ?? 'active');

    if ($productId <= 0 || empty($productName)) {
        $error = "Product ID and Product Name are required.";
    } else {
        $imageUpdateSql = "";
        $updateParams = [
            $productName,
            $productCode,
            $piecePerBox,
            $stockQuantity,
            $depoPrice,
            $shopPrice,
            $customerPrice,
            $status
        ];

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($ext, $allowedExts)) {
                $newFileName = 'prod_' . time() . '_' . uniqid() . '.' . $ext;
                $targetFile = $uploadDir . $newFileName;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $imageUpdateSql = ", image = ?";
                    $updateParams[] = $newFileName;
                }
            }
        }

        $updateParams[] = $productId;

        $updateQuery = "
            UPDATE products 
            SET product_name = ?, 
                product_code = ?, 
                piece_per_box = ?, 
                stock_quantity = ?, 
                depo_price = ?, 
                shop_price = ?, 
                customer_price = ?, 
                status = ?
                {$imageUpdateSql}
            WHERE id = ?
        ";

        $upStmt = $pdo->prepare($updateQuery);
        if ($upStmt->execute($updateParams)) {
            $success = "Product details successfully updated!";
        } else {
            $error = "Failed to update product details.";
        }
    }
}

// ==========================================
// ২. PRODUCT DELETE HANDLER
// ==========================================
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    if ($delId > 0) {
        $delStmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        if ($delStmt->execute([$delId])) {
            $success = "Product removed from catalog successfully!";
        } else {
            $error = "Failed to delete product.";
        }
    }
}

// ==========================================
// ৩. FETCH ALL PRODUCTS
// ==========================================
$stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
$products = $stmt->fetchAll();

// Summaries
$totalProducts = count($products);
$inStockCount  = count(array_filter($products, fn($p) => (int)$p['stock_quantity'] > 10));
$lowStockCount = count(array_filter($products, fn($p) => (int)$p['stock_quantity'] <= 10));
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

    /* Image Thumbnails */
    .product-img-box {
        width: 46px;
        height: 46px;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid var(--saas-border);
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
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
    .status-active {
        background: #ecfdf5;
        color: #059669;
        border: 1px solid #d1fae5;
    }
    .status-active .badge-status-dot { background: #10b981; }

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

    .box-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.6rem;
        border-radius: 6px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-weight: 500;
    }

    /* Price Pills for Mobile */
    .price-tile {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.45rem 0.65rem;
        text-align: center;
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

    .product-card-mobile {
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
            <h4 class="fw-bold mb-1 text-dark tracking-tight">Product Catalog & Inventory</h4>
            <p class="text-muted small mb-0">Manage carton packaging rules, pricing tiers, stock replenishment & items</p>
        </div>
        <div>
            <a href="product_create.php" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 shadow-sm" style="font-weight: 500;">
                <i class="bi bi-plus-lg"></i>
                <span>Add New Product</span>
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

    <!-- Quick Stat Widgets -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-primary-subtle text-primary">
                    <i class="bi bi-boxes"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Total Catalog Items</div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalProducts ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-success-subtle text-success">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Healthy Inventory (&gt;10 pcs)</div>
                    <div class="fs-4 fw-bold text-dark"><?= $inStockCount ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-12 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-warning-subtle text-warning-emphasis">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Low / Depleted Stock</div>
                    <div class="fs-4 fw-bold text-dark"><?= $lowStockCount ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Container Card -->
    <div class="saas-card">
        
        <!-- Search & Filter Controls -->
        <div class="p-3 border-bottom border-light-subtle bg-white">
            <div class="row g-2 justify-content-between align-items-center">
                <div class="col-md-5 col-12">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 0.65rem 0 0 0.65rem; border-color: #e2e8f0;">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="liveSearchInput" class="form-control border-start-0" placeholder="Search by product name or SKU code..." style="border-radius: 0 0.65rem 0.65rem 0; border-color: #e2e8f0;">
                    </div>
                </div>
                <div class="col-md-3 col-6 ms-auto">
                    <select id="statusFilterSelect" class="form-select rounded-3">
                        <option value="all">All Products</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 1. Desktop Table View -->
        <div class="desktop-table-view table-responsive mb-0">
            <table class="table align-middle mb-0" id="productTable">
                <thead>
                    <tr>
                        <th class="ps-4">Product Info</th>
                        <th>Box Packaging</th>
                        <th>Current Stock</th>
                        <th>Depo Rate</th>
                        <th>Shop Rate</th>
                        <th>MRP Price</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-box-seam fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                No products found in the catalog.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $row): 
                            $boxRatio = (int)$row['piece_per_box'] > 0 ? (int)$row['piece_per_box'] : 1;
                            $boxes = floor($row['stock_quantity'] / $boxRatio);
                            $extraPcs = $row['stock_quantity'] % $boxRatio;
                            $status = $row['status'];
                            $hasImage = !empty($row['image']) && file_exists(__DIR__ . '/../uploads/products/' . $row['image']);
                        ?>
                            <tr class="product-row"
                                data-name="<?= strtolower(htmlspecialchars($row['product_name'])) ?>"
                                data-code="<?= strtolower(htmlspecialchars($row['product_code'] ?? '')) ?>"
                                data-status="<?= $status ?>">
                                
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if ($hasImage): ?>
                                            <img src="../uploads/products/<?= htmlspecialchars($row['image']) ?>" alt="Product" class="product-img-box">
                                        <?php else: ?>
                                            <div class="product-img-box text-muted">
                                                <i class="bi bi-image fs-5"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($row['product_name']) ?></div>
                                            <small class="text-muted"><i class="bi bi-upc-scan me-1"></i><?= htmlspecialchars($row['product_code'] ?? 'N/A') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="box-badge">
                                        <i class="bi bi-box-seam me-1 text-primary"></i>1 Box = <?= $boxRatio ?> Pcs
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold <?= $row['stock_quantity'] <= 10 ? 'text-warning-emphasis' : 'text-primary' ?>">
                                        <?= number_format($row['stock_quantity']) ?> Pcs
                                    </div>
                                    <small class="text-muted" style="font-size: 0.78rem;">
                                        (<?= $boxes ?> Box + <?= $extraPcs ?> Pcs)
                                    </small>
                                </td>
                                <td>
                                    <span class="text-secondary small fw-medium">৳<?= number_format($row['depo_price'], 2) ?></span>
                                </td>
                                <td>
                                    <strong class="text-success small">৳<?= number_format($row['shop_price'], 2) ?></strong>
                                </td>
                                <td>
                                    <span class="text-dark small fw-medium">৳<?= number_format($row['customer_price'], 2) ?></span>
                                </td>
                                <td>
                                    <span class="badge-status <?= $status === 'active' ? 'status-active' : 'status-inactive' ?>">
                                        <span class="badge-status-dot"></span>
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" 
                                                class="btn btn-outline-primary rounded-2 px-2.5 py-1 me-1"
                                                onclick='openEditModal(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            <i class="bi bi-pencil-square me-1"></i> Edit
                                        </button>
                                        <a href="product_list.php?delete_id=<?= $row['id'] ?>" 
                                           class="btn btn-outline-danger rounded-2 px-2.5 py-1"
                                           onclick="return confirm('Are you sure you want to remove this product?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
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
            <?php if (empty($products)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-box-seam fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No products found.
                </div>
            <?php else: ?>
                <?php foreach ($products as $row): 
                    $boxRatio = (int)$row['piece_per_box'] > 0 ? (int)$row['piece_per_box'] : 1;
                    $boxes = floor($row['stock_quantity'] / $boxRatio);
                    $extraPcs = $row['stock_quantity'] % $boxRatio;
                    $status = $row['status'];
                    $hasImage = !empty($row['image']) && file_exists(__DIR__ . '/../uploads/products/' . $row['image']);
                ?>
                    <div class="product-card-mobile product-card-item"
                         data-name="<?= strtolower(htmlspecialchars($row['product_name'])) ?>"
                         data-code="<?= strtolower(htmlspecialchars($row['product_code'] ?? '')) ?>"
                         data-status="<?= $status ?>">
                        
                        <div class="d-flex justify-content-between align-items-start mb-2.5">
                            <div class="d-flex align-items-center gap-2.5">
                                <?php if ($hasImage): ?>
                                    <img src="../uploads/products/<?= htmlspecialchars($row['image']) ?>" alt="Product" class="product-img-box">
                                <?php else: ?>
                                    <div class="product-img-box text-muted">
                                        <i class="bi bi-image fs-5"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($row['product_name']) ?></h6>
                                    <small class="text-muted"><i class="bi bi-upc-scan me-1"></i><?= htmlspecialchars($row['product_code'] ?? 'N/A') ?></small>
                                </div>
                            </div>
                            <span class="badge-status <?= $status === 'active' ? 'status-active' : 'status-inactive' ?>">
                                <span class="badge-status-dot"></span>
                                <?= ucfirst($status) ?>
                            </span>
                        </div>

                        <!-- Stock Row -->
                        <div class="p-2.5 rounded-3 bg-light mb-2.5 d-flex justify-content-between align-items-center small">
                            <div>
                                <span class="text-muted d-block" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600;">Stock Available</span>
                                <strong class="<?= $row['stock_quantity'] <= 10 ? 'text-warning-emphasis' : 'text-primary' ?> fs-6">
                                    <?= number_format($row['stock_quantity']) ?> Pcs
                                </strong>
                                <small class="text-muted ms-1">(<?= $boxes ?> Box + <?= $extraPcs ?> Pcs)</small>
                            </div>
                            <span class="box-badge bg-white">
                                <?= $boxRatio ?> Pcs/Box
                            </span>
                        </div>

                        <!-- Price Breakdown Matrix -->
                        <div class="row g-2 text-center mb-3">
                            <div class="col-4">
                                <div class="price-tile">
                                    <small class="text-muted d-block" style="font-size: 0.7rem;">Depo</small>
                                    <strong class="text-secondary small">৳<?= number_format($row['depo_price'], 2) ?></strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="price-tile border-success-subtle bg-success-subtle">
                                    <small class="text-success-emphasis d-block" style="font-size: 0.7rem; font-weight: 600;">Shop</small>
                                    <strong class="text-success small">৳<?= number_format($row['shop_price'], 2) ?></strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="price-tile">
                                    <small class="text-muted d-block" style="font-size: 0.7rem;">Customer</small>
                                    <strong class="text-dark small">৳<?= number_format($row['customer_price'], 2) ?></strong>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Action Buttons -->
                        <div class="d-flex justify-content-end gap-2 pt-2 border-top border-light-subtle">
                            <button type="button" 
                                    class="btn btn-sm btn-outline-primary rounded-2 px-3 py-1"
                                    onclick='openEditModal(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                <i class="bi bi-pencil-square me-1"></i> Edit
                            </button>
                            <a href="product_list.php?delete_id=<?= $row['id'] ?>" 
                               class="btn btn-sm btn-outline-danger rounded-2 px-3 py-1"
                               onclick="return confirm('Are you sure you want to remove this product?')">
                                <i class="bi bi-trash me-1"></i> Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ==========================================
     EDIT PRODUCT MODAL
========================================== -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-sm bg-primary-subtle text-primary" style="width: 34px; height: 34px; font-size: 1rem; border-radius: 8px;">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Edit Catalog Product</h6>
                        <small class="text-muted" style="font-size: 0.78rem;">Modify rates, carton packaging & stock quantities</small>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_product">
                <input type="hidden" name="product_id" id="editProductId">

                <div class="modal-body p-4">
                    
                    <!-- Basic Information Card -->
                    <div class="card bg-light border-0 p-3 mb-3 rounded-3">
                        <span class="text-muted small fw-bold text-uppercase d-block mb-2" style="font-size: 0.72rem;">
                            <i class="bi bi-info-circle me-1"></i> Basic Details
                        </span>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                <input type="text" name="product_name" id="editProductName" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Product Code / SKU</label>
                                <input type="text" name="product_code" id="editProductCode" class="form-control">
                            </div>
                        </div>
                    </div>

                    <!-- Inventory & Packaging Rules -->
                    <div class="card bg-light border-0 p-3 mb-3 rounded-3">
                        <span class="text-muted small fw-bold text-uppercase d-block mb-2" style="font-size: 0.72rem;">
                            <i class="bi bi-box-seam me-1"></i> Inventory & Packaging
                        </span>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Pieces per Box <span class="text-danger">*</span></label>
                                <input type="number" name="piece_per_box" id="editPiecePerBox" class="form-control" min="1" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Stock Quantity (Total Pcs) <span class="text-danger">*</span></label>
                                <input type="number" name="stock_quantity" id="editStockQuantity" class="form-control" min="0" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" id="editStatus" class="form-select" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Multi-Tier Pricing Matrix -->
                    <div class="card bg-light border-0 p-3 mb-3 rounded-3">
                        <span class="text-muted small fw-bold text-uppercase d-block mb-2" style="font-size: 0.72rem;">
                            <i class="bi bi-tag me-1"></i> Multi-Tier Pricing (per Piece)
                        </span>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label">Depo Price (৳) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="depo_price" id="editDepoPrice" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-primary">Shop / Dealer (৳) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="shop_price" id="editShopPrice" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Customer / MRP (৳) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="customer_price" id="editCustomerPrice" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <!-- Image Section -->
                    <div class="row g-3 align-items-center">
                        <div class="col-md-9">
                            <label class="form-label">Update Product Image (Optional)</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Leave empty to keep the existing product photo.</small>
                        </div>
                        <div class="col-md-3 text-center">
                            <label class="form-label d-block">Current Preview</label>
                            <img id="currentImagePreview" src="" class="product-img-box mx-auto" style="width: 56px; height: 56px;" alt="Preview">
                        </div>
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
function openEditModal(prod) {
    document.getElementById('editProductId').value = prod.id;
    document.getElementById('editProductName').value = prod.product_name;
    document.getElementById('editProductCode').value = prod.product_code || '';
    document.getElementById('editPiecePerBox').value = prod.piece_per_box;
    document.getElementById('editStockQuantity').value = prod.stock_quantity;
    document.getElementById('editStatus').value = prod.status;

    document.getElementById('editDepoPrice').value = prod.depo_price;
    document.getElementById('editShopPrice').value = prod.shop_price;
    document.getElementById('editCustomerPrice').value = prod.customer_price;

    const imgPreview = document.getElementById('currentImagePreview');
    if (prod.image) {
        imgPreview.src = '../uploads/products/' + prod.image;
    } else {
        imgPreview.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" fill="%2394a3b8" viewBox="0 0 16 16"><path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/><path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-12zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1h12z"/></svg>';
    }

    const editModal = new bootstrap.Modal(document.getElementById('editProductModal'));
    editModal.show();
}

// Live Search & Filter Logic
const searchInput = document.getElementById('liveSearchInput');
const statusFilter = document.getElementById('statusFilterSelect');
const tableRows = document.querySelectorAll('.product-row');
const mobileCards = document.querySelectorAll('.product-card-item');

function filterProducts() {
    const query = searchInput.value.toLowerCase().trim();
    const filterStatus = statusFilter.value;

    // Desktop Table
    tableRows.forEach(row => {
        const name = row.getAttribute('data-name');
        const code = row.getAttribute('data-code');
        const status = row.getAttribute('data-status');

        const matchesSearch = name.includes(query) || code.includes(query);
        const matchesStatus = (filterStatus === 'all') || (status === filterStatus);

        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });

    // Mobile Cards
    mobileCards.forEach(card => {
        const name = card.getAttribute('data-name');
        const code = card.getAttribute('data-code');
        const status = card.getAttribute('data-status');

        const matchesSearch = name.includes(query) || code.includes(query);
        const matchesStatus = (filterStatus === 'all') || (status === filterStatus);

        card.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
}

searchInput.addEventListener('input', filterProducts);
statusFilter.addEventListener('change', filterProducts);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>