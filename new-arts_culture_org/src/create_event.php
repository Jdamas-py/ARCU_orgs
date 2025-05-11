<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['acc_id'])) {
    header("Location: ARCU-Login.php");
    exit();
}

// Database connection
require_once 'db_connection.php';
try {
    $con = getDatabaseConnection();
    error_log("Database connection successful");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die('Database connection failed: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventName = trim($_POST['eventName'] ?? '');
    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';
    $description = trim($_POST['eventDescription'] ?? '');

    error_log("Received event data: " . print_r($_POST, true));

    $errors = [];

    // Validation
    if (empty($eventName)) {
        $errors[] = "Event name is required";
    }
    if (empty($startDate)) {
        $errors[] = "Start date is required";
    }
    if (empty($endDate)) {
        $errors[] = "End date is required";
    }
    if ($endDate < $startDate) {
        $errors[] = "End date cannot be before start date";
    }

    if (empty($errors)) {
        try {
            error_log("Attempting to insert event into database");
            
            $sql = "INSERT INTO events (eventname, status, startdate, enddate, description) VALUES (?, 'Scheduled', ?, ?, ?)";
            error_log("SQL Query: " . $sql);
            
            $stmt = $con->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("ssss", $eventName, $startDate, $endDate, $description);
                error_log("Parameters bound: eventname=$eventName, startdate=$startDate, enddate=$endDate, description=$description");
                
                if ($stmt->execute()) {
                    error_log("Event created successfully with ID: " . $stmt->insert_id);
                    $_SESSION['success_message'] = "Event created successfully!";
                    header("Location: dashboard.php#events");
                    exit();
                } else {
                    error_log("Error executing statement: " . $stmt->error);
                    $_SESSION['error_message'] = "Error creating event: " . $stmt->error;
                    header("Location: dashboard.php#createEventModal");
                    exit();
                }
            } else {
                error_log("Error preparing statement: " . $con->error);
                $_SESSION['error_message'] = "Error preparing statement: " . $con->error;
                header("Location: dashboard.php#createEventModal");
                exit();
            }
        } catch (Exception $e) {
            error_log("Exception during event creation: " . $e->getMessage());
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        error_log("Validation errors: " . implode(", ", $errors));
        $_SESSION['error_message'] = implode(", ", $errors);
        header("Location: dashboard.php#createEventModal");
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
} 