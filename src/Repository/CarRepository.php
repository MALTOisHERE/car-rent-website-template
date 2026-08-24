<?php

namespace App\Repository;

use App\Domain\Car;
use PDO;

final class CarRepository
{
    private const COLUMNS = 'idcar, name, door, bag, seat, price, type, image';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return Car[] */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT ' . self::COLUMNS . ' FROM car');

        return array_map(
            static fn (array $row): Car => Car::fromRow($row),
            $statement->fetchAll()
        );
    }

    public function find(int $id): ?Car
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM car WHERE idcar = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : Car::fromRow($row);
    }

    /**
     * @param array{name: string, door: int, bag: int, seat: int, price: int, type: int, image: string} $data
     */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO car (name, door, bag, seat, price, type, image)
             VALUES (:name, :door, :bag, :seat, :price, :type, :image)'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM car WHERE idcar = :id');
        $statement->execute(['id' => $id]);
    }
}
