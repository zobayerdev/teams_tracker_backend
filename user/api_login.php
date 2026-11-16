<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../config/database.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$identifier = trim($input['identifier'] ?? '');
$password   = $input['password'] ?? '';

if (empty($identifier) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'Email/Phone and Password are required.'
    ]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR phone = ?) LIMIT 1");
$stmt->execute([$identifier, $identifier]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode([
        'status'  => false,
        'message' => 'Invalid email/phone or password.'
    ]);
    exit;
}

if ($user['status'] === 'pending') {
    http_response_code(403);
    echo json_encode([
        'status'  => false,
        'message' => 'Your account is pending admin approval.'
    ]);
    exit;
}

if ($user['status'] === 'inactive') {
    http_response_code(403);
    echo json_encode([
        'status'  => false,
        'message' => 'Your account has been deactivated.'
    ]);
    exit;
}

// Generate Secure 30-Day Access Token
$accessToken = bin2hex(random_bytes(32));
$expiresAt   = date('Y-m-d H:i:s', strtotime('+30 days'));

// Save token in user_tokens table
$tokenStmt = $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
$tokenStmt->execute([$user['id'], $accessToken, $expiresAt]);

// Clean API JSON Response
http_response_code(200);
echo json_encode([
    'status'  => true,
    'message' => 'Login successful',
    'data'    => [
        'id'           => (int)$user['id'],
        'name'         => $user['name'],
        'email'        => $user['email'],
        'mobile'       => $user['phone'],
        'designation'  => $user['designation'] ?? '',
        'role_type'    => $user['role'],
        'access_token' => $accessToken,
        'token_type'   => 'Bearer',
        'expires_at'   => $expiresAt
    ]
]);