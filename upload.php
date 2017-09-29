<?php
$key      = "cover";
$storeage_path = "/opt/sdbmgr/storeage/cover/";
$tmpfile  = $_POST[$key . "_path"];
$size     = $_POST[$key . "_size"];
$md5      = md5_file($tmpfile);
$dst      = $storeage . $md5 . ".png";
rename($tmpfile, $dst);
echo "http://sdbmgr.singworld.cn/storeage/cover/$md5.png";
?>
