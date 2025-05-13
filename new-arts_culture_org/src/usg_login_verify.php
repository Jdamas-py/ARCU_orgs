<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once 'DB_Connection.php';
header('Content-Type: application/json');

// Database connection
$con = new mysqli('localhost', 'root', '', 'db_arcu');
if ($con->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $con->connect_error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'No data received']);
        exit;
    }

    $userId = trim($data['userId']);
    $password = trim($data['password']);

    $stmt = $con->prepare("SELECT * FROM user WHERE user_id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $storedPassword = $user['password'];

        // Check if the stored password is hashed (starts with $2y$ for bcrypt)
        if (
            (strlen($storedPassword) === 60 && strpos($storedPassword, '$2y$') === 0) &&
            password_verify($password, $storedPassword)
        ) {
            loginSuccess($user);
        } elseif ($password === $storedPassword) {
            // Plain-text password match, migrate to hashed password
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $con->prepare("UPDATE user SET password = ? WHERE user_id = ?");
            $update->bind_param("ss", $newHash, $userId);
            $update->execute();
            $update->close();

            loginSuccess($user);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$con->close();

function loginSuccess($user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['acc_id'] = $user['user_id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'role' => $user['role']
    ]);
}
?>
