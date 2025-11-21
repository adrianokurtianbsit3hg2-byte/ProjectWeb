<?php
session_start();
include("../config.php");
include("../firebaseRDB.php");

// Check authentication
if (!isset($_SESSION['admin_logged_in'])) {
    echo "<div class='error-message'>Not authenticated</div>";
    exit;
}

$db = new firebaseRDB($databaseURL);

// Get selected campus from URL parameter, default to 'Main'
$selectedCampus = $_GET['campus'] ?? 'Main';

// Helper function to format time
function formatTime($time)
{
    if (empty($time) || $time === 'N/A') return 'N/A';

    $timeParts = explode(':', $time);
    if (count($timeParts) >= 2) {
        $hours = (int)$timeParts[0];
        $minutes = $timeParts[1];
        $ampm = $hours >= 12 ? 'PM' : 'AM';
        $hours = $hours % 12;
        if ($hours === 0) $hours = 12;
        return sprintf('%d:%s %s', $hours, $minutes, $ampm);
    }

    return $time;
}

// Function to fetch and filter logs
function fetchLogs($db, $type, $campus)
{
    $logs = [];

    try {
        $entries = json_decode($db->retrieve("Entry_log/{$type}"), true) ?? [];

        foreach ($entries as $key => $entry) {
            // Case-insensitive campus comparison
            if (isset($entry['campus']) && strcasecmp($entry['campus'], $campus) === 0) {
                // Extract date from key based on log type
                $keyParts = explode('-', $key);

                if ($type === 'Student') {
                    // Format: studentId-YYYY-MM-DD-count
                    if (count($keyParts) >= 4) {
                        $entry['date'] = $keyParts[1] . '-' . $keyParts[2] . '-' . $keyParts[3];
                    }
                } else {
                    // Format for VIP and Visitor: YYYY-MM-DD-...
                    if (count($keyParts) >= 3) {
                        $entry['date'] = $keyParts[0] . '-' . $keyParts[1] . '-' . $keyParts[2];
                    }
                }

                // Fallback to current date if date extraction failed
                if (!isset($entry['date'])) {
                    $entry['date'] = date('Y-m-d');
                }

                $logs[] = $entry;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching {$type} logs: " . $e->getMessage());
    }

    return $logs;
}

// Fetch all logs
$studentLogs = fetchLogs($db, 'Student', $selectedCampus);
$vipLogs = fetchLogs($db, 'VIP', $selectedCampus);
$visitorLogs = fetchLogs($db, 'Visitor', $selectedCampus);

// Sort logs by date and time (newest first)
function sortLogsByDateTime($a, $b)
{
    $timeA = $a['time_out'] ?? $a['time_in'] ?? '00:00:00';
    $timeB = $b['time_out'] ?? $b['time_in'] ?? '00:00:00';
    $dateA = ($a['date'] ?? '2000-01-01') . ' ' . $timeA;
    $dateB = ($b['date'] ?? '2000-01-01') . ' ' . $timeB;
    return strcmp($dateB, $dateA);
}

usort($studentLogs, 'sortLogsByDateTime');
usort($vipLogs, 'sortLogsByDateTime');
usort($visitorLogs, 'sortLogsByDateTime');
?>

<style>
    .logs-container {
        padding: 20px;
    }

    .logs-header {
        margin-bottom: 20px;
    }

    .logs-header h2 {
        color: var(--primary);
        margin-bottom: 10px;
    }

    .filter-section {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
        align-items: center;
    }

    .filter-btn {
        padding: 10px 20px;
        border: 2px solid var(--primary);
        background: white;
        color: var(--primary);
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
    }

    .filter-btn:hover {
        background: var(--primary);
        color: white;
    }

    .filter-btn.active {
        background: var(--primary);
        color: white;
    }

    .search-box {
        flex: 1;
        min-width: 250px;
        position: relative;
    }

    .search-box input {
        width: 100%;
        padding: 10px 40px 10px 15px;
        border: 2px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }

    .search-box i {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #666;
    }

    .table-container {
        overflow-x: auto;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .logs-table {
        width: 100%;
        border-collapse: collapse;
    }

    .logs-table thead {
        background: var(--primary);
        color: white;
    }

    .logs-table th {
        padding: 15px;
        text-align: left;
        font-weight: 600;
        font-size: 14px;
    }

    .logs-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #eee;
        font-size: 14px;
    }

    .logs-table tbody tr:hover {
        background: #f8f9fa;
    }

    .action-badge {
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }

    .action-time-in {
        background: #d4edda;
        color: #155724;
    }

    .action-time-out {
        background: #fff3cd;
        color: #856404;
    }

    .violation-badge {
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: 600;
        background: #f8d7da;
        color: #721c24;
    }

    .no-violation {
        color: #28a745;
        font-weight: 600;
    }

    .no-data {
        text-align: center;
        padding: 40px;
        color: #666;
    }

    .date-display {
        color: #666;
        font-size: 12px;
    }

    .refresh-btn {
        padding: 10px 20px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .refresh-btn:hover {
        opacity: 0.9;
    }

    .table-wrapper {
        display: none;
    }

    .table-wrapper.active {
        display: block;
    }

    .badge-count {
        background: white;
        color: var(--primary);
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        margin-left: 5px;
    }

    .filter-btn.active .badge-count {
        background: rgba(255, 255, 255, 0.3);
        color: white;
    }

    .campus-indicator {
        display: inline-block;
        padding: 5px 15px;
        background: #a60212;
        color: #f5ab29;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
        margin-left: 10px;
        font-size: 16px;
    }
</style>

<div class="logs-container">
    <div class="logs-header">
        <h2>
            Log History
            <span class="campus-indicator">
                <i class="fas fa-building">&nbsp;&nbsp;</i> <?php echo htmlspecialchars($selectedCampus); ?> Campus
            </span>
        </h2>
        <!-- <p class="date-display">Last updated: <?php echo date('F j, Y g:i A'); ?></p> -->
    </div>

    <div class="filter-section">
        <button class="filter-btn active" data-filter="student">
            <i class="fas fa-user-graduate"></i> Students
            <span class="badge-count"><?php echo count($studentLogs); ?></span>
        </button>
        <button class="filter-btn" data-filter="vip">
            <i class="fas fa-star"></i> VIP
            <span class="badge-count"><?php echo count($vipLogs); ?></span>
        </button>
        <button class="filter-btn" data-filter="visitor">
            <i class="fas fa-users"></i> Visitors
            <span class="badge-count"><?php echo count($visitorLogs); ?></span>
        </button>

        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search logs...">
            <i class="fas fa-search"></i>
        </div>

        <button class="refresh-btn" onclick="refreshLogs()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>

    <div class="table-container">
        <!-- Student Table -->
        <div class="table-wrapper active" id="student-table">
            <?php if (count($studentLogs) === 0): ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; color: #ccc;"></i><br>
                    <strong>No student logs found</strong><br>
                    <small>for <?php echo htmlspecialchars($selectedCampus); ?> Campus</small>
                </div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Action</th>
                            <th>Time</th>
                            <th>Guard In</th>
                            <th>Gate</th>
                            <th>Violation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($studentLogs as $log):
                            $action = isset($log['time_out']) ? 'Time Out' : 'Time In';
                            $actionClass = isset($log['time_out']) ? 'action-time-out' : 'action-time-in';
                            $time = $log['time_out'] ?? $log['time_in'] ?? 'N/A';
                            $violation = (isset($log['violation']) && $log['violation'] !== 'None') ? $log['violation'] : '';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['student_id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($log['full_name'] ?? 'N/A'); ?></td>
                                <td><span class="action-badge <?php echo $actionClass; ?>"><?php echo $action; ?></span></td>
                                <td>
                                    <strong><?php echo formatTime($time); ?></strong>
                                    <br><small class="date-display"><?php echo date('M d, Y', strtotime($log['date'] ?? 'now')); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($log['guard_in'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($log['gate_in'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($violation): ?>
                                        <span class="violation-badge"><?php echo htmlspecialchars($violation); ?></span>
                                    <?php else: ?>
                                        <span class="no-violation">None</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- VIP Table -->
        <div class="table-wrapper" id="vip-table">
            <?php if (count($vipLogs) === 0): ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; color: #ccc;"></i><br>
                    <strong>No VIP logs found</strong><br>
                    <small>for <?php echo htmlspecialchars($selectedCampus); ?> Campus</small>
                </div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>VIP Name</th>
                            <th>Reason</th>
                            <th>Gate</th>
                            <th>Action</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vipLogs as $log):
                            $action = isset($log['time_out']) ? 'Time Out' : 'Time In';
                            $actionClass = isset($log['time_out']) ? 'action-time-out' : 'action-time-in';
                            $time = $log['time_out'] ?? $log['time_in'] ?? 'N/A';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($log['reason'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($log['gate'] ?? 'N/A'); ?></td>
                                <td><span class="action-badge <?php echo $actionClass; ?>"><?php echo $action; ?></span></td>
                                <td>
                                    <strong><?php echo formatTime($time); ?></strong>
                                    <br><small class="date-display"><?php echo date('M d, Y', strtotime($log['date'] ?? 'now')); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Visitor Table -->
        <div class="table-wrapper" id="visitor-table">
            <?php if (count($visitorLogs) === 0): ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; color: #ccc;"></i><br>
                    <strong>No visitor logs found</strong><br>
                    <small>for <?php echo htmlspecialchars($selectedCampus); ?> Campus</small>
                </div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Visitor Name</th>
                            <th>Reason</th>
                            <th>Address</th>
                            <th>Gate</th>
                            <th>Action</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visitorLogs as $log):
                            $action = isset($log['time_out']) ? 'Time Out' : 'Time In';
                            $actionClass = isset($log['time_out']) ? 'action-time-out' : 'action-time-in';
                            $time = $log['time_out'] ?? $log['time_in'] ?? 'N/A';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($log['reason'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($log['address'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($log['gate'] ?? 'N/A'); ?></td>
                                <td><span class="action-badge <?php echo $actionClass; ?>"><?php echo $action; ?></span></td>
                                <td>
                                    <strong><?php echo formatTime($time); ?></strong>
                                    <br><small class="date-display"><?php echo date('M d, Y', strtotime($log['date'] ?? 'now')); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Wait for DOM to be fully loaded
    (function() {
        // Filter button functionality
        const filterBtns = document.querySelectorAll('.filter-btn');
        const tableWrappers = document.querySelectorAll('.table-wrapper');
        const searchInput = document.getElementById('searchInput');

        console.log('Filter buttons found:', filterBtns.length);
        console.log('Table wrappers found:', tableWrappers.length);

        // Add click handlers to filter buttons
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Filter clicked:', this.dataset.filter);

                // Remove active class from all buttons
                filterBtns.forEach(b => b.classList.remove('active'));

                // Add active class to clicked button
                this.classList.add('active');

                // Hide all tables
                tableWrappers.forEach(t => t.classList.remove('active'));

                // Show selected table
                const filter = this.dataset.filter;
                const targetTable = document.getElementById(filter + '-table');

                if (targetTable) {
                    targetTable.classList.add('active');
                    console.log('Showing table:', filter);
                } else {
                    console.error('Table not found:', filter + '-table');
                }

                // Clear search
                if (searchInput) {
                    searchInput.value = '';
                }

                // Reset all rows visibility
                const allRows = document.querySelectorAll('.logs-table tbody tr');
                allRows.forEach(row => row.style.display = '');
            });
        });

        // Search functionality
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const activeTable = document.querySelector('.table-wrapper.active');

                if (!activeTable) return;

                const rows = activeTable.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    })();

    // Refresh function that reloads the current tab with campus parameter
    function refreshLogs() {
        // Get current campus from parent window's selector
        if (window.parent && typeof window.parent.loadContent === 'function') {
            window.parent.loadContent('logs');
        } else {
            location.reload();
        }
    }
</script>