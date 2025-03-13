<?php
session_start();
include("connectDB.php"); // Fichier de configuration contenant la connexion à la base de données

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Nettoyage des entrées pour éviter les attaques XSS
    $email = trim(htmlspecialchars($_POST['email']));
    $password = trim($_POST['password']);

    try {
        // Connexion à la base de données (assurez-vous que $conn est bien défini dans config.php)
        if (!isset($mysqlconnection)) {
            throw new Exception("Connexion à la base de données non établie.");
        }

        // Requête sécurisée avec PDO
        $stmt = $mysqlconnection->prepare("SELECT iduser, email, password, role FROM user WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vérification du mot de passe hashé
            if (password_verify($password, $user['password'])) {
                // Connexion réussie
                $_SESSION['user_id'] = $user['iduser'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];  // Store the role in session

                // Redirection en fonction du rôle
                if ($_SESSION['role'] == '0') {
                    // If role is 0, redirect to the home page
                    header("Location: index.php");
                } else {
                    // If role is not 0 (complex char), redirect to the admin page
                    header("Location: ../admin");
                }

                exit(); // Arrête l'exécution du script après la redirection
            } else {
                echo "Mot de passe incorrect.";
            }
        } else {
            echo "Aucun utilisateur trouvé avec cet email.";
        }
    } catch (PDOException $e) {
        echo "Erreur de connexion à la base de données: " . $e->getMessage();
    } catch (Exception $e) {
        echo "Erreur: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REIM CARS - Login</title>
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

        .login-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            width: 350px;
        }

        .login-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }

        .login-container input[type="email"],
        .login-container input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .login-container input[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: #011468;
            border: none;
            border-radius: 6px;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .login-container input[type="submit"]:hover {
            background-color: #011468;
        }

        .login-container .options {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }

        .login-container .options a {
            text-decoration: none;
            color: #011468;
            font-size: 14px;
        }

        .login-container .options a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h2>Login</h2>
        <form action="login.php" method="post">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="submit" value="Se connecter">
        </form>
        <div class="options">
            <a href="forgot_password.php">Forgot password ?</a>
            <a href="index.php">Back to Home</a>
        </div>
    </div>
</body>

</html>
