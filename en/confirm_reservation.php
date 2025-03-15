<?php
// Include the database connection file
include("../assets/connectDB.php");

// Retrieve form data
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

try {
    // Use the PDO connection from connectDB.php
    $pdo = $mysqlconnection;

    // Check if the email already exists
    $checkEmailStmt = $pdo->prepare("SELECT email FROM user WHERE email = :email");
    $checkEmailStmt->execute([':email' => $email]);
    if ($checkEmailStmt->rowCount() > 0) {
        // Redirect back to reservation form with error and data
        $params = [
            'error' => 'Email+already+exists',
            'idcar' => $idcar,
            'depart' => $depart,
            'arrive' => $arrive,
            'Date_debut' => $dateDebut,
            'heureDebut' => $heureDebut,
            'Date_fin' => $dateFin,
            'heureFin' => $heureFin,
            'fullname' => $fullname,
            'email' => $email,
            'phone' => $phone
        ];
        $queryString = http_build_query($params);
        header("Location: reserve.php?$queryString");
        exit();
    }

    // Hash the password (only if email is unique)
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user into the `user` table
    $sqlUser = "INSERT INTO user (fullname, email, phone, password) 
                VALUES (:fullname, :email, :phone, :password)";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([
        ':fullname' => $fullname,
        ':email' => $email,
        ':phone' => $phone,
        ':password' => $hashedPassword,
    ]);

    // Get the newly created user's ID
    $iduser = $pdo->lastInsertId();

    // Insert reservation into the `reservation` table
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

    // Get the reservation ID
    $idReservation = $pdo->lastInsertId();

    // Redirect to success page with reservation ID
    header("Location: index.php?message=Your+reservation+has+been+successfully+processed.+Reservation+ID+:+$idReservation");
    exit();
} catch (PDOException $e) {
    // Handle database errors
    header("Location: index.php?message=Erreur+lors+de+la+réservation+:+" . urlencode($e->getMessage()));
    exit();
} catch (Exception $e) {
    // Handle other errors
    header("Location: index.php?message=Erreur+:+" . urlencode($e->getMessage()));
    exit();
}
?>