<?php
require_once 'config.php';

header('Content-Type: application/json');

$auth_token = $_COOKIE['auth_token'] ?? '';
if (!$auth_token) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
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
    echo json_encode(['success' => false, 'message' => '用户验证失败']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

$candidate_id = $_POST['candidate_id'] ?? '';
if (!$candidate_id) {
    echo json_encode(['success' => false, 'message' => '请选择候选人']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM votes WHERE user_id = ?");
$stmt->execute([$user['id']]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => '您已经投过票了']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => '无效的候选人']);
    exit;
}

$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $stmt->fetch();

if (!$settings['voting_enabled']) {
    echo json_encode(['success' => false, 'message' => '投票已暂停']);
    exit;
}

$now = new DateTime();
$startTime = new DateTime($settings['voting_start_time']);
$endTime = new DateTime($settings['voting_end_time']);

if ($now < $startTime) {
    echo json_encode(['success' => false, 'message' => '投票还未开始']);
    exit;
}

if ($now > $endTime) {
    echo json_encode(['success' => false, 'message' => '投票已结束']);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) as vote_count FROM votes WHERE user_id = ?");
$stmt->execute([$user['id']]);
$userVoteCount = $stmt->fetch()['vote_count'];

if ($userVoteCount >= $settings['max_votes_per_user']) {
    echo json_encode(['success' => false, 'message' => '您已达到最大投票次数']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO votes (user_id, candidate_id, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user['id'], $candidate_id]);
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => '投票成功']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 