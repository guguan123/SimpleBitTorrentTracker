<?php
// 引入配置文件
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';


// 检查Scrape请求中是否有info_hash参数，并且它是一个数组
if(isset($_GET['info_hash']) && is_array($_GET['info_hash'])) {
    $info_hashes = $_GET['info_hash'];
} else {
    // 响应信息数组
    die(bencode(['failure reason' => 'missing info_hashe'])); // 如果没有info_hash参数或不是数组，就输出报错
}


// 创建数据库连接
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
// 检查数据库连接是否成功
if ($conn->connect_error) {
    die(bencode(['failure reason' => "Database connection failed: {$conn->connect_error}"]));
}

// 准备SQL语句用于查询Torrent文件的统计信息
$sql = "SELECT info_hash, COUNT(*) AS seeders, SUM(`left`) AS leechers FROM peers WHERE info_hash IN (";
$placeholders = rtrim(str_repeat('?,', count($info_hashes)), ',');
$sql .= $placeholders . ") GROUP BY info_hash";

// 准备并执行SQL语句
if ($stmt = $conn->prepare($sql)) {
    // 绑定参数
    $param_type = str_repeat('s', count($info_hashes)); // 参数类型字符串
    $params = array_merge([$param_type], $info_hashes); // 参数数组
    $stmt->bind_param(...$params);
    
    // 执行SQL查询
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        // 构建响应
        $response = [];
        while ($row = $result->fetch_assoc()) {
            $response[$row['info_hash']] = [
                "complete" => intval($row['seeders']),
                "incomplete" => intval($row['leechers'])
            ];
        }
        
        // 返回Bencode编码的响应
        echo bencode($response);
    } else {
        die(bencode(['failure reason' => "SQL execute failed: {$stmt->error}"]));
    }

    // 关闭statement
    $stmt->close();
} else {
    die(bencode(['failure reason' => "SQL execute failed: {$conn->error}"]));
}

// 关闭连接
$conn->close();


// 定义Bencode编码函数
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