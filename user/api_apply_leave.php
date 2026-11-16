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

// 1. Token Authentication Check
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
    SELECT t.user_id, t.expires_at, u.status, u.name, u.role 
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

// 2. Payload Validation
$leaveType = strtolower(trim($input['leave_type'] ?? 'casual'));
$startDate = trim($input['start_date'] ?? '');
$endDate   = trim($input['end_date'] ?? '');
$reason    = trim($input['reason'] ?? '');

$allowedTypes = ['casual', 'sick', 'emergency', 'other'];

if (!in_array($leaveType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'Invalid leave type. Allowed types: casual, sick, emergency, other.'
    ]);
    exit;
}

if (empty($startDate) || empty($endDate) || empty($reason)) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'start_date, end_date, and reason are required.'
    ]);
    exit;
}

// Date calculation
$startTime = strtotime($startDate);
$endTime   = strtotime($endDate);

if (!$startTime || !$endTime) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'Invalid date format. Use YYYY-MM-DD.'
    ]);
    exit;
}

if ($endTime < $startTime) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'end_date cannot be earlier than start_date.'
    ]);
    exit;
}

$totalDays = (int)(($endTime - $startTime) / (60 * 60 * 24)) + 1;

// 3. Insert into leave_requests Table
$stmt = $pdo->prepare("
    INSERT INTO leave_requests (
        employee_id, leave_type, start_date, end_date, total_days, reason,
        supervisor_status, admin_status, final_status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending', 'pending', NOW())
");

if ($stmt->execute([$employeeId, $leaveType, $startDate, $endDate, $totalDays, $reason])) {
    $leaveId = (int)$pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'status'  => true,
        'message' => 'Leave application submitted successfully.',
        'data'    => [
            'leave_id'          => $leaveId,
            'employee_name'     => $session['name'],
            'leave_type'        => $leaveType,
            'start_date'        => $startDate,
            'end_date'          => $endDate,
            'total_days'        => $totalDays,
            'reason'            => $reason,
            'supervisor_status' => 'pending',
            'admin_status'      => 'pending',
            'final_status'      => 'pending',
            'submitted_at'      => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status'  => false,
        'message' => 'Failed to submit leave application.'
    ]);
}
exit;