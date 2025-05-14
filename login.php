<?php
// Inclure la connexion à la base de données
include('db.php');

session_start();

// Vérifier si le formulaire a été soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupérer les données du formulaire et les sécuriser
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // Rechercher l'utilisateur dans la base de données
    $query = "SELECT * FROM utilisateurs WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Vérifier si l'utilisateur existe et si le mot de passe est correct
    if ($user && password_verify($password, $user['password'])) {
        // Connexion réussie, rediriger vers la page d'accueil
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        header('Location: accueil.php'); // Remplacer par la page d'accueil de ton application
        exit;
    } else {
        // Échec de la connexion, afficher un message d'erreur
        $error_message = "Email ou mot de passe incorrect";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion - HôtelPremium</title>
  <link rel="stylesheet" href="login.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="login-container">
    
    <h1 class="login-title">Connexion</h1>
    
    <!-- Afficher le message d'erreur si la connexion échoue -->
    <?php if (isset($error_message)): ?>
      <div style="color: red; margin-bottom: 15px;"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <form class="login-form" action="login.php" method="POST">
      <div class="form-group">
        <label for="email">Adresse email</label>
        <div class="input-field">
          <i class="fas fa-envelope"></i>
          <input type="email" id="email" name="email" placeholder="votre@email.com" required>
        </div>
      </div>
      
      <div class="form-group">
        <label for="password">Mot de passe</label>
        <div class="input-field">
          <i class="fas fa-lock"></i>
          <input type="password" id="password" name="password" placeholder="••••••••" required>
          <i class="fas fa-eye password-toggle" id="togglePassword"></i>
        </div>
        <a href="oblier.PHP" class="forgot-password">Mot de passe oublié ?</a>
      </div>
      
      <button type="submit" class="btn-login">
        <i class="fas fa-sign-in-alt"></i> Se connecter
      </button>
    </form>
    
    <div class="login-footer">
      Pas encore de compte ? <a href="creer_compt.php">Créer un compte</a>
    </div>
  </div>

  <script>
    // Fonctionnalité pour afficher/masquer le mot de passe
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    
    togglePassword.addEventListener('click', function (e) {
      const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
      password.setAttribute('type', type);
      this.classList.toggle('fa-eye-slash');
    });
    
    // Animation au chargement
    document.addEventListener('DOMContentLoaded', () => {
      const inputs = document.querySelectorAll('input');
      inputs.forEach(input => {
        input.style.opacity = '0';
        input.style.transform = 'translateY(10px)';
        input.style.transition = 'all 0.4s ease-out';
        
        setTimeout(() => {
          input.style.opacity = '1';
          input.style.transform = 'translateY(0)';
        }, 300);
      });
    });
  </script>
</body>
</html>
