<?php
require_once 'config.php';

// 验证token
$auth_token = $_COOKIE['auth_token'] ?? '';
if (!$auth_token) {
    header('Location: /api/login.php');
    exit;
}

// 通过token获取用户信息
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
    header('Location: login.php');
    exit;
}

// 检查用户是否已经投票
$stmt = $pdo->prepare("SELECT candidate_id FROM votes WHERE user_id = ?");
$stmt->execute([$user['id']]);
$userVote = $stmt->fetch();

// 获取候选人和投票数据
$stmt = $pdo->query("SELECT c.*, COUNT(v.id) as vote_count 
                     FROM candidates c 
                     LEFT JOIN votes v ON c.id = v.candidate_id 
                     GROUP BY c.id 
                     ORDER BY vote_count DESC");
$candidates = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>投票系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background-color: #1a1a1a;
            padding: 1rem;
            margin-bottom: 2rem;
        }
        .navbar-brand {
            color: white !important;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .user-info {
            color: #fff;
        }
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            background-color: #1a1a1a;
            color: white;
            font-weight: bold;
        }
        .btn-primary {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .btn-primary:hover {
            background-color: #003d82;
            border-color: #003d82;
        }
        .btn-outline-danger {
            color: #fff;
            border-color: #fff;
        }
        .btn-outline-danger:hover {
            background-color: #dc3545;
            color: #fff;
        }
        .vote-count {
            color: #0056b3;
            font-weight: bold;
        }
        .alert {
            border: none;
            border-radius: 8px;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <span class="navbar-brand">投票系统</span>
            <div class="user-info">
                <span class="me-3">欢迎，<?= htmlspecialchars($user['username']) ?></span>
                <a href="logout.php" class="btn btn-outline-danger">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($userVote): ?>
            <div class="alert alert-info">
                <i class="fas fa-check-circle"></i> 您已经完成投票，感谢参与！
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-circle"></i> 您还未投票，请为您支持的候选人投票！
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">实时投票结果</div>
            <div class="card-body chart-container">
                <canvas id="voteBarChart" style="height: 300px;"></canvas>
            </div>
        </div>
        
        <div class="row">
            <?php foreach ($candidates as $candidate): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header"><?= htmlspecialchars($candidate['name']) ?></div>
                    <div class="card-body">
                        <p class="card-text vote-count">当前票数: <span id="votes-<?= $candidate['id'] ?>"><?= $candidate['vote_count'] ?></span></p>
                        <button class="btn btn-primary vote-btn w-100" 
                                data-id="<?= $candidate['id'] ?>"
                                <?= $userVote ? 'disabled' : '' ?>>
                            <?= $userVote ? '已投票' : '投票' ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    // 初始化图表
    const chart = new Chart(document.getElementById('voteBarChart'), {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',  // 横向条形图
            animation: {
                duration: 1000  // 动画持续时间
            },
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });

    // 更新图表数据
    function updateChart(data) {
        // 按票数排序
        data.sort((a, b) => b.vote_count - a.vote_count);
        
        // 更新图表数据
        chart.data.labels = data.map(c => c.name);
        chart.data.datasets[0].data = data.map(c => c.vote_count);
        chart.update();
    }

    // 实时更新投票数据
    function updateVotes() {
        fetch('get_votes.php')
            .then(response => response.json())
            .then(data => {
                // 更新票数显示
                data.forEach(candidate => {
                    document.getElementById(`votes-${candidate.id}`).textContent = candidate.vote_count;
                });
                // 更新图表
                updateChart(data);
            });
    }

    // 每5秒更新一次数据
    setInterval(updateVotes, 500);

    // 投票按钮点击事件
    document.querySelectorAll('.vote-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('确定要投票给这位候选人吗？投票后将无法更改！')) {
                return;
            }
            
            const candidateId = this.dataset.id;
            fetch('vote.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `candidate_id=${candidateId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('投票成功！');
                    // 禁用所有投票按钮
                    document.querySelectorAll('.vote-btn').forEach(b => {
                        b.disabled = true;
                        b.textContent = '已投票';
                    });
                    updateVotes();
                } else {
                    alert(data.message || '投票失败，请稍后重试');
                }
            })
            .catch(error => {
                alert('系统错误，请稍后重试');
            });
        });
    });

    updateVotes();
    </script>
</body>
</html>
