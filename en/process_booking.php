<?php
session_start();
include("../assets/connectDB.php");

// Check required parameters
$requiredParams = ['idcar', 'depart', 'Date_debut', 'heureDebut', 'Date_fin', 'heureFin'];
foreach ($requiredParams as $param) {
    if (!isset($_GET[$param])) {
        // Redirect with error message if any required parameter is missing
        header("Location: categories.php?message=Missing+required+parameter+:+" . urlencode($param));
        exit();
    }
}

// Get parameters
$idcar = $_GET['idcar'];
$depart = $_GET['depart'];
$arrive = $_GET['arrive'] ?? '';
$dateDebut = $_GET['Date_debut'];
$heureDebut = $_GET['heureDebut'];
$dateFin = $_GET['Date_fin'];
$heureFin = $_GET['heureFin'];

if (isset($_SESSION['user_id'])) {
    try {
        // Double-check availability
        $sqlCheck = "SELECT COUNT(*) FROM reservation 
                     WHERE idcar = :idcar 
                     AND confirm = 1 
                     AND (
                         STR_TO_DATE(CONCAT(Date_debut, ' ', heureDebut), '%Y-%m-%d %H:%i') < STR_TO_DATE(:dateFinHeureFin, '%Y-%m-%d %H:%i')
                         AND
                         STR_TO_DATE(CONCAT(Date_fin, ' ', heureFin), '%Y-%m-%d %H:%i') > STR_TO_DATE(:dateDebutHeureDebut, '%Y-%m-%d %H:%i')
                     )";
        $stmtCheck = $mysqlconnection->prepare($sqlCheck);
        $stmtCheck->execute([
            ':idcar' => $idcar,
            ':dateFinHeureFin' => "$dateFin $heureFin",
            ':dateDebutHeureDebut' => "$dateDebut $heureDebut"
        ]);

        if ($stmtCheck->fetchColumn() > 0) {
            // Redirect with error message if the car is not available
            header("Location: categories.php?message=The+car+is+no+longer+available+for+the+selected+dates.");
            exit();
        }

        // Insert reservation
        $sqlInsert = "INSERT INTO reservation 
                     (depart, arrive, heureDebut, heureFin, Date_debut, Date_fin, idcar, iduser, confirm)
                     VALUES (:depart, :arrive, :heureDebut, :heureFin, :dateDebut,++++++++++++ :dateFin, :idcar, :iduser, 0)";
        $stmtInsert = $mysqlconnection->prepare($sqlInsert);
        $stmtInsert->execute([
            ':depart' => $depart,
            ':arrive' => $arrive,
            ':heureDebut' => $heureDebut,
            ':heureFin' => $heureFin,
            ':dateDebut' => $dateDebut,
            ':dateFin' => $dateFin,
            ':idcar' => $idcar,
            ':iduser' => $_SESSION['user_id']
        ]);

        // Redirect with success message
        header("Location: index.php?message=Your+reservation+has+been+successfully+processed.");
        exit();
    } catch (PDOException $e) {
        // Redirect with error message if there's a database error
        header("Location: index.php?message=Error+processing+reservation+:+" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Redirect to reservation page with parameters for guest users
    $params = http_build_query($_GET);
    header("Location: reserve.php?$params");
    exit();
}
