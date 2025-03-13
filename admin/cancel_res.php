<?php
// Include the database connection file
include("../connectDB.php");

// Check if the reservation ID is provided
if (isset($_POST['idres'])) {
    $idres = $_POST['idres'];

    try {
        // Update the confirm column to NULL (or 0) for the specified reservation
        $sql = "UPDATE reservation SET confirm = NULL WHERE idres = :idres";
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