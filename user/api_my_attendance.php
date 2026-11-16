<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// User Identification
$tokenStmt = $pdo->prepare("
    SELECT t.user_id, t.expires_at, u.name, u.phone, u.role, u.designation, u.status 
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

// 2. Month and Year Filter Handle
// Example: ?month=2026-10 ba default current month
$requestedMonth = trim($_GET['month'] ?? date('Y-m'));

if (!preg_match('/^\d{4}-\d{2}$/', $requestedMonth)) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'Invalid month format. Please use YYYY-MM (e.g. 2026-10).'
    ]);
    exit;
}

$startDate = $requestedMonth . '-01';
$endDate   = date('Y-m-t', strtotime($startDate));

// 3. Fetch Attendance Records for the Given Month
$stmt = $pdo->prepare("
    SELECT 
        id, date, in_time, out_time,
        in_latitude, in_longitude, in_address,
        out_latitude, out_longitude, out_address,
        status, working_hours, created_at
    FROM attendances
    WHERE user_id = ? AND date BETWEEN ? AND ?
    ORDER BY date DESC
");
$stmt->execute([$userId, $startDate, $endDate]);
$records = $stmt->fetchAll();

// 4. Monthly Analytics Calculation
$totalPresent = 0;
$totalLate    = 0;
$totalAbsent  = 0;
$totalWorkingHoursSum = 0.00;
$formattedList = [];

foreach ($records as $row) {
    if ($row['status'] === 'present') {
        $totalPresent++;
    } elseif ($row['status'] === 'late') {
        $totalLate++;
    } elseif ($row['status'] === 'absent') {
        $totalAbsent++;
    }

    $hours = (float)($row['working_hours'] ?? 0);
    $totalWorkingHoursSum += $hours;

    // Duration formatting
    $durationText = 'Incomplete';
    if (!empty($row['out_time'])) {
        $h = floor($hours);
        $m = round(($hours - $h) * 60);
        $durationText = "{$h}h {$m}m";
    }

    $formattedList[] = [
        'id'            => (int)$row['id'],
        'date'          => $row['date'],
        'day_name'      => date('l', strtotime($row['date'])),
        'in_time'       => date('h:i:s A', strtotime($row['in_time'])),
        'out_time'      => !empty($row['out_time']) ? date('h:i:s A', strtotime($row['out_time'])) : null,
        'working_hours' => $durationText,
        'decimal_hours' => $hours,
        'status'        => $row['status'],
        'check_in'      => [
            'latitude'  => (float)$row['in_latitude'],
            'longitude' => (float)$row['in_longitude'],
            'address'   => $row['in_address']
        ],
        'check_out'     => !empty($row['out_time']) ? [
            'latitude'  => (float)$row['out_latitude'],
            'longitude' => (float)$row['out_longitude'],
            'address'   => $row['out_address']
        ] : null
    ];
}

// 5. Output Response
http_response_code(200);
echo json_encode([
    'status'  => true,
    'message' => 'Monthly attendance records fetched successfully.',
    'month'   => $requestedMonth,
    'employee' => [
        'id'          => $userId,
        'name'        => $user['name'],
        'mobile'      => $user['phone'],
        'designation' => $user['designation'] ?? 'Field Officer',
        'role'        => $user['role']
    ],
    'summary' => [
        'total_working_days_logged' => count($formattedList),
        'present_days'              => $totalPresent,
        'late_days'                 => $totalLate,
        'absent_days'               => $totalAbsent,
        'total_hours_worked'        => round($totalWorkingHoursSum, 2)
    ],
    'data' => $formattedList
]);
exit;