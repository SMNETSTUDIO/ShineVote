<?php
require_once 'config.php';

// 验证管理员权限
$auth_token = $_COOKIE['auth_token'] ?? '';
if (!$auth_token) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.* 
    FROM users u 
    JOIN user_tokens t ON u.id = t.user_id 
    WHERE t.token = ? AND u.is_admin = 1
    LIMIT 1
");
$stmt->execute([$auth_token]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => '无权限']);
    exit;
}

// 更新用户权限
$user_id = $_POST['user_id'] ?? '';
$is_admin = $_POST['is_admin'] ?? '';

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
$result = $stmt->execute([$is_admin, $user_id]);

echo json_encode(['success' => $result]); 