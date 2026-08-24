<?php

namespace App\Domain;

use DateTimeImmutable;

final class Reservation
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $departureCity,
        public readonly string $arrivalCity,
        public readonly DateTimeImmutable $startsAt,
        public readonly DateTimeImmutable $endsAt,
        public readonly int $carId,
        public readonly int $userId,
        public readonly ?bool $confirmed
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['idres']) ? (int) $row['idres'] : null,
            departureCity: (string) ($row['depart'] ?? ''),
            arrivalCity: (string) ($row['arrive'] ?? ''),
            startsAt: new DateTimeImmutable($row['Date_debut'] . ' ' . $row['heureDebut']),
            endsAt: new DateTimeImmutable($row['Date_fin'] . ' ' . $row['heureFin']),
            carId: (int) $row['idcar'],
            userId: (int) $row['iduser'],
            confirmed: $row['confirm'] === null ? null : (bool) $row['confirm']
        );
    }
}
