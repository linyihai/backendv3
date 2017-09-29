<?php
@$cmd = $_GET["cmd"];
require_once("./config.php");
require_once("./function-cn.php");
require_once("./class/mysql.php");
$DB = new DB($dbHost,$dbUser,$dbPass,$dbData,$dbConn);
$DB->query("set names utf8");
unset($dbHost,$dbUser,$dbPass,$dbData,$dbConn);
if ($cmd == "login") {
    $json_str = file_get_contents("php://input"); //接收POST数据
    $js       = json_decode($json_str);
    $user     = $js->username;
    $pwd      = $js->password;
    $sql      = "SELECT username,rights FROM t_config_acl WHERE username='".$user."' and  password='".$pwd."'";
    $query    = $DB->query($sql);
    if($rows = $DB->fetchArray($query)) {
        echo "{\"errcode\":0,\"errmsg\":\"login successful\",\"rights\":\"\",\"token\":\"$user\"}";
     }
     else {
         echo "{\"errcode\":-1, \"errmsg\":\"invalid username|password\"}";
     }
}
elseif ($cmd == "logout") {
   $token = $_GET["token"];
   echo "{\"errcode\":0,\"errmsg\":\"\",\"token\":\"$token\"}";
}
else if ($cmd == "invsong_query") {
    $name       = $_GET['name'];
    $singer     = $_GET['singer'];
    $invtor     = $_GET['token'];
    $language   = $_GET['language'];
    $page       = $_GET['page'];
    $id         = $_GET['id'];
    if (!$page)
        $page = 1;
    $pagesize = 10;

    $condition  = " where  1";
    if ($name)
        $condition = $condition . " and name like '%$name%'";
    if ($invtor)
        $condtion  = $condition . " and invtor='$invtor'";
    if ($singer)
        $condtion  = $condtion . " and singer like '%$singer%";
    if ($language)
        $condition = $conditon . " and language = '$language'";
    if ($id)
        $condition = $condition . " and id in ($id)";
    $sql  = "select id, orderid, name, singer, language, invtor, contact, invtime, status from t_runtime_invsong $condition limit " . ($page - 1) * $pagesize . ",$pagesize";
    $sql_total = "select count(*) as total from t_runtime_invsong $condtion";
    
    $recordset  = $DB->query($sql_total);
    $row        = $DB->fetchArray($recordset);
    $total      = $row['total'];
    
    $recordset  = $DB->query($sql);
    $orderlist  = array();
    while ($row = $DB->fetchArray($recordset)) {
        $id     = $row['id'];
        $orderid= $row['orderid'];
        $name   = $row['name'];
        $singer = $row['singer'];
        $language=$row['language'];
        $invtor = $row['invtor'];
        $contact= $row['contact'];
        $invtime= $row['invtime'];
        $status = $row['status'];
        $entry  = array("id"=>$id, "orderid"=>$orderid, "name"=>$name, "singer"=>$singer, "language"=>$language, "invtor"=>$invtor, "contact"=>$contact, "invtime"=>$invtime, "status"=>$status);
        array_push($orderlist, $entry);
    }
    $res = array("errcode"=>0, "errmsg"=>NULL, "total"=>$total, "orderlist"=>$orderlist);
    echo json_encode($res);   
}
else if ($cmd == "invsong_submit") {
    $invtor = $_GET['token'];
    $json_str   = file_get_contents("php://input");
    $js         = json_decode($json_str);
    $name       = $js->name;
    $singer     = $js->singer;
    $language   = $js->language;
    $contact    = "13760656809";
    $sql = "select count(*) as total  from t_runtime_invsong where name='$name' and singer='$singer' and language='$language'";
    $recordset = $DB->query($sql);
    $row       = $DB->fetchArray($recordset);
    $total     = $row['total'];
    if ($total > 0)
        $res = array("errcode"=>-1,  "errmsg"=>"another same song was exist");
    else {
        $sql = "insert into t_runtime_invsong(name, singer, language, invtime, contact, status) values('$name', '$singer', '$language', now(), '$contact', 0)";
        $DB->query($sql);
        $res = array("errcode"=>0, "errmsg"=>NULL);
    }
    echo json_encode($res);
}
else if ($cmd == "invsong_cancel") {
    $id     = $_GET['id'];
    $invtor = $_GET['token'];
    $sql = "delete from t_runtime_invsong where id = $id"; // and invtor='$invtor'";
    $DB->query($sql);
    $res = array("errcode"=>0, "errmsg"=>NULL);
    echo json_encode($res);
}
else if ($cmd == "taskdownload_querytotal") {
    #total song
    $status = $_GET['total'];
    $sql                = "select count(*) as total from t_runtime_download ";
    $sql_album_songs    = "select count(*) as total from t_runtime_download where (albumname is not NULL and albumname != '')";
    $sql_album_num      = "select * from t_runtime_download where (albumname is not NULL and albumname != '') ";
    if ($status) {
        $sql            = $sql . " and status = $status";
        $sql_album_num  = $sql_album_num . " and status = $status";
        $sql_album_songs= $sql_album_songs . " and status = $status";
    }
    $sql_album_num = $sql_album_num. " group by albumname";
    $sql_album_num = "select count(*) as total from ($sql_album_num) as t";

    $recordset = $DB->query($sql);
    $row  =$DB->fetchArray($recordset);
    $total=$row['total'];    

    $recordset = $DB->query($sql_album_num);
    $row       = $DB->fetchArray($recordset);
    $total_album=$row['total'];    
    
    $recordset = $DB->query($sql_album_songs);
    $row       = $DB->fetcharray($recordset);
    $total_album_songs = $row['total'];

    $res = array("errcode"=>0, "errmsg"=>NULL, "song_total"=>$total, "album_total"=>$total_album, "album_song_total"=>$total_album_songs);
    echo json_encode($res);
}
else if ($cmd == "taskdownload_queryalbum") {
    $search_text    = $_GET['search_text']; 
    $status         = $_GET["status"];
    $page           = $_GET["page"];
    $albumname      = $_GET["albumname"]; #albumname
    $pubtime_stime  = $_GET["stime"];
    $pubtime_etime  = $_GET["etime"];
    $corp           = $_GET["copyright"];
    $albumid        = $_GET['albumid'];
    $pagesize       = 10;
    
    $condition = " where phase = 1 and (albumname is not NULL and albumname != '')";
    $sql = "select albumid, albumname, pubtime, count(*) as songnum, singer, status, copyright from t_runtime_download";
    if ($albumname) 
        $condition = $condition . " and albumname like '%$albumname%'";
    if ($albumid)
        $condition = $condition . " and albumid = '$albumid'";
    if ($status)
        $condition = $condition . " and status=$status";
    if ($corp)
        $condition = $condition . " and copyright='$corp'";
    if ($pubtime_stime || $pubtime_etime) 
        $condition = $condition . " and (unix_timestamp(pubtime) >=$pubtime_stime and unix_timestamp(pubtime) <=$pubtime_etime)";
    if ($search_text)
        $condition = $condition . " and concat(albumname,copyright,pubtime) like '%$search_text%'";
    
    $sql = $sql . " $condition group by albumname";
    $song_total     = "select count(*) as total from t_runtime_download $condition";
    $album_total    = "select count(*) as total from ($sql) as t";
    if (!$page)
        $page = 1;
    $sql        = "select * from ($sql) as t limit " . ($page - 1) * $pagesize . "," . $pagesize;

    $recordset  = $DB->query($album_total);
    $row        = $DB->fetchArray($recordset);
    $album_total= $row['total'];

    $recordset  = $DB->query($song_total);
    $row        = $DB->fetchArray($recordset);
    $song_total = $row['total'];

    $recordset = $DB->query($sql);
    $albumlist = array();
    while ($row = $DB->fetchArray($recordset)) {
        $albumid        = $row['albumid'];
        $albumname      = $row['albumname'];
        $pubtime        = $row['pubtime'];
        $songnum        = $row['songnum'];  
        $singer         = $row['singer'];
        $copyright      = $row['copyright'];
        $status         = $row['status'];
        $album = array('albumid'=>$albumid, 'albumname'=>$albumname, 'copyright'=>$copyright, 'singer'=>$singer, 'pubtime'=>$pubtime, 'songnum'=>$songnum, "status"=>$status);
        array_push($albumlist, $album);
    }
    $res = array("errcode"=>0,"errmsg"=>NULL,"albumtotal"=>$album_total, "songtotal"=>$song_total,"albumlist"=>$albumlist);
    echo json_encode($res);
}
else if ($cmd == "taskdownload_queryalbum_detail") {
    $albumid    = $_GET['albumid'];
    $status     = $_GET['status'];
    $name       = $_GET['name'];
    $singer     = $_GET['singer'];
    $page       = $_GET['page'];

    $sql_total  = "select count(*) as total from t_runtime_download where albumid='$albumid'";
    $sql        = "select taskid, lxsongid as songid, name, language, videotype, pubtime, singer, singertype, status, ishd, albumname, copyright, progress from t_runtime_download where phase = 1 and albumid = '$albumid'";
    $condition  = "1";
    if ($status) 
        $condition = $condition . " and status = $status";
    if ($name)
        $condition = $condition . " and name like '%$name%'";   
    if ($singer)
        $condition = $condition . " and singer like '%$singer%'";
    $sql_total = $sql_total . " and " . $condition;
    $sql       = $sql . " and " . $condition;
    if (!$page)
        $page = 1;
    $pagesize = 10;
    $sql = $sql . " limit " . ($page - 1) * $pagesize . ",$pagesize";
    
    $recordset  = $DB->query($sql_total);
    $row        = $DB->fetchArray($recordset);
    $total      = $row['total'];
    $albumname  = "";
    $corp       = "";
    $pubtime    = "";
    
    $recordset = $DB->query($sql);
    $songlist  = array();
    while ($row = $DB->fetchArray($recordset)) {
        $taskid     = $row['taskid'];
        $songid     = $row['songid'];
        $name       = $row['name'];
        $language   = $row['language'];
        $corp       = $row['copyright'];
        $singer     = $row['singer'];
        $singertype = $row['singertype'];
        $videotype  = $row['videotype'];
        $status     = $row['status'];
        $ishd       = $row['ishd'];
        $pubtime    = $row['pubtime'];
        $albumname  = $row['albumname'];
        $item = array("taskid"=>$taskid, "songid"=>$songid, "name"=>$name, "language"=>$language, "copyright"=>$corp, "singer"=>$singer, "singertype"=>$singertype, "videotype"=>$videotype, "status"=>$status, "hd"=>$ishd, "pubtime"=>$pubtime, "albumname"=>$albumname);
        array_push($songlist, $item);
    }   
    $res = array("errcode"=>0, "errmsg"=>NULL, "total"=>$total, "albumname"=>$albumname, "songlist"=>$songlist, "pubtime"=>$pubtime, "copyright"=>$corp);
    echo json_encode($res);
}
else if ($cmd == "taskdownload_querysong") {
    $search_text     = $_GET['search_text'];
    $status         = $_GET["status"];
    $page           = $_GET["page"];
    $name           = $_GET["name"];
    $pubtime_stime  = $_GET["stime"];
    $pubtime_etime  = $_GET["etime"];
    $corp           = $_GET["copyright"];
    $taskids        = $_GET['taskid'];
    $albumname      = $_GET['albumname'];
    $albumid        = $_GET['albumid'];
    $pagesize       = 10;    
    $sql = "select taskid, lxsongid as songid, name, language, videotype, pubtime, singer, singertype, status, ishd, albumname, copyright, filesize, progress, downloader from t_runtime_download where phase = 1";    
    $sql_total = "select count(*) as total from t_runtime_download where phase = 1";
    if ($name) {
        $sql = $sql . " and name like '%$name%'";
        $sql_total = $sql_total . " and name like '%$name%'";
    }
    if ($status) {
        $sql        = $sql . " and status in ($status)";
        $sql_total  = $sql_total . " and status in ($status)";
    }
    if ($corp) {
        $sql        = $sql . " and copyright='$corp'";
        $sql_total  = $sql_total . " and copyright='$corp'";
    }
    if ($pubtime_stime || $pubtime_etime) {
        $sql = $sql . " and (unix_timestamp(pubtime) >=$pubtime_stime and unix_timestamp(pubtime) <=$pubtime_etime)";    
        $sql_total  = $sql_total . " and (unix_timestamp(pubtime) >=$pubtime_stime and unix_timestamp(pubtime) <=$pubtime_etime)";
    }
    if ($taskids) { 
        $sql = $sql . " and taskid in($taskids)";
        $sql_total = $sql_total . " and taskid in($taskids)";
    }
    if ($albumname) {
        $sql = $sql . " and albumname like '%$albumname%'";
        $sql_total = $sql_total . " and albumname like '%$albumname%'";
    }
    if ($albumid) {
        $sql = $sql . " and albumid = '$albumid'";
        $sql_total = $sql_total . " and albumid = '$albumid'";
    }
    if ($search_text) {
        $sql = $sql . " and concat(name,singer,lxsongid,albumname,lxsongid,pubtime,copyright) like '%$search_text%'";
        $sql_total = $sql_total . " and concat(name,singer,lxsongid,albumname,lxsongid,pubtime,copyright) like '%$search_text%'";
    }
    if ($page)
       $sql = $sql . " limit " . ($page - 1) * $pagesize . "," . $pagesize;
    $recordset = $DB->query($sql);  
    $songlist  = array();
    while ($row = $DB->fetchArray($recordset)) {
        $taskid     = $row['taskid'];
        $songid     = $row['songid'];
        $name       = $row['name'];
        $language   = $row['language'];
        $corp       = $row['copyright'];
        $singer     = $row['singer'];
        $singertype = $row['singertype'];
        $videotype  = $row['videotype'];
        $status     = $row['status'];
        $ishd       = $row['ishd'];
        $pubtime    = $row['pubtime'];
        $albumname  = $row['albumname'];
        $filesize   = $row['filesize'];
        $progress   = $row['progress'];
        $downloader = $row['downloader'];
        $item = array("taskid"=>$taskid, "songid"=>$songid, "name"=>$name, "language"=>$language, "copyright"=>$corp, "singer"=>$singer, "singertype"=>$singertype, "videotype"=>$videotype, "status"=>$status, "hd"=>$ishd, "pubtime"=>$pubtime, "progress"=>$progress, "albumname"=>$albumname, "downloader"=>$downloader, "filesize"=>$filesize);
        array_push($songlist, $item);
    }
    $recordset = $DB->query($sql_total);
    $row = $DB->fetcharray($recordset);
    $total = $row['total'];

    $res = array("errcode"=>0,"errmsg"=>NULL, 'total'=>$total, "songlist"=>$songlist);
    echo json_encode($res);
}
else if ($cmd == "taskdownload_queryhistory") {
    $taskids  = $_GET['taskid'];
    $page     = $_GET['page'];  
    $songid   = $_GET['songid'];
    $albumid  = $_GET['albumid'];  
    $pagesize = 10;
    if (!$page)
        $page = 1;
    $condition = " where 1 ";
    $sql        = "select id, lxsongid, name, albumid, albumname, singer, taskid, submittime, stime, etime, result from t_history_download";
    $sql_total  = "select count(*) as total from t_history_download";
    if ($taskids)
        $condition = $condition . " and taskid in($taskids)";
    if ($albumid)
        $condition = $condition . " and albumid in('$albumid')";
    if ($songid) 
        $condition = $condition . " and lxsongid in ($songid)";

    $sql = $sql . $condition . " limit " . ($page - 1) * $pagesize  . "," . $pagesize;
    $sql_total = $sql_total . $condition;
    $recordset = $DB->query($sql_total);
    $row       = $DB->fetchArray($recordset);
    $total     = $row['total'];

    $recordset = $DB->query($sql);
    $history   = array( );
    while ($row = $DB->fetchArray($recordset)) {
        $id         = $row['id'];
        $taskid     = $row['taskid'];
        $name       = $row['name'];
        $album      = $row['album'];
        $subtime    = $row['submittime'];
        $stime      = $row['stime'];
        $etime      = $row['etime'];
        $downloader = $row['downloader'];
        $progress   = $row['progress'];
        $result     = $row['result'];
        $singer     = $row['singer'];
        $item = array("id"=>$id,"taskid"=>$taskid, "name"=>$name, "album"=>$album,"submittime"=>$subtime, "singer"=>$singer, "starttime"=>$stime, "endtime"=>$etime, "downloader"=>$downloader, "progress"=>$progress, "result"=>$result);
        array_push($history, $item);
    }
    $res = array("errcode"=>0, "errmsg"=>NULL, "total"=>$total, "history"=>$history);
    echo json_encode($res);    
}
else if ($cmd == "taskdownload_submitdownload") {
    $downloader = $_GET['token'];
    $songid     = $_GET['songid'];
    $albumid    = $_GET['albumid'];
    $taskid     = $_GET['taskid'];
    $force      = $_GET['force'];
    $condition  = "where 1";
    if ($taskid)
        $condition = $condition . " and taskid in($taskid)";
    if ($songid)
        $condition = $condition . " and lxsongid in($songid)";
    if ($albumid) {
        $albums     = explode(",", $albumid);
        $albumid    = implode("','", $albums);
        $albumid    = "'$albumid'";
        $condition = $condition . " and albumid in ($albumid)";
    }
    $check_sql = "select lxsongid, name, singer, albumname, status from t_runtime_download $condition";
    logger("debug", "$downloader submitdownload, $sql\n");
    $recordset = $DB->query($check_sql);
    $errcode   = 0;
    $errmsg    = NULL;
    while ($row = $DB->fetchArray($recordset)) {
        $songid = $row['lxsongid'];
        $name   = $row['name'];
        $singer = $row['singer'];
        $status = $row['status'];
        $albumname=$row['albumname'];
        if ($status != 0) { //状态不对，处于下载中或已经下载
            if ($force) {
                logger("warning", "$downloader download: $songid, $name, $singer, $albumname, $status, force to dwonload\n");
                $sql = "update t_runtime_download set status = 1,progress = 0, downloader='$downloader' where lxsongid=$songid";
                $DB->query($sql);
            }
            else
                logger("warning", "$downloader download: $songid, $name, $singer, $albumname, $status, skip download\n");
        }
        else {
            logger("info", "$downloader download: $songid, $name, $singer, $albumname, $status download\n");
            $sql = "update t_runtime_download set status = 1,progress=0,downloader='$downloader' where lxsongid=$songid";
            $DB->query($sql);
        }
    }
    #$json_str   = file_get_contents("php://input");
    #$js         = json_decode($json_str);
    #$tasklist   = $js->tasklist;
    #foreach ($tasklist as $taskid) {
    #    $sql = "update t_runtime_download set status = 1, downloader='$downloader' where taskid= $taskid";
    #    $DB->query($sql);
    #}
    $res = array("errcode"=>$errcode, "errmsg"=>$errmsg);
    echo json_encode($res);
}
elseif ($cmd == "taskedit_queryedit") {
    $editor     = $_GET["editor"];
    $cvalue     = $_GET["cvalue"];
    $pagesize   = 10;
    $page       = $_GET["page"];
    $status     = $_GET["status"]; //status = 0, waitng edit, 1, editing, 2, waiting audit
    if (!$page) {
        $page = 1;
    }
    $sql = "select * from t_runtime_editing where (phase = 2 or (phase = 3 and status = 4))";
    $sql_total = "select count(*) as total from t_runtime_editing where (phase = 2 or (phase = 3 and status = 4))";

    if ($status) {
        $sql = $sql . " and status in ($status) ";
        $sql_total = $sql_total . " and status in ($status)";
    }

    if ($editor) {
        $sql = $sql . " and editor='$editor'";
        $sql_total = $sql_total . " and editor ='$editor'";
    }
    else {
        $sql = $sql . " and editor is NULL";
        $sql_total = $sql_total . " and editor is NULL";
    }

    if ($cvalue) {
        $sql        = $sql . " and name like '%$cvalue%'";
        $sql_total  = $sql_total . " and name like '%$cvalue%'";
    }

    $sql = $sql . " limit " . ($page - 1) * $pagesize . "," . $pagesize;

    $recordset  = $DB->query($sql_total);
    $row        = $DB->fetchArray($recordset);
    $total      = (int)($row["total"]);
    $total_page = (int)($total/$pagesize);
    if ($total % $pagesize > 0 || $total_page == 0) {
        $total_page = $total_page + 1;
    }
    $res = array("errcode"=>0, "errmsg"=>"", "total"=>$total, "total_page"=>$total_page);
    $list= array();
    $recordset = $DB->query($sql);
    while ($row = $DB->fetchArray($recordset)) {
        $taskid = $row["taskid"];
        $lxsongid = $row['lxsongid'];
        $name   = $row["name"];
        $singer = $row["singer"];
        $src    = $row["src"];
        $dst    = $row['dst'];
        $duration=$row["duration"];
        $format = $row["format"];
        $acode  = $row["acode"];
        $vcode  = $row["vcode"];
        $size   = $row["videosize"];
        $bitrate= $row["bitrate"];
        $language=$row["language"];
        $videotype=$row["videotype"];
        $intime   =$row["intime"];
        $copyright=$row["copyright"];
        $style    = $row["style"];
        $editor   = $row['editor'];
        $item   = array("taskid"=>$taskid, "lxsongid"=>$lxsongid, "name"=>$name, "singer"=>$singer, "src"=>$src, "dst"=>$dst, "duration"=>$duration, "format"=>$format, "size"=>$size, "bitrate"=>$bitrate,  "acode"=>$acode, "vcode"=>$vcode,"language"=>$language, "imtime"=>$intime, "copyright"=>$copyright,"style"=>$style,"videotype"=>$videotype, "editor"=>$editor);
        array_push($list, $item);
    }
    $res["songlist"]=$list;
    echo json_encode($res);
}
elseif ($cmd == "taskedit_assigneditor") {
    $taskid = $_GET["taskid"];
    $editor = $_GET["editor"];
    $sql = "update t_runtime_editing set editor = '$editor' where taskid in($taskid)";
    $DB->query($sql);
    $res = array("errcode"=>0, "errmsg"=>NULL);
    echo json_encode($res);
}
elseif ($cmd == "taskedit_releaseeditor") {
    $taskid = $_GET['taskid'];
    $sql    = "update t_runtime_editing set editor = NULL, status=0 where taskid in ($taskid)";
    $DB->query($sql);
    $res = array("errcode"=>0, "errmsg"=>NULL);
    echo json_encode($res);
}
elseif ($cmd == "taskedit_save") {
    $json_str = file_get_contents("php://input"); //接收POST数据
    $js       = json_decode($json_str);
    $taskid   = $_GET["taskid"];
    $pb       = base64_encode($json_str);
    $sql      = "update t_runtime_editing set workspace='" . $pb . "' where taskid=" . $taskid;
    $DB->query($sql);
    $res = array("errcode"=>0, "errmsg"=>NULL);
    echo json_encode($res);
}
elseif ($cmd == "taskedit_resume") {
    $taskid     = $_GET["taskid"];
    $sql        = "select * from t_runtime_editing where taskid = " . $taskid;
    $recordset  = $DB->query($sql);
    $row        = $DB->fetchArray($recordset);
    if ($row) {
        $taskid = $row["taskid"];
        $name   = $row["name"];
        $singer = $row["singer"];
        $src    = $row["src"];
        $dst    = $row["dst"];
        $duration=$row["duration"];
        $format = $row["format"];
        $acode  = $row["acode"];
        $vcode  = $row["vcode"];
        $size   = $row["videosize"];
        $bitrate= $row["bitrate"];
        $wp     = $row["workspace"];
        $wp     = base64_decode($wp);
        $jswp   = json_decode($wp);
        $item   = array("taskid"=>$taskid, "name"=>$name, "singer"=>$singer, "src"=>$src, "dst"=>$dst, "duration"=>$duration,  "format"=>$format, "size"=>$size, "bitrate"=>$bitrate,  "acode"=>$acode, "vcode"=>$vcode, "workspace"=>$jswp);
        $res    = array("errcode"=>0, "errmsg"=>NULL, "song"=>$item);
        echo json_encode($res);
    }
    else {
        $res = array("errcode"=>-1, "errmsg"=>"the task is not exist");
        echo json_encode($res);
    }
}
elseif ($cmd == "taskedit_queryaudit") {
    $auditor    = $_GET["auditor"];
    $cvalue     = $_GET["cvalue"];
    $pagesize   = 10;
    $page       = $_GET["page"];
    $status     = $_GET["status"]; //status = 0, waitng edit, 1, editing, 2, waiting audit
    if (!$page) {
        $page = 1;
    }
    $sql = "select * from t_runtime_editing where phase = 3";
    $sql_total = "select count(*) as total from t_runtime_editing where phase = 3";

    if ($status) {
        $sql = $sql . " and status in ($status) ";
        $sql_total = $sql_total . " and status in ($status)";
    }

    if ($auditor) {
        $sql = $sql . " and auditor='$auditor'";
        $sql_total = $sql_total . " and auditor ='$auditor'";
    }
    else {
        $sql = $sql . " and auditor is NULL";
        $sql_total = $sql_total . " and auditor is NULL";
    }

    if ($cvalue) {
        $sql        = $sql . " and name like '%$cvalue%'";
        $sql_total  = $sql_total . " and name like '%$cvalue%'";
    }

    $sql = $sql . " limit " . ($page - 1) * $pagesize . "," . $pagesize;

    $recordset  = $DB->query($sql_total);
    $row        = $DB->fetchArray($recordset);
    $total      = (int)($row["total"]);
    $total_page = (int)($total/$pagesize);
    if ($total % $pagesize > 0 || $total_page == 0) {
        $total_page = $total_page + 1;
    }
    $res = array("errcode"=>0, "errmsg"=>"", "total"=>$total, "total_page"=>$total_page);
    $list= array();
    $recordset = $DB->query($sql);
    while ($row = $DB->fetchArray($recordset)) {
        $taskid     = $row["taskid"];
        $lxsongid   = $row["lxsongid"];
        $name   = $row["name"];
        $singer = $row["singer"];
        $src    = $row["src"];
        $duration=$row["duration"];
        $format = $row["format"];
        $acode  = $row["acode"];
        $vcode  = $row["vcode"];
        $size   = $row["videosize"];
        $bitrate= $row["bitrate"];
        $language=$row["language"];
        $videotype=$row["videotype"];
        $intime   =$row["intime"];
        $copyright=$row["copyright"];
        $style    = $row["style"];
        $editor   = $row['editor'];
        $item   = array("taskid"=>$taskid, "lxsongid"=>$lxsongid, "name"=>$name, "singer"=>$singer, "src"=>$src, "duration"=>$duration, "format"=>$format, "size"=>$size, "bitrate"=>$bitrate,  "acode"=>$acode, "vcode"=>$vcode,"language"=>$language, "imtime"=>$intime, "copyright"=>$copyright,"style"=>$style,"videotype"=>$videotype, "editor"=>$editor);
        array_push($list, $item);
    }
    $res["songlist"]=$list;
    echo json_encode($res);
}
elseif ($cmd == "taskedit_commit") {
    $json_str = file_get_contents("php://input"); //接收POST数据
    $js       = json_decode($json_str);
    $taskid   = $_GET["taskid"];
    $wp       = base64_encode($json_str);
    $sql      = "update t_runtime_editing set workspace = '$wp', status = 2, auditor = NULL,phase=3 where taskid=$taskid";
    $DB->query($sql);
    echo "{\"errcode\":0,\"errmsg\":\"\",\"taskid\":$taskid}"; 	
}
elseif ($cmd == "taskedit_assignauditor") {
    $taskid     = $_GET['taskid'];
    $lxsongid   = $_GET['lxsongid'];
    $auditor    = $_GET['auditor'];
    if ($lxsongid)
        $sql = "update t_runtime_editing set auditor='$auditor' where lxsongid=$lxsongid";
    else
        $sql = "update t_runtime_editing set auditor='$auditor' where taskid=$taskid";
    $DB->query($sql);
    $res = array("errcode"=>0, "errmsg"=>NULL);
    echo json_encode($res);
}
elseif ($cmd == "taskedit_releaseauditor") {
    $taskid = $_GET['taskid'];
    $sql    = "update t_runtime_editing set auditor = NULL where taskid = $taskid";
    $DB->query($sql);
    $res = array("errcode"=>0, "errmsg"=>NULL);
    echo json_encode($res);
}
elseif ($cmd == "taskedit_auditpass") {
    $taskid = $_GET["taskid"];
    $sql = "update t_runtime_editing set phase = 3, status=4 where taskid = " . $taskid;
    $DB->query($sql);
#    $sql = "select * from t_runtime_editing where taskid = $taskid";
#   $recordset = $DB->query($sql);
#    $row       = $DB->fetchArray($recordset);
#    $songid    = $row['lxsongid'];
#    $name      = $row['name'];
#    $src       = $row['src'];
#    $singer    = $row['singer'];
#    $language  = $row['language'];
#    $pubtime   = $row['pubtime'];
#    $copyright = $row['copyright'];
#    $sql = "insert into t_data_song(songid, name, singer1, language, pubtime, copyright) values('lx_web_$songid', '$name', '$singer', '$language', '$pubtime', '$copyright')";
#    $DB->query($sql);
    $res = array("errcode"=>0, "errmsg"=>NULL);
    echo json_encode($res);
}
elseif ($cmd == "taskedit_auditreject") {
    $taskid = $_GET["taskid"];
    $reason = $_GET["reason"];
    $sql = "update t_runtime_editing set reason='$reason', status = 4 where taskid = " . $taskid;
    $DB->query($sql);
    $res = array("errcode"=>0, "errmsg"=>NULL);
    echo json_encode($res);
}
elseif ($cmd == "taskconver_query") {
    $status = $_GET["status"]; //status = 0, waiting conver, status = 1, convering, status, = 2, convrted, 
    $sql    = "select * from t_runtime_editing where phase = 3 and status not in(4, 5)";
    $sql_total = "select count(*) from t_runtime_editing where phase = 3 and status not in(4,5)";
    if ($status) {
        $sql = $sql . " and status = $status";
        $sql_total = $sql_total . " and status = $status";
    }
    $pagesize   = 10;
    $page       = $_GET["page"];
    
    $sql = $sql . " limit " . ($page  - 1) * $pagesize . "," . $pagesize;
    $recordset = $DB->query($sql);
    $list= array();
    $recordset = $DB->query($sql);
    while ($row = $DB->fetchArray($recordset)) {
        $taskid = $row["taskid"];
        $name   = $row["name"];
        $singer = $row["singer"];
        $src    = $row["src"];
        $duration=$row["duration"];
        $format = $row["format"];
        $acode  = $row["acode"];
        $vcode  = $row["vcode"];
        $size   = $row["size"];
        $bitrate= $row["bitrate"];
        $setting= $row["workspace"];
        $item   = array("taskid"=>$taskid, "name"=>$name, "singer"=>$singer, "src"=>$src, "duration"=>$duration, "format"=>$format, "size"=>$size, "bitrate"=>$bitrate,  "acode"=>$acode, "vcode"=>$vcode, "setting"=>base64_decode($setting));
        array_push($list, $item);
    }
    $res = array("errcode"=>0, "errmsg" => NULL, "songlist"=>$list);   
    echo json_encode($res);
}
elseif ($cmd == "data_copyright_query"){
    $filter = $_GET["corp"];
    $sql = "select * from t_config_copyright where 1";
    if ($filter) {
        $sql = "$sql and copyright like '%$filter%‘";
    }
    $recordset = $DB->query($sql);
    $corps = array();
    while ($row = $DB->fetchArray($recordset)) {
        $id   = $row["id"];
        $corp = $row["copyright"];
        $item = array("id"=>$id, "copyright"=>$corp);
        array_push($corps, $item);
    }
    $res = array("errcode"=>0, "errmsg"=>NULL,"corps"=>$corps);
    echo json_encode($res);
}
elseif ($cmd == "data_copyright_add") {
    $corp = $_GET["corp"];
    $sql = "insert into t_config_copyright(copyright) values('$corp') on duplicate key update copyright='$corp'";
    $DB->query($sql);
    $sql = "select * from t_config_copyright where copyright='$corp'";
    $recordset = $DB->query($sql);
    $row = $DB->fetchArray($recordset);
    $id  = $row["id"];
    $cp  = $row["copyright"];
    $corp=array("id"=>$id, "copyright"=>$cp);
    $res =array("errcode"=>0,"errmsg"=>NULL,"corp"=>$corp);
    echo json_encode($res);
}
elseif ($cmd == "data_copyright_del") {
    $corp = $_GET["corp"];
    if ($corp) {
        $sql = "delete from t_config_copyright where copyright='$corp'";
        $DB->query($sql);
        $res = array("errcode"=>0, "errmsg"=>NULL);
        echo json_encode($res);
    }
    else {
        $res = array("errcode"=>-1,"errmsg"=>"unknow corp");
        echo json_encode($res);
    }
}
elseif ($cmd == "data_backlist_add") {
    $type = $_GET['type'];
    $id   = $_GET['id'];
    $desc = $_GET['desc'];
    $sql  = "select type, id from t_data_backlist where type=$type and id = '$id'";
    $recordset = $DB->query($sql);
    $row       = $DB->fetchArray($recordset);
    $res       = NULL;
    if ($row)
        $res = array("errcode"=>-1, "errmsg"=>"the item(singer|song) was in the backlist");
    else {
        $sql = "insert into t_data_backlist(type, id, desc) values($type, '$id', '$desc')";
        $DB->query($sql);
        $res = array("errcode"=>0, "errmsg"=>NULL);
    }       
    echo json_encode($res);
}
elseif ($cmd == "data_backlist_del") {
    $type = $_GET['type'];
    $id   = $_GET['id'];
    $sql  = "delete from t_data_backlist where type=$type and id = '$id'";
    $DB->query($sql);
    $sql  = "insert into t_data_backlist(type, id, desc) values(3, '-1', 'remove from backlist flag') on duplicate key update utime=now()";
    $DB->query($sql);
    $res = array("errcode"=>0, "errmsg"=>NULL);
    echo json_encode($res);
}
elseif ($cmd == "data_backlist_query") {
    $type = $_GET['type'];
    $id   = $_GET['id'];
    $page = $_GET['page'];
    $pagesize = 10;
    if (!$page)
        $page = 1;
    if ($type == 1) {
        $ql = "select * from t_data_song where songid = '$id' limit " . ($page -1) * $pagesize . ",$pagesize";
        $recordset = $DB->query($sql);
        $backlist  = array();
        while ($row = $DB->fetchArray($recordset)) {
            $songid     = $row['songid'];
            $name       = $row['name'];
            $src        = $row['src'];
            $language   = $row['language'];
            $entry      = array("songid"=>$songid, "name"=>$name, "src"=>$src, "language"=>$language);
            array_push($backlist, $entry);
        }
        $res = array("errcode"=>0, "errmsg"=>NULL, "backlist"=>$backlist);
        echo json_encode($res);
    }
    elseif ($type == 2) {
        $sql = "select * from t_data_singer where singerid='$id' limit ". ($page -1) * $pagesize . ",$pagesize";
        $recordset = $DB->query($sql);
        $backlist  = array();
        while ($row = $DB->fetchArray($recordset)) {
            $singerid   = $row['singerid'];
            $name       = $row['name'];
            $language   = $row['language'];
            $area       = $row['area'];
            $entry      = array("singerid"=>$songid, "name"=>$name, "area"=>$area, "language"=>$language);
            array_push($backlist, $entry);
        }   
        $res = array("errcode"=>0, "errmsg"=>NULL, "backlist"=>$backlist);
        echo json_encode($res);      
    }
    else {
        $res = array("errcode"=>-1, "errmsg"=>"unknow backlist type");
        echo json_encode($res);
    }
}
elseif ($cmd == "data_song_query") {
    $search_text    = $_GET['search_text'];
    $status         = $_GET["status"];
    $page           = $_GET["page"];
    $name           = $_GET["name"];
    $pubtime_stime  = $_GET["stime"];
    $pubtime_etime  = $_GET["etime"];
    $corp           = $_GET["copyright"];
    $album          = $_GET['album'];
    $songid         = $_GET['songid'];
    $excep          = $_GET['excep'];

    $pagesize       = 10;    
    $sql = "select songid, name, src_url, cover_url, language, videotype, pubtime, singer1, singertype, ishd, album, copyright, tag_QENRE, tag_FEELING, tag_MELODY, intro from t_data_song where 1";    
    $sql_total = "select count(*) as total from t_data_song where 1";
    
    if ($songid) {
        $sql = $sql . " and songid = '$songid'";
        $sql_total = $sql_total . " and songid='$songid'";
    }
    if ($name) {
        $sql = $sql . " and name like '%$name%'";
        $sql_total = $sql_total . " and name like '%$name%'";
    }
    if ($corp) {
        $sql        = $sql . " and copyright='$corp'";
        $sql_total  = $sql_total . " and copyright='$corp'";
    }
    if ($pubtime_stime || $pubtime_etime) {
        $sql = $sql . " and (unix_timestamp(pubtime) >=$pubtime_stime and unix_timestamp(pubtime) <=$pubtime_etime)";    
        $sql_total  = $sql_total . " and (unix_timestamp(pubtime) >=$pubtime_stime and unix_timestamp(pubtime) <=$pubtime_etime)";
    }

    if ($album) {
        $sql = $sql . " and album like '%$album%'";
        $sql_total = $sql_total . " and album like '%$album%'";
    }
    
    if ($excep) {
        $songids = explode(",", $excep);
        for ($i = 0; $i < count($songids); $i++) {
            $id = $songids[$i];
            $id_str = "\"$id\"";
            $songids[$i] = $id_str;
        }
        $excep_str = implode(",", $songids);
        $sql = $sql . " and songid not in ( $excep_str )";
        $sql_total = $sql_total . " and songid not in ( $excep_str )";
    }
    if ($search_text) {
        $search_type = $_GET['search_type'];
        $condition  = 1;
        if ($search_type == 1)
            $condtion = " name like '%$search_text%";
        elseif ($search_type == 2)
            $condition = " singer1 like '%$search_text%'";
        elseif ($search_type == 3) {
                $songids     = explode(",", $search_text);
                $asongid     = implode("','", $songids);
                $songid      = "'$asongid'";
                $condition   = " songid in ($songid)";
        }
        else
            $condition = " copyright='$search_text'";
        $sql = $sql . " and $condition";
        $sql_total = $sql_total . " and $condition";
    }
    if ($page)
       $sql = $sql . " limit " . ($page - 1) * $pagesize . "," . $pagesize;

    $recordset = $DB->query($sql);  
    $songlist  = array();
    while ($row = $DB->fetchArray($recordset)) {
        $songid     = $row['songid'];
        $name       = $row['name'];
        $language   = $row['language'];
        $corp       = $row['copyright'];
        $singer     = $row['singer1'];
        $singertype = $row['singertype'];
        $videotype  = $row['videotype'];
        $pubtime    = $row['pubtime'];
        $album      = $row['album'];
        $cover_path = $row['cover_url'];
        $src_path   = $row['src_url'];
        $intro      = $row['intro'];        

        $tag_MELODY = $row['tag_MELODY'];
        $tag_FEELING= $row['tag_FEELING'];
        $tag_QENRE   =$row['tag_QENRE'];
        $tag_FEELING_L32 = $tag_FEELING & 0x00000000FFFFFFFF;
        $tag_FEELING_H32 = ($tag_FEELING &  0XFFFFFFFF00000000) >> 32;
        $tag_MELODY_L32  = $tag_MELODY & 0X00000000FFFFFFFF;
        $tag_MELODY_H32  = ($tag_MELODY &  0XFFFFFFFF00000000) >> 32;
        $tag_QENRE_L32   = $tag_QENRE & 0x00000000FFFFFFFF;
        $tag_QENRE_H32   = ($tag_QENRE &  0XFFFFFFFF00000000) >> 32;

        $item = array("songid"=>$songid, "name"=>$name, "language"=>$language, "copyright"=>$corp, "singer"=>$singer, "singertype"=>$singertype, "videotype"=>$videotype, "hd"=>$ishd, "pubtime"=>$pubtime, "album"=>$albumname, "tag_QENRE_L32"=>$tag_QENRE_L32, "tag_QENRE_H32"=>$tag_QENRE_H32, "tag_MELODY_L32"=>$tag_MELODY_L32, "tag_MELODY_H32"=>$tag_MELODY_H32, "tag_FEELING_H32"=>$tag_FEELING_H32, "tag_FEELING_L32"=>$tag_FEELING_L32, "cover_path"=>$cover_path, "src_path"=>$src_path, "intro"=>$intro);
        array_push($songlist, $item);
    }
    $recordset = $DB->query($sql_total);
    $row = $DB->fetcharray($recordset);
    $total = $row['total'];
    $res = array("errcode"=>0,"errmsg"=>NULL, 'total'=>$total, "songlist"=>$songlist);
    echo json_encode($res);
}
elseif ($cmd == "data_singer_query") {
    $name       = $_GET["name"];
    $page       = $_GET["page"];
    $sex        = $_GET['sex'];
    $singerid   = $_GET['singerid'];
    $search_text= $_GET['search_text'];
    if ($search_text && is_numeric($search_text))
        $singerid = $search_text;
    if ($search_text && (!is_numeric($search_text)))
        $name = $search_text;
        
    $pagesize = 10;
    if (!$page)
        $page = 1;
    $condition = " where 1";
    if ($name) 
        $condition = $condition . " and name like '%$name%'";
    if ($sex)
        $condition = $condition . " and sex like '%$sex%'";
    if ($singerid)
        $condition = $condition . " and singerid=$singerid";

    $sql        = "select * from t_data_singer $condition limit " . ($page - 1) * $pagesize . "," . $pagesize;
    $sql_total  = "select count(*) as total from t_data_singer $condition";

    $recordset  = $DB->query($sql_total);
    $row        = $DB->fetchArray($recordset);
    $total      = $row["total"];

    $recordset = $DB->query($sql);
    $res = array("errcode"=>0, "errmsg"=>"", "total"=>$total, "total_page"=>$total_page);
    $list = array();
    while ($row = $DB->fetchArray($recordset)) {
        $singerid    = $row["singerid"];
        $name        = $row["name"];
        $sex         = $row['sex'];
        $country     = $row['country'];
        $pic         = $row['pic'];
        $pinyin      = $row['pinyin'];
        $pinyin_1st  = $row['pinyin_first'];
        $song_count  = $row['song_count'];
        $intro       = $row['intro'];
        $item        = array("singerid"=>$singerid, "name"=>$name, "sex"=>$sex, "country"=>$country, "pic"=>$pic, "pinyin"=>$pinyin, "pinyin_first"=>$pinyin_1st, "song_count"=>$song_count, "intro"=>$intro);
        array_push($list, $item);
    }
    $res["singerlist"] = $list;
    echo json_encode($res);
}
elseif ($cmd == "data_singer_create_singerid"){
    $sql = "select max(singerid) + 1 from t_data_singer";
    $recordset = $DB->query($sql);
    $row       = $DB->fetchArray($recordset);
    $nsingerid = $row[0];
    $res = array("errcode"=>0, "errmsg"=>NULL, "singerid"=>$nsingerid);
    echo json_encode($res);
}
elseif ($cmd == "data_singer_save") {
    $json_str = file_get_contents("php://input"); //接收POST数据
    $js       = json_decode($json_str);
    $singerid = $_GET['singerid'];
    $name     = addslashes($js->name);
    $sex      = $js->sex;
    $country  = $js->country;
    $pic      = $js->pic;
    $pinyin   = $js->pinyin;
    $pinyin_1st=$js->pinyin_first;
    $song_count=$js->song_count;
    $intro     =addslashes($js->intro);

    $sql = "insert into t_data_singer(singerid, name, sex, country, pic, pinyin, pinyin_first, song_count, intro) values('$singerid', '$name', '$sex', '$country', '$pic', '$pinyin', '$pinyin_first', $song_count, '$intro') on duplicate key update name='$name', sex='$sex', pic='$pic', pinyin='$pinyin', pinyin_first='$pinyin_1st', song_count=$song_count, intro='$intro'";
    $DB->query($sql);
    $res = array("errcode"=>0, "errmsg"=>NULL);
    echo json_encode($res);
}
elseif ($cmd == "publish_songlist_createid") {
    $sql = "select max(id) + 1 from t_runtime_songlist";
    $recordset = $DB->query($sql);
    $row = $DB->fetchArray($recordset);
    $id  = 1000;
    if ($row && !is_null($row[0]))
        $id  = $row[0];
    $res = array("errcode"=>0, "errmsg"=>NULL, "listid"=>$id);
    echo json_encode($res);
}
elseif ($cmd == "publish_songlist_save") {
    $json_str = file_get_contents("php://input"); //接收POST数据
    $js       = json_decode($json_str);
    $listid   = $_GET['listid'];
    $creator  = $_GET['creator'];
    $songlist = json_encode($js->songlist);
    $singerlist=json_encode($js->singerlist);
    $name     = $js->name;
    $desc     = $js->desc;
    $sql      = "select * from t_runtime_songlist where id=$listid and status != 0";
    $recordset= $DB->query($sql);
    $row      = $DB->fetchArray($recordset);
    if ($row) {
        $res = array("errcode"=>-1, "errmsg"=>"the songlist was exist, and the songlist can't edit");
        echo json_encode($res);
    }
    else {        
        $sql      = "insert into t_runtime_songlist(id, name, descript, songlist, singerlist, creator, createtime, status) values($listid, '$name', '$desc', '$songlist', '$singerlist', '$creator', now(), 0) ON DUPLICATE KEY UPDATE name='$name', descript ='$desc', songlist='$songlist', singerlist='$singerlist'";
        $DB->query($sql);
        $res = array("errcode"=>0, "errmsg"=>NULL);     
        echo json_encode($res);
    }
}
elseif ($cmd == "publish_songlist_edit") {
    $listid = $_GET['listid'];
    $sql = "select listid, name, desc, songlist from t_publish_songlist where listid = $listid";
    $recordset  = $DB->query($sql);
    $row        = $DB->fetchArray($recordset);
    $name       = $row['row'];
    $desc       = $row['desc'];
    $creator    = $row['creator'];
    $createtime = $row['createtime'];
    $songlist   = json_decode($row['songlist']);
    $singerlist = json_decode($row['singerlist']);
    $res = array("errcode"=>0, "errmsg"=>NULL, "listid"=>$listid, "name"=>$name, "desc"=>$desc, "creator"=>$creator, "createtime"=>$createtime, "songlist"=>$songlist, "singerlist"=>$singerlist);
    echo json_encode($res);
}
elseif ($cmd == "publish_songlist_query") {
    $name   = $_GET['name'];
    $listid = $_GET['listid'];
    $page   = $_GET['page'] ? $_GET['page'] : 1;
    $pagesize = 10;
    $sql    = "select id, name, descript, songlist, singerlist, creator, createtime, status from t_runtime_songlist";
    $sql_total = "select count(*) as total from t_runtime_songlist";
    $condition = " where 1 ";
    if ($name)
        $condition = $condition . " and name like '%$name%'";
    if ($listid)
        $condition = $condition . " and id=$listid";

    $sql_total = $sql_total . $condition;
    $sql       = $sql . $condition . " limit " . ($page - 1) * $pagesize . ",$pagesize";    

    $recordset  = $DB->query($sql_total);
    $row        = $DB->fetchArray($recordset);
    $total      = $row['total'];
    
    $recordset      = $DB->query($sql);
    $songlist_tab   = array();
    while ($row = $DB->fetchArray($recordset)) {
        $listid = $row['id'];
        $name   = $row['name'];
        $desc   = $row['descript'];
        $creator= $row['creator'];
        $createtime =$row['createtime'];
        $songlist   =json_decode($row['songlist']);
        $songnum    = count($songlist);
        $singerlist =json_decode($row['singerlist']);
        $singernum  = count($singerlist);

        $status     =$row['status'];
        $item   = array("listid"=>$listid, "name"=>$name, "desc"=>$desc, "songnum"=>$songnum, "songlist"=>$songlist, "singernum"=>$singernum,  "singerlist"=>$singerlist,  "creator"=>$creator, "createtime"=>$createtime, "status"=>$status);
        array_push($songlist_tab, $item);
    }
    $res        = array("errcode"=>0, "errmsg"=>NULL, "total"=>$total, "songlist"=>$songlist_tab); 
    echo json_encode($res);    
}
elseif ($cmd == "publish_songlist_delete") {
    $listid = $_GET['listid'];
    $sql    = "delete from t_publish_songlist where listid=$listid";
    $DB->query($sql);
    $res = array("errcode"=>0, "errmsg"=>NULL);
    echo json_encode($res);
}
elseif ($cmd == "publish_songlist_machine_query") {
    $sql        = "select * from t_config_machset";
    $sql_total  = "select count(*) as total from t_config_machset";
    $recordset  = $DB->query($sql_total);
    $row        = $DB->fetchArray($recordset);
    $total      = $row['total'];
    
    $recordset  = $DB->query($sql);
    $machsetlist= array();
    while ($row = $DB->fetchArray($recordset)) {
        $id     = $row['id'];
        $name   = $row['name'];
        $machset= json_decode($row['machset']);
        $item   = array("id"=>$id, "name"=>$name, "machset"=>$machset);
        array_push($machsetlist, $item);
    }
    $res = array("errcode"=>0, "errmsg"=>NULL, "total"=>$total, "machset"=>$machsetlist);
    echo json_encode($res);
}
elseif ($cmd == "publish_songlist") {
    $songlistid = $_GET['songlistid'];
    $inc        = $_GET['include'];
    $exc        = $_GET['except'];
    $publisher  = $_GET['publisher'];
    $include    = array();
    $except     = array();
    if ($inc) {
        $tab = explode(",", $inc);
        foreach($tab as $i)
            array_push($include, (int)$i); 
    }  
    if ($exc) {
        $tab = explode(",", $exc);
        foreach($tab as $e) 
            array_push($except, (int)$e);
    }
    $include = json_encode($include);
    $except  = json_encode($except); 
    $sql = "insert into t_publish_songlist(songlistid, include_mset, except_mset, pubtime, publisher, status) values($songlistid, '$include', '$except', now(), '$publisher', 0)";
    $DB->query($sql);
    $res = array("errcode"=>0, "errmsg"=>NULL, "status"=>0);
    echo json_encode($res);
}
elseif ($cmd == "publish_songlist_query") {
    $songlistid = $_GET['songlistid'];
    $sql = "select * from t_publish_songlist where tsonglistid=$songlistid";
    $recordset  = $DB->query($sql);
    $res        = array("errcode"=>0, "errmsg"=>NULL, "songlist"=>NULL);
    if (!$row) {
        $res['errcode'] = -1;
        $res['errmsg']  = "the songlist was not published";
    }
    else {
        $res['songlist']= $row;
    } 
   echo  json_encode($res);
}
elseif ($cmd == "upload") {
    $key      = "file";
    $tmpfile  = isset($_POST[$key . "_path"]) ? $_POST[$key . "_path"] : $_POST["_path"];
    $pic     = isset($_POST[$key . "_name"]) ? $_POST[$key . "_name"] : $_POST["_name"];
    chgrp($tmpfile, 'nobody');
    $md5file  = md5_file($tmpfile);
    $dstfile  = "/usr/local/tengine-2.1.2/html/sdbmgr/storeage/$md5file" . "_$pic";
    if (file_exists($dstfile))
        unlink($dstfile);
    rename($tmpfile, $dstfile);
    $uri      = $_SERVER['REQUEST_URI'];
    $local    = "/usr/local/tengine-2.1.2/html/sdbmgr/";
    $httpref  = "http://sdbmgr.singworld.cn/";
    $src      = str_replace($local, $httpref, $dstfile);
    $res = array("errcode"=>0, "errmsg"=>NULL, "src"=>$src);
    echo json_encode($res);
}
else {
    echo "{\"errcode\":-1,\"errmsg\":\"api undefine cmd:$cmd\"}";
}
?>
