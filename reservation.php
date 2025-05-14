<?php
require_once 'db.php';
session_start();

// Vérification d'authentification et autorisations
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fonctions utilitaires
function formatFrenchDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Initialisation des variables
$filter_status = $_GET['status'] ?? 'all';
$filter_date = $_GET['date'] ?? '';

// Requête sécurisée avec préparation
$sql = "SELECT r.id, r.reference, r.date_arrivee, r.date_depart, r.prix_total, r.statut, 
               CONCAT(c.nom, ' ', c.prenom) AS client_nom,
               CONCAT('Chambre ', ch.numero, ' - ', tc.nom) AS chambre_info
        FROM reservations r
        JOIN clients c ON r.client_id = c.id
        JOIN chambres ch ON r.chambre_id = ch.id
        JOIN types_chambres tc ON ch.type_id = tc.id
        WHERE 1=1";

$params = [];
$types = '';

// Filtrage par statut
if ($filter_status !== 'all') {
    $sql .= " AND r.statut = ?";
    $params[] = $filter_status;
    $types .= 's';
}

// Filtrage par date
if (!empty($filter_date)) {
    $dates = explode(' au ', $filter_date);
    if (count($dates) === 2) {
        $start_date = DateTime::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
        $end_date = DateTime::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d');
        $sql .= " AND r.date_arrivee BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
        $types .= 'ss';
    }
}

$sql .= " ORDER BY r.date_arrivee DESC";

// Exécution sécurisée
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$reservations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Réservations - HôtelLuxe</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <style>
    :root {
      --primary: #0a1f38;
      --secondary: #1e4a8e;
      --accent: #d4af37;
      --light: #f8f9fa;
      --dark: #2d3748;
      --success: #48bb78;
      --warning: #DD6B20;
      --error: #E53E3E;
      --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      --transition: all 0.3s ease;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background-color: #f5f7fa;
      color: var(--dark);
      line-height: 1.6;
    }

    /* Header amélioré */
    header {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: white;
      box-shadow: var(--card-shadow);
      position: sticky;
      top: 0;
      z-index: 1000;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .logo-icon {
      font-size: 1.8rem;
      color: var(--accent);
    }

    .logo-text {
      font-size: 1.5rem;
      font-weight: 600;
    }

    .logo-text span {
      font-weight: 300;
      opacity: 0.9;
    }

    .user-menu {
      display: flex;
      align-items: center;
      gap: 1.5rem;
    }

    .notifications {
      position: relative;
      cursor: pointer;
      color: white;
    }

    .notification-badge {
      position: absolute;
      top: -6px;
      right: -6px;
      background-color: var(--error);
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.7rem;
      font-weight: bold;
    }

    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: var(--accent);
      color: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      cursor: pointer;
      transition: var(--transition);
    }

    .user-avatar:hover {
      transform: scale(1.05);
      box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.3);
    }

    /* Contenu principal */
    .main-content {
      max-width: 1400px;
      margin: 2rem auto;
      padding: 0 2rem;
    }

    /* Titre de page */
    .page-title {
      font-size: 1.8rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
      color: var(--primary);
      position: relative;
      padding-bottom: 0.5rem;
    }

    .page-title::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 60px;
      height: 4px;
      background: var(--accent);
      border-radius: 2px;
    }

    /* Filtres améliorés */
    .filters-container {
      background: white;
      padding: 1.5rem;
      border-radius: 12px;
      margin-bottom: 2rem;
      box-shadow: var(--card-shadow);
    }

    .filter-row {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
      flex-wrap: wrap;
    }

    .filter-group {
      flex: 1;
      min-width: 200px;
    }

    .filter-label {
      display: block;
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
      color: var(--dark);
      font-weight: 500;
    }

    .filter-input {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      font-size: 0.95rem;
      background-color: white;
      transition: var(--transition);
    }

    .filter-input:focus {
      outline: none;
      border-color: var(--secondary);
      box-shadow: 0 0 0 3px rgba(30, 74, 142, 0.1);
    }

    .filter-btn {
      background-color: var(--secondary);
      color: white;
      border: none;
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 500;
      transition: var(--transition);
      align-self: flex-end;
    }

    .filter-btn:hover {
      background-color: var(--primary);
      transform: translateY(-2px);
    }

    /* Liste des réservations */
    .reservations-list {
      display: grid;
      gap: 1.5rem;
    }

    /* Carte de réservation améliorée */
    .reservation-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: var(--card-shadow);
      transition: var(--transition);
      border-left: 4px solid transparent;
    }

    .reservation-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .reservation-card.confirmed {
      border-left-color: var(--success);
    }

    .reservation-card.pending {
      border-left-color: var(--warning);
    }

    .reservation-card.cancelled {
      border-left-color: var(--error);
    }

    .reservation-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 1rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid #edf2f7;
      align-items: center;
    }

    .reservation-id {
      font-weight: 600;
      color: var(--secondary);
      font-size: 1.1rem;
    }

    .reservation-status {
      padding: 0.35rem 1rem;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .status-confirmed {
      background-color: rgba(72, 187, 120, 0.15);
      color: var(--success);
    }

    .status-pending {
      background-color: rgba(221, 107, 32, 0.15);
      color: var(--warning);
    }

    .status-cancelled {
      background-color: rgba(229, 62, 62, 0.15);
      color: var(--error);
    }

    .reservation-details {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .detail-group label {
      display: block;
      font-size: 0.8rem;
      color: #718096;
      margin-bottom: 0.3rem;
      font-weight: 500;
    }

    .detail-value {
      font-weight: 500;
      color: var(--dark);
      font-size: 1rem;
    }

    .price-value {
      font-weight: 600;
      color: var(--secondary);
      font-size: 1.1rem;
    }

    .reservation-actions {
      display: flex;
      justify-content: flex-end;
      gap: 0.75rem;
      border-top: 1px solid #edf2f7;
      padding-top: 1rem;
    }

    .action-btn {
      background: none;
      border: none;
      color: var(--secondary);
      font-size: 1.1rem;
      cursor: pointer;
      transition: var(--transition);
      padding: 0.5rem;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .action-btn:hover {
      background-color: rgba(30, 74, 142, 0.1);
      color: var(--primary);
      transform: scale(1.1);
    }

    .action-btn.delete:hover {
      background-color: rgba(229, 62, 62, 0.1);
      color: var(--error);
    }

    /* Bouton flottant amélioré */
    .floating-btn {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--secondary), var(--accent));
      color: white;
      border: none;
      box-shadow: 0 6px 20px rgba(30, 74, 142, 0.3);
      font-size: 1.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 90;
      cursor: pointer;
      transition: var(--transition);
    }

    .floating-btn:hover {
      transform: scale(1.1) translateY(-5px);
      box-shadow: 0 10px 25px rgba(30, 74, 142, 0.4);
    }

    /* Message vide */
    .empty-state {
      text-align: center;
      padding: 3rem;
      background: white;
      border-radius: 12px;
      box-shadow: var(--card-shadow);
    }

    .empty-state i {
      font-size: 3rem;
      color: #cbd5e0;
      margin-bottom: 1rem;
    }

    .empty-state p {
      color: #718096;
      font-size: 1.1rem;
    }

    /* Pied de page amélioré */
    footer {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      text-align: center;
      padding: 2.5rem;
      margin-top: 5rem;
    }

    .footer-content {
      max-width: 800px;
      margin: 0 auto;
    }

    .footer-links {
      display: flex;
      justify-content: center;
      gap: 2rem;
      margin: 1.5rem 0;
      flex-wrap: wrap;
    }

    .footer-links a {
      color: white;
      text-decoration: none;
      transition: var(--transition);
      opacity: 0.8;
    }

    .footer-links a:hover {
      opacity: 1;
      color: var(--accent);
    }

    .copyright {
      margin-top: 1.5rem;
      opacity: 0.7;
      font-size: 0.9rem;
    }

    /* Animations */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .reservation-card {
      animation: fadeIn 0.5s ease forwards;
    }

    /* Responsive */
    @media (max-width: 768px) {
      header {
        padding: 1rem;
      }

      .main-content {
        padding: 0 1rem;
      }

      .filter-row {
        flex-direction: column;
        gap: 1rem;
      }

      .filter-group {
        width: 100%;
      }

      .reservation-details {
        grid-template-columns: 1fr 1fr;
      }
    }

    @media (max-width: 480px) {
      .reservation-details {
        grid-template-columns: 1fr;
      }

      .reservation-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
      }

      .footer-links {
        flex-direction: column;
        gap: 1rem;
      }
      /* Style pour le bouton de suppression */
.action-btn.delete {
    color: #E53E3E;
}

.action-btn.delete:hover {
    background-color: rgba(229, 62, 62, 0.1);
    color: #E53E3E;
}

/* Animation de suppression */
@keyframes fadeOut {
    to { opacity: 0; height: 0; padding: 0; margin: 0; }
}

.reservation-card.deleting {
    animation: fadeOut 0.3s ease forwards;
}
    }
  </style>
</head>
<body>
  <!-- Header -->
  <header>
    <div class="logo">
      
      <i class="fas fa-hotel logo-icon"></i>
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
    <h1 class="page-title">Gestion des Réservations</h1>
    
    <!-- Filtres améliorés -->
    <div class="filters-container">
      <form method="GET" action="reservation.php">
        <div class="filter-row">
          <div class="filter-group">
            <label class="filter-label">Statut</label>
            <select class="filter-input" name="status">
              <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>Tous</option>
              <option value="confirmee" <?= $filter_status === 'confirmee' ? 'selected' : '' ?>>Confirmées</option>
              <option value="attente" <?= $filter_status === 'attente' ? 'selected' : '' ?>>En attente</option>
              <option value="annulee" <?= $filter_status === 'annulee' ? 'selected' : '' ?>>Annulées</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label">Date d'arrivée</label>
            <input type="text" class="filter-input" placeholder="Sélectionner une période" id="reservation-date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
          </div>
          <button type="submit" class="filter-btn">
            <i class="fas fa-filter"></i> Filtrer
          </button>
        </div>
      </form>
    </div>

    <!-- Liste des réservations -->
    <div class="reservations-list">
      <?php if (empty($reservations)): ?>
        <div class="empty-state">
          <i class="fas fa-calendar-times"></i>
          <p>Aucune réservation trouvée</p>
        </div>
      <?php else: ?>
        <?php foreach ($reservations as $index => $reservation): ?>
          <div class="reservation-card <?= $reservation['statut'] === 'confirmee' ? 'confirmed' : ($reservation['statut'] === 'annulee' ? 'cancelled' : 'pending') ?>">
            <div class="reservation-header">
              <span class="reservation-id">#<?= htmlspecialchars($reservation['reference']) ?></span>
              <span class="reservation-status status-<?= 
                $reservation['statut'] === 'confirmee' ? 'confirmed' : 
                ($reservation['statut'] === 'annulee' ? 'cancelled' : 'pending') 
              ?>">
                <?= 
                  $reservation['statut'] === 'confirmee' ? 'Confirmée' : 
                  ($reservation['statut'] === 'annulee' ? 'Annulée' : 'En attente') 
                ?>
              </span>
            </div>
            <div class="reservation-details">
              <div class="detail-group">
                <label>Client</label>
                <div class="detail-value"><?= htmlspecialchars($reservation['client_nom']) ?></div>
              </div>
              <div class="detail-group">
                <label>Chambre</label>
                <div class="detail-value"><?= htmlspecialchars($reservation['chambre_info']) ?></div>
              </div>
              <div class="detail-group">
                <label>Arrivée</label>
                <div class="detail-value"><?= formatFrenchDate($reservation['date_arrivee']) ?></div>
              </div>
              <div class="detail-group">
                <label>Départ</label>
                <div class="detail-value"><?= formatFrenchDate($reservation['date_depart']) ?></div>
              </div>
              <div class="detail-group">
                <label>Prix total</label>
                <div class="detail-value price-value"><?= number_format($reservation['prix_total'], 2, ',', ' ') ?> €</div>
              </div>
            </div>
            <!-- Dans la partie "reservation-actions" de chaque carte de réservation -->
<div class="reservation-actions">
  <a href="reservation_details.php?id=<?= $reservation['id'] ?>" class="action-btn" title="Voir détails">
    <i class="fas fa-eye"></i>
  </a>
  <a href="edit_reservation.php?id=<?= $reservation['id'] ?>" class="action-btn" title="Modifier">
    <i class="fas fa-edit"></i>
  </a>
  <?php if ($reservation['statut'] === 'attente'): ?>
    <form method="POST" action="confirm_reservation.php" class="confirm-form" style="display:inline;">
  <input type="hidden" name="id" value="<?= $reservation['id'] ?>">
  <button type="submit" class="action-btn confirm-btn" title="Confirmer">
    <i class="fas fa-check"></i>
  </button>
</form>
  <?php endif; ?>
  
  <!-- Bouton de suppression pour toutes les réservations -->
  <form method="POST" action="delete_reservation.php" style="display:inline;">
    <input type="hidden" name="id" value="<?= $reservation['id'] ?>">
    <button type="submit" class="action-btn delete" title="Supprimer" 
            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette réservation? Cette action est irréversible.')">
      <i class="fas fa-trash"></i>
    </button>
  </form>
</div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    

    <!-- Bouton flottant pour nouvelle réservation -->
    <a href="add_reservation.php" class="floating-btn" title="Nouvelle réservation">
      <i class="fas fa-plus"></i>
    </a>
  </div>

  <!-- Pied de page -->
  <footer>
    <div class="footer-content">
      <div class="footer-links">
        <a href="accueil.php">Accueil</a>
        <a href="reservation.php">Réservations</a>
        <a href="#">Services</a>
        <a href="#">Contact</a>
        <a href="#">À propos</a>
        <a href="#">Confidentialité</a>
      </div>
      <div class="copyright">
        &copy; <?= date('Y') ?> HôtelLuxe. Tous droits réservés.
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
  <script>
    // Configuration du datepicker
    flatpickr("#reservation-date", {
      locale: "fr",
      mode: "range",
      dateFormat: "d/m/Y",
      placeholder: "Sélectionner une période"
    });

    // Animation des cartes
    document.addEventListener('DOMContentLoaded', () => {
      const cards = document.querySelectorAll('.reservation-card');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
      });
    });
  </script>
  <script>
document.addEventListener('DOMContentLoaded', function() {
  // Gestion de la confirmation
  document.querySelectorAll('.confirm-form').forEach(form => {
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: {
            'Accept': 'application/json'
          }
        });
        
        const result = await response.json();
        
        if (result.success) {
          // Actualiser la page ou mettre à jour l'interface
          location.reload();
        } else {
          alert(result.message || "Erreur lors de la confirmation");
        }
      } catch (error) {
        console.error('Error:', error);
        alert("Une erreur s'est produite");
      }
    });
  });
});
</script>
</body>
</html>