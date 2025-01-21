<?php
require_once 'config.php';

header('Content-Type: application/json');

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

$candidate_id = filter_input(INPUT_POST, 'candidate_id', FILTER_VALIDATE_INT);
$votes = filter_input(INPUT_POST, 'votes', FILTER_VALIDATE_INT);

if ($candidate_id === false || $candidate_id === null || $votes === false || $votes === null || $votes < 0) {
    echo json_encode(['success' => false, 'message' => '参数无效']);
    exit;
}

try {
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
    
    echo json_encode(['success' => true, 'message' => '更新成功']);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}