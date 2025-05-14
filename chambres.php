<?php
session_start();
require_once 'db.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fonction pour récupérer les statistiques des chambres
function getRoomStats($conn) {
    $stats = [
        'total' => 0,
        'available' => 0,
        'occupied' => 0,
        'maintenance' => 0
    ];
    
    $query = "SELECT COUNT(*) as total FROM chambres";
    $result = mysqli_query($conn, $query);
    $stats['total'] = mysqli_fetch_assoc($result)['total'];
    
    $query = "SELECT COUNT(*) as available FROM chambres WHERE statut = 'disponible' AND disponible = 1";
    $result = mysqli_query($conn, $query);
    $stats['available'] = mysqli_fetch_assoc($result)['available'];
    
    $query = "SELECT COUNT(*) as occupied FROM chambres WHERE statut = 'occupee'";
    $result = mysqli_query($conn, $query);
    $stats['occupied'] = mysqli_fetch_assoc($result)['occupied'];
    
    $query = "SELECT COUNT(*) as maintenance FROM chambres WHERE statut = 'maintenance'";
    $result = mysqli_query($conn, $query);
    $stats['maintenance'] = mysqli_fetch_assoc($result)['maintenance'];
    
    return $stats;
}

// Fonction pour récupérer les types de chambres
function getRoomTypes($conn) {
    $types = [];
    $query = "SELECT * FROM types_chambres";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $types[$row['id']] = $row;
    }
    return $types;
}

// Récupération des chambres avec leurs types
function getRoomsWithTypes($conn, $filters = []) {
    $query = "SELECT c.*, t.nom as type_nom, t.prix_base, t.capacite, t.superficie 
              FROM chambres c 
              JOIN types_chambres t ON c.type_id = t.id 
              WHERE 1=1";
    
    if (!empty($filters['statut'])) {
        $query .= " AND c.statut = '" . mysqli_real_escape_string($conn, $filters['statut']) . "'";
    }
    
    if (!empty($filters['type_id'])) {
        $query .= " AND c.type_id = " . intval($filters['type_id']);
    }
    
    $query .= " ORDER BY c.numero ASC";
    
    $result = mysqli_query($conn, $query);
    $rooms = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rooms[] = $row;
    }
    return $rooms;
}

// Fonction pour obtenir l'URL de l'image
function getRoomImage($image_url) {
    if (!empty($image_url)) {
        $image_path = 'uploads/chambres/' . $image_url;
        if (file_exists($image_path)) {
            return $image_path;
        }
    }
    return 'assets/default-room.jpg';
}

// Traitement des filtres
$filters = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filters['statut'] = $_POST['statut'] ?? '';
    $filters['type_id'] = $_POST['type_id'] ?? '';
}

// Récupération des données
$stats = getRoomStats($conn);
$roomTypes = getRoomTypes($conn);
$rooms = getRoomsWithTypes($conn, $filters);

// Fonctions d'aide
function getStatusClass($status) {
    $classes = [
        'disponible' => 'status-available',
        'occupee' => 'status-occupied',
        'maintenance' => 'status-maintenance',
        'nettoyage' => 'status-warning'
    ];
    return $classes[$status] ?? '';
}

function translateStatus($status) {
    $translations = [
        'disponible' => 'Disponible',
        'occupee' => 'Occupée',
        'maintenance' => 'Maintenance',
        'nettoyage' => 'Nettoyage'
    ];
    return $translations[$status] ?? $status;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chambres - HôtelLuxe</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<style>
    :root {
      --bleu-marine: #0a1f38;
      --bleu-clair: #1e4a8e;
      --or: #d4af37;
      --blanc: #ffffff;
      --gris-clair: #f8f9fa;
      --texte: #2d3748;
      --success: #48bb78;
      --warning: #DD6B20;
      --error: #E53E3E;
      --ombre: 0 10px 30px rgba(0, 0, 0, 0.08);
      --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background-color: var(--gris-clair);
      color: var(--texte);
    }

    /* Header identique aux autres pages */
    header {
      background: linear-gradient(135deg, var(--bleu-marine), var(--bleu-clair));
      padding: 1.2rem 2.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: var(--blanc);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
      font-size: 2rem;
      color: var(--or);
    }

    .logo-text {
      font-size: 1.6rem;
      font-weight: 600;
      letter-spacing: 0.5px;
    }

    .logo-text span {
      font-weight: 300;
      opacity: 0.9;
    }

    .user-menu {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .notifications {
      position: relative;
      cursor: pointer;
    }

    .notification-badge {
      position: absolute;
      top: -6px;
      right: -6px;
      background-color: #e53e3e;
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
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background-color: var(--or);
      color: var(--bleu-marine);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 1.1rem;
      cursor: pointer;
      transition: var(--transition);
    }

    .user-avatar:hover {
      transform: scale(1.05);
    }

    /* Contenu principal */
    .main-content {
      max-width: 1400px;
      margin: 2rem auto;
      padding: 0 2.5rem;
    }

    /* Titre de page */
    .page-title {
      font-size: 1.8rem;
      font-weight: 600;
      margin: 2rem 0 1.5rem;
      color: var(--bleu-marine);
      position: relative;
      display: inline-block;
    }

    .page-title::after {
      content: '';
      position: absolute;
      bottom: -8px;
      left: 0;
      width: 60px;
      height: 4px;
      background: var(--or);
      border-radius: 2px;
    }

    /* Filtres */
    .filters-container {
      background: var(--blanc);
      padding: 1.5rem;
      border-radius: 16px;
      margin-bottom: 2rem;
      box-shadow: var(--ombre);
    }

    .filter-row {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .filter-group {
      flex: 1;
    }

    .filter-label {
      display: block;
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
      color: var(--texte);
    }

    .filter-input {
      width: 100%;
      padding: 0.8rem 1rem;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      font-size: 0.95rem;
      background-color: var(--gris-clair);
    }

    /* Statistiques rapides */
    .quick-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: var(--blanc);
      padding: 1.5rem;
      border-radius: 16px;
      box-shadow: var(--ombre);
      text-align: center;
    }

    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: var(--bleu-clair);
      margin-bottom: 0.5rem;
    }

    .stat-label {
      font-size: 0.9rem;
      color: #718096;
    }

    /* Liste des chambres */
    .rooms-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.5rem;
      margin-bottom: 3rem;
    }

    .room-card {
      background: var(--blanc);
      border-radius: 16px;
      overflow: hidden;
      box-shadow: var(--ombre);
      transition: var(--transition);
    }

    .room-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
    }

    .room-image {
      height: 200px;
      background-size: cover;
      background-position: center;
      position: relative;
    }

    .room-status {
      position: absolute;
      top: 1rem;
      right: 1rem;
      padding: 0.3rem 0.8rem;
      border-radius: 12px;
      font-size: 0.85rem;
      font-weight: 500;
      background-color: var(--blanc);
    }

    .status-available {
      color: var(--success);
    }

    .status-occupied {
      color: var(--error);
    }

    .status-maintenance {
      color: var(--warning);
    }

    .room-details {
      padding: 1.5rem;
    }

    .room-type {
      font-size: 1.2rem;
      font-weight: 600;
      color: var(--bleu-marine);
      margin-bottom: 0.5rem;
    }

    .room-number {
      display: inline-block;
      background-color: var(--bleu-clair);
      color: var(--blanc);
      padding: 0.3rem 0.8rem;
      border-radius: 12px;
      font-size: 0.9rem;
      margin-bottom: 1rem;
    }

    .room-features {
      display: flex;
      flex-wrap: wrap;
      gap: 0.8rem;
      margin-bottom: 1.5rem;
    }

    .feature {
      display: flex;
      align-items: center;
      gap: 0.3rem;
      font-size: 0.9rem;
    }

    .feature i {
      color: var(--or);
    }

    .room-price {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--bleu-clair);
      margin-bottom: 1.5rem;
    }

    .room-price span {
      font-size: 0.9rem;
      font-weight: 400;
      color: #718096;
    }

    .room-actions {
      display: flex;
      gap: 0.8rem;
    }

    .action-btn {
      flex: 1;
      padding: 0.7rem;
      border: none;
      border-radius: 8px;
      font-weight: 500;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .primary-btn {
      background-color: var(--bleu-clair);
      color: var(--blanc);
    }

    .primary-btn:hover {
      background-color: var(--bleu-marine);
    }

    .secondary-btn {
      background-color: var(--gris-clair);
      color: var(--bleu-clair);
    }

    .secondary-btn:hover {
      background-color: #E2E8F0;
    }

    /* Bouton flottant */
    .floating-btn {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--bleu-clair), var(--or));
      color: white;
      border: none;
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
      font-size: 1.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 90;
      cursor: pointer;
      transition: var(--transition);
    }

    .floating-btn:hover {
      transform: scale(1.1);
    }

    /* Pied de page identique aux autres pages */
    footer {
      background: linear-gradient(135deg, var(--bleu-marine), var(--bleu-clair));
      color: var(--blanc);
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
    }

    .footer-links a {
      color: var(--blanc);
      text-decoration: none;
      transition: var(--transition);
      opacity: 0.8;
    }

    .footer-links a:hover {
      opacity: 1;
      color: var(--or);
    }

    .copyright {
      margin-top: 1.5rem;
      opacity: 0.7;
      font-size: 0.9rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
      header {
        padding: 1rem 1.5rem;
      }

      .main-content {
        padding: 0 1.5rem;
      }

      .filter-row {
        flex-direction: column;
      }

      .rooms-grid {
        grid-template-columns: 1fr;
      }
    }
    .logo1{
    display: flex;
    align-items: center;
    gap: 12px;
  }
    @media (max-width: 480px) {
      .footer-links {
        flex-direction: column;
        gap: 1rem;
      }

      .room-actions {
        flex-direction: column;
      }
    }
  </style>
<body>
  <header>
    <div class="logo" onclick="window.location.href='accueil.php'">
      <i class="fas fa-hotel logo-icon"></i>
      <div class="logo-text">Hôtel<span>Luxe</span></div>
    </div>
    <div class="user-menu">
      <div class="notifications">
        <i class="fas fa-bell"></i>
        <span class="notification-badge">3</span>
      </div>
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'AD', 0, 2)) ?></div>
    </div>
  </header>

  <div class="main-content">
    <h1 class="page-title">Gestion des Chambres</h1>
    
    <div class="filters-container">
      <form method="POST" action="chambres.php">
        <div class="filter-row">
          <div class="filter-group">
            <label class="filter-label">Statut</label>
            <select class="filter-input" name="statut">
              <option value="">Toutes</option>
              <option value="disponible" <?= ($filters['statut'] ?? '') === 'disponible' ? 'selected' : '' ?>>Disponibles</option>
              <option value="occupee" <?= ($filters['statut'] ?? '') === 'occupee' ? 'selected' : '' ?>>Occupées</option>
              <option value="maintenance" <?= ($filters['statut'] ?? '') === 'maintenance' ? 'selected' : '' ?>>En maintenance</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label">Type</label>
            <select class="filter-input" name="type_id">
              <option value="">Tous types</option>
              <?php foreach ($roomTypes as $id => $type): ?>
                <option value="<?= $id ?>" <?= ($filters['type_id'] ?? '') == $id ? 'selected' : '' ?>>
                  <?= htmlspecialchars($type['nom']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="filter-row">
          <button type="submit" class="action-btn primary-btn">
            <i class="fas fa-filter"></i> Filtrer
          </button>
          <a href="chambres.php" class="action-btn secondary-btn">
            <i class="fas fa-times"></i> Réinitialiser
          </a>
        </div>
      </form>
    </div>

    <div class="quick-stats">
      <div class="stat-card">
        <div class="stat-value"><?= $stats['total'] ?></div>
        <div class="stat-label">Chambres totales</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $stats['available'] ?></div>
        <div class="stat-label">Chambres disponibles</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $stats['occupied'] ?></div>
        <div class="stat-label">Chambres occupées</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $stats['maintenance'] ?></div>
        <div class="stat-label">En maintenance</div>
      </div>
    </div>

    <div class="rooms-grid">
      <?php foreach ($rooms as $room): ?>
        <div class="room-card">
          <div class="room-image" style="background-image: url('<?= getRoomImage($room['image_url'] ?? '') ?>')">
            <span class="room-status <?= getStatusClass($room['statut']) ?>"><?= translateStatus($room['statut']) ?></span>
          </div>
          <div class="room-details">
            <h3 class="room-type"><?= htmlspecialchars($room['type_nom']) ?></h3>
            <span class="room-number">#<?= htmlspecialchars($room['numero']) ?></span>
            <div class="room-features">
              <span class="feature"><i class="fas fa-bed"></i> <?= $room['capacite'] ?> <?= $room['capacite'] > 1 ? 'lits' : 'lit' ?></span>
              <span class="feature"><i class="fas fa-ruler-combined"></i> <?= $room['superficie'] ?>m²</span>
              <span class="feature"><i class="fas fa-wifi"></i> WiFi</span>
            </div>
            <div class="room-price">
              <?= $room['prix_base'] ?>€ <span>/nuit</span>
            </div>
            <div class="room-actions">
              <?php if ($room['statut'] === 'disponible' && ($room['disponible'] ?? 0) == 1): ?>
                <a href="reservation.php?chambre_id=<?= $room['id'] ?>" class="action-btn primary-btn">
                  <i class="fas fa-calendar-check"></i> Réserver
                </a>
              <?php else: ?>
                <button class="action-btn primary-btn" disabled>
                  <i class="fas fa-<?= $room['statut'] === 'occupee' ? 'times-circle' : 'tools' ?>"></i>
                  <?= translateStatus($room['statut']) ?>
                </button>
              <?php endif; ?>
              <a href="details_chambre.php?id=<?= $room['id'] ?>" class="action-btn secondary-btn">
                <i class="fas fa-info-circle"></i> Détails
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      
      <?php if (empty($rooms)): ?>
        <div class="no-rooms">
          <i class="fas fa-door-open"></i>
          <h3>Aucune chambre trouvée</h3>
          <p>Aucune chambre ne correspond à vos critères de recherche.</p>
        </div>
      <?php endif; ?>
    </div>

    <a href="ajouter_chambre.php" class="floating-btn">
      <i class="fas fa-plus"></i>
    </a>
  </div>

  <footer>
    <div class="footer-content">
      <div class="footer-links">
        <a href="accueil.php">Accueil</a>
        <a href="reservation.php">Réservations</a>
        <a href="chambres.php">Chambres</a>
        <a href="contact.php">Contact</a>
        <a href="apropos.php">À propos</a>
        <a href="confidentialite.php">Confidentialité</a>
      </div>
      <div class="copyright">
        &copy; <?= date('Y') ?> HôtelLuxe. Tous droits réservés.
      </div>
    </div>
  </footer>
</body>
</html>