<?php
session_start();
require_once 'db.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Récupération des types de chambres
function getRoomTypes($conn) {
    $types = [];
    $query = "SELECT * FROM types_chambres";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $types[] = $row;
    }
    return $types;
}

$roomTypes = getRoomTypes($conn);
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero = mysqli_real_escape_string($conn, $_POST['numero']);
    $statut = mysqli_real_escape_string($conn, $_POST['statut']);
    $type_id = intval($_POST['type_id']);
    $disponible = isset($_POST['disponible']) ? 1 : 0;
    $etage = intval($_POST['etage'] ?? 0);

    // Traitement de l'image
    $image_name = '';
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "uploads/chambres/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $target_dir . $image_name;
        
        // Vérification du type de fichier
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($imageFileType, $allowed_types)) {
            move_uploaded_file($_FILES['image']['tmp_name'], $target_file);
        } else {
            $error = "Seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés.";
        }
    }

    if (empty($error)) {
        $query = "INSERT INTO chambres (numero, statut, disponible, type_id, image_url, etage) 
                  VALUES ('$numero', '$statut', $disponible, $type_id, '$image_name', $etage)";

        if (mysqli_query($conn, $query)) {
            header("Location: chambres.php?success=1");
            exit();
        } else {
            $error = "Erreur lors de l'ajout de la chambre: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter Chambre - HôtelLuxe</title>
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
            line-height: 1.6;
        }

        /* Header */
        header {
            background: linear-gradient(135deg, var(--bleu-marine), var(--bleu-clair));
            padding: 1.2rem 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--blanc);
            box-shadow: var(--ombre);
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
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2.5rem;
        }

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

        /* Formulaire */
        .form-container {
            background: var(--blanc);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--ombre);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--bleu-marine);
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            font-size: 0.95rem;
            background-color: var(--gris-clair);
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--bleu-clair);
            box-shadow: 0 0 0 3px rgba(30, 74, 142, 0.1);
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            accent-color: var(--bleu-clair);
        }

        .error-message {
            color: var(--error);
            background-color: rgba(229, 62, 62, 0.1);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .buttons-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
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
        @media (max-width: 768px) {
            header {
                padding: 1rem 1.5rem;
            }

            .main-content {
                padding: 0 1.5rem;
            }

            .buttons-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
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
        <h1 class="page-title">Ajouter une chambre</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="numero" class="form-label">Numéro de chambre *</label>
                    <input type="text" id="numero" name="numero" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="etage" class="form-label">Étage *</label>
                    <input type="number" id="etage" name="etage" class="form-control" min="0" required>
                </div>

                <div class="form-group">
                    <label for="type_id" class="form-label">Type de chambre *</label>
                    <select id="type_id" name="type_id" class="form-control form-select" required>
                        <option value="">Sélectionnez un type</option>
                        <?php foreach ($roomTypes as $type): ?>
                            <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="statut" class="form-label">Statut *</label>
                    <select id="statut" name="statut" class="form-control form-select" required>
                        <option value="disponible">Disponible</option>
                        <option value="occupee">Occupée</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="nettoyage">Nettoyage</option>
                    </select>
                </div>

                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" id="disponible" name="disponible" class="form-check-input" checked>
                        <label for="disponible" class="form-label">Disponible à la réservation</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="image" class="form-label">Image de la chambre</label>
                    <input type="file" id="image" name="image" class="form-control">
                </div>

                <div class="buttons-group">
                    <a href="chambres.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-links">
                <a href="accueil.php">Accueil</a>
                <a href="chambres.php">Chambres</a>
                <a href="reservation.php">Réservations</a>
                <a href="contact.php">Contact</a>
            </div>
            <div class="copyright">
                &copy; <?= date('Y') ?> HôtelLuxe. Tous droits réservés.
            </div>
        </div>
    </footer>
</body>
</html>
