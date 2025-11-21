<?php
session_start();
include("../config.php");
include("../firebaseRDB.php");
if (!isset($_SESSION['staff_logged_in'])) {
    header("Location: ../index.php");
    exit;
}
$db = new firebaseRDB($databaseURL);
$selectedCampus = $_GET['campus'] ?? 'Main';

// Retrieve all data
$studentsData = json_decode($db->retrieve("Student"), true) ?? [];
$staffData = json_decode($db->retrieve("Staff"), true) ?? [];
$guardsData = json_decode($db->retrieve("Guard"), true) ?? [];

// Retrieve entry logs from all types
$studentEntries = json_decode($db->retrieve("Entry_log/Student"), true) ?? [];
$vipEntries = json_decode($db->retrieve("Entry_log/VIP"), true) ?? [];
$visitorEntries = json_decode($db->retrieve("Entry_log/Visitor"), true) ?? [];

// Process entries and extract dates from keys
$allEntries = [];

// Process Student entries
foreach ($studentEntries as $key => $entry) {
    $keyParts = explode('-', $key);
    if (count($keyParts) >= 4) {
        $entry['date'] = $keyParts[1] . '-' . $keyParts[2] . '-' . $keyParts[3];
        $entry['user_type'] = 'Student';
        $allEntries[] = $entry;
    }
}

// Process VIP entries
foreach ($vipEntries as $key => $entry) {
    $keyParts = explode('-', $key);
    if (count($keyParts) >= 3) {
        $entry['date'] = $keyParts[0] . '-' . $keyParts[1] . '-' . $keyParts[2];
        $entry['user_type'] = 'VIP';
        $allEntries[] = $entry;
    }
}

// Process Visitor entries
foreach ($visitorEntries as $key => $entry) {
    $keyParts = explode('-', $key);
    if (count($keyParts) >= 3) {
        $entry['date'] = $keyParts[0] . '-' . $keyParts[1] . '-' . $keyParts[2];
        $entry['user_type'] = 'Visitor';
        $allEntries[] = $entry;
    }
}

// Filter by campus
$students = array_filter($studentsData, function ($s) use ($selectedCampus) {
    return isset($s['campus']) && strcasecmp(trim($s['campus']), trim($selectedCampus)) === 0;
});

$staff = array_filter($staffData, function ($s) use ($selectedCampus) {
    return isset($s['campus']) && strcasecmp(trim($s['campus']), trim($selectedCampus)) === 0;
});

$guards = array_filter($guardsData, function ($g) use ($selectedCampus) {
    return isset($g['campus']) && strcasecmp(trim($g['campus']), trim($selectedCampus)) === 0;
});

$entries = array_filter($allEntries, function ($e) use ($selectedCampus) {
    return isset($e['campus']) && strcasecmp(trim($e['campus']), trim($selectedCampus)) === 0;
});

// Get available months from entries
$availableMonths = [];
foreach ($entries as $entry) {
    if (isset($entry['date']) && !empty($entry['date']) && $entry['date'] !== 'Unknown') {
        $timestamp = strtotime($entry['date']);
        // Only add valid dates (not 1970 or invalid timestamps)
        if ($timestamp !== false && $timestamp > 0 && date('Y', $timestamp) > 1970) {
            $month = date('Y-m', $timestamp);
            $availableMonths[$month] = date('F Y', $timestamp);
        }
    }
}
krsort($availableMonths);

// Get selected month (default to first available month or current month)
$selectedMonth = $_GET['month'] ?? (count($availableMonths) > 0 ? array_key_first($availableMonths) : date('Y-m'));

$monthEntries = array_filter($entries, function ($e) use ($selectedMonth) {
    return isset($e['date']) && strpos($e['date'], $selectedMonth) === 0;
});

// Calculate statistics
function calculateStats($entries)
{
    $gateSummary = [];
    $dailyEntry = [];
    $violations = [];

    foreach ($entries as $entry) {
        // Gate Summary
        $gate = $entry['gate_in'] ?? 'Unknown';
        if (!isset($gateSummary[$gate])) {
            $gateSummary[$gate] = 0;
        }
        $gateSummary[$gate]++;

        // Daily Entry
        $date = $entry['date'] ?? 'Unknown';
        if (!isset($dailyEntry[$date])) {
            $dailyEntry[$date] = [
                'total' => 0,
                'students' => 0,
                'visitors' => 0,
                'vip' => 0
            ];
        }
        $dailyEntry[$date]['total']++;

        $userType = strtolower($entry['user_type'] ?? 'student');
        if (strpos($userType, 'student') !== false) {
            $dailyEntry[$date]['students']++;
        } elseif (strpos($userType, 'visitor') !== false) {
            $dailyEntry[$date]['visitors']++;
        } elseif (strpos($userType, 'vip') !== false) {
            $dailyEntry[$date]['vip']++;
        }

        // Violations
        $violation = $entry['violation'] ?? 'None';
        if ($violation !== 'None' && !empty($violation)) {
            if (!isset($violations[$violation])) {
                $violations[$violation] = 0;
            }
            $violations[$violation]++;
        }
    }

    return [
        'gateSummary' => $gateSummary,
        'dailyEntry' => $dailyEntry,
        'violations' => $violations
    ];
}

$stats = calculateStats($monthEntries);
?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .reports-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #b10312 0%, #8b0110 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(177, 3, 18, 0.2);
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .controls-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .filter-group label {
            font-weight: 600;
            color: #555;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            min-width: 200px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #b10312;
        }

        .btn-download {
            background: linear-gradient(135deg, #b10312 0%, #8b0110 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            box-shadow: 0 3px 10px rgba(177, 3, 18, 0.3);
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(177, 3, 18, 0.4);
        }

        .btn-download i {
            font-size: 18px;
        }

        .report-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            color: #b10312;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 3px solid #f0f0f0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: linear-gradient(135deg, #fff 0%, #f9f9f9 100%);
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #b10312;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card h4 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #b10312;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table thead {
            background: #b10312;
            color: white;
        }

        .data-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .data-table tbody tr:hover {
            background: #fff8f9;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 16px;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-high {
            background: #fee;
            color: #d32f2f;
        }

        .badge-medium {
            background: #fff4e5;
            color: #f57c00;
        }

        .badge-low {
            background: #e8f5e9;
            color: #388e3c;
        }

        @media print {
            .controls-section {
                display: none;
            }

            .report-section {
                page-break-inside: avoid;
            }
        }

        @media (max-width: 768px) {
            .controls-section {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group select {
                width: 100%;
            }

            .btn-download {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <div class="reports-container">
        <div class="page-header">
            <h1>
                <i class="fas fa-chart-bar"></i>
                Reports Dashboard
            </h1>
            <p><?php echo htmlspecialchars($selectedCampus); ?> Campus - Comprehensive Analytics & Insights</p>
        </div>

        <div class="controls-section">
            <div class="filter-group">
                <label>
                    <i class="fas fa-calendar-alt"></i>
                    Select Month:
                </label>
                <select id="monthSelect" onchange="changeMonth()">
                    <?php if (empty($availableMonths)): ?>
                        <option value="">No data available</option>
                    <?php else: ?>
                        <?php foreach ($availableMonths as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $value === $selectedMonth ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <button class="btn-download" onclick="downloadPDF()">
                <i class="fas fa-file-pdf"></i>
                Download PDF Report
            </button>
        </div>

        <!-- User Distribution Section -->
        <div class="report-section" id="userDistribution">
            <div class="section-title">
                <i class="fas fa-users"></i>
                User Distribution Report
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Total Students</h4>
                    <div class="value"><?php echo count($students); ?></div>
                </div>
                <div class="stat-card">
                    <h4>Total Staff</h4>
                    <div class="value"><?php echo count($staff); ?></div>
                </div>
                <div class="stat-card">
                    <h4>Total Guards</h4>
                    <div class="value"><?php echo count($guards); ?></div>
                </div>
                <div class="stat-card">
                    <h4>Total Users</h4>
                    <div class="value"><?php echo count($students) + count($staff) + count($guards); ?></div>
                </div>
            </div>
        </div>

        <!-- Gate Summary Section -->
        <div class="report-section" id="gateSummary">
            <div class="section-title">
                <i class="fas fa-door-open"></i>
                Gate Summary - <?php echo date('F Y', strtotime($selectedMonth . '-01')); ?>
            </div>
            <?php if (empty($stats['gateSummary'])): ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                    <p>No gate entry data available for this month</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Gate Name</th>
                            <th>Total Entries</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalEntries = array_sum($stats['gateSummary']);
                        arsort($stats['gateSummary']);
                        foreach ($stats['gateSummary'] as $gate => $count):
                            $percentage = $totalEntries > 0 ? round(($count / $totalEntries) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($gate); ?></strong></td>
                                <td><?php echo $count; ?></td>
                                <td><?php echo $percentage; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f9f9f9; font-weight: bold;">
                            <td>TOTAL</td>
                            <td><?php echo $totalEntries; ?></td>
                            <td>100%</td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Daily Entry Section -->
        <div class="report-section" id="dailyEntry">
            <div class="section-title">
                <i class="fas fa-calendar-day"></i>
                Daily Entry Breakdown
            </div>
            <?php if (empty($stats['dailyEntry'])): ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 10px;"></i>
                    <p>No daily entry data available for this month</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Total Entries</th>
                            <th>Students</th>
                            <th>Visitors</th>
                            <th>VIP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        krsort($stats['dailyEntry']);
                        foreach ($stats['dailyEntry'] as $date => $data):
                            $dayName = date('l', strtotime($date));
                        ?>
                            <tr>
                                <td><strong><?php echo date('M d, Y', strtotime($date)); ?></strong></td>
                                <td><?php echo $dayName; ?></td>
                                <td><?php echo $data['total']; ?></td>
                                <td><?php echo $data['students']; ?></td>
                                <td><?php echo $data['visitors']; ?></td>
                                <td><?php echo $data['vip']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Violations Section -->
        <div class="report-section" id="violations">
            <div class="section-title">
                <i class="fas fa-exclamation-triangle"></i>
                Violations Report
            </div>
            <?php if (empty($stats['violations'])): ?>
                <div class="no-data">
                    <i class="fas fa-check-circle" style="font-size: 48px; color: #4caf50; margin-bottom: 10px;"></i>
                    <p>No violations recorded for this month</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Violation Type</th>
                            <th>Count</th>
                            <th>Severity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        arsort($stats['violations']);
                        foreach ($stats['violations'] as $violation => $count):
                            $severity = $count > 10 ? 'high' : ($count > 5 ? 'medium' : 'low');
                            $badgeClass = "badge-$severity";
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($violation); ?></strong></td>
                                <td><?php echo $count; ?></td>
                                <td>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo strtoupper($severity); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f9f9f9; font-weight: bold;">
                            <td>TOTAL VIOLATIONS</td>
                            <td><?php echo array_sum($stats['violations']); ?></td>
                            <td>-</td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function changeMonth() {
            const month = document.getElementById('monthSelect').value;
            if (month) {
                const campus = '<?php echo urlencode($selectedCampus); ?>';
                window.location.href = 'reports.php?campus=' + campus + '&month=' + month;
            }
        }

        function downloadPDF() {
            try {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF();

                const campus = <?php echo json_encode($selectedCampus); ?>;
                const month = <?php echo json_encode(date('F Y', strtotime($selectedMonth . '-01'))); ?>;

                // Title
                doc.setFontSize(20);
                doc.setTextColor(177, 3, 18);
                doc.text('Gate System Report', 14, 20);

                doc.setFontSize(12);
                doc.setTextColor(0, 0, 0);
                doc.text('Campus: ' + campus, 14, 28);
                doc.text('Report Period: ' + month, 14, 34);
                doc.text('Generated: ' + new Date().toLocaleDateString(), 14, 40);

                let yPos = 50;

                // User Distribution
                doc.setFontSize(14);
                doc.setTextColor(177, 3, 18);
                doc.text('User Distribution', 14, yPos);
                yPos += 10;

                doc.autoTable({
                    startY: yPos,
                    head: [
                        ['User Type', 'Count']
                    ],
                    body: [
                        ['Students', '<?php echo count($students); ?>'],
                        ['Staff', '<?php echo count($staff); ?>'],
                        ['Guards', '<?php echo count($guards); ?>'],
                        ['Total', '<?php echo count($students) + count($staff) + count($guards); ?>']
                    ],
                    theme: 'grid',
                    headStyles: {
                        fillColor: [177, 3, 18]
                    }
                });

                yPos = doc.lastAutoTable.finalY + 15;

                // Gate Summary
                <?php if (!empty($stats['gateSummary'])): ?>
                    doc.setFontSize(14);
                    doc.setTextColor(177, 3, 18);
                    doc.text('Gate Summary', 14, yPos);
                    yPos += 10;

                    var gateData = [
                        <?php
                        $totalEntries = array_sum($stats['gateSummary']);
                        arsort($stats['gateSummary']);
                        $gateRows = [];
                        foreach ($stats['gateSummary'] as $gate => $count) {
                            $percentage = $totalEntries > 0 ? round(($count / $totalEntries) * 100, 1) : 0;
                            $gateRows[] = "['" . addslashes($gate) . "', '" . $count . "', '" . $percentage . "%']";
                        }
                        echo implode(",\n                    ", $gateRows);
                        ?>
                    ];

                    doc.autoTable({
                        startY: yPos,
                        head: [
                            ['Gate Name', 'Total Entries', 'Percentage']
                        ],
                        body: gateData,
                        theme: 'grid',
                        headStyles: {
                            fillColor: [177, 3, 18]
                        }
                    });

                    yPos = doc.lastAutoTable.finalY + 15;
                <?php endif; ?>

                // Violations
                <?php if (!empty($stats['violations'])): ?>
                    if (yPos > 250) {
                        doc.addPage();
                        yPos = 20;
                    }

                    doc.setFontSize(14);
                    doc.setTextColor(177, 3, 18);
                    doc.text('Violations Report', 14, yPos);
                    yPos += 10;

                    var violationsData = [
                        <?php
                        arsort($stats['violations']);
                        $violRows = [];
                        foreach ($stats['violations'] as $violation => $count) {
                            $violRows[] = "['" . addslashes($violation) . "', '" . $count . "']";
                        }
                        echo implode(",\n                    ", $violRows);
                        ?>
                    ];

                    doc.autoTable({
                        startY: yPos,
                        head: [
                            ['Violation Type', 'Count']
                        ],
                        body: violationsData,
                        theme: 'grid',
                        headStyles: {
                            fillColor: [177, 3, 18]
                        }
                    });
                <?php endif; ?>

                // Save PDF
                var filename = campus + '_Report_' + month.replace(/ /g, '_') + '.pdf';
                doc.save(filename);

                console.log('PDF generated successfully!');
            } catch (error) {
                console.error('PDF Generation Error:', error);
                alert('Error generating PDF: ' + error.message);
            }
        }
    </script>
