<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['acc_id'])) {
    header("Location: ARCU-Login.php");
    exit();
}

// Database connection info - adjust as needed
$host    = 'localhost';
$db      = 'db_arcu';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

// Set up DSN, options
$dsn     = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

// Connect to DB
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die('Database connection failed. Please make sure the database is set up correctly. Error: ' . $e->getMessage());
}

// Fetch user profile information
$user_id = $_SESSION['acc_id'];
$stmt = $pdo->prepare("SELECT up.*, a.role 
                       FROM user_profile up 
                       LEFT JOIN acc a ON up.user_id = a.acc_id 
                       WHERE up.user_id = ?");
$stmt->execute([$user_id]);
$user_profile = $stmt->fetch();

$successMessage = '';
$errors         = [];

// Handle Accept/Decline attendance request (must be before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_action'])) {
    $attendanceId = (int)$_POST['attendance_id'];
    $action = $_POST['attendance_action'];
    if ($attendanceId > 0 && in_array($action, ['accept', 'decline'])) {
        $newStatus = $action === 'accept' ? 'accepted' : 'declined';
        $stmt = $pdo->prepare('UPDATE attendance SET status = ? WHERE id = ?');
        $stmt->execute([$newStatus, $attendanceId]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '#attendanceSection');
        exit();
    }
}

// Delete Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $deleteId = (int) ($_POST['event_id'] ?? 0);
    if ($deleteId > 0) {
        $stmt = $pdo->prepare('DELETE FROM events WHERE id = ?');
        $stmt->execute([$deleteId]);
        // cascade delete will remove attendance linked automatically
        header('Location: ' . $_SERVER['PHP_SELF'] . '#viewEventsSection');
        exit();
    }
}

// Update Event X
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $updateId    = (int) ($_POST['event_id'] ?? 0);
    $eventname   = trim($_POST['eventName'] ?? '');
    $startdate   = $_POST['startDate'] ?? '';
    $enddate     = $_POST['endDate'] ?? '';
    $description = trim($_POST['eventDescription'] ?? '');
    $status      = $_POST['status'] ?? ''; // Assuming 'status' is a field you intend to update

    if ($eventname === '') {
        $errors[] = 'Event Name is required.';
    }
    if (!$startdate) {
        $errors[] = 'Start Date is required.';
    }
    if (!$enddate) {
        $errors[] = 'End Date is required.';
    }
    if ($startdate && $enddate && strtotime($enddate) < strtotime($startdate)) {
        $errors[] = 'End Date cannot be before Start Date.';
    }

    if (empty($errors) && $updateId > 0) {
        $stmt = $pdo->prepare('UPDATE events SET eventname = ?, startdate = ?, enddate = ?, description = ? WHERE id = ?');
        $stmt->execute([$eventname, $startdate, $enddate, $description, $updateId]);
        $successMessage = 'Event updated successfully.';
        header('Location: ' . $_SERVER['PHP_SELF'] . '#viewEventsSection');
        exit();
    }
}

// Handle Create event submission X
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $eventname   = trim($_POST['eventName'] ?? '');
    $startdate   = $_POST['startDate'] ?? '';
    $enddate     = $_POST['endDate'] ?? '';
    $description = trim($_POST['eventDescription'] ?? '');

    if ($eventname === '') {
        $errors[] = 'Event Name is required.';
    }
    if (!$startdate) {
        $errors[] = 'Start Date is required.';
    }
    if (!$enddate) {
        $errors[] = 'End Date is required.';
    }
    if ($startdate && $enddate && strtotime($enddate) < strtotime($startdate)) {
        $errors[] = 'End Date cannot be before Start Date.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO events (eventname, startdate, enddate, description) VALUES (?, ?, ?, ?)');
        $stmt->execute([$eventname, $startdate, $enddate, $description]);
        $successMessage = 'Event created successfully.';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?msg=' . urlencode($successMessage) . '#viewEventsSection');
        exit();
    }
}

// Handle Delete attendance request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_attendance'])) {
    $deleteId = (int) ($_POST['attendance_id'] ?? 0);
    if ($deleteId > 0) {
        $stmt = $pdo->prepare('DELETE FROM attendance WHERE id = ?');
        $stmt->execute([$deleteId]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '#attendanceSection');
        exit();
    }
}

// Handle Create attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_attendance'])) {
    $name     = trim($_POST['attendeeName'] ?? '');
    $date     = $_POST['attDate'] ?? '';
    $time     = $_POST['attTime'] ?? '';
    $event_id = (int) ($_POST['attEvent'] ?? 0);

    if ($name === '') {
        $errors[] = 'Attendee Name is required.';
    }
    if (!$date) {
        $errors[] = 'Attendance Date is required.';
    }
    if (!$time) {
        $errors[] = 'Attendance Time is required.';
    }
    if ($event_id <= 0) {
        $errors[] = 'Valid Event is required.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO attendance (name, attendance_date, time, event_id, status) VALUES (?, ?, ?, ?, ?)');
            $result = $stmt->execute([$name, $date, $time, $event_id, 'pending']);
            if ($result) {
                $successMessage = 'Attendance recorded successfully.';
                header('Location: ' . $_SERVER['PHP_SELF'] . '?msg=' . urlencode($successMessage) . '#attendanceSection');
                exit();
            } else {
                $errors[] = 'Failed to record attendance. Please try again.';
            }
        } catch (\PDOException $e) {
            error_log("Error recording attendance: " . $e->getMessage());
            $errors[] = 'Error recording attendance: ' . $e->getMessage();
        }
    }
}

// Handle Update attendance request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    $updateId = (int) ($_POST['attendance_id'] ?? 0);
    $name     = trim($_POST['attendeeName'] ?? '');
    $date     = $_POST['attDate'] ?? '';
    $time     = $_POST['attTime'] ?? '';
    $event_id = (int) ($_POST['attEvent'] ?? 0);

    if ($name === '') {
        $errors[] = 'Attendee Name is required.';
    }
    if (!$date) {
        $errors[] = 'Attendance Date is required.';
    }
    if (!$time) {
        $errors[] = 'Attendance Time is required.';
    }
    if ($event_id <= 0) {
        $errors[] = 'Valid Event is required.';
    }

    if (empty($errors) && $updateId > 0) {
        try {
            $stmt = $pdo->prepare('UPDATE attendance SET name = ?, attendance_date = ?, time = ?, event_id = ? WHERE id = ?');
            $stmt->execute([$name, $date, $time, $event_id, $updateId]);
            $successMessage = 'Attendance updated successfully.';
            header('Location: ' . $_SERVER['PHP_SELF'] . '#attendanceSection');
            exit();
        } catch (\PDOException $e) {
            $errors[] = 'Error updating attendance: ' . $e->getMessage();
        }
    }
}

// Handle Create Club form submission
$clubSuccess = '';
$clubError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clubName'], $_POST['clubDescription'])) {
    $clubName = trim($_POST['clubName']);
    $clubDescription = trim($_POST['clubDescription']);
    if ($clubName) {
        try {
            $stmt = $pdo->prepare('INSERT INTO clubs (club_name, description, created_by) VALUES (?, ?, ?)');
            $stmt->execute([$clubName, $clubDescription, $_SESSION['acc_id']]);
            $clubSuccess = 'Club created successfully!';
        } catch (PDOException $e) {
            $clubError = 'Error creating club: ' . $e->getMessage();
        }
    } else {
        $clubError = 'Club name is required.';
    }
}

// Handle club deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_club_id'])) {
    $deleteClubId = (int)$_POST['delete_club_id'];
    if ($deleteClubId > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM clubs WHERE club_id = ?');
            $stmt->execute([$deleteClubId]);
            $clubSuccess = 'Club deleted successfully!';
        } catch (PDOException $e) {
            $clubError = 'Error deleting club: ' . $e->getMessage();
        }
    }
}

// Handle club member deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_member_id'])) {
    $deleteMemberId = (int)$_POST['delete_member_id'];
    if ($deleteMemberId > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM club_members WHERE id = ?');
            $stmt->execute([$deleteMemberId]);
            $clubSuccess = 'Club member deleted successfully!';
        } catch (PDOException $e) {
            $clubError = 'Error deleting club member: ' . $e->getMessage();
        }
    }
}

// Handle club member status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_member_status'])) {
    $memberId = (int)$_POST['member_id'];
    $status = $_POST['status'];
    error_log('DEBUG: memberId=' . $memberId . ', status=' . $status);
    if ($memberId > 0 && in_array($status, ['accepted', 'declined'])) {
        try {
            $stmt = $pdo->prepare('UPDATE club_members SET status = ? WHERE id = ?');
            $stmt->execute([$status, $memberId]);
            $clubSuccess = 'Club member status updated successfully!';
            error_log('DEBUG: Update successful');
        } catch (PDOException $e) {
            $clubError = 'Error updating club member status: ' . $e->getMessage();
            error_log('DEBUG: Update failed: ' . $e->getMessage());
        }
    } else {
        error_log('DEBUG: Invalid memberId or status');
    }
}

// Handle image upload
$gallerySuccess = '';
$galleryError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image'])) {
    if (isset($_FILES['gallery_image']) && $_FILES['gallery_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/gallery/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileTmp = $_FILES['gallery_image']['tmp_name'];
        $fileName = basename($_FILES['gallery_image']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($fileExt, $allowed)) {
            $newName = uniqid('img_', true) . '.' . $fileExt;
            $dest = $uploadDir . $newName;
            if (move_uploaded_file($fileTmp, $dest)) {
                $caption = trim($_POST['caption'] ?? '');
                $stmt = $pdo->prepare('INSERT INTO gallery (image_name, image_path, description) VALUES (?, ?, ?)');
                $stmt->execute([$fileName, $newName, $caption]);
                $gallerySuccess = 'Image uploaded successfully!';
            } else {
                $galleryError = 'Failed to move uploaded file.';
            }
        } else {
            $galleryError = 'Invalid file type. Only JPG, JPEG, PNG, GIF allowed.';
        }
    } else {
        $galleryError = 'No file uploaded or upload error.';
    }
}
// Handle image delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image_id'])) {
    $deleteId = (int)$_POST['delete_image_id'];
    $stmt = $pdo->prepare('SELECT image_path FROM gallery WHERE image_id = ?');
    $stmt->execute([$deleteId]);
    $img = $stmt->fetch();
    if ($img) {
        $filePath = __DIR__ . '/uploads/gallery/' . $img['image_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $stmt = $pdo->prepare('DELETE FROM gallery WHERE image_id = ?');
        $stmt->execute([$deleteId]);
        $gallerySuccess = 'Image deleted successfully!';
    }
}
// Fetch all gallery images
$galleryImages = [];
try {
    $stmt = $pdo->query('SELECT * FROM gallery ORDER BY upload_date DESC');
    $galleryImages = $stmt->fetchAll();
} catch (PDOException $e) {
    $galleryImages = [];
}

// Fetch events for display
try {
    error_log("Attempting to fetch events from database...");
    // First check if the table exists and get its structure
    $checkTable = $pdo->query("SHOW COLUMNS FROM events");
    $columns = $checkTable->fetchAll(PDO::FETCH_COLUMN);
    error_log("Available columns: " . print_r($columns, true));
    
    // Now fetch the events without ordering first to see what we get
    $stmt = $pdo->query('SELECT * FROM events');
    $events = $stmt->fetchAll();
    error_log("Number of events fetched: " . count($events));
    if (empty($events)) {
        error_log("No events found in the database");
    } else {
        error_log("Events found: " . print_r($events, true));
    }
} catch (\PDOException $e) {
    error_log("Error fetching events: " . $e->getMessage());
    exit('Error fetching events: ' . htmlspecialchars($e->getMessage()));
}

// Fetch attendance for display with event names using JOIN
try {
    $stmt = $pdo->query('SELECT a.*, e.eventname FROM attendance a LEFT JOIN events e ON a.event_id = e.id ORDER BY a.attendance_date DESC, a.time DESC');
    $attendances = $stmt->fetchAll();
    if (empty($attendances)) {
        error_log("No attendance records found");
    }
} catch (\PDOException $e) {
    error_log("Error in attendance query: " . $e->getMessage());
    $attendances = [];
}

// Calculate event statistics
$totalEvents = count($events);
$now         = new DateTime();
$weekLater   = (new DateTime())->modify('+7 days');
$upcomingCount = 0;
foreach ($events as $event) {
    $eventStart = new DateTime($event['startdate']);
    if ($eventStart >= $now && $eventStart <= $weekLater) {
        $upcomingCount++;
    }
}

// Check for success message from redirect
if (isset($_GET['msg'])) {
    $successMessage = htmlspecialchars($_GET['msg']);
}

// Check edit event or attendance request
$editEvent = null;
if (isset($_GET['edit_id'])) {
    $editId = (int) $_GET['edit_id'];
    $stmt   = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$editId]);
    $editEvent = $stmt->fetch();
}

$editAttendance = null;
if (isset($_GET['edit_att_id'])) {
    $editAttId = (int) $_GET['edit_att_id'];
    $stmt      = $pdo->prepare('SELECT * FROM attendance WHERE id = ?');
    $stmt->execute([$editAttId]);
    $editAttendance = $stmt->fetch();
}

// Fetch club members for admin view
$clubMembers = [];
try {
    $stmt = $pdo->query('SELECT * FROM club_members ORDER BY id DESC');
    $clubMembers = $stmt->fetchAll();
} catch (PDOException $e) {
    $clubMembers = [];
}

// Fetch all clubs created by this admin for view
$allClubs = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM clubs WHERE created_by = ? ORDER BY club_id DESC');
    $stmt->execute([$_SESSION['acc_id']]);
    $allClubs = $stmt->fetchAll();
} catch (PDOException $e) {
    $allClubs = [];
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>USG-Officer_Dashboard</title>
        <link rel="icon" href="../img/ARCULOGO.png" />

        <link rel="stylesheet" href="main.css" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
         <!-- FullCalendar CSS -->
         <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet" />
        <style>
            body {
                background-color: #f8f9fa;
                overflow-x: hidden;
            }
            .sidebar {
                min-height: calc(100vh - 56px);
                background-color: #343a40;
                color: white;
                transition: all 0.3s;
                position: relative;
            }
            .sidebar.collapsed {
                margin-left: -100%;
            }
            .sidebar-expand-btn {
                position: absolute;
                right: -40px;
                top: 10px;
                background-color: #343a40;
                color: white;
                border: none;
                border-radius: 0 4px 4px 0;
                padding: 8px 12px;
                display: none;
                z-index: 1030;
            }
            .sidebar.collapsed .sidebar-expand-btn {
                display: block;
                .sidebar .bi {
    color: #fff !important;
    opacity: 1 !important;
}
            }
            .main-content {
                transition: all 0.3s;
                padding: 20px;
            }
            .main-content.expanded {
                margin-left: 0;
                width: 100%;
            }
            .nav-link {
                color: rgba(255, 255, 255, 0.75);
            }
            .nav-link:hover {
                color: white;
            }
            .nav-link.active {
                color: white;
                background-color: rgba(255, 255, 255, 0.1);
            }
            #sidebarToggle {
                cursor: pointer;
            }
            .section-container {
                padding: 20px;
            }
            .status-Scheduled {
                background-color: #17a2b8 !important;
            }
            .status-Ongoing {
                background-color: #28a745 !important;
            }
            .status-Completed {
                background-color: #6c757d !important;
            }
            .status-Cancelled {
                background-color: #dc3545 !important;
            }
            .admin-logo {
                width: 40px;
                height: 40px;
                background: linear-gradient(45deg, #481919, #232526);
                border-radius: 50%;
                display: flex;
                justify-content: center;
                align-items: center;
                color: white;
                font-weight: bold;
                font-size: 1.2em;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            .dropdown-menu {
                min-width: 200px;
            }
            .dropdown-item {
                padding: 0.5rem 1rem;
            }
            .dropdown-item:hover {
                background-color: rgba(255,255,255,0.1);
            }
            /* Fix form inline buttons spacing */
            form.inline-form {
                display: inline-block;
                margin: 0;
            }
            .transition-arrow {
                transition: transform 0.3s;
            }
            .collapse.show + .transition-arrow,
            .sidebar .bi {
                color: #fff !important;
                opacity: 1 !important;
            }
        </style>
    </head>
    <body>

        <header class="navbar navbar-expand-lg navbar-dark bg-dark" style="background: linear-gradient(140deg, rgb(72, 25, 25) 25%, rgba(10, 10, 10, 1) 60%, rgba(187, 201, 189, 1) 80%);">
            <div class="container-fluid">
                <button id="sidebarToggle" class="btn btn-dark me-2" aria-label="Toggle Sidebar">
                    <i class="bi bi-list"></i>
                </button>

                <div class="d-flex align-items-center">
                    <img src="../img/ARCULOGO.png" alt="Company Logo" height="40" class="me-2" />
                    <a class="navbar-brand" href="#">ARTS AND CULTURE</a>
                </div>

                <div class="dropdown">
                    <button class="d-flex align-items-center text-white dropdown-toggle bg-transparent border-0" id="adminDropdown" data-bs-toggle="dropdown" aria-expanded="false" type="button" style="box-shadow:none;">
                        <div class="d-flex align-items-center">
                            <span class="me-2 d-none d-md-inline">
                                <span class="fw-bold"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></span>
                                <small class="d-block text-muted" style="font-size: 0.8em;"><?= htmlspecialchars($_SESSION['role'] ?? 'Administrator') ?></small>
                            </span>
                            <div class="admin-logo" aria-label="Admin Panel Logo">
                                <?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?>
                            </div>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="adminDropdown">
                        <li class="px-3 py-2">
                            <div class="text-white">
                                <div class="fw-bold"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($user_profile['email'] ?? '') ?></small>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider" /></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Manage Account</a></li>
                        <li><hr class="dropdown-divider" /></li>
                        <li><a class="dropdown-item" href="ARCU-login.php"><i class="bi bi-box-arrow-right me-2"></i>Log Out</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <div class="container-fluid">
            <div class="row">
                <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block sidebar" aria-label="Sidebar navigation" style="background: linear-gradient(135deg, #481919 0%, #232526 100%);">
                    <button id="sidebarExpandBtn" class="sidebar-expand-btn" aria-label="Expand Sidebar">
                        <i class="bi bi-chevron-right"></i>
                    </button>

                    <div class="position-sticky pt-3">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-section="dashboardSection" id="navDashboard">
                                    <i class="bi bi-house me-2"></i>Home
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#eventsSubMenu" role="button" aria-expanded="false" aria-controls="eventsSubMenu">
                                    <span><i class="bi bi-calendar-event me-2"></i>Events</span>
                                    <i class="bi bi-chevron-down ms-2 transition-arrow" id="eventsArrow"></i>
                                </a>
                                <div class="collapse" id="eventsSubMenu">
                                    <ul class="nav flex-column ps-3">
                                        <li class="nav-item">
                                            <a class="nav-link" href="#" data-section="createEventSection" id="navCreateEvent">
                                                <i class="bi bi-plus-circle me-2"></i>Create Event
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" href="#" data-section="viewEventsSection" id="navViewEvents">
                                                <i class="bi bi-eye me-2"></i>View Events
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-section="attendanceSection" id="navAttendance">
                                    <i class="bi bi-people me-2"></i>Attendance
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-section="gallerySection" id="navGallery">
                                    <i class="bi bi-images me-2"></i>Gallery
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-section="clubSection" id="navClub">
                                    <i class="bi bi-people me-2"></i>Clubs
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-section="generateReportSection" id="navGenerateReport">
                                    <i class="bi bi-file-earmark-bar-graph me-2"></i>Generate Report
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>

                <main id="content" class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content" role="main">
                    <?php if ($successMessage): ?>
                        <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                            <?= $successMessage ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <section id="dashboardSection" class="section-container d-none">
                        <div class="row g-4 mb-4">
                            <div class="col-md-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-header bg-secondary text-white fw-bold text-center">
                                        Events
                                    </div>
                                    <div class="card-body text-center">
                                        <h2 class="mb-0" id="totalEventsCount">
                                            <?= $totalEvents ?>
                                        </h2>
                                        <p class="card-text text-muted small mt-2" id="upcomingEventsCount">
                                            <?= $upcomingCount ?> upcoming this week
                                        </p>
                                        <a href="#" class="btn btn-primary w-100" data-section="viewEventsSection" id="btnViewEvents">View Events</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-header bg-secondary text-white fw-bold text-center">
                                        Attendance
                                    </div>
                                    <div class="card-body text-center">
                                        <h2 class="mb-0">
                                            <?= count($attendances) ?>
                                        </h2>
                                        <p class="card-text text-muted small mt-2">Total attendance records</p>
                                        <a href="#" class="btn btn-primary w-100" data-section="attendanceSection" id="btnViewAttendance">View Attendance</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-header bg-secondary text-white fw-bold text-center">
                                        Clubs
                                    </div>
                                    <div class="card-body text-center">
                                        <h2 class="mb-0">
                                            <?= isset($allClubs) ? count($allClubs) : 0 ?>
                                        </h2>
                                        <p class="card-text text-muted small mt-2">Total clubs created</p>
                                        <a href="#" class="btn btn-primary w-100" data-section="clubSection" id="btnViewClubs">View Clubs</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                          <!-- Calendar Card (full width below) -->
                        <div class="row g-4 mt-1">
                        <div class="col-12">
                            <div class="card" aria-label="Calendar">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Calendar</h5>
                                    <div id="calendar"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </section>


                    <section id="createEventSection" class="section-container d-none" aria-label="Create Event Section">
                        <div class="row justify-content-center">
                            <div class="col-12">
                                <div class="card mt-4 mb-4">
                                    <div class="card-header bg-secondary text-white">
                                        <h5 class="card-title mb-0">
                                            <?= $editEvent ? 'Edit Event' : 'Create New Event' ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="createEventForm" method="post" novalidate>
                                            <input type="hidden" name="<?= $editEvent ? 'update_event' : 'create_event' ?>" value="1" />
                                            <?php if ($editEvent): ?>
                                                <input type="hidden" name="event_id" value="<?= $editEvent['id'] ?>" />
                                            <?php endif; ?>
                                            <div class="mb-3">
                                                <label for="eventName" class="form-label">Event Name*</label>
                                                <input type="text" class="form-control" id="eventName" name="eventName" required value="<?= htmlspecialchars($_POST['eventName'] ?? $editEvent['eventname'] ?? '') ?>" />
                                            </div>
                                            <div class="mb-3">
                                                <label for="startDate" class="form-label">Start Date*</label>
                                                <input type="date" class="form-control" id="startDate" name="startDate" required value="<?= htmlspecialchars($_POST['startDate'] ?? ($editEvent ? date('Y-m-d', strtotime($editEvent['startdate'])) : '')) ?>" />
                                            </div>
                                            <div class="mb-3">
                                                <label for="endDate" class="form-label">End Date*</label>
                                                <input type="date" class="form-control" id="endDate" name="endDate" required value="<?= htmlspecialchars($_POST['endDate'] ?? ($editEvent ? date('Y-m-d', strtotime($editEvent['enddate'])) : '')) ?>" />
                                            </div>

                                            <div class="mb-3">
                                                <label for="eventDescription" class="form-label">Description</label>
                                                <textarea class="form-control" id="eventDescription" name="eventDescription" rows="4"><?= htmlspecialchars($_POST['eventDescription'] ?? $editEvent['description'] ?? '') ?></textarea>
                                            </div>
                                            <div class="text-end">
                                                <button type="button" class="btn btn-danger me-2" id="cancelCreateEventBtn">
                                                    Cancel
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <?= $editEvent ? 'Update Event' : 'Create Event' ?>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="viewEventsSection" class="section-container d-none" aria-label="View Events Section">
                        <div class="row">
                            <div class="col-12">
                                <div class="card mt-4 mb-4">
                                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">All Events</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="eventsTable">
                                                <thead>
                                                    <tr>
                                                        <th scope="col">#</th>
                                                        <th scope="col">Event Name</th>
                                                        <th scope="col">Start Date</th>
                                                        <th scope="col">End Date</th>
                                                        <th scope="col">Description</th>
                                                        <th scope="col" style="min-width: 110px">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($events)): ?>
                                                        <?php foreach ($events as $index => $event): ?>
                                                            <tr>
                                                                <th scope="row">
                                                                    <?= $index + 1 ?>
                                                                </th>
                                                                <td>
                                                                    <?= htmlspecialchars($event['eventname']) ?>
                                                                </td>
                                                                <td>
                                                                    <?= (new DateTime($event['startdate']))->format('M d, Y ') ?>
                                                                </td>
                                                                <td>
                                                                    <?= (new DateTime($event['enddate']))->format('M d, Y ') ?>
                                                                </td>
                                                                <td>
                                                                    <?= nl2br(htmlspecialchars($event['description'])) ?>
                                                                </td>
                                                                <td>
                                                                    <a
                                                                        href="?edit_id=<?= $event['id'] ?>#createEventSection"
                                                                        class="btn btn-sm btn-outline-secondary"
                                                                        aria-label="Edit Event <?= htmlspecialchars($event['eventname']) ?>"
                                                                        ><i class="bi bi-pencil"></i
                                                                    ></a>
                                                                    <form
                                                                        method="post"
                                                                        class="inline-form"
                                                                        onsubmit="return confirm('Are you sure you want to delete this event? This will also delete related attendance records.');"
                                                                        aria-label="Delete Event <?= htmlspecialchars($event['eventname']) ?>"
                                                                    >
                                                                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>" />
                                                                        <button type="submit" name="delete_event" class="btn btn-sm btn-outline-danger" title="Delete">
                                                                            <i class="bi bi-trash"></i>
                                                                        </button>
                                                                    </form>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="7" class="text-center">No events found.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="attendanceSection" class="section-container" aria-label="Attendance Section">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Attendance Records</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="attendanceTable">
                                                <thead>
                                                    <tr>
                                                        <th scope="col">#</th>
                                                        <th scope="col">Attendee Name</th>
                                                        <th scope="col">Date</th>
                                                        <th scope="col">Time</th>
                                                        <th scope="col">Event</th>
                                                        <th scope="col">Status</th>
                                                        <th scope="col" style="min-width: 110px">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($attendances)): ?>
                                                        <?php foreach ($attendances as $index => $attendance): ?>
                                                            <tr>
                                                                <th scope="row">
                                                                    <?= $index + 1 ?>
                                                                </th>
                                                                <td>
                                                                    <?= htmlspecialchars($attendance['name']) ?>
                                                                </td>
                                                                <td>
                                                                    <?= htmlspecialchars($attendance['attendance_date']) ?>
                                                                </td>
                                                                <td>
                                                                    <?= htmlspecialchars(date('H:i', strtotime($attendance['time']))) ?>
                                                                </td>
                                                                <td>
                                                                    <?= htmlspecialchars($attendance['eventname']) ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge 
                                                                        <?= $attendance['status'] === 'accepted' ? 'bg-success' : 
                                                                            ($attendance['status'] === 'declined' ? 'bg-danger' : 'bg-warning') ?>">
                                                                        <?= $attendance['status'] === 'accepted' ? 'Accepted' : 
                                                                            ($attendance['status'] === 'declined' ? 'Declined' : 'Pending') ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <form method="post" style="display:inline;">
                                                                        <input type="hidden" name="attendance_id" value="<?= $attendance['id'] ?>">
                                                                        <input type="hidden" name="attendance_action" value="accept">
                                                                        <button type="submit" class="btn btn-sm btn-success">Accept</button>
                                                                    </form>
                                                                    <form method="post" style="display:inline;">
                                                                        <input type="hidden" name="attendance_id" value="<?= $attendance['id'] ?>">
                                                                        <input type="hidden" name="attendance_action" value="decline">
                                                                        <button type="submit" class="btn btn-sm btn-danger">Decline</button>
                                                                    </form>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="7" class="text-center">No attendance records found.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="clubSection" class="section-container d-none" aria-label="Club Section">
                        <div class="row">
                            <div class="col-12">
                                <div class="card mt-4 mb-4">
                                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Club Management</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <button class="btn btn-primary me-2" id="showCreateClub">Create Club</button>
                                            <button class="btn btn-outline-secondary" id="showViewClub">Club joiner</button>
                                        </div>
                                        <div id="createClubSection" class="d-none">
                                            <?php if ($clubSuccess): ?>
                                                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                                                    <?= $clubSuccess ?>
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($clubError): ?>
                                                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                                                    <?= $clubError ?>
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                </div>
                                            <?php endif; ?>
                                            <h6>Create Club</h6>
                                            <form id="createClubForm" method="post">
                                                <div class="mb-3">
                                                    <label for="clubName" class="form-label">Club Name*</label>
                                                    <input type="text" class="form-control" id="clubName" name="clubName" required />
                                                </div>
                                                <div class="mb-3">
                                                    <label for="clubDescription" class="form-label">Description</label>
                                                    <textarea class="form-control" id="clubDescription" name="clubDescription" rows="3"></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-success">Create Club</button>
                                            </form>
                                        </div>
                                        <div id="viewClubSection" class="d-none">
                                            <h6>Club joine</h6>
                                            <div id="clubList">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Name</th>
                                                            <th>Student ID</th>
                                                            <th>Email</th>
                                                            <th>Phone</th>
                                                            <th>Interests</th>
                                                            <th>Why Join</th>
                                                            <th>Status</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($clubMembers)): ?>
                                                            <?php foreach ($clubMembers as $member): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($member['student_name']) ?></td>
                                                                    <td><?= htmlspecialchars($member['student_id']) ?></td>
                                                                    <td><?= htmlspecialchars($member['email']) ?></td>
                                                                    <td><?= htmlspecialchars($member['phone']) ?></td>
                                                                    <td><?= htmlspecialchars($member['interests']) ?></td>
                                                                    <td><?= htmlspecialchars($member['why_join']) ?></td>
                                                                    <td>
                                                                        <span class="badge <?= 
                                                                            $member['status'] === 'accepted' ? 'bg-success' : 
                                                                            ($member['status'] === 'declined' ? 'bg-danger' : 'bg-warning') 
                                                                        ?>">
                                                                            <?= ucfirst($member['status'] ?? 'pending') ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <?php if (!isset($member['status']) || $member['status'] === 'pending'): ?>
                                                                            <form method="post" style="display:inline;">
                                                                                <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                                                                <input type="hidden" name="update_member_status" value="1">
                                                                                <input type="hidden" name="status" value="accepted">
                                                                                <button type="submit" class="btn btn-sm btn-success">Accept</button>
                                                                            </form>
                                                                            <form method="post" style="display:inline;">
                                                                                <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                                                                <input type="hidden" name="update_member_status" value="1">
                                                                                <input type="hidden" name="status" value="declined">
                                                                                <button type="submit" class="btn btn-sm btn-danger">Decline</button>
                                                                            </form>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr><td colspan="8" class="text-center">No club members found.</td></tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div id="adminClubsSection" class="d-none">
                                            <h6>Clubs Created by Admin</h6>
                                            <div id="adminClubList">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Club Name</th>
                                                            <th>Description</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($allClubs)): ?>
                                                            <?php foreach ($allClubs as $club): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($club['club_name']) ?></td>
                                                                    <td><?= htmlspecialchars($club['description']) ?></td>
                                                                    <td>
                                                                        <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this club?');">
                                                                            <input type="hidden" name="delete_club_id" value="<?= $club['club_id'] ?>">
                                                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                                        </form>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr><td colspan="3" class="text-center">No clubs found.</td></tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="gallerySection" class="section-container d-none" aria-label="Gallery Section">
                        <div class="row">
                            <div class="col-12">
                                <div class="card mt-4 mb-4">
                                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Gallery</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($gallerySuccess): ?>
                                            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                                                <?= $gallerySuccess ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($galleryError): ?>
                                            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                                                <?= $galleryError ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>
                                        <?php endif; ?>
                                        <form method="post" enctype="multipart/form-data" class="mb-4">
                                            <div class="mb-3">
                                                <label for="gallery_image" class="form-label">Upload Image*</label>
                                                <input type="file" class="form-control" id="gallery_image" name="gallery_image" accept="image/*" required />
                                            </div>
                                            <div class="mb-3">
                                                <label for="caption" class="form-label">Caption</label>
                                                <input type="text" class="form-control" id="caption" name="caption" maxlength="255" />
                                            </div>
                                            <button type="submit" name="upload_image" class="btn btn-primary">Upload</button>
                                        </form>
                                        <div class="row">
                                            <?php if (!empty($galleryImages)): ?>
                                                <?php foreach ($galleryImages as $img): ?>
                                                    <div class="col-md-3 mb-4">
                                                        <div class="card h-100">
                                                            <img src="uploads/gallery/<?= htmlspecialchars($img['image_path']) ?>" class="card-img-top" alt="Gallery Image" style="object-fit:cover; height:200px;" />
                                                            <div class="card-body">
                                                                <p class="card-text small text-muted"><?= htmlspecialchars($img['description']) ?></p>
                                                                <form method="post" onsubmit="return confirm('Delete this image?');">
                                                                    <input type="hidden" name="delete_image_id" value="<?= $img['image_id'] ?>" />
                                                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="col-12 text-center text-muted">No images in the gallery.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="generateReportSection" class="section-container d-none" aria-label="Generate Report Section">
                        <div class="row">
                            <div class="col-12">
                                <div class="card mt-4 mb-4">
                                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">Generate Reports</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>Download reports for Events, Attendance, and Clubs as Excel (CSV) files:</p>
                                        <a href="download_events_report.php" class="btn btn-success me-2">Download Events Report</a>
                                        <a href="download_attendance_report.php" class="btn btn-success me-2">Download Attendance Report</a>
                                        <a href="download_clubs_report.php" class="btn btn-success">Download Clubs Report</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="accountSection" class="section-container d-none" aria-label="Account Management Section">
                        <div class="row justify-content-center">
                            <div class="col-12 col-md-8">
                                <div class="card mt-4 mb-4">
                                    <div class="card-header bg-secondary text-white">
                                        <h5 class="card-title mb-0">Account Management</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $acc_id = $_SESSION['acc_id'];
                                        $stmt = $pdo->prepare("SELECT a.*, up.* FROM acc a LEFT JOIN user_profile up ON a.acc_id = up.user_id WHERE a.acc_id = ?");
                                        $stmt->execute([$acc_id]);
                                        $account = $stmt->fetch();

                                        $successMessage = '';
                                        $errorMessage = '';

                                        // Handle form submission
                                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account'])) {
                                            $full_name = $_POST['full_name'] ?? '';
                                            $email = $_POST['email'] ?? '';
                                            $phone = $_POST['phone'] ?? '';
                                            try {
                                                $pdo->beginTransaction();
                                                $stmtCheck = $pdo->prepare("SELECT * FROM user_profile WHERE user_id = ?");
                                                $stmtCheck->execute([$acc_id]);
                                                $profileExists = $stmtCheck->fetch();
                                                if ($profileExists) {
                                                    $stmt = $pdo->prepare("UPDATE user_profile SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
                                                    $stmt->execute([$full_name, $email, $phone, $acc_id]);
                                                } else {
                                                    $stmt = $pdo->prepare("INSERT INTO user_profile (user_id, full_name, email, phone) VALUES (?, ?, ?, ?)");
                                                    $stmt->execute([$acc_id, $full_name, $email, $phone]);
                                                }
                                                $pdo->commit();
                                                $_SESSION['full_name'] = $full_name;
                                                $successMessage = "Account updated successfully!";
                                            } catch (Exception $e) {
                                                $pdo->rollBack();
                                                $errorMessage = $e->getMessage();
                                            }
                                        }
                                        ?>

                                        <?php if ($successMessage): ?>
                                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                                <?= htmlspecialchars($successMessage) ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($errorMessage): ?>
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                <?= htmlspecialchars($errorMessage) ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>
                                        <?php endif; ?>

                                        <form method="post" class="needs-validation" novalidate>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="acc_id" class="form-label">Student ID</label>
                                                    <input type="text" class="form-control" id="acc_id" value="<?= htmlspecialchars($account['acc_id']) ?>" readonly>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="full_name" class="form-label">Full Name</label>
                                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($account['full_name'] ?? '') ?>" required>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="email" class="form-label">Email</label>
                                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($account['email'] ?? '') ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="phone" class="form-label">Phone</label>
                                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($account['phone'] ?? '') ?>">
                                                </div>
                                            </div>
                                            <button type="submit" name="update_account" class="btn" style="background:#800080; color:white; font-weight:500;">Update Account</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <!-- FullCalendar JS (LOCAL) -->
        <script src="vendor/fullcalendar/main.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const sidebarToggle = document.getElementById('sidebarToggle');
                const sidebarExpandBtn = document.getElementById('sidebarExpandBtn');
                const sidebar = document.getElementById('sidebar');
                const content = document.getElementById('content');

                const navLinks = document.querySelectorAll('#sidebar .nav-link[data-section]');
                const sections = document.querySelectorAll('main section.section-container');

                // Helper function to hide all sections
                function hideAllSections() {
                    sections.forEach(section => {
                        section.classList.add('d-none');
                    });
                }

                // Toggle sidebar
                function toggleSidebar() {
                    sidebar.classList.toggle('collapsed');
                    content.classList.toggle('expanded');
                    sidebarExpandBtn.style.display = sidebar.classList.contains('collapsed') ? 'block' : 'none';
                }

                // Check window width for initial sidebar state
                function checkWidth() {
                    if (window.innerWidth < 768) {
                        sidebar.classList.add('collapsed');
                        content.classList.add('expanded');
                        sidebarExpandBtn.style.display = 'block';
                    } else {
                        sidebar.classList.remove('collapsed');
                        content.classList.remove('expanded');
                        sidebarExpandBtn.style.display = 'none';
                    }
                }

                // Show section by id and update active links
                function showSection(id) {
                    sections.forEach((section) => section.classList.add('d-none'));
                    const target = document.getElementById(id);
                    if (target) {
                        target.classList.remove('d-none');
                    }
                    navLinks.forEach((link) => {
                        if (link.getAttribute('data-section') === id) {
                            link.classList.add('active');
                        } else {
                            link.classList.remove('active');
                        }
                    });
                }

                // Initial check
                checkWidth();

                // Event listeners
                sidebarToggle.addEventListener('click', toggleSidebar);
                sidebarExpandBtn.addEventListener('click', toggleSidebar);
                window.addEventListener('resize', checkWidth);

                navLinks.forEach((link) => {
                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        showSection(this.getAttribute('data-section'));
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                });

                // Account Management Section
                const accountLink = document.querySelector('.dropdown-item[href="#"]');
                if (accountLink) {
                    accountLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        hideAllSections();
                        document.getElementById('accountSection').classList.remove('d-none');
                    });
                }

                // Buttons inside event dropdown submenu
                document.querySelectorAll('#eventsSubMenu .nav-link').forEach((link) => {
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        showSection(link.getAttribute('data-section'));
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                });

                // Special buttons for toggling between create/view events sections
                const cancelCreateEventBtn = document.getElementById('cancelCreateEventBtn');
                const recordNewAttendanceBtn = document.getElementById('recordNewAttendanceBtn');
                const cancelAttendanceBtn = document.getElementById('cancelAttendanceBtn');

                if (cancelCreateEventBtn) {
                    cancelCreateEventBtn.addEventListener('click', function () {
                        showSection('viewEventsSection');
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                }
                if (recordNewAttendanceBtn) {
                    recordNewAttendanceBtn.addEventListener('click', function () {
                        showSection('attendanceSection');
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                }
                if (cancelAttendanceBtn) {
                    cancelAttendanceBtn.addEventListener('click', function () {
                        showSection('attendanceSection');
                        // If no edit, reset form
                        if (!<?= $editAttendance ? 'true' : 'false' ?>) {
                            document.getElementById('attendanceForm').reset();
                        }
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                }

                // On page load, show based on URL params or default to Attendance
                function showInitialSection() {
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.has('edit_id')) {
                        showSection('createEventSection');
                    } else if (urlParams.has('edit_att_id')) {
                        showSection('attendanceSection');
                    } else {
                        const hash = window.location.hash.replace('#', '');
                        if (hash && document.getElementById(hash)) {
                            showSection(hash);
                        } else {
                            showSection('attendanceSection');
                        }
                    }
                }
                showInitialSection();

                const navClub = document.getElementById('navClub');
                const clubSection = document.getElementById('clubSection');
                const showCreateClub = document.getElementById('showCreateClub');
                const showViewClub = document.getElementById('showViewClub');
                const createClubSection = document.getElementById('createClubSection');
                const viewClubSection = document.getElementById('viewClubSection');

                if (navClub) {
                    navClub.addEventListener('click', function(e) {
                        e.preventDefault();
                        sections.forEach((section) => section.classList.add('d-none'));
                        clubSection.classList.remove('d-none');
                        createClubSection.classList.add('d-none');
                        viewClubSection.classList.add('d-none');
                    });
                }
                if (showCreateClub) {
                    showCreateClub.addEventListener('click', function() {
                        createClubSection.classList.remove('d-none');
                        viewClubSection.classList.add('d-none');
                    });
                }
                if (showViewClub) {
                    showViewClub.addEventListener('click', function() {
                        createClubSection.classList.add('d-none');
                        viewClubSection.classList.remove('d-none');
                    });
                }

                const showAdminClubs = document.createElement('button');
                showAdminClubs.className = 'btn btn-outline-info ms-2';
                showAdminClubs.id = 'showAdminClubs';
                showAdminClubs.textContent = 'View Created Clubs';
                document.getElementById('showViewClub').after(showAdminClubs);
                const adminClubsSection = document.getElementById('adminClubsSection');
                showAdminClubs.addEventListener('click', function() {
                    createClubSection.classList.add('d-none');
                    viewClubSection.classList.add('d-none');
                    adminClubsSection.classList.remove('d-none');
                });
                // Hide admin clubs section by default when switching
                showCreateClub.addEventListener('click', function() {
                    adminClubsSection.classList.add('d-none');
                });
                showViewClub.addEventListener('click', function() {
                    adminClubsSection.classList.add('d-none');
                });

                const navGenerateReport = document.querySelector('#sidebar .nav-link:has(i.bi-file-earmark-bar-graph)');
                if (navGenerateReport) {
                    navGenerateReport.setAttribute('data-section', 'generateReportSection');
                    navGenerateReport.addEventListener('click', function(e) {
                        e.preventDefault();
                        sections.forEach((section) => section.classList.add('d-none'));
                        document.getElementById('generateReportSection').classList.remove('d-none');
                        navLinks.forEach(l => l.classList.remove('active'));
                        navGenerateReport.classList.add('active');
                    });
                }

                const navGallery = document.getElementById('navGallery');
                if (navGallery) {
                    navGallery.addEventListener('click', function(e) {
                        e.preventDefault();
                        sections.forEach((section) => section.classList.add('d-none'));
                        document.getElementById('gallerySection').classList.remove('d-none');
                        navLinks.forEach(l => l.classList.remove('active'));
                        navGallery.classList.add('active');
                    });
                }

                // Profile Image Upload Handling
                const profileImageInput = document.getElementById('profileImageInput');
                const profileImage = document.getElementById('profileImage');
                const adminLogoImg = document.querySelector('.admin-logo img');

                if (profileImageInput && profileImage) {
                    profileImageInput.addEventListener('change', function(e) {
                        const file = e.target.files[0];
                        if (file) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                profileImage.src = e.target.result;
                                if (adminLogoImg) {
                                    adminLogoImg.src = e.target.result;
                                }
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }

                const btnViewEvents = document.getElementById('btnViewEvents');
                const btnViewAttendance = document.getElementById('btnViewAttendance');
                const btnViewClubs = document.getElementById('btnViewClubs');

                if (btnViewEvents) {
                    btnViewEvents.addEventListener('click', function(e) {
                        e.preventDefault();
                        document.getElementById('navViewEvents').click();
                    });
                }
                if (btnViewAttendance) {
                    btnViewAttendance.addEventListener('click', function(e) {
                        e.preventDefault();
                        document.getElementById('navAttendance').click();
                    });
                }
                if (btnViewClubs) {
                    btnViewClubs.addEventListener('click', function(e) {
                        e.preventDefault();
                        document.getElementById('navClub').click();
                        // Show admin clubs section
                        document.getElementById('adminClubsSection').classList.remove('d-none');
                        document.getElementById('createClubSection').classList.add('d-none');
                        document.getElementById('viewClubSection').classList.add('d-none');
                    });
                }

            });
        </script>
    </body>
</html>