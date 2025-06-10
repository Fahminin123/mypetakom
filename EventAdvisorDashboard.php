<?php
session_start();
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Restrict access
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'event_advisor') {
    header("Location: login.php");
    exit();
}
$staff_id = $_SESSION['user_id'];

// 1. Events by Status (Pie Chart)
$eventStatusSql = "SELECT EventStatus, COUNT(*) as total FROM event WHERE StaffID = ? GROUP BY EventStatus";
$stmt = $conn->prepare($eventStatusSql); $stmt->bind_param("s", $staff_id); $stmt->execute(); $eventStatusRes = $stmt->get_result();
$eventStatusData = [];
while ($row = $eventStatusRes->fetch_assoc()) $eventStatusData[$row['EventStatus']] = $row['total'];

// 2. MeritApplication Status (Approved/Rejected only) - for chart
$meritStatusSql = "SELECT ma.MeritApplicationStatus, COUNT(*) as total 
                  FROM meritapplication ma
                  JOIN event e ON ma.EventID = e.EventID
                  WHERE e.StaffID=? 
                  AND (ma.MeritApplicationStatus='Approved' OR ma.MeritApplicationStatus='Rejected') 
                  GROUP BY ma.MeritApplicationStatus";
$stmt = $conn->prepare($meritStatusSql); 
$stmt->bind_param("s", $staff_id); 
$stmt->execute(); 
$meritStatusRes = $stmt->get_result();
$meritStatusData = ["Approved" => 0, "Rejected" => 0];
while ($row = $meritStatusRes->fetch_assoc()) {
    $status = ucfirst(strtolower($row['MeritApplicationStatus']));
    if(isset($meritStatusData[$status])) {
        $meritStatusData[$status] += $row['total'];
    }
}

// 3. All Event Dates for Calendar
$eventDatesSql = "SELECT EventTitle, EventDateandTime FROM event WHERE StaffID=?";
$stmt = $conn->prepare($eventDatesSql); $stmt->bind_param("s", $staff_id); $stmt->execute(); $eventDatesRes = $stmt->get_result();
$calendarEvents = [];
while ($row = $eventDatesRes->fetch_assoc()) {
    $calendarEvents[] = [
        'title' => $row['EventTitle'],
        'date' => substr($row['EventDateandTime'], 0, 10)
    ];
}

// 4.Count
//Total Events
$stmt = $conn->prepare("SELECT COUNT(*) FROM event WHERE StaffID=?");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$stmt->bind_result($totalEvents);
$stmt->fetch();
$stmt->close();

//Total Merit Applications
$stmt = $conn->prepare("SELECT COUNT(*) FROM meritapplication ma JOIN event e ON ma.EventID = e.EventID WHERE e.StaffID=?");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$stmt->bind_result($totalMeritApplications);
$stmt->fetch();
$stmt->close();

//Approved Merit Applications
$stmt = $conn->prepare("SELECT COUNT(*) FROM meritapplication ma JOIN event e ON ma.EventID = e.EventID WHERE e.StaffID=? AND ma.MeritApplicationStatus='Approved'");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$stmt->bind_result($approvedMerit);
$stmt->fetch();
$stmt->close();

//Rejected Merit Applications
$stmt = $conn->prepare("SELECT COUNT(*) FROM meritapplication ma JOIN event e ON ma.EventID = e.EventID WHERE e.StaffID=? AND ma.MeritApplicationStatus='Rejected'");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$stmt->bind_result($rejectedMerit);
$stmt->fetch();
$stmt->close();

//5.Search
$searchResults = [];
$searchFields = ["EventID", "EventTitle", "EventVenue", "EventDateandTime", "EventStatus", "MeritApplicationStatus"];
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['search'])) {
    $searchType = $_GET['search_type'] ?? '';
    $searchValue = $_GET['search_value'] ?? '';
    if ($searchType && $searchValue) {
        $q = '';
        if ($searchType === 'event_title') {
            $q = "SELECT e.EventID, e.EventTitle, e.EventVenue, e.EventDateandTime, e.EventStatus,
                        IFNULL(ma.MeritApplicationStatus, '-') AS MeritApplicationStatus
                  FROM event e
                  LEFT JOIN meritapplication ma ON e.EventID = ma.EventID
                  WHERE e.StaffID = ? AND e.EventTitle LIKE ?";
            $val = "%$searchValue%";
            $stmt = $conn->prepare($q); $stmt->bind_param("ss", $staff_id, $val);
        } elseif ($searchType === 'event_status') {
            $q = "SELECT e.EventID, e.EventTitle, e.EventVenue, e.EventDateandTime, e.EventStatus,
                        IFNULL(ma.MeritApplicationStatus, '-') AS MeritApplicationStatus
                  FROM event e
                  LEFT JOIN meritapplication ma ON e.EventID = ma.EventID
                  WHERE e.StaffID = ? AND e.EventStatus = ?";
            $stmt = $conn->prepare($q); $stmt->bind_param("ss", $staff_id, $searchValue);
        } elseif ($searchType === 'merit_status') {
            $q = "SELECT e.EventID, e.EventTitle, e.EventVenue, e.EventDateandTime, e.EventStatus,
                        IFNULL(ma.MeritApplicationStatus, '-') AS MeritApplicationStatus
                  FROM event e
                  LEFT JOIN meritapplication ma ON e.EventID = ma.EventID
                  WHERE e.StaffID = ? AND ma.MeritApplicationStatus = ?";
            $stmt = $conn->prepare($q); $stmt->bind_param("ss", $staff_id, $searchValue);
        }
        if (isset($stmt)) { $stmt->execute(); $searchResults = $stmt->get_result(); }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Event Advisor Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .header {
            background-color: #1f2d3d;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            position: fixed;
            width: 100%;
            box-sizing: border-box;
            height: 120px;
            z-index: 10;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 0 35px;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-right: 20px;
        }
        .Logo {
            display: flex;
            gap: 20px;
            align-items: center;
            padding: 0 60px;
        }
        .Logo img {
            height: 90px; 
            width: auto; 
        }
        .sidebar {
            width: 200px;
            background-color: #2c3e50;
            color: white;
            position: fixed;
            top: 120px;
            left: 0;
            bottom: 0;
            padding: 20px 0;
            box-sizing: border-box;
            transition: transform 0.3s ease;
            z-index: 9;
        }
        .sidebar.collapsed { 
            transform: translateX(-200px);
        }
        .sidebartitle { 
            color: white; 
            font-size: 1.4rem; 
            margin-bottom: 20px; 
            padding: 0 20px;
        }
        .menuitems { 
            display: flex; 
            flex-direction: column; 
            gap: 8px; 
            padding: 0; 
            margin: 0; 
            list-style: none;
        }
        .menuitem { 
            background-color: rgba(255, 255, 255, 0.1); 
            border-radius: 6px; 
            padding: 14px 18px; 
            color: white; 
            cursor: pointer; 
            transition: all 0.2s; 
            display: flex; 
            align-items: center; 
            gap: 12px;
        }
        .menuitems a { 
            text-decoration: none; 
            color: inherit;
        }
        .menuitem:hover { 
            background-color: #1a0966;
        }
        .menuitem.active { 
            background-color: #1abc9c; 
            font-weight: 500;
        }
        .togglebutton { 
            background-color:rgb(16, 8, 54); 
            color: white; 
            border: 1px solid rgba(18, 1, 63, 0.3); 
            padding: 8px 12px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 1rem; 
            display: flex; 
            align-items: center; 
            gap: 8px;
        }
        .togglebutton:hover { 
            background-color: rgba(255, 255, 255, 0.1);
        }
        .logoutbutton { 
            background-color: rgba(255, 0, 0, 0.2); 
            color: white; 
            border: 1px solid rgba(255, 0, 0, 0.3); 
            padding: 8px 12px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 1rem; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            text-decoration: none;
        }
        .profilebutton { 
            background-color: rgba(46, 204, 113, 0.2); 
            color: white; 
            border: 1px solid rgba(46, 204, 113, 0.3); 
            padding: 8px 15px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 1rem; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            transition: all 0.2s; 
            text-decoration: none;
        }
        .profilebutton:hover { 
            background-color: rgba(46, 204, 113, 0.3);
        }
        .maincontent {
            margin-left: 240px;
            margin-top: 120px;
            padding: 40px;
            flex: 1;
            box-sizing: border-box;
            transition: margin-left 0.3s ease;
        }
        .maincontent.expanded { margin-left: 0;}
        .content {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        .content h1 {
            font-size: 1.5rem;
            margin: 0;
            color: black;
            font-weight: 600;
        }
        .seccontent {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-top: 8px;
            display: flex;
            flex-direction: column;
            gap: 40px;
        }
        .search-panel {
            background: #f7fafc;
            padding: 20px 25px 18px 25px;
            border-radius: 10px;
            margin-bottom: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            width: 100%;
            max-width: 650px;
            align-self: flex-start;
        }
        .search-panel h2 {
            font-size: 1.13rem;
            margin-bottom: 9px;
            font-weight: 500;
            color: #1f2d3d;
        }
        .search-panel form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .search-panel select, .search-panel input[type="text"] {
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            min-width: 160px;
            font-size: 1rem;
        }
        .search-panel button {
            padding: 7px 16px;
            background: #1abc9c;
            border: none;
            color: white;
            border-radius: 4px;
            cursor: pointer;
        }
        .search-panel button:hover { background: #179c81;}
        .search-table {
            margin-top: 14px;
            border-collapse: collapse;
            width: 100%;
            font-size: 0.97rem;
        }
        .search-table th, .search-table td {
            border: 1px solid #e3e3e3;
            padding: 8px;
            text-align: left;
        }
        .search-table th {
            background-color: #f0f0f0;
        }
        .dashboard-cards {
            display: flex;
            gap: 32px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .dashboard-card {
            background: linear-gradient(135deg, #e8f8f5 0%, #fefefe 100%);
            box-shadow: 0 2px 12px rgba(0,0,0,0.09);
            border-radius: 12px;
            flex: 1 1 180px;
            min-width: 180px;
            padding: 22px 13px;
            text-align: center;
            margin-bottom: 5px;
            border: 1.5px solid #e0e0e0;
        }
        .dashboard-card h2 {
            margin: 0;
            font-size: 2.4rem;
            color: #1abc9c;
            letter-spacing: 2px;
        }
        .dashboard-card .label {
            color: #444;
            margin-top: 5px;
            font-size: 1.05rem;
            font-weight: 500;
        }
        .dashboard-graphs {
            display: flex;
            gap: 32px;
            flex-wrap: wrap;
            margin-bottom: 5px;
            width: 100%;
        }
        .dashboard-graph-container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 15px 15px 15px 15px;
            flex: 1 1 320px;
            min-width: 280px;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            max-width: 320px;
            height: 280px;
        }

        .dashboard-graph-container h3 {
            font-size: 1.1rem; 
            margin-bottom: 10px;
            color: #1f2d3d;
            font-weight: 500;
        }

        .dashboard-graph-container canvas {
            max-width: 260px !important;
            max-height: 180px !important;
            margin-top: 10px;
        }
        .calendar-container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 20px 18px 18px 18px;
            flex: 1 1 700px;
            margin-bottom: 7px;
        }
        .calendar-container h3 {
            font-size: 1.1rem;
            margin-bottom: 13px;
            color: #1f2d3d;
            font-weight: 500;
        }
        .footer { background-color: #1f2d3d; color: white; margin-top: 30px;}
        
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <button class="togglebutton" id="togglebutton">
                <i class="fas fa-bars"></i>Menu 
            </button>
            <div class="Logo">    
                <img src="Image/UMPSALogo.png" alt="LogoUMP">
                <img src="Image/PetakomLogo.png" alt="LogoPetakom">
            </div>
        </div>
        <div class="header-right">
            <a href="EventAdvisorProfile.php" class="profilebutton">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>
    <nav class="sidebar" id="sidebar">
        <h2 class="sidebartitle">Event Advisor</h2>
        <ul class="menuitems">
            <li><a href="EventAdvisorDashboard.php" class="menuitem active"><span>Dashboard</span></a></li>
            <li><a href="EventRegistration.php" class="menuitem"><span>Event Registration</span></a></li>
            <li><a href="EventInformation.php" class="menuitem"><span>Event Information</span></a></li>
            <li><a href="Event.php" class="menuitem"><span>Event</span></a></li>
            <li><a href="MeritClaim.php" class="menuitem"><span>Merit Claim</span></a></li>
            <li><a href="AttendanceSlot.php" class="menuitem"><span>Event attendance Slot</span></a></li>
        </ul>
    </nav>
    <div class="maincontent" id="maincontent">
        <div class="content">
            <h1>Welcome to MyPetakom</h1>
        </div>
        <div class="seccontent">


            <div class="search-panel">
                <h2>Search</h2>
                <form method="get" autocomplete="off">
                    <select name="search_type" required onchange="updateSearchInput(this)">
                        <option value="">Select Search Type</option>
                        <option value="event_title">Event Title</option>
                        <option value="event_status">Event Status</option>
                        <option value="merit_status">Merit Application Status</option>
                    </select>
                    <input type="text" name="search_value" id="search_value_box" placeholder="Enter search value..." required>
                    <input type="date" name="search_value_date" id="search_value_date" style="display:none;">
                    <button type="submit" name="search" value="1">Search</button>
                </form>
                <?php if (!empty($searchResults) && $searchResults instanceof mysqli_result && $searchResults->num_rows): ?>
                    <table class="search-table">
                        <tr>
                            <?php foreach($searchResults->fetch_fields() as $field) echo "<th>{$field->name}</th>"; ?>
                        </tr>
                        <?php foreach($searchResults as $r) { echo "<tr>"; foreach($r as $v) echo "<td>$v</td>"; echo "</tr>"; } ?>
                    </table>
                <?php elseif(isset($_GET['search'])): ?>
                    <div style="margin-top:10px;color:#c00;">No results found.</div>
                <?php endif; ?>
            </div>


            <div class="dashboard-cards">
                <div class="dashboard-card"><h2><?= $totalEvents ?></h2><div class="label">Total Events</div></div>
                <div class="dashboard-card"><h2><?= $totalMeritApplications ?></h2><div class="label">Total Merit Applications</div></div>
                <div class="dashboard-card"><h2><?= $approvedMerit ?></h2><div class="label">Approved Merit </div></div>
                <div class="dashboard-card"><h2><?= $rejectedMerit ?></h2><div class="label">Rejected Merit </div></div>
            </div>


            <div class="dashboard-graphs">
                <div class="dashboard-graph-container">
                    <h3>Events by Status</h3>
                    <canvas id="eventStatusChart"></canvas>
                </div>
                <div class="dashboard-graph-container">
                    <h3>Merit Applications (Approved/Rejected)</h3>
                    <canvas id="meritStatusChart"></canvas>
                </div>
            </div>

            <div class="calendar-container">
                <h3>Events Calendar</h3>
                <div id="calendar"></div>
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

            // Events by Status Bar Chart
        new Chart(document.getElementById('eventStatusChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($eventStatusData)) ?>,
                datasets: [{
                    label: 'Events',
                    data: <?= json_encode(array_values($eventStatusData)) ?>,
                    backgroundColor: ['#1abc9c','#f1c40f','#e74c3c','#3498db'],
                    borderWidth: 1,
                    barThickness: 30 
                }]
            },
            options: { 
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' events';
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1  // Whole numbers only
                        }
                    },
                    x: {
                        grid: {
                            display: false  // Cleaner look
                        }
                    }
                }
            }
        });

        // Merit Applications Bar Chart
        new Chart(document.getElementById('meritStatusChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($meritStatusData)) ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?= json_encode(array_values($meritStatusData)) ?>,
                    backgroundColor: ['#1abc9c','#e74c3c'],
                    borderWidth: 1,
                    barThickness: 30  // Thicker bars
                }]
            },
            options: { 
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' applications';
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1  // Whole numbers only
                        }
                    },
                    x: {
                        grid: {
                            display: false  // Cleaner look
                        }
                    }
                }
            }
        });

            
            let calendarEl = document.getElementById('calendar');
            let calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
                events: <?= json_encode($calendarEvents) ?>,
                eventColor: '#1abc9c',
                eventTextColor: '#fff',
                height: 420
            });
            calendar.render();
        });
    </script>
    <div class="footer">
        <footer>
            <center><p>&copy; 2025 MyPetakom</p></center>
        </footer>
    </div>
</body>
</html>