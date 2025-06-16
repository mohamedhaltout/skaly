<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Évaluation - Skilled</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700;800&family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="evaluation.css">
</head>
<body>

    <div class="popup-overlay">
        <div class="evaluation-popup">
            <div class="popup-header">
                <div class="header-left">
                    <img src="img/skilled_logo.svg" alt="Skilled Logo" class="logo-image">
                    <div class="logo-text">Skilled<span class="logo-dot">.</span></div>
                </div>
            </div>

            <h2 class="popup-title">Évaluez le Prestataire</h2>

            <div class="evaluation-card">
                <div class="evaluation-details">
                    <div class="detail-group">
                        <span class="detail-label">Artisan:</span>
                        <span class="detail-value">Mohamed Haltout</span>
                    </div>
                    <div class="detail-group">
                        <span class="detail-label">Projet:</span>
                        <span class="detail-value">Installation Électrique Villa</span>
                    </div>
                    <div class="detail-group">
                        <span class="detail-label">Date:</span>
                        <span class="detail-value">2024-07-03</span>
                    </div>
                </div>
            </div>

            <h3 class="input-label">Note (sur 5)</h3>
            <div class="select-wrapper">
                <select id="note" name="note" required>
                    <option value="">-- Choisir une note --</option>
                    <option value="5">5 - Excellent</option>
                    <option value="4">4 - Très bien</option>
                    <option value="3">3 - Bien</option>
                    <option value="2">2 - Passable</option>
                    <option value="1">1 - Mauvais</option>
                </select>
                <span class="select-arrow">&#9660;</span>
            </div>

            <h3 class="input-label">Commentaire</h3>
            <textarea id="comment" name="comment" rows="5" placeholder="Exprimer Votre Avis" class="comment-box"></textarea>

            <button class="button submit-evaluation-button">Envoyer l'évaluation</button>
        </div>
    </div>

</body>
</html>