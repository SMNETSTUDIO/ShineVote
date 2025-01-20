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

// 获取候选人和投票数据
$stmt = $pdo->query("SELECT c.*, COUNT(v.id) as vote_count 
                     FROM candidates c 
                     LEFT JOIN votes v ON c.id = v.candidate_id 
                     GROUP BY c.id");
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
        <h2 class="mb-4">实时投票结果</h2>
        <div id="voteBarChart"></div>
        
        <div class="row mt-5">
            <?php foreach ($candidates as $candidate): ?>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <img src="<?= htmlspecialchars($candidate['image_url']) ?>" class="card-img-top">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($candidate['name']) ?></h5>
                        <p class="vote-count">当前票数: <span id="votes-<?= $candidate['id'] ?>"><?= $candidate['vote_count'] ?></span></p>
                        <button class="btn btn-primary vote-btn" data-id="<?= $candidate['id'] ?>">投票</button>
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
    setInterval(updateVotes, 5000);

    // 投票按钮点击事件
    document.querySelectorAll('.vote-btn').forEach(btn => {
        btn.addEventListener('click', function() {
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
                    updateVotes();
                }
            });
        });
    });
    </script>
</body>
</html>
