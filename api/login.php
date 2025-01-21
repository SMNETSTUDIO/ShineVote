<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $token = uniqid(rand(), true);
            $stmt = $pdo->prepare("INSERT INTO user_tokens (user_id, token) VALUES (?, ?)");
            $stmt->execute([$user['id'], $token]);
            setcookie('auth_token', $token, time() + (7 * 24 * 60 * 60), '/');
            
            if ($user['is_admin']) {
                header('Location: /api/admin.php');
            } else {
                header('Location: /api/dash.php');
            }
            exit;
        } else {
            $error = '用户名或密码错误！';
        }
    } catch(PDOException $e) {
        $error = '系统错误，请稍后再试！';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>投票系统 - 登录</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f0f2f5 0%, #e6e9f0 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .card {
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }
        .card-header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2c3e50 100%);
            border-radius: 16px 16px 0 0;
            padding: 1.5rem;
        }
        .card-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 10px;
            padding: 0.8rem 1.2rem;
            border: 1px solid #e0e0e0;
            background-color: #f8f9fa;
        }
        .form-control:focus {
            border-color: #0056b3;
            box-shadow: 0 0 0 0.2rem rgba(0, 86, 179, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #0056b3 0%, #0088ff 100%);
            border: none;
            padding: 0.8rem;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .btn-primary:hover {
            background-color: #003d82;
            border-color: #003d82;
            transform: translateY(-2px);
        }
        .alert {
            border: none;
            border-radius: 8px;
        }
        .system-title {
            text-align: center;
            background: linear-gradient(135deg, #1a1a1a 0%, #2c3e50 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 2rem;
        }
        label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <h1 class="system-title">投票系统</h1>
                <div class="card">
                    <div class="card-header">用户登录</div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-4">
                                <label>用户名</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-4">
                                <label>密码</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">登 录</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 