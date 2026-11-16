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
    SELECT t.user_id, t.expires_at, u.*
    FROM user_tokens t
    JOIN users u ON t.user_id = u.id
    WHERE t.token = ? LIMIT 1
");
$tokenStmt->execute([$token]);
$user = $tokenStmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode([
        'status'  => false,
        'message' => 'Invalid or expired session token.'
    ]);
    exit;
}

if (strtotime($user['expires_at']) < time()) {
    http_response_code(401);
    echo json_encode([
        'status'  => false,
        'message' => 'Session expired. Please log in again.'
    ]);
    exit;
}

if ($user['status'] !== 'active') {
    http_response_code(403);
    echo json_encode([
        'status'  => false,
        'message' => 'Account is inactive or pending approval.'
    ]);
    exit;
}

$userId = (int)$user['id'];

// ==========================================
// ২. GET METHOD: প্রোফাইল ও পারফরম্যান্স ডাটা
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // সুপারভাইজারের তথ্য
    $supervisorInfo = null;
    if (!empty($user['supervisor_id'])) {
        $supStmt = $pdo->prepare("SELECT id, name, phone, email, designation FROM users WHERE id = ?");
        $supStmt->execute([$user['supervisor_id']]);
        $supervisor = $supStmt->fetch();
        if ($supervisor) {
            $supervisorInfo = [
                'id'          => (int)$supervisor['id'],
                'name'        => $supervisor['name'],
                'phone'       => $supervisor['phone'],
                'email'       => $supervisor['email'],
                'designation' => $supervisor['designation'] ?? 'Supervisor'
            ];
        }
    }

    // Performance Stats: Sales
    $salesStmt = $pdo->prepare("SELECT COUNT(id) AS total_orders, COALESCE(SUM(total_amount), 0) AS total_sales FROM orders WHERE employee_id = ?");
    $salesStmt->execute([$userId]);
    $salesStats = $salesStmt->fetch();

    // Performance Stats: Collection
    $colStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total_collected FROM collections WHERE employee_id = ?");
    $colStmt->execute([$userId]);
    $totalCollected = (float)$colStmt->fetchColumn();

    // Leave Stats
    $leaveStmt = $pdo->prepare("SELECT COUNT(id) FROM leave_requests WHERE employee_id = ? AND final_status = 'pending'");
    $leaveStmt->execute([$userId]);
    $pendingLeaves = (int)$leaveStmt->fetchColumn();

    // Tour Stats
    $tourStmt = $pdo->prepare("SELECT COUNT(id) FROM tour_plans WHERE employee_id = ? AND status = 'approved'");
    $tourStmt->execute([$userId]);
    $approvedTours = (int)$tourStmt->fetchColumn();

    http_response_code(200);
    echo json_encode([
        'status'  => true,
        'message' => 'Profile data fetched successfully.',
        'data'    => [
            'profile' => [
                'id'                => $userId,
                'name'              => $user['name'],
                'email'             => $user['email'],
                'mobile'            => $user['phone'],
                'designation'       => $user['designation'] ?? 'Field Staff',
                'role_type'         => ($user['role'] === 'employee') ? 'sales_representative' : $user['role'],
                'status'            => $user['status'],
                'age'               => $user['age'],
                'nid'               => $user['nid'],
                'dob'               => $user['dob'],
                'father_name'       => $user['father_name'],
                'mother_name'       => $user['mother_name'],
                'religion'          => $user['religion'],
                'blood_group'       => $user['blood_group'],
                'education'         => $user['education'],
                'present_address'   => $user['present_address'],
                'permanent_address' => $user['permanent_address'],
                'salary'            => (float)($user['salary'] ?? 0.00),
                'member_since'      => date('d M, Y', strtotime($user['created_at']))
            ],
            'supervisor' => $supervisorInfo,
            'statistics' => [
                'total_orders'    => (int)$salesStats['total_orders'],
                'total_sales'     => (float)$salesStats['total_sales'],
                'total_collected' => $totalCollected,
                'pending_leaves'  => $pendingLeaves,
                'approved_tours'  => $approvedTours
            ]
        ]
    ]);
    exit;
}

// ==========================================
// ৩. POST METHOD: প্রোফাইল বা পাসওয়ার্ড আপডেট
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($input['action'] ?? 'update_profile');

    if ($action === 'change_password') {
        $currentPassword = $input['current_password'] ?? '';
        $newPassword     = $input['new_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Current password and new password are required.']);
            exit;
        }

        if (strlen($newPassword) < 6) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'New password must be at least 6 characters.']);
            exit;
        }

        if (!password_verify($currentPassword, $user['password'])) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Incorrect current password.']);
            exit;
        }

        $newHashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $upPass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upPass->execute([$newHashed, $userId]);

        http_response_code(200);
        echo json_encode(['status' => true, 'message' => 'Password changed successfully.']);
        exit;
    }

    // Default: প্রোফাইল তথ্য আপডেট (নাম ও অন্যান্য তথ্য)
    $name              = trim($input['name'] ?? $user['name']);
    $age               = isset($input['age']) ? (int)$input['age'] : $user['age'];
    $nid               = trim($input['nid'] ?? ($user['nid'] ?? ''));
    $dob               = !empty($input['dob']) ? trim($input['dob']) : $user['dob'];
    $father_name       = trim($input['father_name'] ?? ($user['father_name'] ?? ''));
    $mother_name       = trim($input['mother_name'] ?? ($user['mother_name'] ?? ''));
    $religion          = trim($input['religion'] ?? ($user['religion'] ?? ''));
    $blood_group       = trim($input['blood_group'] ?? ($user['blood_group'] ?? ''));
    $education         = trim($input['education'] ?? ($user['education'] ?? ''));
    $present_address   = trim($input['present_address'] ?? ($user['present_address'] ?? ''));
    $permanent_address = trim($input['permanent_address'] ?? ($user['permanent_address'] ?? ''));
    $designation       = trim($input['designation'] ?? ($user['designation'] ?? 'Field Staff'));

    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Name cannot be empty.']);
        exit;
    }

    $upProfile = $pdo->prepare("
        UPDATE users 
        SET name = ?, age = ?, nid = ?, dob = ?, father_name = ?, mother_name = ?, 
            religion = ?, blood_group = ?, education = ?, present_address = ?, permanent_address = ?, designation = ? 
        WHERE id = ?
    ");
    $upProfile->execute([
        $name, $age, $nid, $dob, $father_name, $mother_name,
        $religion, $blood_group, $education, $present_address, $permanent_address, $designation,
        $userId
    ]);

    http_response_code(200);
    echo json_encode([
        'status'  => true,
        'message' => 'Profile updated successfully.',
        'data'    => [
            'id'                => $userId,
            'name'              => $name,
            'email'             => $user['email'],
            'mobile'            => $user['phone'],
            'designation'       => $designation,
            'role_type'         => ($user['role'] === 'employee') ? 'sales_representative' : $user['role'],
            'age'               => $age,
            'nid'               => $nid,
            'dob'               => $dob,
            'father_name'       => $father_name,
            'mother_name'       => $mother_name,
            'religion'          => $religion,
            'blood_group'       => $blood_group,
            'education'         => $education,
            'present_address'   => $present_address,
            'permanent_address' => $permanent_address
        ]
    ]);
    exit;
}