<?php
// Inclure le fichier de connexion à la base de données
include("../assets/connectDB.php");

// Récupérer les données du formulaire
$idcar = $_POST['idcar'] ?? '';
$depart = $_POST['depart'] ?? '';
$arrive = $_POST['arrive'] ?? '';
$dateDebut = $_POST['Date_debut'] ?? '';
$heureDebut = $_POST['heureDebut'] ?? '';
$dateFin = $_POST['Date_fin'] ?? '';
$heureFin = $_POST['heureFin'] ?? '';
$fullname = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$password = $_POST['password'] ?? '';

// Hasher le mot de passe
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    // Utiliser la connexion PDO définie dans connectDB.php
    $pdo = $mysqlconnection;

    // Insérer les données dans la table user avec le mot de passe haché
    $sqlUser = "INSERT INTO user (fullname, email, phone, password) 
                VALUES (:fullname, :email, :phone, :password)";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([
        ':fullname' => $fullname,
        ':email' => $email,
        ':phone' => $phone,
        ':password' => $hashedPassword,
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

    // Récupérer l'ID de la réservation insérée
    $idReservation = $pdo->lastInsertId();

    // Rediriger vers index.php avec un message de succès incluant l'ID de réservation
    header("Location: index.php?message=Votre+réservation+a+été+enregistrée.+ID+de+réservation+:+$idReservation");
    exit(); // Assurer l'arrêt du script après la redirection
} catch (PDOException $e) {
    // Gérer les erreurs PDO
    header("Location: index.php?message=Erreur+lors+de+la+réservation+:+" . urlencode($e->getMessage()));
    exit();
} catch (Exception $e) {
    // Gérer les autres erreurs
    header("Location: index.php?message=Erreur+:+" . urlencode($e->getMessage()));
    exit();
}