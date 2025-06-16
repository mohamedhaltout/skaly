<?php
session_start();

require 'config.php';

$erreur = '';

if (isset($_POST["email"], $_POST["password"])) {
    $email = trim($_POST["email"]);
    $mot_de_passe = trim($_POST["password"]);

    if (!empty($email) && !empty($mot_de_passe)) {
        $stmt = $pdo->prepare("SELECT id_utilisateur, email, mot_de_passe, role FROM Utilisateur WHERE email = ?");
        $stmt->execute([$email]);
        $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($utilisateur && isset($utilisateur['role']) && password_verify($mot_de_passe, $utilisateur['mot_de_passe'])) {

          $role = strtolower(trim($utilisateur['role']));

            $_SESSION['user_id'] = $utilisateur['id_utilisateur'];
            $_SESSION['email'] = $utilisateur['email'];
            $_SESSION['role'] = $role;

            switch ($role) {
                case 'client':
                    header("Location: search.php");
                    exit();
                case 'prestataire':
                    header("Location: artisan_dashboard.php");
                    exit();
                case 'admin':
                    header("Location: admin_dashboard.php");
                    exit();
                default:
                    $erreur = "Rôle d'utilisateur inconnu : " . htmlspecialchars($role);
            }
        } else {
            $erreur = "Adresse e-mail ou mot de passe incorrect.";
        }
    } else {
        $erreur = "Veuillez remplir tous les champs.";
    }
}
?>


<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Connexion</title>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Poppins:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="login.css">
</head>
<body>
  <div class="login-container">
    <div class="left-decoration"></div>
    <div class="right-content">
      <div class="logo-container">
        <img src="img/Logo_image.png" alt="Logo" class="logo-img">
        <div class="logo-text">
          <span>Skilled</span><span>.</span>
        </div>
      </div>

      <div class="welcome-title">Connexion</div>
      <div class="mini-description">
        Veuillez vous connecter avec votre compte pour accéder à la plateforme Skilled.
      </div>

      <?php if (!empty($erreur)): ?>
        <p style="color:red; font-weight:bold; text-align:center;"><?= htmlspecialchars($erreur) ?></p>
      <?php endif; ?>

      <form class="login-form" method="POST" action="">
        <input type="email" name="email" placeholder="Email Adress" class="input-field" required>
        <input type="password" name="password" placeholder="Password" class="input-field" required>
        <button type="submit" class="login-button">Se connecter</button>
      </form>

      <div class="signup-link">
        Vous n’avez pas de compte ? <a href="sign_up_client.php"><b>Créer un compte</b></a>
      </div>
    </div>
  </div>
</body>
</html>
