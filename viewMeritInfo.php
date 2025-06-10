<?php
// viewMeritInfo.php
// Display student basic info (photo, name, matric, phone) and total merit
// Combined logic: committee roles (eventcommittee table) + approved participant claims (meritclaim table)

if (!isset($_GET['student_id'])) {
    echo "Student ID not provided.";
    exit();
}

$student_id = $_GET['student_id'];

// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get student data
$query = "SELECT * FROM student WHERE StudentID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo "Student not found.";
    exit();
}
$student = $result->fetch_assoc();

// --- 1. Committee logic (eventcommittee) ---
$committee_points = 0;
$committee_event_ids = [];

$committee_sql = "SELECT ec.EventID, ec.CR_ID, e.EventLevel
    FROM eventcommittee ec
    JOIN event e ON ec.EventID = e.EventID
    WHERE ec.StudentID = ?";
$stmt = $conn->prepare($committee_sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$committee_result = $stmt->get_result();

$event_level_points = [
    "International" => ["Main" => 100, "Committee" => 70],
    "National"      => ["Main" => 80,  "Committee" => 50],
    "State"         => ["Main" => 60,  "Committee" => 40],
    "District"      => ["Main" => 40,  "Committee" => 30],
    "UMPSA"         => ["Main" => 30,  "Committee" => 20],
];

while ($row = $committee_result->fetch_assoc()) {
    $event_id = $row['EventID'];
    $cr_id = $row['CR_ID'];
    $event_level = $row['EventLevel'];
    $committee_event_ids[] = $event_id;

    $is_main_committee = ($cr_id === "CR01" || $cr_id === "CR02");
    $is_committee = ($cr_id === "CR03" || $cr_id === "CR04");

    if (isset($event_level_points[$event_level])) {
        if ($is_main_committee) {
            $committee_points += $event_level_points[$event_level]["Main"];
        } elseif ($is_committee) {
            $committee_points += $event_level_points[$event_level]["Committee"];
        }
    }
}

// --- 2. Participant logic (approved claims in meritclaim, excluding committee events) ---
$participant_points = 0;

// Get approved participant claims not in committee events
if (count($committee_event_ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($committee_event_ids), '?'));
    $params = array_merge([$student_id], $committee_event_ids);
    $types = str_repeat('s', count($params));
    $claim_sql = "SELECT EventID FROM meritclaim WHERE StudentID = ? AND MeritClaimStatus = 'Approved' AND EventID NOT IN ($placeholders)";
    $stmt = $conn->prepare($claim_sql);
    $stmt->bind_param($types, ...$params);
} else {
    $claim_sql = "SELECT EventID FROM meritclaim WHERE StudentID = ? AND MeritClaimStatus = 'Approved'";
    $stmt = $conn->prepare($claim_sql);
    $stmt->bind_param("s", $student_id);
}
$stmt->execute();
$claim_res = $stmt->get_result();

while ($claim_row = $claim_res->fetch_assoc()) {
    $event_id = $claim_row['EventID'];

    // Get EventLevel for this event
    $event_sql = "SELECT EventLevel FROM event WHERE EventID = ?";
    $event_stmt = $conn->prepare($event_sql);
    $event_stmt->bind_param("s", $event_id);
    $event_stmt->execute();
    $event_result = $event_stmt->get_result();
    if ($event_row = $event_result->fetch_assoc()) {
        $event_level = $event_row['EventLevel'];

        // Get MeritScore for this EventLevel
        $merit_sql = "SELECT MeritScore FROM merit WHERE MeritDescription = ?";
        $merit_stmt = $conn->prepare($merit_sql);
        $merit_stmt->bind_param("s", $event_level);
        $merit_stmt->execute();
        $merit_result = $merit_stmt->get_result();
        if ($merit_row = $merit_result->fetch_assoc()) {
            $participant_points += $merit_row['MeritScore'];
        }
        $merit_stmt->close();
    }
    $event_stmt->close();
}

$total_merit = $committee_points + $participant_points;

// Set student picture path; change this if your folder structure is different
$student_pic = !empty($student['StudentPic']) ? $student['StudentPic'] : 'default_profile.png'; // fallback to default if not set
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Merit Info</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f7f7fa;
        }
        .container {
            max-width: 420px;
            margin: 24px auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(50,50,93,0.09);
            padding: 1.5em 1.2em;
        }
        .title {
            font-size: 1.25em;
            font-weight: 500;
            color: #3a1d6e;
            margin-bottom: 1.3em;
            text-align: center;
        }
        .student-pic {
            display: block;
            margin: 0 auto 1.2em auto;
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #5e3c99;
            background: #f3eeff;
        }
        .student-info {
            margin-bottom: 2.2em;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.1em;
            font-size: 1em;
        }
        .info-label {
            color: #6c6f7b;
            font-weight: 500;
        }
        .info-value {
            color: #23233e;
        }
        .merit-box {
            background: #5e3c99;
            color: #fff;
            border-radius: 10px;
            padding: 1.2em;
            text-align: center;
            margin-bottom: 2em;
        }
        .merit-box .merit-title {
            font-size: 1em;
            margin-bottom: 0.6em;
        }
        .merit-box .merit-value {
            font-size: 2.4em;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .merit-breakdown {
            font-size:0.99em;
            margin-top:1.2em;
            line-height:1.7;
        }
        @media (max-width: 600px) {
            .container {
                padding: 1em 0.3em;
                border-radius: 0;
                box-shadow: none;
            }
            .info-row {
                font-size: 0.92em;
                margin-bottom: 0.7em;
            }
            .student-pic {
                width: 80px;
                height: 80px;
                margin-bottom: 0.8em;
            }
            .merit-box {
                padding: 1em;
                margin-bottom: 1.2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="title">Student Merit Information</div>
        <img src="<?= htmlspecialchars($student_pic) ?>" alt="Student Picture" class="student-pic">
        <div class="student-info">
            <div class="info-row">
                <span class="info-label">Name:</span>
                <span class="info-value"><?= htmlspecialchars($student['StudentName']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Matric No:</span>
                <span class="info-value"><?= htmlspecialchars($student['StudentID']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Phone:</span>
                <span class="info-value"><?= htmlspecialchars($student['StudentContact']) ?></span>
            </div>
        </div>
        <div class="merit-box">
            <div class="merit-title">Total Cumulative Merit Points</div>
            <div class="merit-value"><?= $total_merit ?></div>
            <div class="merit-breakdown">
                <span style="color:#d1c2f7;">Committee Points:</span> <?= $committee_points ?><br>
                <span style="color:#c2e0f7;">Participant Points:</span> <?= $participant_points ?>
            </div>
        </div>
        <div style="text-align:center; font-size:0.92em; color:#888; margin-top:1.5em;">
            &copy; <?= date('Y') ?> MyPetakom
        </div>
    </div>
</body>
</html>