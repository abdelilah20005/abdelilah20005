<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

function convertDateToMySQL($date_fr) {
    $date_obj = DateTime::createFromFormat('d/m/Y', $date_fr);
    return $date_obj ? $date_obj->format('Y-m-d') : false;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = (int)$_POST['client_id'];
    $chambre_id = (int)$_POST['chambre_id'];
    $date_arrivee_fr = $_POST['date_arrivee'];
    $date_depart_fr = $_POST['date_depart'];
    $statut = $_POST['statut'];
    $services_ids = isset($_POST['services']) ? array_map('intval', $_POST['services']) : [];

    try {
        $date_arrivee = convertDateToMySQL($date_arrivee_fr);
        $date_depart = convertDateToMySQL($date_depart_fr);
        
        if (!$date_arrivee || !$date_depart) {
            throw new Exception("Format de date invalide (utiliser JJ/MM/AAAA)");
        }

        $arrivee_obj = new DateTime($date_arrivee);
        $depart_obj = new DateTime($date_depart);
        $nuits = $depart_obj->diff($arrivee_obj)->days;
        
        if ($nuits <= 0) {
            throw new Exception("La date de départ doit être après l'arrivée");
        }

        // Démarrer la transaction
        $conn->begin_transaction();

        // Vérifier la disponibilité de la chambre
        $disponible = $conn->query("SELECT disponible FROM chambres WHERE id = $chambre_id")->fetch_assoc()['disponible'];
        if (!$disponible) {
            throw new Exception("La chambre sélectionnée n'est plus disponible");
        }

        // Prix de la chambre
        $stmt = $conn->prepare("SELECT tc.prix FROM types_chambres tc 
                               JOIN chambres c ON tc.id = c.type_id 
                               WHERE c.id = ?");
        $stmt->bind_param("i", $chambre_id);
        $stmt->execute();
        $chambre_data = $stmt->get_result()->fetch_assoc();
        $prix_total = $nuits * $chambre_data['prix'];

        // Ajout des services
        if (!empty($services_ids)) {
            $placeholders = implode(',', array_fill(0, count($services_ids), '?'));
            $types = str_repeat('i', count($services_ids));
            
            $stmt = $conn->prepare("SELECT SUM(prix) as total FROM services WHERE id IN ($placeholders)");
            
            $params = array_merge([$types], $services_ids);
            $refs = [];
            foreach ($params as $key => $value) {
                $refs[$key] = &$params[$key];
            }
            
            call_user_func_array([$stmt, 'bind_param'], $refs);
            $stmt->execute();
            
            $services_total = $stmt->get_result()->fetch_assoc()['total'];
            $prix_total += $services_total ?: 0;
        }

        // Génération de la référence UNIQUE
        $year = date('Y');
        $result = $conn->query("SELECT MAX(CAST(SUBSTRING_INDEX(reference, '-', -1) AS UNSIGNED)) as max_ref 
                               FROM reservations 
                               WHERE reference LIKE 'RES-$year-%'");
        $max_ref = $result->fetch_assoc()['max_ref'];
        $next_ref = ($max_ref ? $max_ref : 0) + 1;
        $reference = sprintf("RES-%s-%04d", $year, $next_ref);

        // Création de la réservation
        $stmt = $conn->prepare("INSERT INTO reservations 
                              (reference, client_id, chambre_id, date_arrivee, date_depart, statut, nuits, prix_total) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siisssid", $reference, $client_id, $chambre_id, $date_arrivee, $date_depart, $statut, $nuits, $prix_total);
        $stmt->execute();
        $reservation_id = $conn->insert_id;

        // Lier les services
        if (!empty($services_ids)) {
            $stmt = $conn->prepare("INSERT INTO reservation_services (reservation_id, service_id) VALUES (?, ?)");
            foreach ($services_ids as $service_id) {
                $service_id = (int)$service_id;
                $stmt->bind_param("ii", $reservation_id, $service_id);
                $stmt->execute();
            }
        }

        // Mettre à jour la disponibilité de la chambre
        $stmt = $conn->prepare("UPDATE chambres SET disponible = 0 WHERE id = ?");
        $stmt->bind_param("i", $chambre_id);
        $stmt->execute();

        $conn->commit();
        
        $_SESSION['flash_message'] = "Réservation #$reference créée avec succès ($nuits nuits, " . number_format($prix_total, 2) . " €)";
        $_SESSION['message_type'] = "success";
        header("Location: reservation_details.php?id=$reservation_id");
        exit();

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        $message = "Erreur: " . $e->getMessage();
        $message_type = "error";
    }
}

// Récupérer les données pour le formulaire
$clients = $conn->query("SELECT id, nom, prenom FROM clients ORDER BY nom, prenom");
$chambres = $conn->query("SELECT ch.id, ch.numero, tc.nom AS type_chambre, tc.prix 
                         FROM chambres ch
                         JOIN types_chambres tc ON ch.type_id = tc.id
                         WHERE ch.disponible = 1
                         ORDER BY ch.numero");
$services = $conn->query("SELECT * FROM services ORDER BY nom");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle Réservation - HôtelLuxe</title>
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
            --danger: #E53E3E;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light);
            color: var(--dark);
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: var(--primary);
            border-bottom: 2px solid var(--accent);
            padding-bottom: 10px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: rgba(72, 187, 120, 0.2);
            border: 1px solid var(--success);
            color: var(--success);
        }
        
        .alert-error {
            background-color: rgba(229, 62, 62, 0.2);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        select, input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn {
            background-color: var(--accent);
            color: var(--primary);
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .service-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border: 1px solid #eee;
            border-radius: 4px;
        }
        
        .date-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Nouvelle Réservation</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['flash_message'] ?>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="client_id">Client</label>
                <select id="client_id" name="client_id" required>
                    <option value="">Sélectionner un client</option>
                    <?php while ($client = $clients->fetch_assoc()): ?>
                        <option value="<?= $client['id'] ?>">
                            <?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="chambre_id">Chambre</label>
                <select id="chambre_id" name="chambre_id" required>
                    <option value="">Sélectionner une chambre</option>
                    <?php while ($chambre = $chambres->fetch_assoc()): ?>
                        <option value="<?= $chambre['id'] ?>" data-price="<?= $chambre['prix'] ?>">
                            <?= htmlspecialchars("Chambre {$chambre['numero']} - {$chambre['type_chambre']} ({$chambre['prix']}€/nuit)") ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Dates</label>
                <div class="date-group">
                    <div>
                        <label for="date_arrivee">Arrivée</label>
                        <input type="text" id="date_arrivee" name="date_arrivee" required>
                    </div>
                    <div>
                        <label for="date_depart">Départ</label>
                        <input type="text" id="date_depart" name="date_depart" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="statut">Statut</label>
                <select id="statut" name="statut" required>
                    <option value="confirmee">Confirmée</option>
                    <option value="attente">En attente</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Services supplémentaires</label>
                <div class="services-grid">
                    <?php while ($service = $services->fetch_assoc()): ?>
                        <div class="service-item">
                            <input type="checkbox" id="service_<?= $service['id'] ?>" name="services[]" value="<?= $service['id'] ?>">
                            <label for="service_<?= $service['id'] ?>" style="display: inline; font-weight: normal;">
                                <?= htmlspecialchars($service['nom']) ?> (+<?= $service['prix'] ?>€)
                            </label>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-save"></i> Enregistrer
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const dateArrivee = document.getElementById('date_arrivee');
    const dateDepart = document.getElementById('date_depart');
    const chambreSelect = document.getElementById('chambre_id');
    const prixDisplay = document.createElement('div');
    prixDisplay.style.marginTop = '10px';
    prixDisplay.style.fontWeight = 'bold';
    chambreSelect.parentNode.appendChild(prixDisplay);
    
    function calculatePrice() {
        if (dateArrivee.value && dateDepart.value && chambreSelect.value) {
            const arrivee = new Date(dateArrivee.value);
            const depart = new Date(dateDepart.value);
            const diffTime = depart - arrivee;
            const nuits = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (nuits > 0) {
                const prixNuit = chambreSelect.options[chambreSelect.selectedIndex].dataset.price;
                const total = nuits * prixNuit;
                prixDisplay.textContent = `Durée: ${nuits} nuit(s) - Prix total: ${total.toFixed(2)}€`;
            } else {
                prixDisplay.textContent = "Dates invalides";
            }
        }
    }
    
    dateArrivee.addEventListener('change', calculatePrice);
    dateDepart.addEventListener('change', calculatePrice);
    chambreSelect.addEventListener('change', calculatePrice);
});
        // Configuration des datepickers
        flatpickr("#date_arrivee", {
            locale: "fr",
            dateFormat: "d/m/Y",
            minDate: "today"
        });
        
        flatpickr("#date_depart", {
            locale: "fr",
            dateFormat: "d/m/Y",
            minDate: new Date().fp_incr(1)
        });
    </script>
</body>
</html>