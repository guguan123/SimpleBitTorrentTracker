<?php
// 从配置文件获取数据库信息
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

// 创建连接
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// 检查连接
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// 查询当前活跃的 torrents 数量
$sqlTorrents = "SELECT COUNT(*) as total FROM peers";
$resultTorrents = $conn->query($sqlTorrents);

if ($resultTorrents === false) {
  die("Error executing query: " . $conn->error);
}

$totalTorrents = $resultTorrents->fetch_assoc()['total'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GuGuan123's BitTorrent Tracker Status</title>
</head>
<body>
    <h1>BitTorrent Tracker Status</h1>
    <p><strong>Database list:</strong> <?php echo $totalTorrents; ?></p>
    <p>Tracker URL: <i>http://tracker.guguan.000.pe/announce</i></p>
</body>
</html>