<?php
// 引入配置文件
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

// 创建数据库连接
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
// 检查数据库连接是否成功
if ($conn->connect_error) {
    die(bencode(['failure reason' => 'Database connection failed']));
}

// 从GET请求中获取必要的参数
$info_hash = $_GET['info_hash'] ?? null;
$peer_id = $_GET['peer_id'] ?? null;
$port = $_GET['port'] ?? null;
$ipv4 = $_GET['ipv4'] ?? null;
$ipv6 = $_GET['ipv6'] ?? null;
$downloaded = $_GET['downloaded'];
$left = $_GET['left'];
$uploaded = $_GET['uploaded'];
$supportcrypto = isset($_GET['supportcrypto']) ? 1 : 0;

// 检查必要参数是否存在
if (!$info_hash || !$peer_id || !$port) {
    echo bencode(['failure reason' => 'missing required parameters']);
    exit;
}

if (empty($ipv4) && empty($ipv6)) {
    // 如果客户端没报告IP地址信息，获取IPv4地址
    $ipv4 = $_SERVER['REMOTE_ADDR'];
}

// 准备SQL语句用于更新或插入peer信息
$updateSql = "INSERT INTO peers (info_hash, peer_id, ipv4, ipv6, port, downloaded, `left`, uploaded, supportcrypto, updated_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
              ON DUPLICATE KEY UPDATE ipv4=VALUES(ipv4), ipv6=VALUES(ipv6), port=VALUES(port), downloaded=VALUES(downloaded),
              `left`=VALUES(`left`), uploaded=VALUES(uploaded), supportcrypto=VALUES(supportcrypto), updated_at=NOW()";

try {
    // 执行SQL语句
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("ssssiiiii", $info_hash, $peer_id, $ipv4, $ipv6, $port, $downloaded, $left, $uploaded, $supportcrypto);
    $stmt->execute();

    // 删除超过1小时未更新的peers
    $delSql = "DELETE FROM peers WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $conn->query($delSql);

    // 获取当前info_hash下的所有peers（最多50个），排除当前peer_id
    $getSql = "SELECT ipv4, ipv6, port FROM peers WHERE info_hash = ? AND peer_id != ? LIMIT 50";
    $stmt = $conn->prepare($getSql);
    $stmt->bind_param("ss", $info_hash, $peer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $peers = [];
    while ($row = $result->fetch_assoc()) {
      $peerData = ["ipv4" => $row["ipv4"], "port" => $row["port"]];
      if ($row["ipv6"]) {
          $peerData["ipv6"] = $row["ipv6"];
      }
      $peers[] = $peerData;
    }

    // 构建并发送响应数据
    $response = [
        "interval" => 1800,  // 推荐的更新间隔时间（秒）
        "peers" => $peers
    ];

    echo bencode($response);

} catch (Exception $e) {
    echo bencode(['failure reason' => 'Server error']);
} finally {
    // 关闭statement和连接
    $stmt->close();
    $conn->close();
}

// 定义Bencode编码函数
function bencode($data) {
    // Bencode encoding logic
    if (is_string($data)) {
        return strlen($data) . ':' . $data;
    } elseif (is_int($data)) {
        return 'i' . $data . 'e';
    } elseif (is_array($data)) {
        if (array_keys($data) === range(0, count($data) - 1)) {
            return 'l' . implode('', array_map('bencode', $data)) . 'e';
        } else {
            ksort($data);
            return 'd' . implode('', array_map(function ($key, $value) {
                return bencode($key) . bencode($value);
            }, array_keys($data), $data)) . 'e';
        }
    }
}
?>