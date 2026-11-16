<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

date_default_timezone_set('Asia/Dhaka');

require_once __DIR__ . '/../config/database.php';

// 1. Bearer Token Authentication Check
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

// User Identification
$tokenStmt = $pdo->prepare("
    SELECT t.user_id, t.expires_at, u.name, u.email, u.phone, u.role, u.designation, u.status 
    FROM user_tokens t
    JOIN users u ON t.user_id = u.id
    WHERE t.token = ? LIMIT 1
");
$tokenStmt->execute([$token]);
$user = $tokenStmt->fetch();

if (!$user || strtotime($user['expires_at']) < time() || $user['status'] !== 'active') {
    http_response_code(401);
    echo json_encode([
        'status'  => false,
        'message' => 'Invalid or expired session. Please log in again.'
    ]);
    exit;
}

$userId = (int)$user['user_id'];

// 2. Device Payload Catch (Time, Lat, Lng, Address)
$latitude  = isset($input['latitude']) && $input['latitude'] !== '' ? (float)$input['latitude'] : null;
$longitude = isset($input['longitude']) && $input['longitude'] !== '' ? (float)$input['longitude'] : null;
$address   = trim($input['address'] ?? 'Field GPS Spot');

// Device theke time pathale sheta nibe, na hole server er current time dhorbe
$clientTime = trim($input['time'] ?? '');
$currentTime = !empty($clientTime) ? date('H:i:s', strtotime($clientTime)) : date('H:i:s');
$currentHourMinute = date('H:i', strtotime($currentTime));
$todayDate = date('Y-m-d');

if ($latitude === null || $longitude === null) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'Latitude and Longitude are required from device.'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 3. Ajker Attendance Check
    $stmt = $pdo->prepare("SELECT * FROM attendances WHERE user_id = ? AND date = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$userId, $todayDate]);
    $attendance = $stmt->fetch();

    // CASE 1: PROTHOM CLICK -> CHECK-IN LOGIC
    if (!$attendance) {
        // Office Timing Evaluation (09:00 AM start, 09:15 AM grace)
        if ($currentHourMinute <= '09:15') {
            $status = 'present';
            $remark = 'On Time Check-in';
        } elseif ($currentHourMinute > '09:15' && $currentHourMinute <= '17:00') {
            $status = 'late';
            $remark = 'Late Check-in (Office started at 09:00 AM)';
        } else {
            $status = 'absent';
            $remark = 'Check-in after duty hours (05:00 PM)';
        }

        $insStmt = $pdo->prepare("
            INSERT INTO attendances (
                user_id, date, in_time, in_latitude, in_longitude, in_address, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insStmt->execute([$userId, $todayDate, $currentTime, $latitude, $longitude, $address, $status]);
        $attendanceId = (int)$pdo->lastInsertId();

        // Location ping log
        $locStmt = $pdo->prepare("
            INSERT INTO locations (user_id, latitude, longitude, address, recorded_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $locStmt->execute([$userId, $latitude, $longitude, $address]);

        $pdo->commit();

        http_response_code(200);
        echo json_encode([
            'status'        => true,
            'action'        => 'check_in',
            'message'       => 'Duty Started! Check-in successful.',
            'user'          => [
                'id'          => $userId,
                'name'        => $user['name'],
                'mobile'      => $user['phone'],
                'email'       => $user['email'],
                'designation' => $user['designation'] ?? 'Field Officer',
                'role'        => $user['role']
            ],
            'attendance'    => [
                'id'            => $attendanceId,
                'date'          => $todayDate,
                'in_time'       => date('h:i:s A', strtotime($currentTime)),
                'out_time'      => null,
                'working_hours' => '0h 0m',
                'status'        => $status,
                'remark'        => $remark,
                'check_in_location' => [
                    'latitude'  => $latitude,
                    'longitude' => $longitude,
                    'address'   => $address
                ]
            ]
        ]);
        exit;
    }

    // CASE 2: DITIO CLICK -> CHECK-OUT LOGIC
    if (empty($attendance['out_time'])) {
        $inTime = strtotime($todayDate . ' ' . $attendance['in_time']);
        $outTime = strtotime($todayDate . ' ' . $currentTime);
        
        $durationSeconds = max(0, $outTime - $inTime);
        $hours = floor($durationSeconds / 3600);
        $minutes = floor(($durationSeconds % 3600) / 60);
        $decimalHours = round($durationSeconds / 3600, 2);

        $upStmt = $pdo->prepare("
            UPDATE attendances 
            SET out_time = ?, out_latitude = ?, out_longitude = ?, out_address = ?, working_hours = ?
            WHERE id = ?
        ");
        $upStmt->execute([$currentTime, $latitude, $longitude, $address, $decimalHours, $attendance['id']]);

        // Location ping log
        $locStmt = $pdo->prepare("
            INSERT INTO locations (user_id, latitude, longitude, address, recorded_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $locStmt->execute([$userId, $latitude, $longitude, $address]);

        $pdo->commit();

        http_response_code(200);
        echo json_encode([
            'status'        => true,
            'action'        => 'check_out',
            'message'       => 'Duty Closed! Check-out successful.',
            'user'          => [
                'id'          => $userId,
                'name'        => $user['name'],
                'mobile'      => $user['phone'],
                'email'       => $user['email'],
                'designation' => $user['designation'] ?? 'Field Officer',
                'role'        => $user['role']
            ],
            'attendance'    => [
                'id'            => (int)$attendance['id'],
                'date'          => $todayDate,
                'in_time'       => date('h:i:s A', strtotime($attendance['in_time'])),
                'out_time'      => date('h:i:s A', strtotime($currentTime)),
                'working_hours' => "{$hours}h {$minutes}m",
                'status'        => $attendance['status'],
                'check_in_location' => [
                    'latitude'  => (float)$attendance['in_latitude'],
                    'longitude' => (float)$attendance['in_longitude'],
                    'address'   => $attendance['in_address']
                ],
                'check_out_location' => [
                    'latitude'  => $latitude,
                    'longitude' => $longitude,
                    'address'   => $address
                ]
            ]
        ]);
        exit;
    }

    // CASE 3: DUITO-I COMPLETE THAKLE
    $pdo->commit();
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'action'  => 'already_completed',
        'message' => 'Ajker diner duty check-in ebong check-out already complete hoye gese.',
        'data'    => [
            'in_time'       => date('h:i A', strtotime($attendance['in_time'])),
            'out_time'      => date('h:i A', strtotime($attendance['out_time'])),
            'working_hours' => $attendance['working_hours'] . ' hrs',
            'status'        => $attendance['status']
        ]
    ]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status'  => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
}