<?php
// Inclure la connexion à la base de données
include('db.php');

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire et les sécuriser
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm-password']);

    // Validation des mots de passe
    if ($password !== $confirm_password) {
        echo "<script>alert('Les mots de passe ne correspondent pas');</script>";
    } else {
        // Vérifier si l'email existe déjà dans la base de données
        $query = "SELECT * FROM utilisateurs WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // L'email existe déjà
            echo "<script>alert('L\'email est déjà utilisé. Veuillez en choisir un autre.');</script>";
        } else {
            // Hacher le mot de passe avant de le stocker
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insérer les données dans la base de données
            $sql = "INSERT INTO utilisateurs (fullname, email, phone, password) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $fullname, $email, $phone, $hashed_password);

            if ($stmt->execute()) {
                echo "<script>alert('Compte créé avec succès!'); window.location.href = 'login.php';</script>";
            } else {
                echo "<script>alert('Erreur: " . $conn->error . "');</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Créer un compte - HôtelPremium</title>
  <link rel="stylesheet" href="creer_compt.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="signup-container">
    <h1 class="signup-title">Créer un compte</h1>
    <p class="signup-subtitle">Rejoignez notre communauté et profitez de nos services premium</p>
    
    <form class="signup-form" id="signupForm" method="POST">
      <div class="form-group">
        <label for="fullname">Nom complet</label>
        <div class="input-field">
          <i class="fas fa-user"></i>
          <input type="text" id="fullname" name="fullname" placeholder="Jean Dupont" required>
        </div>
      </div>
      
      <div class="form-group">
        <label for="email">Adresse email</label>
        <div class="input-field">
          <i class="fas fa-envelope"></i>
          <input type="email" id="email" name="email" placeholder="votre@email.com" required>
        </div>
      </div>
      
      <div class="form-group">
        <label for="phone">Téléphone</label>
        <div class="input-field">
          <i class="fas fa-phone"></i>
          <input type="tel" id="phone" name="phone" placeholder="+33 6 12 34 56 78">
        </div>
      </div>
      
      <div class="form-group">
        <label for="password">Mot de passe</label>
        <div class="input-field">
          <i class="fas fa-lock"></i>
          <input type="password" id="password" name="password" placeholder="••••••••" required>
          <i class="fas fa-eye password-toggle" id="togglePassword"></i>
        </div>
      </div>
      
      <div class="form-group">
        <label for="confirm-password">Confirmer le mot de passe</label>
        <div class="input-field">
          <i class="fas fa-lock"></i>
          <input type="password" id="confirm-password" name="confirm-password" placeholder="••••••••" required>
          <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
        </div>
      </div>
      
      <button type="submit" class="btn-signup">
        <i class="fas fa-user-plus"></i> Créer mon compte
      </button>
      
      <p class="terms-text">
        En créant un compte, vous acceptez nos <a href="#">Conditions d'utilisation</a> 
        et notre <a href="#">Politique de confidentialité</a>.
      </p>
    </form>
    
    <div class="login-link">
      Vous avez déjà un compte ? <a href="login.php">Se connecter</a>
    </div>
  </div>

  <script>
    // Fonctionnalité pour afficher/masquer le mot de passe
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    const toggleConfirmPassword = document.querySelector('#toggleConfirmPassword');
    const confirmPassword = document.querySelector('#confirm-password');
    
    togglePassword.addEventListener('click', function() {
      const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
      password.setAttribute('type', type);
      this.classList.toggle('fa-eye-slash');
    });
    
    toggleConfirmPassword.addEventListener('click', function() {
      const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
      confirmPassword.setAttribute('type', type);
      this.classList.toggle('fa-eye-slash');
    });
  </script>
</body>
</html>
