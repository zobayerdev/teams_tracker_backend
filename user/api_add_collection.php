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
    echo json_encode(['status' => false, 'message' => 'Unauthorized. Access token is required.']);
    exit;
}

$tokenStmt = $pdo->prepare("
    SELECT t.user_id, t.expires_at, u.status, u.name 
    FROM user_tokens t
    JOIN users u ON t.user_id = u.id
    WHERE t.token = ? LIMIT 1
");
$tokenStmt->execute([$token]);
$session = $tokenStmt->fetch();

if (!$session || strtotime($session['expires_at']) < time() || $session['status'] !== 'active') {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Invalid or expired session.']);
    exit;
}

$employeeId = (int)$session['user_id'];

// ২. Payload Catch
$orderId        = isset($input['order_id']) && !empty($input['order_id']) ? (int)$input['order_id'] : null;
$shopName       = trim($input['shop_name'] ?? '');
$shopPhone      = trim($input['shop_phone'] ?? '');
$amount         = (float)($input['amount'] ?? 0);
$paymentMethod  = trim($input['payment_method'] ?? 'cash'); // cash, bkash, nagad, bank, cheque
$referenceNo    = trim($input['reference_no'] ?? '');
$collectionDate = trim($input['collection_date'] ?? date('Y-m-d'));
$note           = trim($input['note'] ?? '');

if (empty($shopName) || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Shop name and valid collected amount (> 0) are required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Jodi order_id pathano hoy, tahole oi order check kora
    $targetOrder = null;
    if ($orderId) {
        $ordStmt = $pdo->prepare("SELECT id, total_amount, payment_status, shop_name FROM orders WHERE id = ? FOR UPDATE");
        $ordStmt->execute([$orderId]);
        $targetOrder = $ordStmt->fetch();
    }

    // ৩. Insert into collections table
    $insStmt = $pdo->prepare("
        INSERT INTO collections (employee_id, order_id, shop_name, shop_phone, amount, payment_method, reference_no, collection_date, note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insStmt->execute([
        $employeeId, $orderId, $shopName, $shopPhone, $amount, 
        $paymentMethod, $referenceNo, $collectionDate, $note
    ]);
    $collectionId = $pdo->lastInsertId();

    // ৪. Auto Update Order Due/Paid Status
    $newPaymentStatus = 'due';
    $totalPaidSoFar = 0;

    if ($targetOrder) {
        // Ei order-e ager + ekhonker total koto taka collection holo ta hishab kora
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM collections WHERE order_id = ?");
        $sumStmt->execute([$orderId]);
        $totalPaidSoFar = (float)$sumStmt->fetchColumn();

        $orderTotal = (float)$targetOrder['total_amount'];

        if ($totalPaidSoFar >= $orderTotal) {
            $newPaymentStatus = 'paid';
        } elseif ($totalPaidSoFar > 0) {
            $newPaymentStatus = 'partial';
        }

        // Order status update
        $upStmt = $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
        $upStmt->execute([$newPaymentStatus, $orderId]);
    }

    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        'status'  => true,
        'message' => 'Due payment collected successfully.',
        'data'    => [
            'collection_id'     => (int)$collectionId,
            'order_id'          => $orderId,
            'shop_name'         => $shopName,
            'collected_amount'  => $amount,
            'payment_method'    => $paymentMethod,
            'order_status'      => $newPaymentStatus,
            'total_paid'        => $totalPaidSoFar,
            'collector_name'    => $session['name']
        ]
    ]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}