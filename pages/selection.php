<?php

use App\I18n\Translator;
use App\Repository\CarRepository;
use App\Service\BookingService;
use App\Repository\ReservationRepository;

require __DIR__ . '/../assets/connectDB.php';

$active = 'cars';
$t = Translator::forLocale($_SESSION['lang'] ?? 'en');

try {
    $cars = (new CarRepository($mysqlconnection))->all();
} catch (Throwable $e) {
    die(reportDatabaseError($e, 'Loading vehicle availability failed'));
}

$booking = new BookingService(new ReservationRepository($mysqlconnection));

$depart = $_GET['depart'] ?? '';
$arrive = $_GET['arrive'] ?? '';
$dateDebut = $_GET['Date_debut'] ?? '';
$heureDebut = $_GET['heureDebut'] ?? '';
$dateFin = $_GET['Date_fin'] ?? '';
$heureFin = $_GET['heureFin'] ?? '';

$hasSearchWindow = $dateDebut !== '' && $heureDebut !== '' && $dateFin !== '' && $heureFin !== '';
$searchStart = null;
$searchEnd = null;
if ($hasSearchWindow) {
    try {
        $searchStart = new DateTimeImmutable("$dateDebut $heureDebut");
        $searchEnd = new DateTimeImmutable("$dateFin $heureFin");
    } catch (Throwable $e) {
        $hasSearchWindow = false;
    }
}

$bookNowParams = http_build_query([
    'depart' => $depart,
    'arrive' => $arrive,
    'Date_debut' => $dateDebut,
    'heureDebut' => $heureDebut,
    'Date_fin' => $dateFin,
    'heureFin' => $heureFin,
]);

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
                <?php
                $available = true;
                if ($hasSearchWindow) {
                    try {
                        $available = $booking->isAvailable($car->id, $searchStart, $searchEnd);
                    } catch (Throwable $e) {
                        $available = true;
                    }
                }
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="categories-item p-4">
                        <div class="categories-item-inner">
                            <div class="categories-img rounded-top">
                                <img src="../img/<?= htmlspecialchars($car->image) ?>" class="img-fluid w-100 rounded-top"
                                    alt="<?= htmlspecialchars($car->name) ?>">
                            </div>
                            <div class="categories-content rounded-bottom p-4">
                                <h4><?= htmlspecialchars($car->name) ?></h4>
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
                                        <b>$<?= htmlspecialchars((string) $car->pricePerDay) ?></b>
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
                                <?php if ($available): ?>
                                    <a href="process_booking.php?idcar=<?= $car->id ?>&<?= $bookNowParams ?>"
                                        class="btn btn-primary rounded-pill d-flex justify-content-center py-3">
                                        <?= htmlspecialchars($t->t('booking.book_now')) ?>
                                    </a>
                                <?php else: ?>
                                    <a href="#" class="btn btn-primary rounded-pill d-flex justify-content-center py-3 disabled-style"
                                        onclick="alert('<?= htmlspecialchars(addslashes($t->t('booking.unavailable')), ENT_QUOTES) ?>'); return false;">
                                        <?= htmlspecialchars($t->t('booking.book_now')) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<!-- Car categories End -->
<style>
    .disabled-style {
        pointer-events: auto;
        opacity: 0.6;
        cursor: not-allowed;
    }
</style>
<?php
require __DIR__ . '/templates/footer.php';
