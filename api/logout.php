<?php
require_once 'config.php';

// 获取当前用户的auth_token
$auth_token = $_COOKIE['auth_token'] ?? '';

if ($auth_token) {
    try {
        // 从数据库中删除token
        $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE token = ?");
        $stmt->execute([$auth_token]);
        
        // 删除浏览器cookie
        setcookie('auth_token', '', time() - 3600, '/');
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '登出失败：' . $e->getMessage()]);
    }
} else {
    // 用户本来就未登录的情况
    echo json_encode(['success' => true]);
} 