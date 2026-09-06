<?php
$active = "about";
include("header_p.php") ?>

<!-- Header Start -->
<div class="container-fluid bg-breadcrumb">
    <div class="container text-center py-5" style="max-width: 900px;">
        <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s"><?= e(t('public.about_page.title')) ?></h4>
        <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
            <li class="breadcrumb-item"><a href="index.php"><?= e(t('public.breadcrumb.home')) ?></a></li>
            <li class="breadcrumb-item"><a href="#"><?= e(t('public.breadcrumb.pages')) ?></a></li>
            <li style="text-decoration: underline;" class="breadcrumb-item active text-white"><?= e(t('public.about_page.breadcrumb')) ?></li>
        </ol>
    </div>
</div>
<!-- Header End -->

<!-- About Start -->
<div class="container-fluid overflow-hidden about py-5">
    <div class="container py-5">
        <div class="row g-5">
            <div class="col-xl-6 wow fadeInLeft" data-wow-delay="0.2s">
                <div class="about-item">
                    <div class="pb-5">
                        <h1 style="color: <?= e($brandColors['primary']) ?>;" class="display-5 text-capitalize"><?= e(t('public.about_page.heading')) ?></h1>
                        <p class="mb-0"><?= e(t('public.about_page.intro')) ?></p>
                    </div>
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="about-item-inner border p-4">
                                <div class="about-icon mb-4">
                                    <img src="img/about-icon-1.png" class="img-fluid w-50 h-50" alt="Icon">
                                </div>
                                <h5 class="mb-3"><?= e(t('public.about_page.vision_title')) ?></h5>
                                <p class="mb-0"><?= e(t('public.about_page.vision_text')) ?></p>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="about-item-inner border p-4">
                                <div class="about-icon mb-4">
                                    <img src="img/about-icon-2.png" class="img-fluid h-50 w-50" alt="Icon">
                                </div>
                                <h5 class="mb-3"><?= e(t('public.about_page.mission_title')) ?></h5>
                                <p class="mb-0"><?= e(t('public.about_page.mission_text')) ?></p>
                            </div>
                        </div>
                    </div>
                    <p class="text-item my-4"><?= e(t('public.about_page.extra_text')) ?></p>
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="text-center rounded bg-custom-secondary p-4">
                                <h1 class="display-6 text-white">17</h1>
                                <h5 class="text-light mb-0"><?= e(t('public.about_page.years_experience_label')) ?></h5>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="rounded">
                                <p class="mb-2"><i class="fa fa-check-circle text-secondary me-1"></i> <?= e(t('public.about_page.bullet_1')) ?></p>
                                <p class="mb-2"><i class="fa fa-check-circle text-secondary me-1"></i> <?= e(t('public.about_page.bullet_2')) ?></p>
                                <p class="mb-2"><i class="fa fa-check-circle text-secondary me-1"></i> <?= e(t('public.about_page.bullet_3')) ?></p>
                                <p class="mb-0"><i class="fa fa-check-circle text-secondary me-1"></i> <?= e(t('public.about_page.bullet_4')) ?></p>
                            </div>
                        </div>
                        <div class="col-lg-5 d-flex align-items-center">
                            <a href="#" class="btn btn-secondary rounded py-3 px-5"><?= e(t('public.about_page.cta_button')) ?></a>
                        </div>
                        <style>
                            .btn-secondary {
                                background-color: <?= e($brandColors['primary']) ?> !important;
                                /* Default background color */
                                border-color: white !important;
                                /* Default border color */
                                color: white !important;
                                /* Default text color */
                                transition: all 0.3s ease-in-out;
                            }

                            .btn-secondary:hover {
                                background-color: white !important;
                                /* Hover background color */
                                border-color: <?= e($brandColors['primary']) ?> !important;
                                /* Hover border color */
                                color: <?= e($brandColors['primary']) ?> !important;
                                /* Hover text color */
                            }
                        </style>
                        <div class="col-lg-7">
                            <div class="d-flex align-items-center">
                                <img src="img/attachment-img.jpg"
                                    class="img-fluid rounded-circle border border-4 border-secondary"
                                    style="width: 100px; height: 100px;" alt="Image">
                                <div class="ms-4">
                                    <h4><?= e(t('public.about_page.founder_name')) ?></h4>
                                    <p class="mb-0"><?= e(t('public.about_page.founder_title')) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 wow fadeInRight" data-wow-delay="0.2s">
                <div class="about-img">
                    <div class="img-1">
                        <img src="img/give-keys2.jpg" class="img-fluid rounded h-100 w-100" alt="">
                    </div>
                    <div class="img-2">
                        <img src="img/back5.jpeg" class="img-fluid rounded w-100" alt="">
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
<div class="container-fluid counter py-5">
    <div class="container py-5">
        <div class="row g-5">
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.1s">
                <div class="counter-item text-center">
                    <div class="counter-item-icon mx-auto">
                        <i class="fas fa-thumbs-up fa-2x"></i>
                    </div>
                    <div class="counter-counting my-3">
                        <span class="text-white fs-2 fw-bold" data-toggle="counter-up">829</span>
                        <span class="h1 fw-bold text-white">+</span>
                    </div>
                    <h4 class="text-white mb-0"><?= e(t('public.shared_counter.happy_clients')) ?></h4>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.3s">
                <div class="counter-item text-center">
                    <div class="counter-item-icon mx-auto">
                        <i class="fas fa-car-alt fa-2x"></i>
                    </div>
                    <div class="counter-counting my-3">
                        <span class="text-white fs-2 fw-bold" data-toggle="counter-up">56</span>
                        <span class="h1 fw-bold text-white">+</span>
                    </div>
                    <h4 class="text-white mb-0"><?= e(t('public.shared_counter.number_of_cars')) ?></h4>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.5s">
                <div class="counter-item text-center">
                    <div class="counter-item-icon mx-auto">
                        <i class="fas fa-building fa-2x"></i>
                    </div>
                    <div class="counter-counting my-3">
                        <span class="text-white fs-2 fw-bold" data-toggle="counter-up">127</span>
                        <span class="h1 fw-bold text-white">+</span>
                    </div>
                    <h4 class="text-white mb-0"><?= e(t('public.shared_counter.car_center')) ?></h4>
                </div>
            </div>
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.7s">
                <div class="counter-item text-center">
                    <div class="counter-item-icon mx-auto">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                    <div class="counter-counting my-3">
                        <span class="text-white fs-2 fw-bold" data-toggle="counter-up">589</span>
                        <span class="h1 fw-bold text-white">+</span>
                    </div>
                    <h4 class="text-white mb-0"><?= e(t('public.shared_counter.total_kilometers')) ?></h4>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Fact Counter -->

<!-- Features Start -->
<div class="container-fluid feature py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 style="color:<?= e($brandColors['primary']) ?>;" class="display-5 text-capitalize mb-3"><?= e(t('public.about_page.features_title')) ?></h1>
            <p class="mb-0"><?= e(t('public.about_page.features_intro')) ?></p>
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
                                <h5 class="mb-3"><?= e(t('public.about_page.feature_1_title')) ?></h5>
                                <p class="mb-0"><?= e(t('public.about_page.feature_1_text')) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 wow fadeInUp" data-wow-delay="0.3s">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <span class="fa fa-road fa-2x"></span>
                            </div>
                            <div class="ms-4">
                                <h5 class="mb-3"><?= e(t('public.about_page.feature_2_title')) ?></h5>
                                <p class="mb-0"><?= e(t('public.about_page.feature_2_text')) ?></p>
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
                                <h5 class="mb-3"><?= e(t('public.about_page.feature_3_title')) ?></h5>
                                <p class="mb-0"><?= e(t('public.about_page.feature_3_text')) ?></p>
                            </div>
                            <div class="feature-icon">
                                <span class="fa fa-tag fa-2x"></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 wow fadeInUp" data-wow-delay="0.3s">
                        <div class="feature-item justify-content-end">
                            <div class="text-end me-4">
                                <h5 class="mb-3"><?= e(t('public.about_page.feature_4_title')) ?></h5>
                                <p class="mb-0"><?= e(t('public.about_page.feature_4_text')) ?></p>
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

<!-- Car Steps Start -->
<div class="container-fluid steps py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize text-white mb-3"><?= e(t('public.about_page.process_title')) ?></h1>
            <p class="mb-0 text-white"><?= e(t('public.about_page.process_intro')) ?></p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.1s">
                <div class="steps-item p-4 mb-4">
                    <h4><?= e(t('public.about_page.step_1_title')) ?></h4>
                    <p class="mb-0"><?= e(t('public.about_page.step_1_text')) ?></p>
                    <div class="setps-number">01.</div>
                </div>
            </div>
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.3s">
                <div class="steps-item p-4 mb-4">
                    <h4><?= e(t('public.about_page.step_2_title')) ?></h4>
                    <p class="mb-0"><?= e(t('public.about_page.step_2_text')) ?></p>
                    <div class="setps-number">02.</div>
                </div>
            </div>
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.5s">
                <div class="steps-item p-4 mb-4">
                    <h4><?= e(t('public.about_page.step_3_title')) ?></h4>
                    <p class="mb-0"><?= e(t('public.about_page.step_3_text')) ?></p>
                    <div class="setps-number">03.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Car Steps End -->

<!-- Team Start -->
<div class="container-fluid team py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize mb-3"><?= e(t('public.shared_team.title')) ?></h1>
            <p class="mb-0"><?= e(t('public.shared_team.intro')) ?></p>
        </div>
        <div class="row g-4">
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.<?= $i * 2 - 1 ?>s">
                <div class="team-item p-4 pt-0">
                    <div class="team-img">
                        <img src="img/team-<?= $i ?>.jpg" class="img-fluid rounded w-100" alt="Image">
                    </div>
                    <div class="team-content pt-4">
                        <h4><?= e(t('public.shared_team.member_name')) ?></h4>
                        <p><?= e(t('public.shared_team.member_role')) ?></p>
                        <div class="team-icon d-flex justify-content-center">
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i class="fab fa-facebook-f"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i class="fab fa-twitter"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i class="fab fa-instagram"></i></a>
                            <a class="btn btn-square btn-light rounded-circle mx-1" href=""><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>
<!-- Team End -->
<?php
include("footer_p.php") ?>
