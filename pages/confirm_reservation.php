<?php

use App\I18n\Translator;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Service\BookingService;
use App\Service\Exception\CarNotAvailableException;
use App\Service\Exception\EmailAlreadyRegisteredException;
use App\Service\RegistrationService;

require __DIR__ . '/../assets/connectDB.php';

$t = Translator::forLocale($_SESSION['lang'] ?? 'en');

$idcar = (int) ($_POST['idcar'] ?? 0);
$depart = $_POST['depart'] ?? '';
$arrive = $_POST['arrive'] ?? '';
$dateDebut = $_POST['Date_debut'] ?? '';
$heureDebut = $_POST['heureDebut'] ?? '';
$dateFin = $_POST['Date_fin'] ?? '';
$heureFin = $_POST['heureFin'] ?? '';
$fullname = trim((string) ($_POST['fullname'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = $_POST['phone'] ?? '';
$password = (string) ($_POST['password'] ?? '');

$reserveParams = http_build_query([
    'idcar' => $idcar,
    'depart' => $depart,
    'arrive' => $arrive,
    'Date_debut' => $dateDebut,
    'heureDebut' => $heureDebut,
    'Date_fin' => $dateFin,
    'heureFin' => $heureFin,
    'fullname' => $fullname,
    'email' => $email,
    'phone' => $phone,
]);

try {
    $start = new DateTimeImmutable("$dateDebut $heureDebut");
    $end = new DateTimeImmutable("$dateFin $heureFin");
} catch (Throwable $e) {
    header('Location: reserve.php?' . $reserveParams . '&error=' . urlencode($t->t('reserve.error_unavailable')));
    exit();
}

$booking = new BookingService(new ReservationRepository($mysqlconnection));
$registration = new RegistrationService(new UserRepository($mysqlconnection));

try {
    // Availability is (re-)checked here, before creating the account, so a
    // sold-out car doesn't leave behind an orphaned guest account.
    if (!$booking->isAvailable($idcar, $start, $end)) {
        throw new CarNotAvailableException('This car is no longer available for the selected dates.');
    }

    $user = $registration->register($fullname, $email, $phone, $password);
    $reservationId = $booking->book($idcar, $user->id, $depart, $arrive, $start, $end);

    header('Location: index.php?message=' . urlencode($t->t('reserve.success', ['id' => $reservationId])));
    exit();
} catch (EmailAlreadyRegisteredException $e) {
    header('Location: reserve.php?' . $reserveParams . '&error=' . urlencode($t->t('reserve.error_email_exists')));
    exit();
} catch (CarNotAvailableException $e) {
    header('Location: reserve.php?' . $reserveParams . '&error=' . urlencode($t->t('reserve.error_unavailable')));
    exit();
} catch (Throwable $e) {
    $message = reportDatabaseError($e, 'Creating a guest reservation failed');
    header('Location: index.php?message=' . urlencode($message));
    exit();
}
