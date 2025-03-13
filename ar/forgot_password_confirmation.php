<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation</title>
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
        .confirmation-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 300px;
            text-align: center;
        }
        .confirmation-container a {
            color: #007bff;
            text-decoration: none;
        }
        .confirmation-container a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <p><?php echo $_SESSION['message']; ?></p>
        <a href="index.php">Retour à l'accueil</a>
    </div>
</body>
</html>