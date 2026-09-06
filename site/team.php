<?php include("header_p.php") ?>

<!-- Header Start -->
<div class="container-fluid bg-breadcrumb">
    <div class="container text-center py-5" style="max-width: 900px;">
        <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s"><?= e(t('public.team_page.title')) ?></h4>
        <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
            <li class="breadcrumb-item"><a href="index.php"><?= e(t('public.breadcrumb.home')) ?></a></li>
            <li class="breadcrumb-item"><a href="#"><?= e(t('public.breadcrumb.pages')) ?></a></li>
            <li class="breadcrumb-item active text-primary"><?= e(t('public.team_page.breadcrumb')) ?></li>
        </ol>
    </div>
</div>
<!-- Header End -->

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
