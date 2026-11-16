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

// Jodi Header-e na thake, body check kora
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

// Token verify & User identify kora
$tokenStmt = $pdo->prepare("
    SELECT t.user_id, t.expires_at, u.status, u.role, u.name 
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

// ২. Location Coordinates & Metadata Catch kora
$latitude     = isset($input['latitude']) ? (float)$input['latitude'] : null;
$longitude    = isset($input['longitude']) ? (float)$input['longitude'] : null;
$address      = trim($input['address'] ?? '');
$batteryLevel = isset($input['battery_level']) ? (int)$input['battery_level'] : null;
$speed        = isset($input['speed']) ? (float)$input['speed'] : 0.00;

if ($latitude === null || $longitude === null) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'Valid latitude and longitude are required.'
    ]);
    exit;
}

// ৩. Locations Table-e Insert kora
$userId = (int)$session['user_id'];

$insertStmt = $pdo->prepare("
    INSERT INTO locations (user_id, latitude, longitude, address, battery_level, speed, recorded_at) 
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");

if ($insertStmt->execute([$userId, $latitude, $longitude, $address, $batteryLevel, $speed])) {
    http_response_code(200);
    echo json_encode([
        'status'  => true,
        'message' => 'Location point recorded successfully.',
        'data'    => [
            'user_id'      => $userId,
            'name'         => $session['name'],
            'role'         => $session['role'],
            'latitude'     => $latitude,
            'longitude'    => $longitude,
            'recorded_at'  => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status'  => false,
        'message' => 'Failed to save location data.'
    ]);
}
exit;