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

$name = $_POST['name'] ?? '';
if (!$name) {
    echo json_encode(['success' => false, 'message' => '请输入名称']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO candidates (name) VALUES (?)");
$result = $stmt->execute([$name]);

echo json_encode(['success' => $result]); 