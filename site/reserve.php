<?php

// Check if the user is logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to index.php if the user is logged in
    header("Location: index.php");
    exit(); // Ensure no further code is executed
}

// Retrieve variables from the URL
$idcar = $_GET['idcar'] ?? '';
$depart = $_GET['depart'] ?? '';
$arrive = $_GET['arrive'] ?? '';
$dateDebut = $_GET['Date_debut'] ?? '';
$heureDebut = $_GET['heureDebut'] ?? '';
$dateFin = $_GET['Date_fin'] ?? '';
$heureFin = $_GET['heureFin'] ?? '';

include("header_p.php"); // Include header
?>

<!-- Header Start -->
<div class="container-fluid bg-breadcrumb">
    <div class="container text-center py-5" style="max-width: 900px;">
        <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s">Reserve</h4>
        <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="#">Pages</a></li>
            <li style="text-decoration: underline;" class="breadcrumb-item active text-white">Reserve</li>
        </ol>
    </div>
</div>
<!-- Header End -->

<!-- Contact Start -->
<div class="container-fluid contact py-5">
    <div class="container py-5">
        <div class="row g-5">
            <div class="col-xl-12 wow fadeInUp" data-wow-delay="0.1s">
                <div class="bg-secondary p-5 rounded">
                    <h4 class="text-white mb-4">Your Info</h4>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
                    <?php endif; ?>
                    <form action="confirm_reservation.php" method="POST" onsubmit="return validatePassword()">
                        <!-- Hidden fields for reservation information -->
                        <input type="hidden" name="idcar" value="<?= htmlspecialchars($idcar) ?>">
                        <input type="hidden" name="depart" value="<?= htmlspecialchars($depart) ?>">
                        <input type="hidden" name="arrive" value="<?= htmlspecialchars($arrive) ?>">
                        <input type="hidden" name="Date_debut" value="<?= htmlspecialchars($dateDebut) ?>">
                        <input type="hidden" name="heureDebut" value="<?= htmlspecialchars($heureDebut) ?>">
                        <input type="hidden" name="Date_fin" value="<?= htmlspecialchars($dateFin) ?>">
                        <input type="hidden" name="heureFin" value="<?= htmlspecialchars($heureFin) ?>">

                        <!-- User input fields -->
                        <div class="row g-4">
                            <div class="col-lg-12 col-xl-12">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="fullname" name="fullname" placeholder="Fullname" required>
                                    <label for="fullname">Fullname</label>
                                </div>
                            </div>
                            <div class="col-lg-12 col-xl-12">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" name="email" placeholder="E-mail" required>
                                    <label for="email">E-mail</label>
                                </div>
                            </div>
                            <div class="col-lg-12 col-xl-12">
                                <div class="form-floating">
                                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone">
                                    <label for="phone">Phone</label>
                                </div>
                            </div>
                            <div class="col-lg-12 col-xl-6">
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                    <label for="password">Password</label>
                                </div>
                            </div>
                            <div class="col-lg-12 col-xl-6">
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="confirm_password" placeholder="Confirm Password" required>
                                    <label for="confirm_password">Confirm Password</label>
                                </div>
                                <small id="password_error" style="color: red; display: none;">Passwords do not match!</small>
                            </div>
                            <div class="col-lg-12 col-xl-4">
                                <button type="submit" class="btn btn-light w-100 py-3">Reserve</button>
                            </div>
                        </div>
                    </form>

                    <script>
                        function validatePassword() {
                            var password = document.getElementById("password").value;
                            var confirmPassword = document.getElementById("confirm_password").value;
                            var errorText = document.getElementById("password_error");

                            if (password !== confirmPassword) {
                                errorText.style.display = "block"; // Show error message
                                return false; // Prevent form submission
                            } else {
                                errorText.style.display = "none"; // Hide error message
                                return true; // Allow form submission
                            }
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Contact End -->
<?php
include("footer_p.php"); // Include footer
?>