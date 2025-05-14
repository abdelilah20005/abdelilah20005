<?php
// Connexion à la base de données
require_once 'db.php';

// Récupération du terme de recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Requête pour récupérer les données
$query = "SELECT 
            CONCAT(prenom, ' ', nom) AS 'Nom complet',
            email AS 'Email',
            telephone AS 'Téléphone',
            poste AS 'Poste',
            CASE 
                WHEN departement = 'reception' THEN 'Réception'
                WHEN departement = 'menage' THEN 'Ménage'
                WHEN departement = 'maintenance' THEN 'Maintenance'
                WHEN departement = 'direction' THEN 'Direction'
                WHEN departement = 'restauration' THEN 'Restauration'
                ELSE departement
            END AS 'Département',
            date_embauche AS 'Date d\'embauche',
            CASE 
                WHEN statut = 'actif' THEN 'Actif'
                WHEN statut = 'inactif' THEN 'Inactif'
                WHEN statut = 'congé' THEN 'En congé'
                ELSE statut
            END AS 'Statut',
            CONCAT(salaire, ' €') AS 'Salaire'
          FROM personnel";

if (!empty($search)) {
    $query .= " WHERE CONCAT(nom, ' ', prenom) LIKE '%$search%' 
                OR email LIKE '%$search%' 
                OR telephone LIKE '%$search%' 
                OR poste LIKE '%$search%'";
}

$query .= " ORDER BY nom, prenom";
$result = mysqli_query($conn, $query);

// Vérification s'il y a des résultats
if (mysqli_num_rows($result) === 0) {
    die("Aucune donnée à exporter");
}

// Création du nom de fichier
$filename = 'export_personnel_' . date('Y-m-d_H-i') . '.csv';

// En-têtes HTTP pour forcer le téléchargement
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Création du fichier CSV en sortie
$output = fopen('php://output', 'w');

// En-têtes du CSV
fputcsv($output, [
    'Nom complet',
    'Email', 
    'Téléphone',
    'Poste',
    'Département',
    'Date d\'embauche',
    'Statut',
    'Salaire'
], ';');

// Ajout des données
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, $row, ';');
}

// Fermeture du fichier
fclose($output);

// Fermeture de la connexion
mysqli_close($conn);
exit;
?>