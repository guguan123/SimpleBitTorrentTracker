<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die(bencode(['failure reason' => 'Database connection failed']));
}

$info_hash = $_GET['info_hash'] ?? null;
$peer_id = $_GET['peer_id'] ?? null;
$port = $_GET['port'] ?? null;
$ipv6 = $_GET['ipv6'] ?? null;
$downloaded = $_GET['downloaded'] ?? 0;
$left = $_GET['left'] ?? 0;
$uploaded = $_GET['uploaded'] ?? 0;
$supportcrypto = isset($_GET['supportcrypto']) ? 1 : 0;

if (!$info_hash || !$peer_id || !$port) {
    echo bencode(['failure reason' => 'missing required parameters']);
    exit;
}

$ipv4 = $_SERVER['REMOTE_ADDR'];

$updateSql = "INSERT INTO peers (info_hash, peer_id, ipv4, ipv6, port, downloaded, `left`, uploaded, supportcrypto, updated_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
              ON DUPLICATE KEY UPDATE ipv4=VALUES(ipv4), ipv6=VALUES(ipv6), port=VALUES(port), downloaded=VALUES(downloaded),
              `left`=VALUES(`left`), uploaded=VALUES(uploaded), supportcrypto=VALUES(supportcrypto), updated_at=NOW()";

try {
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("ssssiiiii", $info_hash, $peer_id, $ipv4, $ipv6, $port, $downloaded, $left, $uploaded, $supportcrypto);
    $stmt->execute();

    $delSql = "DELETE FROM peers WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $conn->query($delSql);

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

// Bencode编码函数
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