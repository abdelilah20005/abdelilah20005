<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Vérifier si l'ID de réservation est présent
if (!isset($_GET['id'])) {
    $_SESSION['message'] = "Aucune réservation spécifiée";
    $_SESSION['message_type'] = "error";
    header('Location: reservation.php');
    exit();
}

$reservation_id = (int)$_GET['id'];

// Charger les données de la réservation
$stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ?");
$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$reservation = $stmt->get_result()->fetch_assoc();

if (!$reservation) {
    $_SESSION['message'] = "Réservation introuvable";
    $_SESSION['message_type'] = "error";
    header('Location: reservation.php');
    exit();
}

// Charger les services sélectionnés
$selected_services = [];
$stmt = $conn->prepare("SELECT service_id FROM reservation_services WHERE reservation_id = ?");
$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $selected_services[] = $row['service_id'];
}

// Charger les listes pour les selects
$clients = $conn->query("SELECT id, nom, prenom FROM clients ORDER BY nom, prenom");
$services = $conn->query("SELECT * FROM services ORDER BY nom");

// Charger les chambres (disponibles + celle déjà réservée)
$chambres = $conn->query("SELECT ch.id, ch.numero, tc.nom AS type_chambre, tc.prix 
                         FROM chambres ch
                         JOIN types_chambres tc ON ch.type_id = tc.id
                         WHERE ch.disponible = 1 OR ch.id = {$reservation['chambre_id']}
                         ORDER BY ch.numero");

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = (int)$_POST['client_id'];
    $chambre_id = (int)$_POST['chambre_id'];
    $date_arrivee = $_POST['date_arrivee'];
    $date_depart = $_POST['date_depart'];
    $statut = $_POST['statut'];
    $services_ids = isset($_POST['services']) ? array_map('intval', $_POST['services']) : [];

    try {
        $conn->begin_transaction();

        // Convertir les dates au format MySQL
        $date_arrivee_mysql = DateTime::createFromFormat('d/m/Y', $date_arrivee)->format('Y-m-d');
        $date_depart_mysql = DateTime::createFromFormat('d/m/Y', $date_depart)->format('Y-m-d');

        // Calculer le nombre de nuits
        $arrivee_obj = new DateTime($date_arrivee_mysql);
        $depart_obj = new DateTime($date_depart_mysql);
        $nuits = $depart_obj->diff($arrivee_obj)->days;

        if ($nuits <= 0) {
            throw new Exception("La date de départ doit être après la date d'arrivée");
        }

        // Récupérer le prix de la chambre
        $stmt = $conn->prepare("SELECT prix FROM types_chambres tc 
                               JOIN chambres c ON tc.id = c.type_id 
                               WHERE c.id = ?");
        $stmt->bind_param("i", $chambre_id);
        $stmt->execute();
        $chambre_data = $stmt->get_result()->fetch_assoc();
        $prix_total = $nuits * $chambre_data['prix'];

        // Ajouter le prix des services
        if (!empty($services_ids)) {
            $placeholders = implode(',', array_fill(0, count($services_ids), '?'));
            $stmt = $conn->prepare("SELECT SUM(prix) as total FROM services WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($services_ids)), ...$services_ids);
            $stmt->execute();
            $services_total = $stmt->get_result()->fetch_assoc()['total'];
            $prix_total += $services_total;
        }

        // Mettre à jour la réservation
        $stmt = $conn->prepare("UPDATE reservations SET 
                               client_id = ?,
                               chambre_id = ?,
                               date_arrivee = ?,
                               date_depart = ?,
                               statut = ?,
                               nuits = ?,
                               prix_total = ?
                               WHERE id = ?");
        $stmt->bind_param("iisssidi", 
            $client_id, 
            $chambre_id, 
            $date_arrivee_mysql, 
            $date_depart_mysql, 
            $statut, 
            $nuits, 
            $prix_total, 
            $reservation_id
        );
        $stmt->execute();

        // Gérer le changement de chambre si nécessaire
        if ($reservation['chambre_id'] != $chambre_id) {
            // Libérer l'ancienne chambre
            $stmt = $conn->prepare("UPDATE chambres SET disponible = 1 WHERE id = ?");
            $stmt->bind_param("i", $reservation['chambre_id']);
            $stmt->execute();

            // Réserver la nouvelle chambre
            $stmt = $conn->prepare("UPDATE chambres SET disponible = 0 WHERE id = ?");
            $stmt->bind_param("i", $chambre_id);
            $stmt->execute();
        }

        // Mettre à jour les services
        $conn->query("DELETE FROM reservation_services WHERE reservation_id = $reservation_id");
        
        if (!empty($services_ids)) {
            $stmt = $conn->prepare("INSERT INTO reservation_services (reservation_id, service_id) VALUES (?, ?)");
            foreach ($services_ids as $service_id) {
                $stmt->bind_param("ii", $reservation_id, $service_id);
                $stmt->execute();
            }
        }

        $conn->commit();
        
        $_SESSION['message'] = "Réservation #{$reservation['reference']} mise à jour avec succès";
        $_SESSION['message_type'] = "success";
        header("Location: reservation_details.php?id=$reservation_id");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "Erreur lors de la mise à jour: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Réservation - HôtelLuxe</title>
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
            padding: 0;
        }
        
        .container {
            max-width: 1000px;
            margin: 20px auto;
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
        
        select, input {
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
        
        .price-summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Modifier Réservation #<?= htmlspecialchars($reservation['reference']) ?></h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message_type'] ?>">
                <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="client_id">Client</label>
                <select id="client_id" name="client_id" required>
                    <option value="">Sélectionner un client</option>
                    <?php while ($client = $clients->fetch_assoc()): ?>
                        <option value="<?= $client['id'] ?>" 
                            <?= ($client['id'] == $reservation['client_id']) ? 'selected' : '' ?>>
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
                        <option value="<?= $chambre['id'] ?>" 
                            data-price="<?= $chambre['prix'] ?>"
                            <?= ($chambre['id'] == $reservation['chambre_id']) ? 'selected' : '' ?>>
                            Chambre <?= $chambre['numero'] ?> (<?= $chambre['type_chambre'] ?> - <?= number_format($chambre['prix'], 2) ?>€/nuit)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Dates</label>
                <div class="date-group">
                    <div>
                        <label for="date_arrivee">Arrivée</label>
                        <input type="text" id="date_arrivee" name="date_arrivee" 
                               value="<?= htmlspecialchars(DateTime::createFromFormat('Y-m-d', $reservation['date_arrivee'])->format('d/m/Y')) ?>" required>
                    </div>
                    <div>
                        <label for="date_depart">Départ</label>
                        <input type="text" id="date_depart" name="date_depart" 
                               value="<?= htmlspecialchars(DateTime::createFromFormat('Y-m-d', $reservation['date_depart'])->format('d/m/Y')) ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="statut">Statut</label>
                <select id="statut" name="statut" required>
                    <option value="confirmee" <?= ($reservation['statut'] == 'confirmee') ? 'selected' : '' ?>>Confirmée</option>
                    <option value="attente" <?= ($reservation['statut'] == 'attente') ? 'selected' : '' ?>>En attente</option>
                    <option value="annulee" <?= ($reservation['statut'] == 'annulee') ? 'selected' : '' ?>>Annulée</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Services supplémentaires</label>
                <div class="services-grid">
                    <?php 
                    $services->data_seek(0); // Réinitialiser le pointeur
                    while ($service = $services->fetch_assoc()): 
                    ?>
                        <div class="service-item">
                            <input type="checkbox" name="services[]" value="<?= $service['id'] ?>" 
                                   id="service_<?= $service['id'] ?>"
                                   <?= in_array($service['id'], $selected_services) ? 'checked' : '' ?>>
                            <label for="service_<?= $service['id'] ?>" style="display: inline; font-weight: normal;">
                                <?= htmlspecialchars($service['nom']) ?> (+<?= number_format($service['prix'], 2) ?>€)
                            </label>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <div class="price-summary">
                Prix actuel: <?= number_format($reservation['prix_total'], 2) ?>€ pour <?= $reservation['nuits'] ?> nuit(s)
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-save"></i> Mettre à jour
            </button>
            <a href="reservation_details.php?id=<?= $reservation_id ?>" class="btn" style="background: var(--secondary); color: white;">
                <i class="fas fa-times"></i> Annuler
            </a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
    <script>
        // Configuration des datepickers
        flatpickr("#date_arrivee", {
            locale: "fr",
            dateFormat: "d/m/Y",
            minDate: "today"
        });
        
        flatpickr("#date_depart", {
            locale: "fr",
            dateFormat: "d/m/Y",
            minDate: "today"
        });

        // Calcul du prix en temps réel
        function calculatePrice() {
            const arrivee = document.getElementById('date_arrivee').value;
            const depart = document.getElementById('date_depart').value;
            const chambreSelect = document.getElementById('chambre_id');
            const priceDisplay = document.querySelector('.price-summary');
            
            if (arrivee && depart && chambreSelect.value) {
                // Calcul des nuits
                const arriveeDate = new Date(arrivee.split('/').reverse().join('-'));
                const departDate = new Date(depart.split('/').reverse().join('-'));
                const diffTime = departDate - arriveeDate;
                const nuits = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if (nuits > 0) {
                    const prixNuit = parseFloat(chambreSelect.options[chambreSelect.selectedIndex].dataset.price);
                    let total = nuits * prixNuit;
                    
                    // Ajouter le prix des services sélectionnés
                    document.querySelectorAll('input[name="services[]"]:checked').forEach(checkbox => {
                        const priceText = checkbox.nextElementSibling.textContent.match(/\+([\d.]+)€/)[1];
                        total += parseFloat(priceText);
                    });
                    
                    priceDisplay.textContent = `Nouveau prix: ${total.toFixed(2)}€ pour ${nuits} nuit(s)`;
                } else {
                    priceDisplay.textContent = "Dates invalides - vérifiez les dates saisies";
                }
            }
        }

        // Écouteurs d'événements
        document.getElementById('date_arrivee').addEventListener('change', calculatePrice);
        document.getElementById('date_depart').addEventListener('change', calculatePrice);
        document.getElementById('chambre_id').addEventListener('change', calculatePrice);
        document.querySelectorAll('input[name="services[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', calculatePrice);
        });
    </script>
</body>
</html>