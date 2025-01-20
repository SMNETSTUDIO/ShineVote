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
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>投票系统</h2>
            <div>
                <span class="me-3">欢迎，<?= htmlspecialchars($user['username']) ?></span>
                <a href="logout.php" class="btn btn-outline-danger">退出登录</a>
            </div>
        </div>

        <?php if ($userVote): ?>
            <div class="alert alert-info">
                您已经完成投票，感谢参与！
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                您还未投票，请为您支持的候选人投票！
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title">实时投票结果</h4>
                <div id="voteBarChart" style="height: 300px;"></div>
            </div>
        </div>
        
        <div class="row">
            <?php foreach ($candidates as $candidate): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($candidate['name']) ?></h5>
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
