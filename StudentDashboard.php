<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in students
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Get student data from database
$student_id = $_SESSION['user_id'];
$query = "SELECT * FROM student WHERE StudentID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Student not found in database
    session_destroy();
    header("Location: login.php");
    exit();
}
$student = $result->fetch_assoc();

// --- SEARCH FUNCTIONALITY ---
$search_result = [];
if (isset($_POST['search_merit'])) {
    $search_type = $_POST['search_type'];
    $search_value = "%" . $_POST['search_merit'] . "%";
    $base_sql = "SELECT * FROM meritclaim WHERE StudentID = ?";
    if ($search_type == "ClaimID") {
        $sql = $base_sql . " AND ClaimID LIKE ?";
    } elseif ($search_type == "EventID") {
        $sql = $base_sql . " AND EventID LIKE ?";
    } elseif ($search_type == "Status") {
        $sql = $base_sql . " AND MeritClaimStatus LIKE ?";
    } else {
        $sql = $base_sql . " AND (ClaimID LIKE ? OR EventID LIKE ? OR MeritClaimStatus LIKE ?)";
    }
    $stmt = $conn->prepare($sql);
    if ($search_type == "All") {
        $stmt->bind_param("ssss", $student_id, $search_value, $search_value, $search_value);
    } else {
        $stmt->bind_param("ss", $student_id, $search_value);
    }
    $stmt->execute();
    $search_result = $stmt->get_result();
}

// --- DATA REPRESENTATION SUMMARY ---

// 1. Get all attended EventIDs (via attendanceslot)
$attended_event_ids = [];
$attend_sql = "SELECT DISTINCT s.EventID FROM attendance a
               JOIN attendanceslot s ON a.SlotID = s.SlotID
               WHERE a.StudentID = ?";
$stmt = $conn->prepare($attend_sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$attend_res = $stmt->get_result();
while ($row = $attend_res->fetch_assoc()) {
    $attended_event_ids[] = $row['EventID'];
}

// 2. Get all committee EventIDs from attended events
$committee_event_ids = [];
if (count($attended_event_ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($attended_event_ids), '?'));
    $types = str_repeat('s', count($attended_event_ids)+1);
    $params = array_merge([$student_id], $attended_event_ids);
    $sql = "SELECT DISTINCT EventID FROM eventcommittee WHERE StudentID = ? AND EventID IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $comm_res = $stmt->get_result();
    while ($row = $comm_res->fetch_assoc()) {
        $committee_event_ids[] = $row['EventID'];
    }
}

// 3. Participant = attended events NOT as committee
$participant_event_ids = array_diff($attended_event_ids, $committee_event_ids);

// For the chart
$event_chart_labels = ["Committee", "Participant"];
$event_chart_data = [count($committee_event_ids), count($participant_event_ids)];

// For total events
$total_unique_events = count($attended_event_ids);

// Total merit applications
$total_merit_applications = 0;
$total_merit_sql = "SELECT COUNT(*) as total FROM meritclaim WHERE StudentID = ?";
$stmt = $conn->prepare($total_merit_sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$stmt->bind_result($total_merit_applications);
$stmt->fetch();
$stmt->close();

// Approved merit count
$total_approved_merit = 0;
$approved_merit_sql = "SELECT COUNT(*) as total FROM meritclaim WHERE StudentID = ? AND MeritClaimStatus = 'Approved'";
$stmt = $conn->prepare($approved_merit_sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$stmt->bind_result($total_approved_merit);
$stmt->fetch();
$stmt->close();

// Rejected merit count
$total_rejected_merit = 0;
$rejected_merit_sql = "SELECT COUNT(*) as total FROM meritclaim WHERE StudentID = ? AND MeritClaimStatus = 'Rejected'";
$stmt = $conn->prepare($rejected_merit_sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$stmt->bind_result($total_rejected_merit);
$stmt->fetch();
$stmt->close();

// Event Joined by Month (all attended events)
$event_by_month = [];
if (count($attended_event_ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($attended_event_ids), '?'));
    $types = str_repeat('s', count($attended_event_ids));
    $sql = "SELECT DATE_FORMAT(EventDateAndTime, '%Y-%m') as yymm, COUNT(*) as total
            FROM event
            WHERE EventID IN ($placeholders)
            GROUP BY yymm
            ORDER BY yymm ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$attended_event_ids);
    $stmt->execute();
    $month_res = $stmt->get_result();
    while ($row = $month_res->fetch_assoc()) {
        $event_by_month[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f5f5f5; font-family: 'Roboto', sans-serif; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .header { background-color:rgb(31, 8, 77); display: flex; justify-content: space-between; align-items: center; padding: 0 20px; position: fixed; width: 100%; box-sizing: border-box; height: 120px; z-index: 10; }
        .header-left { display: flex; align-items: center; gap: 20px; padding: 0 35px; }
        .header-right { display: flex; align-items: center; gap: 20px; padding-right: 20px; }
        .Logo { display: flex; gap: 20px; align-items: center; padding: 0 60px; }
        .Logo img { height: 90px; width: auto; }
        .sidebar { width: 200px; background-color: #5e3c99;; color: white; position: fixed; top: 120px; left: 0; bottom: 0; padding: 20px 0; box-sizing: border-box; transition: transform 0.3s ease; z-index: 5; }
        .sidebar.collapsed { transform: translateX(-200px); }
        .sidebartitle { color: white; font-size: 1.4rem; margin-bottom: 20px; padding: 0 20px; }
        .menuitems { display: flex; flex-direction: column; gap: 8px; padding: 0; margin: 0; list-style: none; }
        .menuitem { background-color: rgba(255, 255, 255, 0.1); border-radius: 6px; padding: 14px 18px; color: white; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 12px; }
        .menuitems a { text-decoration: none; color: inherit; }
        .menuitem:hover { background-color: #7e57c2; }
        .menuitem.active { background-color: #9575cd; font-weight: 500; }
        .togglebutton { background-color:rgb(16, 8, 54); color: white; border: 1px solid rgba(18, 1, 63, 0.3); padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
        .togglebutton:hover { background-color: rgba(255, 255, 255, 0.1); }
        .logoutbutton { background-color: rgba(255, 0, 0, 0.2); color: white; border: 1px solid rgba(255, 0, 0, 0.3); padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .profilebutton { background-color: rgba(46, 204, 113, 0.2); color: white; border: 1px solid rgba(46, 204, 113, 0.3); padding: 8px 15px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none; }
        .profilebutton:hover { background-color: rgba(46, 204, 113, 0.3); }
        .maincontent { margin-left: 240px; margin-top: 100px; padding: 40px; flex: 1; box-sizing: border-box; transition: margin-left 0.3s ease; }
        .maincontent.expanded { margin-left: 0; }
        .content { background-color: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .content h1 { font-size: 1.5rem; margin: 0; color: black; font-weight: 600; }
        .seccontent { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); margin-bottom: 40px; }
        .search-container { margin-bottom: 25px; padding: 24px 14px; background: #f3f0fb; border-radius: 10px; box-shadow: 0 1px 7px rgba(50,50,93,0.05); display: flex; align-items: center; gap: 24px; flex-wrap: wrap; }
        .search-container form { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; width: 100%; }
        .search-container select, .search-container input[type="text"] { padding: 10px 12px; border-radius: 6px; border: 1px solid #b5a6da; font-size: 1rem; min-width: 140px; margin-right: 5px; }
        .search-container button { padding: 10px 22px; background: #5e3c99; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; font-weight: 500; transition: background 0.2s; }
        .search-container button:hover { background: #3a1d6e; }
        .search-results-table { width: 100%; margin-top: 15px; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 7px rgba(50,50,93,0.04); }
        .search-results-table th, .search-results-table td { padding: 8px 12px; border-bottom: 1px solid #eee; font-size: 1em; }
        .search-results-table th { background: #ede7f6; color: #5e3c99; }
        .summary-cards { display: flex; flex-wrap: wrap; gap: 24px; margin-bottom: 30px; margin-top: 12px; justify-content: left; }
        .summary-card { flex: 1 1 200px; background: linear-gradient(135deg, #5e3c99 70%, #3a1d6e 100%); border-radius: 10px; padding: 32px 14px 20px 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); display: flex; flex-direction: column; align-items: center; min-width: 180px; min-height: 80px; }
        .summary-card .summary-number { color: #fff; font-size: 2.4em; font-weight: bold; margin-bottom: 6px; text-shadow: 1px 1px 0 #3a1d6e; }
        .summary-card .summary-label { color: #ede7f6; font-size: 1.05em; font-weight: 500; text-align: center; opacity: 0.91; }
        .charts-section { display: flex; flex-wrap: wrap; gap: 28px; }
        .chart-box { flex: 1 1 370px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,.05); padding: 18px 18px 5px 18px; min-width: 340px; margin-bottom: 16px; }
        .chart-box h4 { margin: 0 0 10px 0; color: #3a1d6e; font-size: 1.12rem; font-weight: 500; }
        @media (max-width: 900px) { .summary-cards, .charts-section { flex-direction: column; align-items: stretch;} .summary-card { width: 100%; min-width: unset;} }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <button class="togglebutton" id="togglebutton">
                <i class="fas fa-bars"></i>Menu 
            </button>
            <div class="Logo">    
                <img src="image/UMPSALogo.png" alt="LogoUMP">
                <img src="image/PetakomLogo.png" alt="LogoPetakom">
            </div>
        </div>
        <div class="header-right">
        <a href="StudentProfile.php" class="profilebutton">
            <i class="fas fa-user-circle"></i> My Profile
        </a>
        <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        </div>
    </header>

    <nav class="sidebar" id="sidebar">
        <h2 class="sidebartitle">Student</h2>
        <ul class="menuitems">
        <li>
            <a href="StudentDashboard.php" class="menuitem active">
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="StudEvent.php" class="menuitem">
                <span>Event</span>
            </a>
        </li>
        <li>
            <a href="Membership.php" class="menuitem">
                <span>Membership</span>
            </a>
        </li>
        <li>
            <a href="MeritAwarded.php" class="menuitem">
                <span>Merit Awarded</span>
            </a>
        </li>
        <li>
            <a href="MissingMerit.php" class="menuitem">
                <span>Missing Merit</span>
            </a>
        </li>
        </ul>
    </nav>

    <div class="maincontent" id="maincontent">
        <div class="content">
            <h1>Welcome to MyPetakom</h1>
            <div class="search-container">
                <form method="POST">
                    <select name="search_type">
                        <option value="All" <?= (!isset($_POST['search_type']) || $_POST['search_type']=='All') ? 'selected' : '' ?>>All</option>
                        <option value="ClaimID" <?= (isset($_POST['search_type']) && $_POST['search_type']=='ClaimID') ? 'selected' : '' ?>>Claim ID</option>
                        <option value="EventID" <?= (isset($_POST['search_type']) && $_POST['search_type']=='EventID') ? 'selected' : '' ?>>Event ID</option>
                        <option value="Status" <?= (isset($_POST['search_type']) && $_POST['search_type']=='Status') ? 'selected' : '' ?>>Status</option>
                    </select>
                    <input type="text" name="search_merit" placeholder="Enter search value..." value="<?= isset($_POST['search_merit']) ? htmlspecialchars($_POST['search_merit']) : '' ?>">
                    <button type="submit"><i class="fas fa-search"></i> Search</button>
                </form>
            </div>
            <?php if (isset($_POST['search_merit'])): ?>
            <div style="margin-top:10px;">
                <strong>Search Results:</strong>
                <table class="search-results-table">
                    <tr>
                        <th>ClaimID</th>
                        <th>EventID</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                    <?php while ($row = $search_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['ClaimID']) ?></td>
                        <td><?= htmlspecialchars($row['EventID']) ?></td>
                        <td><?= htmlspecialchars($row['MeritClaimStatus']) ?></td>
                        <td><?= htmlspecialchars($row['DateSubmitted']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="seccontent">
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-number"><?= $total_unique_events ?></div>
                    <div class="summary-label">Total Events</div>
                </div>
                <div class="summary-card">
                    <div class="summary-number"><?= $total_merit_applications ?></div>
                    <div class="summary-label">Total Merit Applications</div>
                </div>
                <div class="summary-card">
                    <div class="summary-number"><?= $total_approved_merit ?></div>
                    <div class="summary-label">Approved Merit</div>
                </div>
                <div class="summary-card">
                    <div class="summary-number"><?= $total_rejected_merit ?></div>
                    <div class="summary-label">Rejected Merit</div>
                </div>
            </div>
            <div class="charts-section">
                <div class="chart-box">
                    <h4>Event Join (Committee/Participant)</h4>
                    <canvas id="eventJoinChart"></canvas>
                </div>
                <div class="chart-box">
                    <h4>Total Event Joined by Month</h4>
                    <canvas id="eventMonthChart"></canvas>
                    <?php if (empty($event_by_month)): ?>
                        <div style="color:#7e57c2;text-align:center;padding-top:80px;">No data available for your events</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButton = document.getElementById('togglebutton');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('maincontent');
            
            toggleButton.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                const icon = toggleButton.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                    toggleButton.innerHTML = '<i class="fas fa-bars"></i> Menu';
                } else {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                    toggleButton.innerHTML = '<i class="fas fa-times"></i> Menu';
                }
            });
        });

        // Event Join (Committee/Participant) Chart
        const eventJoinLabels = <?= json_encode($event_chart_labels) ?>;
        const eventJoinData = <?= json_encode($event_chart_data) ?>;
        if (eventJoinLabels.length && eventJoinData.length && (eventJoinData[0] > 0 || eventJoinData[1] > 0)) {
            const ctxJoin = document.getElementById('eventJoinChart').getContext('2d');
            new Chart(ctxJoin, {
                type: 'pie',
                data: {
                    labels: eventJoinLabels,
                    datasets: [{
                        label: 'Event Join',
                        data: eventJoinData,
                        backgroundColor: [
                            '#5e3c99',
                            '#00a88f'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Event Joined by Month Chart
        const monthLabels = <?= json_encode(array_column($event_by_month, 'yymm')) ?>;
        const monthData = <?= json_encode(array_column($event_by_month, 'total')) ?>;
        if (monthLabels.length && monthData.length) {
            const ctx2 = document.getElementById('eventMonthChart').getContext('2d');
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: 'Events Joined',
                        data: monthData,
                        borderColor: '#3a1d6e',
                        backgroundColor: 'rgba(122, 74, 221, 0.2)',
                        fill: true
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    </script>
    <div class="footer">
        <footer>
            <center><p>&copy; 2025 MyPetakom</p></center>
        </footer>
    </div>
</body>
</html>