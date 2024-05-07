# SimpleBitTorrentTracker
一个简单的BitTorrent Tracker，只有最基础的功能。~~纯小白刚刚开始自学PHP，写的烂轻喷~~

## 安装方法
1. 运行`Initialize.sql`初始化数据库。
2. 将`config.php.sample`更名为`config.php`，并按照文件内容提示填写数据库信息。
3. 将除了`Initialize.sql`此外的所有文件和目录导入服务器。


## 注意！
Tracker地址应该是[http://YourDomain/announce/](http://YourDomain/announce/)
如果没有最后面那个斜杠的话，一些BT下载器会无法识别。

如果你有解决的办法，可以通过[Issues](https://github.com/guguan123/BitTorrentTracker/issues)或者pull requests代码，感谢你帮助修复问题 :)
