<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// 1. Token Authentication Check
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$token = '';

if (!empty($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

if (empty($token)) {
    $token = trim($_GET['access_token'] ?? '');
}

if (empty($token)) {
    http_response_code(401);
    echo json_encode([
        'status'  => false,
        'message' => 'Unauthorized. Access token is required.'
    ]);
    exit;
}

// Token verify & user info fetch
$tokenStmt = $pdo->prepare("
    SELECT t.user_id, t.expires_at, u.status, u.name 
    FROM user_tokens t
    JOIN users u ON t.user_id = u.id
    WHERE t.token = ? LIMIT 1
");
$tokenStmt->execute([$token]);
$session = $tokenStmt->fetch();

if (!$session) {
    http_response_code(401);
    echo json_encode([
        'status'  => false,
        'message' => 'Invalid or expired session token.'
    ]);
    exit;
}

if (strtotime($session['expires_at']) < time()) {
    http_response_code(401);
    echo json_encode([
        'status'  => false,
        'message' => 'Session expired. Please log in again.'
    ]);
    exit;
}

if ($session['status'] !== 'active') {
    http_response_code(403);
    echo json_encode([
        'status'  => false,
        'message' => 'Account is inactive or pending approval.'
    ]);
    exit;
}

$employeeId = (int)$session['user_id'];

// 2. Filter Parameters Catch
$filterDate = trim($_GET['date'] ?? '');
$fromDate   = trim($_GET['from_date'] ?? '');
$toDate     = trim($_GET['to_date'] ?? '');
$status     = trim($_GET['payment_status'] ?? '');

$query = "
    SELECT 
        o.id, o.shop_name, o.shop_phone, o.shop_address, 
        o.latitude, o.longitude, o.total_amount, 
        o.payment_status, o.order_status, o.order_date, o.created_at
    FROM orders o
    WHERE o.employee_id = :emp_id
";

$params = [':emp_id' => $employeeId];

if (!empty($filterDate)) {
    $query .= " AND o.order_date = :filter_date";
    $params[':filter_date'] = $filterDate;
} elseif (!empty($fromDate) && !empty($toDate)) {
    $query .= " AND o.order_date BETWEEN :from_date AND :to_date";
    $params[':from_date'] = $fromDate;
    $params[':to_date']   = $toDate;
}

if (!empty($status)) {
    $query .= " AND o.payment_status = :status";
    $params[':status'] = $status;
}

$query .= " ORDER BY o.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// 3. Nested Order Items & Collection Mapping
$orderIds = array_column($orders, 'id');
$itemsMap = [];
$collectionMap = [];

if (!empty($orderIds)) {
    $inClause = implode(',', array_fill(0, count($orderIds), '?'));

    // Fetch ordered products
    $itemsStmt = $pdo->prepare("
        SELECT 
            oi.order_id, oi.product_id, oi.quantity, oi.unit_price, oi.subtotal,
            p.product_name, p.product_code
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id IN ($inClause)
    ");
    $itemsStmt->execute($orderIds);
    $items = $itemsStmt->fetchAll();

    foreach ($items as $item) {
        $itemsMap[$item['order_id']][] = [
            'product_id'   => (int)$item['product_id'],
            'product_name' => $item['product_name'],
            'product_code' => $item['product_code'] ?? '',
            'quantity'     => (int)$item['quantity'],
            'unit_price'   => (float)$item['unit_price'],
            'subtotal'     => (float)$item['subtotal']
        ];
    }

    // Fetch total paid amount per order from collections table
    $colStmt = $pdo->prepare("
        SELECT order_id, COALESCE(SUM(amount), 0) AS total_collected
        FROM collections
        WHERE order_id IN ($inClause)
        GROUP BY order_id
    ");
    $colStmt->execute($orderIds);
    $collections = $colStmt->fetchAll();

    foreach ($collections as $c) {
        $collectionMap[$c['order_id']] = (float)$c['total_collected'];
    }
}

// 4. Build Structured Response & KPI Calculation
$formattedOrders = [];
$totalSalesSum = 0;
$totalCollectedSum = 0;

foreach ($orders as $row) {
    $orderId = (int)$row['id'];
    $totalAmount = (float)$row['total_amount'];
    
    // Paid amount calculation
    $paidAmount = isset($collectionMap[$orderId]) ? $collectionMap[$orderId] : ($row['payment_status'] === 'paid' ? $totalAmount : 0.00);
    $dueAmount = max(0, $totalAmount - $paidAmount);

    $totalSalesSum += $totalAmount;
    $totalCollectedSum += $paidAmount;

    $formattedOrders[] = [
        'order_id'       => $orderId,
        'shop_name'      => $row['shop_name'],
        'shop_phone'     => $row['shop_phone'],
        'shop_address'   => $row['shop_address'] ?? '',
        'latitude'       => $row['latitude'] !== null ? (float)$row['latitude'] : null,
        'longitude'      => $row['longitude'] !== null ? (float)$row['longitude'] : null,
        'total_amount'   => $totalAmount,
        'paid_amount'    => $paidAmount,
        'due_amount'     => $dueAmount,
        'payment_status' => $row['payment_status'],
        'order_status'   => $row['order_status'],
        'order_date'     => $row['order_date'],
        'created_time'   => date('h:i A', strtotime($row['created_at'])),
        'items_count'    => isset($itemsMap[$orderId]) ? count($itemsMap[$orderId]) : 0,
        'items'          => $itemsMap[$orderId] ?? []
    ];
}

// 5. JSON Response
http_response_code(200);
echo json_encode([
    'status'  => true,
    'message' => 'Orders fetched successfully.',
    'summary' => [
        'total_orders'    => count($formattedOrders),
        'total_sales'     => $totalSalesSum,
        'total_collected' => $totalCollectedSum,
        'total_due'       => max(0, $totalSalesSum - $totalCollectedSum)
    ],
    'data'    => $formattedOrders
]);
exit;