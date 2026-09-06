<?php
$active = "contact";
include("header_p.php") ?>

<!-- Header Start -->
<div class="container-fluid bg-breadcrumb">
    <div class="container text-center py-5" style="max-width: 900px;">
        <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s"><?= e(t('public.contact_page.title')) ?></h4>
        <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
            <li class="breadcrumb-item"><a href="index.php"><?= e(t('public.breadcrumb.home')) ?></a></li>
            <li class="breadcrumb-item"><a href="#"><?= e(t('public.breadcrumb.pages')) ?></a></li>
            <li style="text-decoration: underline;" class="breadcrumb-item active text-white"><?= e(t('public.contact_page.breadcrumb')) ?></li>
        </ol>
    </div>
</div>
<!-- Header End -->

<!-- Contact Start -->
<div class="container-fluid contact py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize text-secondary mb-3"><?= e(t('public.contact_page.heading')) ?></h1>
            <p class="mb-0"><?= e(t('public.contact_page.intro')) ?></p>
        </div>
        <div class="row g-5">
            <div class="col-12 wow fadeInUp" data-wow-delay="0.1s">
                <div class="row g-5">
                    <div class="col-md-6 col-lg-6 col-xl-3">
                        <div class="contact-add-item p-4">
                            <div style="background-color: <?= e($brandColors['primary']) ?>;" class="contact-icon mb-4">
                                <i style="color: white;" class="fas fa-map-marker-alt fa-2x"></i>
                            </div>
                            <div>
                                <h4><?= e(t('public.contact_page.address_label')) ?></h4>
                                <p class="mb-0">123 Street New York.USA</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.3s">
                        <div class="contact-add-item p-4">
                            <div style="background-color: <?= e($brandColors['primary']) ?>;" class="contact-icon mb-4">
                                <i style="color: white;" class="fas fa-envelope fa-2x"></i>
                            </div>
                            <div>
                                <h4><?= e(t('public.contact_page.mail_label')) ?></h4>
                                <p class="mb-0">info@example.com</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.5s">
                        <div class="contact-add-item p-4">
                            <div style="background-color: <?= e($brandColors['primary']) ?>;" class="contact-icon mb-4">
                                <i style="color: white;" class="fa fa-phone-alt fa-2x"></i>
                            </div>
                            <div>
                                <h4><?= e(t('public.contact_page.phone_label')) ?></h4>
                                <p class="mb-0">(+012) 3456 7890</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.7s">
                        <div class="contact-add-item p-4">
                            <div style="background-color: <?= e($brandColors['primary']) ?>;" class="contact-icon mb-4">
                                <i style="color: white;" class="fab fa-firefox-browser fa-2x"></i>
                            </div>
                            <div>
                                <h4><?= e(t('public.contact_page.website_label')) ?></h4>
                                <p class="mb-0">(+012) 3456 7890</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 wow fadeInUp" data-wow-delay="0.1s">
                <div style="background-color: <?= e($brandColors['primary']) ?>;" class=" p-5 rounded">
                    <h4 class="text-white mb-4"><?= e(t('public.contact_page.form_title')) ?></h4>
                    <form>
                        <div class="row g-4">
                            <div class="col-lg-12 col-xl-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="name" placeholder="<?= e(t('public.contact_page.form_name')) ?>">
                                    <label for="name"><?= e(t('public.contact_page.form_name')) ?></label>
                                </div>
                            </div>
                            <div class="col-lg-12 col-xl-6">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" placeholder="<?= e(t('public.contact_page.form_email')) ?>">
                                    <label for="email"><?= e(t('public.contact_page.form_email')) ?></label>
                                </div>
                            </div>
                            <div class="col-lg-12 col-xl-6">
                                <div class="form-floating">
                                    <input type="phone" class="form-control" id="phone" placeholder="<?= e(t('public.contact_page.form_phone')) ?>">
                                    <label for="phone"><?= e(t('public.contact_page.form_phone')) ?></label>
                                </div>
                            </div>
                            <div class="col-lg-12 col-xl-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="project" placeholder="<?= e(t('public.contact_page.form_project')) ?>">
                                    <label for="project"><?= e(t('public.contact_page.form_project')) ?></label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="subject" placeholder="<?= e(t('public.contact_page.form_subject')) ?>">
                                    <label for="subject"><?= e(t('public.contact_page.form_subject')) ?></label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" placeholder="<?= e(t('public.contact_page.form_message_placeholder')) ?>" id="message" style="height: 160px"></textarea>
                                    <label for="message"><?= e(t('public.contact_page.form_message')) ?></label>
                                </div>

                            </div>
                            <div class="col-12">
                                <button class="btn btn-light w-100 py-3"><?= e(t('public.contact_page.send_button')) ?></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-12 col-xl-1 wow fadeInUp" data-wow-delay="0.3s">
                <div class="d-flex flex-xl-column align-items-center justify-content-center">
                    <a class="btn btn-xl-square btn-light rounded-circle mb-0 mb-xl-4 me-4 me-xl-0" href=""><i class="fab fa-facebook-f"></i></a>
                    <a class="btn btn-xl-square btn-light rounded-circle mb-0 mb-xl-4 me-4 me-xl-0" href=""><i class="fab fa-twitter"></i></a>
                    <a class="btn btn-xl-square btn-light rounded-circle mb-0 mb-xl-4 me-4 me-xl-0" href=""><i class="fab fa-instagram"></i></a>
                    <a class="btn btn-xl-square btn-light rounded-circle mb-0 mb-xl-0 me-0 me-xl-0" href=""><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="col-12 col-xl-5 wow fadeInUp" data-wow-delay="0.1s">
                <div class="p-5 bg-light rounded">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                    <div class="bg-white rounded p-4 mb-<?= $i === 3 ? '0' : '4' ?>">
                        <h4 class="mb-3"><?= e(t('public.contact_page.branch_label')) ?> 0<?= $i ?></h4>
                        <div class="d-flex align-items-center flex-shrink-0 mb-3">
                            <p class="mb-0 text-dark me-2"><?= e(t('public.contact_page.address_field')) ?></p><i class="fas fa-map-marker-alt text-secondary me-2"></i>
                            <p class="mb-0">123 Street New York.USA</p>
                        </div>
                        <div class="d-flex align-items-center">
                            <p class="mb-0 text-dark me-2"><?= e(t('public.contact_page.phone_field')) ?></p><i class="fa fa-phone-alt text-secondary me-2"></i>
                            <p class="mb-0">(+012) 3456 7890</p>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="col-12">
                <div class="rounded">
                    <iframe class="rounded w-100"
                        style="height: 400px;" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d387191.33750346623!2d-73.97968099999999!3d40.6974881!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c24fa5d33f083b%3A0xc80b8f06e177fe62!2sNew%20York%2C%20NY%2C%20USA!5e0!3m2!1sen!2sbd!4v1694259649153!5m2!1sen!2sbd"
                        loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>

        </div>
    </div>
</div>
<!-- Contact End -->
<?php
include("footer_p.php") ?>
