<?php
// 引入配置文件
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

// 设置HTTP响应头，指定内容类型为B编码
header('Content-Type: text/plain');

// 创建数据库连接
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
// 检查数据库连接是否成功
if ($conn->connect_error) {
    die(bencode(['failure reason' => 'Database connection failed']));
}

// 从GET请求中获取必要的参数
$info_hash = $_GET['info_hash'] ?? null;    // 需要下载的资源的 torrent 文件的 info 部分的 SHA1 值，或者磁力链接中的 xt 值。
$peer_id = $_GET['peer_id'] ?? null;        // 标示这个客户端的字符串。
$port = $_GET['port'] ?? null;              // peer 监听的端口。
$ipv4 = $_GET['ipv4'] ?? null;              // (可选) peer 的 IPv4
$ipv6 = $_GET['ipv6'] ?? null;              // (可选) peer 的 IPv6
$downloaded = $_GET['downloaded'] ?? null;  // 该资源至今为止的下载字节数。
$left = $_GET['left'] ?? null;              // 该资源剩余未完成下载的字节数。
$uploaded = $_GET['uploaded'] ?? null;      // 该资源至今为止的上传字节数。
$supportcrypto = isset($_GET['supportcrypto']) ? 1 : 0;
$numwant = isset($_GET['numwant']) ? intval($_GET['numwant']) : 50; // (可选) 客户端希望从 tracker 接收的 peers 数量。
$event = $_GET['event'] ?? null;            // (可选)当前资源下载状态
$compact = $_GET['compact'] ?? null;        // (可选)客户端希望 tracker 返回压缩 peer 列表。

if (empty($ipv4) && empty($ipv6)) {
    // 如果peer没通过"ipv4"和"ipv6"字段报告IP信息
    $ip = $_GET['ip'] ?? null;
    if ($ip) {
        // 检测"ip"字段的内容是IPv4还是IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipv4 = $ip;
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipv6 = $ip;
        } else {
            // 报告IP格式错误
            die(bencode(['failure reason' => 'Invalid IP']));
        }
    } else {
        // 如果客户端没报告IP地址信息，获取IPv4地址
        $ipv4 = $_SERVER['REMOTE_ADDR'];
    }

}

// 只允许最多获取1000个peers
if ($numwant > 1000) {
    $numwant = 1000;
}

// 检查必要参数并输出具体的错误信息
if (!$info_hash) {
    die(bencode(['failure reason' => 'missing info_hash']));
} elseif (!$peer_id) {
    die(bencode(['failure reason' => 'missing peer_id']));
} elseif (!$port) {
    die(bencode(['failure reason' => 'missing port']));
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

    // 获取当前info_hash下的所有peers，排除当前peer_id
    $getSql = "SELECT ipv4, ipv6, port FROM peers WHERE info_hash = ? AND peer_id != ? LIMIT $numwant";
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