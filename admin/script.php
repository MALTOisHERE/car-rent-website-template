<?php 

// Check if the user is logged in and has the admin role (role != 0)
if (!isset($_SESSION['role']) || $_SESSION['role'] == 0) {
    // Redirect to login page or show an error
    header('Location: ../index.php');
    exit();
}
?>
<!-- Jquery Core Js -->
<script src="plugins/jquery/jquery.min.js"></script>

<!-- Bootstrap Core Js -->
<script src="plugins/bootstrap/js/bootstrap.js"></script>

<!-- Select Plugin Js -->
<script src="plugins/bootstrap-select/js/bootstrap-select.js"></script>

<!-- Slimscroll Plugin Js -->
<script src="plugins/jquery-slimscroll/jquery.slimscroll.js"></script>

<!-- Waves Effect Plugin Js -->
<script src="plugins/node-waves/waves.js"></script>

<!-- Custom Js -->
<script src="js/admin.js"></script>

<!-- Demo Js -->
<script src="js/demo.js"></script>
</body>

</html>