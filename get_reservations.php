<?php
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_GET['client_id'])) {
    echo json_encode([]);
    exit;
}

$clientId = (int)$_GET['client_id'];
$query = "SELECT id, reference, date_arrivee, date_depart 
          FROM reservations 
          WHERE client_id = ? AND statut = 'confirmee'
          ORDER BY date_arrivee DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $clientId);
$stmt->execute();
$result = $stmt->get_result();

$reservations = [];
while ($row = $result->fetch_assoc()) {
    $reservations[] = [
        'id' => $row['id'],
        'reference' => $row['reference'],
        'date_arrivee' => $row['date_arrivee'],
        'date_depart' => $row['date_depart']
    ];
}

echo json_encode($reservations);
?>