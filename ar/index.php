<?php
$active = "index";
include("header_p.php") ?>
<!-- بداية العرض التقديمي -->
<div class="header-carousel">
    <div id="carouselId" class="carousel slide" data-bs-ride="carousel" data-bs-interval="false">
        <div class="carousel-inner" role="listbox">
            <div class="carousel-item active">
                <img src="../img/back4.webp" class="img-fluid w-100" alt="الشريحة الأولى" />
                <div class="carousel-caption">
                    <div class="container py-4">
                        <div class="row g-5">
                            <div class="col-lg-6 fadeInLeft animated" data-animation="fadeInLeft" data-delay="1s"
                                style="animation-delay: 1s;">
                                <div style="background-color: #011468;" class=" rounded p-5">
                                    <h4 class="text-white mb-4">استمر في حجز السيارة</h4>
                                    <form method="GET" action="selection.php">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="input-group">
                                                    <div
                                                        class="d-flex align-items-center bg-light text-body rounded-start p-2">
                                                        <span class="fas fa-map-marker-alt"></span> <span
                                                            class="ms-1">مكان الاستلام</span>
                                                    </div>
                                                    <input class="form-control" type="text" name="depart"
                                                        placeholder="أدخل مدينة أو مطار" required>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <a href="javascript(0);" id="toggleDropOff" class="text-start text-white d-block mb-2">هل تحتاج إلى مكان إرجاع مختلف؟</a>
                                                <div class="input-group" id="dropOffInputGroup" style="display: none;"> <!-- مخفي في البداية -->
                                                    <div class="d-flex align-items-center bg-light text-body rounded-start p-2">
                                                        <span class="fas fa-map-marker-alt"></span>
                                                        <span class="ms-1">مكان الإرجاع</span>
                                                    </div>
                                                    <input class="form-control" type="text" name="arrive" placeholder="أدخل مدينة أو مطار">
                                                </div>
                                            </div>
                                            <script>
                                                document.getElementById('toggleDropOff').addEventListener('click', function(event) {
                                                    event.preventDefault(); // يمنع السلوك الافتراضي للرابط
                                                    var inputGroup = document.getElementById('dropOffInputGroup');
                                                    if (inputGroup.style.display === 'none') {
                                                        inputGroup.style.display = 'flex'; // أو 'block' حسب التخطيط
                                                    } else {
                                                        inputGroup.style.display = 'none';
                                                    }
                                                });
                                            </script>
                                            <div class="col-12">
                                                <div class="input-group">
                                                    <div
                                                        class="d-flex align-items-center bg-light text-body rounded-start p-2">
                                                        <span class="fas fa-calendar-alt"></span><span
                                                            class="ms-1">تاريخ الاستلام</span>
                                                    </div>
                                                    <input class="form-control" type="date" name="Date_debut">
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
                                                    <div
                                                        class="d-flex align-items-center bg-light text-body rounded-start p-2">
                                                        <span class="fas fa-calendar-alt"></span><span
                                                            class="ms-1">تاريخ الإرجاع</span>
                                                    </div>
                                                    <input class="form-control" type="date" name="Date_fin">
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
                                                <button type="submit" class="btn btn-light w-100 py-2">احجز الآن</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="col-lg-6 d-none d-lg-flex fadeInRight animated" data-animation="fadeInRight"
                                data-delay="1s" style="animation-delay: 1s;">
                                <div class="text-start">
                                    <h1 class="display-5 text-white">احصل على خصم 15% على تأجيرك. خطط لرحلتك الآن</h1>
                                    <p>استمتع في المغرب</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- نهاية العرض التقديمي -->

<!-- بداية الميزات -->
<div class="container-fluid feature py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 style="color:#011468;" class="display-5 text-capitalize mb-3">الميزات الرئيسية</h1>
            <p class="mb-0">لوريم إيبسوم دولور سيت أميت، كونسيكتيتور أديبيسينغ إليت. أوت أميت نيمو إكسبيديتا
                أسبريريس كومودي أكرسانتيوم أت كيوم هاروم، إكسبيتوري، كويا تيمبورا كوبيديتات! أدبيسكي فاسيليس
                مودي كويزكيام كويا ديستينكتيو،
            </p>
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
                                <h5 class="mb-3">خدمات من الدرجة الأولى</h5>
                                <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت.
                                    كونسيكتيتور، في إلوم أبيريام أولام ماجني إليجيندي؟</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 wow fadeInUp" data-wow-delay="0.3s">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <span class="fa fa-road fa-2x"></span>
                            </div>
                            <div class="ms-4">
                                <h5 class="mb-3">مساعدة على الطريق 24/7</h5>
                                <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت.
                                    كونسيكتيتور، في إلوم أبيريام أولام ماجني إليجيندي؟</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-12 col-xl-4 wow fadeInUp" data-wow-delay="0.2s">
                <img src="../img/public.avif" class="img-fluid w-100" style="object-fit: cover;" alt="صورة">
            </div>
            <div class="col-xl-4">
                <div class="row gy-4 gx-0">
                    <div class="col-12 wow fadeInUp" data-wow-delay="0.1s">
                        <div class="feature-item justify-content-end">
                            <div class="text-end me-4">
                                <h5 class="mb-3">جودة بحد أدنى</h5>
                                <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت.
                                    كونسيكتيتور، في إلوم أبيريام أولام ماجني إليجيندي؟</p>
                            </div>
                            <div class="feature-icon">
                                <span class="fa fa-tag fa-2x"></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 wow fadeInUp" data-wow-delay="0.3s">
                        <div class="feature-item justify-content-end">
                            <div class="text-end me-4">
                                <h5 class="mb-3">استلام وإرجاع مجاني</h5>
                                <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت.
                                    كونسيكتيتور، في إلوم أبيريام أولام ماجني إليجيندي؟</p>
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
<!-- نهاية الميزات -->

<!-- بداية قسم من نحن -->
<div class="container-fluid overflow-hidden about py-5">
    <div class="container py-5">
        <div class="row g-5">
            <div class="col-xl-6 wow fadeInLeft" data-wow-delay="0.2s">
                <div class="about-item">
                    <div class="pb-5">
                        <h1 style="color: #011468;" class="display-5 text-capitalize">من نحن في REIMS Cars</h1>
                        <p class="mb-0">لوريم إيبسوم دولور سيت أميت، كونسيكتيتور أديبيسينغ إليت. أوت أميت نيمو إكسبيديتا
                            أسبريريس كومودي أكرسانتيوم أت كيوم هاروم، إكسبيتوري، كويا تيمبورا كوبيديتات! أدبيسكي فاسيليس
                            مودي كويزكيام كويا ديستينكتيو،
                        </p>
                    </div>
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="about-item-inner border p-4">
                                <div class="about-icon mb-4">
                                    <img src="../img/about-icon-1.png" class="img-fluid w-50 h-50" alt="أيقونة">
                                </div>
                                <h5 class="mb-3">رؤيتنا</h5>
                                <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت.</p>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="about-item-inner border p-4">
                                <div class="about-icon mb-4">
                                    <img src="../img/about-icon-2.png" class="img-fluid h-50 w-50" alt="أيقونة">
                                </div>
                                <h5 class="mb-3">مهمتنا</h5>
                                <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت.</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-item my-4">لوريم، إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت. بيتاي،
                        أليكوام إيبسوم. سيد سوسكيبيت دولوريم ليبرو سيكوي أوت ناتوس ديبيتيس ريبريهينديريت فاسيليس
                        كوارات سي
                    </p>
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="text-center rounded bg-custom-secondary p-4">
                                <h1 class="display-6 text-white">17</h1>
                                <h5 class="text-light mb-0">سنوات من الخبرة</h5>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="rounded">
                                <p class="mb-2"><i class="fa fa-check-circle text-secondary me-1"></i> موربي تريستيك
                                    سينيكتوس</p>
                                <p class="mb-2"><i class="fa fa-check-circle text-secondary me-1"></i> إسكيليريسك
                                    بوروس</p>
                                <p class="mb-2"><i class="fa fa-check-circle text-secondary me-1"></i> ديكتومست
                                    فيستيبولوم</p>
                                <p class="mb-0"><i class="fa fa-check-circle text-secondary me-1"></i> ديو إينيان سيد
                                    أدبيسينغ</p>
                            </div>
                        </div>
                        <div class="col-lg-5 d-flex align-items-center">
                            <a href="#" class="btn btn-secondary rounded py-3 px-5">تعرف على المزيد عنا</a>
                        </div>
                        <style>
                            .btn-secondary {
                                background-color: #011468 !important;
                                /* لون الخلفية الافتراضي */
                                border-color: white !important;
                                /* لون الحدود الافتراضي */
                                color: white !important;
                                /* لون النص الافتراضي */
                                transition: all 0.3s ease-in-out;
                            }

                            .btn-secondary:hover {
                                background-color: white !important;
                                /* لون الخلفية عند التمرير */
                                border-color: #011468 !important;
                                /* لون الحدود عند التمرير */
                                color: #011468 !important;
                                /* لون النص عند التمرير */
                            }
                        </style>
                        <div class="col-lg-7">
                            <div class="d-flex align-items-center">
                                <img src="../img/attachment-img.jpg"
                                    class="img-fluid rounded-circle border border-4 border-secondary"
                                    style="width: 100px; height: 100px;" alt="صورة">
                                <div class="ms-4">
                                    <h4>ويليام بورغيس</h4>
                                    <p class="mb-0">مؤسس Carveo</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 wow fadeInRight" data-wow-delay="0.2s">
                <div class="about-img">
                    <div class="img-1">
                        <img src="../img/give-keys2.jpg" class="img-fluid rounded h-100 w-100" alt="">
                    </div>
                    <div class="img-2">
                        <img src="../img/back5.jpeg" class="img-fluid rounded w-100" alt="">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    .about-img::before,
    .about-img::after {
        background-color: #011468 !important;
    }
</style>
<!-- نهاية قسم من نحن -->

<!-- بداية عداد الحقائق -->
<div class="container-fluid counter bg-secondary py-5">
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
                    <h4 class="text-white mb-0">عملاء راضون</h4>
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
                    <h4 class="text-white mb-0">عدد السيارات</h4>
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
                    <h4 class="text-white mb-0">مراكز السيارات</h4>
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
                    <h4 class="text-white mb-0">إجمالي الكيلومترات</h4>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- نهاية عداد الحقائق -->

<!-- بداية الخدمات -->
<div class="container-fluid service py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 style="color: #011468;" class="display-5 text-capitalize mb-3">الخدمات الرئيسية</h1>
            <p class="mb-0">لوريم إيبسوم دولور سيت أميت، كونسيكتيتور أديبيسينغ إليت. أوت أميت نيمو إكسبيديتا
                أسبريريس كومودي أكرسانتيوم أت كيوم هاروم، إكسبيتوري، كويا تيمبورا كوبيديتات! أدبيسكي فاسيليس
                مودي كويزكيام كويا ديستينكتيو،
            </p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.1s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-phone-alt fa-2x"></i>
                    </div>
                    <h5 class="mb-3">الحجز عبر الهاتف</h5>
                    <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت. ريبريهينديريت إيبسام
                        كواسي كويبوسدام إيبسا بيرفيرينديس إيوستو؟</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.3s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-money-bill-alt fa-2x"></i>
                    </div>
                    <h5 class="mb-3">أسعار خاصة</h5>
                    <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت. ريبريهينديريت إيبسام
                        كواسي كويبوسدام إيبسا بيرفيرينديس إيوستو؟</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.5s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-road fa-2x"></i>
                    </div>
                    <h5 class="mb-3">تأجير باتجاه واحد</h5>
                    <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت. ريبريهينديريت إيبسام
                        كواسي كويبوسدام إيبسا بيرفيرينديس إيوستو؟</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.1s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-umbrella fa-2x"></i>
                    </div>
                    <h5 class="mb-3">تأمين الحياة</h5>
                    <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت. ريبريهينديريت إيبسام
                        كواسي كويبوسدام إيبسا بيرفيرينديس إيوستو؟</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.3s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-building fa-2x"></i>
                    </div>
                    <h5 class="mb-3">من مدينة إلى مدينة</h5>
                    <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت. ريبريهينديريت إيبسام
                        كواسي كويبوسدام إيبسا بيرفيرينديس إيوستو؟</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.5s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-car-alt fa-2x"></i>
                    </div>
                    <h5 class="mb-3">رحلات مجانية</h5>
                    <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت. ريبريهينديريت إيبسام
                        كواسي كويبوسدام إيبسا بيرفيرينديس إيوستو؟</p>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- نهاية الخدمات -->

<!-- بداية خطوات التأجير -->
<div class="container-fluid steps py-5">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize text-white mb-3">العملية الرئيسية</h1>
            <p class="mb-0 text-white">لوريم إيبسوم دولور سيت أميت، كونسيكتيتور أديبيسينغ إليت. أوت أميت نيمو إكسبيديتا
                أسبريريس كومودي أكرسانتيوم أت كيوم هاروم، إكسبيتوري، كويا تيمبورا كوبيديتات! أدبيسكي فاسيليس
                مودي كويزكيام كويا ديستينكتيو،
            </p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.1s">
                <div class="steps-item p-4 mb-4">
                    <h4>الاتصال بنا</h4>
                    <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت. أد، دولوريم!</p>
                    <div class="setps-number">01.</div>
                </div>
            </div>
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.3s">
                <div class="steps-item p-4 mb-4">
                    <h4>اختيار السيارة</h4>
                    <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت. أد، دولوريم!</p>
                    <div class="setps-number">02.</div>
                </div>
            </div>
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.5s">
                <div class="steps-item p-4 mb-4">
                    <h4>الاستمتاع بالقيادة</h4>
                    <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسينغ إليت. أد، دولوريم!</p>
                    <div class="setps-number">03.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- نهاية خطوات التأجير -->

<!-- بداية الفريق -->
<div class="container-fluid team pb-5" style="padding-top: 100px;">
    <div class="container pb-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize mb-3">مركز دعم العملاء <span class="text-secondary"> </span></h1>
            <p class="mb-0">لوريم إيبسوم دولور سيت أميت، كونسيكتيتور أديبيسيسينغ إيليت. أوت أميت نيمو إكسبيديتي
                أسبيريويريس كوميودي أكوسانتيم أَت كوم هاروم، إكسبتوري، كويّا تيمبورا كوبيديتاتي! أديبيسكي فاسيليس
                مودي كويشوام كويّا ديستينكشيو،
            </p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.1s">
                <div class="team-item p-4 pt-0">
                    <div class="team-img">
                        <img src="../img/team-1.jpg" class="img-fluid rounded w-100" alt="Image">
                    </div>
                    <div class="team-content pt-4">
                        <h4>مارتن دو</h4>
                        <p>المهنة</p>
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
                        <img src="../img/team-2.jpg" class="img-fluid rounded w-100" alt="Image">
                    </div>
                    <div class="team-content pt-4">
                        <h4>مارتن دو</h4>
                        <p>المهنة</p>
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
                        <img src="../img/team-3.jpg" class="img-fluid rounded w-100" alt="Image">
                    </div>
                    <div class="team-content pt-4">
                        <h4>مارتن دو</h4>
                        <p>المهنة</p>
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
                        <img src="../img/team-4.jpg" class="img-fluid rounded w-100" alt="Image">
                    </div>
                    <div class="team-content pt-4">
                        <h4>مارتن دو</h4>
                        <p>المهنة</p>
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
<!-- نهاية الفريق -->

<style>
    .owl-carousel .owl-dots {
        display: none !important;
    }
</style>

<!-- بداية الشهادات -->
<div class="container-fluid testimonial pb-5">
    <div class="container pb-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize mb-3">آراء <span class="text-secondary">عملائنا</span></h1>
            <p class="mb-0">لوريم إيبسوم دولور سيت أميت، كونسيكتيتور أديبيسيسينغ إيليت. أوت أميت نيمو إكسبيديتي
                أسبيريويريس كوميودي أكوسانتيم أَت كوم هاروم، إكسبتوري، كويّا تيمبورا كوبيديتاتي! أديبيسكي فاسيليس
                مودي كويشوام كويّا ديستينكشيو،
            </p>
        </div>
        <style>
            .d-flex.text-primary i {
                color: gold !important;
                /* يجعل جميع النجوم ذهبية */
            }

            .d-flex.text-primary .text-body {
                color: silver !important;
                /* يغير اللون للنجمة الأخيرة */
            }
        </style>
        <div class="owl-carousel testimonial-carousel wow fadeInUp" data-wow-delay="0.1s">
            <div class="testimonial-item">
                <div class="testimonial-quote"><i class="fa fa-quote-right fa-2x"></i>
                </div>
                <div class="testimonial-inner p-4">
                    <img src="../img/testimonial-1.jpg" class="img-fluid" alt="">
                    <div class="ms-4">
                        <h4>اسم الشخص</h4>
                        <p>المهنة</p>
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
                    <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسيسينغ إيليت. كوام سولوتا نيكوي
                        آب ريبوديندي ريهبرينديدي إيبسوم إيوس كومكوي إيسي ريبيليندوس إمبيديتي.</p>
                </div>
            </div>
            <div class="testimonial-item">
                <div class="testimonial-quote"><i class="fa fa-quote-right fa-2x"></i>
                </div>
                <div class="testimonial-inner p-4">
                    <img src="../img/testimonial-2.jpg" class="img-fluid" alt="">
                    <div class="ms-4">
                        <h4>اسم الشخص</h4>
                        <p>المهنة</p>
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
                    <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسيسينغ إيليت. كوام سولوتا نيكوي
                        آب ريبوديندي ريهبرينديدي إيبسوم إيوس كومكوي إيسي ريبيليندوس إمبيديتي.</p>
                </div>
            </div>
            <div class="testimonial-item">
                <div class="testimonial-quote"><i class="fa fa-quote-right fa-2x"></i>
                </div>
                <div class="testimonial-inner p-4">
                    <img src="../img/testimonial-3.jpg" class="img-fluid" alt="">
                    <div class="ms-4">
                        <h4>اسم الشخص</h4>
                        <p>المهنة</p>
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
                    <p class="mb-0">لوريم إيبسوم دولور سيت أميت كونسيكتيتور أديبيسيسينغ إيليت. كوام سولوتا نيكوي
                        آب ريبوديندي ريهبرينديدي إيبسوم إيوس كومكوي إيسي ريبيليندوس إمبيديتي.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- نهاية الشهادات -->


<?php
include("footer_p.php") ?>