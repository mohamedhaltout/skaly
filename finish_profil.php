<?php
require 'config.php';
session_start();

if (!isset($_SESSION['id_utilisateur'])) {
    die("Unauthorized access.");
}

$message = "";

// Fetch standard categories
$categories = $pdo->query("SELECT id_categorie, nom FROM Categories WHERE type = 'standard'")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['specialite'], $_POST['pays'], $_POST['ville'], $_POST['telephone'], $_POST['tarif_journalier']) && isset($_FILES['photo'])) {
    $id_utilisateur = $_SESSION['id_utilisateur'];
    $id_categorie = $_POST['specialite'];
    $pays = $_POST['pays'];
    $ville = $_POST['ville'];
    $telephone = $_POST['telephone'];
    $tarif_journalier = $_POST['tarif_journalier'];
    $accepte_budget_global = isset($_POST['accept_budget']) ? 1 : 0;

    $error = false;

    // Validate fields
    if (empty($id_categorie) || empty($pays) || empty($ville) || empty($telephone) || empty($tarif_journalier)) {
        $message = "All fields are required.";
        $error = true;
    }

    // Validate photo
    if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $message = "Profile photo is required.";
        $error = true;
    }

    // Upload photo
    if (!$error) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir); // auto create uploads if not exists

        $fileName = uniqid() . '_' . basename($_FILES['photo']['name']);
        $uploadPath = $uploadDir . $fileName;
        move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath);
        $photoPath = $uploadPath;

        // Insert into Prestataire
        $stmt = $pdo->prepare("INSERT INTO Prestataire 
            (id_utilisateur, id_categorie, photo, specialite, pays, ville, telephone, tarif_journalier, accepte_budget_global, disponibilite) 
            VALUES (?, ?, ?, '', ?, ?, ?, ?, ?, 'available')");

        $stmt->execute([
            $id_utilisateur,
            $id_categorie,
            $photoPath,
            $pays,
            $ville,
            $telephone,
            $tarif_journalier,
            $accepte_budget_global
        ]);

        header("Location: ad_exeperience.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Finish Profile</title>
  <link rel="stylesheet" href="finish_profil.css">
</head>
<body>
  <form class="finish-form" action="" method="POST" enctype="multipart/form-data">
    <div class="signup-container">
      <div class="left-decoration"></div>
      <div class="right-content">
        <div class="title">Finish Your Profile</div>

        <?php if (!empty($message)): ?>
            <p style="color:red; font-weight:bold"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <!-- Upload Photo -->
        <div class="form-group upload-group">
          <div class="form-info">
            <label for="profile-photo">Upload Profile Photo</label>
            <div class="description">Upload a clear photo to help others recognize and trust you.</div>
          </div>
          <div class="form-input photo-upload-input">
            <label for="profile-photo" class="upload-button">
              <input type="file" id="profile-photo" name="photo" accept="image/*" hidden required>
              <img src="img/upload_image.svg" alt="Upload Icon" class="upload-icon">
              <span class="upload-placeholder">Upload Photo</span>
            </label>
          </div>
        </div>

        <!-- Specialty -->
        <div class="form-group">
          <div class="form-info">
            <label for="specialite">Area of Expertise</label>
            <div class="description">e.g., plumbing, painting</div>
          </div>
          <div class="form-input custom-select">
            <select name="specialite" required>
              <option value="" disabled selected>Choose your specialty</option>
              <?php foreach($categories as $cat): ?>
                <option value="<?= $cat['id_categorie'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
              <?php endforeach; ?>
            </select>
            <img src="img/arrow_down.svg" class="dropdown-icon">
          </div>
        </div>

        <!-- Country -->
        <div class="form-group">
          <div class="form-info">
            <label for="country-select">Choose your country</label>
            <div class="description">Used to match you with nearby clients.</div>
          </div>
          <div class="form-input custom-select">
            <select name="pays" id="country-select" onchange="updateCities()" required>
              <option disabled selected>Country</option>
              <option value="Morocco">Morocco</option>
              <option value="Spain">Spain</option>
              <option value="France">France</option>
              <option value="Belgium">Belgium</option>
              <option value="Netherlands">Netherlands</option>
              <option value="United Kingdom">United Kingdom</option>
            </select>
            <img src="img/arrow_down.svg" class="dropdown-icon">
          </div>
        </div>

        <!-- City -->
        <div class="form-group">
          <div class="form-info">
            <label for="city-select">Choose your city</label>
            <div class="description">We’ll match you with nearby jobs.</div>
          </div>
          <div class="form-input custom-select">
            <select name="ville" id="city-select" required>
              <option disabled selected>City</option>
            </select>
            <img src="img/arrow_down.svg" class="dropdown-icon">
          </div>
        </div>

        <!-- Phone -->
        <div class="form-group">
          <div class="form-info">
            <label for="phone-input">Phone Number</label>
            <div class="description">e.g., +212 600 000 000</div>
          </div>
          <div class="form-input">
            <input type="tel" name="telephone" id="phone-input" placeholder="Phone Number" required>
          </div>
        </div>

        <!-- Daily Rate -->
        <div class="form-group">
          <div class="form-info">
            <label for="daily-rate-input">Daily Rate</label>
            <div class="description">e.g., 100</div>
          </div>
          <div class="form-input">
            <input type="text" name="tarif_journalier" id="daily-rate-input" placeholder="Daily Rate (e.g., 100)" required>
          </div>
        </div>

        <!-- Accept Budget -->
        <div class="form-group checkbox-group">
          <div class="form-info">
            <label>Accept Total Budget</label>
            <div class="description">Allow clients to propose a total project budget.</div>
          </div>
          <div class="form-input checkbox-input-container">
            <input type="checkbox" id="accept-budget" name="accept_budget" class="custom-checkbox">
            <label for="accept-budget" class="checkbox-label">
              <img src="img/checkbox.svg" class="checkbox-icon">
              <span>Accept total budget</span>
            </label>
          </div>
        </div>

        <div class="divider"></div>

        <button type="submit" class="submit-button">Complete</button>

        <div class="alert-box">
          <img src="img/avertisement.svg" alt="Alert Icon">
          <span>Please do not make any transactions, payments, or<br> contact outside of Skillid. This platform is designed to protect you.</span>
        </div>
      </div>
    </div>
  </form>

<script>
let citiesByCountry = {
  "Morocco": ["Tanger ","Casablanca", "Rabat", "Marrakech", "Fes"],
  "Spain": ["Madrid", "Barcelona", "Seville"],
  "France": ["Paris", "Lyon", "Marseille"],
  "Belgium": ["Brussels", "Antwerp", "Ghent"],
  "Netherlands": ["Amsterdam", "Rotterdam", "Utrecht"],
  "United Kingdom": ["London", "Manchester", "Birmingham"]
};

function updateCities() {
  let country = document.getElementById("country-select").value;
  let citySelect = document.getElementById("city-select");
  citySelect.innerHTML = '<option disabled selected>Select City</option>';

  if (citiesByCountry[country]) {
    citiesByCountry[country].forEach(function(city) {
      let option = document.createElement("option");
      option.value = city;
      option.textContent = city;
      citySelect.appendChild(option);
    });
  }
}
</script>

</body>
</html>
