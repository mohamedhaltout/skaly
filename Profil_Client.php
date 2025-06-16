<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Profile</title>
    <link rel="stylesheet" href="Profil_Client.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="profile-container">
        <h1>Client Profile</h1>
        <div class="profile-details">
            <div class="profile-picture">
                <img src="img/profil.png" alt="Profile Picture">
                <input type="file" id="profileImageUpload" accept="image/*" style="display: none;">
                <label for="profileImageUpload" class="upload-icon"><i class="fas fa-camera"></i></label>
            </div>
            <form action="update_profile.php" method="POST">
                <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'search.php'); ?>">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" value="John Doe">
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number:</label>
                    <input type="text" id="phone" name="phone" value="+1234567890">
                </div>
                <button type="submit" class="save-button">Save Changes</button>
            </form>
        </div>
    </div>
</body>
</html>