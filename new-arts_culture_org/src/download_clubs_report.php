<?php
// download_clubs_report.php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=clubs_report.csv');

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
    $stmt = $pdo->query('SELECT * FROM clubs ORDER BY club_id DESC');
    $clubs = $stmt->fetchAll();
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
    exit;
}

$output = fopen('php://output', 'w');
// Output the column headings
fputcsv($output, ['Club ID', 'Club Name', 'Description', 'Meeting Schedule', 'Location', 'Status', 'Created At', 'Created By']);
// Output the data
foreach ($clubs as $club) {
    fputcsv($output, [
        $club['club_id'],
        $club['club_name'],
        $club['description'],
        $club['meeting_schedule'],
        $club['location'],
        $club['status'],
        $club['created_at'],
        $club['created_by'],
    ]);
}
fclose($output);
exit; 