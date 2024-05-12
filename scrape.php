<?php
// 引入配置文件
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

// 创建数据库连接
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
// 检查数据库连接是否成功
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// 获取Scrape请求中的info_hash
$info_hashes = $_GET['info_hash'] ?? null;

// 检查info_hash是否存在
if (!$info_hashes) {
    die('Missing info_hash parameter');
}

// 准备SQL语句用于查询Torrent文件的统计信息
$sql = "SELECT info_hash, COUNT(*) AS seeders, SUM(`left`) AS leechers FROM peers WHERE info_hash IN (";
$placeholders = rtrim(str_repeat('?,', count($info_hashes)), ',');
$sql .= $placeholders . ") GROUP BY info_hash";
$stmt = $conn->prepare($sql);

// 绑定参数
$params = array();
foreach ($info_hashes as $key => $hash) {
    $params[$key] = &$info_hashes[$key];
}
call_user_func_array(array($stmt, 'bind_param'), array_merge(array(str_repeat('s', count($info_hashes))), $params));

$stmt->execute();
$result = $stmt->get_result();

// 构建响应
$response = array();
while ($row = $result->fetch_assoc()) {
    $response[$row['info_hash']] = array(
        "complete" => intval($row['seeders']),
        "incomplete" => intval($row['leechers'])
    );
}

echo bencode($response); // 返回Bencode编码的响应

// 关闭statement和连接
$stmt->close();
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