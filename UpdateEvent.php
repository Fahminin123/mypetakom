<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in event advisors
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'event_advisor') {
    header("Location: login.php");
    exit();
}

// Get staff data from database
$staff_id = $_SESSION['user_id'];
$query = "SELECT * FROM staff WHERE StaffID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header("Location: login.php");
    exit();
}
$staff = $result->fetch_assoc();

// Get event ID from URL
$event_id = isset($_GET['id']) ? $_GET['id'] : null;

// Fetch event data
$event_query = "SELECT * FROM event WHERE EventID = ? AND StaffID = ?";
$event_stmt = $conn->prepare($event_query);
$event_stmt->bind_param("ss", $event_id, $staff_id);
$event_stmt->execute();
$event_result = $event_stmt->get_result();

$event = $event_result->fetch_assoc();

// EventStatus options
$event_status_options = ['Pending', 'Active', 'Postponed', 'Cancelled', 'Completed'];
// EventLevel options
$event_level_options = ['International', 'National', 'State', 'District', 'UMPSA'];

// Handle form submission
$update_message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $event_title = $_POST["title"] ?? $event['EventTitle'];
    $event_venue = $_POST["venue"] ?? $event['EventVenue'];
    $event_datetime = $_POST["dateandtime"] ?? $event['EventDateandTime'];
    $event_geolocation = $_POST["geolocation"] ?? $event['geolocation'];
    $event_status = $_POST["event_status"] ?? $event['EventStatus'];
    if (!in_array($event_status, $event_status_options)) $event_status = $event['EventStatus'];
    $event_level = $_POST["event_level"] ?? $event['EventLevel'];
    if (!in_array($event_level, $event_level_options)) $event_level = $event['EventLevel'];

    // Handle file upload if a new approval letter is provided
    $approval_letter = $event['ApprovalLetter'];
    if (isset($_FILES['approval'])) {
        if ($_FILES['approval']['error'] == UPLOAD_ERR_OK) {
            $uploadDir = "uploads/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $filename = basename($_FILES['approval']['name']);
            $targetPath = $uploadDir . time() . "_" . $filename;
            if (move_uploaded_file($_FILES['approval']['tmp_name'], $targetPath)) {
                if ($approval_letter && file_exists($approval_letter)) {
                    unlink($approval_letter);
                }
                $approval_letter = $targetPath;
            }
        } elseif ($_FILES['approval']['error'] != UPLOAD_ERR_NO_FILE) {
            $update_message = "Error uploading approval letter.";
            $message_type = "error";
        }
    }

    if (empty($event_geolocation)) {
        $update_message = "Event geolocation is required. Please select a location on the map.";
        $message_type = "error";
    } else {
        $update_query = "UPDATE event SET 
                        EventTitle = ?, 
                        EventVenue = ?, 
                        EventDateandTime = ?, 
                        ApprovalLetter = ?, 
                        geolocation = ?,
                        EventStatus = ?,
                        EventLevel = ?
                        WHERE EventID = ? AND StaffID = ?";
        
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param(
            "sssssssss",
            $event_title,
            $event_venue,
            $event_datetime,
            $approval_letter,
            $event_geolocation,
            $event_status,
            $event_level,
            $event_id,
            $staff_id
        );

        if ($update_stmt->execute()) {
            $update_message = "Event updated successfully!";
            $message_type = "success";
            $event['EventTitle'] = $event_title;
            $event['EventVenue'] = $event_venue;
            $event['EventDateandTime'] = $event_datetime;
            $event['ApprovalLetter'] = $approval_letter;
            $event['geolocation'] = $event_geolocation;
            $event['EventStatus'] = $event_status;
            $event['EventLevel'] = $event_level;
        } else {
            $update_message = "Error updating event: " . $update_stmt->error;
            $message_type = "error";
        }

        $update_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Event</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
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
            z-index: 1000;
            top: 0;
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
            z-index: 900;
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
            background-color: rgb(16, 8, 54);
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
            margin-top: 130px;
            padding: 40px 20px 40px 20px;
            box-sizing: border-box;
            min-height: 100vh;
        }
        .maincontent.expanded {
            margin-left: 0;
        }
        .content {
            max-width: 750px;
            margin: 0 auto;
            background-color: white;
            padding: 35px 30px 30px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.06);
        }
        .content h1 {
            font-size: 1.7rem;
            margin-bottom: 30px;
            color: #222;
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
            color: #2c3e50;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #1abc9c;
        }
        .map-container {
            margin: 10px 0 0 0;
            border-radius: 8px;
            overflow: hidden;
        }
        #map {
            width: 100%;
            height: 300px;
            border-radius: 8px;
        }
        .geo-coords {
            display: block;
            margin-top: 12px;
            font-size: 0.97rem;
            color: #666;
        }
        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-primary {
            background-color: #1abc9c;
            color: white;
        }
        .btn-primary:hover {
            background-color: #16a085;
        }
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
            font-weight: 500;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            margin-left: 15px;
            padding: 12px 20px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background-color 0.3s;
        }
        .back-link:hover {
            background-color: #5a6268;
        }
        .file-info {
            margin-top: 8px;
            font-size: 13px;
            color: #666;
        }
        .current-file {
            margin-top: 10px;
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            font-size: 14px;
        }
        .current-file a {
            color: #1abc9c;
            text-decoration: none;
        }
        .current-file a:hover {
            text-decoration: underline;
        }
        .footer {
            background-color: #1f2d3d;
            color: white;
            text-align: center;
            padding: 15px 0;
            margin-top: auto;
        }
        
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <button class="togglebutton" id="togglebutton">
                <i class="fas fa-bars"></i> Menu
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
            <li>
                <a href="EventAdvisorDashboard.php" class="menuitem">
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="EventRegistration.php" class="menuitem">
                    <span>Event Registration</span>
                </a>
            </li>
            <li>
                <a href="EventInformation.php" class="menuitem">
                    <span>Event Information</span>
                </a>
            </li>
            <li>
                <a href="Event.php" class="menuitem active">
                    <span>Event</span>
                </a>
            </li>
            <li>
                <a href="MeritClaim.php" class="menuitem">
                    <span>Merit Claim</span>
                </a>
            </li>
            <li>
                <a href="AttendanceSlot.php" class="menuitem">
                    <span>Event attendance Slot</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="maincontent" id="maincontent">
        <div class="content">
            <h1>Update Event Information</h1>
            <?php if ($update_message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $update_message; ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $event_id; ?>" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Event Title</label>
                    <input type="text" class="form-control" id="title" name="title" 
                        value="<?php echo htmlspecialchars($event['EventTitle']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="venue">Event Venue</label>
                    <input type="text" class="form-control" id="venue" name="venue" 
                           value="<?php echo htmlspecialchars($event['EventVenue']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="event_level">Event Level</label>
                    <select class="form-control" id="event_level" name="event_level" required>
                        <option value="">-- Select Level --</option>
                        <?php foreach ($event_level_options as $level): ?>
                            <option value="<?= htmlspecialchars($level) ?>" <?= ($event['EventLevel'] === $level) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($level) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="geolocation">Event Geolocation <span style="color:red">*</span></label>
                    <div class="map-container">
                        <div id="map"></div>
                    </div>
                    <span class="geo-coords" id="geoCoords">
                        <?php
                        if (!empty($event['geolocation'])) {
                            $coords = explode(",", $event['geolocation']);
                            if (count($coords) == 2) {
                                echo "Lat: " . number_format((float)$coords[0], 6) . ", Lon: " . number_format((float)$coords[1], 6);
                            }
                        }
                        ?>
                    </span>
                    <input type="hidden" id="geolocation" name="geolocation" value="<?php echo htmlspecialchars($event['geolocation']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="dateandtime">Event Date and Time</label>
                    <input type="datetime-local" class="form-control" id="dateandtime" name="dateandtime" 
                           value="<?php echo date('Y-m-d\TH:i', strtotime($event['EventDateandTime'])); ?>" required>
                </div>

                <div class="form-group">
                    <label for="event_status">Event Status</label>
                    <select class="form-control" id="event_status" name="event_status" required>
                        <?php foreach ($event_status_options as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>" <?= ($event['EventStatus'] === $status) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="approval">Approval Letter (PDF only)</label>
                    <input type="file" class="form-control" id="approval" name="approval" accept=".pdf">
                    <div class="file-info">Leave blank to keep current file</div>
                    <?php if ($event['ApprovalLetter']): ?>
                        <div class="current-file">
                            Current file: 
                            <a href="<?php echo $event['ApprovalLetter']; ?>" target="_blank">
                                View Approval Letter
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary">Update Event</button>
                <a href="Event.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Event
                </a>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButton = document.getElementById('togglebutton');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('maincontent');
            toggleButton.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });

            var defaultLat = 3.5438;
            var defaultLng = 103.4281;
            var map = L.map('map').setView([defaultLat, defaultLng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            var marker;
            var initialGeolocation = document.getElementById('geolocation').value;
            if (initialGeolocation && initialGeolocation.split(',').length === 2) {
                var coords = initialGeolocation.split(',');
                var lat = parseFloat(coords[0]);
                var lng = parseFloat(coords[1]);
                marker = L.marker([lat, lng]).addTo(map);
                map.setView([lat, lng], 16);
            }

            function setCoords(lat, lng) {
                document.getElementById('geolocation').value = lat + ',' + lng;
                document.getElementById('geoCoords').textContent = "Lat: " + lat.toFixed(6) + ", Lon: " + lng.toFixed(6);
            }

            map.on('click', function(e) {
                var lat = e.latlng.lat;
                var lng = e.latlng.lng;
                if (marker) {
                    marker.setLatLng(e.latlng);
                } else {
                    marker = L.marker(e.latlng).addTo(map);
                }
                setCoords(lat, lng);
            });

            if (!marker && navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    var lat = position.coords.latitude;
                    var lng = position.coords.longitude;
                    map.setView([lat, lng], 16);
                });
            }

            document.querySelector('form').addEventListener('submit', function(e) {
                var geolocation = document.getElementById('geolocation').value;
                if (!geolocation) {
                    alert('Please select event geolocation on the map.');
                    e.preventDefault();
                }
            });
        });
    </script>

    <div class="footer">
        <footer>
            <p>&copy; 2025 MyPetakom</p>
        </footer>
    </div>
</body>
</html>