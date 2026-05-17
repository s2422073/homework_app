<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$host = 'localhost';
$db   = 'reosato';
$user = 'reosato';
$pass = 'U7Q3MHJl';
$dsn  = "pgsql:host=$host;dbname=$db";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    echo 'データベース接続失敗: ' . $e->getMessage();
    exit;
}
?>