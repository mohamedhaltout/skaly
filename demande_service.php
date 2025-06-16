<?php
session_start();
require 'config.php';

if (!isset($_SESSION['id_utilisateur'])) {
    header("Location: login.php");
    exit();
}

$id_utilisateur_session = $_SESSION['id_utilisateur'];
$user_role = $_SESSION['role'];
$view_only = isset($_GET['view_only']) && $_GET['view_only'] === 'true';

$id_client = null;
$id_prestataire = null;
$reservation_details = null;
$artisan = null;
$client_info = null;

if (($user_role === 'client' || $user_role === 'prestataire') && !$view_only) {
    // Fetch the actual id_client from the Client table using id_utilisateur
    $stmt_client = $pdo->prepare("SELECT id_client FROM Client WHERE id_utilisateur = ?");
    $stmt_client->execute([$id_utilisateur_session]);
    $client_data = $stmt_client->fetch(PDO::FETCH_ASSOC);

    if (!$client_data) {
        // If the user is an artisan acting as a client, create a client record on the fly
        $stmt_tel = $pdo->prepare("SELECT telephone FROM Prestataire WHERE id_utilisateur = ?");
        $stmt_tel->execute([$id_utilisateur_session]);
        $tel = $stmt_tel->fetchColumn() ?: '0000000000';
        $stmt_ins = $pdo->prepare("INSERT INTO Client (id_utilisateur, telephone) VALUES (?, ?)");
        $stmt_ins->execute([$id_utilisateur_session, $tel]);
        $id_client = $pdo->lastInsertId();
    } else {
        $id_client = $client_data['id_client'];
    }
    $id_prestataire = isset($_GET['id_prestataire']) ? (int)$_GET['id_prestataire'] : 0;

    if ($id_prestataire === 0) {
        die("Artisan not specified.");
    }

    // Fetch artisan details for new request
    $stmt = $pdo->prepare("SELECT u.nom, u.prenom, p.specialite FROM Prestataire p JOIN Utilisateur u ON p.id_utilisateur = u.id_utilisateur WHERE p.id_prestataire = ?");
    $stmt->execute([$id_prestataire]);
    $artisan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$artisan) {
        die("Artisan not found.");
    }
} elseif ($view_only && isset($_GET['id_reservation'])) {
    $id_reservation = (int)$_GET['id_reservation'];

    // Fetch reservation details
    $stmt_res = $pdo->prepare("SELECT r.*, u_client.nom AS client_nom, u_client.prenom AS client_prenom, u_artisan.nom AS artisan_nom, u_artisan.prenom AS artisan_prenom, p.specialite FROM Reservation r JOIN Client c ON r.id_client = c.id_client JOIN Utilisateur u_client ON c.id_utilisateur = u_client.id_utilisateur JOIN Prestataire pr ON r.id_prestataire = pr.id_prestataire JOIN Utilisateur u_artisan ON pr.id_utilisateur = u_artisan.id_utilisateur JOIN Categories p ON pr.id_categorie = p.id_categorie WHERE r.id_reservation = ?");
    $stmt_res->execute([$id_reservation]);
    $reservation_details = $stmt_res->fetch(PDO::FETCH_ASSOC);

    if (!$reservation_details) {
        die("Reservation not found.");
    }

    // Set artisan and client info for display in view-only mode
    $artisan = ['nom' => $reservation_details['artisan_nom'], 'prenom' => $reservation_details['artisan_prenom'], 'specialite' => $reservation_details['specialite']];
    $client_info = ['nom' => $reservation_details['client_nom'], 'prenom' => $reservation_details['client_prenom']];

    // Ensure the current user is authorized to view this reservation
    if ($user_role === 'client' && $reservation_details['id_client'] != $id_utilisateur_session) {
        die("Unauthorized access.");
    } elseif ($user_role === 'prestataire' && $reservation_details['id_prestataire'] != $id_utilisateur_session) {
        die("Unauthorized access.");
    }

} else {
    die("Invalid access or missing parameters.");
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$view_only) { // Only process POST if not in view-only mode
    $description_service = $_POST['description_service'] ?? '';
    $budget_total = $_POST['budget_total'] ?? null;
    $tarif_par_jour = $_POST['tarif_par_jour'] ?? null;

    // Convert empty strings to null for database insertion
    $budget_total = ($budget_total === '') ? null : (float)$budget_total;
    $tarif_par_jour = ($tarif_par_jour === '') ? null : (float)$tarif_par_jour;
    $date_debut = $_POST['date_debut'] ?? '';
    $nb_jours_estime = $_POST['nb_jours_estime'] ?? '';

    if (empty($description_service) || empty($date_debut) || empty($nb_jours_estime)) {
        $message = "Please fill all required fields.";
    } elseif ($budget_total === null && $tarif_par_jour === null) {
        $message = "Please provide either a total budget or a daily rate.";
    } else {
        // Ensure only one of budget_total or tarif_par_jour is set, as per database CHECK constraint
        if ($budget_total !== null && $tarif_par_jour !== null) {
            // If both are provided, prioritize budget_total and set tarif_par_jour to null
            $tarif_par_jour = null;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO Reservation (id_client, id_prestataire, description_service, budget_total, tarif_par_jour, date_debut, nb_jours_estime, statut) VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente')");
            $stmt->execute([$id_client, $id_prestataire, $description_service, $budget_total, $tarif_par_jour, $date_debut, $nb_jours_estime]);
            $message = "Service request submitted successfully!";
            header("Location: client_Dashboard.php?status=demande_sent");
            exit();
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300..700;1,300..700&family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=League+Spartan:wght@100..900&family=Lora:ital,wght@0,400..700;1,400..700&family=Nunito:ital,wght@0,200..1000;1,200..1000&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Red+Hat+Text:ital,wght@0,300..700;1,300..700&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="demande_service.css">
    <title>Service Request</title>
</head>
<body>
    <div class="request-container">
        <div class="left-decoration"></div>
        <div class="right-content">
            <div class="title">
                <?php if ($view_only): ?>
                    Service Request Details (Client: <?= htmlspecialchars($client_info['prenom'] . ' ' . $client_info['nom']) ?>)
                <?php else: ?>
                    Service Request for <?= htmlspecialchars($artisan['prenom'] . ' ' . $artisan['nom']) ?> (<?= htmlspecialchars($artisan['specialite']) ?>)
                <?php endif; ?>
            </div>

            <?php if ($message): ?>
                <p class="message"><?= htmlspecialchars($message) ?></p>
            <?php endif; ?>

            <form method="POST" action="demande_service.php?id_prestataire=<?= $id_prestataire ?>" <?= $view_only ? 'onsubmit="return false;"' : '' ?>>
                <div class="form-group">
                    <div class="form-info">
                        <label for="description_service">Description of the service you need</label>
                        <div class="description">(e.g., I need to paint my room, I have a water leak)</div>
                    </div>
                    <div class="form-input">
                        <input type="text" id="description_service" name="description_service" placeholder="Describe the service" value="<?= htmlspecialchars($reservation_details['description_service'] ?? '') ?>" <?= $view_only ? 'disabled' : 'required' ?> />
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-info">
                        <label for="budget_total">Total cost</label>
                        <div class="description">(e.g., $300 to paint the bedroom)</div>
                    </div>
                    <div class="form-input">
                        <input type="number" step="0.01" id="budget_total" name="budget_total" placeholder="Budget (e.g., $300)" value="<?= htmlspecialchars($reservation_details['budget_total'] ?? '') ?>" <?= $view_only ? 'disabled' : '' ?> />
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-info">
                        <label for="tarif_par_jour">Daily budget</label>
                        <div class="description">(e.g., $100)</div>
                    </div>
                    <div class="form-input">
                        <input type="number" step="0.01" id="tarif_par_jour" name="tarif_par_jour" placeholder="Enter daily budget" value="<?= htmlspecialchars($reservation_details['tarif_par_jour'] ?? '') ?>" <?= $view_only ? 'disabled' : '' ?> />
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-info">
                        <label for="date_debut">Start Date</label>
                        <div class="description">(When do you want the service to start?)</div>
                    </div>
                    <div class="form-input">
                        <input type="date" id="date_debut" name="date_debut" value="<?= htmlspecialchars($reservation_details['date_debut'] ?? $_GET['date_dispo'] ?? '') ?>" <?= $view_only ? 'disabled' : 'required' ?> />
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-info">
                        <label for="nb_jours_estime">Estimated Number of Days</label>
                        <div class="description">(How many days do you estimate the project will take?)</div>
                    </div>
                    <div class="form-input">
                        <input type="number" id="nb_jours_estime" name="nb_jours_estime" value="<?= htmlspecialchars($reservation_details['nb_jours_estime'] ?? '') ?>" <?= $view_only ? 'disabled' : 'required' ?> />
                    </div>
                </div>
                
                <div class="info-text">
                    If the artisan accepts your service request, they will call you to discuss the details.
                </div>

                <div class="divider"></div>

                <?php if (!$view_only): ?>
                    <button type="submit" class="submit-button">Submit request</button>
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
