# SimpleBitTorrentTracker
一个简单的BitTorrent Tracker，只有最基础的功能。~~纯小白刚刚开始自学PHP，写的烂轻喷~~
这个项目写的实在是太糟糕了，不建议用这个项目来搭建Tracker服务器...

## 安装方法
1. 下载releases，上传到服务器并解压。
2. 运行`Initialize.sql`初始化数据库。
3. 按照文件`config.php`的内容提示填写数据库信息。
4. 将除了`Initialize.sql`此外的所有文件和目录导入服务器。
5. 设置服务器伪静态规则，将`/announce`路径指向`/announce/index.php`
