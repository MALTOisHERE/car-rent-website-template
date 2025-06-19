<?php
try {
    $mysqlconnection = new PDO(
        'mysql:host=localhost;port=3306;dbname=u301125912_rental_car;charset=utf8',
        'u301125912_Kab4', // Nom d'utilisateur
        'X:q491o:mnt'     // Mot de passe
    );
    $mysqlconnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Activer les exceptions pour les erreurs PDO
} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}
?>