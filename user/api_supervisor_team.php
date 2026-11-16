<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
    $token = trim($input['access_token'] ?? ($_GET['access_token'] ?? ''));
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
    SELECT t.user_id, t.expires_at, u.id, u.name, u.role, u.status 
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

// Supervisor role validation
if (!in_array($session['role'], ['supervisor', 'admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode([
        'status'  => false,
        'message' => 'Access forbidden. Only supervisors and admins can access team data.'
    ]);
    exit;
}

$supervisorId = (int)$session['user_id'];

// ==========================================
// ২. GET METHOD: Team Dashboard & Real-Time Stats
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // সুপারভাইজারের আন্ডারে থাকা কর্মীদের লিস্ট
    $empStmt = $pdo->prepare("
        SELECT id, name, email, phone, designation, status, created_at
        FROM users 
        WHERE supervisor_id = ? AND role = 'employee'
        ORDER BY name ASC
    ");
    $empStmt->execute([$supervisorId]);
    $teamMembers = $empStmt->fetchAll();

    $teamData = [];
    $teamIds = [];
    $todayDate = date('Y-m-d');

    foreach ($teamMembers as $emp) {
        $empId = (int)$emp['id'];
        $teamIds[] = $empId;

        // লেটেস্ট লোকেশন ফেচ
        $locStmt = $pdo->prepare("
            SELECT latitude, longitude, address, battery_level, speed, recorded_at
            FROM locations
            WHERE user_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $locStmt->execute([$empId]);
        $latestLocation = $locStmt->fetch();

        // আজকের সেলস
        $salesStmt = $pdo->prepare("
            SELECT COUNT(id) AS today_orders, COALESCE(SUM(total_amount), 0) AS today_sales
            FROM orders
            WHERE employee_id = ? AND order_date = ?
        ");
        $salesStmt->execute([$empId, $todayDate]);
        $salesStats = $salesStmt->fetch();

        // আজকের কালেকশন
        $colStmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) AS today_collected
            FROM collections
            WHERE employee_id = ? AND collection_date = ?
        ");
        $colStmt->execute([$empId, $todayDate]);
        $todayCollected = (float)$colStmt->fetchColumn();

        $teamData[] = [
            'id'             => $empId,
            'name'           => $emp['name'],
            'phone'          => $emp['phone'],
            'email'          => $emp['email'],
            'designation'    => $emp['designation'] ?? 'Field Officer',
            'status'         => $emp['status'],
            'latest_location'=> $latestLocation ? [
                'latitude'      => (float)$latestLocation['latitude'],
                'longitude'     => (float)$latestLocation['longitude'],
                'address'       => $latestLocation['address'] ?? 'Spot Pinpoint',
                'battery_level' => $latestLocation['battery_level'] ? (int)$latestLocation['battery_level'] : null,
                'speed'         => (float)$latestLocation['speed'],
                'last_ping'     => $latestLocation['recorded_at']
            ] : null,
            'today_performance' => [
                'orders_booked'   => (int)$salesStats['today_orders'],
                'sales_amount'    => (float)$salesStats['today_sales'],
                'collection_amount' => $todayCollected
            ]
        ];
    }

    // টিমের পেন্ডিং লিভ রিকোয়েস্টসমূহ
    $pendingLeaves = [];
    if (!empty($teamIds)) {
        $inClause = implode(',', array_fill(0, count($teamIds), '?'));
        $leaveStmt = $pdo->prepare("
            SELECT lr.id AS leave_id, lr.employee_id, u.name AS employee_name, u.phone AS employee_phone,
                   lr.leave_type, lr.start_date, lr.end_date, lr.total_days, lr.reason, 
                   lr.supervisor_status, lr.admin_status, lr.created_at
            FROM leave_requests lr
            JOIN users u ON lr.employee_id = u.id
            WHERE lr.employee_id IN ($inClause) AND lr.supervisor_status = 'pending'
            ORDER BY lr.id DESC
        ");
        $leaveStmt->execute($teamIds);
        $pendingLeaves = $leaveStmt->fetchAll();
    }

    http_response_code(200);
    echo json_encode([
        'status'  => true,
        'message' => 'Supervisor team overview fetched successfully.',
        'data'    => [
            'supervisor' => [
                'id'   => $supervisorId,
                'name' => $session['name'],
                'role' => $session['role']
            ],
            'summary' => [
                'total_team_members' => count($teamData),
                'pending_leave_approvals' => count($pendingLeaves)
            ],
            'team_members'   => $teamData,
            'pending_leaves' => $pendingLeaves
        ]
    ]);
    exit;
}

// ==========================================
// ৩. POST METHOD: Supervisor Leave Review Action
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($input['action'] ?? '');

    if ($action === 'review_leave') {
        $leaveId      = (int)($input['leave_id'] ?? 0);
        $statusAction = strtolower(trim($input['status_action'] ?? ''));
        $remarks      = trim($input['remarks'] ?? '');

        if ($leaveId <= 0 || !in_array($statusAction, ['approved', 'rejected'])) {
            http_response_code(400);
            echo json_encode([
                'status'  => false,
                'message' => 'Invalid leave_id or status_action. Must be "approved" or "rejected".'
            ]);
            exit;
        }

        // আবেদনকারী কর্মকর্তা এই সুপারভাইজারের অধীনে আছে কিনা চেক
        $checkStmt = $pdo->prepare("
            SELECT lr.id, lr.employee_id, lr.supervisor_status 
            FROM leave_requests lr
            JOIN users u ON lr.employee_id = u.id
            WHERE lr.id = ? AND u.supervisor_id = ?
        ");
        $checkStmt->execute([$leaveId, $supervisorId]);
        $leave = $checkStmt->fetch();

        if (!$leave) {
            http_response_code(403);
            echo json_encode([
                'status'  => false,
                'message' => 'Unauthorized. This leave application does not belong to your team.'
            ]);
            exit;
        }

        // সুপারভাইজারের অ্যাকশন আপডেট
        $updateStmt = $pdo->prepare("
            UPDATE leave_requests
            SET supervisor_status = ?, 
                supervisor_action_by = ?, 
                supervisor_action_at = NOW(), 
                supervisor_remarks = ?
            WHERE id = ?
        ");

        if ($updateStmt->execute([$statusAction, $supervisorId, $remarks, $leaveId])) {
            http_response_code(200);
            echo json_encode([
                'status'  => true,
                'message' => "Leave application successfully {$statusAction} by supervisor.",
                'data'    => [
                    'leave_id'          => $leaveId,
                    'supervisor_status' => $statusAction,
                    'supervisor_action_by' => $session['name'],
                    'remarks'           => $remarks,
                    'action_at'         => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'status'  => false,
                'message' => 'Failed to record supervisor action.'
            ]);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'Invalid action parameter specified.'
    ]);
    exit;
}