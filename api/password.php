<?php
$hash = '';
$password = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>密码哈希生成器</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">密码哈希生成器</div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label>输入密码</label>
                                <input type="text" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">生成哈希</button>
                        </form>
                        
                        <?php if ($hash): ?>
                        <div class="mt-4">
                            <h5>生成结果：</h5>
                            <div class="alert alert-info">
                                <p><strong>原始密码：</strong> <?= htmlspecialchars($password) ?></p>
                                <p><strong>哈希值：</strong> <?= htmlspecialchars($hash) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 