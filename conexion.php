<?php
$host = 'sql202.infinityfree.com';
$db   = 'if0_43478245_agenda';
$user = 'if0_43478245';
$pass = 'pagina2026';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>