<?php

$active = "1";

include("admin_header.php");

// Include the database connection file
include("../assets/connectDB.php");

// Check if the user is logged in and has the admin role (role != 0)
if (!isset($_SESSION['role']) || $_SESSION['role'] == 0) {
    // Redirect to login page or show an error
    header('Location: ../index.php');
    exit();
}

// Fetch car data from the database

try {
    $sql = "SELECT idcar, name, door, bag, seat, price, type, image FROM car";
    $stmt = $mysqlconnection->prepare($sql);
    $stmt->execute();
    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des données : " . $e->getMessage());
}

// Include the admin header

?>

<section class="content">
    <div class="container-fluid">
        <!-- Hover Rows -->
        <div class="row clearfix">
            <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <div class="card">
                    <div class="header">
                        <h2>
                            CARS
                            <small>Confirm CARS</small>
                        </h2>
                        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addCarModal">
                            <i class="material-icons">add</i> Add Car
                        </button>
                    </div>
                    <div class="modal fade" id="addCarModal" tabindex="-1" role="dialog" aria-labelledby="addCarModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="addCarModalLabel">Add a New Car</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form action="add_car.php" method="POST" enctype="multipart/form-data">
                                        <div class="form-group">
                                            <label for="name">Car Name</label>
                                            <input type="text" class="form-control" id="name" name="name" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="door">Doors</label>
                                            <input type="number" class="form-control" id="door" name="door" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="bag">Bags</label>
                                            <input type="number" class="form-control" id="bag" name="bag" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="seat">Seats</label>
                                            <input type="number" class="form-control" id="seat" name="seat" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="price">Price ($)</label>
                                            <input type="number" class="form-control" id="price" name="price" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="type">Type</label>
                                            <select id="type" name="type">
                                                <option value="0">Manual</option>
                                                <option value="1">Automatic</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="car_image">Car Image</label>
                                            <input type="file" class="form-control" id="car_image" name="car_image" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Add Car</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="body table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Doors</th>
                                    <th>Bags</th>
                                    <th>Seats</th>
                                    <th>Price</th>
                                    <th>Type</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cars as $car): ?>
                                    <tr>
                                        <th scope="row"><?= htmlspecialchars($car['idcar']) ?></th>
                                        <td>
                                            <img src="../img/<?= htmlspecialchars($car['image']) ?>" alt="Car Image" width="100">
                                        </td>
                                        <td><?= htmlspecialchars($car['name']) ?></td>
                                        <td><?= htmlspecialchars($car['door']) ?></td>
                                        <td><?= htmlspecialchars($car['bag']) ?></td>
                                        <td><?= htmlspecialchars($car['seat']) ?></td>
                                        <td><?= htmlspecialchars($car['price']) ?>$</td>
                                        <td><?= htmlspecialchars($car['type'] == 0 ? 'Manual' : 'Automatic') ?></td>
                                        <td>
                                            <a href="delete_car.php?id=<?= htmlspecialchars($car['idcar']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this car?');">
                                                Delete
                                            </a>
                                        </td>
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

<?php include("script.php"); ?>