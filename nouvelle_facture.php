<?php
require_once 'db.php';
session_start();

// Vérifier que l'utilisateur est connecté et a les droits
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupérer les listes depuis la base
$clients = $conn->query("SELECT id, CONCAT(nom, ' ', prenom) AS name FROM clients ORDER BY nom")->fetch_all(MYSQLI_ASSOC);
$services = $conn->query("SELECT id, nom, prix FROM services")->fetch_all(MYSQLI_ASSOC);

// Récupérer les statuts possibles depuis la structure de la table
$statutsQuery = $conn->query("SHOW COLUMNS FROM factures LIKE 'statut_paiement'");
$statutsRow = $statutsQuery->fetch_assoc();
preg_match("/enum\(\'(.*)\'\)/", $statutsRow['Type'], $matches);
$statutsPossibles = explode("','", $matches[1]);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $requiredFields = ['client_id', 'reservation_id', 'date_emission', 'statut_paiement'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $error = "Le champ " . ucfirst(str_replace('_', ' ', $field)) . " est requis";
            break;
        }
    }

    if (!isset($error)) {
        $reservationId = (int)$_POST['reservation_id'];
        $clientId = (int)$_POST['client_id'];
        $dateEmission = $_POST['date_emission'];
        $statutPaiement = $_POST['statut_paiement'];
        $notes = $_POST['notes'] ?? '';
        $servicesSelected = $_POST['services'] ?? [];

        // Vérifier que la réservation appartient bien au client
        $checkReservation = $conn->prepare("SELECT id FROM reservations WHERE id = ? AND client_id = ?");
        $checkReservation->bind_param('ii', $reservationId, $clientId);
        $checkReservation->execute();
        
        if ($checkReservation->get_result()->num_rows === 0) {
            $error = "La réservation sélectionnée n'existe pas ou ne correspond pas au client";
        } elseif (!in_array($statutPaiement, $statutsPossibles)) {
            $error = "Statut de paiement invalide";
        } else {
            // Calcul du total
            $subtotal = 0;
            $serviceDetails = [];
            foreach ($servicesSelected as $serviceId => $quantity) {
                $quantity = (int)$quantity;
                if ($quantity > 0) {
                    foreach ($services as $service) {
                        if ($service['id'] == $serviceId) {
                            $subtotal += $service['prix'] * $quantity;
                            $serviceDetails[] = [
                                'id' => $serviceId,
                                'quantity' => $quantity,
                                'price' => $service['prix']
                            ];
                            break;
                        }
                    }
                }
            }

            $taxe = $subtotal * 0.10;
            $total = $subtotal + $taxe;

            // Génération du numéro de facture
            $numero = 'FAC-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Transaction pour garantir l'intégrité des données
            $conn->begin_transaction();

            try {
                // Insertion de la facture
                $insertFacture = $conn->prepare("INSERT INTO factures (
                    numero, reservation_id, date_emission, date_echeance, 
                    montant_total, taxe, statut_paiement, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                $dateEcheance = date('Y-m-d', strtotime($dateEmission . ' +15 days'));
                $insertFacture->bind_param(
                    'sisssdss',
                    $numero,
                    $reservationId,
                    $dateEmission,
                    $dateEcheance,
                    $total,
                    $taxe,
                    $statutPaiement,
                    $notes
                );

                if (!$insertFacture->execute()) {
                    throw new Exception("Erreur lors de la création de la facture: " . $conn->error);
                }

                $factureId = $conn->insert_id;

                // Insertion des services
                foreach ($serviceDetails as $service) {
                    $insertService = $conn->prepare("INSERT INTO reservation_services (
                        reservation_id, service_id, quantite, prix, date_service
                    ) VALUES (?, ?, ?, ?, NOW())");
                    $insertService->bind_param('iiid', 
                        $reservationId,
                        $service['id'],
                        $service['quantity'],
                        $service['price']
                    );

                    if (!$insertService->execute()) {
                        throw new Exception("Erreur lors de l'ajout du service: " . $conn->error);
                    }
                }

                $conn->commit();
                header("Location: facturation.php?success=1&id=$factureId");
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouvelle Facture - HôtelLuxe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reprenez le même CSS que facturation.php */
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
            padding: 2rem;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--ombre);
        }

        h1 {
            color: var(--bleu-marine);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        select, input, textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .service-card {
            border: 1px solid #eee;
            padding: 1rem;
            border-radius: 8px;
        }

        .service-card h3 {
            margin-bottom: 0.5rem;
            color: var(--bleu-clair);
        }

        .service-card p {
            margin-bottom: 0.5rem;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quantity-control input {
            width: 60px;
            text-align: center;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--bleu-clair), var(--or));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .error {
            color: var(--error);
            margin-bottom: 1rem;
        }

        .success {
            color: var(--success);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-file-invoice"></i> Nouvelle Facture</h1>

        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="client_id">Client</label>
                <select id="client_id" name="client_id" required>
                    <option value="">Sélectionnez un client</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <div class="form-group">
                <label for="statut_paiement">Statut de paiement</label>
                <select id="statut_paiement" name="statut_paiement" required>
                    <?php foreach ($statutsPossibles as $statut): ?>
                        <option value="<?= $statut ?>">
                            <?= ucfirst($statut) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="reservation_id">Réservation</label>
                <select id="reservation_id" name="reservation_id" required>
                    <option value="">Sélectionnez d'abord un client</option>
                </select>
            </div>

            <div class="form-group">
                <label for="date_emission">Date de facturation</label>
                <input type="date" id="date_emission" name="date_emission" required 
                       value="<?= date('Y-m-d') ?>">
            </div>

            <h2>Services</h2>
            <div class="services-grid">
                <?php foreach ($services as $service): ?>
                    <div class="service-card">
                        <h3><?= htmlspecialchars($service['nom']) ?></h3>
                        <p>Prix: <?= number_format($service['prix'], 2, ',', ' ') ?>€</p>
                        <div class="quantity-control">
                            <label>Quantité:</label>
                            <input type="number" name="services[<?= $service['id'] ?>]" 
                                   min="0" value="0" class="quantity-input">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="form-group">
                <label for="notes">Remarques</label>
                <textarea id="notes" name="notes" rows="3"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Enregistrer la facture
            </button>
        </form>
    </div>

    <script>
        // Chargement dynamique des réservations
        document.getElementById('client_id').addEventListener('change', function() {
            const clientId = this.value;
            const reservationSelect = document.getElementById('reservation_id');
            
            if (!clientId) {
                reservationSelect.innerHTML = '<option value="">Sélectionnez d\'abord un client</option>';
                return;
            }

            fetch(`get_reservations.php?client_id=${clientId}`)
                .then(response => response.json())
                .then(reservations => {
                    let options = '<option value="">Sélectionnez une réservation</option>';
                    
                    reservations.forEach(reservation => {
                        options += `<option value="${reservation.id}">
                            ${reservation.reference} (${reservation.date_arrivee} - ${reservation.date_depart})
                        </option>`;
                    });
                    
                    reservationSelect.innerHTML = options;
                })
                .catch(error => {
                    console.error('Error:', error);
                    reservationSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                });
        });

        // Calcul automatique des quantités
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                if (this.value < 0) this.value = 0;
            });
        });
    </script>
</body>
</html>