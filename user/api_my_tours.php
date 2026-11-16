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

// ২. Query Tour Plans
$statusFilter = strtolower(trim($_GET['status'] ?? ''));

$query = "
    SELECT 
        tp.id AS tour_id,
        tp.title,
        tp.destination,
        tp.start_date,
        tp.end_date,
        tp.total_days,
        tp.purpose,
        tp.est_transport,
        tp.est_da,
        tp.est_hotel,
        tp.est_other,
        tp.total_estimated_budget,
        tp.status,
        tp.approved_budget,
        tp.action_at,
        tp.admin_remarks,
        tp.created_at,
        adm.name AS reviewer_name
    FROM tour_plans tp
    LEFT JOIN users adm ON tp.action_by = adm.id
    WHERE tp.employee_id = :emp_id
";

$params = [':emp_id' => $employeeId];

if (!empty($statusFilter) && in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
    $query .= " AND tp.status = :status";
    $params[':status'] = $statusFilter;
}

$query .= " ORDER BY tp.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tours = $stmt->fetchAll();

// ৩. Response Data Formatting & Summary Calculation
$tourList = [];
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$totalApprovedBudget = 0;

foreach ($tours as $row) {
    $status = $row['status'];
    $approvedBudget = $row['approved_budget'] !== null ? (float)$row['approved_budget'] : null;

    if ($status === 'approved') {
        $approvedCount++;
        $totalApprovedBudget += ($approvedBudget ?? 0.00);
    } elseif ($status === 'rejected') {
        $rejectedCount++;
    } else {
        $pendingCount++;
    }

    $tourList[] = [
        'tour_id'                => (int)$row['tour_id'],
        'title'                  => $row['title'],
        'destination'            => $row['destination'],
        'start_date'             => $row['start_date'],
        'end_date'               => $row['end_date'],
        'total_days'             => (int)$row['total_days'],
        'purpose'                => $row['purpose'],
        'expense_breakdown'      => [
            'transport_ta'       => (float)$row['est_transport'],
            'daily_allowance_da' => (float)$row['est_da'],
            'hotel'              => (float)$row['est_hotel'],
            'other'              => (float)$row['est_other']
        ],
        'total_estimated_budget' => (float)$row['total_estimated_budget'],
        'status'                 => $status,
        'approved_budget'        => $approvedBudget,
        'review_details'         => [
            'reviewed_by'        => $row['reviewer_name'] ?? null,
            'remarks'            => $row['admin_remarks'] ?? null,
            'action_at'          => $row['action_at'] ?? null
        ],
        'submitted_at'           => $row['created_at']
    ];
}

// ৪. Structured JSON Response
http_response_code(200);
echo json_encode([
    'status'  => true,
    'message' => 'Tour history fetched successfully.',
    'summary' => [
        'total_tours'            => count($tourList),
        'approved'               => $approvedCount,
        'pending'                => $pendingCount,
        'rejected'               => $rejectedCount,
        'total_approved_budget'  => $totalApprovedBudget
    ],
    'data'    => $tourList
]);
exit;