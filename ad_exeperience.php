<?php
require 'config.php';
session_start();

if (!isset($_SESSION['id_utilisateur'])) {
    die("Unauthorized access.");
}

// Get the id_prestataire
$stmt = $pdo->prepare("SELECT id_prestataire FROM Prestataire WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['id_utilisateur']]);
$prestataire = $stmt->fetch();

if (!$prestataire) {
    die("No prestataire found.");
}

$id_prestataire = $prestataire['id_prestataire'];
$message = "";

if (isset($_POST['titre_experience'], $_POST['description'], $_POST['date_project'])) {
    $titre = $_POST['titre_experience'];
    $description = $_POST['description'];
    $annee = $_POST['date_project'];

    if (empty($titre) || empty($description) || empty($annee)) {
        $message = "All fields are required.";
    } else {
        // Insert experience
        $stmt = $pdo->prepare("INSERT INTO Experience_prestataire (id_prestataire, titre_experience, description, date_project) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id_prestataire, $titre, $description, $annee]);
        $id_experience = $pdo->lastInsertId();

        // Handle multiple file uploads
        if (!empty($_FILES['media_files']['name'][0])) {
            $uploadDir = "uploads/media/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            foreach ($_FILES['media_files']['tmp_name'] as $index => $tmpName) {
                if ($_FILES['media_files']['error'][$index] === UPLOAD_ERR_OK) {
                    $originalName = $_FILES['media_files']['name'][$index];
                    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                    $type = in_array($ext, ['mp4', 'mov', 'avi']) ? 'video' : 'image';
                    $newName = uniqid() . '_' . basename($originalName);
                    $destPath = $uploadDir . $newName;

                    move_uploaded_file($tmpName, $destPath);

                    $stmt = $pdo->prepare("INSERT INTO Media_experience (id_experience, type_contenu, chemin_fichier) VALUES (?, ?, ?)");
                    $stmt->execute([$id_experience, $type, $destPath]);
                }
            }
        }

        $message = "Experience added successfully!";
        // ✅ Redirect automatically to profile page
        header("Location: artisan.php?id=" . $id_prestataire);
        exit;
    }
}
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Work Experience</title>
    <link rel="stylesheet" href="ad_exeperience.css">
    <style>
        .media-upload-button.preview-added {
            border-color: #3E185B;
            background-color: #EDEDED;
        }
        .media-upload-button img.preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="left-decoration"></div>
        <div class="right-content">
            <div class="title">Add Work Experience</div>

            <?php if (!empty($message)): ?>
                <p style="color: green; font-weight: bold; text-align:center;"> <?= htmlspecialchars($message) ?> </p>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <div class="form-info">
                        <label for="experience-title-input">Experience Title</label>
                        <div class="description">e.g., Full villa painting 2023</div>
                    </div>
                    <div class="form-input">
                        <input type="text" name="titre_experience" id="experience-title-input" placeholder="Experience Title" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-info">
                        <label for="detailed-description-textarea">Detailed Description</label>
                        <div class="description">Describe what you did, techniques used, and time taken...</div>
                    </div>
                    <div class="form-input">
                        <textarea name="description" id="detailed-description-textarea" placeholder="Your description here..." required></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-info">
                        <label for="project-year-input">Enter the year</label>
                        <div class="description">(e.g., 2023)</div>
                    </div>
                    <div class="form-input">
                        <input type="text" name="date_project" id="project-year-input" placeholder="2023" required>
                    </div>
                </div>

                <div class="form-group upload-group">
                    <div class="form-info">
                        <label>Upload photos or videos</label>
                        <div class="description">Showcase your work</div>
                    </div>
                    <div class="form-input media-upload-container">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label for="media-upload-<?= $i ?>" class="media-upload-button" id="button-<?= $i ?>">
                                <input type="file" name="media_files[]" id="media-upload-<?= $i ?>" accept="image/*,video/*" hidden onchange="previewMedia(this, <?= $i ?>)">
                                <img src="img/add_media.svg" alt="Add Icon" class="add-icon">
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="divider"></div>

                <button type="submit" class="submit-button">Complete</button>

                <div class="alert-box">
                    <img src="img/avertisement.svg" alt="Alert Icon">
                    <span>Please do not make any transactions, payments, or<br> contact outside of Skillid.</span>
                </div>
            </form>
        </div>
    </div>



</body>
</html>
