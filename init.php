<?php
@$cmd = $_GET["cmd"];
require_once("./config.php");
require_once("./function-cn.php");
require_once("./class/mysql.php");
$DB = new DB($dbHost,$dbUser,$dbPass,$dbData,$dbConn);
unset($dbHost,$dbUser,$dbPass,$dbData,$dbConn);

if ($cmd == "submit_newtask") {
    $json_str = file_get_contents("php://input"); //接收POST数据
    $js       = json_decode($json_str);
    $src      = $js->src;
    $format   = $js->format;
    $acode    = $js->acode;
    $vcode    = $js->vcode;
    $bitrate  = $js->bitrate;
    $sql      = "insert into t_runtime_mediainfo(src, format, acode, vcode, bitrate
}
else {
    echo "{\"errcode\":-1,\"errmsg\":\"api undefine cmd:$cmd\"}";
}
?>
