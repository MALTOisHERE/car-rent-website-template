<?php
session_start();
include("connectDB.php");

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // Vérifier si le token est valide et non expiré
        $stmt = $conn->prepare("SELECT iduser FROM user WHERE reset_token = :token AND reset_token_expiry > NOW()");
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $userId = $user['iduser'];

            // Afficher le formulaire de réinitialisation de mot de passe
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $newPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

                // Mettre à jour le mot de passe et effacer le token
                $stmt = $conn->prepare("UPDATE user SET password = :password, reset_token = NULL, reset_token_expiry = NULL WHERE iduser = :iduser");
                $stmt->bindParam(':password', $newPassword);
                $stmt->bindParam(':iduser', $userId);
                $stmt->execute();

                $_SESSION['message'] = "Votre mot de passe a été réinitialisé avec succès.";
                header("Location: login.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "Lien invalide ou expiré.";
            header("Location: forgot_password.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
        header("Location: forgot_password.php");
        exit();
    }
} else {
    header("Location: forgot_password.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .reset-password-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 300px;
        }
        .reset-password-container h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .reset-password-container input[type="password"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .reset-password-container input[type="submit"] {
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            border: none;
            border-radius: 4px;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
        }
        .reset-password-container input[type="submit"]:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>
    <div class="reset-password-container">
        <h2>Réinitialisation du mot de passe</h2>
        <form method="post">
            <input type="password" name="new_password" placeholder="Nouveau mot de passe" required>
            <input type="submit" value="Réinitialiser le mot de passe">
        </form>
    </div>
</body>
</html>