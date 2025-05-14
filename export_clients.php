<?php
// export_clients.php
require_once 'db.php';

// Vérifier que l'utilisateur est autorisé à exporter
session_start();
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    die('Accès refusé');
}

// Vérifier que la connexion à la base de données est valide
if (!$conn) {
    header('HTTP/1.1 500 Internal Server Error');
    die('Erreur de connexion à la base de données');
}

// Configuration de l'export
$filename = 'clients_hotel_luxe_' . date('Y-m-d') . '.csv';

// En-têtes HTTP pour forcer le téléchargement
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Création du fichier de sortie
$output = fopen('php://output', 'w');

// En-têtes du CSV
fputcsv($output, [
    'ID',
    'Nom',
    'Prénom',
    'Email', 
    'Téléphone',
    'Adresse',
    'Ville',
    'Code Postal',
    'Pays',
    'Statut',
    'Date Création'
], ';');

// Requête pour récupérer les clients
$query = "SELECT 
            id, 
            nom, 
            prenom, 
            email, 
            telephone, 
            adresse, 
            ville, 
            code_postal, 
            pays, 
            statut, 
            DATE_FORMAT(date_creation, '%d/%m/%Y %H:%i') as date_creation
          FROM clients 
          ORDER BY nom, prenom";

$result = mysqli_query($conn, $query);

if (!$result) {
    // Journaliser l'erreur avant d'envoyer les en-têtes CSV
    error_log("Erreur MySQL: " . mysqli_error($conn));
    header('HTTP/1.1 500 Internal Server Error');
    die('Erreur lors de la récupération des données');
}

// Écrire les données des clients
while ($row = mysqli_fetch_assoc($result)) {
    // Nettoyer les données pour le CSV
    $row['statut'] = ($row['statut'] === 'actif') ? 'Actif' : 'Inactif';
    
    // Écrire la ligne dans le CSV
    fputcsv($output, $row, ';');
}

// Fermer la connexion et le fichier
mysqli_free_result($result);
fclose($output);
exit();