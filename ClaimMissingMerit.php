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
    session_destroy();
    header("Location: login.php");
    exit();
}
$student = $result->fetch_assoc();

// Get event_id from URL (student clicked "Claim Merit" for a specific event)
$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : null;

// For update draft: get claim ID from URL or POST
$claim_id = isset($_GET['update_claim_id']) ? $_GET['update_claim_id'] : (isset($_POST['claim_id']) ? $_POST['claim_id'] : null);

// Optionally: Fetch event info for display (title, etc.)
$event_title = "";
if ($event_id) {
    $eventRes = $conn->prepare("SELECT EventTitle FROM event WHERE EventID = ?");
    $eventRes->bind_param("s", $event_id);
    $eventRes->execute();
    $eventRes->bind_result($event_title);
    $eventRes->fetch();
    $eventRes->close();
}

// Fetch draft claim data if updating
$existing_draft = null;
if ($claim_id) {
    $draft_stmt = $conn->prepare("SELECT ClaimID, ProofDocument FROM meritclaim WHERE ClaimID = ? AND StudentID = ? AND MeritClaimStatus = 'Draft'");
    $draft_stmt->bind_param("ss", $claim_id, $student_id);
    $draft_stmt->execute();
    $draft_result = $draft_stmt->get_result();
    if ($draft_result->num_rows > 0) {
        $existing_draft = $draft_result->fetch_assoc();
    }
    $draft_stmt->close();
}

// Handle file upload and merit claim submission/draft/update
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claim_status = isset($_POST['draft']) ? 'Draft' : 'Submitted';
    $date_submitted = date("Y-m-d H:i:s");
    $claim_id_post = isset($_POST['claim_id']) ? $_POST['claim_id'] : null;
    $proof_blob = null;

    // Get staff ID from event (assuming EventID is unique and StaffID is in event table)
    $staff_id = "";
    $event_query = $conn->prepare("SELECT StaffID FROM event WHERE EventID = ?");
    $event_query->bind_param("s", $event_id);
    $event_query->execute();
    $event_query->bind_result($staff_id);
    $event_query->fetch();
    $event_query->close();

    $file_uploaded = isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK;

    if ($file_uploaded) {
        $proof_blob = file_get_contents($_FILES['proof']['tmp_name']);
    }

    if ($claim_id_post) {
        // UPDATE existing draft
        if ($file_uploaded) {
            $stmt = $conn->prepare("UPDATE meritclaim SET ProofDocument = ?, DateSubmitted = ?, MeritClaimStatus = ? WHERE ClaimID = ? AND StudentID = ?");
            $null = NULL;
            $stmt->bind_param("bssss", $null, $date_submitted, $claim_status, $claim_id_post, $student_id);
            $stmt->send_long_data(0, $proof_blob);
        } else {
            // No new file uploaded, only update status and date
            $stmt = $conn->prepare("UPDATE meritclaim SET DateSubmitted = ?, MeritClaimStatus = ? WHERE ClaimID = ? AND StudentID = ?");
            $stmt->bind_param("ssss", $date_submitted, $claim_status, $claim_id_post, $student_id);
        }

        if ($stmt->execute()) {
            $message = "<span style='color:green;'>Your claim has been saved as <b>$claim_status</b>.</span>";
        } else {
            $message = "<span style='color:red;'>Error updating claim: {$stmt->error}</span>";
        }
        $stmt->close();
        // After submit, redirect to avoid resubmission and to remove draft if submitted
        if ($claim_status === 'Submitted') {
            header("Location: MissingMerit.php");
            exit();
        }
    } else {
        // INSERT new claim, ensure unique ClaimID
        do {
            $new_claim_id = 'CLM' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
            $check = $conn->prepare("SELECT 1 FROM meritclaim WHERE ClaimID = ?");
            $check->bind_param("s", $new_claim_id);
            $check->execute();
            $check->store_result();
            $exists = $check->num_rows > 0;
            $check->close();
        } while ($exists);

        if ($file_uploaded) {
            $stmt = $conn->prepare(
                "INSERT INTO meritclaim (ClaimID, StaffID, StudentID, EventID, ProofDocument, DateSubmitted, MeritClaimStatus) 
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $null = NULL;
            $stmt->bind_param("ssssbss", $new_claim_id, $staff_id, $student_id, $event_id, $null, $date_submitted, $claim_status);
            $stmt->send_long_data(4, $proof_blob);

            if ($stmt->execute()) {
                $message = "<span style='color:green;'>Your claim has been saved as <b>$claim_status</b>.</span>";
            } else {
                $message = "<span style='color:red;'>Error saving claim: {$stmt->error}</span>";
            }
            $stmt->close();
            if ($claim_status === 'Submitted') {
                header("Location: MissingMerit.php");
                exit();
            }
        } else {
            $message = "<span style='color:red;'>Please upload a valid official letter.</span>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard - Claim Merit</title>
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
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-top: 20px;
        }
        .msg {
            margin-bottom: 12px;
        }
        .claim-form label {
            font-weight: 500;
        }
        .claim-form input[type="file"] {
            width: 100%;
            padding: 8px 10px;
            margin: 8px 0 18px 0;
            border-radius: 4px;
            border: 1px solid #bbb;
            background: #fafafa;
        }
        .claim-form .btn-group {
            text-align: center;
        }
        .claim-form button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            margin: 0 8px;
        }
        .claim-form .draft-btn { background: #9575cd; }
        .claim-form .draft-btn:hover { background: #7e57c2; }
        .claim-form .submit-btn:hover { background: #388e3c; }
        .footer {
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
                <a href="StudentDashboard.php" class="menuitem ">
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
                <a href="MissingMerit.php" class="menuitem active">
                    <span>Missing Merit</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="maincontent" id="maincontent">
        <div class="content">
            <h1>Claim Merit</h1>
            <?php if($event_id): ?>
                <div style="font-size:1.1em; margin-bottom:10px;">
                    <strong>For Event:</strong> <?= htmlspecialchars($event_title) ?> (<?= htmlspecialchars($event_id) ?>)
                </div>
            <?php endif; ?>
        </div>

        <div class="seccontent">
            <h2><?= $existing_draft ? 'Update Draft Claim' : 'Submit or Draft Merit Claim' ?></h2>
            <div class="msg"><?= $message ?></div>
            <form class="claim-form" action="" method="post" enctype="multipart/form-data">
                <?php if ($claim_id): ?>
                    <input type="hidden" name="claim_id" value="<?= htmlspecialchars($claim_id) ?>">
                <?php endif; ?>
                <label for="proof">Upload Official Letter (PDF/Image):</label>
                <input type="file" name="proof" id="proof" accept=".pdf,.jpg,.jpeg,.png" <?= $existing_draft ? "" : "required" ?>>
                <?php if ($existing_draft): ?>
                    <div style="color:#888; font-size:0.95em;">If you do not upload a new file, the previous one will be kept.</div>
                <?php endif; ?>
                <div class="btn-group">
                    <button type="submit" name="draft" class="draft-btn"><i class="fas fa-save"></i> Draft</button>
                    <button type="submit" name="submit" class="submit-btn"><i class="fas fa-paper-plane"></i> Submit</button>
                </div>
            </form>
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
    </script>

    <div class="footer">
        <footer>
            <center><p>&copy; 2025 MyPetakom</p></center>
        </footer>
    </div>
</body>
</html>