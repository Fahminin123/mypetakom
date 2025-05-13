<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in admins
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'coordinator') {
    header("Location: Login.php");
    exit();
}

// Get admin data from database
$admin_id = $_SESSION['user_id'];
$query = "SELECT * FROM staff WHERE StaffID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $admin_id); // Fix applied here
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Admin not found in database
    session_destroy();
    header("Location: Login.php");
    exit();
}

$staff = $result->fetch_assoc();
?>


<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
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
            background-color:rgb(222, 116, 24); 
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
            background-color: #d35400; 
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
            background-color: #a04000; /* Even darker orange */
        }

        .menuitem.active {
            background-color: #e67e22; /* Matching header orange */
            font-weight: 500;
        }

        .togglebutton {
            background-color: #e67e22; /* Orange */
            color: white;
            border: 1px solid rgba(230, 126, 34, 0.3);
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .togglebutton:hover {
            background-color: #d35400; /* Darker orange */
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
            background-color: rgba(52, 152, 219, 0.3);
        }
        
        .maincontent {
            margin-left: 200px;
            margin-top: 120px;
            padding: 40px;
            flex: 1;
            box-sizing: border-box;
            gap: 40px;
            transition: margin-left 0.3s ease;
            justify-content: space-between;
        }

        .maincontent.expanded {
            margin-left: 0;
        }

        .content {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .seccontent {
            background-color: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            min-height: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .thirdcontent {
            background-color: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            min-height: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .footer {
            background-color: #e67e22; /* Orange */
            color: white;
            padding: 15px 0;
        }
        table, th, td {
         border:1px solid black;
        }
        .edit-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .edit-btn:hover {
            background-color: #2980b9;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-edit {
            background-color: #3498db;
            color: white;
        }
        .btn-edit:hover {
            background-color: #2980b9;
        }
        .btn-view {
            background-color: #2ecc71;
            color: white;
        }
        .btn-view:hover {
            background-color: #27ae60;
        }
        .btn-delete {
            background-color: #e74c3c;
            color: white;
        }
        .btn-delete:hover {
            background-color: #c0392b;
        }
        .btn i {
            margin-right: 5px;
            font-size: 12px;
        }

        .button {
            border: none;
            color: white;
            padding: 15px 32px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 4px 2px;
            cursor: pointer;
        }

        .button2 {background-color: #008CBA;} /* Blue */
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
        <a href="AdminProfile.php" class="profilebutton">
    <i class="fas fa-user-circle"></i> My Profile
</a>
            <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
  <i class="fas fa-sign-out-alt"></i> Logout
</a>
        </div>
    </header>

    <nav class="sidebar" id="sidebar">
        <h2 class="sidebartitle">Admin</h2>
        <ul class="menuitems">
        <li>
            <a href="AdminDashboard.php" class="menuitem active">
                    <span>Dashboard</span>
                </a>
                
            </li>
          
            
            <li>
            <a href="MeritApplicationApproval.php" class="menuitem">
                <span>Merit Application Approval</span>
    </a>
            </li>
            
            <li>
            <a href="MembershipApproval.php" class="menuitem">
                <span>Membership Approval</span>
    </a>
            </li>
        </ul>
    </nav>

    <div class="maincontent" id="maincontent">
        <div class="content">
            <h1>Welcome to MyPetakom</h1>
            
        </div>

        <div class="seccontent">
<table style="width:100%">
  <tr>
    <th>Student ID</th>
    <th>Student Name</th>
    <th>Student Contact</th>
    <th>Student Email</th>
    <th>Action</th>
  </tr>
  
  <tr>
    <td>P0001</td>
    <td>Ahmad Shahridzuan</td>
    <td>P0001@adab.umpsa.edu.my</td>
    <td>0146157720</td>
    <td> <div class="action-buttons">
                            <button class="btn btn-edit"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn btn-view"><i class="fas fa-eye"></i> View</button>
                            <button class="btn btn-delete"><i class="fas fa-trash-alt"></i> Delete</button>
                        </div></td>
  </tr>

  <tr>
    <td>P0002</td>
    <td>Nur Syafawati</td>
    <td>P0002@adab.umpsa.edu.my</td>
    <td>0142713321</td>
    <td><div class="action-buttons">
                            <button class="btn btn-edit"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn btn-view"><i class="fas fa-eye"></i> View</button>
                            <button class="btn btn-delete"><i class="fas fa-trash-alt"></i> Delete</button>
                        </div></td>
  </tr>

   <tr>
    <td>P0003</td>
    <td>Amirul Aiman</td>
    <td>P0003@adab.umpsa.edu.my</td>
    <td>01125627776</td>
    <td><div class="action-buttons">
                            <button class="btn btn-edit"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn btn-view"><i class="fas fa-eye"></i> View</button>
                            <button class="btn btn-delete"><i class="fas fa-trash-alt"></i> Delete</button>
                        </div></td>
  </tr>
</table>
        </div>
          <div class="thirdcontent">
            <button class="button button2">Add New Student</button>
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