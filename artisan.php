<?php
require 'config.php';

if (!isset($_GET['id'])) {
    die("No artisan specified.");
}
$id_prestataire = (int) $_GET['id'];

$stmt = $pdo->query("SELECT nom, icone FROM Categories WHERE type = 'standard'");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT p.*, u.nom AS user_nom, u.prenom AS user_prenom, c.nom AS categorie_nom, p.statut_disponibilite FROM Prestataire p JOIN Utilisateur u ON p.id_utilisateur = u.id_utilisateur JOIN Categories c ON p.id_categorie = c.id_categorie WHERE p.id_prestataire = ?");
$stmt->execute([$id_prestataire]);
$prestataire = $stmt->fetch();
if (!$prestataire) die("Artisan not found.");

$stmt = $pdo->prepare("SELECT * FROM Experience_prestataire WHERE id_prestataire = ? ORDER BY date_project DESC LIMIT 1");
$stmt->execute([$id_prestataire]);
$experience = $stmt->fetch();

$media = [];
if ($experience) {
    $stmt = $pdo->prepare("SELECT * FROM Media_experience WHERE id_experience = ?");
    $stmt->execute([$experience['id_experience']]);
    $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$stmt = $pdo->prepare("SELECT date_debut, nb_jours_estime, date_fin FROM Reservation WHERE id_prestataire = ? AND statut = 'acceptée' AND (statut != 'terminée' OR (project_ended_client = FALSE OR project_ended_artisan = FALSE))");
$stmt->execute([$id_prestataire]);
$activeReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unavailableDates = [];

// Add dates from active reservations
foreach ($activeReservations as $res) {
    $startDate = new DateTime($res['date_debut']);
    $endDate = $res['date_fin'] ? new DateTime($res['date_fin']) : (clone $startDate)->modify('+' . ($res['nb_jours_estime'] - 1) . ' days');

    $currentDate = clone $startDate;
    while ($currentDate <= $endDate) {
        $unavailableDates[] = $currentDate->format('Y-m-d');
        $currentDate->modify('+1 day');
    }
}

// Add manually set unavailable dates
$stmt = $pdo->prepare("SELECT unavailable_date FROM Artisan_Availability WHERE id_prestataire = ?");
$stmt->execute([$id_prestataire]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $unavailableDates[] = $row['unavailable_date'];
}

$unavailableDates = array_unique($unavailableDates); // Remove duplicates
sort($unavailableDates); // Sort for consistency

$is_available = ($prestataire['statut_disponibilite'] === 'available');
if ($is_available && in_array(date('Y-m-d'), $unavailableDates)) {
    $is_available = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="artisan.css">
    <title>Artisan Profile</title>
</head>
<body>
        <header class="main-header">
        <div class="header-top-bar">
            <div class="header-logo">
                <a href="search.php"><img src="img/skilled_logo.svg" class="header-logo-img" /></a>
                <a href="search.php"><span class="header-logo-text">Skilled<span class="header-logo-dot">.</span></span></a>
            </div>
            <div class="header-search-container">
                <form method="GET" action="search.php" class="header-search-form">
                    <input type="text" name="search" placeholder="What Service are you looking for today..." class="header-search-input" />
                    <div class="header-search-icon-wrapper">
                        <button type="submit" style="background: none; border: none; padding: 0;">
                            <img src="img/search.png" alt="Search" class="header-search-icon" />
                        </button>
                    </div>
                </form>
            </div>
            <div class="header-right-nav">
                <img src="img/Notification.svg" class="header-icon" />
                <img src="img/message.svg" class="header-icon" />
                <img src="img/favorite.svg" class="header-icon" />
                <span class="header-orders">Orders</span>
                <span class="header-switch">Switch to Artisans</span>
                <a href="Profil_Client.php"><img src="img/profil.svg" class="header-profile-pic" /></a>
            </div>
        </div>
        <div class="header-line"></div>
        <div class="categories-nav-bar">
            <div class="categories-scroll-slider">
                <?php foreach ($categories as $cat): ?>
                    <span class="category-item">
                        <img src="img/icons/<?= htmlspecialchars($cat['icone']) ?>" alt="" class="category-icon" />
                        <?= htmlspecialchars($cat['nom']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <img src="img/arrow.svg" id="scrollRight" class="category-arrow-icon" />
        </div>
    </header>

    <main class="artisan-profile-layout">
        <section class="profile-info-section">
            <div class="artisan-bio-header">
                <img src="<?= htmlspecialchars(get_image_path($prestataire['photo'], 'prestataire')) ?>" class="artisan-profile-avatar">
                <div class="artisan-main-details">
                    <div class="name-and-price-line">
                        <h1 class="artisan-full-name"><?= htmlspecialchars($prestataire['user_prenom'] . ' ' . $prestataire['user_nom']) ?></h1>
                        <span class="artisan-service-price"><?= htmlspecialchars($prestataire['tarif_journalier']) ?> DH/jour</span>
                    </div>
                    <div class="artisan-reviews-summary">
                        <div class="star-rating-display" data-rating="4"></div>
                        <span class="artisan-rating-score">4.0</span>
                        <span class="artisan-total-reviews">(0)</span>
                    </div>
                    <div class="artisan-location-info">
                        <img src="img/location_icon.svg" class="location-marker-icon">
                        <span class="artisan-location-text"><?= htmlspecialchars($prestataire['ville'] . ', ' . $prestataire['pays']) ?></span>
                    </div>
                </div>
            </div>
            <h2 class="artisan-service-category"><?= htmlspecialchars($prestataire['categorie_nom']) ?></h2>
            <p class="artisan-short-description"><?= htmlspecialchars($experience['titre_experience'] ?? 'No experience yet') ?></p>
            <p class="artisan-long-description"><?= htmlspecialchars($experience['description'] ?? 'No description provided') ?></p>

            <div class="artisan-media-gallery">
                <div class="main-media-viewer">
                    <img src="<?= htmlspecialchars(get_image_path($media[0]['chemin_fichier'] ?? '', 'media')) ?>" class="main-gallery-image">
                    <img src="img/arrow_slide_left.svg" class="gallery-arrow arrow-left">
                    <img src="img/arrow_slide_right.svg" class="gallery-arrow arrow-right">
                </div>
                <div class="thumbnail-media-carousel">
                    <?php foreach ($media as $m): ?>
                        <img src="<?= htmlspecialchars(get_image_path($m['chemin_fichier'], 'media')) ?>" class="gallery-thumbnail">
                    <?php endforeach; ?>
                </div>
            </div>

            <section class="customer-reviews-section">
                <h3 class="reviews-section-title">Reviews</h3>
                <!-- Section left blank for future reviews -->
            </section>
        </section>

        <section class="availability-sidebar">
            <h3 class="calendar-section-title">Availability Calendar</h3>
            <div class="service-calendar-widget">
                <div class="calendar-header">
                    <span class="calendar-nav-arrow prev-month">&lt;</span>
                    <span class="calendar-month-year"></span>
                    <span class="calendar-nav-arrow next-month">&gt;</span>
                </div>
                <div class="calendar-days-of-week">
                    <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                </div>
                <div class="calendar-grid"></div>
            </div>
            <?php
            // Check if the artisan has any ongoing projects that make them fully unavailable
            $is_globally_unavailable = false;
            $stmt_global_check = $pdo->prepare("SELECT COUNT(*) FROM Reservation WHERE id_prestataire = ? AND statut = 'acceptée' AND (statut != 'terminée' OR (project_ended_client = FALSE OR project_ended_artisan = FALSE)) AND CURDATE() BETWEEN date_debut AND COALESCE(date_fin, DATE_ADD(date_debut, INTERVAL nb_jours_estime DAY))");
            $stmt_global_check->execute([$id_prestataire]);
            if ($stmt_global_check->fetchColumn() > 0) {
                $is_globally_unavailable = true;
            }
            ?>
            <?php if (!$is_available): ?>
                <button class="request-service-action-btn unavailable-btn" disabled>Indisponible</button>
            <?php else: ?>
                <button class="request-service-action-btn" id="requestServiceBtn" data-prestataire-id="<?= $id_prestataire ?>" disabled>Demande De Service</button>
            <?php endif; ?>

        </section>
    </main>


    <footer class="footer">
    <div class="footer-top-line"></div>
  
    <div class="footer-content">

        <div class="footer-column">
        <h3 class="footer-title">Categories</h3>
        <ul>
          <li>Construction</li>
          <li>Carpentry</li>
          <li>Electrical</li>
          <li>Plumbing</li>
          <li>HVAC</li>
          <li>Cleaning</li>
          <li>Metalwork</li>
          <li>Aluminum Work</li>
          <li>Gardening</li>
          <li>Security</li>
          <li>General Handyman</li>
        </ul>
      </div>
  

      <div class="footer-column">
        <h3 class="footer-title">For Client</h3>
        <ul>
          <li>How Skilled Works</li>
          <li>Customer Success Stories</li>
          <li>Trust & Safety</li>
          <li>Quality Guide</li>
          <li>Skilled Guide</li>
          <li>Skilled Faq</li>
        </ul>
      </div>
  

      <div class="footer-column">
        <h3 class="footer-title">For Artisans</h3>
        <ul>
          <li>Become a Skilled Artisans</li>
          <li>Become in Artisans</li>
          <li>Community Hub</li>
          <li>Forum</li>
          <li>Events</li>
        </ul>
      </div>
  

      <div class="footer-column">
        <h3 class="footer-title">Solutions</h3>
        <ul>
          <li>Become a Skilled Artisans</li>
          <li>Become in Artisans</li>
          <li>Community Hub</li>
          <li>Forum</li>
          <li>Events</li>
        </ul>
      </div>
  

      <div class="footer-column">
        <h3 class="footer-title">Company</h3>
        <ul>
          <li>Become a Skilled Artisans</li>
          <li>Become in Artisans</li>
          <li>Community Hub</li>
          <li>Forum</li>
          <li>Events</li>
        </ul>
      </div>
    </div>
  
    <div class="footer-bottom">
      <div class="footer-left">
        <img src="img/Skillid..png" alt="Skilled Logo" class="footer-logo" />
        <span class="footer-copy">© Skilled Ltd .2025</span>
      </div>
      <div class="footer-right">
        <div class="language-selector">
          <img src="img/language.png" class="lang-icon" />
          <span>English</span>
        </div>
        <div class="social-icons">
          <img src="img/tiktok.svg" alt="TikTok" />
          <img src="img/insta.svg" alt="Instagram" />
          <img src="img/link.svg" alt="LinkedIn" />
          <img src="img/fb.svg" alt="Facebook" />
          <img src="img/x.svg" alt="X" />
        </div>
      </div>
    </div>
  </footer>


    <script>
    const unavailableDates = <?= json_encode($unavailableDates) ?>.map(d => new Date(d).toDateString());
    const artisanId = <?= $id_prestataire ?>; // Define artisanId globally for artisan.js
    </script>
    <script src="artisan.js"></script>
    
</body>
</html>
