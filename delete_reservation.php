<?php
require_once 'db.php';
session_start();

// Vérifier l'autorisation
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Accès refusé');
}

// Vérifier le token CSRF
// Dans delete_reservation.php, remplacez la vérification CSRF par :
    if (false && (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
        header('HTTP/1.1 403 Forbidden');
        exit('Token de sécurité invalide (désactivé pour test)');
    }


// Valider l'ID
$reservation_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$reservation_id) {
    header('HTTP/1.1 400 Bad Request');
    exit('ID de réservation invalide');
}

try {
    // Démarrer une transaction
    $conn->begin_transaction();
    
    // 1. Supprimer les services associés
    $stmt = $conn->prepare("DELETE FROM reservation_services WHERE reservation_id = ?");
    $stmt->bind_param('i', $reservation_id);
    $stmt->execute();
    $stmt->close();
    
    // 2. Supprimer la réservation
    $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
    $stmt->bind_param('i', $reservation_id);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Aucune réservation trouvée avec cet ID");
    }
    
    $stmt->close();
    $conn->commit();
    
    $_SESSION['flash_message'] = "Réservation #$reservation_id supprimée avec succès";
    header('Location: reservation.php');
    exit();

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    $_SESSION['error_message'] = "Erreur lors de la suppression : " . $e->getMessage();
    error_log("Erreur suppression réservation: " . $e->getMessage());
    header('Location: reservation.php');
    exit();
}
?>