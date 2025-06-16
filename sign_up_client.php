<?php
require 'config.php';
session_start();

$message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $prenom    = trim($_POST['prenom'] ?? '');
    $nom       = trim($_POST['nom'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['mot_de_passe'] ?? '';
    $telephone = trim($_POST['telephone'] ?? '');

    if (empty($prenom) || empty($nom) || empty($email) || empty($password) || empty($telephone)) {
        $message = "All fields are required.";
    } else {
        // Check if email is already used
        $stmt = $pdo->prepare("SELECT * FROM Utilisateur WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $message = "Email already used. Please choose another.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert into Utilisateur
            $stmt = $pdo->prepare("INSERT INTO Utilisateur (email, nom, prenom, mot_de_passe, role) VALUES (?, ?, ?, ?, 'client')");
            $stmt->execute([$email, $nom, $prenom, $hashed_password]);
            $id_utilisateur = $pdo->lastInsertId();

            // Insert into Client
            $stmt = $pdo->prepare("INSERT INTO Client (id_utilisateur, telephone) VALUES (?, ?)");
            $stmt->execute([$id_utilisateur, $telephone]);

            // Save to session and redirect
            $_SESSION['id_utilisateur'] = $id_utilisateur;
            $_SESSION['role'] = 'client';
            header("Location: search.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sign Up Page</title>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Poppins:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="sign_up_client.css">
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

      <div class="welcome-title">Create Account</div>
      <div class="mini-description">
        Please fill in the information below to create a new account.
      </div>

      <?php if (!empty($message)): ?>
        <p style="color: red; font-weight: bold; text-align: center;"><?= htmlspecialchars($message) ?></p>
      <?php endif; ?>

      <form class="login-form" method="POST">
        <input type="text" name="prenom" placeholder="First Name" class="input-field" required>
        <input type="text" name="nom" placeholder="Last Name" class="input-field" required>
        <input type="email" name="email" placeholder="Email" class="input-field" required>
        <input type="password" name="mot_de_passe" placeholder="Password" class="input-field" required>
        <input type="text" name="telephone" placeholder="Phone Number" class="input-field" required>
        <button type="submit" class="login-button">Sign Up</button>
      </form>

      <div class="signup-link">
        Already have an account? <a href="login_client.php"><b>Log In</b></a>
      </div>
    </div>
  </div>
</body>
</html>
