<?php

use App\I18n\Translator;
use App\Repository\CarRepository;

require __DIR__ . '/../assets/connectDB.php';

$active = 'cars';
$t = Translator::forLocale($_SESSION['lang'] ?? 'en');

try {
    $cars = (new CarRepository($mysqlconnection))->all();
} catch (Throwable $e) {
    die(reportDatabaseError($e, 'Loading cars failed'));
}

require __DIR__ . '/templates/header.php';
?>

<!-- Header Start -->
<div class="container-fluid bg-breadcrumb">
    <div class="container text-center py-5" style="max-width: 900px;">
        <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s"><?= htmlspecialchars($t->t('cars.our_cars')) ?></h4>
        <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
            <li class="breadcrumb-item"><a href="index.php"><?= htmlspecialchars($t->t('nav.home')) ?></a></li>
            <li class="breadcrumb-item"><a href="#"><?= htmlspecialchars($t->t('breadcrumb.pages')) ?></a></li>
            <li style="text-decoration: underline;" class="breadcrumb-item active text-white"><?= htmlspecialchars($t->t('breadcrumb.categories')) ?></li>
        </ol>
    </div>
</div>
<!-- Header End -->

<!-- Car categories Start -->
<div class="container-fluid categories py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize mb-3"><?= htmlspecialchars($t->t('cars.vehicle_categories')) ?></h1>
            <p class="mb-0"><?= htmlspecialchars($t->t('cars.lorem')) ?></p>
        </div>
        <div class="row g-4 wow fadeInUp" data-wow-delay="0.1s">
            <?php foreach ($cars as $car): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="categories-item p-4">
                        <div class="categories-item-inner">
                            <div class="categories-img rounded-top">
                                <img src="../img/<?= htmlspecialchars($car->image) ?>" class="img-fluid w-100 rounded-top"
                                    alt="<?= htmlspecialchars($car->name) ?>">
                            </div>
                            <div class="categories-content rounded-bottom p-4">
                                <h4><?= htmlspecialchars($car->name) ?></h4>
                                <div class="mb-4">
                                    <h4 class="bg-white text-secondary rounded-pill py-2 px-4 mb-0">
                                        <b><?= htmlspecialchars((string) $car->pricePerDay) ?>MAD</b>
                                        <small><?= htmlspecialchars($t->t('booking.per_day')) ?></small>
                                    </h4>
                                </div>
                                <div class="row gy-2 gx-0 text-center mb-4">
                                    <div class="col-4 border-end border-white">
                                        <i class="fas fa-users text-dark"></i>
                                        <span class="text-body ms-1"><?= htmlspecialchars($t->t('booking.seats', ['count' => $car->seats])) ?></span>
                                    </div>
                                    <div class="col-4 border-end border-white">
                                        <?php if ($car->transmission === \App\Domain\Transmission::Automatic): ?>
                                            <i class="fas fa-tachometer-alt text-dark"></i>
                                            <span class="text-body ms-1"><?= htmlspecialchars($t->t('booking.transmission_auto')) ?></span>
                                        <?php else: ?>
                                            <i class="fas fa-hand-paper text-dark"></i>
                                            <span class="text-body ms-1"><?= htmlspecialchars($t->t('booking.transmission_manual')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-4">
                                        <i class="fas fa-gas-pump text-dark"></i>
                                        <span class="text-body ms-1"><?= htmlspecialchars($t->t('booking.petrol')) ?></span>
                                    </div>
                                    <div class="col-4 border-end border-white mt-2">
                                        <i class="fas fa-door-closed text-dark"></i>
                                        <span class="text-body ms-1"><?= htmlspecialchars($t->t('booking.doors', ['count' => $car->doors])) ?></span>
                                    </div>
                                    <div class="col-4 border-end border-white mt-2">
                                        <i class="fas fa-cogs text-dark"></i>
                                        <span class="text-body ms-1"><?= htmlspecialchars($t->t('booking.gear_6speed')) ?></span>
                                    </div>
                                    <div class="col-4 mt-2">
                                        <i class="fas fa-suitcase text-dark"></i>
                                        <span class="text-body ms-1"><?= htmlspecialchars($t->t('booking.bags', ['count' => $car->bags])) ?></span>
                                    </div>
                                </div>
                                <a href="index.php" class="btn btn-primary rounded-pill d-flex justify-content-center py-3">
                                    <?= htmlspecialchars($t->t('booking.book_now')) ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<!-- Car categories End -->

<!-- Car Steps Start -->
<div class="container-fluid steps py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize text-white mb-3"><?= htmlspecialchars($t->t('cars.process_heading')) ?></h1>
            <p class="mb-0 text-white"><?= htmlspecialchars($t->t('cars.process_lead')) ?></p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.1s">
                <div class="steps-item p-4 mb-4">
                    <h4><?= htmlspecialchars($t->t('cars.step1_title')) ?></h4>
                    <p class="mb-0"><?= htmlspecialchars($t->t('cars.step_lorem')) ?></p>
                    <div class="setps-number">01.</div>
                </div>
            </div>
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.3s">
                <div class="steps-item p-4 mb-4">
                    <h4><?= htmlspecialchars($t->t('cars.step2_title')) ?></h4>
                    <p class="mb-0"><?= htmlspecialchars($t->t('cars.step_lorem')) ?></p>
                    <div class="setps-number">02.</div>
                </div>
            </div>
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.5s">
                <div class="steps-item p-4 mb-4">
                    <h4><?= htmlspecialchars($t->t('cars.step3_title')) ?></h4>
                    <p class="mb-0"><?= htmlspecialchars($t->t('cars.step_lorem')) ?></p>
                    <div class="setps-number">03.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Car Steps End -->
<?php
require __DIR__ . '/templates/footer.php';
