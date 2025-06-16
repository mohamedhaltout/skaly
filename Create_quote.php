<?php
session_start();
require 'config.php';

$is_artisan = isset($_SESSION['id_utilisateur']) && $_SESSION['role'] === 'prestataire';
$is_client = isset($_SESSION['id_utilisateur']) && $_SESSION['role'] === 'client';

if (!$is_artisan && !$is_client) {
    header("Location: login.php");
    exit();
}

$id_utilisateur = $_SESSION['id_utilisateur'];
$view_only = isset($_GET['view_only']) && $_GET['view_only'] === 'true';

$id_reservation = isset($_GET['id_reservation']) ? (int)$_GET['id_reservation'] : 0;
$id_devis = isset($_GET['id_devis']) ? (int)$_GET['id_devis'] : 0;

$quote_data = null;
$reservation_data = null;
$message = '';

if ($id_devis > 0) {
    // Viewing an existing quote
    $stmt = $pdo->prepare("SELECT d.*, r.description_service, r.id_client, r.id_prestataire, u_client.nom AS client_nom, u_client.prenom AS client_prenom, u_artisan.nom AS artisan_nom, u_artisan.prenom AS artisan_prenom FROM Devis d JOIN Reservation r ON d.id_reservation = r.id_reservation JOIN Utilisateur u_client ON r.id_client = u_client.id_utilisateur JOIN Utilisateur u_artisan ON r.id_prestataire = u_artisan.id_utilisateur WHERE d.id_devis = ?");
    $stmt->execute([$id_devis]);
    $quote_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quote_data) {
        die("Quote not found.");
    }

    // Ensure the user has permission to view this quote
    if (($is_artisan && $quote_data['id_prestataire'] !== $id_utilisateur) || ($is_client && $quote_data['id_client'] !== $id_utilisateur)) {
        die("Access denied.");
    }
    $reservation_data = $pdo->prepare("SELECT * FROM Reservation WHERE id_reservation = ?")->execute([$quote_data['id_reservation']])->fetch(PDO::FETCH_ASSOC);

} elseif ($id_reservation > 0 && $is_artisan) {
    // Creating a new quote for a reservation
    $stmt = $pdo->prepare("SELECT r.*, u.nom AS client_nom, u.prenom AS client_prenom FROM Reservation r JOIN Utilisateur u ON r.id_client = u.id_utilisateur WHERE r.id_reservation = ? AND r.id_prestataire = ? AND r.statut = 'acceptée'");
    $stmt->execute([$id_reservation, $id_utilisateur]);
    $reservation_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reservation_data) {
        die("Reservation not found or not accepted by you.");
    }

    // Check if a quote already exists for this reservation
    $stmt = $pdo->prepare("SELECT id_devis FROM Devis WHERE id_reservation = ?");
    $stmt->execute([$id_reservation]);
    if ($stmt->fetch()) {
        $message = "A quote for this reservation already exists. You can view it from your dashboard.";
        $view_only = true; // Force view mode if quote already exists
    }

} else {
    die("Invalid access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_artisan && !$view_only) {
    $date_debut_travaux = $_POST['date_debut_travaux'] ?? '';
    $cout_total = $_POST['cout_total'] ?? null;
    $tarif_journalier = $_POST['tarif_journalier'] ?? null;
    $acompte = $_POST['acompte'] ?? null;

    if (empty($date_debut_travaux) || empty($cout_total) || empty($acompte)) {
        $message = "Please fill all required fields.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO Devis (id_reservation, id_prestataire, date_debut_travaux, cout_total, tarif_journalier, acompte, statut_devis) VALUES (?, ?, ?, ?, ?, ?, 'en_attente')");
            $stmt->execute([$id_reservation, $id_utilisateur, $date_debut_travaux, $cout_total, $tarif_journalier, $acompte]);
            $message = "Quote created successfully!";
            header("Location: artisan_dashboard.php?status=quote_created");
            exit();
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// Logic for client accepting quote
if ($is_client && isset($_GET['action']) && $_GET['action'] === 'accept_quote' && $id_devis > 0) {
    $stmt = $pdo->prepare("UPDATE Devis SET statut_devis = 'accepté' WHERE id_devis = ? AND id_reservation IN (SELECT id_reservation FROM Reservation WHERE id_client = ?)");
    $stmt->execute([$id_devis, $id_utilisateur]);
    if ($stmt->rowCount() > 0) {
        header("Location: payment_page.php?id_devis=" . $id_devis);
        exit();
    } else {
        $message = "Failed to accept quote or unauthorized.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $view_only ? 'View Quote' : 'Create a Quote' ?> - Skilled</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700;800&family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Create_quote.css">
</head>
<body>
    <div class="signup-container">
        <div class="left-decoration"></div>
        <div class="right-content">
            <div class="title"><?= $view_only ? 'View Quote' : 'Create a Quote' ?></div>

            <?php if ($message): ?>
                <p class="message"><?= htmlspecialchars($message) ?></p>
            <?php endif; ?>

            <?php if ($quote_data || $reservation_data): ?>
                <div class="quote-details-summary">
                    <h3>
                        <?php if ($is_artisan): ?>
                            Client: <?= htmlspecialchars($quote_data['client_prenom'] ?? $reservation_data['client_prenom']) ?> <?= htmlspecialchars($quote_data['client_nom'] ?? $reservation_data['client_nom']) ?>
                        <?php elseif ($is_client): ?>
                            Artisan: <?= htmlspecialchars($quote_data['artisan_prenom']) ?> <?= htmlspecialchars($quote_data['artisan_nom']) ?>
                        <?php endif; ?>
                    </h3>
                    <p>Service: <?= htmlspecialchars($quote_data['description_service'] ?? $reservation_data['description_service']) ?></p>
                    <?php if ($quote_data): ?>
                        <p>Quote Status: <span class="status-<?= strtolower($quote_data['statut_devis']) ?>"><?= htmlspecialchars($quote_data['statut_devis']) ?></span></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="Create_quote.php?<?= $id_devis ? 'id_devis=' . $id_devis : 'id_reservation=' . $id_reservation ?>">
                <div class="form-group">
                    <div class="form-info">
                        <label for="date_debut_travaux">Start date of work</label>
                        <div class="description">When the work is expected to begin.</div>
                    </div>
                    <div class="form-input">
                        <input type="date" id="date_debut_travaux" name="date_debut_travaux" value="<?= htmlspecialchars($quote_data['date_debut_travaux'] ?? '') ?>" <?= $view_only ? 'readonly' : 'required' ?> />
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-info">
                        <label for="cout_total">Total Cost</label>
                        <div class="description">The total estimated cost for the project.</div>
                    </div>
                    <div class="form-input">
                        <input type="number" step="0.01" id="cout_total" name="cout_total" placeholder="Enter total cost" value="<?= htmlspecialchars($quote_data['cout_total'] ?? '') ?>" <?= $view_only ? 'readonly' : 'required' ?> />
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-info">
                        <label for="tarif_journalier">Daily Rate (Optional)</label>
                        <div class="description">The daily rate if applicable.</div>
                    </div>
                    <div class="form-input">
                        <input type="number" step="0.01" id="tarif_journalier" name="tarif_journalier" placeholder="Enter daily rate" value="<?= htmlspecialchars($quote_data['tarif_journalier'] ?? '') ?>" <?= $view_only ? 'readonly' : '' ?> />
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-info">
                        <label for="acompte">Deposit Amount</label>
                        <div class="description">The initial deposit required.</div>
                    </div>
                    <div class="form-input">
                        <input type="number" step="0.01" id="acompte" name="acompte" placeholder="Enter deposit amount" value="<?= htmlspecialchars($quote_data['acompte'] ?? '') ?>" <?= $view_only ? 'readonly' : 'required' ?> />
                    </div>
                </div>

                <div class="divider"></div>

                <?php if ($is_artisan && !$view_only): ?>
                    <button type="submit" class="submit-button">Submit Quote</button>
                <?php elseif ($is_client && $quote_data && $quote_data['statut_devis'] === 'en_attente'): ?>
                    <a href="Create_quote.php?action=accept_quote&id_devis=<?= $id_devis ?>" class="submit-button confirm-quote-button">Confirm Quote and Proceed to Payment</a>
                <?php endif; ?>
            </form>

            <div class="alert-box">
                <img src="img/avertisement.svg" alt="Alert Icon">
                <span>Please do not make any transactions, payments, or<br> contact outside of Skillid. This platform is designed to protect you.</span>
            </div>
        </div>
    </div>
</body>
</html>