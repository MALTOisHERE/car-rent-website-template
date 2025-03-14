<?php 
session_start();

// Check if the user is logged in and has the admin role (role != 0)
if (!isset($_SESSION['role']) || $_SESSION['role'] == 0) {
    // Redirect to login page or show an error
    header('Location: ../index.php');
    exit();
}
?>

<?php
// Include the database connection file
include("../assets/connectDB.php");

// Check if the reservation ID is provided
if (isset($_POST['idres'])) {
    $idres = $_POST['idres'];

    try {
        // Update the confirm column to 1 for the specified reservation
        $sql = "UPDATE reservation SET confirm = 1 WHERE idres = :idres";
        $stmt = $mysqlconnection->prepare($sql);
        $stmt->execute([':idres' => $idres]);

        // Redirect back to the reservations page
        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        die("Error updating reservation: " . $e->getMessage());
    }
} else {
    die("Invalid request.");
}
?>