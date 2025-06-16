<?php
session_start();
require 'config.php';

if (!isset($_SESSION['id_utilisateur']) || $_SESSION['role'] !== 'client') {
    header("Location: login.php");
    exit();
}

$id_client = $_SESSION['id_utilisateur'];

// Fetch client details
$stmt = $pdo->prepare("SELECT nom, prenom FROM Utilisateur WHERE id_utilisateur = ?");
$stmt->execute([$id_client]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    // Handle case where client user is not found, though it should not happen if session is valid
    session_destroy();
    header("Location: login.php");
    exit();
}

$client_name = htmlspecialchars($client['prenom'] . ' ' . $client['nom']);

// Handle cancellation
if (isset($_GET['action']) && $_GET['action'] === 'cancel_demand' && isset($_GET['id_reservation'])) {
    $id_reservation_to_cancel = (int)$_GET['id_reservation'];
    $stmt = $pdo->prepare("UPDATE Reservation SET statut = 'annulé' WHERE id_reservation = ? AND id_client = ? AND statut = 'en_attente'");
    $stmt->execute([$id_reservation_to_cancel, $id_client]);
    header("Location: client_Dashboard.php?status=demand_cancelled");
    exit();
}

// Handle quote acceptance
if (isset($_GET['action']) && $_GET['action'] === 'accept_quote' && isset($_GET['id_devis'])) {
    $id_devis_to_accept = (int)$_GET['id_devis'];
    try {
        $pdo->beginTransaction();

        // Update Devis status to 'accepté'
        $stmt = $pdo->prepare("UPDATE Devis SET statut_devis = 'accepté' WHERE id_devis = ?");
        $stmt->execute([$id_devis_to_accept]);

        // Get reservation ID from devis
        $stmt_res = $pdo->prepare("SELECT id_reservation FROM Devis WHERE id_devis = ?");
        $stmt_res->execute([$id_devis_to_accept]);
        $id_reservation_from_devis = $stmt_res->fetchColumn();

        // Update Reservation status and client_accepted_meeting
        $stmt_update_res = $pdo->prepare("UPDATE Reservation SET statut = 'acceptée', client_accepted_meeting = TRUE WHERE id_reservation = ? AND id_client = ?");
        $stmt_update_res->execute([$id_reservation_from_devis, $id_client]);

        $pdo->commit();
        header("Location: payment_page.php?id_devis=" . $id_devis_to_accept);
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Error accepting quote: " . $e->getMessage();
    }
}

// Handle quote refusal
if (isset($_GET['action']) && $_GET['action'] === 'refuse_quote' && isset($_GET['id_devis'])) {
    $id_devis_to_refuse = (int)$_GET['id_devis'];
    try {
        $stmt = $pdo->prepare("UPDATE Devis SET statut_devis = 'refusé' WHERE id_devis = ?");
        $stmt->execute([$id_devis_to_refuse]);
        header("Location: client_Dashboard.php?status=quote_refused");
        exit();
    } catch (PDOException $e) {
        $message = "Error refusing quote: " . $e->getMessage();
    }
}

// Fetch pending demands (reservations)
$stmt = $pdo->prepare("SELECT r.*, u.nom AS artisan_nom, u.prenom AS artisan_prenom FROM Reservation r JOIN Prestataire p ON r.id_prestataire = p.id_prestataire JOIN Utilisateur u ON p.id_utilisateur = u.id_utilisateur WHERE r.id_client = ? AND r.statut = 'en_attente' ORDER BY r.date_debut DESC");
$stmt->execute([$id_client]);
$pending_demands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch quotes awaiting client review (statut_devis = 'en_attente')
$stmt = $pdo->prepare("SELECT r.*, u.nom AS artisan_nom, u.prenom AS artisan_prenom, d.id_devis, d.cout_total, d.acompte, d.statut_devis FROM Reservation r JOIN Prestataire p ON r.id_prestataire = p.id_prestataire JOIN Utilisateur u ON p.id_utilisateur = u.id_utilisateur JOIN Devis d ON r.id_reservation = d.id_reservation WHERE r.id_client = ? AND d.statut_devis = 'en_attente' ORDER BY r.date_debut DESC");
$stmt->execute([$id_client]);
$quotes_awaiting_review = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch accepted projects (reservations where client_accepted_meeting is TRUE)
$stmt = $pdo->prepare("SELECT r.*, u.nom AS artisan_nom, u.prenom AS artisan_prenom, d.id_devis, d.cout_total, d.acompte, d.statut_devis FROM Reservation r JOIN Prestataire p ON r.id_prestataire = p.id_prestataire JOIN Utilisateur u ON p.id_utilisateur = u.id_utilisateur JOIN Devis d ON r.id_reservation = d.id_reservation WHERE r.id_client = ? AND r.statut = 'acceptée' AND r.client_accepted_meeting = TRUE ORDER BY r.date_debut DESC");
$stmt->execute([$id_client]);
$accepted_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch payments
$stmt = $pdo->prepare("SELECT p.*, d.id_devis, u.nom AS artisan_nom, u.prenom AS artisan_prenom FROM Paiement p JOIN Devis d ON p.id_devis = d.id_devis JOIN Prestataire pr ON d.id_prestataire = pr.id_prestataire JOIN Utilisateur u ON pr.id_utilisateur = u.id_utilisateur WHERE p.id_client = ? ORDER BY p.date_paiement DESC");
$stmt->execute([$id_client]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary card data
$tasks_in_progress = count($accepted_demands); // Assuming accepted demands are tasks in progress
$total_paid_stmt = $pdo->prepare("SELECT SUM(montant) AS total_paid FROM Paiement WHERE id_client = ? AND statut_paiement = 'effectué'");
$total_paid_stmt->execute([$id_client]);
$total_paid = $total_paid_stmt->fetch(PDO::FETCH_ASSOC)['total_paid'] ?? 0;

// Placeholder for evaluations - needs actual logic
$evaluations_count = 0; // You'll need to implement logic to count pending evaluations
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - Skilled</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700;800&family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Client_dashborad.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <img src="img/skilled_logo.svg" alt="Skilled Logo" class="logo-image">
            <div class="logo-text">Skilled<span class="logo-dot">.</span></div>
        </div>
        <div class="header-right">
            <button class="logout-button" onclick="location.href='logout.php'">Logout</button>
            <span class="client-name"><?= $client_name ?></span>
            <a href="Profil_Client.php"><img src="img/profil.svg" alt="Profile Picture" class="profile-image"></a>
        </div>
    </header>
    <div class="header-divider"></div>

    <main class="dashboard-content">
        <h1 class="welcome-message">Welcome, <?= $client_name ?></h1>

        <section class="summary-cards">
            <div class="card in-progress">
                <div class="card-number"><?= count($accepted_projects) ?></div>
                <div class="card-text">Projects In Progress</div>
            </div>
            <div class="card evaluation">
                <div class="card-number"><?= $evaluations_count ?></div>
                <div class="card-text">Pending Evaluations</div>
            </div>
            <div class="card total-paid">
                <div class="card-number"><?= number_format($total_paid, 2) ?><span class="currency"> DH</span></div>
                <div class="card-text">Total Amount Paid</div>
            </div>
        </section>

        <section class="demands-section">
            <h2 class="section-title">Demandes en attente</h2>
            <div class="reservation-list">
                <?php if (count($pending_demands) > 0): ?>
                    <?php foreach ($pending_demands as $demand): ?>
                        <div class="reservation-item">
                            <div class="reservation-details">
                                <div class="detail-group">
                                    <span class="detail-label">Artisan:</span>
                                    <span class="detail-value"><?= htmlspecialchars($demand['artisan_prenom'] . ' ' . $demand['artisan_nom']) ?></span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Service:</span>
                                    <span class="detail-value"><?= htmlspecialchars($demand['description_service']) ?></span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Start date:</span>
                                    <span class="detail-value"><?= htmlspecialchars($demand['date_debut']) ?></span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Estimated Days:</span>
                                    <span class="detail-value"><?= htmlspecialchars($demand['nb_jours_estime']) ?> Days</span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Status:</span>
                                    <span class="detail-value status-pending"><?= htmlspecialchars($demand['statut']) ?></span>
                                </div>
                            </div>
                            <div class="reservation-actions">
                                <a href="client_Dashboard.php?action=cancel_demand&id_reservation=<?= $demand['id_reservation'] ?>" class="button cancel-button" onclick="return confirm('Are you sure you want to cancel this demand?');">Cancel</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No pending demands.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="quotes-section">
            <h2 class="section-title">Quotes Awaiting Your Review</h2>
            <div class="reservation-list">
                <?php if (count($quotes_awaiting_review) > 0): ?>
                    <?php foreach ($quotes_awaiting_review as $quote): ?>
                        <div class="reservation-item">
                            <div class="reservation-details">
                                <div class="detail-group">
                                    <span class="detail-label">Artisan:</span>
                                    <span class="detail-value"><?= htmlspecialchars($quote['artisan_prenom'] . ' ' . $quote['artisan_nom']) ?></span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Service:</span>
                                    <span class="detail-value"><?= htmlspecialchars($quote['description_service']) ?></span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Start date:</span>
                                    <span class="detail-value"><?= htmlspecialchars($quote['date_debut']) ?></span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Total Cost:</span>
                                    <span class="detail-value"><?= number_format($quote['cout_total'], 2) ?> DH</span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Down Payment:</span>
                                    <span class="detail-value"><?= number_format($quote['acompte'], 2) ?> DH</span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Status:</span>
                                    <span class="detail-value status-pending"><?= htmlspecialchars($quote['statut_devis']) ?></span>
                                </div>
                            </div>
                            <div class="reservation-actions">
                                <a href="Create_quote.php?id_devis=<?= $quote['id_devis'] ?>" class="button see-quote-button">See Quote</a>
                                <?php if ($quote['statut_devis'] === 'en_attente'): ?>
                                    <a href="client_Dashboard.php?action=accept_quote&id_devis=<?= $quote['id_devis'] ?>" class="button accept-button" onclick="return confirm('Are you sure you want to accept this quote? You will be redirected to the payment page.');">Accept Quote</a>
                                    <a href="client_Dashboard.php?action=refuse_quote&id_devis=<?= $quote['id_devis'] ?>" class="button cancel-button" onclick="return confirm('Are you sure you want to refuse this quote?');">Refuse Quote</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No quotes awaiting your review.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="projects-in-progress-section">
            <h2 class="section-title">Projects In Progress</h2>
            <div class="reservation-list">
                <?php if (count($accepted_projects) > 0): ?>
                    <?php foreach ($accepted_projects as $project): ?>
                        <div class="reservation-item">
                            <div class="reservation-details">
                                <div class="detail-group">
                                    <span class="detail-label">Artisan:</span>
                                    <span class="detail-value"><?= htmlspecialchars($project['artisan_prenom'] . ' ' . $project['artisan_nom']) ?></span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Service:</span>
                                    <span class="detail-value"><?= htmlspecialchars($project['description_service']) ?></span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Start date:</span>
                                    <span class="detail-value"><?= htmlspecialchars($project['date_debut']) ?></span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Total Cost:</span>
                                    <span class="detail-value"><?= number_format($project['cout_total'], 2) ?> DH</span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Down Payment:</span>
                                    <span class="detail-value"><?= number_format($project['acompte'], 2) ?> DH</span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Status:</span>
                                    <span class="detail-value status-completed"><?= htmlspecialchars($project['statut_devis']) ?></span>
                                </div>
                            </div>
                            <div class="reservation-actions">
                                <!-- Actions for accepted projects, e.g., view project details, mark as complete -->
                                <a href="view_project.php?id_reservation=<?= $project['id_reservation'] ?>" class="button see-quote-button">View Project</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No projects in progress.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="payments-section">
            <h2 class="section-title">Payments</h2>
            <div class="payment-list">
                <?php if (count($payments) > 0): ?>
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-item">
                            Payment of <?= number_format($payment['montant'], 2) ?> DH made on <?= htmlspecialchars(date('Y-m-d', strtotime($payment['date_paiement']))) ?> for quote #<?= htmlspecialchars($payment['id_devis']) ?> (Artisan: <?= htmlspecialchars($payment['artisan_prenom'] . ' ' . $payment['artisan_nom']) ?>). Status: <?= htmlspecialchars($payment['statut_paiement']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No payments made yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="history-section">
            <h2 class="section-title">History</h2>
            <div class="history-list">
                <!-- History items will be populated here based on completed reservations/projects -->
                <p>No history items yet.</p>
            </div>
        </section>
    </main>
</body>
</html>