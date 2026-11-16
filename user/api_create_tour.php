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
$title        = trim($input['title'] ?? '');
$destination  = trim($input['destination'] ?? '');
$startDate    = trim($input['start_date'] ?? '');
$endDate      = trim($input['end_date'] ?? '');
$purpose      = trim($input['purpose'] ?? '');

$transport    = max(0, (float)($input['est_transport'] ?? 0));
$da           = max(0, (float)($input['est_da'] ?? 0));
$hotel        = max(0, (float)($input['est_hotel'] ?? 0));
$other        = max(0, (float)($input['est_other'] ?? 0));

if (empty($title) || empty($destination) || empty($startDate) || empty($endDate) || empty($purpose)) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'title, destination, start_date, end_date, and purpose are required.'
    ]);
    exit;
}

// Date & Days Calculation
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
$totalEstimatedBudget = $transport + $da + $hotel + $other;

// ৩. Insert into tour_plans Table
$stmt = $pdo->prepare("
    INSERT INTO tour_plans (
        employee_id, title, destination, start_date, end_date, total_days,
        purpose, est_transport, est_da, est_hotel, est_other,
        total_estimated_budget, status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
");

if ($stmt->execute([
    $employeeId, $title, $destination, $startDate, $endDate, $totalDays,
    $purpose, $transport, $da, $hotel, $other, $totalEstimatedBudget
])) {
    $tourId = (int)$pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'status'  => true,
        'message' => 'Tour plan submitted successfully.',
        'data'    => [
            'tour_id'                => $tourId,
            'employee_name'          => $session['name'],
            'title'                  => $title,
            'destination'            => $destination,
            'start_date'             => $startDate,
            'end_date'               => $endDate,
            'total_days'             => $totalDays,
            'purpose'                => $purpose,
            'expense_breakdown'      => [
                'transport_ta'       => $transport,
                'daily_allowance_da' => $da,
                'hotel'              => $hotel,
                'other'              => $other
            ],
            'total_estimated_budget' => $totalEstimatedBudget,
            'status'                 => 'pending',
            'submitted_at'           => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status'  => false,
        'message' => 'Failed to submit tour plan.'
    ]);
}
exit;