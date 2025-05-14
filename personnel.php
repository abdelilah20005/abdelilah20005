<?php
// Connexion à la base de données
require_once 'db.php';
// Ajoutez cette fonction au début de votre fichier PHP (après la connexion DB)
function getDepartmentName($department) {
    $departments = [
        'reception' => 'Réception',
        'menage' => 'Ménage',
        'maintenance' => 'Maintenance',
        'direction' => 'Direction',
        'restauration' => 'Restauration'
    ];
    return $departments[$department] ?? $department;
}


// Initialisation des variables
$action = isset($_GET['action']) ? $_GET['action'] : '';
$staffId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save') {
        // Sauvegarde d'un membre du personnel
        $nom = mysqli_real_escape_string($conn, $_POST['nom']);
        $prenom = mysqli_real_escape_string($conn, $_POST['prenom']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $telephone = mysqli_real_escape_string($conn, $_POST['telephone']);
        $poste = mysqli_real_escape_string($conn, $_POST['poste']);
        $departement = mysqli_real_escape_string($conn, $_POST['departement']);
        $date_embauche = mysqli_real_escape_string($conn, $_POST['date_embauche']);
        $statut = mysqli_real_escape_string($conn, $_POST['statut']);
        $salaire = isset($_POST['salaire']) ? floatval($_POST['salaire']) : null;

        if (isset($_POST['staff_id']) && $_POST['staff_id'] > 0) {
            // Mise à jour
            $id = intval($_POST['staff_id']);
            $query = "UPDATE personnel SET 
                      nom='$nom', 
                      prenom='$prenom', 
                      email='$email', 
                      telephone='$telephone', 
                      poste='$poste', 
                      departement='$departement', 
                      date_embauche='$date_embauche', 
                      statut='$statut', 
                      salaire=$salaire 
                      WHERE id=$id";
        } else {
            // Création
            $query = "INSERT INTO personnel (nom, prenom, email, telephone, poste, departement, date_embauche, statut, salaire) 
                      VALUES ('$nom','$prenom','$email','$telephone','$poste','$departement','$date_embauche','$statut',$salaire)";
        }
        
        mysqli_query($conn, $query);
        header('Location: personnel.php?search='.urlencode($search));
        exit();
    }
} elseif ($action === 'delete' && $staffId > 0) {
    $query = "DELETE FROM personnel WHERE id=$staffId";
    mysqli_query($conn, $query);
    header('Location: personnel.php?search='.urlencode($search));
    exit();
}

// Récupération du personnel avec recherche
$query = "SELECT * FROM personnel";
if (!empty($search)) {
    $query .= " WHERE CONCAT(nom, ' ', prenom) LIKE '%$search%' 
                OR email LIKE '%$search%' 
                OR telephone LIKE '%$search%' 
                OR poste LIKE '%$search%'";
}
$query .= " ORDER BY nom, prenom";
$result = mysqli_query($conn, $query);
$staffMembers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $staffMembers[] = $row;
}

// Récupération pour édition
$staffToEdit = null;
if ($action === 'edit' && $staffId > 0) {
    $query = "SELECT * FROM personnel WHERE id=$staffId";
    $result = mysqli_query($conn, $query);
    $staffToEdit = mysqli_fetch_assoc($result);
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du Personnel - HôtelLuxe</title>
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

    /* Tableau */
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

    .staff-avatar {
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

    .role-badge {
      padding: 0.3rem 0.8rem;
      border-radius: 12px;
      font-size: 0.85rem;
      font-weight: 500;
      display: inline-block;
    }

    .role-manager {
      background-color: rgba(212, 175, 55, 0.15);
      color: var(--or);
    }

    .role-reception {
      background-color: rgba(30, 74, 142, 0.15);
      color: var(--bleu-clair);
    }

    .role-housekeeping {
      background-color: rgba(72, 187, 120, 0.15);
      color: var(--success);
    }

    .role-maintenance {
      background-color: rgba(221, 107, 32, 0.15);
      color: var(--warning);
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

    /* Modal */
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
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
    }

    .form-input {
      width: 100%;
      padding: 0.8rem 1rem;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 1rem;
    }

    .form-select {
      width: 100%;
      padding: 0.8rem 1rem;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 1rem;
      appearance: none;
      background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 1rem center;
      background-size: 1em;
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 1rem;
      margin-top: 2rem;
    }

    /* Responsive */
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
    }
  
        /* Ajouter les styles spécifiques pour les badges de département */
        .role-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .role-reception {
            background-color: rgba(30, 74, 142, 0.15);
            color: var(--bleu-clair);
        }
        
        .role-menage {
            background-color: rgba(72, 187, 120, 0.15);
            color: var(--success);
        }
        
        .role-maintenance {
            background-color: rgba(221, 107, 32, 0.15);
            color: var(--warning);
        }
        
        .role-direction {
            background-color: rgba(212, 175, 55, 0.15);
            color: var(--or);
        }
        
        .role-restauration {
            background-color: rgba(156, 39, 176, 0.15);
            color: #9c27b0;
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
            Gestion du Personnel
            <button class="btn btn-primary" onclick="openModal('add')">
                <i class="fas fa-user-plus"></i> Nouveau membre
            </button>
        </h1>

        <div class="tools-bar">
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <form method="GET" action="personnel.php" id="searchForm">
                    <input type="text" class="search-input" placeholder="Rechercher un membre..." 
                           id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>"
                           oninput="filterStaff()">
                </form>
            </div>
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="exportStaff()">
                    <i class="fas fa-file-export"></i> Exporter
                </button>
            </div>
        </div>

        <div class="table-container">
            <table id="staffTable">
                <thead>
                    <tr>
                        <th>Membre</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Poste</th>
                        <th>Département</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffMembers as $staff): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                             <?php
                              $initiales = strtoupper(substr($staff['nom'], 0, 1)) . 
                               strtoupper(substr($staff['prenom'], 0, 1));
                              ?>
                            <div class="client-avatar"><?= $initiales ?></div>         
      </div>
                        </td>
                        <td><?= htmlspecialchars($staff['email']) ?></td>
                        <td><?= htmlspecialchars($staff['telephone']) ?></td>
                        <td><?= htmlspecialchars($staff['poste']) ?></td>
                        <td>
                            <span class="role-badge role-<?= htmlspecialchars($staff['departement']) ?>">
                                <?= getDepartmentName($staff['departement']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?= $staff['statut'] === 'actif' ? 'status-active' : ($staff['statut'] === 'inactif' ? 'status-inactive' : 'status-vacation') ?>">
                                <?= ucfirst($staff['statut']) ?>
                            </span>
                        </td>
                        <td>
                            <button class="action-btn edit-btn" onclick="editStaff(<?= $staff['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn delete-btn" onclick="confirmDelete(<?= $staff['id'] ?>)">
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
    <div class="modal" id="staffModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Nouveau Membre</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="staffForm" method="POST" action="personnel.php?action=save&search=<?= urlencode($search) ?>">
                <input type="hidden" id="staffId" name="staff_id">
                <input type="hidden" name="action" id="formAction" value="add">
                <div class="form-group">
                    <label class="form-label">Nom</label>
                    <input type="text" class="form-input" id="staffNom" name="nom" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Prénom</label>
                    <input type="text" class="form-input" id="staffPrenom" name="prenom" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" id="staffEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="tel" class="form-input" id="staffTelephone" name="telephone" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Poste</label>
                    <input type="text" class="form-input" id="staffPoste" name="poste" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Département</label>
                    <select class="form-input" id="staffDepartment" name="departement" required>
                        <option value="reception">Réception</option>
                        <option value="menage">Ménage</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="direction">Direction</option>
                        <option value="restauration">Restauration</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date d'embauche</label>
                    <input type="date" class="form-input" id="staffHireDate" name="date_embauche" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Statut</label>
                    <select class="form-input" id="staffStatus" name="statut" required>
                        <option value="actif">Actif</option>
                        <option value="inactif">Inactif</option>
                        <option value="congé">En congé</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Salaire (€)</label>
                    <input type="number" step="0.01" class="form-input" id="staffSalaire" name="salaire">
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
            <p>Êtes-vous sûr de vouloir supprimer ce membre du personnel ?</p>
            <div class="form-actions">
                <button type="button" class="btn" onclick="closeModal()">Annuler</button>
                <a id="confirmDeleteLink" href="#" class="btn btn-primary">Supprimer</a>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour obtenir le nom complet du département
        function getDepartmentName(department) {
            const names = {
                'reception': 'Réception',
                'menage': 'Ménage',
                'maintenance': 'Maintenance',
                'direction': 'Direction',
                'restauration': 'Restauration'
            };
            return names[department] || department;
        }

        // Fonction de filtrage côté client
        function filterStaff() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('staffTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const tdName = tr[i].getElementsByTagName('td')[0];
                const tdEmail = tr[i].getElementsByTagName('td')[1];
                const tdPhone = tr[i].getElementsByTagName('td')[2];
                const tdPoste = tr[i].getElementsByTagName('td')[3];
                const tdDept = tr[i].getElementsByTagName('td')[4];
                
                if (tdName || tdEmail || tdPhone || tdPoste || tdDept) {
                    const txtValueName = tdName.textContent || tdName.innerText;
                    const txtValueEmail = tdEmail.textContent || tdEmail.innerText;
                    const txtValuePhone = tdPhone.textContent || tdPhone.innerText;
                    const txtValuePoste = tdPoste.textContent || tdPoste.innerText;
                    const txtValueDept = tdDept.textContent || tdDept.innerText;
                    
                    if (txtValueName.toUpperCase().indexOf(filter) > -1 ||
                        txtValueEmail.toUpperCase().indexOf(filter) > -1 ||
                        txtValuePhone.toUpperCase().indexOf(filter) > -1 ||
                        txtValuePoste.toUpperCase().indexOf(filter) > -1 ||
                        txtValueDept.toUpperCase().indexOf(filter) > -1) {
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
        function openModal(mode, staffId = null) {
            const modal = document.getElementById('staffModal');
            const title = document.getElementById('modalTitle');
            const formAction = document.getElementById('formAction');
            
            if (mode === 'add') {
                title.textContent = 'Nouveau Membre';
                document.getElementById('staffForm').reset();
                document.getElementById('staffId').value = '';
                document.getElementById('staffHireDate').valueAsDate = new Date();
                formAction.value = 'add';
            } else {
                title.textContent = 'Modifier Membre';
                const staff = <?= json_encode($staffMembers) ?>.find(s => s.id == staffId);
                if (staff) {
                    document.getElementById('staffId').value = staff.id;
                    document.getElementById('staffNom').value = staff.nom;
                    document.getElementById('staffPrenom').value = staff.prenom;
                    document.getElementById('staffEmail').value = staff.email;
                    document.getElementById('staffTelephone').value = staff.telephone;
                    document.getElementById('staffPoste').value = staff.poste;
                    document.getElementById('staffDepartment').value = staff.departement;
                    document.getElementById('staffHireDate').value = staff.date_embauche;
                    document.getElementById('staffStatus').value = staff.statut;
                    document.getElementById('staffSalaire').value = staff.salaire;
                    formAction.value = 'edit';
                }
            }
            
            modal.style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('staffModal').style.display = 'none';
            document.getElementById('confirmModal').style.display = 'none';
        }

        function editStaff(staffId) {
            openModal('edit', staffId);
        }

        function confirmDelete(staffId) {
            document.getElementById('confirmModal').style.display = 'flex';
            document.getElementById('confirmDeleteLink').href = 
                `personnel.php?action=delete&id=${staffId}&search=<?= urlencode($search) ?>`;
        }

        function exportStaff() {
            window.location.href = `export_personnel.php?search=<?= urlencode($search) ?>`;
        }

        
    // Initialisation si en mode édition
    // Initialisation si en mode édition
    <?php if ($action === 'edit' && isset($staffToEdit)) { ?>
        document.addEventListener('DOMContentLoaded', function() {
            openModal('edit', <?php echo $staffToEdit['id']; ?>);
        });
    <?php } ?>

    </script>
</body>
</html>