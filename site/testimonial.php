<?php
$active = "testimonial";
include("header_p.php") ?>

<!-- Header Start -->
<div class="container-fluid bg-breadcrumb">
    <div class="container text-center py-5" style="max-width: 900px;">
        <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s"><?= e(t('public.testimonial_page.title')) ?></h4>
        <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
            <li class="breadcrumb-item"><a href="index.php"><?= e(t('public.breadcrumb.home')) ?></a></li>
            <li class="breadcrumb-item"><a href="#"><?= e(t('public.breadcrumb.pages')) ?></a></li>
            <li style="text-decoration: underline;" class="breadcrumb-item active text-white"><?= e(t('public.testimonial_page.breadcrumb')) ?></li>
        </ol>
    </div>
</div>
<!-- Header End -->
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

<style>
    .owl-carousel .owl-dots {
        display: none !important;
    }
</style>
<?php
include("footer_p.php") ?>
