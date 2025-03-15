<?php
session_start();

// Vérifier si l'utilisateur est connecté et a le rôle admin (role != 0)
if (!isset($_SESSION['role']) || $_SESSION['role'] == 0) {
    // Rediriger vers la page de connexion ou afficher une erreur
    header('Location: ../index.php');
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <title>Admin Dashboard</title>
    <!-- Favicon-->
    <link rel="icon" href="" type="image/x-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&subset=latin,cyrillic-ext" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" type="text/css">

    <!-- Bootstrap Core Css -->
    <link href="plugins/bootstrap/css/bootstrap.css" rel="stylesheet">

    <!-- Waves Effect Css -->
    <link href="plugins/node-waves/waves.css" rel="stylesheet" />

    <!-- Animation Css -->
    <link href="plugins/animate-css/animate.css" rel="stylesheet" />

    <!-- Custom Css -->
    <link href="css/style.css" rel="stylesheet">

    <!-- AdminBSB Themes. You can choose a theme from css/themes instead of get all themes -->
    <link href="css/themes/all-themes.css" rel="stylesheet" />
</head>

<body class="theme-red">
    <!-- Page Loader -->
    <div class="page-loader-wrapper">
        <div class="loader">
            <div class="preloader">
                <div class="spinner-layer pl-red">
                    <div class="circle-clipper left">
                        <div class="circle"></div>
                    </div>
                    <div class="circle-clipper right">
                        <div class="circle"></div>
                    </div>
                </div>
            </div>
            <p>Please wait...</p>
        </div>
    </div>
    <!-- #END# Page Loader -->

    <!-- Overlay For Sidebars -->
    <div class="overlay"></div>
    <!-- #END# Overlay For Sidebars -->

    <!-- Top Bar -->
    <nav class="navbar">
        <div class="container-fluid">
            <div class="navbar-header">
                <a href="javascript:void(0);" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse" aria-expanded="false"></a>
                <a href="javascript:void(0);" class="bars"></a>
                <a class="navbar-brand" href="#">ADMIN DASHBOARD</a>
            </div>
        </div>
    </nav>
    <!-- #Top Bar -->

    <!-- Left Sidebar -->
    <aside id="leftsidebar" class="sidebar">
        <!-- User Info -->
        <div class="user-info">
            <div class="image">
                <img src="images/user.png" width="48" height="48" alt="User" />
            </div>
            <div class="info-container">
                <div class="name" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <?php echo htmlspecialchars($_SESSION['username']); ?>
                </div>
                <div class="email">
                    <?php echo htmlspecialchars($_SESSION['email']); ?>
                </div>
                <div class="btn-group user-helper-dropdown">
                    <i class="material-icons" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">keyboard_arrow_down</i>
                    <ul class="dropdown-menu pull-right">
                        <!--
                        <li><a href="javascript:void(0);"><i class="material-icons">person</i>Profile</a></li> 
                        <li role="seperator" class="divider"></li>-->
                        <li><a href=".."><i class="material-icons">web</i>Website</a></li>
                        <li><a href="logout.php"><i class="material-icons">input</i>Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- #User Info -->

        <!-- Menu -->
        <div class="menu">
            <ul class="list">
                <li class="header">MAIN NAVIGATION</li>
                <li class="<?php if ($active == "1") {
                                echo "active";
                            } ?>">
                    <a href="cars.php">
                        <i class="material-icons">home</i>
                        <span>Cars</span>
                    </a>
                </li>
                <li class="<?php if ($active == "2") {
                                echo "active";
                            } ?>">
                    <a href="index.php">
                        <i class="material-icons">home</i>
                        <span>Reservation</span>
                    </a>
                </li>
            </ul>
        </div>
        <!-- #Menu -->

        <!-- Footer -->
        <div class="legal">
            <div class="copyright">
                &copy; 2025 <a href="javascript:void(0);">Admin Dashboard</a>.
            </div>
            <div class="version">
                <b>Version: </b> 1.0.0
            </div>
        </div>
        <!-- #Footer -->
    </aside>
    <!-- #END# Left Sidebar -->
</body>

</html>