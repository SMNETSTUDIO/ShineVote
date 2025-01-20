<?php
$host = '34.92.145.166:26481';
$dbname = 'vote';
$username = 'avnadmin';
$password = 'AVNS_eUddmTXxsj0qVM33mSx';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("连接失败: " . $e->getMessage());
} 