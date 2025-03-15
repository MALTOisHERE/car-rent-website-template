<?php
session_start();

// Check if the user is logged in and has the admin role (role != 0)
if (!isset($_SESSION['role']) || $_SESSION['role'] == 0) {
    // Redirect to login page or show an error
    header('Location: ../index.php');
    exit();
}

include("../assets/connectDB.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $door = $_POST['door'];
    $bag = $_POST['bag'];
    $seat = $_POST['seat'];
    $price = $_POST['price'];
    $type = $_POST['type'];

    // Gérer l'upload de l'image
    $target_dir = "../img/"; // Dossier cible
    $image_tmp_name = time() . "_" . basename($_FILES["car_image"]["name"]); // Génère un nom unique
    $target_file = $target_dir . $image_tmp_name; // Chemin complet
    move_uploaded_file($_FILES["car_image"]["tmp_name"], $target_file);

    // Insérer uniquement le nom de l'image dans la base de données
    try {
        $sql = "INSERT INTO car (name, door, bag, seat, price, type, image) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $mysqlconnection->prepare($sql);
        $stmt->execute([$name, $door, $bag, $seat, $price, $type, $image_tmp_name]); // Stocke seulement le nom de fichier
        header("Location: cars.php"); // Redirection après ajout
        exit();
    } catch (PDOException $e) {
        die("Erreur lors de l'ajout de la voiture : " . $e->getMessage());
    }
}
?>
