<?php
session_start();
require_once 'db.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Vérification de l'ID de chambre
if (!isset($_GET['id'])) {
    header("Location: chambres.php");
    exit();
}

$room_id = intval($_GET['id']);

// Récupération des détails de la chambre
$query = "SELECT c.*, t.nom as type_nom, t.prix_base, t.capacite, t.superficie, t.description as type_description 
          FROM chambres c 
          JOIN types_chambres t ON c.type_id = t.id 
          WHERE c.id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $room_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$room = mysqli_fetch_assoc($result);

if (!$room) {
    header("Location: chambres.php?error=chambre_non_trouvee");
    exit();
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

// Traduction du statut
function translateStatus($status) {
    $translations = [
        'disponible' => 'Disponible',
        'occupee' => 'Occupée',
        'maintenance' => 'Maintenance',
        'nettoyage' => 'Nettoyage'
    ];
    return $translations[$status] ?? $status;
}

// Classe CSS pour le statut
function getStatusClass($status) {
    $classes = [
        'disponible' => 'status-available',
        'occupee' => 'status-occupied',
        'maintenance' => 'status-maintenance',
        'nettoyage' => 'status-warning'
    ];
    return $classes[$status] ?? '';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Détails de la chambre - HôtelLuxe</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

    /* Header */
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
      cursor: pointer;
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
      max-width: 1200px;
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

    /* Détails de la chambre */
    .room-detail-container {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2rem;
      margin-bottom: 3rem;
    }

    .room-image-container {
      position: relative;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: var(--ombre);
      height: 400px;
    }

    .room-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .room-status {
      position: absolute;
      top: 1rem;
      right: 1rem;
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-size: 0.9rem;
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

    .room-info {
      background: var(--blanc);
      padding: 2rem;
      border-radius: 16px;
      box-shadow: var(--ombre);
    }

    .room-type {
      font-size: 1.5rem;
      font-weight: 600;
      color: var(--bleu-marine);
      margin-bottom: 0.5rem;
    }

    .room-number {
      display: inline-block;
      background-color: var(--bleu-clair);
      color: var(--blanc);
      padding: 0.3rem 1rem;
      border-radius: 20px;
      font-size: 1rem;
      margin-bottom: 1.5rem;
    }

    .room-description {
      margin-bottom: 2rem;
      line-height: 1.7;
    }

    .room-features {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .feature {
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }

    .feature-icon {
      width: 40px;
      height: 40px;
      background-color: rgba(30, 74, 142, 0.1);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--bleu-clair);
    }

    .feature-text strong {
      display: block;
      margin-bottom: 0.2rem;
    }

    .feature-text span {
      font-size: 0.9rem;
      color: #718096;
    }

    .room-price {
      font-size: 1.8rem;
      font-weight: 700;
      color: var(--bleu-clair);
      margin: 2rem 0;
    }

    .room-price span {
      font-size: 1rem;
      font-weight: 400;
      color: #718096;
    }

    .room-actions {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
    }

    .btn {
      padding: 0.8rem 1.5rem;
      border: none;
      border-radius: 8px;
      font-weight: 500;
      cursor: pointer;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .btn-primary {
      background-color: var(--bleu-clair);
      color: var(--blanc);
    }

    .btn-primary:hover {
      background-color: var(--bleu-marine);
    }

    .btn-secondary {
      background-color: var(--gris-clair);
      color: var(--bleu-clair);
    }

    .btn-secondary:hover {
      background-color: #E2E8F0;
    }

    /* Pied de page */
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
    @media (max-width: 992px) {
      .room-detail-container {
        grid-template-columns: 1fr;
      }
      
      .room-image-container {
        height: 300px;
      }
    }

    @media (max-width: 768px) {
      header {
        padding: 1rem 1.5rem;
      }

      .main-content {
        padding: 0 1.5rem;
      }

      .room-features {
        grid-template-columns: 1fr;
      }

      .room-actions {
        flex-direction: column;
      }
    }

    @media (max-width: 480px) {
      .footer-links {
        flex-direction: column;
        gap: 1rem;
      }
    }
  </style>
</head>
<body>
  <header>
    <div class="logo" onclick="window.location.href='accueil.php'">
      <i class="fas fa-hotel logo-icon"></i>
      <div class="logo-text">Hôtel<span>Luxe</span></div>
    </div>
    <div class="user-menu">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'AD', 0, 2)) ?></div>
    </div>
  </header>

  <div class="main-content">
    <h1 class="page-title">Détails de la chambre</h1>
    
    <div class="room-detail-container">
      <div class="room-image-container">
        <img src="<?= getRoomImage($room['image_url'] ?? '') ?>" alt="Chambre <?= htmlspecialchars($room['numero']) ?>" class="room-image">
        <span class="room-status <?= getStatusClass($room['statut']) ?>"><?= translateStatus($room['statut']) ?></span>
      </div>
      
      <div class="room-info">
        <h2 class="room-type"><?= htmlspecialchars($room['type_nom']) ?></h2>
        <span class="room-number">#<?= htmlspecialchars($room['numero']) ?></span>
        
        <p class="room-description"><?= htmlspecialchars($room['type_description']) ?></p>
        
        <div class="room-features">
          <div class="feature">
            <div class="feature-icon">
              <i class="fas fa-bed"></i>
            </div>
            <div class="feature-text">
              <strong>Capacité</strong>
              <span><?= $room['capacite'] ?> <?= $room['capacite'] > 1 ? 'personnes' : 'personne' ?></span>
            </div>
          </div>
          
          <div class="feature">
            <div class="feature-icon">
              <i class="fas fa-ruler-combined"></i>
            </div>
            <div class="feature-text">
              <strong>Superficie</strong>
              <span><?= $room['superficie'] ?> m²</span>
            </div>
          </div>
          
          <div class="feature">
            <div class="feature-icon">
              <i class="fas fa-wifi"></i>
            </div>
            <div class="feature-text">
              <strong>WiFi</strong>
              <span>Gratuit</span>
            </div>
          </div>
          
          <div class="feature">
            <div class="feature-icon">
              <i class="fas fa-tv"></i>
            </div>
            <div class="feature-text">
              <strong>Télévision</strong>
              <span>Écran plat</span>
            </div>
          </div>
          
          <div class="feature">
            <div class="feature-icon">
              <i class="fas fa-snowflake"></i>
            </div>
            <div class="feature-text">
              <strong>Climatisation</strong>
              <span>Incluse</span>
            </div>
          </div>
          
          <div class="feature">
            <div class="feature-icon">
              <i class="fas fa-umbrella-beach"></i>
            </div>
            <div class="feature-text">
              <strong>Vue</strong>
              <span><?= $room['etage'] > 3 ? 'Panoramique' : 'Sur la ville' ?></span>
            </div>
          </div>
        </div>
        
        <div class="room-price">
          <?= $room['prix_base'] ?>€ <span>/nuit</span>
        </div>
        
        <div class="room-actions">
          <?php if ($room['statut'] === 'disponible' && ($room['disponible'] ?? 0) == 1): ?>
            <a href="reservation.php?chambre_id=<?= $room['id'] ?>" class="btn btn-primary">
              <i class="fas fa-calendar-check"></i> Réserver maintenant
            </a>
          <?php else: ?>
            <button class="btn btn-primary" disabled>
              <i class="fas fa-<?= $room['statut'] === 'occupee' ? 'times-circle' : 'tools' ?>"></i>
              <?= translateStatus($room['statut']) ?>
            </button>
          <?php endif; ?>
          <a href="chambres.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour aux chambres
          </a>
        </div>
      </div>
    </div>
  </div>

  <footer>
    <div class="footer-content">
      <div class="footer-links">
        <a href="accueil.php">Accueil</a>
        <a href="reservation.php">Réservations</a>
        <a href="chambres.php">Chambres</a>
        <a href="contact.php">Contact</a>
        <a href="apropos.php">À propos</a>
      </div>
      <div class="copyright">
        &copy; <?= date('Y') ?> HôtelLuxe. Tous droits réservés.
      </div>
    </div>
  </footer>
</body>
</html>