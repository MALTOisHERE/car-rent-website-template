<?php
// delete_car.php

// Include the database connection
require 'connectDB.php'; // Make sure this file contains your PDO connection code

try {
    // Get the car ID from the query string
    $idcar = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($idcar > 0) {
        // Prepare the SQL statement to delete the car
        $sql = "DELETE FROM car WHERE idcar = :idcar";
        $stmt = $mysqlconnection->prepare($sql);
        $stmt->bindParam(':idcar', $idcar, PDO::PARAM_INT);

        // Execute the statement
        if ($stmt->execute()) {
            // Redirect back to the car list page with a success message
            header("Location: cars.php?message=Car+deleted+successfully");
            exit();
        } else {
            // Handle error
            header("Location: cars.php?message=Error+deleting+car");
            exit();
        }
    } else {
        // Invalid ID
        header("Location: cars.php?message=Invalid+car+ID");
        exit();
    }
} catch (PDOException $e) {
    // Handle PDO exceptions
    die('Database error: ' . $e->getMessage());
}