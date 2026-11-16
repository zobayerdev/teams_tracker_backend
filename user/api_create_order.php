<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// ১. Token Authentication Check
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$token = '';

if (!empty($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (empty($token)) {
    $token = trim($input['access_token'] ?? '');
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

// ২. Payload Validation
$shopName       = trim($input['shop_name'] ?? '');
$shopPhone      = trim($input['shop_phone'] ?? '');
$shopAddress    = trim($input['shop_address'] ?? '');
$latitude       = isset($input['latitude']) && $input['latitude'] !== '' ? (float)$input['latitude'] : null;
$longitude      = isset($input['longitude']) && $input['longitude'] !== '' ? (float)$input['longitude'] : null;
$paymentStatus  = trim($input['payment_status'] ?? 'due');
$orderDate      = trim($input['order_date'] ?? date('Y-m-d'));
$items          = $input['items'] ?? [];

if (empty($shopName) || empty($shopPhone)) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'Shop name and shop phone are required.'
    ]);
    exit;
}

if (empty($items) || !is_array($items)) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'Items list must be a non-empty array of products.'
    ]);
    exit;
}

// ৩. Database Transaction Shuru
try {
    $pdo->beginTransaction();

    $calculatedTotal = 0;
    $validatedItems = [];

    // Shob item iterate kore price & subtotal check kora
    foreach ($items as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $qty       = (int)($item['quantity'] ?? 0);

        if ($productId <= 0 || $qty <= 0) {
            throw new Exception("Proti ta item-e valid product_id ebong quantity (> 0) thaka lagbe.");
        }

        // Product database theke fetch kora
        $pStmt = $pdo->prepare("SELECT id, product_name, shop_price, stock_quantity FROM products WHERE id = ? AND status = 'active' FOR UPDATE");
        $pStmt->execute([$productId]);
        $product = $pStmt->fetch();

        if (!$product) {
            throw new Exception("Product ID: {$productId} khuje pawa jay ni ba inactive ache.");
        }

        // Custom price jodi app theke na pathay, shop_price default hobe
        $unitPrice = isset($item['unit_price']) && (float)$item['unit_price'] >= 0 
                     ? (float)$item['unit_price'] 
                     : (float)$product['shop_price'];

        $subtotal = $qty * $unitPrice;
        $calculatedTotal += $subtotal;

        $validatedItems[] = [
            'product_id'   => $productId,
            'product_name' => $product['product_name'],
            'quantity'     => $qty,
            'unit_price'   => $unitPrice,
            'subtotal'     => $subtotal
        ];
    }

    // ৪. Insert into orders table
    $orderStmt = $pdo->prepare("
        INSERT INTO orders (
            employee_id, shop_name, shop_phone, shop_address, 
            latitude, longitude, total_amount, payment_status, 
            order_status, order_date, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, NOW())
    ");
    $orderStmt->execute([
        $employeeId, $shopName, $shopPhone, $shopAddress, 
        $latitude, $longitude, $calculatedTotal, $paymentStatus, $orderDate
    ]);

    $orderId = (int)$pdo->lastInsertId();

    // ৫. Insert into order_items table & Deduct Stock
    $itemInsertStmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) 
        VALUES (?, ?, ?, ?, ?)
    ");

    $stockUpdateStmt = $pdo->prepare("
        UPDATE products 
        SET stock_quantity = GREATEST(0, stock_quantity - ?) 
        WHERE id = ?
    ");

    foreach ($validatedItems as $vItem) {
        $itemInsertStmt->execute([
            $orderId, 
            $vItem['product_id'], 
            $vItem['quantity'], 
            $vItem['unit_price'], 
            $vItem['subtotal']
        ]);

        // Stock komano
        $stockUpdateStmt->execute([$vItem['quantity'], $vItem['product_id']]);
    }

    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        'status'  => true,
        'message' => 'Order created successfully.',
        'data'    => [
            'order_id'       => $orderId,
            'employee_name'  => $session['name'],
            'shop_name'      => $shopName,
            'shop_phone'     => $shopPhone,
            'total_amount'   => $calculatedTotal,
            'payment_status' => $paymentStatus,
            'total_items'    => count($validatedItems),
            'items'          => $validatedItems,
            'order_date'     => $orderDate
        ]
    ]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => $e->getMessage()
    ]);
    exit;
}