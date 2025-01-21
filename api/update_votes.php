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
$candidate_id = filter_input(INPUT_POST, 'candidate_id', FILTER_VALIDATE_INT);
$votes = filter_input(INPUT_POST, 'votes', FILTER_VALIDATE_INT);

if ($candidate_id === false || $candidate_id === null || $votes === false || $votes === null || $votes < 0) {
    echo json_encode(['success' => false, 'message' => '参数无效']);
    exit;
}

try {
    // 开始事务
    $pdo->beginTransaction();
    
    // 验证候选人是否存在并更新票数
    $stmt = $pdo->prepare("
        UPDATE candidates 
        SET votes_count = ? 
        WHERE id = ?
    ");
    
    if (!$stmt->execute([$votes, $candidate_id])) {
        throw new Exception('更新失败');
    }
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('候选人不存在');
    }

    // 同时更新votes表中的记录
    $stmt = $pdo->prepare("DELETE FROM votes WHERE candidate_id = ?");
    $stmt->execute([$candidate_id]);

    // 插入新的投票记录
    for ($i = 0; $i < $votes; $i++) {
        $stmt = $pdo->prepare("INSERT INTO votes (candidate_id) VALUES (?)");
        $stmt->execute([$candidate_id]);
    }
    
    // 提交事务
    $pdo->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // 发生错误时回滚
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}