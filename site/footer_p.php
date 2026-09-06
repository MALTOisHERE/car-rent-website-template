<style>
    footer .btn:hover {
        color: <?= e($brandColors['primary']) ?> !important;
        background-color: white !important;
        border-color: <?= e($brandColors['primary']) ?> !important;
    }

    a:hover,
    .dropdown-item:hover {
        color: white !important;
    }

    .btn-md-square:hover {
        background-color: white !important;
    }

    .btn-md-square:hover i {
        color: <?= e($brandColors['primary']) ?> !important;
    }
</style>
<!-- Footer Start -->
<div class="container-fluid footer py-5 wow fadeIn" data-wow-delay="0.2s">
    <div class="container py-5">
        <div class="row g-5">
            <div class="col-md-6 col-lg-6 col-xl-3">
                <div class="footer-item d-flex flex-column">
                    <div class="footer-item">
                        <h4 class="text-white mb-4"><?= e(t('public.footer.about_title')) ?></h4>
                        <p class="mb-3"><?= e(t('public.footer.about_text')) ?></p>
                    </div>
                    <div class="position-relative">
                        <input class="form-control rounded-pill w-100 py-3 ps-4 pe-5" type="text"
                            placeholder="<?= e(t('public.footer.email_placeholder')) ?>">
                        <button type="button"
                            class="btn btn-secondary rounded-pill position-absolute top-0 end-0 py-2 mt-2 me-2"><?= e(t('public.footer.subscribe')) ?></button>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-6 col-xl-3">
                <div class="footer-item d-flex flex-column">
                    <h4 class="text-white mb-4"><?= e(t('public.footer.quick_links_title')) ?></h4>
                    <a href="#"><i class="fas fa-angle-right me-2"></i> <?= e(t('public.footer.quick_link_1')) ?></a>
                    <a href="#"><i class="fas fa-angle-right me-2"></i> <?= e(t('public.footer.quick_link_2')) ?></a>
                    <a href="#"><i class="fas fa-angle-right me-2"></i> <?= e(t('public.footer.quick_link_3')) ?></a>
                    <a href="#"><i class="fas fa-angle-right me-2"></i> <?= e(t('public.footer.quick_link_4')) ?></a>
                    <a href="#"><i class="fas fa-angle-right me-2"></i> <?= e(t('public.footer.quick_link_5')) ?></a>
                    <a href="#"><i class="fas fa-angle-right me-2"></i> <?= e(t('public.footer.quick_link_6')) ?></a>
                </div>
            </div>

            <div class="col-md-6 col-lg-6 col-xl-3">
                <div class="footer-item d-flex flex-column">
                    <h4 class="text-white mb-4"><?= e(t('public.footer.hours_title')) ?></h4>
                    <div class="mb-3">
                        <h6 class="text-muted mb-0"><?= e(t('public.footer.weekday_label')) ?></h6>
                        <p class="text-white mb-0"><?= e(t('public.footer.weekday_hours')) ?></p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-muted mb-0"><?= e(t('public.footer.saturday_label')) ?></h6>
                        <p class="text-white mb-0"><?= e(t('public.footer.saturday_hours')) ?></p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-muted mb-0"><?= e(t('public.footer.sunday_label')) ?></h6>
                        <p class="text-white mb-0"><?= e(t('public.footer.sunday_hours')) ?></p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-6 col-xl-3">
                <div class="footer-item d-flex flex-column">
                    <h4 class="text-white mb-4"><?= e(t('public.footer.contact_title')) ?></h4>
                    <a href="#"><i class="fa fa-map-marker-alt me-2"></i> <?= e(t('public.footer.address')) ?></a>
                    <a href="mailto:<?= e(t('public.footer.email')) ?>"><i class="fas fa-envelope me-2"></i> <?= e(t('public.footer.email')) ?></a>
                    <a href="tel:<?= e(t('public.footer.phone')) ?>"><i class="fas fa-phone me-2"></i> <?= e(t('public.footer.phone')) ?></a>
                    <a href="tel:<?= e(t('public.footer.fax')) ?>" class="mb-3"><i class="fas fa-print me-2"></i> <?= e(t('public.footer.fax')) ?></a>
                    <div class="d-flex">
                        <a class="btn btn-secondary btn-md-square rounded-circle me-3" href=""><i
                                class="fab fa-facebook-f text-white"></i></a>
                        <a class="btn btn-secondary btn-md-square rounded-circle me-3" href=""><i
                                class="fab fa-twitter text-white"></i></a>
                        <a class="btn btn-secondary btn-md-square rounded-circle me-3" href=""><i
                                class="fab fa-instagram text-white"></i></a>
                        <a class="btn btn-secondary btn-md-square rounded-circle me-0" href=""><i
                                class="fab fa-linkedin-in text-white"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Footer End -->

<!-- Copyright Start -->
<div class="container-fluid copyright py-4">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-md-6 text-center text-md-start mb-md-0">
                <span class="text-body"><i
                        class="fas fa-copyright text-light me-2"></i>Aurevo<?= e(t('public.footer.copyright_suffix')) ?></span>
            </div>
            <div class="col-md-6 text-center text-md-end text-body">
                <?= e(t('public.footer.designed_by')) ?> <b>Unitime</b>
            </div>
        </div>
    </div>
</div>
<!-- Copyright End -->

<style>
    .back-to-top {
        background-color: <?= e($brandColors['primary']) ?> !important;
        border: 2px solid white !important;
        color: white !important;
        transition: all 0.3s ease-in-out;
    }

    .back-to-top:hover {
        background-color: white !important;
        border: 2px solid <?= e($brandColors['primary']) ?> !important;
        color: <?= e($brandColors['primary']) ?> !important;
    }
</style>
<!-- Back to Top -->
<a href="#" class="btn btn-secondary btn-lg-square rounded-circle back-to-top"><i class="fa fa-arrow-up"></i></a>

<!-- JavaScript Libraries -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/public/lib/wow/wow.min.js"></script>
<script src="assets/public/lib/easing/easing.min.js"></script>
<script src="assets/public/lib/waypoints/waypoints.min.js"></script>
<script src="assets/public/lib/counterup/counterup.min.js"></script>
<script src="assets/public/lib/owlcarousel/owl.carousel.min.js"></script>

<!-- Template Javascript -->
<script src="assets/public/js/main.js"></script>
</body>

</html>
