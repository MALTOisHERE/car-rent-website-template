<?php
session_start();

// If a language is chosen, set it in the session and redirect to the corresponding folder
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
    if ($_GET['lang'] == 'en') {
        header("Location: .");
    } else if ($_GET['lang'] == 'fr') {
        header("Location: ../fr/");
    } else if ($_GET['lang'] == 'ar') {
        header("Location: ../ar/");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>REIMS CARS</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="" name="keywords">
    <meta content="" name="description">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,400;0,700;0,900;1,400;1,700;1,900&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="lib/animate/animate.min.css" rel="stylesheet">
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">


    <!-- Customized Bootstrap Stylesheet -->
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="css/style.css" rel="stylesheet">
</head>

<body>

    <!-- Spinner Start -->
    <div id="spinner"
        class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-border" style="width: 3rem; height: 3rem; color: #011468;" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <!-- Spinner End -->

    <!-- Topbar Start -->
    <div style="background-color: #011468;" class="container-fluid topbar d-none d-xl-block w-100">
        <div class="container">
            <div class="row gx-0 align-items-center" style="height: 45px;">
                <div class="col-lg-6 text-center text-lg-start mb-lg-0">
                    <div class="d-flex flex-wrap">
                        <a href="#" class="text-white me-4"><i class="fas fa-map-marker-alt text-white me-2"></i>Find A
                            Location</a>
                        <a href="tel:+01234567890" class="text-white me-4"><i
                                class="fas fa-phone-alt text-white me-2"></i>+01234567890</a>
                        <a href="mailto:example@gmail.com" class="text-white me-0"><i
                                class="fas fa-envelope text-white me-2"></i>Example@gmail.com</a>
                    </div>
                </div>
                <div class="col-lg-6 text-center text-lg-end">
                    <div class="d-flex align-items-center justify-content-end">
                        <a href="#" class="btn btn-light btn-sm-square rounded-circle me-3"><i
                                class="fab fa-facebook-f"></i></a>
                        <a href="#" class="btn btn-light btn-sm-square rounded-circle me-3"><i
                                class="fab fa-twitter"></i></a>
                        <a href="#" class="btn btn-light btn-sm-square rounded-circle me-3"><i
                                class="fab fa-instagram"></i></a>
                        <a href="#" class="btn btn-light btn-sm-square rounded-circle me-0"><i
                                class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Topbar End -->

    <!-- Navbar & Hero Start -->
    <div class="container-fluid nav-bar sticky-top px-0 px-lg-4 py-2 py-lg-0">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light">
                <a href="" class="navbar-brand p-0">
                    <img src="../img/logo.png" alt="Logo">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                    <span class="fa fa-bars"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <div class="navbar-nav mx-auto py-0">
                        <a href="index.php" class="nav-item nav-link custom-nav-link <?php if ($active == "index") {
                                                                                            echo "active";
                                                                                        } ?>">Home</a>
                        <a href="cars.php" class="nav-item nav-link <?php if ($active == "cars") {
                                                                        echo "active";
                                                                    } ?>">Our Cars</a>
                        <a href="about.php" class="nav-item nav-link <?php if ($active == "about") {
                                                                            echo "active";
                                                                        } ?>">About</a>
                        <a href="service.php" class="nav-item nav-link <?php if ($active == "service") {
                                                                            echo "active";
                                                                        } ?>">Service</a>
                        <a href="testimonial.php" class="nav-item nav-link <?php if ($active == "testimonial") {
                                                                                echo "active";
                                                                            } ?>">Testimonial</a>
                        <a href="contact.php" class="nav-item nav-link <?php if ($active == "contact") {
                                                                            echo "active";
                                                                        } ?>">Contact</a>
                        <style>
                            /* Custom hover effect for dropdown items */
                            .dropdown-item:hover {
                                background-color: #011468 !important;
                                /* Change background to blue on hover */
                                color: white !important;
                                /* Change text color to white on hover */
                            }
                        </style>

                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Language</a>
                            <div class="dropdown-menu m-0">
                                <!-- Language Toggle Start -->
                                <a href="?lang=en" class="dropdown-item">
                                    <img src="https://flagcdn.com/us.svg" alt="English" width="20" style="vertical-align: middle; margin-right: 5px;"> English
                                </a>
                                <a href="?lang=fr" class="dropdown-item">
                                    <img src="https://flagcdn.com/fr.svg" alt="Français" width="20" style="vertical-align: middle; margin-right: 5px;"> Français
                                </a>
                                <a href="?lang=ar" class="dropdown-item">
                                    <img src="https://flagcdn.com/sa.svg" alt="العربية" width="20" style="vertical-align: middle; margin-right: 5px;"> العربية
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- Your existing HTML code -->

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- Display Logout button and user information if logged in -->
                        <div style="margin-right: 10px;">
                            <?php if ($_SESSION['role'] != 0): ?>
                                <!-- Show Admin Dashboard button for admins -->
                                <a href="../admin/" class="btn btn-warning rounded-pill py-2 px-4">
                                    <i class="fas fa-tachometer-alt me-2"></i> Admin Dashboard
                                </a>
                            <?php endif; ?>

                            <!-- Logout button for all logged-in users -->
                            <a href="../logout.php" class="btn btn-danger rounded-pill py-2 px-4">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Display Login button if not logged in -->
                        <a style="margin-right: 10px;" href="login.php" class="btn btn-primary rounded-pill py-2 px-4">
                            <i class="fas fa-user me-2"></i> Login
                        </a>
                    <?php endif; ?>

                    <!-- Your existing HTML code -->
                </div>
            </nav>
        </div>
    </div>
    <!-- Navbar & Hero End -->

    <!-- Bootstrap JS (Optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>