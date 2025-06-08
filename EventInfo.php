<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mypetakom";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$event_id = $_GET['eventid'] ?? '';
if (!$event_id) { echo "Invalid event."; exit(); }
$stmt = $conn->prepare("SELECT * FROM event WHERE EventID = ?");
$stmt->bind_param("s", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$event) { echo "Event not found."; exit(); }

// Get merit application status for this event
$merit_status = null;
$merit_stmt = $conn->prepare("SELECT MeritApplicationStatus FROM meritapplication WHERE EventID = ?");
$merit_stmt->bind_param("s", $event_id);
$merit_stmt->execute();
$merit_result = $merit_stmt->get_result();
if ($row = $merit_result->fetch_assoc()) {
    $merit_status = $row['MeritApplicationStatus'];
}
$merit_stmt->close();

// Determine merit message
$merit_message = "";
$merit_class = "";
if ($merit_status) {
    switch (strtolower($merit_status)) {
        case 'approved':
        case 'accepted':
            $merit_message = "This Event Has Merit";
            $merit_class = "merit-approved";
            break;
        case 'rejected':
            $merit_message = "This Event Not Provide Merit";
            $merit_class = "merit-rejected";
            break;
        case 'pending':
        default:
            $merit_message = "Merit Application is in progress";
            $merit_class = "merit-pending";
            break;
    }
}

// Parse geolocation
$lat = $lng = null;
if (!empty($event['geolocation'])) {
    $coords = explode(',', $event['geolocation']);
    if (count($coords) == 2) {
        $lat = floatval($coords[0]);
        $lng = floatval($coords[1]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Event Info</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Leaflet CSS for map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        body {
            background: #f7fafd;
            font-family: 'Roboto', Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #222;
        }
        .container {
            max-width: 430px;
            margin: 32px auto 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 2px 18px 0 rgba(30,60,90,0.09);
            padding: 24px 18px 22px 18px;
        }
        .event-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 14px;
            color: #1f2d3d;
            text-align: center;
        }
        .event-info-list {
            list-style: none;
            padding: 0;
            margin: 0 0 18px 0;
        }
        .event-info-list li {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 14px;
            border-bottom: 1px solid #f0f2f5;
            padding-bottom: 10px;
        }
        .event-label {
            font-weight: 500;
            color: #34495e;
            font-size: 1.07rem;
            flex: 0 0 50%;
        }
        .event-value {
            color: #222;
            font-size: 1.06rem;
            text-align: right;
        }
        .statusbadge {
            display: inline-block;
            min-width: 90px;
            padding: 4px 14px;
            border-radius: 4px;
            font-size: 0.97em;
            font-weight: 500;
            text-align: center;
        }
        .approved { 
            background: #d4edda; 
            color: #155724; 
        }
        .pending { 
            background: #fff3cd; 
            color: #856404; 
        }
        .rejected { 
            background: #f8d7da; 
            color: #721c24; 
        }
        .active { 
            background: #cce5ff; 
            color: #004085; 
        }
        .postponed { 
            background: #ffe6a3; 
            color: #8a6d1e; 
        }
        .cancelled { 
            background: #f8d7da; 
            color: #721c24; 
        }
        .completed { 
            background: #e2e3e5; 
            color: #383d41; 
        }

        .merit-status {
            margin: 18px 0 0 0;
            padding: 13px;
            font-size: 1.13rem;
            border-radius: 7px;
            text-align: center;
            font-weight: 600;
        }
        .merit-approved {
            background: #e1fbe1;
            color: #1e7d22;
            border: 1px solid #baf2c7;
        }
        .merit-rejected {
            background: #ffe1e1;
            color: #b53c3c;
            border: 1px solid #ffc0c0;
        }
        .merit-pending {
            background: #fff9d8;
            color: #b2932a;
            border: 1px solid #f6e6a9;
        }

        #map {
            width: 100%;
            height: 240px;
            border-radius: 10px;
            margin: 18px 0 0 0;
            background: #e6e9ef;
            box-shadow: 0 2px 8px #eef3f8;
        }
        @media (max-width: 600px) {
            .container {
                margin: 10px 4px 0 4px;
                padding: 13px 4vw 18px 4vw;
                border-radius: 9px;
            }
            .event-title { font-size: 1.22rem; }
            .event-label, .event-value { font-size: 0.99rem; }
            #map { height: 170px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="event-title"><?= htmlspecialchars($event['EventTitle']); ?></div>
        <ul class="event-info-list">
            <li>
                <span class="event-label">Event Name</span>
                <span class="event-value"><?= htmlspecialchars($event['EventTitle']); ?></span>
            </li>
            <li>
                <span class="event-label">Event Venue</span>
                <span class="event-value"><?= htmlspecialchars($event['EventVenue']); ?></span>
            </li>
            <li>
                <span class="event-label">Event Date &amp; Time</span>
                <span class="event-value">
                    <?= date('j F Y, g:i A', strtotime($event['EventDateandTime'])); ?>
                </span>
            </li>
            <li>
                <span class="event-label">Event Status</span>
                <?php
                    $status = strtolower($event['EventStatus']);
                    $statusClass = "";
                    switch($status) {
                        case 'approved': $statusClass = "approved"; break;
                        case 'pending': $statusClass = "pending"; break;
                        case 'rejected': $statusClass = "rejected"; break;
                        case 'active': $statusClass = "active"; break;
                        case 'postponed': $statusClass = "postponed"; break;
                        case 'cancelled': $statusClass = "cancelled"; break;
                        case 'completed': $statusClass = "completed"; break;
                        default: $statusClass = "pending"; break;
                    }
                ?>
                <span class="event-value statusbadge <?= $statusClass ?>">
                    <?= htmlspecialchars($event['EventStatus']); ?>
                </span>
            </li>
        </ul>
        <?php if ($merit_message): ?>
            <div class="merit-status <?= $merit_class ?>">
                <?= $merit_message ?>
            </div>
        <?php endif; ?>
        <?php if ($lat && $lng): ?>
            <div id="map"></div>
        <?php endif; ?>
    </div>

    <?php if ($lat && $lng): ?>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var lat = <?= json_encode($lat) ?>;
            var lng = <?= json_encode($lng) ?>;
            var map = L.map('map').setView([lat, lng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
            L.marker([lat, lng]).addTo(map)
                .bindPopup('<?= htmlspecialchars(addslashes($event['EventTitle'])) ?>')
                .openPopup();
        });
    </script>
    <?php endif; ?>
    
</body>
</html>