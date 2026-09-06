<?php
$active = "cars";
require_once __DIR__ . '/../assets/connectDB.php';
require_once __DIR__ . '/../app/database.php';
include("header_p.php");

try {
    if ($resolvedAgency) {
        $vehicles = dbFetchAll(
            "SELECT v.*, vc.name AS category_name FROM vehicles v LEFT JOIN vehicle_categories vc ON vc.id=v.category_id
             WHERE v.agency_id=:agency AND v.archived_at IS NULL AND v.status='available' ORDER BY v.base_daily_price",
            ['agency' => $resolvedAgency['id']]
        );
    } else {
        $sql = "SELECT * FROM car";
        $stmt = $mysqlconnection->prepare($sql);
        $stmt->execute();
        $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    die(reportDatabaseError($e, "Loading cars failed"));
}
?>
<!-- Header Start -->
<div class="container-fluid bg-breadcrumb">
    <div class="container text-center py-5" style="max-width: 900px;">
        <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s"><?= $resolvedAgency ? htmlspecialchars($resolvedAgency['name']) . ' - Our Fleet' : 'Our Cars' ?></h4>
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
                <?php if ($resolvedAgency): ?>
                <?php foreach ($vehicles as $vehicle): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="categories-item p-4">
                            <div class="categories-item-inner">
                                <div class="categories-img rounded-top d-flex align-items-center justify-content-center" style="min-height:200px;background:#f1f3f7;">
                                    <i class="fas fa-car fa-3x text-secondary" aria-hidden="true"></i>
                                </div>
                                <div class="categories-content rounded-bottom p-4">
                                    <h4><?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) ?></h4>
                                    <div class="mb-4">
                                        <h4 class="bg-white text-secondary rounded-pill py-2 px-4 mb-0">
                                            <b><?= htmlspecialchars(number_format((float) $vehicle['base_daily_price'], 2)) ?> <?= htmlspecialchars($resolvedAgency['currency']) ?></b>
                                            <small>/Day</small>
                                        </h4>
                                    </div>
                                    <div class="row gy-2 gx-0 text-center mb-4">
                                        <div class="col-4 border-end border-white">
                                            <i class="fas fa-users text-dark"></i>
                                            <span class="text-body ms-1"><?= (int) $vehicle['seats'] ?> Seats</span>
                                        </div>
                                        <div class="col-4 border-end border-white">
                                            <?php if (strtolower((string) $vehicle['transmission']) === 'automatic'): ?>
                                                <i class="fas fa-tachometer-alt text-dark"></i>
                                                <span class="text-body ms-1">AUTO</span>
                                            <?php else: ?>
                                                <i class="fas fa-hand-paper text-dark"></i>
                                                <span class="text-body ms-1">Manual</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-4">
                                            <i class="fas fa-gas-pump text-dark"></i>
                                            <span class="text-body ms-1"><?= htmlspecialchars(ucfirst((string) ($vehicle['fuel'] ?? ''))) ?></span>
                                        </div>
                                        <div class="col-4 border-end border-white mt-2">
                                            <i class="fas fa-door-closed text-dark"></i>
                                            <span class="text-body ms-1"><?= (int) $vehicle['doors'] ?> Doors</span>
                                        </div>
                                        <div class="col-4 border-end border-white mt-2">
                                            <i class="fas fa-cogs text-dark"></i>
                                            <span class="text-body ms-1"><?= htmlspecialchars($vehicle['category_name'] ?? '') ?></span>
                                        </div>
                                        <div class="col-4 mt-2">
                                            <i class="fas fa-suitcase text-dark"></i>
                                            <span class="text-body ms-1"><?= (int) $vehicle['luggage_capacity'] ?> Bags</span>
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
                <?php else: ?>
                <?php foreach ($cars as $car): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="categories-item p-4">
                            <div class="categories-item-inner">
                                <div class="categories-img rounded-top">
                                    <img src="img/<?= $car['image'] ?>"
                                        class="img-fluid w-100 rounded-top"
                                        alt="<?= htmlspecialchars($car['name']) ?>">
                                </div>
                                <div class="categories-content rounded-bottom p-4">
                                    <h4><?= htmlspecialchars($car['name']) ?></h4>
                                    <div class="mb-4">
                                        <h4 class="bg-white text-secondary rounded-pill py-2 px-4 mb-0">
                                            <b><?= htmlspecialchars($car['price']) ?>MAD</b>
                                            <small>/Day</small>
                                        </h4>
                                    </div>
                                    <div class="row gy-2 gx-0 text-center mb-4">
                                        <div class="col-4 border-end border-white">
                                            <i class="fas fa-users text-dark"></i>
                                            <span class="text-body ms-1"><?= $car['seat'] ?> Seats</span>
                                        </div>
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
                                        <div class="col-4 border-end border-white mt-2">
                                            <i class="fas fa-door-closed text-dark"></i>
                                            <span class="text-body ms-1"><?= $car['door'] ?> Doors</span>
                                        </div>
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
                <?php endif; ?>
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
