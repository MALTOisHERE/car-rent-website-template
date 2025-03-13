<?php
session_start();

// إذا تم اختيار لغة، قم بتعيينها في الجلسة وإعادة التوجيه إلى المجلد المقابل
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
    if ($_GET['lang'] == 'en') {
        header("Location: ../en/");
    } else if ($_GET['lang'] == 'fr') {
        header("Location: ../fr/");
    } else if ($_GET['lang'] == 'ar') {
        header("Location: .");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="ar">

<head>
    <meta charset="utf-8">
    <title>REIMS CARS</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="" name="keywords">
    <meta content="" name="description">

    <!-- خطوط جوجل -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,400;0,700;0,900;1,400;1,700;1,900&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">

    <!-- خط الأيقونات -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- أنماط المكتبات -->
    <link href="lib/animate/animate.min.css" rel="stylesheet">
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

    <!-- أنماط Bootstrap المخصصة -->
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- أنماط القالب -->
    <link href="css/style.css" rel="stylesheet">
</head>

<body>

    <!-- بداية المؤشر الدوار -->
    <div id="spinner"
        class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-border" style="width: 3rem; height: 3rem; color: #011468;" role="status">
            <span class="sr-only">جار التحميل...</span>
        </div>
    </div>
    <!-- نهاية المؤشر الدوار -->

    <!-- بداية الشريط العلوي -->
    <div style="background-color: #011468;" class="container-fluid topbar d-none d-xl-block w-100">
        <div class="container">
            <div class="row gx-0 align-items-center" style="height: 45px;">
                <div class="col-lg-6 text-center text-lg-start mb-lg-0">
                    <div class="d-flex flex-wrap">
                        <a href="#" class="text-white me-4"><i class="fas fa-map-marker-alt text-white me-2"></i>العثور على عنوان</a>
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
    <!-- نهاية الشريط العلوي -->

    <!-- بداية الشريط العلوي والبطولة -->
    <div class="container-fluid nav-bar sticky-top px-0 px-lg-4 py-2 py-lg-0">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light">
                <a href="" class="navbar-brand p-0">
                    <img src="../img/logo.png" alt="الشعار">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                    <span class="fa fa-bars"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <div class="navbar-nav mx-auto py-0">
                        <a href="index.php" class="nav-item nav-link custom-nav-link <?php if ($active == "index") {
                                                                                            echo "active";
                                                                                        } ?>">الصفحة الرئيسية</a>
                        <a href="cars.php" class="nav-item nav-link <?php if ($active == "cars") {
                                                                        echo "active";
                                                                    } ?>">سياراتنا</a>
                        <a href="about.php" class="nav-item nav-link <?php if ($active == "about") {
                                                                            echo "active";
                                                                        } ?>">عن الموقع</a>
                        <a href="service.php" class="nav-item nav-link <?php if ($active == "service") {
                                                                            echo "active";
                                                                        } ?>">الخدمات</a>
                        <a href="testimonial.php" class="nav-item nav-link <?php if ($active == "testimonial") {
                                                                                echo "active";
                                                                            } ?>">الشهادات</a>
                        <a href="contact.php" class="nav-item nav-link <?php if ($active == "contact") {
                                                                            echo "active";
                                                                        } ?>">اتصل بنا</a>

                        <style>
                            /* تأثير التمرير المخصص لعناصر القوائم المنسدلة */
                            .dropdown-item:hover {
                                background-color: #011468 !important;
                                /* تغيير الخلفية إلى اللون الأزرق عند التمرير */
                                color: white !important;
                                /* تغيير لون النص إلى الأبيض عند التمرير */
                            }
                        </style>

                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">اللغة</a>
                            <div class="dropdown-menu m-0">
                                <!-- بداية تبديل اللغة -->
                                <a href="?lang=en" class="dropdown-item">
                                    <img src="https://flagcdn.com/us.svg" alt="الإنجليزية" width="20" style="vertical-align: middle; margin-right: 5px;"> الإنجليزية
                                </a>
                                <a href="?lang=fr" class="dropdown-item">
                                    <img src="https://flagcdn.com/fr.svg" alt="الفرنسية" width="20" style="vertical-align: middle; margin-right: 5px;"> الفرنسية
                                </a>
                                <a href="?lang=ar" class="dropdown-item">
                                    <img src="https://flagcdn.com/sa.svg" alt="العربية" width="20" style="vertical-align: middle; margin-right: 5px;"> العربية
                                </a>
                            </div>
                        </div>
                    </div>
                    <a style="margin-right: 10px;" href="login.php" class="btn btn-primary rounded-pill py-2 px-4">
                        <i class="fas fa-user me-2"></i> تسجيل الدخول
                    </a>
                </div>
            </nav>
        </div>
    </div>
    <!-- نهاية الشريط العلوي والبطولة -->

    <!-- JavaScript الخاص بـ Bootstrap (اختياري) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>