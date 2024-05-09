# SimpleBitTorrentTracker
一个简单的BitTorrent Tracker，只有最基础的功能。~~纯小白刚刚开始自学PHP，写的烂轻喷~~

## 安装方法
1. 运行`Initialize.sql`初始化数据库。
2. 将`config.php.sample`更名为`config.php`，并按照文件内容提示填写数据库信息。
3. 将除了`Initialize.sql`此外的所有文件和目录导入服务器。
4. 设置服务器伪静态规则，将`/announce`路径指向`/announce/index.php`unce$ /announce/index.php [L]
```