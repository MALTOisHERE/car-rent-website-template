<?php
$active = "index";
include("header_p.php");
$aid = $resolvedAgency['id'] ?? null;
$cHero = agencyPageContent($aid, 'hero', $lang);
$cAbout = agencyPageContent($aid, 'about', $lang);
$cFeatures = agencyPageContent($aid, 'features', $lang);
$cCounters = agencyPageContent($aid, 'counters', $lang);
$cServices = agencyPageContent($aid, 'services', $lang);
$cSteps = agencyPageContent($aid, 'steps', $lang);
$cTeam = agencyPageContent($aid, 'team', $lang);
$cTestimonials = agencyPageContent($aid, 'testimonials', $lang);
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<!-- Custom CSS for SweetAlert2 Button -->
<style>
    .swal2-confirm {
        background-color: <?= e($brandColors['primary']) ?> !important;
        border-color: <?= e($brandColors['primary']) ?> !important;
    }

    .swal2-confirm:hover {
        background-color: <?= e($brandColors['dark']) ?> !important;
        border-color: <?= e($brandColors['dark']) ?> !important;
    }
</style>
<!-- Include SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- PHP to Check for Message -->
<?php
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
    echo "<script>
        Swal.fire({
            title: 'Notification',
            text: '$message',
            icon: 'success',
            confirmButtonText: 'OK',
            customClass: {
                confirmButton: 'swal2-confirm'
            }
        });
    </script>";
}
?>
<!-- Carousel Start -->
<div class="header-carousel">
    <div id="carouselId" class="carousel slide" data-bs-ride="carousel" data-bs-interval="false">
        <div class="carousel-inner" role="listbox">
            <div class="carousel-item active">
                <img src="img/back4.webp" class="img-fluid w-100" alt="First slide" />
                <div class="carousel-caption">
                    <div class="container py-4">
                        <div class="row g-5">
                            <div class="col-lg-6 fadeInLeft animated" data-animation="fadeInLeft" data-delay="1s"
                                style="animation-delay: 1s;">
                                <div style="background-color: <?= e($brandColors['primary']) ?>;" class=" rounded p-5">
                                    <h4 class="text-white mb-4">CONTINUE CAR RESERVATION</h4>
                                    <form method="GET" action="selection.php">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="input-group">
                                                    <div class="d-flex align-items-center bg-light text-body rounded-start p-2">
                                                        <span class="fas fa-map-marker-alt"></span> <span class="ms-1">Pick Up</span>
                                                    </div>
                                                    <select class="form-control" name="depart" required>
                                                        <option value="" disabled selected>Select Pickup City</option>
                                                        <option value="Casablanca">Casablanca</option>
                                                        <option value="Rabat">Rabat</option>
                                                        <option value="Marrakech">Marrakech</option>
                                                        <option value="Fes">Fes</option>
                                                        <option value="Tangier">Tangier</option>
                                                        <option value="Agadir">Agadir</option>
                                                        <option value="Meknes">Meknes</option>
                                                        <option value="Oujda">Oujda</option>
                                                        <option value="Kenitra">Kenitra</option>
                                                        <option value="Tetouan">Tetouan</option>
                                                        <option value="Safi">Safi</option>
                                                        <option value="El Jadida">El Jadida</option>
                                                        <option value="Nador">Nador</option>
                                                        <option value="Beni Mellal">Beni Mellal</option>
                                                        <option value="Taza">Taza</option>
                                                        <option value="Khouribga">Khouribga</option>
                                                        <option value="Al Hoceima">Al Hoceima</option>
                                                        <option value="Settat">Settat</option>
                                                        <option value="Mohammedia">Mohammedia</option>
                                                        <option value="Larache">Larache</option>
                                                        <option value="Khemisset">Khemisset</option>
                                                        <option value="Errachidia">Errachidia</option>
                                                        <option value="Taroudant">Taroudant</option>
                                                        <option value="Ouarzazate">Ouarzazate</option>
                                                        <option value="Sidi Kacem">Sidi Kacem</option>
                                                        <option value="Tiznit">Tiznit</option>
                                                        <option value="Guelmim">Guelmim</option>
                                                        <option value="Dakhla">Dakhla</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <a href="javascript:void(0);" id="toggleDropOff" class="text-start text-white d-block mb-2">Need a different drop-off location?</a>
                                                <div class="input-group" id="dropOffInputGroup" style="display: none;">
                                                    <div class="d-flex align-items-center bg-light text-body rounded-start p-2">
                                                        <span class="fas fa-map-marker-alt"></span>
                                                        <span class="ms-1">Drop off</span>
                                                    </div>
                                                    <select class="form-control" name="arrive">
                                                        <option value="" disabled selected>Select Drop-off City</option>
                                                        <option value="Casablanca">Casablanca</option>
                                                        <option value="Rabat">Rabat</option>
                                                        <option value="Marrakech">Marrakech</option>
                                                        <option value="Fes">Fes</option>
                                                        <option value="Tangier">Tangier</option>
                                                        <option value="Agadir">Agadir</option>
                                                        <option value="Meknes">Meknes</option>
                                                        <option value="Oujda">Oujda</option>
                                                        <option value="Kenitra">Kenitra</option>
                                                        <option value="Tetouan">Tetouan</option>
                                                        <option value="Safi">Safi</option>
                                                        <option value="El Jadida">El Jadida</option>
                                                        <option value="Nador">Nador</option>
                                                        <option value="Beni Mellal">Beni Mellal</option>
                                                        <option value="Taza">Taza</option>
                                                        <option value="Khouribga">Khouribga</option>
                                                        <option value="Al Hoceima">Al Hoceima</option>
                                                        <option value="Settat">Settat</option>
                                                        <option value="Mohammedia">Mohammedia</option>
                                                        <option value="Larache">Larache</option>
                                                        <option value="Khemisset">Khemisset</option>
                                                        <option value="Errachidia">Errachidia</option>
                                                        <option value="Taroudant">Taroudant</option>
                                                        <option value="Ouarzazate">Ouarzazate</option>
                                                        <option value="Sidi Kacem">Sidi Kacem</option>
                                                        <option value="Tiznit">Tiznit</option>
                                                        <option value="Guelmim">Guelmim</option>
                                                        <option value="Dakhla">Dakhla</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <script>
                                                document.getElementById('toggleDropOff').addEventListener('click', function(event) {
                                                    event.preventDefault();
                                                    var inputGroup = document.getElementById('dropOffInputGroup');
                                                    if (inputGroup.style.display === 'none') {
                                                        inputGroup.style.display = 'flex';
                                                    } else {
                                                        inputGroup.style.display = 'none';
                                                    }
                                                });
                                            </script>
                                            <div class="col-12">
                                                <div class="input-group">
                                                    <div class="d-flex align-items-center bg-light text-body rounded-start p-2">
                                                        <span class="fas fa-calendar-alt"></span><span class="ms-1">Pick Up</span>
                                                    </div>
                                                    <input class="form-control" type="date" name="Date_debut" required>
                                                    <select class="form-select ms-3" name="heureDebut" required>
                                                        <option value="00:00:00">00:00</option>
                                                        <option value="01:00:00">01:00</option>
                                                        <option value="02:00:00">02:00</option>
                                                        <option value="03:00:00">03:00</option>
                                                        <option value="04:00:00">04:00</option>
                                                        <option value="05:00:00">05:00</option>
                                                        <option value="06:00:00">06:00</option>
                                                        <option value="07:00:00">07:00</option>
                                                        <option value="08:00:00">08:00</option>
                                                        <option value="09:00:00">09:00</option>
                                                        <option value="10:00:00">10:00</option>
                                                        <option value="11:00:00">11:00</option>
                                                        <option value="12:00:00">12:00</option>
                                                        <option value="13:00:00">13:00</option>
                                                        <option value="14:00:00">14:00</option>
                                                        <option value="15:00:00">15:00</option>
                                                        <option value="16:00:00">16:00</option>
                                                        <option value="17:00:00">17:00</option>
                                                        <option value="18:00:00">18:00</option>
                                                        <option value="19:00:00">19:00</option>
                                                        <option value="20:00:00">20:00</option>
                                                        <option value="21:00:00">21:00</option>
                                                        <option value="22:00:00">22:00</option>
                                                        <option value="23:00:00">23:00</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="input-group">
                                                    <div class="d-flex align-items-center bg-light text-body rounded-start p-2">
                                                        <span class="fas fa-calendar-alt"></span><span class="ms-1">Drop off</span>
                                                    </div>
                                                    <input class="form-control" type="date" name="Date_fin" required>
                                                    <select class="form-select ms-3" name="heureFin" required>
                                                        <option value="00:00:00">00:00</option>
                                                        <option value="01:00:00">01:00</option>
                                                        <option value="02:00:00">02:00</option>
                                                        <option value="03:00:00">03:00</option>
                                                        <option value="04:00:00">04:00</option>
                                                        <option value="05:00:00">05:00</option>
                                                        <option value="06:00:00">06:00</option>
                                                        <option value="07:00:00">07:00</option>
                                                        <option value="08:00:00">08:00</option>
                                                        <option value="09:00:00">09:00</option>
                                                        <option value="10:00:00">10:00</option>
                                                        <option value="11:00:00">11:00</option>
                                                        <option value="12:00:00">12:00</option>
                                                        <option value="13:00:00">13:00</option>
                                                        <option value="14:00:00">14:00</option>
                                                        <option value="15:00:00">15:00</option>
                                                        <option value="16:00:00">16:00</option>
                                                        <option value="17:00:00">17:00</option>
                                                        <option value="18:00:00">18:00</option>
                                                        <option value="19:00:00">19:00</option>
                                                        <option value="20:00:00">20:00</option>
                                                        <option value="21:00:00">21:00</option>
                                                        <option value="22:00:00">22:00</option>
                                                        <option value="23:00:00">23:00</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" class="btn btn-light w-100 py-2">Book Now</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-lg-6 d-none d-lg-flex fadeInRight animated" data-animation="fadeInRight"
                                data-delay="1s" style="animation-delay: 1s;">
                                <div class="text-start">
                                    <h1 class="display-5 text-white"><?= e(pc($cHero, 'headline', t('public.content.hero.headline'))) ?></h1>
                                    <p><?= e(pc($cHero, 'subtext', t('public.content.hero.subtext'))) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Date Validation Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const pickUpDateInput = document.querySelector('input[name="Date_debut"]');
        const dropOffDateInput = document.querySelector('input[name="Date_fin"]');

        form.addEventListener('submit', function(event) {
            const pickUpDate = new Date(pickUpDateInput.value);
            const dropOffDate = new Date(dropOffDateInput.value);

            if (pickUpDate > dropOffDate) {
                event.preventDefault();
                alert('Error: Pick Up Date must be before Drop Off Date.');
            }
        });
    });
</script>
<!-- Carousel End -->

<!-- Features Start -->
<div class="container-fluid feature py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 style="color:<?= e($brandColors['primary']) ?>;" class="display-5 text-capitalize mb-3"><?= e(pc($cFeatures, 'title', t('public.content.features.title'))) ?></h1>
            <p class="mb-0"><?= e(pc($cFeatures, 'intro', t('public.content.features.intro'))) ?></p>
        </div>
        <div class="row g-4 align-items-center">
            <div class="col-xl-4">
                <div class="row gy-4 gx-0">
                    <div class="col-12 wow fadeInUp" data-wow-delay="0.1s">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <span class="fa fa-trophy fa-2x"></span>
                            </div>
                            <div class="ms-4">
                                <h5 class="mb-3"><?= e(pc($cFeatures, 'item_1_title', t('public.content.features.item_1_title'))) ?></h5>
                                <p class="mb-0"><?= e(pc($cFeatures, 'item_1_text', t('public.content.features.item_1_text'))) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 wow fadeInUp" data-wow-delay="0.3s">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <span class="fa fa-road fa-2x"></span>
                            </div>
                            <div class="ms-4">
                                <h5 class="mb-3"><?= e(pc($cFeatures, 'item_2_title', t('public.content.features.item_2_title'))) ?></h5>
                                <p class="mb-0"><?= e(pc($cFeatures, 'item_2_text', t('public.content.features.item_2_text'))) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-12 col-xl-4 wow fadeInUp" data-wow-delay="0.2s">
                <img src="img/public.avif" class="img-fluid w-100" style="object-fit: cover;" alt="Img">
            </div>
            <div class="col-xl-4">
                <div class="row gy-4 gx-0">
                    <div class="col-12 wow fadeInUp" data-wow-delay="0.1s">
                        <div class="feature-item justify-content-end">
                            <div class="text-end me-4">
                                <h5 class="mb-3"><?= e(pc($cFeatures, 'item_3_title', t('public.content.features.item_3_title'))) ?></h5>
                                <p class="mb-0"><?= e(pc($cFeatures, 'item_3_text', t('public.content.features.item_3_text'))) ?></p>
                            </div>
                            <div class="feature-icon">
                                <span class="fa fa-tag fa-2x"></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 wow fadeInUp" data-wow-delay="0.3s">
                        <div class="feature-item justify-content-end">
                            <div class="text-end me-4">
                                <h5 class="mb-3"><?= e(pc($cFeatures, 'item_4_title', t('public.content.features.item_4_title'))) ?></h5>
                                <p class="mb-0"><?= e(pc($cFeatures, 'item_4_text', t('public.content.features.item_4_text'))) ?></p>
                            </div>
                            <div class="feature-icon">
                                <span class="fa fa-map-pin fa-2x"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Features End -->

<!-- About Start -->
<div class="container-fluid overflow-hidden about py-5">
    <div class="container py-5">
        <div class="row g-5">
            <div class="col-xl-6 wow fadeInLeft" data-wow-delay="0.2s">
                <div class="about-item">
                    <div class="pb-5">
                        <h1 style="color: <?= e($brandColors['primary']) ?>;" class="display-5 text-capitalize"><?= e(pc($cAbout, 'title', t('public.content.about.title'))) ?></h1>
                        <p class="mb-0"><?= e(pc($cAbout, 'intro', t('public.content.about.intro'))) ?></p>
                    </div>
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="about-item-inner border p-4">
                                <div class="about-icon mb-4">
                                    <img src="img/about-icon-1.png" class="img-fluid w-50 h-50" alt="Icon">
                                </div>
                                <h5 class="mb-3"><?= e(pc($cAbout, 'vision_title', t('public.content.about.vision_title'))) ?></h5>
                                <p class="mb-0"><?= e(pc($cAbout, 'vision_text', t('public.content.about.vision_text'))) ?></p>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="about-item-inner border p-4">
                                <div class="about-icon mb-4">
                                    <img src="img/about-icon-2.png" class="img-fluid h-50 w-50" alt="Icon">
                                </div>
                                <h5 class="mb-3"><?= e(pc($cAbout, 'mission_title', t('public.content.about.mission_title'))) ?></h5>
                                <p class="mb-0"><?= e(pc($cAbout, 'mission_text', t('public.content.about.mission_text'))) ?></p>
                            </div>
                        </div>
                    </div>
                    <p class="text-item my-4"><?= e(pc($cAbout, 'extra_text', t('public.content.about.extra_text'))) ?></p>
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="text-center rounded bg-custom-secondary p-4">
                                <h1 class="display-6 text-white"><?= e(pc($cAbout, 'years_experience', t('public.content.about.years_experience'))) ?></h1>
                                <h5 class="text-light mb-0">Years Of Experience</h5>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="rounded">
                                <p class="mb-2"><i class="fa fa-check-circle text-secondary me-1"></i> <?= e(pc($cAbout, 'bullet_1', t('public.content.about.bullet_1'))) ?></p>
                                <p class="mb-2"><i class="fa fa-check-circle text-secondary me-1"></i> <?= e(pc($cAbout, 'bullet_2', t('public.content.about.bullet_2'))) ?></p>
                                <p class="mb-2"><i class="fa fa-check-circle text-secondary me-1"></i> <?= e(pc($cAbout, 'bullet_3', t('public.content.about.bullet_3'))) ?></p>
                                <p class="mb-0"><i class="fa fa-check-circle text-secondary me-1"></i> <?= e(pc($cAbout, 'bullet_4', t('public.content.about.bullet_4'))) ?></p>
                            </div>
                        </div>
                        <div class="col-lg-5 d-flex align-items-center">
                            <a href="#" class="btn btn-secondary rounded py-3 px-5">More About Us</a>
                        </div>
                        <style>
                            .btn-secondary {
                                background-color: <?= e($brandColors['primary']) ?> !important;
                                border-color: white !important;
                                color: white !important;
                                transition: all 0.3s ease-in-out;
                            }

                            .btn-secondary:hover {
                                background-color: white !important;
                                border-color: <?= e($brandColors['primary']) ?> !important;
                                color: <?= e($brandColors['primary']) ?> !important;
                            }
                        </style>
                        <div class="col-lg-7">
                            <div class="d-flex align-items-center">
                                <img src="img/attachment-img.jpg"
                                    class="img-fluid rounded-circle border border-4 border-secondary"
                                    style="width: 100px; height: 100px;" alt="Image">
                                <div class="ms-4">
                                    <h4><?= e(pc($cAbout, 'founder_name', t('public.content.about.founder_name'))) ?></h4>
                                    <p class="mb-0"><?= e(pc($cAbout, 'founder_title', t('public.content.about.founder_title'))) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 wow fadeInRight" data-wow-delay="0.2s">
                <div class="about-img">
                    <div class="img-1">
                        <img src="img/give-keys2.jpg" class="img-fluid rounded h-100 w-100" alt="Customer receiving car keys">
                    </div>
                    <div class="img-2">
                        <img src="img/back5.jpeg" class="img-fluid rounded w-100" alt="Luxury car showcase">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    .about-img::before,
    .about-img::after {
        background-color: <?= e($brandColors['primary']) ?> !important;
    }
</style>
<!-- About End -->

<!-- Fact Counter -->
<div class="container-fluid counter bg-secondary py-5">
    <div class="container py-5">
        <div class="row g-5">
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.1s">
                <div class="counter-item text-center">
                    <div class="counter-item-icon mx-auto">
                        <i class="fas fa-thumbs-up fa-2x"></i>
                    </div>
                    <div class="counter-counting my-3">
                        <span class="text-white fs-2 fw-bold" data-toggle="counter-up"><?= e(pc($cCounters, 'counter_1_value', t('public.content.counters.counter_1_value'))) ?></span>
                        <span class="h1 fw-bold text-white">+</span>
                    </div>
                    <h4 class="text-white mb-0"><?= e(pc($cCounters, 'counter_1_label', t('public.content.counters.counter_1_label'))) ?></h4>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.3s">
                <div class="counter-item text-center">
                    <div class="counter-item-icon mx-auto">
                        <i class="fas fa-car-alt fa-2x"></i>
                    </div>
                    <div class="counter-counting my-3">
                        <span class="text-white fs-2 fw-bold" data-toggle="counter-up"><?= e(pc($cCounters, 'counter_2_value', t('public.content.counters.counter_2_value'))) ?></span>
                        <span class="h1 fw-bold text-white">+</span>
                    </div>
                    <h4 class="text-white mb-0"><?= e(pc($cCounters, 'counter_2_label', t('public.content.counters.counter_2_label'))) ?></h4>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.5s">
                <div class="counter-item text-center">
                    <div class="counter-item-icon mx-auto">
                        <i class="fas fa-building fa-2x"></i>
                    </div>
                    <div class="counter-counting my-3">
                        <span class="text-white fs-2 fw-bold" data-toggle="counter-up"><?= e(pc($cCounters, 'counter_3_value', t('public.content.counters.counter_3_value'))) ?></span>
                        <span class="h1 fw-bold text-white">+</span>
                    </div>
                    <h4 class="text-white mb-0"><?= e(pc($cCounters, 'counter_3_label', t('public.content.counters.counter_3_label'))) ?></h4>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.7s">
                <div class="counter-item text-center">
                    <div class="counter-item-icon mx-auto">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                    <div class="counter-counting my-3">
                        <span class="text-white fs-2 fw-bold" data-toggle="counter-up"><?= e(pc($cCounters, 'counter_4_value', t('public.content.counters.counter_4_value'))) ?></span>
                        <span class="h1 fw-bold text-white">+</span>
                    </div>
                    <h4 class="text-white mb-0"><?= e(pc($cCounters, 'counter_4_label', t('public.content.counters.counter_4_label'))) ?></h4>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Fact Counter -->

<!-- Services Start -->
<div class="container-fluid service py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 style="color: <?= e($brandColors['primary']) ?>;" class="display-5 text-capitalize mb-3"><?= e(pc($cServices, 'title', t('public.content.services.title'))) ?></h1>
            <p class="mb-0"><?= e(pc($cServices, 'intro', t('public.content.services.intro'))) ?></p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.1s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-phone-alt fa-2x"></i>
                    </div>
                    <h5 class="mb-3"><?= e(pc($cServices, 'item_1_title', t('public.content.services.item_1_title'))) ?></h5>
                    <p class="mb-0"><?= e(pc($cServices, 'item_1_text', t('public.content.services.item_1_text'))) ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.3s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-money-bill-alt fa-2x"></i>
                    </div>
                    <h5 class="mb-3"><?= e(pc($cServices, 'item_2_title', t('public.content.services.item_2_title'))) ?></h5>
                    <p class="mb-0"><?= e(pc($cServices, 'item_2_text', t('public.content.services.item_2_text'))) ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.5s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-road fa-2x"></i>
                    </div>
                    <h5 class="mb-3"><?= e(pc($cServices, 'item_3_title', t('public.content.services.item_3_title'))) ?></h5>
                    <p class="mb-0"><?= e(pc($cServices, 'item_3_text', t('public.content.services.item_3_text'))) ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.1s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-umbrella fa-2x"></i>
                    </div>
                    <h5 class="mb-3"><?= e(pc($cServices, 'item_4_title', t('public.content.services.item_4_title'))) ?></h5>
                    <p class="mb-0"><?= e(pc($cServices, 'item_4_text', t('public.content.services.item_4_text'))) ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.3s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-building fa-2x"></i>
                    </div>
                    <h5 class="mb-3"><?= e(pc($cServices, 'item_5_title', t('public.content.services.item_5_title'))) ?></h5>
                    <p class="mb-0"><?= e(pc($cServices, 'item_5_text', t('public.content.services.item_5_text'))) ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.5s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-car-alt fa-2x"></i>
                    </div>
                    <h5 class="mb-3"><?= e(pc($cServices, 'item_6_title', t('public.content.services.item_6_title'))) ?></h5>
                    <p class="mb-0"><?= e(pc($cServices, 'item_6_text', t('public.content.services.item_6_text'))) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Services End -->

<!-- Car Steps Start -->
<div class="container-fluid steps py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize text-white mb-3"><?= e(pc($cSteps, 'title', t('public.content.steps.title'))) ?></h1>
            <p class="mb-0 text-white"><?= e(pc($cSteps, 'intro', t('public.content.steps.intro'))) ?></p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.1s">
                <div class="steps-item p-4 mb-4">
                    <h4><?= e(pc($cSteps, 'step_1_title', t('public.content.steps.step_1_title'))) ?></h4>
                    <p class="mb-0"><?= e(pc($cSteps, 'step_1_text', t('public.content.steps.step_1_text'))) ?></p>
                    <div class="setps-number">01.</div>
                </div>
            </div>
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.3s">
                <div class="steps-item p-4 mb-4">
                    <h4><?= e(pc($cSteps, 'step_2_title', t('public.content.steps.step_2_title'))) ?></h4>
                    <p class="mb-0"><?= e(pc($cSteps, 'step_2_text', t('public.content.steps.step_2_text'))) ?></p>
                    <div class="setps-number">02.</div>
                </div>
            </div>
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.5s">
                <div class="steps-item p-4 mb-4">
                    <h4><?= e(pc($cSteps, 'step_3_title', t('public.content.steps.step_3_title'))) ?></h4>
                    <p class="mb-0"><?= e(pc($cSteps, 'step_3_text', t('public.content.steps.step_3_text'))) ?></p>
                    <div class="setps-number">03.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Car Steps End -->

<!-- Team Start -->
<div class="container-fluid team pb-5" style="padding-top: 100px;">
    <div class="container pb-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize mb-3"><?= e(pc($cTeam, 'title', t('public.content.team.title'))) ?></h1>
            <p class="mb-0"><?= e(pc($cTeam, 'intro', t('public.content.team.intro'))) ?></p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.1s">
                <div class="team-item p-4 pt-0">
                    <div class="team-img">
                        <img src="img/team-1.jpg" class="img-fluid rounded w-100" alt="Image">
                    </div>
                    <div class="team-content pt-4">
                        <h4><?= e(pc($cTeam, 'member_1_name', t('public.content.team.member_1_name'))) ?></h4>
                        <p><?= e(pc($cTeam, 'member_1_role', t('public.content.team.member_1_role'))) ?></p>
                        <div class="team-icon d-flex justify-content-center">
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-facebook-f"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-twitter"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-instagram"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.3s">
                <div class="team-item p-4 pt-0">
                    <div class="team-img">
                        <img src="img/team-2.jpg" class="img-fluid rounded w-100" alt="Image">
                    </div>
                    <div class="team-content pt-4">
                        <h4><?= e(pc($cTeam, 'member_2_name', t('public.content.team.member_2_name'))) ?></h4>
                        <p><?= e(pc($cTeam, 'member_2_role', t('public.content.team.member_2_role'))) ?></p>
                        <div class="team-icon d-flex justify-content-center">
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-facebook-f"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-twitter"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-instagram"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.5s">
                <div class="team-item p-4 pt-0">
                    <div class="team-img">
                        <img src="img/team-3.jpg" class="img-fluid rounded w-100" alt="Image">
                    </div>
                    <div class="team-content pt-4">
                        <h4><?= e(pc($cTeam, 'member_3_name', t('public.content.team.member_3_name'))) ?></h4>
                        <p><?= e(pc($cTeam, 'member_3_role', t('public.content.team.member_3_role'))) ?></p>
                        <div class="team-icon d-flex justify-content-center">
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-facebook-f"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-twitter"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-instagram"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.7s">
                <div class="team-item p-4 pt-0">
                    <div class="team-img">
                        <img src="img/team-4.jpg" class="img-fluid rounded w-100" alt="Image">
                    </div>
                    <div class="team-content pt-4">
                        <h4><?= e(pc($cTeam, 'member_4_name', t('public.content.team.member_4_name'))) ?></h4>
                        <p><?= e(pc($cTeam, 'member_4_role', t('public.content.team.member_4_role'))) ?></p>
                        <div class="team-icon d-flex justify-content-center">
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-facebook-f"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-twitter"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-instagram"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i
                                    class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Team End -->
<style>
    .owl-carousel .owl-dots {
        display: none !important;
    }
</style>
<!-- Testimonial Start -->
<div class="container-fluid testimonial pb-5">
    <div class="container pb-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize mb-3"><?= e(pc($cTestimonials, 'title', t('public.content.testimonials.title'))) ?></h1>
            <p class="mb-0"><?= e(pc($cTestimonials, 'intro', t('public.content.testimonials.intro'))) ?></p>
        </div>
        <style>
            .d-flex.text-primary i {
                color: gold !important;
            }

            .d-flex.text-primary .text-body {
                color: silver !important;
            }
        </style>
        <div class="owl-carousel testimonial-carousel wow fadeInUp" data-wow-delay="0.1s">
            <div class="testimonial-item">
                <div class="testimonial-quote"><i class="fa fa-quote-right fa-2x"></i>
                </div>
                <div class="testimonial-inner p-4">
                    <img src="img/testimonial-1.jpg" class="img-fluid" alt="">
                    <div class="ms-4">
                        <h4><?= e(pc($cTestimonials, 'item_1_name', t('public.content.testimonials.item_1_name'))) ?></h4>
                        <p><?= e(pc($cTestimonials, 'item_1_role', t('public.content.testimonials.item_1_role'))) ?></p>
                        <div class="d-flex text-primary">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star text-body"></i>
                        </div>
                    </div>
                </div>
                <div class="border-top rounded-bottom p-4">
                    <p class="mb-0"><?= e(pc($cTestimonials, 'item_1_text', t('public.content.testimonials.item_1_text'))) ?></p>
                </div>
            </div>
            <div class="testimonial-item">
                <div class="testimonial-quote"><i class="fa fa-quote-right fa-2x"></i>
                </div>
                <div class="testimonial-inner p-4">
                    <img src="img/testimonial-2.jpg" class="img-fluid" alt="">
                    <div class="ms-4">
                        <h4><?= e(pc($cTestimonials, 'item_2_name', t('public.content.testimonials.item_2_name'))) ?></h4>
                        <p><?= e(pc($cTestimonials, 'item_2_role', t('public.content.testimonials.item_2_role'))) ?></p>
                        <div class="d-flex text-primary">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star text-body"></i>
                            <i class="fas fa-star text-body"></i>
                        </div>
                    </div>
                </div>
                <div class="border-top rounded-bottom p-4">
                    <p class="mb-0"><?= e(pc($cTestimonials, 'item_2_text', t('public.content.testimonials.item_2_text'))) ?></p>
                </div>
            </div>
            <div class="testimonial-item">
                <div class="testimonial-quote"><i class="fa fa-quote-right fa-2x"></i>
                </div>
                <div class="testimonial-inner p-4">
                    <img src="img/testimonial-3.jpg" class="img-fluid" alt="">
                    <div class="ms-4">
                        <h4><?= e(pc($cTestimonials, 'item_3_name', t('public.content.testimonials.item_3_name'))) ?></h4>
                        <p><?= e(pc($cTestimonials, 'item_3_role', t('public.content.testimonials.item_3_role'))) ?></p>
                        <div class="d-flex text-primary">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star text-body"></i>
                            <i class="fas fa-star text-body"></i>
                            <i class="fas fa-star text-body"></i>
                        </div>
                    </div>
                </div>
                <div class="border-top rounded-bottom p-4">
                    <p class="mb-0"><?= e(pc($cTestimonials, 'item_3_text', t('public.content.testimonials.item_3_text'))) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Testimonial End -->

<?php
include("footer_p.php") ?>
