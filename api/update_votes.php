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
$admin = $stmt->fetch();

if (!$admin) {
    echo json_encode(['success' => false, 'message' => '无权限']);
    exit;
}

// 获取参数
$candidate_id = $_POST['candidate_id'] ?? '';
$votes = $_POST['votes'] ?? '';

if (!$candidate_id || !is_numeric($votes) || $votes < 0) {
    echo json_encode(['success' => false, 'message' => '参数无效']);
    exit;
}

try {
    // 开始事务
    $pdo->beginTransaction();
    
    // 先删除现有票数
    $stmt = $pdo->prepare("DELETE FROM votes WHERE candidate_id = ?");
    $stmt->execute([$candidate_id]);
    
    // 获取系统管理员用户ID
    $admin_id = $admin['id'];
    
    // 插入新的票数
    $stmt = $pdo->prepare("INSERT INTO votes (candidate_id, user_id) VALUES (?, ?)");
    for ($i = 0; $i < $votes; $i++) {
        $stmt->execute([$candidate_id, $admin_id]); // 使用管理员ID代替0
    }
    
    // 提交事务
    $pdo->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // 发生错误时回滚
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}