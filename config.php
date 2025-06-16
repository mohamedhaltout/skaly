<?php
$host = 'localhost';
$dbname = 'skilled';
$user = 'root'; 
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

function get_image_path($filename, $type = 'prestataire') {
    if (empty($filename)) {
        if ($type === 'prestataire') {
            return 'img/profil.png'; // Default for prestataire profile photo
        } elseif ($type === 'media') {
            return 'img/service-large.png'; // Default for media gallery
        }
        return 'img/default-placeholder.png'; // Generic default
    }
    if (str_starts_with($filename, 'http://') || str_starts_with($filename, 'https://')) {
        return $filename; // Already a full URL
    }
    if (str_starts_with($filename, 'uploads/')) {
        return $filename; // Already has the uploads prefix
    }

    if ($type === 'media') {
        return 'uploads/media/' . $filename;
    }
    return 'uploads/' . $filename;
}
// Function to process daily payments and project completion
function process_daily_payments($pdo) {
    $last_run_file = __DIR__ . '/last_payment_run.txt';
    $last_run_date = null;

    if (file_exists($last_run_file)) {
        $last_run_date = trim(file_get_contents($last_run_file));
    }

    $today = date('Y-m-d');

    // Prevent running more than once a day
    if ($last_run_date === $today) {
        return;
    }

    // Update last run date
    file_put_contents($last_run_file, $today);

    // Find reservations where both client and artisan have accepted the meeting
    $stmt = $pdo->prepare("SELECT r.*, p.accepte_budget_global, p.tarif_journalier FROM Reservation r JOIN Prestataire p ON r.id_prestataire = p.id_prestataire WHERE r.client_accepted_meeting = TRUE AND r.artisan_accepted_meeting = TRUE AND r.statut = 'acceptée' AND r.date_debut <= CURDATE() AND (r.date_fin IS NULL OR r.date_fin >= CURDATE()) AND r.project_ended_client = FALSE AND r.project_ended_artisan = FALSE");
    $stmt->execute();
    $active_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($active_projects as $project) {
        $id_reservation = $project['id_reservation'];
        $id_client = $project['id_client'];
        $id_prestataire = $project['id_prestataire'];
        $tarif_journalier_prestataire = $project['tarif_journalier'];
        $accepte_budget_global = $project['accepte_budget_global'];
        $date_debut_project = new DateTime($project['date_debut']);
        $nb_jours_estime = $project['nb_jours_estime'];

        // Check if a quote exists for this reservation
        $stmt_devis = $pdo->prepare("SELECT * FROM Devis WHERE id_reservation = ? AND statut_devis = 'accepté'");
        $stmt_devis->execute([$id_reservation]);
        $devis = $stmt_devis->fetch(PDO::FETCH_ASSOC);

        if (!$devis) {
            continue; // Skip if no accepted quote exists
        }

        $id_devis = $devis['id_devis'];
        $cout_total = $devis['cout_total'];

        $amount_to_pay = 0;
        $payment_type = '';

        if ($accepte_budget_global) {
            // Calculate daily payment based on global budget
            $total_days = $nb_jours_estime; // Assuming nb_jours_estime is the total project duration
            if ($total_days > 0) {
                $amount_to_pay = $cout_total / $total_days;
                $payment_type = 'global';
            }
        } else {
            // Pay based on daily rate
            $amount_to_pay = $tarif_journalier_prestataire;
            $payment_type = 'par_jour';
        }

        if ($amount_to_pay > 0) {
            // Check if payment for today has already been made
            $stmt_check_payment = $pdo->prepare("SELECT COUNT(*) FROM Paiement WHERE id_devis = ? AND date(date_paiement) = CURDATE() AND type_paiement = ?");
            $stmt_check_payment->execute([$id_devis, $payment_type]);
            if ($stmt_check_payment->fetchColumn() == 0) {
                // Insert daily payment
                $stmt_insert_payment = $pdo->prepare("INSERT INTO Paiement (id_devis, id_client, id_prestataire, montant, type_paiement, methode_paiement, statut_paiement) VALUES (?, ?, ?, ?, ?, 'system_daily', 'effectué')");
                $stmt_insert_payment->execute([$id_devis, $id_client, $id_prestataire, $amount_to_pay, $payment_type]);
            }
        }

        // Check for project completion
        if ($project['project_ended_client'] && $project['project_ended_artisan']) {
            $stmt_complete_project = $pdo->prepare("UPDATE Reservation SET statut = 'terminée' WHERE id_reservation = ?");
            $stmt_complete_project->execute([$id_reservation]);
        }
    }
}

// Call the daily payment processing function
process_daily_payments($pdo);
?>
