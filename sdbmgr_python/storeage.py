#!/usr/bin/python
#coding=utf-8
import os
import sys
import time
import ConfigParser
import uuid
import httplib
import pycurl
import demjson
import logging
import string
import zipfile
import re
import MySQLdb
import sqlite3
import hashlib
import gzip
import urllib2
import base64
from ctypes import *
from StringIO import StringIO
from sys import argv
import threadpool
import xml.dom.minidom
from multiprocessing import Process,Pool
import sys
reload(sys)
sys.setdefaultencoding('utf-8')
date = "";
if len(argv) > 1 :
    date = argv[1];
else :
    date = time.strftime('%Y%m',time.localtime(time.time()));
dbhost      = "192.168.1.104";
dbport      = 3306;
dbuser      = "root";
dbpasswd    = "aimei";
songdb      = "sdbmgr";

path        = sys.path[0];
logfile     = path + os.sep + ".." + os.sep + "logs" + os.sep + "storeage.log";
logger      = logging.getLogger("sdbmgr");
logger.setLevel(logging.DEBUG);
fh          = logging.FileHandler(logfile);
fh.setLevel(logging.DEBUG);
formatter   = logging.Formatter('%(asctime)s <%(name)s> %(levelname)s: %(message)s');
fh.setFormatter(formatter);
logger.addHandler(fh);

#global config
storeage_srv = "http://videosrc.song.singworld.cn/";
local_srv    = "http://sdbmgr.singworld.cn/";
local_root   = "/usr/local/tengine-2.1.2/html/sdbmgr/";
encoder      = "/opt/sdbmgr/tools/mencode_d";

def upload2clod(localfile, serverpath, dstfilename) :
    buff = StringIO( );
    c = pycurl.Curl()
    c.setopt(c.URL, serverpath);
    c.setopt(c.WRITEDATA, buff);
    c.setopt(c.HTTPPOST, [('sdb', (c.FORM_FILE, localfile,c.FORM_FILENAME, dstfilename, c.FORM_CONTENTTYPE, "application/octet-stream")),])
    c.perform()
    c.close();
    print(buff.getvalue());
    respone = demjson.decode(buff.getvalue());
    md5     = respone['md5'];
    src     = respone['src'];
    return src, md5;

def upload_song_cover(lxsongid, src) :
    cover       = src.replace(local_srv, local_root);
    dstfilename = "web_lx" + str(lxsongid) + "." + cover.split(".")[-1];
    now     = time.localtime(time.time());
    st_path = storeage_srv + "upload?path=/song/" +time.strftime("%Y%m%d",now) + "/web_lx_" + str(lxsongid) + "/";
    return  upload2clod(cover, st_path, dstfilename);

def upload_song_video(lxsongid, src) :
    mp4       = src.replace(local_srv, local_root);
    dstfilename = "web_lx" + str(lxsongid) + "." + mp4.split(".")[-1];
    now     = time.localtime(time.time());
    st_path = storeage_srv + "upload?path=/song/" +time.strftime("%Y%m%d",now) + "/web_lx_" + str(lxsongid) + "/";
    return  upload2clod(mp4, st_path, dstfilename);

def encodemp4(srcmp4):
    dstmp4 = srcmp4 + "_encoded";
    cmd =  encoder + " -s " + srcmp4  + " -o " + dstmp4;
    reault = os.popen(cmd);
    return dstmp4;
    
def putin_songdb(conn, row) :
    taskid      = row[0];
    lxsongid    = row[1];
    songid      = "web_lx_" + str(lxsongid);
    workspace   = base64.b64decode(row[2]);
    duration    = row[3];
    localmp4    = row[4];
    encoded_mp4 = encodemp4(localmp4);

    wp          = demjson.decode(workspace);       
    name        = wp['name'].encode('utf8');
    cover       = wp['banner_src'];
    (cover_src, cover_md5) = upload_song_cover(lxsongid, cover);
    (video_src, video_md5) = upload_song_video(lxsongid, encoded_mp4);
    pinyin_first= wp['first_letter'];
    pinyin      = wp['chinese_pinyin'];
    descript    = wp['song_description'];
    lyricist    = wp['lyricists'].encode('utf8');
    album       = wp['belong_album'].encode('utf8');
    sql = ("insert into t_data_song(songid, name, cover_url, cover_md5, src_url,  src_md5, pinyin_first, pinyin, descript, lyricist,  album) values("
            "%s, %s, %s, %s, %s,   %s, %s, %s, %s, %s,  %s)");
            #%(songid, name, cover_src, cover_md5, video_src,  video_md5, pinyin_first, pinyin, descript, lyricist,  album)
          #); 
    cur = conn.cursor();
    cur.execute("set names utf8");
    conn.commit();
    cur.execute(sql, (MySQLdb.Binary(songid), MySQLdb.Binary(name), MySQLdb.Binary(cover_src), MySQLdb.Binary(cover_md5), MySQLdb.Binary(video_src), MySQLdb.Binary(video_md5), MySQLdb.Binary(pinyin_first), MySQLdb.Binary(pinyin), MySQLdb.Binary(descript), MySQLdb.Binary(lyricist), MySQLdb.Binary(album)));
    conn.commit();
    sql = "update t_runtime_editing set status = 5 where taskid = " + str(taskid);
    cur.execute(sql);
    conn.commit();
    sql = "update t_runtime_download set status = 4 where taskid = " + str(taskid);
    cur.execute(sql);
    conn.commit();
    print(workspace);
    return 0;
conn = MySQLdb.connect(host=dbhost, port=dbport, user=dbuser, passwd=dbpasswd, db=songdb)
cur  = conn.cursor();
cur.execute("set names utf8");
conn.commit();
cur.close();
while (1) :
    cur = conn.cursor()
    cur.execute("select taskid, lxsongid, workspace, duration, dst from t_runtime_editing where status = '4'")
    rows = cur.fetchall()
    conn.commit()
    for row in rows:
        try :
            putin_songdb(conn, row);
        except Exception,e:
            print(e);
    cur.close();
    time.sleep(10);
conn.close();
