<?php 
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'hotel_luxe';

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    error_log('Erreur de connexion: ' . mysqli_connect_error());
    exit('Erreur de connexion à la base de données.');
}

mysqli_set_charset($conn, 'utf8');
?>
