<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
        .forgot-password-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            width: 350px;
        }
        .forgot-password-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
        .forgot-password-container input[type="email"] {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .forgot-password-container input[type="submit"] {
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
        .forgot-password-container input[type="submit"]:hover {
            background-color: #011468;
        }
        .forgot-password-container .options {
            display: flex;
            justify-content: center;
            margin-top: 15px;
        }
        .forgot-password-container .options a {
            text-decoration: none;
            color: #011468;
            font-size: 14px;
        }
        .forgot-password-container .options a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <h2>Forgot Password</h2>
        <form action="send_reset_link.php" method="post">
            <input type="email" name="email" placeholder="Email" required>
            <input type="submit" value="Send Reset Link">
        </form>
        <div class="options">
            <a href="index.php">Back to Home</a>
        </div>
    </div>
</body>
</html>
