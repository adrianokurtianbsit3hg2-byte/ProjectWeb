<?php
session_start();
include("../config.php");
include("../firebaseRDB.php");

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode([]);
    exit;
}

$db = new firebaseRDB($databaseURL);
$type = $_GET['type'] ?? 'student';
$campus = $_GET['campus'] ?? 'Main';

$logs = [];

if ($type === 'student') {
    $entries = json_decode($db->retrieve("Entry_log/Student"), true) ?? [];
    foreach ($entries as $key => $entry) {
        if (isset($entry['campus']) && strcasecmp($entry['campus'], $campus) === 0) {
            // Extract date from key (format: studentId-YYYY-MM-DD-count)
            $keyParts = explode('-', $key);
            if (count($keyParts) >= 4) {
                $entry['date'] = $keyParts[1] . '-' . $keyParts[2] . '-' . $keyParts[3];
            }
            $logs[] = $entry;
        }
    }
} elseif ($type === 'vip') {
    $entries = json_decode($db->retrieve("Entry_log/VIP"), true) ?? [];
    foreach ($entries as $key => $entry) {
        if (isset($entry['campus']) && strcasecmp($entry['campus'], $campus) === 0) {
            $keyParts = explode('-', $key);
            if (count($keyParts) >= 3) {
                $entry['date'] = $keyParts[0] . '-' . $keyParts[1] . '-' . $keyParts[2];
            }
            $logs[] = $entry;
        }
    }
} elseif ($type === 'visitor') {
    $entries = json_decode($db->retrieve("Entry_log/Visitor"), true) ?? [];
    foreach ($entries as $key => $entry) {
        if (isset($entry['campus']) && strcasecmp($entry['campus'], $campus) === 0) {
            $keyParts = explode('-', $key);
            if (count($keyParts) >= 3) {
                $entry['date'] = $keyParts[0] . '-' . $keyParts[1] . '-' . $keyParts[2];
            }
            $logs[] = $entry;
        }
    }
}

echo json_encode($logs);