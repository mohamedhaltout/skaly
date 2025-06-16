<?php
session_start();
require 'config.php';

if (!isset($_SESSION['id_utilisateur']) || $_SESSION['role'] !== 'prestataire') {
    header("Location: login.php");
    exit();
}

$id_prestataire = $_SESSION['id_utilisateur'];

// Fetch artisan details
$stmt = $pdo->prepare("SELECT u.nom, u.prenom, p.photo FROM Prestataire p JOIN Utilisateur u ON p.id_utilisateur = u.id_utilisateur WHERE p.id_utilisateur = ?");
$stmt->execute([$id_prestataire]);
$artisan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$artisan) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$artisan_name = htmlspecialchars($artisan['prenom'] . ' ' . $artisan['nom']);
$artisan_photo = htmlspecialchars(get_image_path($artisan['photo'], 'prestataire'));

// Handle demand actions (accept/refuse)
if (isset($_GET['action']) && isset($_GET['id_reservation'])) {
    $id_reservation = (int)$_GET['id_reservation'];
    if ($_GET['action'] === 'accept_demand') {
        $stmt = $pdo->prepare("UPDATE Reservation SET statut = 'acceptée' WHERE id_reservation = ? AND id_prestataire = ? AND statut = 'en_attente'");
        $stmt->execute([$id_reservation, $id_prestataire]);
        header("Location: artisan_dashboard.php?status=demand_accepted");
        exit();
    } elseif ($_GET['action'] === 'refuse_demand') {
        // Delete the reservation permanently
        $stmt = $pdo->prepare("DELETE FROM Reservation WHERE id_reservation = ? AND id_prestataire = ? AND statut = 'en_attente'");
        $stmt->execute([$id_reservation, $id_prestataire]);
        header("Location: artisan_dashboard.php?status=demand_refused_deleted");
        exit();
    } elseif ($_GET['action'] === 'accept_meeting') {
        $stmt = $pdo->prepare("UPDATE Reservation SET artisan_accepted_meeting = TRUE WHERE id_reservation = ? AND id_prestataire = ?");
        $stmt->execute([$id_reservation, $id_prestataire]);
        $stmt_check = $pdo->prepare("SELECT client_accepted_meeting FROM Reservation WHERE id_reservation = ?");
        $stmt_check->execute([$id_reservation]);
        if ($stmt_check->fetchColumn()) {
            $pdo->prepare("UPDATE Reservation SET statut = 'en_cours' WHERE id_reservation = ?")->execute([$id_reservation]);
        }
        header("Location: artisan_dashboard.php?status=meeting_accepted");
        exit();
    } elseif ($_GET['action'] === 'mark_project_ended') {
        $stmt = $pdo->prepare("UPDATE Reservation SET project_ended_artisan = TRUE WHERE id_reservation = ? AND id_prestataire = ?");
        $stmt->execute([$id_reservation, $id_prestataire]);
        header("Location: artisan_dashboard.php?status=project_marked_ended");
        exit();
    }
}

// Fetch pending demands
$stmt = $pdo->prepare("SELECT r.*, u.nom AS client_nom, u.prenom AS client_prenom FROM Reservation r JOIN Utilisateur u ON r.id_client = u.id_utilisateur WHERE r.id_prestataire = ? AND r.statut = 'en_attente' ORDER BY r.date_debut DESC");
$stmt->execute([$id_prestataire]);
$pending_demands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch accepted demands (reservations where artisan has accepted the meeting)
$stmt = $pdo->prepare("SELECT r.*, u.nom AS client_nom, u.prenom AS client_prenom, d.id_devis, d.statut_devis FROM Reservation r JOIN Utilisateur u ON r.id_client = u.id_utilisateur LEFT JOIN Devis d ON r.id_reservation = d.id_reservation WHERE r.id_prestataire = ? AND r.statut IN ('acceptée','en_cours') ORDER BY r.date_debut DESC");
$stmt->execute([$id_prestataire]);
$accepted_demands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch payments received
$stmt = $pdo->prepare("SELECT p.*, d.id_devis, u.nom AS client_nom, u.prenom AS client_prenom FROM Paiement p JOIN Devis d ON p.id_devis = d.id_devis JOIN Reservation r ON d.id_reservation = r.id_reservation JOIN Utilisateur u ON r.id_client = u.id_utilisateur WHERE p.id_prestataire = ? ORDER BY p.date_paiement DESC");
$stmt->execute([$id_prestataire]);
$payments_received = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary card data
$received_requests_count = count($pending_demands);
$accepted_requests_count = count($accepted_demands);
// Placeholder for completed projects and average rating - needs actual logic
$completed_projects_count = 0;
$average_rating = 0.0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prestataire Dashboard - Skilled</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700;800&family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="artisan_dashboard.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <img src="img/skilled_logo.svg" alt="Skilled Logo" class="logo-image">
            <div class="logo-text">Skilled<span class="logo-dot">.</span></div>
        </div>
        <div class="header-right">
            <button class="logout-button" onclick="location.href='logout.php'">Logout</button>
            <span class="client-name"><?= $artisan_name ?></span>
            <a href="Profil_Client.php"><img src="<?= $artisan_photo ?>" alt="Profile Picture" class="profile-image"></a>
        </div>
    </header>
    <div class="header-divider"></div>

    <main class="dashboard-content">
        <h1 class="welcome-message">Welcome, <?= $artisan_name ?></h1>

        <section class="summary-cards">
            <div class="card received-requests">
                <div class="card-number"><?= $received_requests_count ?></div>
                <div class="card-text">Demandes reçues</div>
            </div>
            <div class="card accepted-requests">
                <div class="card-number"><?= $accepted_requests_count ?></div>
                <div class="card-text">Demandes acceptées</div>
            </div>
            <div class="card completed-projects">
                <div class="card-number"><?= $completed_projects_count ?></div>
                <div class="card-text">Projets terminés</div>
            </div>
            <div class="card average-rating">
                <div class="card-number"><?= number_format($average_rating, 1) ?></div>
                <div class="card-text">Note moyenne</div>
            </div>
        </section>

        <section class="demandes-section">
            <h2 class="section-title">Demandes en attente</h2>
            <div class="demande-list">
                <?php if (count($pending_demands) > 0): ?>
                    <?php foreach ($pending_demands as $demand): ?>
                        <div class="demande-item">
                            <div class="demande-details">
                                <div class="detail-group">
                                    <span class="detail-label">Client:</span>
                                    <span class="detail-value"><?= htmlspecialchars($demand['client_prenom'] . ' ' . $demand['client_nom']) ?></span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Service:</span>
                                    <span class="detail-value"><?= htmlspecialchars($demand['description_service']) ?></span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Date début:</span>
                                    <span class="detail-value"><?= htmlspecialchars($demand['date_debut']) ?></span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Estimated Days:</span>
                                    <span class="detail-value"><?= htmlspecialchars($demand['nb_jours_estime']) ?> Days</span>
                                </div>
                            </div>
                            <div class="demande-actions">
                                <a href="demande_service.php?id_reservation=<?= $demand['id_reservation'] ?>&view_only=true" class="button view-request-button">See The Request</a>
                                <a href="artisan_dashboard.php?action=refuse_demand&id_reservation=<?= $demand['id_reservation'] ?>" class="button refuse-button" onclick="return confirm('Are you sure you want to refuse and delete this demand?');">Refuse</a>
                                <a href="artisan_dashboard.php?action=accept_demand&id_reservation=<?= $demand['id_reservation'] ?>" class="button accept-button" onclick="return confirm('Are you sure you want to accept this demand?');">Accept</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No pending demands.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="accepted-demandes-section">
            <h2 class="section-title">Accepted Demands</h2>
            <div class="accepted-demande-list">
                <?php if (count($accepted_demands) > 0): ?>
                    <?php foreach ($accepted_demands as $demand): ?>
                        <div class="accepted-demande-item">
                            <div class="accepted-demande-details">
                                <div class="detail-group">
                                    <span class="detail-label">Client:</span>
                                    <span class="detail-value"><?= htmlspecialchars($demand['client_prenom'] . ' ' . $demand['client_nom']) ?></span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Service:</span>
                                    <span class="detail-value"><?= htmlspecialchars($demand['description_service']) ?></span>
                                </div>
                                <div class="detail-group">
                                    <span class="detail-label">Start date:</span>
                                    <span class="detail-value"><?= htmlspecialchars($demand['date_debut']) ?></span>
                                </div>
                                <?php if ($demand['budget_total']): ?>
                                    <div class="detail-group">
                                        <span class="detail-label">Total Budget:</span>
                                        <span class="detail-value"><?= number_format($demand['budget_total'], 2) ?> DH</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($demand['tarif_par_jour']): ?>
                                    <div class="detail-group">
                                        <span class="detail-label">Daily Rate:</span>
                                        <span class="detail-value"><?= number_format($demand['tarif_par_jour'], 2) ?> DH</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="accepted-demande-actions">
                                <?php if (!$demand['id_devis']): ?>
                                    <a href="Create_quote.php?id_reservation=<?= $demand['id_reservation'] ?>" class="button create-devis-button">Create Quote</a>
                                <?php else: ?>
                                    <a href="Create_quote.php?id_devis=<?= $demand['id_devis'] ?>" class="button view-request-button">See Quote</a>
                                <?php endif; ?>
                                <?php if (!$demand['artisan_accepted_meeting']): ?>
                                    <a href="artisan_dashboard.php?action=accept_meeting&id_reservation=<?= $demand['id_reservation'] ?>" class="button accept-button">Confirmer la rencontre</a>
                                <?php endif; ?>
                                <?php if ($demand['artisan_accepted_meeting'] && !$demand['project_ended_artisan']): ?>
                                    <a href="artisan_dashboard.php?action=mark_project_ended&id_reservation=<?= $demand['id_reservation'] ?>" class="button refuse-button" onclick="return confirm('Mark this project as completed?');">Mark Completed</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No accepted demands.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="payments-section">
            <h2 class="section-title">Paiements reçus</h2>
            <div class="payment-list">
                <?php if (count($payments_received) > 0): ?>
                    <?php foreach ($payments_received as $payment): ?>
                        <div class="payment-item">
                            Payment of <?= number_format($payment['montant'], 2) ?> DH received on <?= htmlspecialchars(date('Y-m-d', strtotime($payment['date_paiement']))) ?> for quote #<?= htmlspecialchars($payment['id_devis']) ?> (Client: <?= htmlspecialchars($payment['client_prenom'] . ' ' . $payment['client_nom']) ?>). Status: <?= htmlspecialchars($payment['statut_paiement']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No payments received yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="experiences-section">
            <h2 class="section-title">Vos expériences</h2>
            <div class="experience-list">
                <!-- Existing experience items -->
                <div class="experience-item">
                    <div class="experience-details">
                        <div class="detail-group">
                            <span class="detail-label">Projet:</span>
                            <span class="detail-value">Rénovation cuisine</span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Année:</span>
                            <span class="detail-value">2023</span>
                        </div>
                    </div>
                    <div class="experience-actions">
                        <button class="button modify-button">Modifier</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-buttons">
            <button class="button large-button add-experience-button" onclick="location.href='ad_exeperience.php'">Ajouter une nouvelle expérience</button>
            <button class="button large-button manage-availability-button" onclick="location.href='manage_availability.php'">Gérer mes disponibilités</button>
            <button class="button large-button view-payments-button">Voir mes paiements</button>
        </section>
    </main>
</body>
</html>