<?php
session_start();
include("../config.php");
include("../firebaseRDB.php");

if (!isset($_SESSION['staff_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

$db = new firebaseRDB($databaseURL);

// Get staff campus from session
$staffCampus = $_SESSION['staff_campus'] ?? 'Main';

// Retrieve all data
$studentsData  = json_decode($db->retrieve("Student"), true) ?? [];
$guardsData    = json_decode($db->retrieve("Guard"), true) ?? [];
$schedulesData = json_decode($db->retrieve("Schedule"), true) ?? [];

// Filter by staff campus
$students  = [];
$sections  = [];
$guards    = [];
$schedules = [];

foreach ($studentsData as $s) {
    if (isset($s['campus']) && strcasecmp(trim($s['campus']), $staffCampus) === 0) {
        $students[] = $s;
        if (isset($s['section']) && !in_array($s['section'], $sections)) {
            $sections[] = $s['section'];
        }
    }
}

foreach ($guardsData as $g) {
    if (isset($g['campus']) && strcasecmp(trim($g['campus']), $staffCampus) === 0) {
        $guards[] = $g;
    }
}

foreach ($schedulesData as $sched) {
    if (isset($sched['campus']) && strcasecmp(trim($sched['campus']), $staffCampus) === 0) {
        $schedules[] = $sched;
    }
}

// Totals
$totalStudents  = count($students);
$totalSections  = count($sections);
$totalGuards    = count($guards);
$totalSchedules = count($schedules);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: "Inter", sans-serif;
            background: #f0f2f5;
            color: #333;
            margin: 0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            font-size: 26px;
            color: #b10312;
            margin-bottom: 25px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 10px;
        }

        .card {
            background: #ffffff;
            border-radius: 18px;
            padding: 10px 28px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.07);
            border: 1px solid #ececec;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 10px 32px rgba(177, 3, 18, 0.18);
            border-color: #b10312;
        }

        .card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: #b10312;
            border-radius: 18px 0 0 18px;
        }

        .card-icon {
            font-size: 40px;
            color: #b10312;
            margin-bottom: 12px;
        }

        .card h3 {
            margin: 0 0 12px 0;
            font-size: 15px;
            color: #555;
            font-weight: 600;
        }

        .card .number {
            font-size: 40px;
            font-weight: 800;
            color: #b10312;
            margin: 0;
        }

        @media(max-width:768px) {
            h1 {
                font-size: 20px;
            }

            .card .number {
                font-size: 32px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="section">
            <h1><i class="fas fa-users"></i> Overview - <?= htmlspecialchars($staffCampus) ?> Campus</h1>
            <div class="cards-grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-user-graduate"></i></div>
                    <h3>Total Students</h3>
                    <p class="number"><?= $totalStudents ?></p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-layer-group"></i></div>
                    <h3>Total Sections</h3>
                    <p class="number"><?= $totalSections ?></p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-shield-alt"></i></div>
                    <h3>Total Guards</h3>
                    <p class="number"><?= $totalGuards ?></p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-calendar-alt"></i></div>
                    <h3>Total Schedules</h3>
                    <p class="number"><?= $totalSchedules ?></p>
                </div>
            </div>
        </div>
    </div>
</body>

</html>