<?php
require_once 'config.php';
$auth_token = $_COOKIE['auth_token'] ?? '';

if ($auth_token) {
    try {
        $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE token = ?");
        $stmt->execute([$auth_token]);
        setcookie('auth_token', '', time() - 3600, '/');
        $success = true;
    } catch (Exception $e) {
        $success = false;
        $error_message = '登出失败：' . $e->getMessage();
    }
} else {
    $success = true;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>退出登录</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f0f2f5 0%, #e6e9f0 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logout-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            text-align: center;
            width: 100%;
            max-width: 400px;
        }
        .icon-container {
            margin-bottom: 1.5rem;
        }
        .success-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #0056b3 0%, #0088ff 100%);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }
        .error-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #dc3545 0%, #ff4d5b 100%);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }
        .message {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: #1a1a1a;
        }
        .countdown {
            color: #666;
            font-size: 0.9rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading {
            animation: spin 2s linear infinite;
        }
    </style>
</head>
<body>
    <div class="logout-card">
        <div class="icon-container">
            <?php if ($success): ?>
                <div class="success-icon">✓</div>
            <?php else: ?>
                <div class="error-icon">✕</div>
            <?php endif; ?>
        </div>
        
        <div class="message">
            <?php if ($success): ?>
                退出登录成功
            <?php else: ?>
                <?= htmlspecialchars($error_message) ?>
            <?php endif; ?>
        </div>
        
        <?php if ($success): ?>
            <div class="countdown">
                <span id="countdown">3</span> 秒后返回登录页面
            </div>
        <?php endif; ?>
    </div>

    <script>
        <?php if ($success): ?>
        let count = 3;
        const countdownElement = document.getElementById('countdown');
        
        const countdown = setInterval(() => {
            count--;
            countdownElement.textContent = count;
            
            if (count <= 0) {
                clearInterval(countdown);
                window.location.href = 'login.php';
            }
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html> 