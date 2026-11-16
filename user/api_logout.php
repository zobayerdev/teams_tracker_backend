<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Preflight request handle
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// ১. Token Extract (Header অথবা JSON Body থেকে)
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$token = '';

if (!empty($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];
} else {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $token = trim($input['access_token'] ?? '');
}

if (empty($token)) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'Access token is required for logout.'
    ]);
    exit;
}

// ২. Token চেক এবং ডেটাবেস থেকে ডিলিট করা
$stmt = $pdo->prepare("SELECT id, user_id FROM user_tokens WHERE token = ?");
$stmt->execute([$token]);
$record = $stmt->fetch();

if (!$record) {
    http_response_code(401);
    echo json_encode([
        'status'  => false,
        'message' => 'Invalid or already expired token.'
    ]);
    exit;
}

// টোকেন মুছে ফেলা (Token Invalidation)
$delStmt = $pdo->prepare("DELETE FROM user_tokens WHERE token = ?");
$delStmt->execute([$token]);

http_response_code(200);
echo json_encode([
    'status'  => true,
    'message' => 'Successfully logged out. Access token has been invalidated.'
]);
exit;