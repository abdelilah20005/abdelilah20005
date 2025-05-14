<?php
// Connexion à la base de données
require_once 'db.php';

// Initialisation des variables
$action = isset($_GET['action']) ? $_GET['action'] : '';
$clientId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save') {
        // Sauvegarde d'un client
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);

        if (isset($_POST['client_id']) && $_POST['client_id'] > 0) {
            // Mise à jour
            $id = intval($_POST['client_id']);
            $query = "UPDATE clients SET name='$name', email='$email', phone='$phone', status='$status' WHERE id=$id";
        } else {
            // Création
            $query = "INSERT INTO clients (name, email, phone, status) VALUES ('$name','$email','$phone','$status')";
        }
        
        mysqli_query($conn, $query);
        header('Location: clientes.php?search='.urlencode($search));
        exit();
    }
} elseif ($action === 'delete' && $clientId > 0) {
    $query = "DELETE FROM clients WHERE id=$clientId";
    mysqli_query($conn, $query);
    header('Location: clientes.php?search='.urlencode($search));
    exit();
}

// Récupération des clients avec recherche
$query = "SELECT * FROM clients";
if (!empty($search)) {
    $query .= " WHERE name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%'";
}
$query .= " ORDER BY name";
$result = mysqli_query($conn, $query);
$clients = [];
while ($row = mysqli_fetch_assoc($result)) {
    $clients[] = $row;
}

// Récupération pour édition
$clientToEdit = null;
if ($action === 'edit' && $clientId > 0) {
    $query = "SELECT * FROM clients WHERE id=$clientId";
    $result = mysqli_query($conn, $query);
    $clientToEdit = mysqli_fetch_assoc($result);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Clients - HôtelLuxe</title>
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

        .tools-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-container {
            position: relative;
            flex: 1;
            min-width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 3rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            box-shadow: var(--ombre);
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--bleu-clair);
            box-shadow: 0 0 0 2px rgba(30, 74, 142, 0.2);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #718096;
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
            position: sticky;
            top: 0;
        }

        tr:hover {
            background-color: #F7FAFC;
        }

        .client-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--bleu-clair);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-active {
            background-color: rgba(72, 187, 120, 0.15);
            color: var(--success);
        }

        .status-inactive {
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

        .edit-btn {
            color: var(--bleu-clair);
        }

        .delete-btn {
            color: var(--error);
        }

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
            max-width: 600px;
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
            color: #6b7280;
            transition: var(--transition);
        }

        .close-btn:hover {
            color: var(--error);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--texte);
        }

        .form-input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--bleu-clair);
            box-shadow: 0 0 0 2px rgba(30, 74, 142, 0.2);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
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
            
            .search-container {
                min-width: 100%;
            }
            
            th, td {
                padding: 0.8rem;
            }
            
            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .form-actions {
                flex-direction: column-reverse;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
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
            Gestion des Clients
            <button class="btn btn-primary" onclick="openModal('add')">
                <i class="fas fa-user-plus"></i> Nouveau client
            </button>
        </h1>

        <div class="tools-bar">
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <form method="GET" action="clientes.php" id="searchForm">
                    <input type="text" class="search-input" placeholder="Rechercher client..." 
                           id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>"
                           oninput="filterClients()">
                </form>
            </div>
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="exportClients()">
                    <i class="fas fa-file-export"></i> Exporter
                </button>
            </div>
        </div>

        <div class="table-container">
            <table id="clientsTable">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="client-avatar"><?= strtoupper(substr($client['name'], 0, 1)) ?></div>
                                <span><?= htmlspecialchars($client['name']) ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($client['email']) ?></td>
                        <td><?= htmlspecialchars($client['phone']) ?></td>
                        <td>
                            <span class="status-badge <?= $client['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                <?= $client['status'] === 'active' ? 'Actif' : 'Inactif' ?>
                            </span>
                        </td>
                        <td>
                            <button class="action-btn edit-btn" onclick="editClient(<?= $client['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn delete-btn" onclick="confirmDelete(<?= $client['id'] ?>)">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Ajout/Modification -->
    <div class="modal" id="clientModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Nouveau Client</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="clientForm" method="POST" action="clientes.php?action=save&search=<?= urlencode($search) ?>">
                <input type="hidden" id="clientId" name="client_id">
                <input type="hidden" name="action" id="formAction" value="add">
                <div class="form-group">
                    <label class="form-label">Nom complet</label>
                    <input type="text" class="form-input" id="clientName" name="name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" id="clientEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="tel" class="form-input" id="clientPhone" name="phone" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Statut</label>
                    <select class="form-input" id="clientStatus" name="status" required>
                        <option value="active">Actif</option>
                        <option value="inactive">Inactif</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Confirmation Suppression -->
    <div class="modal" id="confirmModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2 class="modal-title">Confirmation</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <p>Êtes-vous sûr de vouloir supprimer ce client ?</p>
            <div class="form-actions">
                <button type="button" class="btn" onclick="closeModal()">Annuler</button>
                <a id="confirmDeleteLink" href="#" class="btn btn-primary">Supprimer</a>
            </div>
        </div>
    </div>

    <script>
        // Fonction de filtrage côté client
        function filterClients() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('clientsTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const tdName = tr[i].getElementsByTagName('td')[0];
                const tdEmail = tr[i].getElementsByTagName('td')[1];
                const tdPhone = tr[i].getElementsByTagName('td')[2];
                
                if (tdName || tdEmail || tdPhone) {
                    const txtValueName = tdName.textContent || tdName.innerText;
                    const txtValueEmail = tdEmail.textContent || tdEmail.innerText;
                    const txtValuePhone = tdPhone.textContent || tdPhone.innerText;
                    
                    if (txtValueName.toUpperCase().indexOf(filter) > -1 ||
                        txtValueEmail.toUpperCase().indexOf(filter) > -1 ||
                        txtValuePhone.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = '';
                    } else {
                        tr[i].style.display = 'none';
                    }
                }
            }
        }

        // Soumission automatique après délai
        let searchTimer;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                document.getElementById('searchForm').submit();
            }, 800);
        });

        // Gestion des modales
        function openModal(mode, clientId = null) {
            const modal = document.getElementById('clientModal');
            const title = document.getElementById('modalTitle');
            const formAction = document.getElementById('formAction');
            
            if (mode === 'add') {
                title.textContent = 'Nouveau Client';
                document.getElementById('clientForm').reset();
                document.getElementById('clientId').value = '';
                formAction.value = 'add';
            } else {
                title.textContent = 'Modifier Client';
                const client = <?= json_encode($clients) ?>.find(c => c.id == clientId);
                if (client) {
                    document.getElementById('clientId').value = client.id;
                    document.getElementById('clientName').value = client.name;
                    document.getElementById('clientEmail').value = client.email;
                    document.getElementById('clientPhone').value = client.phone;
                    document.getElementById('clientStatus').value = client.status;
                    formAction.value = 'edit';
                }
            }
            
            modal.style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('clientModal').style.display = 'none';
            document.getElementById('confirmModal').style.display = 'none';
        }

        function editClient(clientId) {
            openModal('edit', clientId);
        }

        function confirmDelete(clientId) {
            document.getElementById('confirmModal').style.display = 'flex';
            document.getElementById('confirmDeleteLink').href = 
                `clientes.php?action=delete&id=${clientId}&search=<?= urlencode($search) ?>`;
        }

        function exportClients() {
            window.location.href = `export_clients.php?search=<?= urlencode($search) ?>`;
        }

        // Initialisation si en mode édition
        <?php if ($action === 'edit' && isset($clientToEdit)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openModal('edit', <?= $clientToEdit['id'] ?>);
        });
        <?php endif; ?>
    </script>
</body>
</html>