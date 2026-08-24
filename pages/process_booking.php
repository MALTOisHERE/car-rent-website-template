<?php

use App\I18n\Translator;
use App\Repository\ReservationRepository;
use App\Service\BookingService;
use App\Service\Exception\CarNotAvailableException;

require __DIR__ . '/../assets/connectDB.php';

$t = Translator::forLocale($_SESSION['lang'] ?? 'en');

$requiredParams = ['idcar', 'depart', 'Date_debut', 'heureDebut', 'Date_fin', 'heureFin'];
foreach ($requiredParams as $param) {
    if (!isset($_GET[$param]) || $_GET[$param] === '') {
        header('Location: selection.php?message=' . urlencode('Missing required parameter: ' . $param));
        exit();
    }
}

$idcar = (int) $_GET['idcar'];
$depart = $_GET['depart'];
$arrive = $_GET['arrive'] ?? '';
$dateDebut = $_GET['Date_debut'];
$heureDebut = $_GET['heureDebut'];
$dateFin = $_GET['Date_fin'];
$heureFin = $_GET['heureFin'];

$searchParams = http_build_query([
    'depart' => $depart,
    'arrive' => $arrive,
    'Date_debut' => $dateDebut,
    'heureDebut' => $heureDebut,
    'Date_fin' => $dateFin,
    'heureFin' => $heureFin,
]);

if (!isset($_SESSION['user_id'])) {
    header('Location: reserve.php?idcar=' . $idcar . '&' . $searchParams);
    exit();
}

try {
    $start = new DateTimeImmutable("$dateDebut $heureDebut");
    $end = new DateTimeImmutable("$dateFin $heureFin");
} catch (Throwable $e) {
    header('Location: selection.php?' . $searchParams . '&message=' . urlencode('Invalid dates.'));
    exit();
}

$booking = new BookingService(new ReservationRepository($mysqlconnection));

try {
    $reservationId = $booking->book($idcar, (int) $_SESSION['user_id'], $depart, $arrive, $start, $end);
    header('Location: index.php?message=' . urlencode($t->t('reserve.success', ['id' => $reservationId])));
    exit();
} catch (CarNotAvailableException $e) {
    header('Location: selection.php?' . $searchParams . '&message=' . urlencode($t->t('booking.unavailable')));
    exit();
} catch (Throwable $e) {
    $message = reportDatabaseError($e, 'Creating a reservation failed');
    header('Location: index.php?message=' . urlencode($message));
    exit();
}
