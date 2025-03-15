<?php 

$active = "2";

include("admin_header.php");

// Check if the user is logged in and has the admin role (role != 0)
if (!isset($_SESSION['role']) || $_SESSION['role'] == 0) {
    // Redirect to login page or show an error
    header('Location: ../index.php');
    exit();
}
?>

<?php
// Include the database connection file
include("../assets/connectDB.php");

try {
    // Retrieve data from the reservation table with joins
    $sql = "
        SELECT 
            reservation.idres, 
            reservation.depart, 
            reservation.arrive, 
            reservation.Date_debut, 
            reservation.Date_fin, 
            reservation.heureDebut, 
            reservation.heureFin,
            reservation.confirm, 
            user.name AS user_name, 
            user.email AS user_email,
            user.phone AS user_phone, 
            car.name AS car_name, 
            car.price AS car_price
        FROM 
            reservation
        JOIN 
            user ON reservation.iduser = user.iduser
        JOIN 
            car ON reservation.idcar = car.idcar
    ";
    $stmt = $mysqlconnection->prepare($sql);
    $stmt->execute();
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error retrieving data: " . $e->getMessage());
}

?>

</section>
<section class="content">
    <div class="container-fluid">
        <!-- Hover Rows -->
        <div class="row clearfix">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <div class="card">
                    <div class="header">
                        <h2>
                            Reservation
                            <small>Reservation CARS</small>
                        </h2>
                        <ul class="header-dropdown m-r--5">
                            <li class="dropdown">
                                <a href="javascript:void(0);" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                                    <i class="material-icons">more_vert</i>
                                </a>
                                <ul class="dropdown-menu pull-right">
                                    <li><a href="javascript:void(0);">Action</a></li>
                                    <li><a href="javascript:void(0);">Another action</a></li>
                                    <li><a href="javascript:void(0);">Something else here</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                    <div class="body table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Departure</th>
                                    <th>Arrival</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>U.Name</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>C. Name</th>
                                    <th>C. Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($reservation['idres'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['depart'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= !empty($reservation['arrive']) ? htmlspecialchars($reservation['arrive'], ENT_QUOTES, 'UTF-8') : '-' ?></td>
                                        <td><?= htmlspecialchars($reservation['Date_debut'], ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars(substr($reservation['heureDebut'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['Date_fin'], ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars(substr($reservation['heureFin'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['user_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['user_phone'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['user_email'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['car_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['car_price'], ENT_QUOTES, 'UTF-8') ?> MAD</td>
                                        <style>
                                            .rounded-btn {
                                                border-radius: 50%;
                                                /* Fully rounded */
                                                width: 30px;
                                                /* Adjust size */
                                                height: 30px;
                                                /* Adjust size */
                                                display: flex;
                                                align-items: center;
                                                justify-content: center;
                                                padding: 0;
                                                /* Remove default padding */
                                            }
                                        </style>
                                        <td>
                                            <style>
                                                .rounded-circle {
                                                    width: 30px;
                                                    /* Adjust size */
                                                    height: 30px;
                                                    /* Adjust size */
                                                    padding: 0;
                                                    /* Remove default padding */
                                                }
                                            </style>
                                            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                                            <?php if ($reservation['confirm'] == 1): ?>
                                                <form action="cancel_res.php" method="POST" style="display: inline;">
                                                    <input type="hidden" name="idres" value="<?= htmlspecialchars($reservation['idres'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm rounded-btn" onclick="return confirm('Are you sure you want to cancel this reservation?')">
                                                        <i class="fas fa-times"></i> <!-- Cancel icon -->
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form action="confirm_res.php" method="POST" style="display: inline;">
                                                    <input type="hidden" name="idres" value="<?= htmlspecialchars($reservation['idres'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn-success btn-sm rounded-btn" onclick="return confirm('Are you sure you want to confirm this reservation?')">
                                                        <i class="fas fa-check"></i> <!-- Confirm icon -->
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form action="delete_res.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="idres" value="<?= htmlspecialchars($reservation['idres'], ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="btn btn-danger btn-sm rounded-circle" onclick="return confirm('Are you sure you want to delete this reservation?')">
                                                    <i class="fas fa-trash"></i> <!-- Delete icon -->
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-warning btn-sm rounded-circle" data-toggle="modal" data-target="#editReservationModal<?= $reservation['idres'] ?>">
                                                <i class="fas fa-edit"></i> <!-- Edit icon -->
                                            </button>
                                        </td>
                                        <!-- Modal for Editing Reservation -->
                                        <div class="modal fade" id="editReservationModal<?= $reservation['idres'] ?>" tabindex="-1" role="dialog" aria-labelledby="editReservationModalLabel" aria-hidden="true">
                                            <div class="modal-dialog" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="editReservationModalLabel">Modifier la Réservation</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form action="update_reservation.php" method="POST">
                                                            <input type="hidden" name="idres" value="<?= $reservation['idres'] ?>">
                                                            <div class="form-group">
                                                                <label for="depart">Departure</label>
                                                                <input type="text" class="form-control" id="depart" name="depart" value="<?= $reservation['depart'] ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="arrive">Arrival</label>
                                                                <input type="text" class="form-control" id="arrive" name="arrive" value="<?= $reservation['arrive'] ?>">
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="Date_debut">Start Date</label>
                                                                <input type="date" class="form-control" id="Date_debut" name="Date_debut" value="<?= $reservation['Date_debut'] ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="Date_fin">End Date</label>
                                                                <input type="date" class="form-control" id="Date_fin" name="Date_fin" value="<?= $reservation['Date_fin'] ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="heureDebut">Start Time</label>
                                                                <input type="time" class="form-control" id="heureDebut" name="heureDebut" value="<?= date('H:00', strtotime($reservation['heureDebut'])) ?>" step="3600" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="heureFin">End Time</label>
                                                                <input type="time" class="form-control" id="heureFin" name="heureFin" value="<?= date('H:00', strtotime($reservation['heureFin'])) ?>" step="3600" required>
                                                            </div>
                                                            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- #END# Hover Rows -->
    </div>
</section>
<?php include("script.php") ?>