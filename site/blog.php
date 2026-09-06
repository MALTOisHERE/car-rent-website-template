<?php include("header_p.php") ?>

<!-- Header Start -->
<div class="container-fluid bg-breadcrumb">
    <div class="container text-center py-5" style="max-width: 900px;">
        <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s"><?= e(t('public.blog_page.title')) ?></h4>
        <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
            <li class="breadcrumb-item"><a href="index.php"><?= e(t('public.breadcrumb.home')) ?></a></li>
            <li class="breadcrumb-item"><a href="#"><?= e(t('public.breadcrumb.pages')) ?></a></li>
            <li class="breadcrumb-item active text-primary"><?= e(t('public.blog_page.breadcrumb')) ?></li>
        </ol>
    </div>
</div>
<!-- Header End -->

<!-- Blog Start -->
<div class="container-fluid blog py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize mb-3"><?= e(t('public.blog_page.heading')) ?></h1>
            <p class="mb-0"><?= e(t('public.blog_page.intro')) ?></p>
        </div>
        <div class="row g-4">
            <?php
            $posts = [
                ['date' => '30 Dec 2025', 'img' => 1, 'key' => 'post_1'],
                ['date' => '25 Dec 2025', 'img' => 2, 'key' => 'post_2'],
                ['date' => '27 Dec 2025', 'img' => 3, 'key' => 'post_3'],
            ];
            foreach ($posts as $i => $post):
            ?>
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.<?= $i * 2 + 1 ?>s">
                <div class="blog-item">
                    <div class="blog-img">
                        <img src="img/blog-<?= $post['img'] ?>.jpg" class="img-fluid rounded-top w-100" alt="Image">
                    </div>
                    <div class="blog-content rounded-bottom p-4">
                        <div class="blog-date"><?= e($post['date']) ?></div>
                        <div class="blog-comment my-3">
                            <div class="small"><span class="fa fa-user text-primary"></span><span class="ms-2"><?= e(t('public.blog_page.author')) ?></span></div>
                            <div class="small"><span class="fa fa-comment-alt text-primary"></span><span class="ms-2"><?= e(t('public.blog_page.comments_label')) ?></span></div>
                        </div>
                        <a href="#" class="h4 d-block mb-3"><?= e(t('public.blog_page.' . $post['key'] . '_title')) ?></a>
                        <p class="mb-3"><?= e(t('public.blog_page.' . $post['key'] . '_text')) ?></p>
                        <a href="#" class=""><?= e(t('public.blog_page.read_more')) ?> <i class="fa fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<!-- Blog End -->

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

<!-- Banner Start -->
<div class="container-fluid banner py-5 wow zoomInDown" data-wow-delay="0.1s">
    <div class="container py-5">
        <div class="banner-item rounded">
            <img src="img/banner-1.jpg" class="img-fluid rounded w-100" alt="">
            <div class="banner-content">
                <h2 class="text-primary"><?= e(t('public.shared_banner.title')) ?></h2>
                <h1 class="text-white"><?= e(t('public.shared_banner.heading')) ?></h1>
                <p class="text-white"><?= e(t('public.shared_banner.text')) ?></p>
                <div class="banner-btn">
                    <a href="#" class="btn btn-secondary rounded-pill py-3 px-4 px-md-5 me-2"><?= e(t('public.shared_banner.whatsapp')) ?></a>
                    <a href="#" class="btn btn-primary rounded-pill py-3 px-4 px-md-5 ms-2"><?= e(t('public.shared_banner.contact_us')) ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Banner End -->
<?php
include("footer_p.php") ?>
