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
include("../assets/connectDB.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $idres = $_POST['idres'];
    $depart = $_POST['depart'];
    $arrive = $_POST['arrive'];
    $Date_debut = $_POST['Date_debut'];
    $Date_fin = $_POST['Date_fin'];
    $heureDebut = $_POST['heureDebut'];
    $heureFin = $_POST['heureFin'];

    try {
        $sql = "UPDATE reservation 
                SET depart = :depart, arrive = :arrive, Date_debut = :Date_debut, Date_fin = :Date_fin, heureDebut = :heureDebut, heureFin = :heureFin 
                WHERE idres = :idres";
        $stmt = $mysqlconnection->prepare($sql);
        $stmt->execute([
            ':depart' => $depart,
            ':arrive' => $arrive,
            ':Date_debut' => $Date_debut,
            ':Date_fin' => $Date_fin,
            ':heureDebut' => $heureDebut,
            ':heureFin' => $heureFin,
            ':idres' => $idres
        ]);

        header("Location: index.php?status=updated");
        exit();
    } catch (PDOException $e) {
        echo "Erreur lors de la mise à jour : " . $e->getMessage();
    }
}
?>