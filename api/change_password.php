<?php
require_once 'config.php';

$auth_token = $_COOKIE['auth_token'] ?? '';
if (!$auth_token) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.* 
    FROM users u 
    JOIN user_tokens t ON u.id = t.user_id 
    WHERE t.token = ?
    LIMIT 1
");
$stmt->execute([$auth_token]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

$current_password = $_POST['current_password'] ?? '';
if (!password_verify($current_password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => '当前密码错误']);
    exit;
}

$new_password = $_POST['new_password'] ?? '';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
$result = $stmt->execute([$hashed_password, $user['id']]);

echo json_encode(['success' => $result]); 