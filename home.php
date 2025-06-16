<?php
require 'config.php';
session_start();

$categories = $pdo->query("SELECT id_categorie, nom FROM Categories WHERE type = 'standard'")->fetchAll(PDO::FETCH_ASSOC);

$popularCategories = $pdo->query("SELECT id_categorie, nom FROM Categories ORDER BY RAND() LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

$standardCategories = $pdo->query("SELECT id_categorie, nom, icone FROM Categories WHERE type = 'standard'")->fetchAll(PDO::FETCH_ASSOC);

$urgentCategories = $pdo->query("SELECT id_categorie, nom, icone FROM Categories WHERE type = 'emergency'")->fetchAll(PDO::FETCH_ASSOC);

$search_query = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';
$country_filter = $_GET['country'] ?? '';
$city_filter = $_GET['city'] ?? '';
$price_min = $_GET['price_min'] ?? '';
$price_max = $_GET['price_max'] ?? '';
$availability_filter = $_GET['availability'] ?? '';
$rating_filter = $_GET['rating'] ?? '';

$sql = "SELECT p.id_prestataire, p.photo, p.tarif_journalier, p.ville, p.pays, p.disponibilite,
               u.nom AS user_nom, u.prenom AS user_prenom, c.nom AS categorie_nom,
               (SELECT AVG(note) FROM Evaluation e WHERE e.id_prestataire = p.id_prestataire) AS avg_rating,
               (SELECT COUNT(*) FROM Evaluation e WHERE e.id_prestataire = p.id_prestataire) AS review_count,
               (SELECT description FROM Experience_prestataire ep WHERE ep.id_prestataire = p.id_prestataire ORDER BY date_project DESC LIMIT 1) AS latest_experience
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

$sql .= " ORDER BY RAND() LIMIT 6"; // Keep the limit for home page

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prestatairesHome = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countries = ['Morocco', 'Spain', 'France', 'Belgium', 'Netherlands', 'United Kingdom'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300..700;1,300..700&family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=League+Spartan:wght@100..900&family=Lora:ital,wght@0,400..700;1,400..700&family=Nunito:ital,wght@0,200..1000;1,200..1000&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Red+Hat+Text:ital,wght@0,300..700;1,300..700&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300..700;1,300..700&family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=League+Spartan:wght@100..900&family=Lora:ital,wght@0,400..700;1,400..700&family=Nunito:ital,wght@0,200..1000;1,200..1000&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Red+Hat+Text:ital,wght@0,300..700;1,300..700&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style_home.css">
    <link rel="shortcut icon" href="img/favicon_skilled.png" type="image/x-icon">
    <title>Skilled - Reserve. Confirm. Complete.</title>
</head>
<body>
    <header>
        <div class="top_header"><p>Lorem ipsum is a dummy or placeholder text commonly used in graphic design, publishing, and web development.</p> <img src="img/exit.svg" alt="exit"></div>
        <div class="header">
            <div class="logo">
                <a href="search.php"><img src="img/skilled_logo.svg" alt="skilled_logo"></a>
                <a href="search.php"><h1>Skillid<span>.</span></h1></a>
            </div>

            <div class="hamburger" onclick="toggleMenu()">
  <span></span>
  <span></span>
  <span></span>
</div>


            <div class="menu">
                <ul>
                    <li><a href="#">Home</a></li>
                    <li><a href="search.php">Explore</a></li>
                    <li><a href="#"><img src="img/language.svg" alt="language_icon">En</a></li>
                    <li><a href="sign_up_artisan.php">Become In Artisan</a></li>
                    <li><a href="login.php">Sign In</a></li>
                    <li class="mobile_cta_button">
        <button onclick="location.href='sign_up_client.php'">Join</button>
      </li>
                </ul>
            </div>

            <div class="cta_button">
                <button class="btn btn-primary" onclick="location.href='sign_up_client.php'">Join</button>
            </div>

        </div>
    </header>


    <main>
        
        <h1>Main Text as a<br>
hero Title Exemple</h1>

<form action="search.php" method="GET" class="search">
<input type="search" name="search" id="search" placeholder="Search for any service ..." />
      <button class="search-btn" type="submit">
        <img src="img/search.png" alt="Search">
      </button>
    </form>


 <!-- Popular Categories -->
 <div class="popular-categories">
      <div class="popular-header">
        <p class="popular-title">Popular:</p>
        <div class="categories-list">
          <?php foreach ($popularCategories as $cat): ?>
            <a href="search.php?category=<?= htmlspecialchars($cat['id_categorie']) ?>" class="category"><?= htmlspecialchars($cat['nom']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Background Image -->
    <img src="img/back_skilled.png" alt="skilled" >
  </main>


  <section class="categories-section">
    <?php foreach ($standardCategories as $cat): ?>
      <a href="search.php?category=<?= htmlspecialchars($cat['id_categorie']) ?>" class="category-card-link">
        <div class="category-card">
          <img src="img/icons/<?= htmlspecialchars($cat['icone']) ?>" alt="<?= htmlspecialchars($cat['nom']) ?>" class="category-icon" />
          <p class="category-text"><?= htmlspecialchars($cat['nom']) ?></p>
        </div>
      </a>
    <?php endforeach; ?>
  </section>

  <hr class="section-divider" />


  <section class="urgent-services">
    <h2 class="urgent-title">Urgent Services</h2>
    <div class="urgent-cards">
      <?php foreach ($urgentCategories as $cat): ?>
        <a href="search.php?category=<?= htmlspecialchars($cat['id_categorie']) ?>" class="urgent-card-link">
          <div class="urgent-card">
            <img src="img/icons/<?= htmlspecialchars($cat['icone']) ?>" alt="<?= htmlspecialchars($cat['nom']) ?>" class="urgent-icon" />
            <span class="urgent-text"><?= htmlspecialchars($cat['nom']) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>


<section class="cards-filters-section">
  <h2 class="section-title">Cards and filtres</h2>
  <p class="section-description">
    Lorem ipsum is a dummy or placeholder text commonly used in graphic design, publishing, and web development.
  </p>

  <div class="filters-container">
    <div class="filter-card">
      <img src="img/categoryes.svg" alt="Category Icon" class="filter-icon" />
      <span class="filter-title">Category</span>
      <img src="img/down_arrow.svg" alt="Dropdown Arrow" class="filter-arrow" />
      <select name="category" class="filter-select" onchange="this.form.submit()" form="filterForm">
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
      <select name="country" id="country-select" class="filter-select" onchange="updateCities(); this.form.submit()" form="filterForm">
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
      <select name="city" id="city-select" class="filter-select" onchange="this.form.submit()" form="filterForm">
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
      <select name="availability" class="filter-select" onchange="this.form.submit()" form="filterForm">
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
      <select name="rating" class="filter-select" onchange="this.form.submit()" form="filterForm">
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
  <?php foreach ($prestatairesHome as $artisan): ?>
<a href="artisan.php?id=<?= $artisan['id_prestataire'] ?>" class="prestataire-card-link">
    <div class="prestataire-card">
      <img src="<?= htmlspecialchars(get_image_path($artisan['photo'], 'prestataire')) ?>" alt="Service" class="service-image" />
      <div class="card-content">
        <div class="profile-category">
          <img src="<?= htmlspecialchars(get_image_path($artisan['photo'], 'prestataire')) ?>" alt="Profile" class="profile-photo" />
          <div class="name-category">
            <span class="prestataire-name"><?= htmlspecialchars($artisan['user_prenom']) ?> <?= htmlspecialchars($artisan['user_nom']) ?></span>
            <span class="prestataire-category"><?= htmlspecialchars($artisan['categorie_nom']) ?></span>
          </div>
        </div>
        
        <p class="service-description">
  <?= htmlspecialchars($artisan['latest_experience'] ?? 'No experience description available.') ?>
</p>



        <div class="reviews-section">
          <img src="img/review_group.svg" alt="Reviews" class="review-icon" />
          <span class="review-score"><?= number_format($artisan['avg_rating'], 1) ?></span>
          <span class="total-reviews">(<?= $artisan['review_count'] ?>)</span>
        </div>
        <div class="price-section">$<?= htmlspecialchars($artisan['tarif_journalier']) ?>/day</div>
        <div class="location-availability">
          <div class="location">
            <img src="img/location_icon.svg" alt="Location" class="location-icon" />
            <span class="location-text"><?= htmlspecialchars($artisan['ville']) ?>, <?= htmlspecialchars($artisan['pays']) ?></span>
          </div>
          <div class="availability <?= strtolower($artisan['disponibilite']) === 'available' ? 'available' : 'unavailable' ?>">
  <img src="img/<?= strtolower($artisan['disponibilite']) === 'available' ? 'avalaibility.svg' : 'unavailability.png' ?>" alt="Availability" class="availability-icon" />
  <span class="availability-text"><?= ucfirst($artisan['disponibilite']) ?></span>
</div>

        </div>
      </div>
    </div>
</a>
  <?php endforeach; ?>
</div>




<section class="premium-artisans-section">
    <div class="premium-container">
        <div class="premium-logo">
            <h1>Skillid<span>.</span></h1>
        </div>
        <div class="premium-content">
            <div class="premium-explanation">
                <h2 class="premium-title">The <span class="premium-keyword">premium</span> artisans<br>solution for projects</h2>

                <div class="features-grid">
                    <div class="feature-item">
                        <img src="img/cheked.svg" alt="Dedicated Experts Icon" class="feature-icon">
                        <h3 class="feature-title">Dedicated hiring experts</h3>
                        <p class="feature-description">Lorem ipsum is a dummy or placeholder<br>text commonly used in graphic design,<br>publishing, and web development.</p>
                    </div>
                    <div class="feature-item">
                        <img src="img/cheked.svg" alt="Satisfaction Guarantee Icon" class="feature-icon">
                        <h3 class="feature-title">Satisfaction guaranteed</h3>
                        <p class="feature-description">Order confidently, with guaranteed<br>refunds for less-than-<br>satisfactory deliveries.</p>
                    </div>
                    <div class="feature-item">
                        <img src="img/cheked.svg" alt="Advanced Management Icon" class="feature-icon">
                        <h3 class="feature-title">Advanced management</h3>
                        <p class="feature-description">Seamlessly integrate freelancers into<br>your team and projects.</p>
                    </div>
                    <div class="feature-item">
                        <img src="img/cheked.svg" alt="Flexible Payment Icon" class="feature-icon">
                        <h3 class="feature-title">Flexible payment models</h3>
                        <p class="feature-description">Pay per project or opt for hourly rates<br>to facilitate longer-term collaboration.</p>
                    </div>
                </div>

                <button class="btn try-now-btn">Try Now</button>
            </div>
            <div class="premium-image-container">
                <img src="img/Explaination_image.png" alt="Premium Artisan Solution Banner" class="premium-service-image">
            </div>
        </div>
    </div>
</section>

<div class="success-section">
  <h2 class="success-title">
    What success on Skilled looks like
  </h2>
  <p class="success-subtitle">
    Vontélle Eyewear turns to Fiverr freelancers to bring their vision to life.
  </p>

  <div class="success-banner">
    <div class="banner-content">
      <div class="banner-logo">Skilled<span>.</span></div>
      <p class="banner-text">
        Create and <span class="enhance-word">enhance</span> your talent in <br>Skilled.
      </p>
    </div>
  </div>
</div>


<div class="artisans-section">
  <h2 class="artisans-title">
    Make it all happen with artisans
  </h2>
  <div class="artisans-elements">
    <div class="artisans-item">
      <img src="img/door.svg" alt="Icon 1" class="artisans-icon">
      <p class="artisans-text">
        Access a pool of top talent<br>
        across 700 categories
      </p>
    </div>
    <div class="artisans-item">
      <img src="img/puzzle.svg" alt="Icon 2" class="artisans-icon">
      <p class="artisans-text">
        Enjoy a simple, easy-to-use<br>
        matching experience
      </p>
    </div>
    <div class="artisans-item">
      <img src="img/metre.svg" alt="Icon 3" class="artisans-icon">
      <p class="artisans-text">
        Get quality work done quickly<br>
        and within budget
      </p>
    </div>
    <div class="artisans-item">
      <img src="img/domino.svg" alt="Icon 4" class="artisans-icon">
      <p class="artisans-text">
        Only pay when you’re happy<br>
        Join now
      </p>
    </div>
  </div>
</div>


<section class="projects-section">
  <h2 class="collage-title">Made with Skilled artisan</h2>

  <div class="collage-container">

    <div class="collage-wrapper">
      <img src="img/img_collage_1.png" class="img img-1" />

      <div class="right-side">
        <div class="row">
          <img src="img/img_collage_2.png" class="img img-2" />
          <img src="img/img_collage_2.png" class="img img-3" />
        </div>
        <div class="row">
          <img src="img/img_collage_2.png" class="img img-4" />
          <img src="img/img_collage_2.png" class="img img-5" />
        </div>
      </div>
    </div>


    <div class="line-3">
      <img src="img/image_collage_3.png" class="img img-6" />
      <img src="img/img_collage_4.png" class="img img-7" />
    </div>


    <div class="line-4">
      <img src="img/img_collage_5.png" class="img img-8" />
      <img src="img/img_collage_6.png" class="img img-9" />
      <img src="img/img_collage_7.png" class="img img-10" />
      <img src="img/img_collage_8.png" class="img img-11" />
    </div>
  </div>
</section>


<section class="cta-banner">
  <div class="banner-content">
    <h2 class="banner-text">Artisans services at your <span class="highlight-text">fingertips</span></h2>
    <button class="cta-button">Book Now</button>
  </div>
</section>





<footer class="footer">
  <div class="footer-top-line"></div>

  <div class="footer-content">
    <!-- Column 1 -->
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

    <!-- Column 2 -->
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

    <!-- Column 3 -->
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

    <!-- Column 4 -->
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

    <!-- Column 5 -->
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

</section>



    <script>
        function toggleMenu() {
            let menu = document.querySelector('.header .menu');
            menu.classList.toggle('show');
        }

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
    </script>

</body>
</html>