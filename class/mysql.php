<?php
class DB{

    var $conn;

    function __construct($dbHost,$dbUser,$dbPass,$dbData,$dbConn=0) {
        $this->connect($dbHost,$dbUser,$dbPass,$dbData,$dbConn);
    }

    function connect($dbHost,$dbUser,$dbPass,$dbData,$dbConn=0) {
        $this->conn = mysqli_connect($dbHost,$dbUser,$dbPass, $dbData) or die("Do not connect ".$dbHost.",Please Check the File:config.php");
        $this->query("set names utf8");
    }

    function close() {
        return mysqli_close($this->conn);
    }

    function query($sql) {
        global $prefix;
        $sql = ($prefix == "" ? $sql : str_replace("",$prefix,$sql));
        $query = mysqli_query($this->conn, $sql);
        if ($query == FALSE) {
            echo mysqli_error($this->conn);
            return NULL;
        }
        if(empty($query)) echo (mysqli_error($this->conn)."<br />\n");
        return $query;
    }

    function fetchArray($query) {
        return mysqli_fetch_array($query);
    }

    function affectedRows() {
        return mysqli_affected_rows();
    }

    function numRows($query) {
        $rows = mysqli_num_rows($query);
        return $rows;
    }

    function freeResult($query) {
        return mysqli_free_result($query);
    }

    function insertID() {
        $id = mysqli_insert_id();
        return $id;
    }

    function fetchRow($query) {
        $rows = mysqli_fetch_row($query);
        return $rows;
    }

    function mysqlResult($query,$row=0) {
        return mysql_result($query,$row);
    }

    function numFields($query) {
        return mysql_num_fields($query);
    }

    function escapeString($msg) {
        return mysql_escape_string($msg);
    }

    #function getdescript($property, $value) {
    #    $sql = "select `desc` from 1s2ndescript where property='$property' and value=$value";
    #    $record = $this->query($sql);
    #    $m_rows = $this->fetchArray($record);
    #    return $m_rows["desc"];
    #}
}
?>
           
