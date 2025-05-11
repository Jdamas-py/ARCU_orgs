<?php
// download_events_report.php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=events_report.csv');

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
    $stmt = $pdo->query('SELECT * FROM events ORDER BY id DESC');
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
    exit;
}

$output = fopen('php://output', 'w');
fputcsv($output, ['Event ID', 'Event Name', 'Start Date', 'End Date', 'Description']);
foreach ($events as $event) {
    fputcsv($output, [
        $event['id'],
        $event['eventname'],
        $event['startdate'],
        $event['enddate'],
        $event['description'],
    ]);
}
fclose($output);
exit; 