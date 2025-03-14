<?php
$active = "index";
include("header_p.php") ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<!-- Custom CSS for SweetAlert2 Button -->
<style>
    .swal2-confirm {
        background-color: #011468 !important;
        border-color: #011468 !important;
    }

    .swal2-confirm:hover {
        background-color: #012080 !important;
        border-color: #012080 !important;
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
            icon: 'success', // You can change this to 'error' for error messages
            confirmButtonText: 'OK',
            customClass: {
                confirmButton: 'swal2-confirm' // Apply custom class to the confirm button
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
                <img src="../img/back4.webp" class="img-fluid w-100" alt="First slide" />
                <div class="carousel-caption">
                    <div class="container py-4">
                        <div class="row g-5">
                            <div class="col-lg-6 fadeInLeft animated" data-animation="fadeInLeft" data-delay="1s"
                                style="animation-delay: 1s;">
                                <div style="background-color: #011468;" class=" rounded p-5">
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
                                    <h1 class="display-5 text-white">Get 15% off your rental Plan your trip now</h1>
                                    <p>Treat yourself in Morocco</p>
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
            <h1 style="color:#011468;" class="display-5 text-capitalize mb-3">REIMS Cars Features</h1>
            <p class="mb-0">Discover the exceptional services and features that set us apart. We are committed to providing top-notch solutions tailored to meet your needs, ensuring a seamless and satisfying experience every time.</p>
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
                                <h5 class="mb-3">First-Class Services</h5>
                                <p class="mb-0">Experience unparalleled service with our dedicated team, ensuring your needs are met with professionalism and care.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 wow fadeInUp" data-wow-delay="0.3s">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <span class="fa fa-road fa-2x"></span>
                            </div>
                            <div class="ms-4">
                                <h5 class="mb-3">24/7 Road Assistance</h5>
                                <p class="mb-0">We’ve got you covered, day or night. Our round-the-clock road assistance ensures you’re never stranded.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-12 col-xl-4 wow fadeInUp" data-wow-delay="0.2s">
                <img src="../img/public.avif" class="img-fluid w-100" style="object-fit: cover;" alt="Img">
            </div>
            <div class="col-xl-4">
                <div class="row gy-4 gx-0">
                    <div class="col-12 wow fadeInUp" data-wow-delay="0.1s">
                        <div class="feature-item justify-content-end">
                            <div class="text-end me-4">
                                <h5 class="mb-3">Quality at Minimum Cost</h5>
                                <p class="mb-0">Enjoy premium services without breaking the bank. We deliver exceptional value at competitive prices.</p>
                            </div>
                            <div class="feature-icon">
                                <span class="fa fa-tag fa-2x"></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 wow fadeInUp" data-wow-delay="0.3s">
                        <div class="feature-item justify-content-end">
                            <div class="text-end me-4">
                                <h5 class="mb-3">Free Pick-Up & Drop-Off</h5>
                                <p class="mb-0">Convenience at your fingertips. We offer free pick-up and drop-off services to make your life easier.</p>
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
                        <h1 style="color: #011468;" class="display-5 text-capitalize">REIMS Cars About</h1>
                        <p class="mb-0">At REIMS Cars, we are passionate about delivering exceptional automotive experiences. With years of expertise, we provide top-quality vehicles and services tailored to meet your needs. Our commitment to excellence ensures that every customer drives away satisfied.</p>
                    </div>
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="about-item-inner border p-4">
                                <div class="about-icon mb-4">
                                    <img src="../img/about-icon-1.png" class="img-fluid w-50 h-50" alt="Icon">
                                </div>
                                <h5 class="mb-3">Our Vision</h5>
                                <p class="mb-0">To revolutionize the automotive industry by offering innovative solutions and unparalleled customer service.</p>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="about-item-inner border p-4">
                                <div class="about-icon mb-4">
                                    <img src="../img/about-icon-2.png" class="img-fluid h-50 w-50" alt="Icon">
                                </div>
                                <h5 class="mb-3">Our Mission</h5>
                                <p class="mb-0">To provide high-quality vehicles and services that exceed customer expectations while fostering long-term relationships.</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-item my-4">With a focus on innovation, reliability, and customer satisfaction, REIMS Cars has become a trusted name in the automotive industry. Our team of experts is dedicated to helping you find the perfect vehicle for your lifestyle and needs.</p>
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="text-center rounded bg-custom-secondary p-4">
                                <h1 class="display-6 text-white">17</h1>
                                <h5 class="text-light mb-0">Years Of Experience</h5>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="rounded">
                                <p class="mb-2"><i class="fa fa-check-circle text-secondary me-1"></i> Wide selection of premium vehicles</p>
                                <p class="mb-2"><i class="fa fa-check-circle text-secondary me-1"></i> Certified pre-owned options</p>
                                <p class="mb-2"><i class="fa fa-check-circle text-secondary me-1"></i> Transparent pricing and financing</p>
                                <p class="mb-0"><i class="fa fa-check-circle text-secondary me-1"></i> Exceptional after-sales support</p>
                            </div>
                        </div>
                        <div class="col-lg-5 d-flex align-items-center">
                            <a href="#" class="btn btn-secondary rounded py-3 px-5">More About Us</a>
                        </div>
                        <style>
                            .btn-secondary {
                                background-color: #011468 !important;
                                border-color: white !important;
                                color: white !important;
                                transition: all 0.3s ease-in-out;
                            }

                            .btn-secondary:hover {
                                background-color: white !important;
                                border-color: #011468 !important;
                                color: #011468 !important;
                            }
                        </style>
                        <div class="col-lg-7">
                            <div class="d-flex align-items-center">
                                <img src="../img/attachment-img.jpg"
                                    class="img-fluid rounded-circle border border-4 border-secondary"
                                    style="width: 100px; height: 100px;" alt="Image">
                                <div class="ms-4">
                                    <h4>William Burgess</h4>
                                    <p class="mb-0">REIMS Cars Founder</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 wow fadeInRight" data-wow-delay="0.2s">
                <div class="about-img">
                    <div class="img-1">
                        <img src="../img/give-keys2.jpg" class="img-fluid rounded h-100 w-100" alt="Customer receiving car keys">
                    </div>
                    <div class="img-2">
                        <img src="../img/back5.jpeg" class="img-fluid rounded w-100" alt="Luxury car showcase">
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
                        <span class="text-white fs-2 fw-bold" data-toggle="counter-up">829</span>
                        <span class="h1 fw-bold text-white">+</span>
                    </div>
                    <h4 class="text-white mb-0">Happy Clients</h4>
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
                    <h4 class="text-white mb-0">Number of Cars</h4>
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
                    <h4 class="text-white mb-0">Car Center</h4>
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
                    <h4 class="text-white mb-0">Total kilometers</h4>
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
            <h1 style="color: #011468;" class="display-5 text-capitalize mb-3">Central Services</h1>
            <p class="mb-0">At REIMS Cars, we offer a wide range of services designed to make your car rental experience seamless and enjoyable. From convenient reservations to flexible rental options, we’ve got you covered every step of the way.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.1s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-phone-alt fa-2x"></i>
                    </div>
                    <h5 class="mb-3">Phone Reservation</h5>
                    <p class="mb-0">Book your car effortlessly over the phone. Our friendly team is available 24/7 to assist with your reservations and answer any questions.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.3s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-money-bill-alt fa-2x"></i>
                    </div>
                    <h5 class="mb-3">Special Rates</h5>
                    <p class="mb-0">Enjoy exclusive discounts and special rates tailored for long-term rentals, corporate clients, and frequent travelers.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.5s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-road fa-2x"></i>
                    </div>
                    <h5 class="mb-3">One-Way Rental</h5>
                    <p class="mb-0">Need to drop off your rental car at a different location? Our one-way rental service offers flexibility and convenience.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.1s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-umbrella fa-2x"></i>
                    </div>
                    <h5 class="mb-3">Comprehensive Insurance</h5>
                    <p class="mb-0">Drive with peace of mind. Our insurance plans cover all your needs, ensuring you’re protected on every journey.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.3s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-building fa-2x"></i>
                    </div>
                    <h5 class="mb-3">City-to-City Transfers</h5>
                    <p class="mb-0">Travel between cities with ease. Our city-to-city transfer service ensures a smooth and hassle-free experience.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.5s">
                <div class="service-item p-4">
                    <div class="service-icon mb-4">
                        <i class="fa fa-car-alt fa-2x"></i>
                    </div>
                    <h5 class="mb-3">Free Pick-Up & Drop-Off</h5>
                    <p class="mb-0">We offer free pick-up and drop-off services to make your rental experience as convenient as possible.</p>
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
            <h1 class="display-5 text-capitalize text-white mb-3">Central Process</h1>
            <p class="mb-0 text-white">At REIMS Cars, we’ve streamlined the car rental process to make it simple, fast, and hassle-free. Follow these easy steps to get behind the wheel of your dream car in no time.</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.1s">
                <div class="steps-item p-4 mb-4">
                    <h4>Get In Touch</h4>
                    <p class="mb-0">Contact us via phone, email, or visit our website to start your car rental journey. Our team is ready to assist you with any inquiries.</p>
                    <div class="setps-number">01.</div>
                </div>
            </div>
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.3s">
                <div class="steps-item p-4 mb-4">
                    <h4>Choose Your Car</h4>
                    <p class="mb-0">Browse our wide selection of vehicles and pick the one that suits your needs. From compact cars to luxury SUVs, we have it all.</p>
                    <div class="setps-number">02.</div>
                </div>
            </div>
            <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.5s">
                <div class="steps-item p-4 mb-4">
                    <h4>Hit the Road</h4>
                    <p class="mb-0">Once you’ve completed the booking process, it’s time to enjoy your ride. Drive with confidence and make unforgettable memories.</p>
                    <div class="setps-number">03.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Car Steps End -->
<!-- Car Steps End -->

<!-- Team Start -->
<div class="container-fluid team pb-5" style="padding-top: 100px;">
    <div class="container pb-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
            <h1 class="display-5 text-capitalize mb-3">Customer Support <span class="text-secondary"> Center</span></h1>
            <p class="mb-0">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Ut amet nemo expedita
                asperiores commodi accusantium at cum harum, excepturi, quia tempora cupiditate! Adipisci facilis
                modi quisquam quia distinctio,
            </p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.1s">
                <div class="team-item p-4 pt-0">
                    <div class="team-img">
                        <img src="../img/team-1.jpg" class="img-fluid rounded w-100" alt="Image">
                    </div>
                    <div class="team-content pt-4">
                        <h4>MARTIN DOE</h4>
                        <p>Profession</p>
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
                        <h4>MARTIN DOE</h4>
                        <p>Profession</p>
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
                        <h4>MARTIN DOE</h4>
                        <p>Profession</p>
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
                        <h4>MARTIN DOE</h4>
                        <p>Profession</p>
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
            <h1 class="display-5 text-capitalize mb-3">Our Clients<span class="text-secondary"> Riviews</span></h1>
            <p class="mb-0">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Ut amet nemo expedita
                asperiores commodi accusantium at cum harum, excepturi, quia tempora cupiditate! Adipisci facilis
                modi quisquam quia distinctio,
            </p>
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
            <div class="testimonial-item">
                <div class="testimonial-quote"><i class="fa fa-quote-right fa-2x"></i>
                </div>
                <div class="testimonial-inner p-4">
                    <img src="../img/testimonial-1.jpg" class="img-fluid" alt="">
                    <div class="ms-4">
                        <h4>Person Name</h4>
                        <p>Profession</p>
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
                    <p class="mb-0">Lorem ipsum dolor sit amet consectetur adipisicing elit. Quam soluta neque ab
                        repudiandae reprehenderit ipsum eos cumque esse repellendus impedit.</p>
                </div>
            </div>
            <div class="testimonial-item">
                <div class="testimonial-quote"><i class="fa fa-quote-right fa-2x"></i>
                </div>
                <div class="testimonial-inner p-4">
                    <img src="../img/testimonial-2.jpg" class="img-fluid" alt="">
                    <div class="ms-4">
                        <h4>Person Name</h4>
                        <p>Profession</p>
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
                    <p class="mb-0">Lorem ipsum dolor sit amet consectetur adipisicing elit. Quam soluta neque ab
                        repudiandae reprehenderit ipsum eos cumque esse repellendus impedit.</p>
                </div>
            </div>
            <div class="testimonial-item">
                <div class="testimonial-quote"><i class="fa fa-quote-right fa-2x"></i>
                </div>
                <div class="testimonial-inner p-4">
                    <img src="../img/testimonial-3.jpg" class="img-fluid" alt="">
                    <div class="ms-4">
                        <h4>Person Name</h4>
                        <p>Profession</p>
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
                    <p class="mb-0">Lorem ipsum dolor sit amet consectetur adipisicing elit. Quam soluta neque ab
                        repudiandae reprehenderit ipsum eos cumque esse repellendus impedit.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Testimonial End -->

<?php
include("footer_p.php") ?>