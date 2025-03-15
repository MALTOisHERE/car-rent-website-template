<?php
try {
    $mysqlconnection = new PDO(
        'mysql:host=localhost;port=3306;dbname=rental_car;charset=utf8',
        'root', // Nom d'utilisateur
        ''     // Mot de passe
    );
    $mysqlconnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Activer les exceptions pour les erreurs PDO
} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}
?>