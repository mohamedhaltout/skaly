<?php
session_start();
require 'config.php';

if (!isset($_SESSION['id_utilisateur']) || $_SESSION['role'] !== 'client') {
    header("Location: login.php");
    exit();
}

$id_client = $_SESSION['id_utilisateur'];
$id_devis = isset($_GET['id_devis']) ? (int)$_GET['id_devis'] : 0;

if ($id_devis === 0) {
    die("No quote specified for payment.");
}

// Fetch quote details
$stmt = $pdo->prepare("SELECT d.*, r.id_client, r.id_prestataire, r.description_service, u_artisan.nom AS artisan_nom, u_artisan.prenom AS artisan_prenom FROM Devis d JOIN Reservation r ON d.id_reservation = r.id_reservation JOIN Utilisateur u_artisan ON r.id_prestataire = u_artisan.id_utilisateur WHERE d.id_devis = ? AND r.id_client = ? AND d.statut_devis = 'accepté'");
$stmt->execute([$id_devis, $id_client]);
$devis = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$devis) {
    die("Quote not found, not accepted, or not yours.");
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_number = $_POST['card_number'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?? '';
    $cvv = $_POST['cvv'] ?? '';

    // Simple validation for simulation
    if (empty($card_number) || empty($expiry_date) || empty($cvv)) {
        $message = "Please fill all payment details.";
    } else {
        // Simulate payment success
        $montant_acompte = $devis['acompte'];
        $id_prestataire = $devis['id_prestataire'];

        try {
            $pdo->beginTransaction();

            // Insert payment record for the down payment
            $stmt = $pdo->prepare("INSERT INTO Paiement (id_devis, id_client, id_prestataire, montant, type_paiement, methode_paiement, statut_paiement, reference_transaction) VALUES (?, ?, ?, ?, 'acompte', 'simulation', 'effectué', ?)");
            $stmt->execute([$id_devis, $id_client, $id_prestataire, $montant_acompte, 'SIM_' . uniqid()]);

            // Update quote status to paid (or add a payment status to quote)
            // For now, we'll just rely on the Paiement table for payment status
            
            $pdo->commit();
            header("Location: thank_you.php?id_devis=" . $id_devis);
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Payment failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Skilled</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700;800&family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="payment_page.css">
</head>
<body>
    <div class="payment-container">
        <div class="left-decoration"></div>
        <div class="right-content">
            <div class="title">Complete Your Payment</div>

            <?php if ($message): ?>
                <p class="message"><?= htmlspecialchars($message) ?></p>
            <?php endif; ?>

            <div class="payment-summary">
                <h3>Payment for Service: <?= htmlspecialchars($devis['description_service']) ?></h3>
                <p>Artisan: <?= htmlspecialchars($devis['artisan_prenom'] . ' ' . $devis['artisan_nom']) ?></p>
                <p>Total Quote: <?= number_format($devis['cout_total'], 2) ?> DH</p>
                <p>Deposit Due: <span class="amount-due"><?= number_format($devis['acompte'], 2) ?> DH</span></p>
            </div>

            <form method="POST" action="payment_page.php?id_devis=<?= $id_devis ?>">
                <div class="form-group">
                    <div class="form-info">
                        <label for="card_number">Card Number</label>
                        <div class="description">Enter your 16-digit card number.</div>
                    </div>
                    <div class="form-input">
                        <input type="text" id="card_number" name="card_number" placeholder="XXXX XXXX XXXX XXXX" required pattern="\d{16}" title="16-digit card number" />
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-info">
                        <label for="expiry_date">Expiry Date</label>
                        <div class="description">MM/YY</div>
                    </div>
                    <div class="form-input">
                        <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" required pattern="(0[1-9]|1[0-2])\/\d{2}" title="MM/YY format" />
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-info">
                        <label for="cvv">CVV</label>
                        <div class="description">3 or 4 digit code on the back of your card.</div>
                    </div>
                    <div class="form-input">
                        <input type="text" id="cvv" name="cvv" placeholder="XXX" required pattern="\d{3,4}" title="3 or 4 digit CVV" />
                    </div>
                </div>
                
                <div class="divider"></div>

                <button type="submit" class="submit-button">Pay Now</button>
            </form>

            <div class="alert-box">
                <img src="img/avertisement.svg" alt="Alert Icon">
                <span>Please do not make any transactions, payments, or<br> contact outside of Skillid. This platform is designed to protect you.</span>
            </div>
        </div>
    </div>
</body>
</html>