<?php
session_start();
include("../assets/connectDB.php");

// Anti-brute force configuration
$MAX_ATTEMPTS = 3;          // Max allowed attempts
$LOCKOUT_TIME = 30;         // Lockout time in seconds
$CAPTCHA_REQUIRE_ATTEMPTS = 2; // Require captcha after N attempts

// Function to increment failed attempts
function incrementFailedAttempt() {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['last_failed_time'] = time();
}

// Function to clear failed attempts
function clearFailedAttempts() {
    unset($_SESSION['login_attempts']);
    unset($_SESSION['last_failed_time']);
    unset($_SESSION['captcha_sum']);
}

// Generate new captcha if needed
if (!isset($_SESSION['captcha_sum']) || ($_SESSION['login_attempts'] ?? 0) >= $CAPTCHA_REQUIRE_ATTEMPTS) {
    $num1 = rand(1, 15);
    $num2 = rand(1, 15);
    $_SESSION['captcha_sum'] = $num1 + $num2;
    $_SESSION['captcha_num1'] = $num1;
    $_SESSION['captcha_num2'] = $num2;
}

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if user is temporarily locked out
    if (isset($_SESSION['last_failed_time']) && 
        isset($_SESSION['login_attempts']) && 
        $_SESSION['login_attempts'] >= $MAX_ATTEMPTS) {
        
        $elapsed = time() - $_SESSION['last_failed_time'];
        if ($elapsed < $LOCKOUT_TIME) {
            $remaining = $LOCKOUT_TIME - $elapsed;
            $_SESSION['error'] = "Trop de tentatives. Veuillez réessayer dans $remaining secondes.";
            header("Location: login.php");
            exit();
        } else {
            // Lockout period expired - reset attempts
            clearFailedAttempts();
        }
    }

    // Verify captcha if required
    $captcha_required = ($_SESSION['login_attempts'] ?? 0) >= $CAPTCHA_REQUIRE_ATTEMPTS;
    if ($captcha_required) {
        $user_captcha = filter_input(INPUT_POST, 'captcha', FILTER_VALIDATE_INT);
        
        if ($user_captcha === false || $user_captcha !== $_SESSION['captcha_sum']) {
            $_SESSION['error'] = "Vérification anti-robot incorrecte";
            incrementFailedAttempt();
            header("Location: login.php");
            exit();
        }
    }

    // Process credentials
    $email = trim(htmlspecialchars($_POST['email']));
    $password = trim($_POST['password']);

    try {
        if (!isset($mysqlconnection)) {
            throw new Exception("Connexion à la base de données non établie.");
        }

        $stmt = $mysqlconnection->prepare("SELECT iduser, fullname, email, password, role FROM user WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($password, $user['password'])) {
                // Successful login
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['iduser'];
                $_SESSION['username'] = $user['fullname'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Clear failed attempts
                clearFailedAttempts();
                
                header("Location: index.php");
                exit();
            } else {
                $_SESSION['error'] = "Mot de passe incorrect.";
                incrementFailedAttempt();
            }
        } else {
            $_SESSION['error'] = "Aucun utilisateur trouvé avec cet email.";
            incrementFailedAttempt();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = reportDatabaseError($e, "User login query failed");
    } catch (Exception $e) {
        $_SESSION['error'] = reportDatabaseError($e, "User login failed");
    }
    
    // Redirect back to login page after error
    header("Location: login.php");
    exit();
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

        .notification {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: #ff4444;
            color: white;
            text-align: center;
            padding: 10px;
            font-size: 16px;
            z-index: 1000;
            display: none;
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

        .form-group {
            margin-bottom: 15px;
        }

        .login-container input[type="email"],
        .login-container input[type="password"],
        .login-container input[type="text"] {
            width: 100%;
            padding: 12px;
            margin: 5px 0;
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
            margin-top: 10px;
        }

        .login-container input[type="submit"]:hover {
            background-color: #00104f;
        }

        .captcha-container {
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 6px;
            margin: 15px 0;
            border: 1px solid #eee;
        }

        .captcha-prompt {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .captcha-prompt span {
            font-weight: bold;
            margin: 0 5px;
            font-size: 16px;
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

        .attempt-warning {
            color: #ff4444;
            font-size: 13px;
            margin-top: 5px;
            display: none;
        }
    </style>
</head>

<body>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="notification" id="notification">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']); 
            ?>
        </div>
        <script>
            document.getElementById('notification').style.display = 'block';
            setTimeout(function() {
                document.getElementById('notification').style.display = 'none';
            }, 5000);
        </script>
    <?php endif; ?>

    <div class="login-container">
        <h2>Connexion</h2>
        <form action="login.php" method="post">
            <div class="form-group">
                <input type="email" name="email" placeholder="Email" required>
            </div>
            
            <div class="form-group">
                <input type="password" name="password" placeholder="Mot de passe" required>
            </div>
            
            <?php if (($_SESSION['login_attempts'] ?? 0) >= $CAPTCHA_REQUIRE_ATTEMPTS): ?>
            <div class="captcha-container">
                <div class="captcha-prompt">
                    Protection anti-robot: 
                    <span><?= $_SESSION['captcha_num1'] ?></span> + 
                    <span><?= $_SESSION['captcha_num2'] ?></span> = ?
                </div>
                <input type="text" name="captcha" placeholder="Entrez le résultat" required>
                <div class="attempt-warning">
                    <?php 
                    $attempts = $_SESSION['login_attempts'] ?? 0;
                    $remaining = $MAX_ATTEMPTS - $attempts;
                    if ($remaining > 0) {
                        echo "Il vous reste $remaining tentatives";
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
            
            <input type="submit" value="Se connecter">
            
            <div class="options">
                <a href="forgot_password.php">Mot de passe oublié ?</a>
                <a href="signup.php">Pas de compte ? Inscription</a>
            </div>
        </form>
    </div>
    
    <script>
        // Show attempt warning if needed
        const captchaContainer = document.querySelector('.captcha-container');
        if (captchaContainer) {
            const warning = captchaContainer.querySelector('.attempt-warning');
            if (warning.textContent.trim() !== '') {
                warning.style.display = 'block';
            }
        }
    </script>
</body>

</html>
