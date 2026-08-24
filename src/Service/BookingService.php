<?php

namespace App\Service;

use App\Repository\ReservationRepository;
use App\Service\Exception\CarNotAvailableException;
use DateTimeImmutable;

/**
 * Single source of truth for availability-checking and reservation
 * creation — previously reimplemented separately in selection.php,
 * process_booking.php and confirm_reservation.php.
 */
final class BookingService
{
    public function __construct(private readonly ReservationRepository $reservations)
    {
    }

    public function isAvailable(int $carId, DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        return !$this->reservations->hasOverlap($carId, $start, $end);
    }

    public function book(
        int $carId,
        int $userId,
        string $departureCity,
        string $arrivalCity,
        DateTimeImmutable $start,
        DateTimeImmutable $end
    ): int {
        if (!$this->isAvailable($carId, $start, $end)) {
            throw new CarNotAvailableException('This car is no longer available for the selected dates.');
        }

        return $this->reservations->create([
            'depart' => $departureCity,
            'arrive' => $arrivalCity,
            'heureDebut' => $start->format('H:i:s'),
            'heureFin' => $end->format('H:i:s'),
            'dateDebut' => $start->format('Y-m-d'),
            'dateFin' => $end->format('Y-m-d'),
            'idcar' => $carId,
            'iduser' => $userId,
        ]);
    }
}
