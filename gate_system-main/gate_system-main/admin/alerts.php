<?php
session_start();
include("../config.php");
include("../firebaseRDB.php");

if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['staff_logged_in'])) {
    echo "<div class='error-message'>Not authenticated</div>";
    exit;
}

$db = new firebaseRDB($databaseURL);
date_default_timezone_set('Asia/Manila');

$selectedCampus = $_GET['campus'] ?? $_SESSION['staff_campus'] ?? 'Main';

$entries = json_decode($db->retrieve("Entry_log/Student"), true) ?? [];
$schedules = json_decode($db->retrieve("Schedule"), true) ?? [];

function isOutsideSchedule($studentSection, $time, $schedules)
{
    $now = DateTime::createFromFormat('H:i:s', $time);
    foreach ($schedules as $sched) {
        if (strtolower(trim($sched['section'])) === strtolower(trim($studentSection))) {
            $timeFrom = DateTime::createFromFormat('H:i', $sched['time_from']);
            $timeTo   = DateTime::createFromFormat('H:i', $sched['time_to']);
            $allowedStart = (clone $timeFrom)->modify('-1 hour');
            $allowedEnd   = (clone $timeTo)->modify('+1 hour');
            if ($now >= $allowedStart && $now <= $allowedEnd) return false;
        }
    }
    return true;
}

$campusEntries = array_filter($entries, fn($entry) => strtolower($entry['campus'] ?? '') === strtolower($selectedCampus));

echo "<div class='alerts-container' style='display:flex; flex-direction:column; gap:20px; max-width:750px; margin:auto;'>";

foreach ($campusEntries as $entry) {
    $studentId = $entry['student_id'] ?? '';
    $section = $entry['section'] ?? '';
    $campus = $entry['campus'] ?? '';
    $gateIn = $entry['gate_in'] ?? '';
    $violation = $entry['violation'] ?? 'None';
    $timeIn = $entry['time_in'] ?? '';
    $timeOut = $entry['time_out'] ?? '';

    $message = '';
    $icon = '';
    $bgGradient = '';
    $borderColor = '';
    $iconBg = '';
    $textColor = '#333';

    if ($violation !== 'None') {
        $message = "Student <strong>$studentId</strong> from <strong>$section</strong> at <strong>$campus</strong> via <strong>$gateIn</strong> committed a violation: <em>$violation</em>.";
        $icon = '<i class="fas fa-exclamation-triangle"></i>';
        $bgGradient = 'linear-gradient(135deg, #a60212, #ff4d4d)';
        $borderColor = '#a60212';
        $iconBg = '#fff0f0';
        $textColor = 'white';
    } elseif ($timeOut && isOutsideSchedule($section, $timeOut, $schedules)) {
        $message = "Student <strong>$studentId</strong> from <strong>$section</strong> at <strong>$campus</strong> via <strong>$gateIn</strong> attempted to leave the campus outside scheduled time.";
        $icon = '<i class="fas fa-door-open"></i>';
        $bgGradient = 'linear-gradient(135deg, #f5ab29, #ffd87c)';
        $borderColor = '#f5ab29';
        $iconBg = 'rgba(255,255,255,0.2)';
        $textColor = '#333';
    } elseif ($timeIn && isOutsideSchedule($section, $timeIn, $schedules)) {
        $message = "Student <strong>$studentId</strong> from <strong>$section</strong> at <strong>$campus</strong> via <strong>$gateIn</strong> attempted to enter the campus outside scheduled time.";
        $icon = '<i class="fas fa-user-clock"></i>';
        $bgGradient = 'linear-gradient(135deg, #f5ab29, #ffd87c)';
        $borderColor = '#f5ab29';
        $iconBg = 'rgba(255,255,255,0.2)';
        $textColor = '#333';
    }

    if ($message !== '') {
        echo "
        <div class='alert-card' style='display:flex; align-items:center; gap:15px; padding:18px 22px; border-radius:14px; background:$bgGradient; border-left:6px solid $borderColor; box-shadow:0 6px 18px rgba(0,0,0,0.12); transition:transform 0.2s, box-shadow 0.2s; cursor:pointer;'>
            <div class='alert-icon-wrapper' style='width:50px; height:50px; border-radius:50%; background:$iconBg; display:flex; align-items:center; justify-content:center; font-size:22px; color:$borderColor; flex-shrink:0;'>$icon</div>
            <div class='alert-message' style='font-size:15px; color:$textColor; line-height:1.5;'>$message</div>
        </div>
        <style>
            .alert-card:hover {
                transform: translateY(-3px);
                box-shadow:0 10px 20px rgba(0,0,0,0.18);
            }
        </style>
        ";
    }
}

echo "</div>";

if (empty($campusEntries)) {
    echo "<div class='no-alerts' style='font-size:15px; color:#555; text-align:center; padding:20px;'>No alerts at the moment for <strong>$selectedCampus</strong> campus.</div>";
}
