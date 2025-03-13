<?php
session_start();
include("connectDB.php"); // Inclure votre fichier de connexion à la base de données

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];

    try {
        // 1. Vérifier si l'email existe dans la base de données
        $stmt = $conn->prepare("SELECT iduser FROM user WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $userId = $user['iduser'];

            // 2. Générer un token de réinitialisation de mot de passe
            $token = bin2hex(random_bytes(50)); // Token sécurisé
            $expiry = date("Y-m-d H:i:s", strtotime("+1 hour")); // Token valide pendant 1 heure

            // 3. Enregistrer le token dans la base de données
            $stmt = $conn->prepare("UPDATE user SET reset_token = :token, reset_token_expiry = :expiry WHERE id = :id");
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expiry', $expiry);
            $stmt->bindParam(':iduser', $userId);
            $stmt->execute();

            // 4. Envoyer un email à l'utilisateur avec un lien de réinitialisation
            $resetLink = "http://votresite.com/reset_password.php?token=$token"; // Lien de réinitialisation
            $subject = "Réinitialisation de votre mot de passe";
            $message = "Bonjour,\n\nCliquez sur le lien suivant pour réinitialiser votre mot de passe :\n$resetLink\n\nCe lien expirera dans 1 heure.";
            $headers = "From: no-reply@votresite.com";

            if (mail($email, $subject, $message, $headers)) {
                // 5. Rediriger l'utilisateur vers une page de confirmation
                $_SESSION['message'] = "Un lien de réinitialisation a été envoyé à votre adresse email.";
                header("Location: forgot_password_confirmation.php");
                exit();
            } else {
                throw new Exception("Erreur lors de l'envoi de l'email.");
            }
        } else {
            $_SESSION['error'] = "Aucun utilisateur trouvé avec cet email.";
            header("Location: forgot_password.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
        header("Location: forgot_password.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: forgot_password.php");
        exit();
    }
} else {
    header("Location: forgot_password.php");
    exit();
}
?>