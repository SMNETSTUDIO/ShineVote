<?php
require_once 'config.php';
$auth_token = $_COOKIE['auth_token'] ?? '';

if ($auth_token) {
    try {
        $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE token = ?");
        $stmt->execute([$auth_token]);
        setcookie('auth_token', '', time() - 3600, '/');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '登出失败：' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => true]);
} 