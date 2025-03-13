<?php
// Inclure le fichier de connexion à la base de données
include("connectDB.php");

// Récupérer les données du formulaire
$idcar = $_POST['idcar'] ?? '';
$depart = $_POST['depart'] ?? '';
$arrive = $_POST['arrive'] ?? '';
$dateDebut = $_POST['Date_debut'] ?? '';
$heureDebut = $_POST['heureDebut'] ?? '';
$dateFin = $_POST['Date_fin'] ?? '';
$heureFin = $_POST['heureFin'] ?? '';
$name = $_POST['name'] ?? '';
$fullname = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$password = $_POST['password'] ?? '';

try {
    // Utiliser la connexion PDO définie dans connectDB.php
    $pdo = $mysqlconnection;

    // Insérer les données dans la table user
    $sqlUser = "INSERT INTO user (name, fullname, email, phone, password) 
                VALUES (:name, :fullname, :email, :phone, :password)";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([
        ':name' => $name,
        ':fullname' => $fullname,
        ':email' => $email,
        ':phone' => $phone,
        ':password' => $password,
    ]);

    // Récupérer l'ID de l'utilisateur inséré
    $iduser = $pdo->lastInsertId();

    // Insérer les données dans la table reservation
    $sqlReservation = "INSERT INTO reservation (depart, arrive, heureDebut, heureFin, Date_debut, Date_fin, idcar, iduser) 
                       VALUES (:depart, :arrive, :heureDebut, :heureFin, :Date_debut, :Date_fin, :idcar, :iduser)";
    $stmtReservation = $pdo->prepare($sqlReservation);
    $stmtReservation->execute([
        ':depart' => $depart,
        ':arrive' => $arrive,
        ':heureDebut' => $heureDebut,
        ':heureFin' => $heureFin,
        ':Date_debut' => $dateDebut,
        ':Date_fin' => $dateFin,
        ':idcar' => $idcar,
        ':iduser' => $iduser
    ]);

    echo "Réservation confirmée avec succès !";
} catch (PDOException $e) {
    // Gestion des erreurs PDO
    echo "Erreur lors de la réservation : " . $e->getMessage();
} catch (Exception $e) {
    // Gestion des autres erreurs
    echo "Erreur : " . $e->getMessage();
}
?>