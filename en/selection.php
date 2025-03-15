<?php
// Include the database connection file
include("../assets/connectDB.php");
include("header_p.php");

try {
    // Fetch all cars
    $sqlCars = "SELECT * FROM car";
    $stmtCars = $mysqlconnection->prepare($sqlCars);
    $stmtCars->execute();
    $cars = $stmtCars->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all cars
    $sqlreservation = "SELECT * FROM reservation";
    $stmtreservation = $mysqlconnection->prepare($sqlreservation);
    $stmtreservation->execute();
    $reservations = $stmtreservation->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('Error retrieving data: ' . $e->getMessage());
}

// Récupérer les paramètres de l'URL
$depart = $_GET['depart'] ?? '';
$arrive = $_GET['arrive'] ?? '';
$dateDebut = $_GET['Date_debut'] ?? '';
$heureDebut = $_GET['heureDebut'] ?? '';
$dateFin = $_GET['Date_fin'] ?? '';
$heureFin = $_GET['heureFin'] ?? '';

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
        <div class="row g-4 wow fadeInUp" data-wow-delay="0.1s"> <!-- Utilisation d'une grille Bootstrap -->
            <div class="row g-4 wow fadeInUp" data-wow-delay="0.1s">
                <?php foreach ($cars as $car): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="categories-item p-4">
                            <div class="categories-item-inner">
                                <div class="categories-img rounded-top">
                                    <img src="../img/<?= $car['image'] ?>" class="img-fluid w-100 rounded-top"
                                        alt="<?= htmlspecialchars($car['name']) ?>">
                                </div>
                                <div class="categories-content rounded-bottom p-4">
                                    <h4><?= htmlspecialchars($car['name']) ?></h4>
                                    <div class="categories-review mb-4">
                                        <div class="me-3">4.5 Review</div>
                                        <div class="d-flex justify-content-center text-secondary">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star text-body"></i>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <h4 class="bg-white text-scondary rounded-pill py-2 px-4 mb-0">
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
                                    <style>
                                        .disabled-style {
                                            pointer-events: auto;
                                            /* Enable clicking */
                                            opacity: 0.6;
                                            /* Grayed-out appearance */
                                            cursor: not-allowed;
                                            /* Show a "disabled" cursor */
                                        }
                                    </style>
                                    <?php
                                    // ... existing code ...

                                    // Inside the car loop
                                    $sqlReservation = "SELECT COUNT(*) FROM reservation 
                   WHERE idcar = :idcar 
                   AND confirm = 1 
                   AND (
                       STR_TO_DATE(CONCAT(Date_debut, ' ', heureDebut), '%Y-%m-%d %H:%i') < STR_TO_DATE(:dateFinHeureFin, '%Y-%m-%d %H:%i')
                       AND
                       STR_TO_DATE(CONCAT(Date_fin, ' ', heureFin), '%Y-%m-%d %H:%i') > STR_TO_DATE(:dateDebutHeureDebut, '%Y-%m-%d %H:%i')
                   )";
                                    $stmtReservation = $mysqlconnection->prepare($sqlReservation);
                                    $stmtReservation->execute([
                                        ':idcar' => $car['idcar'],
                                        ':dateFinHeureFin' => "$dateFin $heureFin",
                                        ':dateDebutHeureDebut' => "$dateDebut $heureDebut"
                                    ]);
                                    $count = $stmtReservation->fetchColumn();

                                    if ($count > 0) {
                                        $availableAt = "This car is already booked for the selected period.";
                                        $isDisabled = 'disabled-style';
                                        $onclick = "alert('$availableAt'); return false;";
                                    } else {
                                        $isDisabled = '';
                                        $onclick = "";
                                    }
                                    ?>

                                    <a href="process_booking.php?idcar=<?= $car['idcar'] ?>&depart=<?= urlencode($depart) ?>&arrive=<?= urlencode($arrive) ?>&Date_debut=<?= urlencode($dateDebut) ?>&heureDebut=<?= urlencode($heureDebut) ?>&Date_fin=<?= urlencode($dateFin) ?>&heureFin=<?= urlencode($heureFin) ?>"
                                        class="btn btn-primary rounded-pill d-flex justify-content-center py-3 <?= $isDisabled ?>"
                                        onclick="<?= $onclick ?>">
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
</div>
<!-- Car categories End -->
<?php
include("footer_p.php") ?>