<?php
require_once 'DB_Connection.php';

header('Content-Type: application/json');

try {
    $query = "SELECT eventname AS title, description, startdate AS date, enddate AS end_date FROM events ORDER BY startdate ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();

    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($events);
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to fetch events: ' . $e->getMessage()]);
}
?>