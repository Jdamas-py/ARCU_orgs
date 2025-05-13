<?php
// download_attendance_report.php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=attendance_report.csv');

$host    = 'localhost';
$db      = 'db_arcu';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';
$dsn     = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $stmt = $pdo->query('SELECT * FROM attendance ORDER BY id DESC');
    $attendance = $stmt->fetchAll();
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
    exit;
}

$output = fopen('php://output', 'w');
fputcsv($output, ['Attendance ID', 'Name', 'Attendance Date', 'Time', 'Event ID', 'Status']);
foreach ($attendance as $row) {
    fputcsv($output, [
        $row['id'],
        $row['name'],
        $row['attendance_date'],
        $row['time'],
        $row['event_id'],
        $row['status'],
    ]);
}
fclose($output);
exit; 