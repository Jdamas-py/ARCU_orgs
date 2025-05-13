<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['acc_id']) || $_SESSION['role'] !== 'student') {
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

$successMessage = '';
$errors         = [];

// Fetch all clubs for the join club form
$allClubs = [];
try {
    $stmt = $pdo->query('SELECT * FROM clubs ORDER BY club_name ASC');
    $allClubs = $stmt->fetchAll();
} catch (PDOException $e) {
    $allClubs = [];
}

// Fetch all gallery images for student view
$galleryImages = [];
try {
    $stmt = $pdo->query('SELECT * FROM gallery ORDER BY upload_date DESC');
    $galleryImages = $stmt->fetchAll();
} catch (PDOException $e) {
    $galleryImages = [];
}

// Fetch all attendance records (for student view, like admin)
$studentAttendance = [];
try {
    $stmt = $pdo->query('SELECT * FROM attendance ORDER BY attendance_date DESC, time DESC');
    $studentAttendance = $stmt->fetchAll();
} catch (PDOException $e) {
    $studentAttendance = [];
}

// Fetch all events for the attendance form
$events = [];
try {
    $stmt = $pdo->query('SELECT * FROM events ORDER BY startdate DESC');
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    $events = [];
}

// Fetch student's club applications
$clubApplications = [];
try {
    $stmt = $pdo->prepare('
        SELECT cm.*, c.club_name 
        FROM club_members cm 
        LEFT JOIN clubs c ON cm.interests = c.club_name 
        WHERE cm.student_id = ? 
        ORDER BY cm.id DESC
    ');
    $stmt->execute([$_SESSION['acc_id']]);
    $clubApplications = $stmt->fetchAll();
} catch (PDOException $e) {
    $clubApplications = [];
}

// Handle Join Club form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['studentName'], $_POST['studentId'], $_POST['email'], $_POST['interests'], $_POST['whyJoin'])) {
    $studentName = trim($_POST['studentName']);
    $studentId = trim($_POST['studentId']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $interests = is_array($_POST['interests']) ? implode(',', $_POST['interests']) : trim($_POST['interests']);
    $whyJoin = trim($_POST['whyJoin']);

    if ($studentName && $studentId && $email && $interests && $whyJoin) {
        try {
            $stmt = $pdo->prepare('INSERT INTO club_members (student_name, student_id, email, phone, interests, why_join) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$studentName, $studentId, $email, $phone, $interests, $whyJoin]);
            // Redirect to avoid form resubmission
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit();
        } catch (PDOException $e) {
            $errors[] = 'Error joining club: ' . $e->getMessage();
        }
    } else {
        $errors[] = 'Please fill in all required fields.';
    }
}

// Show success message if redirected
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $successMessage = 'Successfully joined the club!';
}

// Handle student attendance form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_attendance'])) {
    $name = trim($_POST['attendeeName'] ?? '');
    $date = $_POST['attDate'] ?? '';
    $time = $_POST['attTime'] ?? '';
    $event_id = (int) ($_POST['attEvent'] ?? 0);
    $status = 'present';
    $student_id = $_SESSION['acc_id'];
    $errors = [];
    if ($name === '') $errors[] = 'Attendee Name is required.';
    if (!$date) $errors[] = 'Attendance Date is required.';
    if (!$time) $errors[] = 'Attendance Time is required.';
    if ($event_id <= 0) $errors[] = 'Valid Event is required.';
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO attendance (name, student_id, attendance_date, time, event_id, status) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $student_id, $date, $time, $event_id, $status]);
            $successMessage = 'Attendance recorded successfully!';
        } catch (PDOException $e) {
            $errors[] = 'Error recording attendance: ' . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="main.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            overflow-x: hidden;
        }
        .navbar {
            background: linear-gradient(140deg, #4e342e, #3e2723);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .admin-logo {
            width: 40px;
            height: 40px;
            background-color: #6c757d;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-weight: bold;
        }
        .sidebar {
            background-color: #3e2723;
            color: white;
            min-height: 100vh;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.75);
            transition: color 0.3s, background-color 0.3s;
            padding: 10px 15px;
            border-radius: 5px;
        }
        .sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.2);

        }
        .sidebar .bi {
            color: #fff !important;
            opacity: 1 !important;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(255, 255, 255, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background-color: #5d4037;
            color: white;
            font-weight: bold;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        .btn {
            border-radius: 5px;
            font-weight: bold;
        }
        .btn-primary {
            background-color: #6a1b9a;
            border-color: #6a1b9a;
        }
        .btn-primary:hover {
            background-color: #4a148c;
            border-color: #4a148c;
        }
        .section-title {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #3e2723;
        }
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        .table th {
            background-color: #6c757d;
            color: white;
        }
        .table td {
            background-color: #f8f9fa;
        }
        /* Responsive sidebar styles */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -250px;
                width: 250px;
                height: 100%;
                transition: left 0.3s ease;
                z-index: 1050;
            }
            .sidebar.collapsed {
                left: 0;
            }
            .main-content {
                margin-left: 0;
                transition: margin-left 0.3s ease;
            }
            .sidebar.collapsed + .main-content {
                margin-left: 250px;
            }
        }
    </style>
</head>
<body>
    <header class="navbar navbar-expand-lg navbar-dark">
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
                    <span class="me-2 d-none d-md-inline">Student Panel</span>
                    <div class="admin-logo" aria-label="Student Panel Logo">
                        S
                    </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="adminDropdown">
                    <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Manage Account</a></li>
                    <li><hr class="dropdown-divider" /></li>
                    <li><a class="dropdown-item" href="ARCU-login.php"><i class="bi bi-box-arrow-right me-2"></i>Log Out</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#" data-section="dashboardSection">
                                <i class="bi bi-house me-2"></i>Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-section="eventsSection">
                                <i class="bi bi-calendar-event me-2"></i>Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-section="attendanceSection">
                                <i class="bi bi-people me-2"></i>Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-section="gallerySection" id="navGallery">
                                <i class="bi bi-images me-2"></i>Gallery
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-section="joinClubSection" id="navJoinClub">
                                <i class="bi bi-collection me-2"></i>Clubs
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main id="content" class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
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
                <section id="dashboardSection" class="section-container">
                    <h2 class="section-title">Welcome to Your Dashboard</h2>
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-secondary text-white fw-bold text-center">
                                    Upcoming Events
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Check out the latest events happening this week.</p>
                                    <a href="#" class="btn btn-primary w-100" id="btnViewEvents">View Events</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-secondary text-white fw-bold text-center">
                                    Attendance Records
                                </div>
                                <div class="card-body">
                                    <p class="card-text">View and manage your attendance records.</p>
                                    <a href="#" class="btn btn-primary w-100" id="btnViewAttendance">View Attendance</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-secondary text-white fw-bold text-center">
                                    Join a Club
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Explore and join various clubs and organizations.</p>
                                    <a href="#" class="btn btn-primary w-100" id="btnJoinNow">Join Now</a>
                                    <?php if (!empty($clubApplications)): ?>
                                        <div class="mt-3">
                                            <h6>Your Applications</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Club</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($clubApplications as $application): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($application['club_name'] ?? $application['interests']) ?></td>
                                                                <td>
                                                                    <?php
                                                                    $statusClass = 'bg-warning';
                                                                    $statusText = 'Pending';
                                                                    if ($application['status'] === 'accepted') {
                                                                        $statusClass = 'bg-success';
                                                                        $statusText = 'Accepted';
                                                                    } elseif ($application['status'] === 'declined') {
                                                                        $statusClass = 'bg-danger';
                                                                        $statusText = 'Declined';
                                                                    }
                                                                    ?>
                                                                    <span class="badge <?= $statusClass ?>">
                                                                        <?= $statusText ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- All Club Members Table -->
                    <div class="mt-4">
                        <h4>All Club Members</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Student ID</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Interests</th>
                                        <th>Why Join</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $allClubMembers = [];
                                    try {
                                        $stmt = $pdo->query('SELECT * FROM club_members ORDER BY id DESC');
                                        $allClubMembers = $stmt->fetchAll();
                                    } catch (PDOException $e) {
                                        $allClubMembers = [];
                                    }
                                    if (!empty($allClubMembers)):
                                        foreach ($allClubMembers as $member): ?>
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
                                            </tr>
                                        <?php endforeach;
                                    else: ?>
                                        <tr><td colspan="7" class="text-center">No club members found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section id="eventsSection" class="section-container d-none">
                    <h2 class="section-title">Events</h2>
                    <div class="row">
                        <div class="col-12">
                            <div class="card mt-4 mb-4">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="card-title mb-0">All Events</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Event Name</th>
                                                    <th>Start Date</th>
                                                    <th>End Date</th>
                                                    <th>Description</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($events)): ?>
                                                    <?php foreach ($events as $index => $event): ?>
                                                        <tr>
                                                            <td><?= $index + 1 ?></td>
                                                            <td><?= htmlspecialchars($event['eventname']) ?></td>
                                                            <td><?= htmlspecialchars($event['startdate']) ?></td>
                                                            <td><?= htmlspecialchars($event['enddate']) ?></td>
                                                            <td><?= htmlspecialchars($event['description']) ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-secondary btn-record-attendance" data-event-id="<?= $event['id'] ?>" title="Record Attendance for this Event">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr><td colspan="6" class="text-center">No events found.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="attendanceSection" class="section-container d-none">
                    <h2 class="section-title">Attendance</h2>
                    <div class="row justify-content-center">
                        <div class="col-12">
                            <div class="card mt-4 mb-4">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="card-title mb-0">Record Attendance</h5>
                                </div>
                                <div class="card-body">
                                    <form id="attendanceForm" method="post" novalidate>
                                        <input type="hidden" name="record_attendance" value="1" />
                                        <div class="mb-3">
                                            <label for="attendeeName" class="form-label">Attendee Name*</label>
                                            <input type="text" class="form-control" id="attendeeName" name="attendeeName" required value="<?= htmlspecialchars($_POST['attendeeName'] ?? '') ?>" />
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="attDate" class="form-label">Date*</label>
                                                <input type="date" class="form-control" id="attDate" name="attDate" required value="<?= htmlspecialchars($_POST['attDate'] ?? date('Y-m-d')) ?>" />
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="attTime" class="form-label">Time*</label>
                                                <input type="time" class="form-control" id="attTime" name="attTime" required value="<?= htmlspecialchars($_POST['attTime'] ?? date('H:i')) ?>" />
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="attEvent" class="form-label">Event*</label>
                                            <select class="form-select" id="attEvent" name="attEvent" required>
                                                <option value="">Select Event</option>
                                                <?php foreach ($events as $event): ?>
                                                    <option value="<?= $event['id'] ?>"><?= htmlspecialchars($event['eventname']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="text-end">
                                            <button type="submit" class="btn btn-primary">Record Attendance</button>
                                        </div>
                                    </form>
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
                                    <div class="row">
                                        <?php if (!empty($galleryImages)): ?>
                                            <?php foreach ($galleryImages as $img): ?>
                                                <div class="col-md-3 mb-4">
                                                    <div class="card h-100">
                                                        <img src="uploads/gallery/<?= htmlspecialchars($img['image_path']) ?>" class="card-img-top" alt="Gallery Image" style="object-fit:cover; height:200px;" />
                                                        <div class="card-body">
                                                            <p class="card-text small text-muted"><?= htmlspecialchars($img['description']) ?></p>
                                                            <a href="uploads/gallery/<?= htmlspecialchars($img['image_path']) ?>" download="<?= htmlspecialchars($img['image_name']) ?>" class="btn btn-sm btn-success">Download</a>
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

                <section id="joinClubSection" class="section-container d-none" aria-label="Join Club Section">
                    <div class="row justify-content-center">
                        <div class="col-12">
                            <div class="card mt-4 mb-4">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="card-title mb-0">Join ARCU Club</h5>
                                </div>
                                <div class="card-body">
                                    <form id="joinClubForm" method="post" novalidate>
                                        <div class="mb-3">
                                            <label for="studentName" class="form-label">Full Name*</label>
                                            <input type="text" class="form-control" id="studentName" name="studentName" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="studentId" class="form-label">Student ID*</label>
                                            <input type="text" class="form-control" id="studentId" name="studentId" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address*</label>
                                            <input type="email" class="form-control" id="email" name="email" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone">
                                        </div>
                                        <div class="mb-3">
                                            <label for="interests" class="form-label">Areas of Interest*</label>
                                            <select class="form-select" id="interests" name="interests" required>
                                                <option value="">Select Club</option>
                                                <?php foreach ($allClubs as $club): ?>
                                                    <option value="<?= htmlspecialchars($club['club_name']) ?>"><?= htmlspecialchars($club['club_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="whyJoin" class="form-label">Why do you want to join?*</label>
                                            <textarea class="form-control" id="whyJoin" name="whyJoin" rows="3" required></textarea>
                                        </div>
                                        <div class="text-end">
                                            <button type="submit" class="btn btn-primary">Submit Application</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            sidebarToggle.addEventListener('click', function () {
                sidebar.classList.toggle('collapsed');
            });

            // Sidebar navigation using data-section
            const navLinks = document.querySelectorAll('#sidebar .nav-link[data-section]');
            const sections = document.querySelectorAll('main section.section-container');
            navLinks.forEach((link) => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = link.getAttribute('data-section');
                    sections.forEach(section => section.classList.add('d-none'));
                    if (targetId && document.getElementById(targetId)) {
                        document.getElementById(targetId).classList.remove('d-none');
                    }
                    navLinks.forEach(l => l.classList.remove('active'));
                    link.classList.add('active');
                });
            });

            // Home section buttons navigation
            const btnViewEvents = document.getElementById('btnViewEvents');
            const btnViewAttendance = document.getElementById('btnViewAttendance');
            const btnJoinNow = document.getElementById('btnJoinNow');
            if (btnViewEvents) {
                btnViewEvents.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('main section.section-container').forEach(section => section.classList.add('d-none'));
                    document.getElementById('eventsSection').classList.remove('d-none');
                    document.querySelectorAll('#sidebar .nav-link').forEach(l => l.classList.remove('active'));
                    document.querySelector('#sidebar .nav-link[data-section="eventsSection"]').classList.add('active');
                });
            }
            if (btnViewAttendance) {
                btnViewAttendance.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('main section.section-container').forEach(section => section.classList.add('d-none'));
                    document.getElementById('attendanceSection').classList.remove('d-none');
                    document.querySelectorAll('#sidebar .nav-link').forEach(l => l.classList.remove('active'));
                    document.querySelector('#sidebar .nav-link[data-section="attendanceSection"]').classList.add('active');
                });
            }
            if (btnJoinNow) {
                btnJoinNow.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelectorAll('main section.section-container').forEach(section => section.classList.add('d-none'));
                    document.getElementById('joinClubSection').classList.remove('d-none');
                    document.querySelectorAll('#sidebar .nav-link').forEach(l => l.classList.remove('active'));
                    document.querySelector('#sidebar .nav-link[data-section="joinClubSection"]').classList.add('active');
                });
            }

            // Event section: Record Attendance pencil button
            document.querySelectorAll('.btn-record-attendance').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const eventId = this.getAttribute('data-event-id');
                    // Switch to attendance section
                    document.querySelectorAll('main section.section-container').forEach(section => section.classList.add('d-none'));
                    document.getElementById('attendanceSection').classList.remove('d-none');
                    document.querySelectorAll('#sidebar .nav-link').forEach(l => l.classList.remove('active'));
                    document.querySelector('#sidebar .nav-link[data-section="attendanceSection"]').classList.add('active');
                    // Pre-select the event in the attendance form
                    const attEvent = document.getElementById('attEvent');
                    if (attEvent) {
                        attEvent.value = eventId;
                    }
                });
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>