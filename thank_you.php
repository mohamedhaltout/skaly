<?php
session_start();
require 'config.php';

if (!isset($_SESSION['id_utilisateur']) || $_SESSION['role'] !== 'client') {
    header("Location: login.php");
    exit();
}

$id_devis = isset($_GET['id_devis']) ? (int)$_GET['id_devis'] : 0;

// Optional: Fetch some details about the payment/quote to display
$devis_info = null;
if ($id_devis > 0) {
    $stmt = $pdo->prepare("SELECT d.acompte, r.description_service FROM Devis d JOIN Reservation r ON d.id_reservation = r.id_reservation WHERE d.id_devis = ?");
    $stmt->execute([$id_devis]);
    $devis_info = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You - Skilled</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700;800&family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f8f8f8;
            margin: 0;
            text-align: center;
            flex-direction: column;
        }
        .thank-you-container {
            background-color: #fff;
            padding: 40px 60px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 90%;
            box-sizing: border-box;
        }
        h1 {
            color: #3E185B;
            font-size: 36px;
            margin-bottom: 20px;
        }
        p {
            color: #62646A;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .redirect-message {
            font-size: 16px;
            color: #8E7B9C;
        }
        .logo-text {
            font-family: 'Poppins', sans-serif;
            font-weight: bold;
            font-size: 40px;
            color: #3E185B;
            margin-top: 30px;
        }
        .logo-dot {
            color: #8E7B9C;
        }
    </style>
</head>
<body>
    <div class="thank-you-container">
        <h1>Thank You!</h1>
        <p>Your payment for the deposit of <strong><?= number_format($devis_info['acompte'] ?? 0, 2) ?> DH</strong> for the service "<?= htmlspecialchars($devis_info['description_service'] ?? 'N/A') ?>" has been successfully processed.</p>
        <p>You will be redirected to your dashboard shortly.</p>
        <p class="redirect-message">If you are not redirected automatically, please <a href="client_Dashboard.php">click here</a>.</p>
    </div>
    <div class="logo-text">Skilled<span class="logo-dot">.</span></div>

    <script>
        setTimeout(function() {
            window.location.href = 'client_Dashboard.php?status=payment_successful';
        }, 3000); // Redirect after 3 seconds
    </script>
</body>
</html>