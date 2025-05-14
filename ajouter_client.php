<?php
// ajouter_client.php
require_once 'db.php';

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et sécuriser les données du formulaire
    $nom = mysqli_real_escape_string($conn, $_POST['nom']);
    $prenom = mysqli_real_escape_string($conn, $_POST['prenom']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $telephone = mysqli_real_escape_string($conn, $_POST['telephone']);
    $adresse = mysqli_real_escape_string($conn, $_POST['adresse']);
    $ville = mysqli_real_escape_string($conn, $_POST['ville']);
    $code_postal = mysqli_real_escape_string($conn, $_POST['code_postal']);
    $pays = mysqli_real_escape_string($conn, $_POST['pays']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    $statut = isset($_POST['statut']) ? 'actif' : 'inactif';

    // Requête d'insertion
    $query = "INSERT INTO clients (nom, prenom, email, telephone, adresse, ville, code_postal, pays, notes, statut, date_creation)
              VALUES ('$nom', '$prenom', '$email', '$telephone', '$adresse', '$ville', '$code_postal', '$pays', '$notes', '$statut', NOW())";

    // Exécution de la requête
    if (mysqli_query($conn, $query)) {
        // Redirection après succès
        header('Location: clients.php?success=1');
        exit();
    } else {
        // Gestion des erreurs
        die("Erreur lors de l'ajout du client: " . mysqli_error($conn));
    }
}

// Si on arrive ici, c'est qu'on n'a pas soumis le formulaire
// On affiche le formulaire d'ajout
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un client - HôtelLuxe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reprendre le même CSS que clients.php */
        :root {
            --bleu-marine: #0a1f38;
            --bleu-clair: #1e4a8e;
            --or: #d4af37;
            --blanc: #ffffff;
            --gris-clair: #f8f9fa;
            --texte: #2d3748;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--gris-clair);
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: var(--bleu-marine);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .form-actions {
            margin-top: 30px;
            text-align: right;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-primary {
            background-color: var(--bleu-clair);
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-user-plus"></i> Ajouter un nouveau client</h1>
        
        <form method="POST" action="ajouter_client.php">
            <div class="form-group">
                <label for="nom">Nom</label>
                <input type="text" id="nom" name="nom" required>
            </div>
            
            <div class="form-group">
                <label for="prenom">Prénom</label>
                <input type="text" id="prenom" name="prenom" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="telephone">Téléphone</label>
                <input type="tel" id="telephone" name="telephone" required>
            </div>
            
            <div class="form-group">
                <label for="adresse">Adresse</label>
                <input type="text" id="adresse" name="adresse">
            </div>
            
            <div class="form-group">
                <label for="ville">Ville</label>
                <input type="text" id="ville" name="ville">
            </div>
            
            <div class="form-group">
                <label for="code_postal">Code Postal</label>
                <input type="text" id="code_postal" name="code_postal">
            </div>
            
            <div class="form-group">
                <label for="pays">Pays</label>
                <input type="text" id="pays" name="pays">
            </div>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="statut" checked>
                    Client actif
                </label>
            </div>
            
            <div class="form-actions">
                <a href="clients.php" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</body>
</html>