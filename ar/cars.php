<?php
$active = "cars";
include("../assets/connectDB.php");
include("header_p.php");

try {
    // Requête SQL pour sélectionner toutes les colonnes de la table `car`
    $sql = "SELECT * FROM car";

    // Préparer la requête
    $stmt = $mysqlconnection->prepare($sql);

    // Exécuter la requête
    $stmt->execute();

    // Récupérer les résultats sous forme de tableau associatif
    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('Erreur lors de la récupération des données : ' . $e->getMessage());
}
?>
<!-- Header Start -->
<div class="container-fluid bg-breadcrumb">
    <div class="container text-center py-5" style="max-width: 900px;">
        <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s">Our Cars</h4>
        <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="#">Pages</a></li>
            <li style="text-decoration: underline;" class="breadcrumb-item active text-white">Categories</li>
        </ol>
    </div>
</div>
<!-- Header End -->

<!-- Car categories Start -->
<div class="container-fluid categories py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize mb-3">Vehicle <span class="text-secondary">Categories</span></h1>
            <p class="mb-0">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Ut amet nemo expedita
                asperiores commodi accusantium at cum harum, excepturi, quia tempora cupiditate! Adipisci facilis
                modi quisquam quia distinctio.</p>
        </div>
        <div class="row g-4 wow fadeInUp" data-wow-delay="0.1s">
            <div class="row g-4 wow fadeInUp" data-wow-delay="0.1s">
                <?php foreach ($cars as $car): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="categories-item p-4">
                            <div class="categories-item-inner">
                                <div class="categories-img rounded-top">
                                    <img src="img/car-<?= $car['idcar'] ?>.png"
                                        class="img-fluid w-100 rounded-top"
                                        alt="<?= htmlspecialchars($car['name']) ?>">
                                </div>
                                <div class="categories-content rounded-bottom p-4">
                                    <h4><?= htmlspecialchars($car['name']) ?></h4>
                                    <div class="mb-4">
                                        <h4 class="bg-white text-secondary rounded-pill py-2 px-4 mb-0">
                                            <b>$<?= htmlspecialchars($car['price']) ?></b>
                                            <small>/Day</small>
                                        </h4>
                                    </div>
                                    <div class="row gy-2 gx-0 text-center mb-4">
                                        <!-- Ligne 1 -->
                                        <div class="col-4 border-end border-white">
                                            <i class="fas fa-users text-dark"></i>
                                            <span class="text-body ms-1"><?= $car['seat'] ?> Seats</span>
                                        </div>

                                        <!-- Transmission -->
                                        <div class="col-4 border-end border-white">
                                            <?php if ($car['type'] == 1): ?>
                                                <i class="fas fa-tachometer-alt text-dark"></i>
                                                <span class="text-body ms-1">AUTO</span>
                                            <?php else: ?>
                                                <i class="fas fa-hand-paper text-dark"></i>
                                                <span class="text-body ms-1">Manual</span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-4">
                                            <i class="fas fa-gas-pump text-dark"></i>
                                            <span class="text-body ms-1">Petrol</span>
                                        </div>

                                        <!-- Ligne 2 -->
                                        <div class="col-4 border-end border-white mt-2">
                                            <i class="fas fa-door-closed text-dark"></i>
                                            <span class="text-body ms-1"><?= $car['door'] ?> Doors</span>
                                        </div>

                                        <!-- Vitesses -->
                                        <div class="col-4 border-end border-white mt-2">
                                            <i class="fas fa-cogs text-dark"></i>
                                            <span class="text-body ms-1"><?= $car['gear'] ?? '6-Speed' ?></span>
                                        </div>

                                        <div class="col-4 mt-2">
                                            <i class="fas fa-suitcase text-dark"></i>
                                            <span class="text-body ms-1"><?= $car['bag'] ?> Bags</span>
                                        </div>
                                    </div>
                                    <a href="index.php" class="btn btn-primary rounded-pill d-flex justify-content-center py-3">
                                        Book Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Car categories End -->

<!-- Car Steps Start -->
<div class="container-fluid steps py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize text-white mb-3">Cental<span class="text-white"> Process</span>
            </h1>
            <p class="mb-0 text-white">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Ut amet nemo
                expedita asperiores commodi accusantium at cum harum, excepturi, quia tempora cupiditate! Adipisci
                facilis modi quisquam quia distinctio,
            </p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.1s">
                <div class="steps-item p-4 mb-4">
                    <h4>Come In Contact</h4>
                    <p class="mb-0">Lorem ipsum dolor sit amet consectetur adipisicing elit. Ad, dolorem!</p>
                    <div class="setps-number">01.</div>
                </div>
            </div>
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.3s">
                <div class="steps-item p-4 mb-4">
                    <h4>Choose A Car</h4>
                    <p class="mb-0">Lorem ipsum dolor sit amet consectetur adipisicing elit. Ad, dolorem!</p>
                    <div class="setps-number">02.</div>
                </div>
            </div>
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.5s">
                <div class="steps-item p-4 mb-4">
                    <h4>Enjoy Driving</h4>
                    <p class="mb-0">Lorem ipsum dolor sit amet consectetur adipisicing elit. Ad, dolorem!</p>
                    <div class="setps-number">03.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Car Steps End -->
<?php
include("footer_p.php") ?>