<?php
$link = mysqli_connect("localhost", "root", "", "mypetakom");

if (!$link) {
    die("Connection failed: " . mysqli_connect_error());
}

// Accept SlotID from either GET or POST
if (
    ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['SlotID']) && !empty($_POST['SlotID']))
    || (isset($_GET['slot_id']) && !empty($_GET['slot_id']))
) {
    // SlotID from POST (form submit) or GET (QR scan)
    $slotID = $_SERVER["REQUEST_METHOD"] == "POST" ? $_POST['SlotID'] : $_GET['slot_id'];

    // Prepare and execute the query to get event details
    $stmt = $link->prepare("SELECT AttendanceSlot.SlotID, Event.EventTitle, Event.EventDateAndTime, Event.EventVenue
        FROM AttendanceSlot
        JOIN Event ON AttendanceSlot.EventID = Event.EventID
        WHERE AttendanceSlot.SlotID = ?");

    if (!$stmt) {
        die("Prepare failed: " . $link->error);
    }

    $stmt->bind_param("s", $slotID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $eventTitle = $row['EventTitle'];
        $eventDateTimeRaw = $row['EventDateAndTime'];
        $eventDateTimeObj = new DateTime($eventDateTimeRaw);
        $formattedEventDateTime = $eventDateTimeObj->format('d F Y, h:i A');
        $eventVenue = $row['EventVenue'];
    } else {
        die("No event found for this slot ID.");
    }

    $stmt->close();
} else {
    die("Invalid access. Please select a slot from the attendance page.");
}
?>


<!DOCTYPE html>
<html>
<head>
    <title>Attendance Form</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            background-color:rgb(31, 8, 77); 
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            position: fixed;
            width: 100%;
            box-sizing: border-box;
            height: 120px;
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
            background-color: #5e3c99;;
            color: white;
            position: fixed;
            top: 120px;
            left: 0;
            bottom: 0;
            padding: 20px 0;
            box-sizing: border-box;
            transition: transform 0.3s ease;
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
            background-color: #7e57c2;
        }

        .menuitem.active {
            background-color: #9575cd;
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
            margin-top: 100px;
            padding: 40px;
            flex: 1;
            box-sizing: border-box;
            transition: margin-left 0.3s ease;
        }

        .maincontent.expanded {
            margin-left: 0;
        }

        .content {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container{
            margin: 0 15px;
        }

        .form-box{
            width: 100%;
            max-width: 450px;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h2{
            font-size: 34px;
            text-align: center;
            margin-bottom: 10px;
        }

        input{
            width: 100%;
            padding: 12px;
            background: #eee;
            border-radius: 6px;
            border: none;
            outline: none;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .button{
            width: 100%;
            padding: 12px;
            background: #7494ec;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #fff;
            font-weight: 500;
            margin-bottom: 20px;
            transition: 0.5s;
        }

        .button:hover{
            background: #6884d3;
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
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        .footer
        {
            background-color: #3a1d6e; 
            color: white;
        }

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
        <a href="StudentDashboard.php" class="menuitem">
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
        <a href="StudEvent.php" class="menuitem">
                    <span>Event</span>
                </a>
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
            <li>
        <a href="Attendance.php" class="menuitem active">
            <span>Attendance</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="maincontent" id="maincontent">
        <div class="content">
            <div class="content">
            <div class="container">
                <div class="form-box" id="attendance">
                    <form id="attendanceForm" action="Attendance_Submit.php" method="post">
                        <h2>Attendance</h2>
                        <p><strong><?php echo htmlspecialchars($eventTitle); ?></strong></p>
                        <p><strong>Date and Time:</strong> <?php echo htmlspecialchars($formattedEventDateTime); ?></p>
                        <p><strong>Venue:</strong><?php echo htmlspecialchars($eventVenue); ?></p>
                        <label>Student ID:</label>
                        <input type="hidden" id="AttendanceID" name="attendanceID" value="">
                        <input type="hidden" name="SlotID" value="<?= htmlspecialchars($slotID) ?>">
                        <input type="text" name="StudentID" required>
                        <label>Password:</label>
                        <input type="password" name="password" required>
                        <input type="hidden" id="submissionTime" name="submissionTime" value="">
                        <input type="hidden" name="ActualGeoLocation" id="ActualGeoLocation">
                        <?php if (!empty($error)): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <button type="submit" name="submit" class="button">Submit</button>
                    </form>
                </div>
            </div>            
        </div>

    <!-- script for check-in time -->
    <script>
        document.getElementById('attendanceForm').addEventListener('submit', function(event) {

        // Format the date and time as 'YYYY-MM-DD HH:MM:SS'
        const formattedTimestamp = now.getFullYear() + '-' +
            String(now.getMonth() + 1).padStart(2, '0') + '-' +
            String(now.getDate()).padStart(2, '0') + ' ' +
            String(now.getHours()).padStart(2, '0') + ':' +
            String(now.getMinutes()).padStart(2, '0') + ':' +
            String(now.getSeconds()).padStart(2, '0');

        // Set the value of the hidden input field to the formatted timestamp
        document.getElementById('submissionTime').value = formattedTimestamp;
        });
    </script>

    <!-- // script for geolocation -->
    <script>
    let geoReady = false;

    function setGeoValue(lat, lng) {
        document.getElementById("ActualGeoLocation").value = lat + "," + lng;
        geoReady = true;
    }

    function requestLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    setGeoValue(position.coords.latitude, position.coords.longitude);
                },
                function(error) {
                    geoReady = false;
                    alert("You must allow location access to submit attendance. If you denied it, please reload and allow location.");
                }
            );
        } else {
            alert("Geolocation is not supported by this browser.");
        }
    }

    // Request location as soon as page loads
    window.onload = function() {
        requestLocation();
    };

    // Block form submission if location not available
    document.getElementById('attendanceForm').addEventListener('submit', function(event) {
        if (!geoReady) {
            event.preventDefault();
            requestLocation();
            alert("Waiting for location... Please allow location access and try again.");
        }
    });
    </script>

    <!-- // script for submit -->
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
    </script>
            
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
                    toggleBtn.innerHTML = '<i class="fas fa-times"></i> Menu';
                }
            });
        });
    </script>

    <div class="footer">
            <footer>
                <center><p>&copy; 2025 MyPetakom</p></center>
            </footer>
        </div>
</body>
</html>