<?php

namespace App\Domain;

final class Car
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly int $doors,
        public readonly int $bags,
        public readonly int $seats,
        public readonly int $pricePerDay,
        public readonly Transmission $transmission,
        public readonly string $image
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: isset($row['idcar']) ? (int) $row['idcar'] : null,
            name: (string) $row['name'],
            doors: (int) $row['door'],
            bags: (int) $row['bag'],
            seats: (int) $row['seat'],
            pricePerDay: (int) $row['price'],
            transmission: Transmission::from((int) $row['type']),
            image: ($row['image'] ?? '') !== '' ? (string) $row['image'] : 'default.png'
        );
    }
}
