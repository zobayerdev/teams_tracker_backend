<?php
require_once __DIR__ . '/includes/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name    = trim($_POST['product_name'] ?? '');
    $product_code    = trim($_POST['product_code'] ?? '');
    $piece_per_box   = (int)($_POST['piece_per_box'] ?? 1);
    $stock_boxes     = (int)($_POST['stock_boxes'] ?? 0);
    $stock_extra_pcs = (int)($_POST['stock_extra_pcs'] ?? 0);
    $depo_price      = (float)($_POST['depo_price'] ?? 0);
    $shop_price      = (float)($_POST['shop_price'] ?? 0);
    $customer_price  = (float)($_POST['customer_price'] ?? 0);

    // Total pieces calculate
    $piece_per_box = ($piece_per_box > 0) ? $piece_per_box : 1;
    $total_pieces = ($stock_boxes * $piece_per_box) + $stock_extra_pcs;

    if (empty($product_name)) {
        $error = 'Product name is required.';
    } elseif ($depo_price < 0 || $shop_price < 0 || $customer_price < 0) {
        $error = 'Prices cannot be negative values.';
    } else {
        // Image Upload Logic
        $imageName = null;
        if (!empty($_FILES['image']['name'])) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $fileExt = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowedExts)) {
                $error = 'Invalid image format. Only JPG, PNG & WEBP are allowed.';
            } else {
                $uploadDir = __DIR__ . '/../uploads/products/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $imageName = 'prod_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName)) {
                    $error = 'Failed to upload product image.';
                }
            }
        }

        if (empty($error)) {
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    product_name, product_code, piece_per_box, stock_quantity, 
                    depo_price, shop_price, customer_price, image, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([
                $product_name, $product_code, $piece_per_box, $total_pieces, 
                $depo_price, $shop_price, $customer_price, $imageName, $_SESSION['user_id'] ?? null
            ])) {
                $success = 'Product added successfully!';
                $product_name = $product_code = '';
                $piece_per_box = 1;
                $stock_boxes = $stock_extra_pcs = $depo_price = $shop_price = $customer_price = 0;
            } else {
                $error = 'Failed to save product in database.';
            }
        }
    }
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
        --input-focus-border: #6366f1;
    }
    body { background-color: #f8fafc; }
    .custom-card {
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 1.25rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        background: #ffffff;
    }
    .form-label {
        font-size: 0.825rem;
        font-weight: 600;
        letter-spacing: 0.025em;
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
        border-radius: 0 0.75rem 0.75rem 0;
        padding: 0.65rem 0.9rem;
        font-size: 0.95rem;
        color: #1e293b;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--input-focus-border);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
    }
    .btn-gradient {
        background: var(--primary-gradient);
        border: none;
        color: #ffffff;
        font-weight: 600;
        border-radius: 0.75rem;
        padding: 0.7rem 1.5rem;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
    }
    .btn-gradient:hover {
        transform: translateY(-1px);
        color: #fff;
    }
    .btn-light-secondary {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        font-weight: 500;
        border-radius: 0.75rem;
        padding: 0.7rem 1.25rem;
    }
    .avatar-icon-circle {
        width: 44px;
        height: 44px;
        background: #eef2ff;
        color: #4f46e5;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-size: 1.3rem;
    }
    .calc-banner {
        background-color: #f1f5f9;
        border-left: 4px solid #4f46e5;
        padding: 10px 14px;
        border-radius: 4px;
        font-size: 0.85rem;
    }
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card custom-card">
                <div class="card-body p-4 p-md-5">
                    
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="avatar-icon-circle">
                            <i class="bi bi-box-seam-fill"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-1 text-dark">Add New Product</h4>
                            <p class="text-muted small mb-0">Define product details, box quantities, and price tier levels</p>
                        </div>
                    </div>

                    <hr class="text-muted opacity-25 my-4">

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 border-0 py-3 mb-4 rounded-3 shadow-sm">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success d-flex align-items-center gap-2 border-0 py-3 mb-4 rounded-3 shadow-sm">
                            <i class="bi bi-check-circle-fill fs-5"></i>
                            <div><?= htmlspecialchars($success) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="productForm">
                        
                        <!-- Product Name & Code -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-tag"></i></span>
                                    <input type="text" name="product_name" class="form-control" placeholder="e.g. Energy Biscuit 50g" value="<?= htmlspecialchars($product_name ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Product Code/SKU</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                                    <input type="text" name="product_code" class="form-control" placeholder="e.g. PRD-101" value="<?= htmlspecialchars($product_code ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Box Mapping & Stock -->
                        <div class="card bg-light border-0 p-3 mb-4 rounded-3">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-boxes me-2"></i>Box & Stock Configuration</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Pieces Per 1 Box/Carton <span class="text-danger">*</span></label>
                                    <input type="number" name="piece_per_box" id="piece_per_box" class="form-control rounded-3" value="<?= $piece_per_box ?? 1 ?>" min="1" required>
                                    <small class="text-muted" style="font-size: 0.72rem;">1 ta single product hole 1 rakhun</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Initial Boxes/Cartons</label>
                                    <input type="number" name="stock_boxes" id="stock_boxes" class="form-control rounded-3" value="<?= $stock_boxes ?? 0 ?>" min="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Extra Loose Pieces</label>
                                    <input type="number" name="stock_extra_pcs" id="stock_extra_pcs" class="form-control rounded-3" value="<?= $stock_extra_pcs ?? 0 ?>" min="0">
                                </div>
                            </div>
                            <div class="calc-banner mt-3">
                                <strong>Calculated Total Stock:</strong> <span id="display_total_pcs" class="fw-bold text-primary">0</span> Pieces 
                                (<span id="display_box_summary" class="text-muted">0 Box, 0 Pcs</span>)
                            </div>
                        </div>

                        <!-- Pricing Tiers -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Depo / Wholeseller Price (৳)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-cash-stack"></i></span>
                                    <input type="number" step="0.01" name="depo_price" class="form-control" placeholder="0.00" value="<?= $depo_price ?? '' ?>" required>
                                </div>
                                <small class="text-muted" style="font-size: 0.72rem;">Per Piece Price</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Shop Price (৳)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-shop"></i></span>
                                    <input type="number" step="0.01" name="shop_price" class="form-control" placeholder="0.00" value="<?= $shop_price ?? '' ?>" required>
                                </div>
                                <small class="text-muted" style="font-size: 0.72rem;">Trade/Dealer Price to Shop</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Customer Price / MRP (৳)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-receipt"></i></span>
                                    <input type="number" step="0.01" name="customer_price" class="form-control" placeholder="0.00" value="<?= $customer_price ?? '' ?>" required>
                                </div>
                                <small class="text-muted" style="font-size: 0.72rem;">Consumer Retail Price</small>
                            </div>
                        </div>

                        <!-- Product Image -->
                        <div class="mb-4">
                            <label class="form-label">Product Image</label>
                            <input type="file" name="image" class="form-control rounded-3" accept="image/png, image/jpeg, image/webp">
                            <small class="text-muted" style="font-size: 0.72rem;">Upload image format: JPG, PNG or WEBP (Max: 2MB)</small>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex flex-wrap gap-2 pt-2">
                            <button type="submit" class="btn btn-gradient d-inline-flex align-items-center gap-2">
                                <i class="bi bi-plus-circle"></i>
                                <span>Save Product</span>
                            </button>
                            <a href="product_list.php" class="btn btn-light-secondary d-inline-flex align-items-center gap-2">
                                <i class="bi bi-list-ul"></i>
                                <span>View Products List</span>
                            </a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const piecePerBoxInput = document.getElementById('piece_per_box');
    const stockBoxesInput = document.getElementById('stock_boxes');
    const stockPcsInput = document.getElementById('stock_extra_pcs');
    const displayTotalPcs = document.getElementById('display_total_pcs');
    const displayBoxSummary = document.getElementById('display_box_summary');

    function calculateTotal() {
        const perBox = parseInt(piecePerBoxInput.value) || 1;
        const boxes = parseInt(stockBoxesInput.value) || 0;
        const pcs = parseInt(stockPcsInput.value) || 0;

        const totalPcs = (boxes * perBox) + pcs;
        displayTotalPcs.innerText = totalPcs;

        const calcBoxes = Math.floor(totalPcs / perBox);
        const remainingPcs = totalPcs % perBox;
        displayBoxSummary.innerText = calcBoxes + ' Box(es), ' + remainingPcs + ' Pc(s)';
    }

    piecePerBoxInput.addEventListener('input', calculateTotal);
    stockBoxesInput.addEventListener('input', calculateTotal);
    stockPcsInput.addEventListener('input', calculateTotal);
    calculateTotal();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>