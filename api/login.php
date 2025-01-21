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
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .card {
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            background-color: #1a1a1a;
            color: white;
            font-size: 1.25rem;
            font-weight: bold;
            padding: 1rem;
            text-align: center;
        }
        .card-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 8px;
            padding: 0.75rem;
            border: 1px solid #ced4da;
        }
        .form-control:focus {
            border-color: #0056b3;
            box-shadow: 0 0 0 0.2rem rgba(0, 86, 179, 0.25);
        }
        .btn-primary {
            background-color: #0056b3;
            border-color: #0056b3;
            padding: 0.75rem;
            width: 100%;
            font-weight: bold;
            border-radius: 8px;
            transition: all 0.3s;
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
            color: #1a1a1a;
            font-size: 2rem;
            font-weight: bold;
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