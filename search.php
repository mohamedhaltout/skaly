<?php
require 'config.php';
session_start();


$categories = $pdo->query("SELECT id_categorie, nom FROM Categories WHERE type = 'standard'")->fetchAll(PDO::FETCH_ASSOC);


$search_query = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';
$country_filter = $_GET['country'] ?? '';
$city_filter = $_GET['city'] ?? '';
$price_min = $_GET['price_min'] ?? '';
$price_max = $_GET['price_max'] ?? '';
$availability_filter = $_GET['availability'] ?? '';
$rating_filter = $_GET['rating'] ?? '';

$sql = "SELECT p.id_prestataire, p.photo, p.tarif_journalier, p.ville, p.pays,
               u.nom AS user_nom, u.prenom AS user_prenom, c.nom AS categorie_nom,
               (SELECT AVG(note) FROM Evaluation e WHERE e.id_prestataire = p.id_prestataire) AS avg_rating,
               (SELECT COUNT(*) FROM Evaluation e WHERE e.id_prestataire = p.id_prestataire) AS review_count,
               (SELECT description FROM Experience_prestataire ep WHERE ep.id_prestataire = p.id_prestataire ORDER BY date_project DESC LIMIT 1) AS latest_experience,
               (SELECT COUNT(*) FROM Reservation r WHERE r.id_prestataire = p.id_prestataire AND r.statut = 'acceptée' AND (r.statut != 'terminée' OR (r.project_ended_client = FALSE OR r.project_ended_artisan = FALSE)) AND CURDATE() BETWEEN r.date_debut AND COALESCE(r.date_fin, DATE_ADD(r.date_debut, INTERVAL r.nb_jours_estime DAY))) AS is_globally_unavailable
        FROM Prestataire p
        JOIN Utilisateur u ON p.id_utilisateur = u.id_utilisateur
        JOIN Categories c ON p.id_categorie = c.id_categorie
        WHERE 1=1";

$params = [];

if ($search_query) {
    $sql .= " AND (c.nom LIKE ? OR p.ville LIKE ? OR EXISTS (SELECT 1 FROM Experience_prestataire ep WHERE ep.id_prestataire = p.id_prestataire AND ep.description LIKE ?))";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if ($category_filter) {
    $sql .= " AND p.id_categorie = ?";
    $params[] = $category_filter;
}
if ($country_filter) {
    $sql .= " AND p.pays = ?";
    $params[] = $country_filter;
}
if ($city_filter) {
    $sql .= " AND p.ville = ?";
    $params[] = $city_filter;
}
if ($price_min !== '' && is_numeric($price_min)) {
    $sql .= " AND p.tarif_journalier >= ?";
    $params[] = $price_min;
}
if ($price_max !== '' && is_numeric($price_max)) {
    $sql .= " AND p.tarif_journalier <= ?";
    $params[] = $price_max;
}
if ($availability_filter) {
    $days = match ($availability_filter) {
        '2' => 2,
        '7' => 7,
        '15' => 15,
        default => null
    };
    if ($days) {
        $date_limit = date('Y-m-d', strtotime("+$days days"));
        $sql .= " AND NOT EXISTS (SELECT 1 FROM Devis d WHERE d.id_prestataire = p.id_prestataire AND d.date_debut_travaux <= ?)";
        $params[] = $date_limit;
    }
}
if ($rating_filter && in_array($rating_filter, ['3', '4', '5'])) {
    $sql .= " AND (SELECT AVG(note) FROM Evaluation e WHERE e.id_prestataire = p.id_prestataire) >= ?";
    $params[] = $rating_filter;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prestataires = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countries = ['Morocco', 'Spain', 'France', 'Belgium', 'Netherlands', 'United Kingdom'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="search.css">
  <title>Skilled - Search For Artisans</title>
</head>
<body>
  <header class="prestataires-header">
    <div class="header-top">
      <div class="logo">
        <a href="search.php"><img src="img/skilled_logo.svg" alt="Skilled Logo" class="logo-img" /></a>
        <a href="search.php"><span class="logo-text">Skilled<span class="dot">.</span></span></a>
      </div>
      <div class="header-right">
        <img src="img/Notification.svg" class="icon" alt="Notifications" />
        <img src="img/message.svg" class="icon" alt="Messages" />
        <img src="img/favorite.svg" class="icon" alt="Favorites" />
        <span class="orders">Orders</span>
        <span class="switch">Switch to Artisans</span>
        <a href="Profil_Client.php"><img src="img/profil.svg" class="profile-pic" alt="Profile" /></a>
      </div>
    </div>
    <div class="line"></div>
    <div class="categories-bar">
      <div class="categories-slider">
        <?php foreach ($categories as $cat): ?>
          <a href="search.php?category=<?= $cat['id_categorie'] ?>" class="category"> <?= htmlspecialchars($cat['nom']) ?> </a>
        <?php endforeach; ?>
      </div>
      <img src="img/arrow.svg" alt="More" class="arrow-icon" id="scrollRight" />
    </div>
  </header>

  <section class="search-section">
    <div class="search-texts">
      <h1 class="search-title">Find Your Artisan</h1>
      <p class="search-subtitle">Discover skilled professionals ...</p>
    </div>
    <div class="search-box">
      <form method="GET" action="">
        <input type="text" name="search" placeholder="Search for any service ..." value="<?= htmlspecialchars($search_query) ?>" />
        <div class="search-icon-wrapper">
          <button type="submit" style="background: none; border: none; padding: 0;">
            <img src="img/search.png" alt="Search" class="search-icon" />
          </button>
        </div>
      </form>
    </div>
  </section>

        </div>
    </div>

    <section class="cards-filters-section">
        <h2 class="section-title">Artisans</h2>
        <p class="section-description">
            Browse our selection of verified artisans ready to assist with your needs.
        </p>

        <div class="filters-container">
            <div class="filter-card">
                <img src="img/categoryes.svg" alt="Category Icon" class="filter-icon" />
                <span class="filter-title">Category</span>
                <img src="img/down_arrow.svg" alt="Dropdown Arrow" class="filter-arrow" />
                <select name="category" onchange="this.form.submit()" form="filterForm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id_categorie'] ?>" <?= $category_filter == $cat['id_categorie'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-card">
                <img src="img/country.svg" alt="Country Icon" class="filter-icon" />
                <span class="filter-title">Country</span>
                <img src="img/down_arrow.svg" alt="Dropdown Arrow" class="filter-arrow" />
                <select name="country" id="country-select" onchange="updateCities(); this.form.submit()" form="filterForm">
                    <option value="">All Countries</option>
                    <?php foreach ($countries as $country): ?>
                        <option value="<?= htmlspecialchars($country) ?>" <?= $country_filter == $country ? 'selected' : '' ?>>
                            <?= htmlspecialchars($country) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-card">
                <img src="img/City.svg" alt="City Icon" class="filter-icon" />
                <span class="filter-title">City</span>
                <img src="img/down_arrow.svg" alt="Dropdown Arrow" class="filter-arrow" />
                <select name="city" id="city-select" onchange="this.form.submit()" form="filterForm">
                    <option value="">All Cities</option>
                    <?php if ($country_filter): ?>
                        <?php
                        $cities = [
                            'Morocco' => ['Tanger', 'Casablanca', 'Rabat', 'Marrakech', 'Fes'],
                            'Spain' => ['Madrid', 'Barcelona', 'Seville'],
                            'France' => ['Paris', 'Lyon', 'Marseille'],
                            'Belgium' => ['Brussels', 'Antwerp', 'Ghent'],
                            'Netherlands' => ['Amsterdam', 'Rotterdam', 'Utrecht'],
                            'United Kingdom' => ['London', 'Manchester', 'Birmingham']
                        ][$country_filter] ?? [];
                        foreach ($cities as $city): ?>
                            <option value="<?= htmlspecialchars($city) ?>" <?= $city_filter == $city ? 'selected' : '' ?>>
                                <?= htmlspecialchars($city) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="filter-card">
                <img src="img/price.svg" alt="Price Icon" class="filter-icon" />
                <span class="filter-title">Price/day</span>
                <img src="img/down_arrow.svg" alt="Dropdown Arrow" class="filter-arrow" />
                <div class="price-range">
                    <input type="number" name="price_min" placeholder="Min" value="<?= htmlspecialchars($price_min) ?>" form="filterForm">
                    <span>-</span>
                    <input type="number" name="price_max" placeholder="Max" value="<?= htmlspecialchars($price_max) ?>" form="filterForm">
                    <button type="submit" form="filterForm"></button>
                </div>
            </div>

            <div class="filter-card">
                <img src="img/calendar.svg" alt="Availability Icon" class="filter-icon" />
                <span class="filter-title">Availability</span>
                <img src="img/down_arrow.svg" alt="Dropdown Arrow" class="filter-arrow" />
                <select name="availability" onchange="this.form.submit()" form="filterForm">
                    <option value="">Any Time</option>
                    <option value="2" <?= $availability_filter == '2' ? 'selected' : '' ?>>Within 2 Days</option>
                    <option value="7" <?= $availability_filter == '7' ? 'selected' : '' ?>>Within 7 Days</option>
                    <option value="15" <?= $availability_filter == '15' ? 'selected' : '' ?>>Within 15 Days</option>
                </select>
            </div>

            <div class="filter-card">
                <img src="img/rating.svg" alt="Rating Icon" class="filter-icon" />
                <span class="filter-title">Rating</span>
                <img src="img/down_arrow.svg" alt="Dropdown Arrow" class="filter-arrow" />
                <select name="rating" onchange="this.form.submit()" form="filterForm">
                    <option value="">All Ratings</option>
                    <option value="5" <?= $rating_filter == '5' ? 'selected' : '' ?>>5 Stars</option>
                    <option value="4" <?= $rating_filter == '4' ? 'selected' : '' ?>>4 Stars & Up</option>
                    <option value="3" <?= $rating_filter == '3' ? 'selected' : '' ?>>3 Stars & Up</option>
                </select>
            </div>
        </div>

        <form id="filterForm" method="GET" style="display: none;">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
        </form>

        <div class="cards-container">
            <?php if (empty($prestataires)): ?>
                <p>No artisans found matching your criteria.</p>
            <?php else: ?>
                <?php foreach ($prestataires as $prestataire): ?>
                    <a href="artisan.php?id=<?= $prestataire['id_prestataire'] ?>" class="prestataire-card-link">
                        <div class="prestataire-card">
                            <img src="<?= htmlspecialchars(get_image_path($prestataire['photo'], 'prestataire')) ?>" alt="Service" class="service-image" />
                            <div class="card-content">
                                <div class="profile-category">
                                    <img src="<?= htmlspecialchars(get_image_path($prestataire['photo'], 'prestataire')) ?>" alt="Profile" class="profile-photo" />
                                    <div class="name-category">
                                        <span class="prestataire-name"><?= htmlspecialchars($prestataire['user_prenom'] . ' ' . $prestataire['user_nom']) ?></span>
                                        <span class="prestataire-category"><?= htmlspecialchars($prestataire['categorie_nom']) ?></span>
                                    </div>
                                </div>
                                <p class="service-description">
                                    <?= htmlspecialchars($prestataire['latest_experience'] ?? 'No experience description available.') ?>
                                </p>
                                <div class="reviews-section">
                                    <img src="img/review_group.svg" alt="Reviews" class="review-icon" />
                                    <span class="review-score"><?= number_format($prestataire['avg_rating'] ?? 0, 1) ?></span>
                                    <span class="total-reviews">(<?= $prestataire['review_count'] ?? 0 ?>)</span>
                                </div>
                                <div class="price-section"><?= htmlspecialchars($prestataire['tarif_journalier']) ?> DH/day</div>
                                <div class="location-availability">
                                    <div class="location">
                                        <img src="img/location_icon.svg" alt="Location" class="location-icon" />
       <span class="location-text"><?= htmlspecialchars($prestataire['ville'] . ', ' . $prestataire['pays']) ?></span>
   </div>
   <?php
   $is_globally_unavailable = $prestataire['is_globally_unavailable'] > 0;
   $availability_status_text = $is_globally_unavailable ? 'Indisponible' : 'Disponible';
   $availability_icon = $is_globally_unavailable ? 'univailible.png' : 'avalaibility.svg';
   $availability_class = $is_globally_unavailable ? 'unavailable' : 'available';
   ?>
   <div class="availability <?= $availability_class ?>">
       <img src="img/<?= $availability_icon ?>" alt="Availability" class="availability-icon" />
       <span class="availability-text"><?= htmlspecialchars($availability_status_text) ?></span>
   </div>
</div>
</div>
</div>
</a>
<?php endforeach; ?>
<?php endif; ?>
</div>
</section>

    <footer class="footer">
        <div class="footer-top-line"></div>
        <div class="footer-content">
            <div class="footer-column">
                <h3 class="footer-title">Categories</h3>
                <ul>
                    <?php foreach ($categories as $cat): ?>
                        <li><a href="search.php?category=<?= $cat['id_categorie'] ?>"><?= htmlspecialchars($cat['nom']) ?></a></li>
                    <?php endforeach; ?>
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
                    <li>Become a Skilled Artisan</li>
                    <li>Artisan Community</li>
                    <li>Community Hub</li>
                    <li>Forum</li>
                    <li>Events</li>
                </ul>
            </div>
            <div class="footer-column">
                <h3 class="footer-title">Solutions</h3>
                <ul>
                    <li>Skilled for Business</li>
                    <li>Enterprise Solutions</li>
                    <li>Community Hub</li>
                    <li>Forum</li>
                    <li>Events</li>
                </ul>
            </div>
            <div class="footer-column">
                <h3 class="footer-title">Company</h3>
                <ul>
                    <li>About Us</li>
                    <li>Careers</li>
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
        const scrollRightBtn = document.getElementById('scrollRight');
        const slider = document.querySelector('.categories-slider');
        scrollRightBtn.addEventListener('click', () => {
            slider.scrollBy({ left: 200, behavior: 'smooth' });
        });

        let citiesByCountry = {
            "Morocco": ["Tanger", "Casablanca", "Rabat", "Marrakech", "Fes"],
            "Spain": ["Madrid", "Barcelona", "Seville"],
            "France": ["Paris", "Lyon", "Marseille"],
            "Belgium": ["Brussels", "Antwerp", "Ghent"],
            "Netherlands": ["Amsterdam", "Rotterdam", "Utrecht"],
            "United Kingdom": ["London", "Manchester", "Birmingham"]
        };

        function updateCities() {
            let country = document.getElementById("country-select").value;
            let citySelect = document.getElementById("city-select");
            citySelect.innerHTML = '<option value="">All Cities</option>';

            if (citiesByCountry[country]) {
                citiesByCountry[country].forEach(function(city) {
                    let option = document.createElement("option");
                    option.value = city;
                    option.textContent = city;
                    citySelect.appendChild(option);
                });
            }
        }

        <?php if ($country_filter): ?>
            updateCities();
            document.getElementById("city-select").value = "<?= htmlspecialchars($city_filter) ?>";
        <?php endif; ?>


        document.querySelectorAll('.filter-card').forEach(card => {
            card.addEventListener('click', (e) => {
                if (!e.target.closest('select') && !e.target.closest('input')) {
                    card.classList.toggle('active');
                }
            });
        });


        document.querySelectorAll('.price-range input').forEach(input => {
            input.addEventListener('change', () => {
                document.getElementById('filterForm').submit();
            });
        });

        // Sticky search visibility
        window.addEventListener('scroll', () => {
            if (window.scrollY > document.querySelector('.search-section').offsetHeight) {
                document.body.classList.add('scrolled');
            } else {
                document.body.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>