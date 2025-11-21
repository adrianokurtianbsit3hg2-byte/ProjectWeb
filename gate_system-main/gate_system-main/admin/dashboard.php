<?php
session_start();
include("../config.php");
include("../firebaseRDB.php");

if (!isset($_SESSION['staff_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

$db = new firebaseRDB($databaseURL);

// Get selected campus from query parameter
$selectedCampus = $_GET['campus'] ?? 'Main';

// Retrieve all data
$studentsData = json_decode($db->retrieve("Student"), true) ?? [];
$staffData = json_decode($db->retrieve("Staff"), true) ?? [];
$guardsData = json_decode($db->retrieve("Guard"), true) ?? [];
$entriesData = json_decode($db->retrieve("Entry_log/Student"), true) ?? [];

// Filter students by campus
$students = [];
foreach ($studentsData as $key => $student) {
    if (isset($student['campus']) && strcasecmp(trim($student['campus']), trim($selectedCampus)) === 0) {
        $students[$key] = $student;
    }
}

// Filter staff by campus
$staff = [];
foreach ($staffData as $key => $s) {
    if (isset($s['campus']) && strcasecmp(trim($s['campus']), trim($selectedCampus)) === 0) {
        $staff[$key] = $s;
    }
}

// Filter guards by campus
$guards = [];
foreach ($guardsData as $key => $g) {
    if (isset($g['campus']) && strcasecmp(trim($g['campus']), trim($selectedCampus)) === 0) {
        $guards[$key] = $g;
    }
}

$totalStudents = count($students);
$totalStaff = count($staff);
$totalGuards = count($guards);

// Filter entries by campus
$entries = [];
foreach ($entriesData as $key => $entry) {
    if (isset($entry['campus']) && strcasecmp(trim($entry['campus']), trim($selectedCampus)) === 0) {
        $entries[$key] = $entry;
    }
}

$totalTimeIn = $totalTimeOut = $totalViolations = 0;
$recentLogs = [];

foreach ($entries as $key => $entry) {
    if (isset($entry['time_in'])) $totalTimeIn++;
    if (isset($entry['time_out'])) $totalTimeOut++;
    if (isset($entry['violation']) && $entry['violation'] !== 'None') $totalViolations++;
    $recentLogs[] = array_merge($entry, ['key' => $key]);
}

usort($recentLogs, function ($a, $b) {
    $timeA = $a['time_out'] ?? $a['time_in'] ?? '00:00:00';
    $timeB = $b['time_out'] ?? $b['time_in'] ?? '00:00:00';
    $dateA = ($a['date'] ?? '2000-01-01') . ' ' . $timeA;
    $dateB = ($b['date'] ?? '2000-01-01') . ' ' . $timeB;
    return strcmp($dateB, $dateA);
});

$recentLogs = array_slice($recentLogs, 0, 10);

function formatTime($time)
{
    if (empty($time) || $time === 'N/A') return 'N/A';
    $parts = explode(':', $time);
    $hours = (int)$parts[0];
    $minutes = $parts[1] ?? '00';
    $ampm = $hours >= 12 ? 'PM' : 'AM';
    $hours = $hours % 12;
    if ($hours === 0) $hours = 12;
    return sprintf('%d:%s %s', $hours, $minutes, $ampm);
}
?>
    <style>
        body {
            font-family: "Inter", sans-serif;
            background: #f0f2f5;
            color: #333;
            margin: 0;
        }

        /* Layout */
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Titles */
        h1 {
            font-size: 26px;
            color: #b10312;
            margin-bottom: 25px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        h3 {
            color: black !important;
            font-weight: bold;
            letter-spacing: 1px;
        }

        /* Sections */
        .section {
            margin-bottom: 20px;
        }

        /* Cards Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 10px;
        }

        /* Card */
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

        /* Accent Bar */
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

        /* Card Icon */
        .card-icon {
            font-size: 40px;
            color: #b10312;
            margin-bottom: 12px;
        }

        /* Card Label */
        .card h3 {
            margin: 0 0 12px 0;
            font-size: 15px;
            color: #555;
            font-weight: 600;
        }

        /* Card Number */
        .card .number {
            font-size: 40px;
            font-weight: 800;
            color: #b10312;
            margin: 0;
        }

        /* Table */
        .table-container {
            background: #ffffff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.07);
            border: 1px solid #e8e8e8;
        }

        /* Table headings */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #b10312;
            color: #ffffff;
            padding: 16px 12px;
            text-align: left;
            font-size: 13.5px;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        /* Table Row Hover */
        tr:hover {
            background: #fff4f5;
        }

        /* Badges */
        .badge {
            padding: 6px 14px;
            border-radius: 25px;
            font-size: 11.5px;
            font-weight: 600;
            display: inline-block;
            letter-spacing: 0.4px;
        }

        .badge-in {
            background: #e8f7ee;
            color: #1b8d34;
        }

        .badge-out {
            background: #fff1db;
            color: #d46a00;
        }

        /* Violations */
        .violation {
            color: #d32f2f;
            font-weight: 700;
        }

        .no-violation {
            color: #2e7d32;
            font-weight: 700;
        }

        .no-data {
            text-align: center;
            padding: 45px;
            color: #777;
            font-size: 15px;
        }

        /* Responsive */
        @media(max-width:768px) {
            h1 {
                font-size: 20px;
            }

            .card .number {
                font-size: 32px;
            }
        }
    </style>

    <div class="container">

        <div class="section">
            <h1><i class="fas fa-users"></i> User Overview - <?php echo htmlspecialchars($selectedCampus); ?> Campus</h1>
            <div class="cards-grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-user-graduate"></i></div>
                    <h3>Total Students</h3>
                    <p class="number"><?php echo $totalStudents; ?></p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <h3>Total Staff</h3>
                    <p class="number"><?php echo $totalStaff; ?></p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-shield-alt"></i></div>
                    <h3>Total Guards</h3>
                    <p class="number"><?php echo $totalGuards; ?></p>
                </div>
            </div>
        </div>

        <div class="section">
            <h1><i class="fas fa-door-open"></i> Entry Overview</h1>
            <div class="cards-grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-sign-in-alt"></i></div>
                    <h3>Total Time In</h3>
                    <p class="number"><?php echo $totalTimeIn; ?></p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-sign-out-alt"></i></div>
                    <h3>Total Time Out</h3>
                    <p class="number"><?php echo $totalTimeOut; ?></p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <h3>Total Violations</h3>
                    <p class="number"><?php echo $totalViolations; ?></p>
                </div>
            </div>
        </div>

        <div class="section">
            <h1><i class="fas fa-history"></i> Recent Gate Logs</h1>
            <div class="table-container">
                <?php if (empty($recentLogs)): ?>
                    <div class="no-data">No recent logs available for <?php echo htmlspecialchars($selectedCampus); ?> campus.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Full Name</th>
                                <th>Action</th>
                                <th>Time</th>
                                <th>Campus</th>
                                <th>Gate</th>
                                <th>Violation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log):
                                $action = isset($log['time_out']) ? 'Time Out' : 'Time In';
                                $time = $log['time_out'] ?? $log['time_in'] ?? 'N/A';
                                $violation = ($log['violation'] ?? 'None') !== 'None' ? $log['violation'] : 'None';
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($log['student_id'] ?? 'N/A'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($log['full_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge <?php echo isset($log['time_out']) ? 'badge-out' : 'badge-in'; ?>">
                                            <?php echo $action; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatTime($time); ?></td>
                                    <td><?php echo htmlspecialchars($log['campus'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($log['gate_in'] ?? 'N/A'); ?></td>
                                    <td class="<?php echo $violation !== 'None' ? 'violation' : 'no-violation'; ?>">
                                        <?php echo htmlspecialchars($violation); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>
