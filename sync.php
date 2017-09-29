<?php
header('Content-type: text/xml');
@$cmd = $_GET["cmd"];
require_once("./config.php");
require_once("./function-cn.php");
require_once("./class/mysql.php");
$DB = new DB($dbHost,$dbUser,$dbPass,$dbData,$dbConn);
$DB->query("set names utf8");
unset($dbHost,$dbUser,$dbPass,$dbData,$dbConn);
if ($cmd == "sync_songlist_getsonglist") {
    $mid        = $_GET['mid'];
    $nasid      = $_GET['nasid'];
    if (!$mid or !$nasid or $mid < 0) {
        $res = array("errcode"=>-1, "errmsg"=>"srv: mid or nasid invalid", "songlist"=>array());
        echo json_encode($res);
        return;
    }
    $json_str   = file_get_contents("php://input"); //接收POST数据
    sync_logger("debug", "recv current songlist:$json_str\n");
    $js         = json_decode($json_str);
    $src_songlist   = $js->songlist;
    $sql            = "select mid, nasid from t_runtime_songlist_sync where mid=$mid";
    $recordset      = $DB->query($sql);
    
    //更新同步关系 和当前客户端的歌单列表
    $sql = "select nasid, executor, dst_songlist from t_runtime_songlist_sync where nasid='$nasid' and mid=$mid";
    $recordset = $DB->query($sql);
    $row       = $DB->fetchArray($recordset);
    if ($row) {  //机器没有更换nas盘   
            $dst_songlist = $row['dst_songlist'];
            $executor     = $row['executor'];
            $sql = "update t_runtime_songlist_sync set src_songlist='$src_songlist' where mid=$mid and nasid = '$nasid'";
            $DB->query($sql);
            sync_logger("debug", "[config] nas:$nasid, mid:$mid, sync config not change\n");
     }
     else {    //老机器更换了nas盘（使用新的nas盘或 加入到其他nas盘中） || 新机器加入（加入到老的nas盘组或者使用新的nas盘）
            $sql = "select nasid, executor from t_runtime_songlist_sync where mid = $mid";
            $recordset  = $DB->query($sql);
            $row        = $DB->fetchArray($recordset);
            if ($row) { 
                //同步关系已经再存在，清空同步关系
                $old_nasid  = $row['nasid'];
                $executor   = $row['executor'];
                if ($executor == $mid) { //本身是leader，原来的组需要重新选举leader
                    $sql = "select mid from t_runtime_songlist_sync where nasid='$old_nasid' and mid != $mid";
                    $recordset  = $DB->query($sql);
                    $candidates = array();
                    while ($row = $DB->fetchArray($recordset)) {
                        $cand  = $row['mid'];
                        array_push($candidates, $cand);
                    }
                    sort($candidates);
                    $mids    = $candidates;
                    array_push($mids, $mid);
                    $strmids = implode(",", $mids);
                    if (count($candidates) > 0) { //选择mid最大的作为executor
                        $new_executor = end($candidates);
                        $sql          = "update t_runtime_songlist_sync set executor=$new_executor where nasid='$old_nasid'";
                        $DB->query($sql);
                        sync_logger("info", "[config] nas:$old_nasid, mids:[$strmids], vote new executor:$new_executor, ex-executor:$mid want to left\n");
                    }
                }
                $sql        = "delete from t_runtime_songlist_sync where mid=$mid";
                $DB->query($sql);
                sync_logger("info", "[config] nas:$old_nasid, mid:$mid, minik left\n");
            }
            //清除了原来的同步关系后，作为一个新的机器添加到同步系统中，有可能加入已经存在的nas组，或者启用新的nas组
            $sql        = "select nasid, executor, dst_songlist from t_runtime_songlist_sync where nasid = '$nasid'";
            $recordset  = $DB->query($sql);
            $row        = $DB->fetchArray($recordset);    
            if ($row) { //加入到已经存在的nas组中
                $executor       = $row['executor'];
                $dst_songlist   = $row['dst_songlist'];
                if ($dst_songlist)
                    $sql = "insert into t_runtime_songlist_sync(mid, nasid, executor, src_songlist, dst_songlist) values($mid, '$nasid', '$executor', '$src_songlist', '$dst_songlist')";  
                else
                    $sql = "insert into t_runtime_songlist_sync(mid, nasid, executor, src_songlist, dst_songlist) values($mid, '$nasid', '$executor', '$src_songlist', NULL)";  
                $DB->query($sql);
                sync_logger("info", "[config] nas:$nasid, mid:$mid, minik json in, executor:$executor\n");
            }
            else {     //所使用的nas没有存在同步关系，需要对该nas进行计算，从而确定该设备的nas版本
                $executor = $mid;
                $sql = "insert into t_runtime_songlist_sync(mid, nasid, executor, src_songlist, dst_songlist) values($mid, '$nasid', '$executor', '$src_songlist', NULL)";  
                $DB->query($sql);
                sync_logger("info", "[config] nas:$nasid, mid:$mid, build new group, executor:$mid\n");
            }
        }
    //
    $sql = "select nasid, executor, dst_songlist from t_runtime_songlist_sync where nasid='$nasid' and mid=$mid";
    $recordset = $DB->query($sql);
    $row       = $DB->fetchArray($recordset);
    if ($row) {  
        $executor       = $row['executor'];
        $dst_songlist   = $row['dst_songlist'];
        if ($executor == $mid) {
            if (!$dst_songlist || strlen($dst_songlist) == 0) 
                $res = array("errcode"=>0, "errmsg"=>"the server still resolving songlist, please wait", "songlist"=>array());
            else {
                $res = array("errcode"=>0,"errmsg"=>"srv: last songlist", "songlist"=>json_decode($dst_songlist));
            }
            echo json_encode($res);
        }
        else {
            $res = array("errcode"=>0, "errmsg"=>"srv: neighbor($executor) was downloading", "songlist"=>array());
            echo json_encode($res);       
        }
    }
    else {
        $res = array("errcode"=>-1, "errmsg"=>"srv: build the sync config failed, maybe db error", "songlist"=>array());
        echo json_encode($res);
    }  
}
elseif ($cmd == "sync_songlist_getsonglist_detail") {
    $songlistid = $_GET['songlistid'];
    $mid        = $_GET['mid'];
    $sql        = "select * from t_runtime_songlist where id = $songlistid";
    $recordset  = $DB->query($sql);
    $row        = $DB->fetchArray($recordset);
    if (!$row) {
        $res = array("errcode"=>-1, "errmsg"=>"srv: the songlist($songlistid) is not exist");
    }
    else {
        $songlist   = $row['songlist'];
        $songlist   = json_decode($row['songlist']);
        $songdetail = array();
        foreach ($songlist as $songid) {
            $sql = "select songid, cover_url, cover_md5, src_url, src_md5 from t_data_song where songid='$songid'";
            $recordsetx = $DB->query($sql);
            $rowx       = $DB->fetchArray($recordsetx);
            if (!$rowx) {
                sync_logger("error", "get song detail failed: songid($songid) was not exist\n");
            }
            else {
                $id         = $rowx['songid'];
                $src_url    = $rowx['src_url'];
                $src_md5    = $rowx['src_md5'];
                $src_dst    = "\$minik_resourcepath\\Ver_$songlistid\\video\\$id.mp4";
                $src_txt    = "0|video\\$id.mp4|0";
                $pic_url    = $rowx['cover_url'];
                $pic_md5    = $rowx['cover_md5'];
                $e          = explode(".", $pic_url);
                $t          = $e[count($e) - 1];
                $pic_dst    = "\$minik_resourcepath\\Ver_$songlistid\\video\\$id.$t";
                $pic_txt    = "1|video\\$id.$t|0"; 
                $item       = array("songid"=>$id, "src_url"=>$src_url, "src_md5"=>$src_md5, "src_dst"=>$src_dst, "src_txt"=>$src_txt,  "cover_url"=>$pic_url, "cover_md5"=>$pic_md5, "cover_dst"=>$pic_dst, "cover_txt"=>$pic_txt);
                array_push($songdetail, $item);
            }
        }

        $singerdetail= array();
        $singerlist = json_decode($row['singerlist']);
        foreach ($singerlist as $singerid) {
            $sql = "select singerid, pic, md5 from t_data_singer where singerid = '$singerid'";
            $recordset  = $DB->query($sql);
            $rowy        = $DB->fetchArray($recordset);
            if (!$rowy) {
                sync_logger("error", "get singer detail failed: singerid($songid) was not exist\n");
            }
            else {
                $singerid = $rowy['singerid'];
                $src      = $rowy['pic'];
                $md5      = $rowy['md5'];
                $dst      = "\$minik_resourcepath\Ver_$songlistid\\singer\\$singerid.jpg";
                $txt      = "2|singer\\$singerid.jpg|0";
                $item     = array("singerid"=>$singerid, "src"=>$src, "md5"=>$md5, "dst"=>$dst, "txt"=>$txt);
                array_push($singerdetail, $item);          
            }
        }
        $swsong_url = $row['swsong_url'];
        $swsong_md5 = $row['swsong_md5'];
        $swsong_dst = "\$minik_resourcepath\\Ver_$songlistid\\profile\\swsong_v3.db";
        $swsong_txt = "3|profile\\swsong_v3.db|0";
        $version    = 3;

        $swsong_v2_url = $row['swsong_v2_url'];
        $swsong_v2_md5 = $row['swsong_v2_md5'];
        $swsong_v2_dst = "\$minik_resourcepath\\Ver_$songlistid\\profile\\swsong.db";
        $swsong_v2_txt = "3|profile\\swsong.db|0";
        $version_v2    = 2;
        
        $swsong = array();
        $item1 = array("url"=>$swsong_url, "md5"=>$swsong_md5, "dst"=>$swsong_dst, "txt"=>$swsong_txt, "ver"=>$version);
        $item2 = array("url"=>$swsong_v2_url, "md5"=>$swsong_v2_md5, "dst"=>$swsong_v2_dst, "txt"=>$swsong_v2_txt, "ver"=>$version_v2);
        array_push($swsong, $item1);
        array_push($swsong, $item2);

        $listtxt    = "\$minik_resourcepath\\Ver_$songlistid\\profile\\list_v3.txt";
        $listtxt_v2 = "\$minik_resourcepath\\Ver_$songlistid\\profile\\list.txt";
        $res = array("errcode"=>0, "errmsg"=>NULL, "singerlist"=>$singerdetail, "songlist"=>$songdetail, "swsongdb"=>$swsong, "listtxt_v3"=>$listtxt, "listtxt_v2"=>$listtxt_v2);
    }
    echo json_encode($res);
}
elseif ($cmd == "sync_backlist_getlastutime") {
    $sql        = "select max(unix_timestamp(utime)) as utime from t_data_backlist;";
    $recordset  = $DB->query($sql);
    $row        = $DB->fetchArray($recordset);
    if ($row)
        $res = array("errcode"=>0, "errmsg"=>NULL, "lastutime"=>$row['utime']);
    else
        $res = array("errcode"=>-1, "errmsg"=>"not last update time");
    echo json_encode($res);
}
elseif ($cmd == "sync_backlist_getbacklist") {
    $sql = "select type, id, unix_timestamp(utime) as utime from t_data_backlist where type = 1"; //song
    $recordset  = $DB->query($sql);
    $song     = array();
    while ($row = $DB->fetchArray($recordset)) {
        $type = $row['type'];
        $id   = $row['id'];
        $utime= $row['utime'];
        $item = array("id"=>$id, 'utime'=>$utime);
        array_push($song, $item);
    }
    
    $sql = "select type, id, unix_timestamp(utime) as utime from t_data_backlist where type = 2"; //singer
    $recordset  = $DB->query($sql);
    $singer     = array();
    while ($row = $DB->fetchArray($recordset)) {
        $type = $row['type'];
        $id   = $row['id'];
        $utime= $row['utime'];
        $item = array("id"=>$id, "utime"=>$utime);
        array_push($singer, $item);
    }
    $backlist = array("singer"=>$singer, "song"=>$song);
    $res = array("errcode"=>0, "errmsg"=>NULL, "backlist"=>$backlist);
    echo json_encode($res);
}
else {
    echo "{\"errcode\":-1,\"errmsg\":\"api undefine cmd:$cmd\"}";
}
?>
