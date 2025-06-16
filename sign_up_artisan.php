<?php
require 'config.php';
session_start();

// Check if form data is submitted
if (isset($_POST['nom'], $_POST['prenom'], $_POST['email'], $_POST['mot_de_passe'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $mot_de_passe = $_POST['mot_de_passe'];
    $role = 'prestataire';

    // Validate required fields
    if (empty($nom) || empty($prenom) || empty($email) || empty($mot_de_passe)) {
        echo "<script>alert('All fields are required.');</script>";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id_utilisateur FROM Utilisateur WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            echo "<script>alert('This email is already registered.');</script>";
        } else {
            // Hash password and insert new user
            $hashed_password = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO Utilisateur (email, nom, prenom, mot_de_passe, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$email, $nom, $prenom, $hashed_password, $role]);

            $_SESSION['id_utilisateur'] = $pdo->lastInsertId();

            // Redirect to profile completion page
            header("Location: finish_profil.php");
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
  <link rel="stylesheet" href="sign_up_artisan.css">
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

    <form class="login-form" method="POST">
  <input type="text" name="prenom" placeholder="First Name" class="input-field" required>
  <input type="text" name="nom" placeholder="Last Name" class="input-field" required>
  <input type="email" name="email" placeholder="Email" class="input-field" required>
  <input type="password" name="mot_de_passe" placeholder="Password" class="input-field" required>
  <button type="submit" class="login-button">Sign Up</button>
</form>


      <div class="signup-link">
        Already have an account? <b>Log In</b>
      </div>
    </div>
  </div>
</body>
</html>
