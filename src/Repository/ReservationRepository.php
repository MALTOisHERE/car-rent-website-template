<?php

namespace App\Repository;

use App\Domain\Reservation;
use DateTimeInterface;
use PDO;

final class ReservationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * True if a *confirmed* reservation on this car overlaps the given window.
     * Mirrors the overlap query previously duplicated in selection.php and
     * process_booking.php.
     */
    public function hasOverlap(int $carId, DateTimeInterface $start, DateTimeInterface $end): bool
    {
        $sql = "SELECT COUNT(*) FROM reservation
                WHERE idcar = :idcar
                AND confirm = 1
                AND (
                    STR_TO_DATE(CONCAT(Date_debut, ' ', heureDebut), '%Y-%m-%d %H:%i') < :endsAt
                    AND
                    STR_TO_DATE(CONCAT(Date_fin, ' ', heureFin), '%Y-%m-%d %H:%i') > :startsAt
                )";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'idcar' => $carId,
            'endsAt' => $end->format('Y-m-d H:i'),
            'startsAt' => $start->format('Y-m-d H:i'),
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * @param array{depart: string, arrive: string, heureDebut: string, heureFin: string, dateDebut: string, dateFin: string, idcar: int, iduser: int} $data
     */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO reservation (depart, arrive, heureDebut, heureFin, Date_debut, Date_fin, idcar, iduser, confirm)
             VALUES (:depart, :arrive, :heureDebut, :heureFin, :dateDebut, :dateFin, :idcar, :iduser, 0)'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function find(int $id): ?Reservation
    {
        $statement = $this->pdo->prepare('SELECT * FROM reservation WHERE idres = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : Reservation::fromRow($row);
    }
}
