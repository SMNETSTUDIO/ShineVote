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

// 删除候选人
$id = $_POST['id'] ?? '';

if (!$id) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

// 开始事务
$pdo->beginTransaction();

try {
    // 先删除相关的投票记录
    $stmt = $pdo->prepare("DELETE FROM votes WHERE candidate_id = ?");
    $stmt->execute([$id]);
    
    // 再删除候选人
    $stmt = $pdo->prepare("DELETE FROM candidates WHERE id = ?");
    $stmt->execute([$id]);
    
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 