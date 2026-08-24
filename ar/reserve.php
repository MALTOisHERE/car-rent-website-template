<?php
// Récupérer les variables depuis l'URL
$idcar = $_GET['idcar'] ?? '';
$depart = $_GET['depart'] ?? '';
$arrive = $_GET['arrive'] ?? '';
$dateDebut = $_GET['Date_debut'] ?? '';
$heureDebut = $_GET['heureDebut'] ?? '';
$dateFin = $_GET['Date_fin'] ?? '';
$heureFin = $_GET['heureFin'] ?? '';

include("header_p.php");
?>

<!-- Header Start -->
<div class="container-fluid bg-breadcrumb">
    <div class="container text-center py-5" style="max-width: 900px;">
        <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s">Reserve</h4>
        <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="#">Pages</a></li>
            <li class="breadcrumb-item active text-primary">Reserve</li>
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
                    <h4 class="text-primary mb-4">Your Info</h4>
                    <form action="confirm_reservation.php" method="POST">
                        <!-- Champs cachés pour les informations de réservation -->
                        <input type="hidden" name="idcar" value="<?= htmlspecialchars($idcar) ?>">
                        <input type="hidden" name="depart" value="<?= htmlspecialchars($depart) ?>">
                        <input type="hidden" name="arrive" value="<?= htmlspecialchars($arrive) ?>">
                        <input type="hidden" name="Date_debut" value="<?= htmlspecialchars($dateDebut) ?>">
                        <input type="hidden" name="heureDebut" value="<?= htmlspecialchars($heureDebut) ?>">
                        <input type="hidden" name="Date_fin" value="<?= htmlspecialchars($dateFin) ?>">
                        <input type="hidden" name="heureFin" value="<?= htmlspecialchars($heureFin) ?>">

                        <!-- Champs du formulaire utilisateur -->
                        <div class="row g-4">
                            <div class="col-lg-12 col-xl-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="name" name="name"
                                        placeholder="Your Name" required>
                                    <label for="name">Your Name</label>
                                </div>
                            </div>
                            <div class="col-lg-12 col-xl-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="fullname" name="fullname"
                                        placeholder="fullname" required>
                                    <label for="fullname">Fullname</label>
                                </div>
                            </div>
                            <div class="col-lg-12 col-xl-6">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" name="email"
                                        placeholder="email" required>
                                    <label for="email">E-mail</label>
                                </div>
                            </div>
                            <div class="col-lg-12 col-xl-6">
                                <div class="form-floating">
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                        placeholder="phone">
                                    <label for="phone">Phone</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="password" name="password"
                                        placeholder="password">
                                    <label for="password">Password</label>
                                </div>
                            </div>
                            <div class="col-2">
                                <button type="submit" class="btn btn-light w-100 py-3">Send Message</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
<!-- Contact End -->
<?php
include("footer_p.php") ?>