<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// 1. Mandatory Fields
$name     = trim($input['name'] ?? '');
$phone    = trim($input['phone'] ?? ($input['mobile'] ?? ''));
$password = $input['password'] ?? '';

// 2. Optional Biographical & Identity Fields
$email             = trim($input['email'] ?? '');
$age               = !empty($input['age']) ? (int)$input['age'] : null;
$nid               = trim($input['nid'] ?? '');
$dob               = !empty($input['dob']) ? trim($input['dob']) : null;
$father_name       = trim($input['father_name'] ?? '');
$mother_name       = trim($input['mother_name'] ?? '');
$religion          = trim($input['religion'] ?? '');
$blood_group       = trim($input['blood_group'] ?? '');
$education         = trim($input['education'] ?? '');
$present_address   = trim($input['present_address'] ?? '');
$permanent_address = trim($input['permanent_address'] ?? '');
$designation       = trim($input['designation'] ?? 'Sales Representative');
$salary            = !empty($input['salary']) ? (float)$input['salary'] : 0.00;
$role_type         = trim($input['role_type'] ?? ($input['role'] ?? 'employee'));
$supervisor_id     = !empty($input['supervisor_id']) ? (int)$input['supervisor_id'] : null;

// Normalize role
if ($role_type === 'sales_representative') {
    $role_type = 'employee';
}

// 3. Validation
if (empty($name) || empty($phone) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'Mandatory fields missing: Full Name, Mobile Number, and Password are required.'
    ]);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode([
        'status'  => false,
        'message' => 'Password must be at least 6 characters long.'
    ]);
    exit;
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'status'  => false,
        'message' => 'Invalid email address format.'
    ]);
    exit;
}

// Check duplicate phone
$checkPhone = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
$checkPhone->execute([$phone]);
if ($checkPhone->fetch()) {
    http_response_code(409);
    echo json_encode([
        'status'  => false,
        'message' => 'This mobile number is already registered.'
    ]);
    exit;
}

// Check duplicate email if provided
if (!empty($email)) {
    $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch()) {
        http_response_code(409);
        echo json_encode([
            'status'  => false,
            'message' => 'This email address is already registered.'
        ]);
        exit;
    }
}

// 4. Save User to Database with 'active' status directly
try {
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $finalEmail = !empty($email) ? $email : null;

    $stmt = $pdo->prepare("
        INSERT INTO users (
            name, email, phone, password, role, designation,
            age, nid, dob, father_name, mother_name, religion, blood_group, education,
            present_address, permanent_address, salary, supervisor_id, status, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, 'active', NOW()
        )
    ");

    $success = $stmt->execute([
        $name, $finalEmail, $phone, $hashedPassword, $role_type, $designation,
        $age, $nid, $dob, $father_name, $mother_name, $religion, $blood_group, $education,
        $present_address, $permanent_address, $salary, $supervisor_id
    ]);

    if ($success) {
        $newUserId = (int)$pdo->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'status'  => true,
            'message' => 'Registration successful! Your account is active and you can now log in.',
            'data'    => [
                'user_id'           => $newUserId,
                'name'              => $name,
                'mobile'            => $phone,
                'email'             => $finalEmail,
                'designation'       => $designation,
                'role'              => ($role_type === 'employee') ? 'sales_representative' : $role_type,
                'status'            => 'active',
                'age'               => $age,
                'nid'               => $nid,
                'dob'               => $dob,
                'father_name'       => $father_name,
                'mother_name'       => $mother_name,
                'religion'          => $religion,
                'blood_group'       => $blood_group,
                'education'         => $education,
                'present_address'   => $present_address,
                'permanent_address' => $permanent_address,
                'salary'            => $salary,
                'supervisor_id'     => $supervisor_id
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status'  => false,
            'message' => 'Database error: Failed to complete registration.'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
