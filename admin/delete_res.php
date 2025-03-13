<?php
// Include the database connection file
include("connectDB.php");

// Check if the reservation ID is provided and is a valid integer
if (isset($_POST['idres']) && ctype_digit($_POST['idres'])) {
    $idres = (int)$_POST['idres']; // Ensure the ID is an integer

    try {
        // Delete the reservation with the specified ID
        $sql = "DELETE FROM reservation WHERE idres = :idres";
        $stmt = $mysqlconnection->prepare($sql);
        $stmt->execute([':idres' => $idres]);

        // Check if any rows were affected
        if ($stmt->rowCount() > 0) {
            // Redirect back to the reservations page with a success message
            header("Location: index.php?status=success");
            exit();
        } else {
            // No rows were deleted (reservation ID might not exist)
            header("Location: index.php?status=not_found");
            exit();
        }
    } catch (PDOException $e) {
        // Log the error and display a user-friendly message
        error_log("Error deleting reservation: " . $e->getMessage());
        die("An error occurred while processing your request. Please try again later.");
    }
} else {
    // Invalid or missing reservation ID
    die("Invalid request. Reservation ID is missing or invalid.");
}
?>