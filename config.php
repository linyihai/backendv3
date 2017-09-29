<?php
$dbHost = "192.168.1.104";//数据库主机
$dbUser = "root";//数据库用户
$dbPass = "aimei";//数据库密码
$dbData = "sdbmgr";//数据库名称

$epagesize      = 10;
$userpagesize   = 10;

$logfile        = "/usr/local/tengine-2.1.2/html/sdbmgr/backend/logs";

$dbConn = 0;//连接方式，1为持续连接,0为一般链接(虚拟主机用户推荐)
$prefix = "";//数据表前缀

//定义要禁止直接从地址栏里访问的文件，仅根目录有效
$lockFile = "config.php function.php function-cn.php";//多个文件用空格分开
if(strpos($lockFile,basename($_SERVER["PHP_SELF"])) !== false) die("操作非法，您所访问的文件不存在或不允许直接浏览");
?>

