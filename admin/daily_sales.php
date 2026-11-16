<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/header.php';

$selectedDate = isset($_GET['date']) && !empty($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$employeeFilter = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

// Employees List for dropdown filter
$empStmt = $pdo->query("SELECT id, name FROM users WHERE role IN ('employee', 'supervisor') ORDER BY name ASC");
$employeeList = $empStmt->fetchAll();

// 1. Fetch Orders cleanly without string CONCAT collation issues
$query = "
    SELECT 
        o.id, o.shop_name, o.shop_phone, o.shop_address, o.latitude, o.longitude, 
        o.total_amount, o.payment_status, o.order_status, o.created_at,
        u.name AS emp_name, u.phone AS emp_phone
    FROM orders o
    JOIN users u ON o.employee_id = u.id
    WHERE o.order_date = :selected_date
";

$params = [':selected_date' => $selectedDate];

if ($employeeFilter > 0) {
    $query .= " AND o.employee_id = :employee_id";
    $params[':employee_id'] = $employeeFilter;
}

$query .= " ORDER BY o.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// 2. Fetch Order Items separately and map to orders (100% immune to collation error)
$orderIds = array_column($orders, 'id');
$itemsMap = [];

if (!empty($orderIds)) {
    $inClause = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsStmt = $pdo->prepare("
        SELECT oi.order_id, oi.quantity, oi.unit_price, p.product_name 
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id IN ($inClause)
    ");
    $itemsStmt->execute($orderIds);
    $items = $itemsStmt->fetchAll();

    foreach ($items as $it) {
        $itemsMap[$it['order_id']][] = [
            'name' => htmlspecialchars($it['product_name']),
            'qty' => $it['quantity'],
            'price' => $it['unit_price']
        ];
    }
}

// Total Calculation
$totalSales = 0;
$totalOrders = count($orders);
foreach ($orders as &$ord) {
    $totalSales += (float)$ord['total_amount'];
}
unset($ord);
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

    /* Avatar */
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
    .status-paid {
        background: #ecfdf5;
        color: #059669;
    }
    .status-paid .badge-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #10b981;
    }
    .status-due {
        background: #fffbeb;
        color: #b45309;
    }
    .status-due .badge-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #f59e0b;
    }

    /* Item Tag */
    .item-tag {
        display: inline-block;
        font-size: 0.78rem;
        padding: 0.2rem 0.55rem;
        border-radius: 6px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        margin-bottom: 0.25rem;
        color: #334155;
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

    .order-card-mobile {
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
            <h4 class="fw-bold mb-1 text-dark tracking-tight">Daily Sales Monitoring</h4>
            <p class="text-muted small mb-0">Shop-wise spot orders, GPS delivery points, and field representative collections</p>
        </div>
        <div>
            <button onclick="window.location.reload();" class="btn btn-light border text-secondary d-inline-flex align-items-center gap-2 px-3 py-2 rounded-3 shadow-sm">
                <i class="bi bi-arrow-clockwise"></i>
                <span>Refresh Orders</span>
            </button>
        </div>
    </div>

    <!-- Quick Stat Widgets -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-primary-subtle text-primary">
                    <i class="bi bi-currency-exchange"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Total Sales Volume</div>
                    <div class="fs-4 fw-bold text-dark">৳<?= number_format($totalSales, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-success-subtle text-success">
                    <i class="bi bi-cart-check-fill"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Total Orders Booked</div>
                    <div class="fs-4 fw-bold text-dark"><?= $totalOrders ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-12 col-lg-4">
            <div class="metric-card">
                <div class="metric-icon-box bg-secondary-subtle text-secondary">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div>
                    <div class="text-muted small fw-medium">Reporting Date</div>
                    <div class="fs-5 fw-bold text-dark"><?= date('d M, Y', strtotime($selectedDate)) ?></div>
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
                    <i class="bi bi-person-badge"></i> Officer:
                </span>
            </div>
            <div class="col-auto">
                <select name="employee_id" class="form-select form-select-sm rounded-3 border-light-subtle" onchange="this.form.submit()">
                    <option value="0">All Field Officers</option>
                    <?php foreach ($employeeList as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $employeeFilter == $emp['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-auto ms-auto">
                <a href="daily_sales.php" class="btn btn-sm btn-light border rounded-3 px-3 text-secondary">
                    Today
                </a>
            </div>
        </form>
    </div>

    <!-- Data Container Card -->
    <div class="saas-card">
        
        <!-- Live Search & Status Filters -->
        <div class="p-3 border-bottom border-light-subtle bg-white">
            <div class="row g-2 justify-content-between align-items-center">
                <div class="col-md-5 col-12">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 0.65rem 0 0 0.65rem;">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="liveSearchInput" class="form-control border-start-0" placeholder="Search by shop name, phone or officer..." style="border-radius: 0 0.65rem 0.65rem 0;">
                    </div>
                </div>
                <div class="col-md-3 col-6 ms-auto">
                    <select id="paymentFilterSelect" class="form-select rounded-3">
                        <option value="all">All Payments</option>
                        <option value="paid">Paid Only</option>
                        <option value="due">Due / Unpaid Only</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 1. Desktop Table View -->
        <div class="desktop-table-view table-responsive mb-0">
            <table class="table align-middle mb-0" id="orderTable">
                <thead>
                    <tr>
                        <th class="ps-4">Shop Information</th>
                        <th>Ordered Products</th>
                        <th>Total Amount</th>
                        <th>Field Officer</th>
                        <th>Time</th>
                        <th class="text-end pe-4">Order GPS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-clipboard-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                No orders submitted for this date.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $row): 
                            $initial = strtoupper(substr($row['shop_name'], 0, 1));
                            $hasCoords = !empty($row['latitude']) && !empty($row['longitude']);
                            $isPaid = strtolower($row['payment_status']) === 'paid';
                            $itemsList = $itemsMap[$row['id']] ?? [];
                        ?>
                            <tr class="order-row"
                                data-shop="<?= strtolower(htmlspecialchars($row['shop_name'])) ?>"
                                data-phone="<?= htmlspecialchars($row['shop_phone']) ?>"
                                data-officer="<?= strtolower(htmlspecialchars($row['emp_name'])) ?>"
                                data-payment="<?= $isPaid ? 'paid' : 'due' ?>">
                                
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-sm bg-primary-subtle text-primary">
                                            <?= $initial ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($row['shop_name']) ?></div>
                                            <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['shop_phone']) ?></small>
                                            <small class="text-secondary" style="font-size: 0.78rem;"><?= htmlspecialchars($row['shop_address'] ?? 'No address') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (empty($itemsList)): ?>
                                        <span class="text-muted small">No items recorded</span>
                                    <?php else: ?>
                                        <div class="d-flex flex-column">
                                            <?php foreach ($itemsList as $it): ?>
                                                <div class="item-tag">
                                                    <strong><?= $it['name'] ?></strong>
                                                    <span class="text-muted">(<?= $it['qty'] ?> pcs × ৳<?= number_format($it['price'], 2) ?>)</span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="fs-6 text-dark d-block">৳<?= number_format($row['total_amount'], 2) ?></strong>
                                    <span class="badge-status <?= $isPaid ? 'status-paid' : 'status-due' ?>">
                                        <span class="badge-status-dot"></span>
                                        <?= ucfirst($row['payment_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark"><?= htmlspecialchars($row['emp_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($row['emp_phone']) ?></small>
                                </td>
                                <td class="text-muted small">
                                    <i class="bi bi-clock me-1 text-muted"></i><?= date('h:i A', strtotime($row['created_at'])) ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($hasCoords): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 rounded-2"
                                                onclick="openShopMap('<?= addslashes(htmlspecialchars($row['shop_name'])) ?>', <?= $row['latitude'] ?>, <?= $row['longitude'] ?>, '<?= addslashes(htmlspecialchars($row['shop_address'] ?? '')) ?>')">
                                            <i class="bi bi-geo-alt-fill"></i> View GPS
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border fw-normal">No GPS</span>
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
            <?php if (empty($orders)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-clipboard-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No orders submitted for this date.
                </div>
            <?php else: ?>
                <?php foreach ($orders as $row): 
                    $initial = strtoupper(substr($row['shop_name'], 0, 1));
                    $hasCoords = !empty($row['latitude']) && !empty($row['longitude']);
                    $isPaid = strtolower($row['payment_status']) === 'paid';
                    $itemsList = $itemsMap[$row['id']] ?? [];
                ?>
                    <div class="order-card-mobile order-card-item"
                         data-shop="<?= strtolower(htmlspecialchars($row['shop_name'])) ?>"
                         data-phone="<?= htmlspecialchars($row['shop_phone']) ?>"
                         data-officer="<?= strtolower(htmlspecialchars($row['emp_name'])) ?>"
                         data-payment="<?= $isPaid ? 'paid' : 'due' ?>">
                        
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm bg-primary-subtle text-primary">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($row['shop_name']) ?></h6>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($row['shop_phone']) ?></small>
                                </div>
                            </div>
                            <span class="badge-status <?= $isPaid ? 'status-paid' : 'status-due' ?>">
                                <span class="badge-status-dot"></span>
                                <?= ucfirst($row['payment_status']) ?>
                            </span>
                        </div>

                        <!-- Address -->
                        <?php if (!empty($row['shop_address'])): ?>
                            <p class="text-muted small mb-2" style="font-size: 0.8rem;">
                                <i class="bi bi-geo-alt me-1 text-primary"></i><?= htmlspecialchars($row['shop_address']) ?>
                            </p>
                        <?php endif; ?>

                        <!-- Products Ordered -->
                        <div class="p-2.5 rounded-3 bg-light mb-3">
                            <span class="text-muted d-block mb-1.5" style="font-size: 0.72rem; text-transform: uppercase; font-weight: 600;">Items Ordered</span>
                            <?php if (empty($itemsList)): ?>
                                <small class="text-muted">No items recorded</small>
                            <?php else: ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($itemsList as $it): ?>
                                        <div class="item-tag bg-white mb-0">
                                            <strong><?= $it['name'] ?></strong>
                                            <span class="text-muted">(<?= $it['qty'] ?> × ৳<?= number_format($it['price'], 2) ?>)</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Price and Officer Footer -->
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top border-light-subtle">
                            <div>
                                <small class="text-muted d-block" style="font-size: 0.72rem;">Total Amount</small>
                                <strong class="fs-5 text-dark">৳<?= number_format($row['total_amount'], 2) ?></strong>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <?php if ($hasCoords): ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 rounded-2 py-1 px-2.5"
                                            onclick="openShopMap('<?= addslashes(htmlspecialchars($row['shop_name'])) ?>', <?= $row['latitude'] ?>, <?= $row['longitude'] ?>, '<?= addslashes(htmlspecialchars($row['shop_address'] ?? '')) ?>')">
                                        <i class="bi bi-geo-alt-fill"></i> Map
                                    </button>
                                <?php endif; ?>
                                <div class="text-end">
                                    <span class="small fw-semibold text-dark d-block"><?= htmlspecialchars($row['emp_name']) ?></span>
                                    <small class="text-muted"><?= date('h:i A', strtotime($row['created_at'])) ?></small>
                                </div>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Map Modal -->
<div class="modal fade" id="shopMapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom border-light-subtle py-3">
                <h6 class="modal-title fw-bold text-dark" id="shopModalTitle">
                    <i class="bi bi-geo-alt-fill text-danger me-1"></i> Shop Location
                </h6>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="modalMap" style="height: 420px; width: 100%;"></div>
            </div>
            <div class="modal-footer border-top border-light-subtle py-2 d-flex justify-content-between">
                <small class="text-muted" id="shopModalAddress"></small>
                <button type="button" class="btn btn-secondary btn-sm rounded-3 px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    let modalMap = null;
    let currentMarker = null;

    function openShopMap(shopName, lat, lng, address) {
        document.getElementById('shopModalTitle').innerHTML = '<i class="bi bi-geo-alt-fill text-danger me-1"></i> ' + shopName;
        document.getElementById('shopModalAddress').innerText = address ? 'Address: ' + address : 'Coordinates: ' + lat + ', ' + lng;

        const mapModal = new bootstrap.Modal(document.getElementById('shopMapModal'));
        mapModal.show();

        document.getElementById('shopMapModal').addEventListener('shown.bs.modal', function () {
            if (!modalMap) {
                modalMap = L.map('modalMap').setView([lat, lng], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(modalMap);
            } else {
                modalMap.setView([lat, lng], 16);
                modalMap.invalidateSize();
            }

            if (currentMarker) {
                modalMap.removeLayer(currentMarker);
            }

            currentMarker = L.marker([lat, lng]).addTo(modalMap)
                .bindPopup('<b>' + shopName + '</b><br>' + address).openPopup();
        }, { once: true });
    }

    // Live Search and Status Filtering Logic
    const searchInput = document.getElementById('liveSearchInput');
    const paymentFilter = document.getElementById('paymentFilterSelect');
    const tableRows = document.querySelectorAll('.order-row');
    const mobileCards = document.querySelectorAll('.order-card-item');

    function filterOrders() {
        const query = searchInput.value.toLowerCase().trim();
        const payStatus = paymentFilter.value;

        // Desktop Table Rows
        tableRows.forEach(row => {
            const shop = row.getAttribute('data-shop');
            const phone = row.getAttribute('data-phone');
            const officer = row.getAttribute('data-officer');
            const payment = row.getAttribute('data-payment');

            const matchesSearch = shop.includes(query) || phone.includes(query) || officer.includes(query);
            const matchesPay = (payStatus === 'all') || (payment === payStatus);

            row.style.display = (matchesSearch && matchesPay) ? '' : 'none';
        });

        // Mobile Cards
        mobileCards.forEach(card => {
            const shop = card.getAttribute('data-shop');
            const phone = card.getAttribute('data-phone');
            const officer = card.getAttribute('data-officer');
            const payment = card.getAttribute('data-payment');

            const matchesSearch = shop.includes(query) || phone.includes(query) || officer.includes(query);
            const matchesPay = (payStatus === 'all') || (payment === payStatus);

            card.style.display = (matchesSearch && matchesPay) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterOrders);
    paymentFilter.addEventListener('change', filterOrders);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>