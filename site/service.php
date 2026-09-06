<?php
$active = "service";
include("header_p.php") ?>

<!-- Header Start -->
<div class="container-fluid bg-breadcrumb">
    <div class="container text-center py-5" style="max-width: 900px;">
        <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s"><?= e(t('public.service_page.title')) ?></h4>
        <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
            <li class="breadcrumb-item"><a href="index.php"><?= e(t('public.breadcrumb.home')) ?></a></li>
            <li class="breadcrumb-item"><a href="#"><?= e(t('public.breadcrumb.pages')) ?></a></li>
            <li style="text-decoration: underline;" class="breadcrumb-item active text-white"><?= e(t('public.service_page.breadcrumb')) ?></li>
        </ol>
    </div>
</div>
<!-- Header End -->

<!-- Services Start -->
<div class="container-fluid service py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize mb-3"><?= e(t('public.service_page.heading')) ?></h1>
            <p class="mb-0"><?= e(t('public.service_page.intro')) ?></p>
        </div>
        <div class="row g-4">
            <?php
            $items = [
                ['icon' => 'fa-phone-alt', 'key' => 'item_1'],
                ['icon' => 'fa-money-bill-alt', 'key' => 'item_2'],
                ['icon' => 'fa-road', 'key' => 'item_3'],
                ['icon' => 'fa-umbrella', 'key' => 'item_4'],
                ['icon' => 'fa-building', 'key' => 'item_5'],
                ['icon' => 'fa-car-alt', 'key' => 'item_6'],
            ];
            foreach ($items as $i => $item):
            ?>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.<?= ($i % 3) * 2 + 1 ?>s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa <?= e($item['icon']) ?> fa-2x"></i>
                    </div>
                    <h5 class="mb-3"><?= e(t('public.service_page.' . $item['key'] . '_title')) ?></h5>
                    <p class="mb-0"><?= e(t('public.service_page.' . $item['key'] . '_text')) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<!-- Services End -->

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
<!-- Team End -->
<style>
    .owl-carousel .owl-dots {
        display: none !important;
    }
</style>
<div style="height: 100px;"></div>
<!-- Testimonial Start -->
<div class="container-fluid testimonial pb-5">
    <div class="container pb-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize mb-3"><?= e(t('public.shared_testimonials.title')) ?></h1>
            <p class="mb-0"><?= e(t('public.shared_testimonials.intro')) ?></p>
        </div>
        <style>
            .d-flex.text-primary i {
                color: gold !important;
                /* Makes all stars gold */
            }

            .d-flex.text-primary .text-body {
                color: silver !important;
                /* Changes the last star */
            }
        </style>
        <div class="owl-carousel testimonial-carousel wow fadeInUp" data-wow-delay="0.1s">
            <?php $stars = [5, 3, 2]; foreach ($stars as $i => $filled): ?>
            <div class="testimonial-item">
                <div class="testimonial-quote"><i class="fa fa-quote-right fa-2x"></i>
                </div>
                <div class="testimonial-inner p-4">
                    <img src="img/testimonial-<?= $i + 1 ?>.jpg" class="img-fluid" alt="">
                    <div class="ms-4">
                        <h4><?= e(t('public.shared_testimonials.name')) ?></h4>
                        <p><?= e(t('public.shared_testimonials.role')) ?></p>
                        <div class="d-flex text-primary">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                            <i class="fas fa-star<?= $s > $filled ? ' text-body' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <div class="border-top rounded-bottom p-4">
                    <p class="mb-0"><?= e(t('public.shared_testimonials.text')) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<!-- Testimonial End -->

<?php
include("footer_p.php") ?>
