<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';
$today  = date('Y-m-d');
$now    = date('H:i:s');

// Helper JSON response function
function sendResponse($status, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'status'  => $status,
        'message' => $message,
        'data'    => $data
    ]);
    exit;
}

// 1. GET TODAY'S ATTENDANCE STATUS
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'today_status') {
    $userId = (int)($_GET['user_id'] ?? 0);

    if ($userId <= 0) {
        sendResponse(false, 'Valid User ID is required', null, 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM attendances WHERE user_id = ? AND attendance_date = ? LIMIT 1");
    $stmt->execute([$userId, $today]);
    $attendance = $stmt->fetch();

    if ($attendance) {
        sendResponse(true, 'Attendance record found for today.', $attendance);
    } else {
        sendResponse(true, 'No attendance recorded for today.', [
            'status' => 'absent',
            'check_in_time' => null,
            'check_out_time' => null
        ]);
    }
}

// 2. CHECK-IN ACTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'check_in') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;

    $userId    = (int)($data['user_id'] ?? 0);
    $lat       = !empty($data['latitude']) ? (float)$data['latitude'] : null;
    $lng       = !empty($data['longitude']) ? (float)$data['longitude'] : null;
    $address   = trim($data['address'] ?? '');
    $remarks   = trim($data['remarks'] ?? '');

    if ($userId <= 0) {
        sendResponse(false, 'Valid User ID is required.', null, 400);
    }

    // Check existing record
    $checkStmt = $pdo->prepare("SELECT id, check_in_time FROM attendances WHERE user_id = ? AND attendance_date = ? LIMIT 1");
    $checkStmt->execute([$userId, $today]);
    $existing = $checkStmt->fetch();

    if ($existing && !empty($existing['check_in_time'])) {
        sendResponse(false, 'Already checked in for today at ' . date('h:i A', strtotime($existing['check_in_time'])), null, 409);
    }

    // Determine late check-in (e.g., after 09:30 AM)
    $isLate = (strtotime($now) > strtotime('09:30:00')) ? 'late' : 'present';

    if ($existing) {
        $update = $pdo->prepare("
            UPDATE attendances 
            SET check_in_time = ?, check_in_lat = ?, check_in_lng = ?, check_in_address = ?, status = ?, remarks = ?
            WHERE id = ?
        ");
        $update->execute([$now, $lat, $lng, $address, $isLate, $remarks, $existing['id']]);
    } else {
        $insert = $pdo->prepare("
            INSERT INTO attendances (user_id, attendance_date, check_in_time, check_in_lat, check_in_lng, check_in_address, status, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([$userId, $today, $now, $lat, $lng, $address, $isLate, $remarks]);
    }

    sendResponse(true, 'Checked in successfully at ' . date('h:i A'), [
        'user_id'       => $userId,
        'date'          => $today,
        'check_in_time' => $now,
        'status'        => $isLate
    ]);
}

// 3. CHECK-OUT ACTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'check_out') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;

    $userId  = (int)($data['user_id'] ?? 0);
    $lat     = !empty($data['latitude']) ? (float)$data['latitude'] : null;
    $lng     = !empty($data['longitude']) ? (float)$data['longitude'] : null;
    $address = trim($data['address'] ?? '');

    if ($userId <= 0) {
        sendResponse(false, 'Valid User ID is required.', null, 400);
    }

    $checkStmt = $pdo->prepare("SELECT id, check_in_time, check_out_time FROM attendances WHERE user_id = ? AND attendance_date = ? LIMIT 1");
    $checkStmt->execute([$userId, $today]);
    $existing = $checkStmt->fetch();

    if (!$existing || empty($existing['check_in_time'])) {
        sendResponse(false, 'Cannot check-out without checking in first.', null, 400);
    }

    if (!empty($existing['check_out_time'])) {
        sendResponse(false, 'Already checked out today at ' . date('h:i A', strtotime($existing['check_out_time'])), null, 409);
    }

    $update = $pdo->prepare("
        UPDATE attendances 
        SET check_out_time = ?, check_out_lat = ?, check_out_lng = ?, check_out_address = ?
        WHERE id = ?
    ");
    $update->execute([$now, $lat, $lng, $address, $existing['id']]);

    sendResponse(true, 'Checked out successfully at ' . date('h:i A'), [
        'user_id'        => $userId,
        'date'           => $today,
        'check_out_time' => $now
    ]);
}

sendResponse(false, 'Invalid API action or request method.', null, 404);