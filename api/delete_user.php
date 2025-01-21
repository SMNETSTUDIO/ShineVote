<?php
require_once 'config.php';

// 验证管理员权限
$auth_token = $_COOKIE['auth_token'] ?? '';
if (!$auth_token) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

// 验证是否为管理员
$stmt = $pdo->prepare("
    SELECT u.* 
    FROM users u 
    JOIN user_tokens t ON u.id = t.user_id 
    WHERE t.token = ? AND u.is_admin = 1
    LIMIT 1
");
$stmt->execute([$auth_token]);
$admin = $stmt->fetch();

if (!$admin) {
    echo json_encode(['success' => false, 'message' => '无权限']);
    exit;
}

// 获取要删除的用户ID
$user_id = $_POST['user_id'] ?? '';
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

try {
    // 开始事务
    $pdo->beginTransaction();
    
    // 先删除用户的投票记录
    $stmt = $pdo->prepare("DELETE FROM votes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // 删除用户的token
    $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // 最后删除用户
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
    // 提交事务
    $pdo->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // 发生错误时回滚事务
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => '删除失败：' . $e->getMessage()]);
} 