<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Vérification de l'ID de réservation
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "Réservation invalide";
    $_SESSION['message_type'] = "error";
    header('Location: reservation.php');
    exit();
}

$reservation_id = (int)$_GET['id'];

// Récupération des données de la réservation
$stmt = $conn->prepare("SELECT 
    r.*,
    c.nom AS client_nom,
    c.prenom AS client_prenom,
    COALESCE(c.telephone, 'Non spécifié') AS client_telephone,
    COALESCE(c.email, 'Non spécifié') AS client_email,
    ch.numero AS chambre_numero,
    tc.nom AS chambre_type,
    tc.prix AS prix_nuit
    FROM reservations r
    JOIN clients c ON r.client_id = c.id
    JOIN chambres ch ON r.chambre_id = ch.id
    JOIN types_chambres tc ON ch.type_id = tc.id
    WHERE r.id = ?");
$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$reservation = $stmt->get_result()->fetch_assoc();

if (!$reservation) {
    $_SESSION['message'] = "Réservation introuvable";
    $_SESSION['message_type'] = "error";
    header('Location: reservation.php');
    exit();
}

// Récupération des services
$services = $conn->query("SELECT 
    s.nom, 
    s.prix 
    FROM reservation_services rs
    JOIN services s ON rs.service_id = s.id
    WHERE rs.reservation_id = $reservation_id");

// Formatage des dates
$date_arrivee = DateTime::createFromFormat('Y-m-d', $reservation['date_arrivee'])->format('d/m/Y');
$date_depart = DateTime::createFromFormat('Y-m-d', $reservation['date_depart'])->format('d/m/Y');
?>


    
</head>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Réservation #<?= htmlspecialchars($reservation['reference']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<style>
   /* styles.css */
:root {
  --primary-color: #4361ee;
  --secondary-color: #3f37c9;
  --accent-color: #4895ef;
  --light-color: #f8f9fa;
  --dark-color: #212529;
  --success-color: #4cc9f0;
  --warning-color: #f72585;
  --border-radius: 8px;
  --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  --transition: all 0.3s ease;
}

body {
  font-family: 'Poppins', sans-serif;
  background-color: #f5f7fa;
  color: var(--dark-color);
  margin: 0;
  padding: 0;
  line-height: 1.6;
}

.container {
  max-width: 1200px;
  margin: 2rem auto;
  padding: 2rem;
  background: white;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e9ecef;
}

.header h1 {
  color: var(--primary-color);
  margin: 0;
  font-size: 1.8rem;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.detail-card {
  background: var(--light-color);
  padding: 1.5rem;
  border-radius: var(--border-radius);
  transition: var(--transition);
}

.detail-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
}

.detail-label {
  font-weight: 600;
  color: var(--secondary-color);
  display: block;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
}

.detail-value {
  font-size: 1.1rem;
  color: var(--dark-color);
}

.services-section {
  margin-top: 2rem;
  background: var(--light-color);
  padding: 1.5rem;
  border-radius: var(--border-radius);
}

.services-title {
  color: var(--primary-color);
  margin-top: 0;
  margin-bottom: 1.5rem;
  font-size: 1.3rem;
}

.services-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 1rem;
}

.service-item {
  display: flex;
  justify-content: space-between;
  padding: 0.8rem 1rem;
  background: white;
  border-radius: var(--border-radius);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.service-name {
  font-weight: 500;
}

.service-price {
  color: var(--success-color);
  font-weight: 600;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.7rem 1.2rem;
  background: var(--primary-color);
  color: white;
  border: none;
  border-radius: var(--border-radius);
  cursor: pointer;
  text-decoration: none;
  font-weight: 500;
  transition: var(--transition);
}

.btn:hover {
  background: var(--secondary-color);
  transform: translateY(-2px);
}

.btn i {
  font-size: 0.9rem;
}

.status-badge {
  display: inline-block;
  padding: 0.3rem 0.8rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: capitalize;
}

.status-confirmee {
  background: #e3fafc;
  color: #1098ad;
}

.status-attente {
  background: #fff3bf;
  color: #f08c00;
}

.status-annulee {
  background: #ffe3e3;
  color: #e03131;
}

.status-terminee {
  background: #ebfbee;
  color: #2b8a3e;
}

.price-highlight {
  font-size: 1.3rem;
  color: var(--primary-color);
  font-weight: 700;
}

/* Responsive */
@media (max-width: 768px) {
  .container {
    padding: 1rem;
    margin: 1rem;
  }
  
  .header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
  
  .detail-grid {
    grid-template-columns: 1fr;
  }
}
    </style>
<body>
    <div class="container">
        <div class="header">
            <h1>Détails Réservation #<?= htmlspecialchars($reservation['reference']) ?></h1>
            <a href="reservation.php" class="btn">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>

        <div class="detail-grid">
            <div class="detail-card">
                <span class="detail-label">Client</span>
                <p class="detail-value"><?= htmlspecialchars($reservation['client_prenom']) . ' ' . htmlspecialchars($reservation['client_nom']) ?></p>
            </div>

            <div class="detail-card">
                <span class="detail-label">Téléphone</span>
                <p class="detail-value"><?= htmlspecialchars($reservation['client_telephone']) ?></p>
            </div>

            <div class="detail-card">
                <span class="detail-label">Email</span>
                <p class="detail-value"><?= htmlspecialchars($reservation['client_email']) ?></p>
            </div>

            <div class="detail-card">
                <span class="detail-label">Chambre</span>
                <p class="detail-value">Chambre <?= htmlspecialchars($reservation['chambre_numero']) ?> (<?= htmlspecialchars($reservation['chambre_type']) ?>)</p>
            </div>

            <div class="detail-card">
                <span class="detail-label">Dates</span>
                <p class="detail-value">Du <?= $date_arrivee ?> au <?= $date_depart ?> (<?= $reservation['nuits'] ?> nuit(s))</p>
            </div>

            <div class="detail-card">
                <span class="detail-label">Statut</span>
                <p class="detail-value">
                    <span class="status-badge status-<?= $reservation['statut'] ?>">
                        <?= htmlspecialchars(ucfirst($reservation['statut'])) ?>
                    </span>
                </p>
            </div>

            <div class="detail-card">
                <span class="detail-label">Prix total</span>
                <p class="detail-value price-highlight"><?= number_format($reservation['prix_total'], 2) ?> €</p>
            </div>
        </div>

        <?php if ($services->num_rows > 0): ?>
        <div class="services-section">
            <h2 class="services-title">
                <i class="fas fa-concierge-bell"></i> Services supplémentaires
            </h2>
            <ul class="services-list">
                <?php while ($service = $services->fetch_assoc()): ?>
                    <li class="service-item">
                        <span class="service-name"><?= htmlspecialchars($service['nom']) ?></span>
                        <span class="service-price">+<?= number_format($service['prix'], 2) ?> €</span>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
</body>
</html>