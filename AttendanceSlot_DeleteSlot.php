<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "mypetakom");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure POST request and slot ID is set
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['slot_id'])) {
    $slot_id = $_POST['slot_id'];

    // Get the related QrCodeID
    $qr_stmt = $conn->prepare("SELECT QrCodeID FROM AttendanceSlot WHERE SlotID = ?");
    $qr_stmt->bind_param("s", $slot_id);
    $qr_stmt->execute();
    $qr_result = $qr_stmt->get_result();
    $qr_row = $qr_result->fetch_assoc();

    if ($qr_row) {
        $qrcode_id = $qr_row['QrCodeID'];

        // Delete from AttendanceSlot
        $delete_slot = $conn->prepare("DELETE FROM AttendanceSlot WHERE SlotID = ?");
        $delete_slot->bind_param("s", $slot_id);
        
        if ($delete_slot->execute()) {
            // Delete from QrCode
            $delete_qr = $conn->prepare("DELETE FROM QrCode WHERE QrCodeID = ?");
            $delete_qr->bind_param("s", $qrcode_id);
            $delete_qr->execute();

            echo "<script>alert('Slot deleted successfully!'); window.location.href='AttendanceSlot.php';</script>";
        } else {
            echo "<script>alert('Failed to delete slot.'); window.location.href='AttendanceSlot.php';</script>";
        }
    } else {
        echo "<script>alert('Slot not found.'); window.location.href='AttendanceSlot.php';</script>";
    }
} else {
    // Redirect if accessed directly without POST
    header("Location: AttendanceSlot.php");
    exit();
}
?>



