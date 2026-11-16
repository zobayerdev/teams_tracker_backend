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

// ২. Query Leave Requests
$query = "
    SELECT 
        lr.id AS leave_id,
        lr.leave_type,
        lr.start_date,
        lr.end_date,
        lr.total_days,
        lr.reason,
        lr.supervisor_status,
        lr.supervisor_remarks,
        lr.supervisor_action_at,
        sup.name AS supervisor_name,
        lr.admin_status,
        lr.admin_remarks,
        lr.admin_action_at,
        adm.name AS admin_name,
        lr.final_status,
        lr.created_at
    FROM leave_requests lr
    LEFT JOIN users sup ON lr.supervisor_action_by = sup.id
    LEFT JOIN users adm ON lr.admin_action_by = adm.id
    WHERE lr.employee_id = :emp_id
    ORDER BY lr.id DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute([':emp_id' => $employeeId]);
$leaves = $stmt->fetchAll();

// ৩. Response Data Formatting
$leaveList = [];
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

foreach ($leaves as $row) {
    if ($row['final_status'] === 'approved') {
        $approvedCount++;
    } elseif ($row['final_status'] === 'rejected') {
        $rejectedCount++;
    } else {
        $pendingCount++;
    }

    $leaveList[] = [
        'leave_id'          => (int)$row['leave_id'],
        'leave_type'        => $row['leave_type'],
        'start_date'        => $row['start_date'],
        'end_date'          => $row['end_date'],
        'total_days'        => (int)$row['total_days'],
        'reason'            => $row['reason'],
        'supervisor_approval' => [
            'status'        => $row['supervisor_status'],
            'reviewed_by'   => $row['supervisor_name'] ?? null,
            'remarks'       => $row['supervisor_remarks'] ?? null,
            'action_at'     => $row['supervisor_action_at'] ?? null
        ],
        'admin_approval'    => [
            'status'        => $row['admin_status'],
            'reviewed_by'   => $row['admin_name'] ?? null,
            'remarks'       => $row['admin_remarks'] ?? null,
            'action_at'     => $row['admin_action_at'] ?? null
        ],
        'final_status'      => $row['final_status'],
        'applied_at'        => $row['created_at']
    ];
}

// ৪. Clean JSON Output
http_response_code(200);
echo json_encode([
    'status'  => true,
    'message' => 'Leave history fetched successfully.',
    'summary' => [
        'total_applied' => count($leaveList),
        'approved'      => $approvedCount,
        'pending'       => $pendingCount,
        'rejected'      => $rejectedCount
    ],
    'data'    => $leaveList
]);
exit;