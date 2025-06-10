<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Strictly allow only logged-in PetakomCoordinator admins
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'coordinator') {
    header("Location: Login.php");
    exit();
}

// Get admin data and check position is PetakomCoordinator
$admin_id = $_SESSION['user_id'];
$query = "SELECT * FROM staff WHERE StaffID = ? AND Position = 'PetakomCoordinator'";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Admin with correct role not found
    session_destroy();
    header("Location: Login.php");
    exit();
}

$staff = $result->fetch_assoc();

// Handle profile update (email/phone)
$update_success = false;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_profile'])) {
    $new_email = trim($_POST['email']);
    $new_phone = trim($_POST['phone']);
    $update_stmt = $conn->prepare("UPDATE staff SET StaffEmail = ?, StaffContact = ? WHERE StaffID = ?");
    $update_stmt->bind_param("sis", $new_email, $new_phone, $admin_id);
    if ($update_stmt->execute()) {
        $update_success = true;
        // Refresh staff data after update
        $query = "SELECT * FROM staff WHERE StaffID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $staff = $result->fetch_assoc();
    }
}

// Handle profile picture upload/update/delete
$pic_upload_success = false;
$pic_upload_error = '';
$pic_delete_success = false;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_pic'])) {
    if (isset($_FILES['staff_pic']) && $_FILES['staff_pic']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['staff_pic']['tmp_name'];
        $fileType = mime_content_type($fileTmpPath);

        // Only allow image types
        if (strpos($fileType, 'image/') === 0) {
            $imgData = file_get_contents($fileTmpPath);
            $update_pic_stmt = $conn->prepare("UPDATE staff SET StaffPic = ? WHERE StaffID = ?");
            $update_pic_stmt->bind_param("bs", $imgData, $admin_id);
            $update_pic_stmt->send_long_data(0, $imgData);
            if ($update_pic_stmt->execute()) {
                $pic_upload_success = true;
                // Refresh staff data after update
                $query = "SELECT * FROM staff WHERE StaffID = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $admin_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $staff = $result->fetch_assoc();
            }
        } else {
            $pic_upload_error = "Only image files are allowed.";
        }
    } else {
        $pic_upload_error = "Please select an image file to upload.";
    }
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_pic'])) {
    $delete_pic_stmt = $conn->prepare("UPDATE staff SET StaffPic = NULL WHERE StaffID = ?");
    $delete_pic_stmt->bind_param("s", $admin_id);
    if ($delete_pic_stmt->execute()) {
        $pic_delete_success = true;
        // Refresh staff data after update
        $query = "SELECT * FROM staff WHERE StaffID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $staff = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Profile</title>
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
        background-color: rgb(222, 116, 24); 
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
    .sidebar.collapsed { transform: translateX(-200px); }
    .sidebartitle { color: white; font-size: 1.4rem; margin-bottom: 20px; padding: 0 20px; }
    .menuitems { display: flex; flex-direction: column; gap: 8px; padding: 0; margin: 0; list-style: none; }
    .menuitem { background-color: rgba(255, 255, 255, 0.1); border-radius: 6px; padding: 14px 18px; color: white; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 12px; }
    .menuitems a { text-decoration: none; color: inherit; }
    .menuitem:hover { background-color: #a04000; }
    .menuitem.active { background-color: #e67e22; font-weight: 500; }
    .togglebutton { background-color: #e67e22; color: white; border: 1px solid rgba(230, 126, 34, 0.3); padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
    .togglebutton:hover { background-color: #d35400; }
    .logoutbutton { background-color: rgba(255, 0, 0, 0.2); color: white; border: 1px solid rgba(255, 0, 0, 0.3); padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; text-decoration: none; }
    .profilebutton { background-color: rgba(46, 204, 113, 0.2); color: white; border: 1px solid rgba(46, 204, 113, 0.3); padding: 8px 15px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none; }
    .profilebutton:hover { background-color: rgba(52, 152, 219, 0.3); }
    .maincontent { margin-left: 240px; margin-top: 100px; padding: 40px; flex: 1; box-sizing: border-box; gap: 40px; transition: margin-left 0.3s ease; }
    .maincontent.expanded { margin-left: 0; }
    .content { background-color: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
    .content h1 { font-size: 1.5rem; margin: 0; color: black; font-weight: 600; }
    .seccontent { background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
    .footer { background-color: #e67e22; color: white; padding: 15px 0; }
    .info-row { display: flex; margin-bottom: 8px; align-items: center; }
    .info-label { min-width: 140px; font-weight: bold; }
    .edit-btn, .save-btn, .cancel-btn { margin-left: 20px; padding: 6px 16px; border-radius: 4px; border: none; font-size: 1rem; cursor: pointer; }
    .edit-btn { background-color: #e67e22; color: white; }
    .edit-btn:hover { background-color: #d35400; }
    .save-btn { background-color: #43a047; color: white; }
    .save-btn:hover { background-color: #388e3c; }
    .cancel-btn { background-color: #e53935; color: white; }
    .cancel-btn:hover { background-color: #b71c1c; }
    .add-pic-btn, .edit-pic-btn { margin-top: 10px; padding: 6px 16px; border-radius: 4px; border: none; font-size: 1rem; cursor: pointer; background-color: #e67e22; color: white; }
    .add-pic-btn:hover, .edit-pic-btn:hover { background-color: #d35400; }
    .del-pic-btn { margin-top: 10px; padding: 6px 16px; border-radius: 4px; border: none; font-size: 1rem; cursor: pointer; background-color: #e53935; color: white; }
    .del-pic-btn:hover { background-color: #b71c1c; }
    .pic-upload-form { margin-top: 10px; display: flex; flex-direction: column; align-items: center; }
    .pic-upload-form input[type="file"] { margin: 0 auto; display: block; text-align: center;}
    .pic-upload-buttons {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 24px;
        margin-top: 0;
    }
    .card-top { display: flex; flex-direction: column; align-items: center; margin-bottom: 28px; }
    .thumb { width: 180px; height: 180px; object-fit: cover; border: 2px solid #ccc; border-radius: 8px; background: #e0e0e0; display: block; margin: 0 auto; }
    .hidden { display: none !important; }
    .success-msg { color: #388e3c; margin-bottom: 12px; font-weight: 500; }
    .error-msg { color: #e53935; margin-bottom: 12px; font-weight: 500; }
    .input-edit { font-size: 1rem; padding: 6px 8px; border-radius: 4px; border: 1px solid #ccc; }
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
                <a href="AdminDashboard.php" class="menuitem">
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="ManageUser.php" class="menuitem">
                    <span>Manage User</span>
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
            <h1>Admin Profile</h1>
        </div>
        <div class="seccontent">
            <?php if ($update_success): ?>
                <div class="success-msg">Profile updated successfully!</div>
            <?php endif; ?>
            <?php if ($pic_upload_success): ?>
                <div class="success-msg">Profile picture uploaded successfully!</div>
            <?php endif; ?>
            <?php if ($pic_delete_success): ?>
                <div class="success-msg">Profile picture deleted successfully!</div>
            <?php endif; ?>
            <?php if ($pic_upload_error): ?>
                <div class="error-msg"><?php echo $pic_upload_error; ?></div>
            <?php endif; ?>
            <div class="card-top">
                <?php if (!empty($staff['StaffPic'])): ?>
                    <img class="thumb" src="view_staff_pic.php?id=<?php echo urlencode($staff['StaffID']); ?>" alt="Admin Picture" />
                    <button type="button" class="edit-pic-btn" id="showEditPicFormBtn">Edit Picture</button>
                    <form method="POST" action="" enctype="multipart/form-data" id="editPicForm" class="pic-upload-form hidden">
                        <input type="file" name="staff_pic" accept="image/*" required style="margin-bottom:8px;">
                        <button type="submit" name="upload_pic" class="edit-pic-btn">Upload</button>
                        <div class="pic-upload-buttons">
                            <button type="button" class="cancel-btn" id="cancelEditPicBtn">Cancel</button>
                            <button type="submit" name="delete_pic" class="del-pic-btn" onclick="return confirm('Are you sure you want to delete your profile picture?');">Delete Picture</button>
                        </div>
                    </form>
                <?php else: ?>
                    <img class="thumb" src="image/no-profile.png" alt="No Profile Picture" />
                    <button type="button" class="add-pic-btn" id="showAddPicFormBtn">Add Image</button>
                    <form method="POST" action="" enctype="multipart/form-data" id="addPicForm" class="pic-upload-form hidden">
                        <input type="file" name="staff_pic" accept="image/*" required style="margin-bottom:8px;">
                        <button type="submit" name="upload_pic" class="add-pic-btn">Upload</button>
                        <div class="pic-upload-buttons">
                            <button type="button" class="cancel-btn" id="cancelAddPicBtn">Cancel</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            <form id="profileForm" method="POST" action="">
                <div class="info-row">
                    <div class="info-label">Name:</div>
                    <div><?php echo htmlspecialchars($staff['StaffName']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Staff ID:</div>
                    <div><?php echo htmlspecialchars($staff['StaffID']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Position:</div>
                    <div><?php echo htmlspecialchars($staff['Position']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email:</div>
                    <div id="email-view"><?php echo htmlspecialchars($staff['StaffEmail']); ?></div>
                    <input type="email" id="email-edit" name="email" class="input-edit" value="<?php echo htmlspecialchars($staff['StaffEmail']); ?>" style="display:none;" required>
                </div>
                <div class="info-row">
                    <div class="info-label">Phone Number:</div>
                    <div id="phone-view"><?php echo htmlspecialchars($staff['StaffContact']); ?></div>
                    <input type="text" id="phone-edit" name="phone" class="input-edit" value="<?php echo htmlspecialchars($staff['StaffContact']); ?>" style="display:none;" pattern="[0-9]{10,13}" title="Please enter a valid phone number">
                </div>
                <div class="info-row" id="edit-row">
                    <button type="button" class="edit-btn" id="editBtn">Edit</button>
                    <button type="submit" class="save-btn" id="saveBtn" name="edit_profile" style="display:none;">Save</button>
                    <button type="button" class="cancel-btn" id="cancelBtn" style="display:none;">Cancel</button>
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

            // Edit form show/hide for profile picture
            const showEditPicFormBtn = document.getElementById('showEditPicFormBtn');
            const editPicForm = document.getElementById('editPicForm');
            const cancelEditPicBtn = document.getElementById('cancelEditPicBtn');
            if (showEditPicFormBtn && editPicForm) {
                showEditPicFormBtn.addEventListener('click', function() {
                    editPicForm.classList.remove('hidden');
                    showEditPicFormBtn.classList.add('hidden');
                });
            }
            if (cancelEditPicBtn && editPicForm && showEditPicFormBtn) {
                cancelEditPicBtn.addEventListener('click', function() {
                    editPicForm.classList.add('hidden');
                    showEditPicFormBtn.classList.remove('hidden');
                });
            }

            const showAddPicFormBtn = document.getElementById('showAddPicFormBtn');
            const addPicForm = document.getElementById('addPicForm');
            const cancelAddPicBtn = document.getElementById('cancelAddPicBtn');
            if (showAddPicFormBtn && addPicForm) {
                showAddPicFormBtn.addEventListener('click', function() {
                    addPicForm.classList.remove('hidden');
                    showAddPicFormBtn.classList.add('hidden');
                });
            }
            if (cancelAddPicBtn && addPicForm && showAddPicFormBtn) {
                cancelAddPicBtn.addEventListener('click', function() {
                    addPicForm.classList.add('hidden');
                    showAddPicFormBtn.classList.remove('hidden');
                });
            }

            // Edit functionality for profile info
            const editBtn = document.getElementById('editBtn');
            const saveBtn = document.getElementById('saveBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            const emailView = document.getElementById('email-view');
            const emailEdit = document.getElementById('email-edit');
            const phoneView = document.getElementById('phone-view');
            const phoneEdit = document.getElementById('phone-edit');

            editBtn.addEventListener('click', function() {
                emailView.style.display = 'none';
                phoneView.style.display = 'none';
                emailEdit.style.display = 'inline-block';
                phoneEdit.style.display = 'inline-block';
                saveBtn.style.display = 'inline-block';
                cancelBtn.style.display = 'inline-block';
                editBtn.style.display = 'none';
            });

            cancelBtn.addEventListener('click', function() {
                emailView.style.display = 'inline-block';
                phoneView.style.display = 'inline-block';
                emailEdit.style.display = 'none';
                phoneEdit.style.display = 'none';
                saveBtn.style.display = 'none';
                cancelBtn.style.display = 'none';
                editBtn.style.display = 'inline-block';
                // Reset values
                emailEdit.value = emailView.textContent.trim();
                phoneEdit.value = phoneView.textContent.trim();
            });

            // Optional: Prevent accidental leave if editing
            let formChanged = false;
            emailEdit.addEventListener('input', function() { formChanged = true; });
            phoneEdit.addEventListener('input', function() { formChanged = true; });

            window.addEventListener('beforeunload', function (e) {
                if (formChanged && saveBtn.style.display === 'inline-block') {
                    e.preventDefault();
                    e.returnValue = '';
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