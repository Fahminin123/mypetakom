<?php
// Start a new or resume existing session
session_start();

// Database connection parameters
$conn = new mysqli("localhost", "root", "", "mypetakom", 3306);

// Check if database connection failed
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize error message variable
$error = '';

// Handle login form submission when POST request is received
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'], $_POST['type_user'])) {
    // Sanitize user inputs to prevent SQL injection
    $userType = $conn->real_escape_string($_POST['type_user']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password']; // Password not escaped as it will be hashed/verified

    // Validate that required fields aren't empty
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        // Prepare different SQL queries based on user type
        if ($userType === 'student') {
            // Query for student login
            $query = "SELECT * FROM student WHERE StudentID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $username); // Bind username parameter
        } elseif ($userType === 'event_advisor') {
            // Query for event advisor login
            $query = "SELECT * FROM staff WHERE StaffID = ? AND Position = 'EventAdvisor'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $username);
        } elseif ($userType === 'coordinator') {
            // Query for coordinator login
            $query = "SELECT * FROM staff WHERE StaffID = ? AND Position = 'PetakomCoordinator'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $username);
        } else {
            $error = "Invalid user type selected";
        }

        // If no errors in query preparation, execute it
        if (empty($error)) {
            $stmt->execute(); // Run the prepared statement
            $result = $stmt->get_result(); // Get the result set

            // Check if exactly one user was found
            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc(); // Get user data as associative array
                
                // Get the correct password field based on user type
                $hashedPassword = ($userType === 'student') ? $user['StudentPassword'] : $user['StaffPassword'];
                
                // Verify password (works for both hashed and plaintext passwords)
                if (password_verify($password, $hashedPassword) || $password === $hashedPassword) {
                    // Store user data in session variables
                    $_SESSION['user_id'] = $username;
                    $_SESSION['type_user'] = $userType;
                    $_SESSION['user_data'] = $user;

                    // Redirect to appropriate dashboard based on user type
                    switch ($userType) {
                        case 'student':
                            header("Location: StudentDashboard.php");
                            break;
                        case 'event_advisor':
                            header("Location: EventAdvisorDashboard.php");
                            break;
                        case 'coordinator':
                            header("Location: AdminDashboard.php");
                            break;
                    }
                    exit(); // Terminate script after redirect
                } else {
                    $error = "Incorrect password.";
                }
            } else {
                $error = "User not found or invalid credentials.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MYPetakom Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url("image/Petakom.jpg") no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            width: 320px;
        }
        .login-box h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #005b96;
        }
        .login-box input[type="text"],
        .login-box input[type="password"] {
            width: 100%;
            padding: 12px 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .login-box button {
            width: 100%;
            padding: 12px;
            background: #005b96;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        .login-box button:hover {
            background: #004080;
        }
        .login-box select {
            width: 100%;
            padding: 12px 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Login to MyPetakom</h2>
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="text" name="username" placeholder="Staff ID or Student ID" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="type_user" required>
                <option value="" disabled selected>Select User Type</option>
                <option value="student">Student</option>
                <option value="event_advisor">Event Advisor</option>
                <option value="coordinator">Petakom Coordinator</option>
            </select>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>