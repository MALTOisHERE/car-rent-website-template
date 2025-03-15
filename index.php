<?php
session_start();

// If language is already set, redirect to the corresponding folder (en or fr)
if (isset($_SESSION['lang'])) {
    if ($_SESSION['lang'] == 'en') {
        header("Location: en/");
        exit();
    } elseif ($_SESSION['lang'] == 'fr') {
        header("Location: fr/");
        exit();
    }elseif ($_SESSION['lang'] == 'ar') {
        header("Location: ar/");
        exit();
    }
}

// If no language is set in the session, redirect to English by default
header("Location: en/");
exit();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Language</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Custom styles for centering and aesthetics */
        .full-height {
            min-height: 100vh;
        }
        .spinner-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center full-height">

    <!-- Spinner Start (same as in the header) -->
    <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-border" style="width: 3rem; height: 3rem; color: #011468;" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <!-- Spinner End -->
    
    <!-- Bootstrap JS (Optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
