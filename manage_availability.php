<?php
session_start();
require 'config.php';

if (!isset($_SESSION['id_utilisateur']) || $_SESSION['role'] !== 'prestataire') {
    header("Location: login.php");
    exit();
}

$id_prestataire = $_SESSION['id_utilisateur'];

$message = '';

// Fetch current artisan's global availability status
$stmt = $pdo->prepare("SELECT statut_disponibilite FROM Prestataire WHERE id_utilisateur = ?");
$stmt->execute([$id_prestataire]);
$current_status = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_status'])) {
        $new_status = ($current_status === 'available') ? 'unavailable' : 'available';
        try {
            $stmt = $pdo->prepare("UPDATE Prestataire SET statut_disponibilite = ? WHERE id_utilisateur = ?");
            $stmt->execute([$new_status, $id_prestataire]);
            $message = "Global availability status updated to " . htmlspecialchars($new_status) . "!";
            $current_status = $new_status; // Update current status for display
        } catch (PDOException $e) {
            $message = "Error updating global availability: " . $e->getMessage();
        }
    } else {
        $unavailable_dates_str = $_POST['unavailable_dates'] ?? '';
        $unavailable_dates_array = json_decode($unavailable_dates_str, true);

        try {
            $pdo->beginTransaction();

            // Clear existing manual unavailable dates for this artisan
            $stmt = $pdo->prepare("DELETE FROM Artisan_Availability WHERE id_prestataire = ?");
            $stmt->execute([$id_prestataire]);

            // Insert new unavailable dates
            if (!empty($unavailable_dates_array)) {
                $insert_sql = "INSERT INTO Artisan_Availability (id_prestataire, unavailable_date) VALUES (?, ?)";
                $stmt = $pdo->prepare($insert_sql);
                foreach ($unavailable_dates_array as $date) {
                    $stmt->execute([$id_prestataire, $date]);
                }
            }
            $pdo->commit();
            $message = "Availability updated successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error updating availability: " . $e->getMessage();
        }
    }
}

// Fetch current manually set unavailable dates
$stmt = $pdo->prepare("SELECT unavailable_date FROM Artisan_Availability WHERE id_prestataire = ?");
$stmt->execute([$id_prestataire]);
$manualUnavailableDates = array_map(function($row) {
    return $row['unavailable_date'];
}, $stmt->fetchAll(PDO::FETCH_ASSOC));

// Fetch active reservations for the artisan
$stmt = $pdo->prepare("SELECT date_debut, nb_jours_estime, date_fin FROM Reservation WHERE id_prestataire = ? AND statut = 'acceptée' AND (statut != 'terminée' OR (project_ended_client = FALSE OR project_ended_artisan = FALSE))");
$stmt->execute([$id_prestataire]);
$activeReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$projectUnavailableDates = [];
foreach ($activeReservations as $res) {
    $startDate = new DateTime($res['date_debut']);
    $endDate = $res['date_fin'] ? new DateTime($res['date_fin']) : (clone $startDate)->modify('+' . ($res['nb_jours_estime'] - 1) . ' days');

    $currentDate = clone $startDate;
    while ($currentDate <= $endDate) {
        $projectUnavailableDates[] = $currentDate->format('Y-m-d');
        $currentDate->modify('+1 day');
    }
}

$allUnavailableDates = array_unique(array_merge($manualUnavailableDates, $projectUnavailableDates));
sort($allUnavailableDates);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Availability - Skilled</title>
    <link rel="stylesheet" href="artisan_dashboard.css"> <!-- Reusing some styles -->
    <style>
        /* Specific styles for this page */
        .availability-container {
            padding: 20px;
            max-width: 900px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .availability-container h1 {
            color: #3E185B;
            text-align: center;
            margin-bottom: 30px;
        }
        .calendar-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .calendar-controls button {
            background-color: #3E185B;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        .calendar-controls button:hover {
            background-color: #5A3378;
        }
        .calendar-grid-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-weight: bold;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        .calendar-day {
            padding: 10px 5px;
            border: 1px solid #eee;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
            background-color: #f9f9f9;
            position: relative;
        }
        .calendar-day.empty {
            background-color: transparent;
            border-color: transparent;
            cursor: default;
        }
        .calendar-day.current-month {
            background-color: #fff;
        }
        .calendar-day.selected {
            background-color: #FFDDC1; /* Light orange for selected unavailable */
            border-color: #FF9933;
        }
        .calendar-day.project-unavailable {
            background-color: #FFCCCC; /* Light red for project unavailable */
            border-color: #FF0000;
            cursor: not-allowed;
        }
        .calendar-day.today {
            border: 2px solid #3E185B;
        }
        .calendar-day span {
            font-size: 14px;
            color: #333;
        }
        .calendar-day.project-unavailable span {
            color: #666;
        }
        .save-button-container {
            text-align: center;
            margin-top: 30px;
        }
        .save-button {
            background-color: #02AA1B;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }
        .save-button:hover {
            background-color: #02CC1B;
        }
        .message {
            text-align: center;
            margin-top: 15px;
            font-weight: bold;
            color: green;
        }
        .message.error {
            color: red;
        }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <img src="img/skilled_logo.svg" alt="Skilled Logo" class="logo-image">
            <div class="logo-text">Skilled<span class="logo-dot">.</span></div>
        </div>
        <div class="header-right">
            <button class="logout-button" onclick="location.href='logout.php'">Logout</button>
            <span class="client-name">Artisan Name</span> <!-- Placeholder -->
            <a href="artisan_dashboard.php"><img src="img/profil.svg" alt="Profile Picture" class="profile-image"></a> <!-- Placeholder -->
        </div>
    </header>
    <div class="header-divider"></div>

    <div class="availability-container">
        <h1>Manage Your Availability</h1>
        <?php if ($message): ?>
            <p class="message <?= strpos($message, 'Error') !== false ? 'error' : '' ?>"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>
        <p>Current Global Status: <strong><?= htmlspecialchars(ucfirst($current_status)) ?></strong></p>

        <div class="calendar-controls">
            <button id="prevMonth">Previous Month</button>
            <h2 id="currentMonthYear"></h2>
            <button id="nextMonth">Next Month</button>
        </div>

        <div class="calendar-grid-header">
            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
        </div>
        <div class="calendar-grid" id="calendarGrid"></div>

        <div class="save-button-container">
            <button class="save-button" id="saveAvailability">Save Availability</button>
            <button class="save-button" id="toggleAvailabilityStatus">Toggle Global Availability</button>
        </div>
    </div>

    <script>
        const calendarGrid = document.getElementById('calendarGrid');
        const currentMonthYear = document.getElementById('currentMonthYear');
        const prevMonthBtn = document.getElementById('prevMonth');
        const nextMonthBtn = document.getElementById('nextMonth');
        const saveAvailabilityBtn = document.getElementById('saveAvailability');

        let currentDate = new Date();
        let selectedUnavailableDates = new Set(<?= json_encode($manualUnavailableDates) ?>);
        const projectUnavailableDates = new Set(<?= json_encode($projectUnavailableDates) ?>);

        function renderCalendar() {
            calendarGrid.innerHTML = '';
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            currentMonthYear.textContent = new Date(year, month).toLocaleString('default', { month: 'long', year: 'numeric' });

            const firstDayOfMonth = new Date(year, month, 1);
            const lastDayOfMonth = new Date(year, month + 1, 0);
            const daysInMonth = lastDayOfMonth.getDate();
            const firstDayOfWeek = firstDayOfMonth.getDay(); // 0 for Sunday, 1 for Monday, etc.

            // Add empty cells for days before the 1st of the month
            for (let i = 0; i < firstDayOfWeek; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.classList.add('calendar-day', 'empty');
                calendarGrid.appendChild(emptyDay);
            }

            // Add days of the month
            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = document.createElement('div');
                dayElement.classList.add('calendar-day');
                
                const fullDate = new Date(year, month, day);
                const dateString = fullDate.toISOString().split('T')[0]; // YYYY-MM-DD

                dayElement.innerHTML = `<span>${day}</span>`;
                dayElement.dataset.date = dateString;

                if (fullDate.toDateString() === new Date().toDateString()) {
                    dayElement.classList.add('today');
                }

                if (projectUnavailableDates.has(dateString)) {
                    dayElement.classList.add('project-unavailable');
                    dayElement.title = 'Unavailable due to ongoing project';
                } else if (selectedUnavailableDates.has(dateString)) {
                    dayElement.classList.add('selected');
                }

                if (!projectUnavailableDates.has(dateString)) {
                    dayElement.addEventListener('click', () => {
                        if (selectedUnavailableDates.has(dateString)) {
                            selectedUnavailableDates.delete(dateString);
                            dayElement.classList.remove('selected');
                        } else {
                            selectedUnavailableDates.add(dateString);
                            dayElement.classList.add('selected');
                        }
                    });
                }

                calendarGrid.appendChild(dayElement);
            }
        }

        prevMonthBtn.addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        });

        nextMonthBtn.addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        });

        saveAvailabilityBtn.addEventListener('click', () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'manage_availability.php';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'unavailable_dates';
            input.value = JSON.stringify(Array.from(selectedUnavailableDates));
            form.appendChild(input);

            document.body.appendChild(form);
            form.submit();
        });

        renderCalendar();

        const toggleAvailabilityStatusBtn = document.getElementById('toggleAvailabilityStatus');
        if (toggleAvailabilityStatusBtn) {
            toggleAvailabilityStatusBtn.addEventListener('click', () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'manage_availability.php';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'toggle_status';
                input.value = 'true';
                form.appendChild(input);

                document.body.appendChild(form);
                form.submit();
            });
        }
    </script>
</body>
</html>