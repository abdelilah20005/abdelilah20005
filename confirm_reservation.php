<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

try {
    // Vérifications de sécurité
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Non autorisé", 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Méthode non autorisée", 405);
    }

    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        throw new Exception("ID invalide", 400);
    }

    $reservation_id = (int)$_POST['id'];
    $conn->begin_transaction();

    // Vérifier la réservation
    $stmt = $conn->prepare("SELECT id, chambre_id, statut FROM reservations WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();

    if (!$reservation) {
        throw new Exception("Réservation introuvable", 404);
    }

    if ($reservation['statut'] !== 'attente') {
        throw new Exception("Seules les réservations en attente peuvent être confirmées", 400);
    }

    // Confirmer la réservation
    $stmt = $conn->prepare("UPDATE reservations SET statut = 'confirmee' WHERE id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();

    // Mettre à jour la chambre
    $stmt = $conn->prepare("UPDATE chambres SET disponible = 0 WHERE id = ?");
    $stmt->bind_param("i", $reservation['chambre_id']);
    $stmt->execute();

    // Ajouter une notification
    $message = "Réservation #$reservation_id confirmée";
    $stmt = $conn->prepare("INSERT INTO notifications (utilisateur_id, message, type) VALUES (?, ?, 'reservation')");
    $stmt->bind_param("is", $_SESSION['user_id'], $message);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Réservation confirmée',
        'reservation_id' => $reservation_id
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
?>