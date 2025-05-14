<?php
require_once 'db.php';

// Vérifier si l'utilisateur est connecté (à adapter selon votre système d'authentification)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fonctions utilitaires
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function calculateInvoiceTotal($items, $taxRate = 0.10) {
    $subtotal = array_reduce($items, function($sum, $item) {
        return $sum + ($item['unit_price'] * $item['quantity']);
    }, 0);
    
    $tax = $subtotal * $taxRate;
    $total = $subtotal + $tax;
    
    return [
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $total
    ];
}

// Récupérer les factures depuis la base de données
// Modifiez la fonction getInvoices() comme suit :
function getInvoices($conn, $filters = []) {
    $query = "SELECT f.*, 
                     r.client_id,
                     CONCAT(c.nom, ' ', c.prenom) AS client_name,
                     c.adresse AS client_address, 
                     c.ville AS client_city,
                     c.code_postal AS client_zip
              FROM factures f
              JOIN reservations r ON f.reservation_id = r.id
              JOIN clients c ON r.client_id = c.id
              WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Filtres
    if (!empty($filters['status'])) {
        $query .= " AND f.statut_paiement = ?";
        $params[] = $filters['status'];
        $types .= 's';
    }
    
    if (!empty($filters['date_from'])) {
        $query .= " AND f.date_emission >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND f.date_emission <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }
    
    $query .= " ORDER BY f.date_emission DESC";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $invoices = [];
    while ($row = $result->fetch_assoc()) {
        // Vérification que client_id existe bien dans les résultats
        if (!isset($row['client_id'])) {
            error_log("Client ID manquant pour la facture ID: " . $row['id']);
            continue; // On ignore cette facture ou on peut lui attribuer une valeur par défaut
        }
        
        // Récupérer les items de la facture
        $items = getInvoiceItems($conn, $row['id']);
        
        $invoices[] = [
            'id' => $row['id'],
            'number' => $row['numero'],
            'client' => [
                'id' => $row['client_id'],
                'name' => $row['client_name'] ?? 'Client inconnu',
                'address' => $row['client_address'] ?? '',
                'city' => $row['client_city'] ?? '',
                'zip' => $row['client_zip'] ?? ''
            ],
            'date' => $row['date_emission'],
            'due_date' => $row['date_echeance'],
            'status' => $row['statut_paiement'],
            'items' => $items,
            'notes' => $row['notes']
        ];
    }
    
    return $invoices;
}
function getInvoiceItems($conn, $invoiceId) {
    $query = "SELECT rs.*, s.nom AS service_name, s.prix
              FROM reservation_services rs
              JOIN services s ON rs.service_id = s.id
              WHERE rs.reservation_id = (
                  SELECT reservation_id FROM factures WHERE id = ?
              )";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'description' => $row['service_name'],
            'unit_price' => $row['prix'],
            'quantity' => $row['quantite']
        ];
    }
    
    return $items;
}

function getClients($conn) {
    $query = "SELECT id, CONCAT(nom, ' ', prenom) AS name 
              FROM clients 
              ORDER BY nom";
    
    $result = $conn->query($query);
    
    $clients = [];
    while ($row = $result->fetch_assoc()) {
        $clients[] = $row;
    }
    
    return $clients;
}

function createInvoice($conn, $data) {
   
    // Vérifiez d'abord que la réservation existe
    $checkQuery = "SELECT id FROM reservations WHERE id = ? AND client_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param('ii', $data['reservation_id'], $data['client_id']);
    $checkStmt->execute();
    
    if (!$checkStmt->get_result()->num_rows) {
        return false; // Réservation non trouvée ou ne correspond pas au client
    }
    
    // ... reste du code inchangé ...

    // Commencer une transaction
    $conn->begin_transaction();
    
    try {
        // 1. Créer la réservation (facture)
        $query = "INSERT INTO factures (
                    numero, 
                    reservation_id, 
                    date_emission, 
                    date_echeance, 
                    montant_total, 
                    taxe, 
                    statut_paiement, 
                    notes
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $dueDate = date('Y-m-d', strtotime($data['date'] . ' +15 days'));
        $totals = calculateInvoiceTotal($data['items']);
        
        $invoiceNumber = 'FAC-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            'sisssdss', 
            $invoiceNumber,
            $data['reservation_id'],
            $data['date'],
            $dueDate,
            $totals['total'],
            $totals['tax'],
            $data['status'],
            $data['notes']
        );
        $stmt->execute();
        $invoiceId = $conn->insert_id;
        
        // 2. Ajouter les services (items)
        foreach ($data['items'] as $item) {
            $query = "INSERT INTO reservation_services (
                        reservation_id, 
                        service_id, 
                        quantite, 
                        date_service, 
                        notes
                      ) VALUES (?, ?, ?, NOW(), ?)";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                'iiis', 
                $data['reservation_id'],
                $item['service_id'],
                $item['quantity'],
                $item['description']
            );
            $stmt->execute();
        }
        
        // Valider la transaction
        $conn->commit();
        
        return $invoiceId;
    } catch (Exception $e) {
        // Annuler en cas d'erreur
        $conn->rollback();
        error_log("Erreur création facture: " . $e->getMessage());
        return false;
    }
}

// Traitement des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_invoices':
            $filters = [
                'status' => $_GET['status'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null
            ];
            
            echo json_encode(getInvoices($conn, $filters));
            exit;
            
        case 'get_clients':
            echo json_encode(getClients($conn));
            exit;
            
        case 'get_invoice':
            if (isset($_GET['id'])) {
                $invoice = getInvoices($conn, ['id' => (int)$_GET['id']]);
                echo json_encode($invoice[0] ?? null);
            }
            exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'create_invoice') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($invoiceId = createInvoice($conn, $data)) {
            echo json_encode(['success' => true, 'invoice_id' => $invoiceId]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur création facture']);
        }
        exit;
    }
}

// Récupération initiale des données
$statusFilter = $_GET['status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$invoices = getInvoices($conn, [
    'status' => $statusFilter === 'all' ? null : $statusFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo
]);

$clients = getClients($conn);

// Calcul des statistiques
$statsQuery = "SELECT 
                SUM(montant_total) as monthly_revenue,
                SUM(CASE WHEN statut_paiement = 'impaye' THEN montant_total ELSE 0 END) as unpaid_amount,
                COUNT(CASE WHEN statut_paiement = 'impaye' THEN 1 END) as unpaid_count
               FROM factures
               WHERE date_emission BETWEEN ? AND ?";
               
$stmt = $conn->prepare($statsQuery);
$stmt->bind_param('ss', $dateFrom, $dateTo);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Calcul de la moyenne par séjour
$avgQuery = "SELECT AVG(montant_total) as avg_per_stay
             FROM factures f
             JOIN reservations r ON f.reservation_id = r.id
             WHERE f.date_emission BETWEEN ? AND ?";
             
$stmt = $conn->prepare($avgQuery);
$stmt->bind_param('ss', $dateFrom, $dateTo);
$stmt->execute();
$avgStats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Facturation - HôtelLuxe</title>
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

    .user-menu {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    /* Contenu principal */
    .main-content {
      max-width: 1400px;
      margin: 2rem auto;
      padding: 0 2.5rem;
    }

    .page-title {
      font-size: 1.8rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
      color: var(--bleu-marine);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    /* Barre d'outils */
    .tools-bar {
      display: flex;
      justify-content: space-between;
      margin-bottom: 1.5rem;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .filter-container {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .filter-group {
      position: relative;
    }

    .filter-label {
      display: block;
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
      color: var(--texte);
    }

    .filter-input, .filter-select {
      padding: 0.8rem 1rem;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 1rem;
      min-width: 200px;
    }

    .action-buttons {
      display: flex;
      gap: 1rem;
    }

    .btn {
      padding: 0.8rem 1.5rem;
      border: none;
      border-radius: 8px;
      font-weight: 500;
      cursor: pointer;
      display: flex;
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

    .btn-secondary {
      background-color: var(--blanc);
      color: var(--bleu-clair);
      border: 1px solid var(--bleu-clair);
    }

    /* Tableau des factures */
    .table-container {
      background: white;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: var(--ombre);
      margin-bottom: 2rem;
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      padding: 1rem 1.5rem;
      text-align: left;
      border-bottom: 1px solid #EDF2F7;
    }

    th {
      background-color: var(--bleu-marine);
      color: white;
      font-weight: 500;
    }

    tr:hover {
      background-color: #F7FAFC;
    }

    .invoice-status {
      padding: 0.3rem 0.8rem;
      border-radius: 12px;
      font-size: 0.85rem;
      font-weight: 500;
      display: inline-block;
    }

    .status-paid {
      background-color: rgba(56, 161, 105, 0.15);
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

    .action-btn {
      background: none;
      border: none;
      cursor: pointer;
      padding: 0.5rem;
      border-radius: 50%;
      transition: var(--transition);
    }

    .action-btn:hover {
      background-color: rgba(0, 0, 0, 0.05);
    }

    .view-btn {
      color: var(--bleu-clair);
    }

    .print-btn {
      color: var(--or);
    }

    /* Panneau de statistiques */
    .stats-panel {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: white;
      padding: 1.5rem;
      border-radius: 16px;
      box-shadow: var(--ombre);
    }

    .stat-title {
      font-size: 1rem;
      color: #718096;
      margin-bottom: 0.5rem;
    }

    .stat-value {
      font-size: 1.8rem;
      font-weight: 700;
      color: var(--bleu-clair);
    }

    .stat-detail {
      font-size: 0.9rem;
      color: #718096;
      margin-top: 0.5rem;
    }

    /* Modal Facture */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 2000;
      justify-content: center;
      align-items: center;
    }

    .modal-content {
      background: white;
      border-radius: 16px;
      width: 90%;
      max-width: 800px;
      padding: 2rem;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      max-height: 90vh;
      overflow-y: auto;
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      border-bottom: 1px solid #EDF2F7;
      padding-bottom: 1rem;
    }

    .modal-title {
      font-size: 1.5rem;
      font-weight: 600;
      color: var(--bleu-marine);
    }

    .close-btn {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
    }

    .invoice-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 2rem;
    }

    .invoice-info {
      margin-bottom: 2rem;
    }

    .invoice-details {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 2rem;
    }

    .invoice-details th {
      background-color: var(--gris-clair);
      color: var(--texte);
      text-align: left;
      padding: 0.8rem 1rem;
    }

    .invoice-details td {
      padding: 0.8rem 1rem;
      border-bottom: 1px solid #EDF2F7;
    }

    .invoice-total {
      text-align: right;
      margin-top: 1.5rem;
    }

    .total-amount {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--bleu-clair);
    }

    .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 1rem;
      margin-top: 2rem;
    }

    /* Responsive */
    @media (max-width: 1024px) {
      .invoice-header {
        flex-direction: column;
        gap: 1rem;
      }
    }

    @media (max-width: 768px) {
      header {
        padding: 1rem;
      }
      
      .main-content {
        padding: 0 1rem;
      }
      
      .tools-bar {
        flex-direction: column;
      }
      
      .filter-container {
        width: 100%;
      }
      
      .filter-input, .filter-select {
        min-width: 100%;
      }
      
      th, td {
        padding: 0.8rem;
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
      <div class="notifications">
        <i class="fas fa-bell"></i>
      </div>
      <div class="user-avatar">AD</div>
    </div>
  </header>
  <div class="main-content">
    <h1 class="page-title">
  Gestion des Factures
  <a href="nouvelle_facture.php" class="btn btn-primary">
    <i class="fas fa-file-invoice"></i> Nouvelle facture
  </a>
</h1>

    <!-- Panneau de statistiques -->
    <div class="stats-panel">
      <div class="stat-card">
        <div class="stat-title">Chiffre d'affaires ce mois</div>
        <div class="stat-value"><?= number_format($stats['monthly_revenue'] ?? 0, 2, ',', ' ') ?>€</div>
        <div class="stat-detail">
          <?php 
          // Calculer l'évolution vs mois dernier
          $lastMonth = date('Y-m', strtotime('first day of previous month'));
          $lastMonthQuery = "SELECT SUM(montant_total) as revenue 
                            FROM factures 
                            WHERE date_emission BETWEEN ? AND ?";
          
          $stmt = $conn->prepare($lastMonthQuery);
          $firstDay = date('Y-m-01', strtotime($lastMonth));
          $lastDay = date('Y-m-t', strtotime($lastMonth));
          $stmt->bind_param('ss', $firstDay, $lastDay);
          $stmt->execute();
          $lastMonthRevenue = $stmt->get_result()->fetch_assoc()['revenue'] ?? 0;
          
          if ($lastMonthRevenue > 0) {
              $evolution = (($stats['monthly_revenue'] - $lastMonthRevenue) / $lastMonthRevenue) * 100;
              echo sprintf('%s%% vs mois dernier', number_format($evolution, 1));
          }
          ?>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Factures impayées</div>
        <div class="stat-value"><?= number_format($stats['unpaid_amount'] ?? 0, 2, ',', ' ') ?>€</div>
        <div class="stat-detail"><?= $stats['unpaid_count'] ?? 0 ?> factures en attente</div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Moyenne par séjour</div>
        <div class="stat-value"><?= number_format($avgStats['avg_per_stay'] ?? 0, 2, ',', ' ') ?>€</div>
        <div class="stat-detail">
          <?php
          // Calculer la durée moyenne des séjours
          $avgStayQuery = "SELECT AVG(DATEDIFF(r.date_depart, r.date_arrivee)) as avg_nights
                          FROM reservations r
                          JOIN factures f ON r.id = f.reservation_id
                          WHERE f.date_emission BETWEEN ? AND ?";
          
          $stmt = $conn->prepare($avgStayQuery);
          $stmt->bind_param('ss', $dateFrom, $dateTo);
          $stmt->execute();
          $avgNights = $stmt->get_result()->fetch_assoc()['avg_nights'] ?? 0;
          
          echo sprintf('%.1f nuits en moyenne', $avgNights);
          ?>
        </div>
      </div>
    </div>

    <!-- Barre de filtres -->
    <div class="tools-bar">
      <div class="filter-container">
        <div class="filter-group">
          <label class="filter-label">Statut</label>
          <select class="filter-select" id="statusFilter" onchange="filterInvoices()">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
            <option value="paye" <?= $statusFilter === 'paye' ? 'selected' : '' ?>>Payées</option>
            <option value="impaye" <?= $statusFilter === 'impaye' ? 'selected' : '' ?>>En attente</option>
            <option value="annule" <?= $statusFilter === 'annule' ? 'selected' : '' ?>>Annulées</option>
          </select>
        </div>
        <div class="filter-group">
          <label class="filter-label">Date de</label>
          <input type="date" class="filter-input" id="dateFrom" 
                 value="<?= htmlspecialchars($dateFrom) ?>" onchange="filterInvoices()">
        </div>
        <div class="filter-group">
          <label class="filter-label">Date à</label>
          <input type="date" class="filter-input" id="dateTo" 
                 value="<?= htmlspecialchars($dateTo) ?>" onchange="filterInvoices()">
        </div>
      </div>
      <div class="action-buttons">
        <button class="btn btn-secondary" onclick="exportInvoices()">
          <i class="fas fa-file-export"></i> Exporter
        </button>
      </div>
    </div>

    <!-- Tableau des factures -->
    <div class="table-container">
      <table id="invoicesTable">
        <thead>
          <tr>
            <th>N° Facture</th>
            <th>Client</th>
            <th>Date</th>
            <th>Montant</th>
            <th>Statut</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $invoice): 
            $totals = calculateInvoiceTotal($invoice['items']);
            $statusClass = '';
            $statusText = '';
            
            switch($invoice['status']) {
              case 'paye': 
                $statusClass = 'status-paid';
                $statusText = 'Payée';
                break;
              case 'impaye': 
                $statusClass = 'status-pending';
                $statusText = 'En attente';
                break;
              case 'annule': 
                $statusClass = 'status-cancelled';
                $statusText = 'Annulée';
                break;
            }
          ?>
          <tr>
            <td><?= htmlspecialchars($invoice['number']) ?></td>
            <td><?= htmlspecialchars($invoice['client']['name']) ?></td>
            <td><?= formatDate($invoice['date']) ?></td>
            <td><?= number_format($totals['total'], 2, ',', ' ') ?>€</td>
            <td><span class="invoice-status <?= $statusClass ?>"><?= $statusText ?></span></td>
            <td>
              <button class="action-btn view-btn" onclick="viewInvoice(<?= $invoice['id'] ?>)">
                <i class="fas fa-eye"></i>
              </button>
              <button class="action-btn print-btn" onclick="printInvoice(<?= $invoice['id'] ?>)">
                <i class="fas fa-print"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- [MODALS restent identiques mais avec adaptation pour le PHP] -->

  <script>
    // Fonctions JavaScript adaptées pour utiliser le backend PHP
    function filterInvoices() {
      const status = document.getElementById('statusFilter').value;
      const dateFrom = document.getElementById('dateFrom').value;
      const dateTo = document.getElementById('dateTo').value;
      
      // Recharger la page avec les nouveaux filtres
      window.location.href = `facturation.php?status=${status}&date_from=${dateFrom}&date_to=${dateTo}`;
    }

    async function viewInvoice(invoiceId) {
      try {
        const response = await fetch(`facturation.php?action=get_invoice&id=${invoiceId}`);
        const invoice = await response.json();
        
        if (!invoice) {
          alert('Facture non trouvée');
          return;
        }
        
        // Calculer les totaux
        const totals = calculateInvoiceTotal(invoice.items);
        
        // Mettre à jour le modal (identique à votre version mais avec les données de l'API)
        document.getElementById('invoiceNumber').textContent = invoice.number;
        document.getElementById('invoiceClientName').textContent = invoice.client.name;
        document.getElementById('invoiceClientAddress').textContent = invoice.client.address;
        document.getElementById('invoiceClientCity').textContent = invoice.client.city;
        
        document.getElementById('invoiceDate').textContent = new Date(invoice.date).toLocaleDateString('fr-FR');
        document.getElementById('invoiceDueDate').textContent = new Date(invoice.dueDate).toLocaleDateString('fr-FR');
        
        // Mettre à jour le statut
        const statusElement = document.querySelector('#invoiceModal .invoice-status');
        statusElement.className = 'invoice-status';
        statusElement.classList.add(`status-${invoice.status}`);
        statusElement.textContent = 
          invoice.status === 'paye' ? 'Payée' : 
          invoice.status === 'impaye' ? 'En attente' : 'Annulée';
        
        // Ajouter les articles
        const itemsContainer = document.getElementById('invoiceItems');
        itemsContainer.innerHTML = '';
        
        invoice.items.forEach(item => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${escapeHtml(item.description)}</td>
            <td></td>
            <td>${item.unitPrice.toFixed(2)}€</td>
            <td>${item.quantity}</td>
            <td>${(item.unitPrice * item.quantity).toFixed(2)}€</td>
          `;
          itemsContainer.appendChild(tr);
        });
        
        // Mettre à jour les totaux
        document.getElementById('invoiceSubtotal').textContent = totals.subtotal.toFixed(2) + '€';
        document.getElementById('invoiceTax').textContent = totals.tax.toFixed(2) + '€';
        document.getElementById('invoiceTotal').textContent = totals.total.toFixed(2) + '€';
        
        // Afficher le modal
        document.getElementById('invoiceModal').style.display = 'flex';
      } catch (error) {
        console.error('Erreur:', error);
        alert('Erreur lors du chargement de la facture');
      }
    }

    async function saveNewInvoice() {
      const form = document.getElementById('newInvoiceForm');
      const formData = new FormData(form);
      
      // Récupérer les articles
      const items = [];
      const rows = document.getElementById('invoiceItemsInput').querySelectorAll('tr');
      
      rows.forEach(row => {
        items.push({
          description: row.querySelector('td:nth-child(1) input').value,
          unitPrice: parseFloat(row.querySelector('td:nth-child(2) input').value),
          quantity: parseInt(row.querySelector('td:nth-child(3) input').value)
        });
      });
      
      const invoiceData = {
        action: 'create_invoice',
        client_id: formData.get('client_id'),
        date: formData.get('invoice_date'),
        items: items,
        notes: formData.get('invoice_notes'),
        status: 'impaye'
      };
      
      try {
        const response = await fetch('facturation.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(invoiceData)
        });
        
        const result = await response.json();
        
        if (result.success) {
          alert('Facture créée avec succès');
          window.location.reload();
        } else {
          alert('Erreur: ' + (result.error || 'Échec de la création'));
        }
      } catch (error) {
        console.error('Erreur:', error);
        alert('Erreur lors de la création de la facture');
      }
    }

    // [Autres fonctions JavaScript restent similaires]
    
    function escapeHtml(unsafe) {
      return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }
  </script>
</body>
</html>