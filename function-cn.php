<?php
function logger($level, $message)
{
    $tody = date("Ymd");
    global  $logfile;
    $f   = "$logfile/sdbmgrweb$tody.log";
    $now     = date("Y-m-d H:i:s");
    $msg     = $now . " <sdbmgrweb> " . $level . " : " . $message;
    error_log($msg, 3, $f);
}

function sync_logger($level, $message)
{
    $tody = date("Ymd");
    global  $logfile;
    $f   = "$logfile/sync$tody.log";
    $now     = date("Y-m-d H:i:s");
    $msg     = $now . " <sync> " . $level . " : " . $message;
    error_log($msg, 3, $f);
}
?>
