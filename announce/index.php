<?php
// 从配置文件获取数据库信息
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

// 创建连接
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// 检查连接
if ($conn->connect_error) {
  die(bencode(['failure reason' => 'Database connection failed']));
}

// 验证必要的请求参数
$info_hash = $_GET['info_hash'] ?? null;
$peer_id = $_GET['peer_id'] ?? null;
$port = $_GET['port'] ?? null;

if (!$info_hash || !$peer_id || !$port) {
    echo bencode(['failure reason' => 'missing info_hash, peer_id or port']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'];

// 更新或插入新的peer信息
$updateSql = "INSERT INTO peers (info_hash, peer_id, ip, port) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE ip=VALUES(ip), port=VALUES(port), updated_at=NOW()";

try {
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("sssi", $info_hash, $peer_id, $ip, $port);
    $stmt->execute();

    // 清理过期的peer
    $delSql = "DELETE FROM peers WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $conn->query($delSql);

    // 获取响应给客户端的peers列表
    $getSql = "SELECT ip, port FROM peers WHERE info_hash = ? AND peer_id != ? LIMIT 50";
    $stmt = $conn->prepare($getSql);
    $stmt->bind_param("ss", $info_hash, $peer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $peers = [];
    while ($row = $result->fetch_assoc()) {
      $peers[] = ["ip" => $row["ip"], "port" => $row["port"]];
    }

    // 构建响应
    $response = [
        "interval" => 1800,  // 推荐的更新间隔时间（秒）
        "peers" => $peers
    ];

    echo bencode($response);

} catch (Exception $e) {
    echo bencode(['failure reason' => 'Server error']);
} finally {
    $stmt->close();
    $conn->close();
}

// 一个简单的Bencode编码函数
function bencode($data) {
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