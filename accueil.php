<?php
// Inclure le fichier db.php pour la connexion à la base de données
require_once 'db.php';
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fonction pour récupérer les statistiques du tableau de bord
function getDashboardStats($conn) {
    // Requête SQL corrigée pour utiliser la colonne "statut"
    $sql = "SELECT COUNT(*) AS bookings, 
            (SUM(CASE WHEN statut = 'confirmee' THEN 1 ELSE 0 END) / COUNT(*)) * 100 AS occupancy_rate,
            SUM(prix_total) AS revenue
            FROM reservations";
    
    // Exécution de la requête
    $result = $conn->query($sql);
    
    // Vérification du résultat de la requête
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return [
            'bookings' => 0,
            'occupancy_rate' => 0,
            'revenue' => 0
        ];
    }
}

// Récupérer les statistiques du tableau de bord
$stats = getDashboardStats($conn);

// Formater les valeurs pour l'affichage
$reservations = htmlspecialchars($stats['bookings']);
$occupation = htmlspecialchars($stats['occupancy_rate']);
$revenue = htmlspecialchars(number_format($stats['revenue'] / 1000, 1) . 'K€');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HôtelLuxe - Accueil</title>
  <link rel="stylesheet" href="accueil.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <header>
    <div class="logo">
      <img src="logo.png" alt="Logo HôtelLuxe" class="logo-icon">
      <div class="logo-text">Hôtel<span>Luxe</span></div>
    </div>
    <div class="user-menu">
      <div class="notifications">
        <i class="fas fa-bell"></i>
        <span class="notification-badge">3</span>
      </div>
      <div class="user-avatar">AD</div>
    </div>
  </header>

  <div class="main-content">
    <div class="stats-container">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">
            <i class="fas fa-calendar-check"></i>
          </div>
          <div class="stat-value"><?php echo $reservations; ?></div>
          <div class="stat-label">Réservations</div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">
            <i class="fas fa-bed"></i>
          </div>
          <div class="stat-value"><?php echo $occupation; ?>%</div>
          <div class="stat-label">Taux d'occupation</div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">
            <i class="fas fa-euro-sign"></i>
          </div>
          <div class="stat-value"><?php echo $revenue; ?></div>
          <div class="stat-label">Revenus mensuels</div>
        </div>
      </div>
    </div>

    <h2 class="section-title">Gestion Hôtelière</h2>

    <div class="features-grid">
      <div class="feature-card" onclick="location.href='reservation.php'">
        <div class="feature-icon">
          <i class="fas fa-calendar-check"></i>
        </div>
        <h3 class="feature-title">Réservations</h3>
        <p class="feature-desc">Gérer les réservations</p>
      </div>

      <div class="feature-card" onclick="location.href='chambres.php'">
        <div class="feature-icon">
          <i class="fas fa-bed"></i>
        </div>
        <h3 class="feature-title">Chambres</h3>
        <p class="feature-desc">Statut et disponibilité</p>
      </div>

      <div class="feature-card" onclick="location.href='clientes.php'">
        <div class="feature-icon">
          <i class="fas fa-users"></i>
        </div>
        <h3 class="feature-title">Clients</h3>
        <p class="feature-desc">Fiches et historiques</p>
      </div>

      <div class="feature-card" onclick="location.href='personnel.php'">
        <div class="feature-icon">
          <i class="fas fa-user-tie"></i>
        </div>
        <h3 class="feature-title">Personnel</h3>
        <p class="feature-desc">Gestion des équipes</p>
      </div>

      <div class="feature-card" onclick="location.href='facturation.php'">
        <div class="feature-icon">
          <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <h3 class="feature-title">Facturation</h3>
        <p class="feature-desc">Paiements et rapports</p>
      </div>
    </div>
  </div>

  <footer>
    <div class="footer-content">
      <div class="footer-links">
        <a href="#">Accueil</a>
        <a href="#">Services</a>
        <a href="#">Contact</a>
        <a href="#">À propos</a>
        <a href="#">Confidentialité</a>
      </div>
      <div class="copyright">
        &copy; <?php echo date('Y'); ?> HôtelLuxe. Tous droits réservés.
      </div>
    </div>
  </footer>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const cards = document.querySelectorAll('.feature-card');
      cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transitionDelay = `${index * 0.1}s`;

        setTimeout(() => {
          card.style.opacity = '1';
          card.style.transform = 'translateY(0)';
        }, 300);
      });
    });
  </script>
</body>
</html>
