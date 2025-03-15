<?php
include("../assets/connectDB.php"); // Fichier de connexion à la base de données

$error = ""; // Variable pour stocker les messages d'erreur

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération et nettoyage des données
    $name = trim(htmlspecialchars($_POST['name']));
    $email = trim(htmlspecialchars($_POST['email']));
    $phone = trim(htmlspecialchars($_POST['phone']));
    $password = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirm_password']);

    // Validation des champs
    if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = "Name, email, and passwords are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Vérifier si l'email existe déjà
            $stmt = $mysqlconnection->prepare("SELECT email FROM user WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $error = "Email already exists.";
            } else {
                // Hasher le mot de passe
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Insérer l'utilisateur dans la base de données
                $sql = "INSERT INTO user (fullname, email, password, phone, role) 
                        VALUES (:username, :email, :password, :phone, :role)";

                $stmt = $mysqlconnection->prepare($sql);
                $stmt->execute([
                    ':username' => $name,
                    ':email' => $email,
                    ':password' => $hashedPassword,
                    ':phone' => $phone, // NULL si vide
                    ':role' => 0 // 0 = utilisateur normal, 1 = admin
                ]);

                // Rediriger vers la page de connexion avec un message de succès
                header("Location: login.php?success=Account+created+successfully");
                exit();
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REIM CARS - Signup</title>
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

        .signup-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            width: 350px;
        }

        .signup-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }

        .signup-container input[type="text"],
        .signup-container input[type="email"],
        .signup-container input[type="password"],
        .signup-container input[type="tel"] {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .signup-container input[type="submit"] {
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

        .signup-container input[type="submit"]:hover {
            background-color: #011468;
        }

        .options {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }

        .options a {
            text-decoration: none;
            color: #011468;
            font-size: 14px;
        }

        .options a:hover {
            text-decoration: underline;
        }

        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <div class="signup-container">
        <h2>Create Account</h2>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?= $error ?></div>
        <?php endif; ?>

        <form action="signup.php" method="post" onsubmit="return validatePasswords()">
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="tel" name="phone" placeholder="Phone" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <input type="submit" value="Sign Up">
        </form>

        <div class="options">
            <a href="login.php">Already have an account?</a>
            <a href="index.php">Home</a>
        </div>
    </div>

    <script>
        function validatePasswords() {
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            const errorMessage = document.createElement("div");
            errorMessage.style.color = "red";
            errorMessage.style.marginBottom = "10px";
            errorMessage.style.textAlign = "center";

            if (password !== confirmPassword) {
                errorMessage.textContent = "Passwords do not match.";
                const form = document.querySelector("form");
                form.insertBefore(errorMessage, form.firstChild);
                return false; // Empêche la soumission du formulaire
            } else {
                return true; // Autorise la soumission du formulaire
            }
        }
    </script>
</body>

</html>