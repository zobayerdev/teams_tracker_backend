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

// ১. Token Authentication Check
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

// Token verify
$tokenStmt = $pdo->prepare("
    SELECT t.user_id, t.expires_at, u.status 
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
        'message' => 'Your account is pending approval or inactive.'
    ]);
    exit;
}

// ২. Active Products Fetch Logic
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$baseUrl  = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');

$stmt = $pdo->prepare("
    SELECT 
        id, product_name, product_code, piece_per_box, stock_quantity,
        depo_price, shop_price, customer_price, image, status
    FROM products 
    WHERE status = 'active'
    ORDER BY product_name ASC
");
$stmt->execute();
$products = $stmt->fetchAll();

$productList = [];

foreach ($products as $p) {
    $perBox = (int)$p['piece_per_box'] > 0 ? (int)$p['piece_per_box'] : 1;
    $stockQty = (int)$p['stock_quantity'];

    $boxes = floor($stockQty / $perBox);
    $extraPcs = $stockQty % $perBox;

    $imageUrl = null;
    if (!empty($p['image'])) {
        $imageUrl = $baseUrl . '/uploads/products/' . $p['image'];
    }

    $productList[] = [
        'id'             => (int)$p['id'],
        'product_name'   => $p['product_name'],
        'product_code'   => $p['product_code'] ?? '',
        'piece_per_box'  => $perBox,
        'stock_quantity' => $stockQty,
        'stock_boxes'    => (int)$boxes,
        'stock_loose_pcs'=> (int)$extraPcs,
        'depo_price'     => (float)$p['depo_price'],
        'shop_price'     => (float)$p['shop_price'],
        'customer_price' => (float)$p['customer_price'],
        'image_url'      => $imageUrl
    ];
}

// ৩. Response
http_response_code(200);
echo json_encode([
    'status'       => true,
    'message'      => 'Active products fetched successfully.',
    'total_count'  => count($productList),
    'data'         => $productList
]);
exit;