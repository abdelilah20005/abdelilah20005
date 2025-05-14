<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mot de passe oublié - HôtelPremium</title>
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
      --error: #e53e3e;
      --ombre: 0 10px 30px rgba(0, 0, 0, 0.15);
      --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, var(--bleu-marine), var(--bleu-clair));
      color: var(--texte);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 20px;
    }

    .password-container {
      background-color: var(--blanc);
      padding: 2.5rem;
      border-radius: 16px;
      box-shadow: var(--ombre);
      width: 100%;
      max-width: 420px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .password-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 6px;
      background: linear-gradient(90deg, var(--bleu-clair), var(--or));
    }

    .password-logo {
      width: 80px;
      height: 80px;
      object-fit: contain;
      margin-bottom: 1.5rem;
      border-radius: 50%;
      padding: 10px;
      background-color: rgba(30, 74, 142, 0.1);
    }

    .password-title {
      color: var(--bleu-marine);
      margin-bottom: 1rem;
      font-size: 1.8rem;
      font-weight: 600;
      position: relative;
      display: inline-block;
    }

    .password-title::after {
      content: '';
      position: absolute;
      bottom: -8px;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 3px;
      background: var(--or);
      border-radius: 2px;
    }

    .password-subtitle {
      color: #718096;
      margin-bottom: 2rem;
      font-size: 0.95rem;
      line-height: 1.6;
    }

    .password-form {
      margin-top: 1.5rem;
    }

    .form-group {
      margin-bottom: 1.5rem;
      text-align: left;
      position: relative;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      color: var(--bleu-marine);
      font-weight: 500;
      font-size: 0.95rem;
    }

    .input-field {
      position: relative;
    }

    .input-field i {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--bleu-clair);
      font-size: 1rem;
    }

    .form-group input {
      width: 100%;
      padding: 12px 15px 12px 42px;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      font-size: 0.95rem;
      transition: var(--transition);
      background-color: var(--gris-clair);
    }

    .form-group input:focus {
      outline: none;
      border-color: var(--bleu-clair);
      box-shadow: 0 0 0 3px rgba(30, 74, 142, 0.2);
    }

    .btn-submit {
      background: linear-gradient(135deg, var(--bleu-clair), var(--or));
      color: var(--blanc);
      border: none;
      padding: 14px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      width: 100%;
      font-size: 1rem;
      margin-top: 0.5rem;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
      background: linear-gradient(135deg, var(--bleu-marine), var(--bleu-clair));
    }

    .back-to-login {
      display: block;
      text-align: center;
      margin-top: 1.5rem;
      font-size: 0.9rem;
      color: var(--bleu-clair);
      text-decoration: none;
      transition: var(--transition);
    }

    .back-to-login:hover {
      color: var(--or);
      text-decoration: underline;
    }

    .success-message {
      display: none;
      color: var(--success);
      margin-bottom: 1.5rem;
      font-size: 0.95rem;
    }

    /* Responsive */
    @media (max-width: 480px) {
      .password-container {
        padding: 2rem 1.5rem;
      }
      
      .password-title {
        font-size: 1.5rem;
      }
      
      .form-group input {
        padding: 12px 15px 12px 40px;
      }
    }

    /* Animation */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .password-container {
      animation: fadeIn 0.6s ease-out forwards;
    }
  </style>
</head>
<body>
  <div class="password-container">
    <h1 class="password-title">Mot de passe oublié</h1>
    <p class="password-subtitle">Entrez votre adresse email pour recevoir un lien de réinitialisation</p>
    
    <div class="success-message" id="successMessage">
      <i class="fas fa-check-circle"></i> Un email de réinitialisation a été envoyé !
    </div>
    
    <form class="password-form" id="passwordForm">
      <div class="form-group">
        <label for="email">Adresse email</label>
        <div class="input-field">
          <i class="fas fa-envelope"></i>
          <input type="email" id="email" name="email" placeholder="votre@email.com" required>
        </div>
      </div>
      
      <button type="submit" class="btn-submit">
        <i class="fas fa-paper-plane"></i> Envoyer le lien
      </button>
    </form>
    
    <a href="login.html" class="back-to-login">
      <i class="fas fa-arrow-left"></i> Retour à la connexion
    </a>
  </div>

  <script>
    // Gestion du formulaire
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Simulation d'envoi réussi
      document.getElementById('passwordForm').style.display = 'none';
      document.getElementById('successMessage').style.display = 'block';
      
      // Réinitialiser après 5 secondes (pour la démo)
      setTimeout(() => {
        document.getElementById('passwordForm').style.display = 'block';
        document.getElementById('successMessage').style.display = 'none';
        document.getElementById('passwordForm').reset();
      }, 5000);
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