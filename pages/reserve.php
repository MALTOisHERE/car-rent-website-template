<?php

use App\I18n\Translator;

require __DIR__ . '/../bootstrap.php';

$t = Translator::forLocale($_SESSION['lang'] ?? 'en');
$active = 'cars';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$idcar = $_GET['idcar'] ?? '';
$depart = $_GET['depart'] ?? '';
$arrive = $_GET['arrive'] ?? '';
$dateDebut = $_GET['Date_debut'] ?? '';
$heureDebut = $_GET['heureDebut'] ?? '';
$dateFin = $_GET['Date_fin'] ?? '';
$heureFin = $_GET['heureFin'] ?? '';

require __DIR__ . '/templates/header.php';
?>

<!-- Header Start -->
<div class="container-fluid bg-breadcrumb">
    <div class="container text-center py-5" style="max-width: 900px;">
        <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s"><?= htmlspecialchars($t->t('reserve.heading')) ?></h4>
        <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
            <li class="breadcrumb-item"><a href="index.php"><?= htmlspecialchars($t->t('nav.home')) ?></a></li>
            <li class="breadcrumb-item"><a href="#"><?= htmlspecialchars($t->t('breadcrumb.pages')) ?></a></li>
            <li style="text-decoration: underline;" class="breadcrumb-item active text-white"><?= htmlspecialchars($t->t('reserve.heading')) ?></li>
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
                    <h4 class="text-white mb-4"><?= htmlspecialchars($t->t('reserve.your_info')) ?></h4>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
                    <?php endif; ?>
                    <form action="confirm_reservation.php" method="POST" onsubmit="return validatePassword()">
                        <input type="hidden" name="idcar" value="<?= htmlspecialchars($idcar) ?>">
                        <input type="hidden" name="depart" value="<?= htmlspecialchars($depart) ?>">
                        <input type="hidden" name="arrive" value="<?= htmlspecialchars($arrive) ?>">
                        <input type="hidden" name="Date_debut" value="<?= htmlspecialchars($dateDebut) ?>">
                        <input type="hidden" name="heureDebut" value="<?= htmlspecialchars($heureDebut) ?>">
                        <input type="hidden" name="Date_fin" value="<?= htmlspecialchars($dateFin) ?>">
                        <input type="hidden" name="heureFin" value="<?= htmlspecialchars($heureFin) ?>">

                        <div class="row g-4">
                            <div class="col-lg-12 col-xl-12">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="fullname" name="fullname" placeholder="<?= htmlspecialchars($t->t('reserve.fullname')) ?>" required>
                                    <label for="fullname"><?= htmlspecialchars($t->t('reserve.fullname')) ?></label>
                                </div>
                            </div>
                            <div class="col-lg-12 col-xl-12">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" name="email" placeholder="<?= htmlspecialchars($t->t('auth.email')) ?>" required>
                                    <label for="email"><?= htmlspecialchars($t->t('auth.email')) ?></label>
                                </div>
                            </div>
                            <div class="col-lg-12 col-xl-12">
                                <div class="form-floating">
                                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="<?= htmlspecialchars($t->t('reserve.phone')) ?>">
                                    <label for="phone"><?= htmlspecialchars($t->t('reserve.phone')) ?></label>
                                </div>
                            </div>
                            <div class="col-lg-12 col-xl-6">
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="<?= htmlspecialchars($t->t('auth.password')) ?>" required>
                                    <label for="password"><?= htmlspecialchars($t->t('auth.password')) ?></label>
                                </div>
                            </div>
                            <div class="col-lg-12 col-xl-6">
                                <div class="form-floating">
                                    <input type="password" class="form-control" id="confirm_password" placeholder="<?= htmlspecialchars($t->t('reserve.confirm_password')) ?>" required>
                                    <label for="confirm_password"><?= htmlspecialchars($t->t('reserve.confirm_password')) ?></label>
                                </div>
                                <small id="password_error" style="color: red; display: none;"><?= htmlspecialchars($t->t('reserve.password_mismatch')) ?></small>
                            </div>
                            <div class="col-lg-12 col-xl-4">
                                <button type="submit" class="btn btn-light w-100 py-3"><?= htmlspecialchars($t->t('reserve.submit')) ?></button>
                            </div>
                        </div>
                    </form>

                    <script>
                        function validatePassword() {
                            var password = document.getElementById("password").value;
                            var confirmPassword = document.getElementById("confirm_password").value;
                            var errorText = document.getElementById("password_error");

                            if (password !== confirmPassword) {
                                errorText.style.display = "block";
                                return false;
                            }
                            errorText.style.display = "none";
                            return true;
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Contact End -->
<?php
require __DIR__ . '/templates/footer.php';
