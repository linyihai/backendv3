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
logfile     = path + os.sep + ".." + os.sep + "logs" + os.sep + "downloader.log";
conver_log  = path + os.sep + ".." + os.sep + "logs" + os.sep + "conver_";
logger      = logging.getLogger("sdbmgr");
logger.setLevel(logging.DEBUG);
fh          = logging.FileHandler(logfile);
fh.setLevel(logging.DEBUG);
formatter   = logging.Formatter('%(asctime)s <%(name)s> %(levelname)s: %(message)s');
fh.setFormatter(formatter);
logger.addHandler(fh);

local_filepath = '/usr/local/tengine-2.1.2/html/sdbmgr/';
local_httppath = 'http://sdbmgr.singworld.cn/';

progress_prev = 0
progressing_lxsongid = 0; ' ' 
def progress(dt, d, ut, u):
    global conn
    global cur
    global progressing_lxsongid;
    global progress_prev
    if dt == 0:
        return
    progress = int(d / dt * 100)
    if progress != progress_prev and progress % 5 == 0:
        print ("downloaded:","%d%%" % progress)
        sql = 'update t_runtime_download set progress = %d where lxsongid = %s' %(progress, progressing_lxsongid)
        print(sql);
        cur.execute(sql)
        conn.commit()
    progress_prev = progress

def download_song(conn, taskid, lxsongid, src) :
    print("[" +  str(lxsongid) + "] start to process...");
    cur = conn.cursor();
    progress_prev = 0;
    dst = '/opt/sdbmgr/download/'
    tmp_path = dst + src.split('/')[-1].split('.')[0] + "_tmp"
    file_path = dst + src.split('/')[-1].split('.')[0] + ".mpg"
    stime = time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(time.time()))
    print("[" +  str(lxsongid) + "] start to download...");
    sql1 = "update t_runtime_download set status = 1, progress = 0 where lxsongid = %d"%(lxsongid)
    cur.execute(sql1)
    conn.commit();
    global progressing_lxsongid;
    progressing_lxsongid = lxsongid;
    #save file
    with open(tmp_path, 'wb') as f:
        curl = pycurl.Curl();
        curl.setopt(curl.URL, src);
        curl.setopt(curl.HTTPHEADER, ["USERINFO: username=yx_mdyy;password=46d3d32f012cc48a88e746eb496a9d29;"]);
        curl.setopt(curl.WRITEDATA, f);
        curl.setopt(pycurl.NOPROGRESS,False)
        curl.setopt(pycurl.PROGRESSFUNCTION,progress)
        curl.perform( );
        curl.close( );
        logger.info("download(" + src + ") finish\n");
        os.rename(tmp_path, file_path);
    print("[" +  str(lxsongid) + "]  download finish");
    etime = time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(time.time()));
    sql = "update t_runtime_download set status = 2, progress=100 where lxsongid=" + str(lxsongid);
    cur.execute(sql);
    conn.commit();
    #conve video

    try:
        src     = file_path;
        dst     = local_filepath + "storeage/web_lx_" + str(lxsongid) + ".mp4";
        cmd = "/home/caoqinan/ffmpeg -i %s -map 0:v -vcodec libx264 -pix_fmt yuv420p -x264opts keyint=60:min-keyint=10:threads=8:bframes=4 -preset veryslow -crf 20 -map 0:a -acodec copy -sn -y %s > %s%d.log 2>&1" %(src, dst, conver_log, lxsongid);
        print("[%d] start to conver, cmd:%s"%(lxsongid, cmd));
        reault = os.system(cmd);
        sql = "update t_runtime_download set status = 3, dst='%s' where lxsongid=" + str(lxsongid, dst);
        cur.execute(sql);
        conn.commit();
        #print reault.read()
        print("[" +  str(lxsongid) + "] conver is finish...");
    except Exception as e:
        print e

    try:
        print("[%d] add to download history..."%lxsongid);
        etime = time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(time.time()))
        sql2 = 'insert into t_history_download (taskid,lxsongid,name,singer,albumid,albumname,downloader,stime, etime, ts) select taskid,lxsongid,name,singer,albumid,albumname,downloader, "%s", "%s", ts from t_runtime_download where lxsongid = %d ' % (stime, etime, lxsongid);
        print(sql2);
        cur.execute(sql2)
        conn.commit()
        #insert t_runtime_editing
        print("[%d] move from download list to edit list..."%lxsongid);
        linux_command = "mediainfo %s --Output=XML" % (dst)
        nodes = os.popen(linux_command)
        dom = xml.dom.minidom.parse(nodes)
        format_video = dom.getElementsByTagName('Format')[1].firstChild.data
        format_audio = dom.getElementsByTagName('Format')[2].firstChild.data
        duration_node = dom.getElementsByTagName('Duration')[0].firstChild.data.replace(' s','').split('min')
        duration_second =int(duration_node[0]) * 60 + int(duration_node[1])
        bitrate_node = dom.getElementsByTagName('Bit_rate')[0].firstChild.data
        width_node = dom.getElementsByTagName('Width')[0].firstChild.data
        height_node = dom.getElementsByTagName('Height')[0].firstChild.data
        videosize_node = width_node.replace(' pixels','') + 'x' + height_node.replace('pixels','');
        playaddr = dst.replace(local_filepath, local_httppath);
        sql6 = 'insert into t_runtime_editing(taskid,lxsongid,src,name,singer,album,pubtime,videotype,language,copyright, phase, status, dst, acode, vcode, duration, bitrate, videosize, intime, format) select taskid,lxsongid,src,name,singer,albumname,pubtime,videotype,language,copyright, 2, 0, "%s", "%s", "%s", %d, "%s", "%s", now(), "mp4" from t_runtime_download where lxsongid = %d' % (playaddr, format_video, format_audio, duration_second, bitrate_node, videosize_node, lxsongid);
        print(sql6);
        cur.execute(sql6);
        conn.commit()
        #update t_runtime_download table
        sql3 = "update t_runtime_download set status = 3, progress =100, dst='%s' where taskid = %d"%(dst, taskid)
        cur.execute(sql3)
        conn.commit()
        print("[" +  str(lxsongid) + "] move from download list to edit list is finish");
    except Exception as e:
        sql_roll = "update t_runtime_download set status = -1 where lxsongid= %d" % lxsongid
        cur.execute(sql_roll)
        conn.commit()
        print e
    print("[" +  str(lxsongid) + "] process is finish");
    return 0;

conn = MySQLdb.connect(host=dbhost, port=dbport, user=dbuser, passwd=dbpasswd, db=songdb)
while (1) :
    cur = conn.cursor()
    cur.execute("select taskid, lxsongid, src from t_runtime_download where status = '1'")
    rows = cur.fetchall()
    conn.commit()
    counter = 0;
    for row in rows:
        taskid      = row[0];
        lxsongid    = row[1];
        src         = row[2];
        download_song(conn, taskid, lxsongid, src);
        counter = counter+1;
    logger.info("download " + str(counter) + " songs\n");
    time.sleep(1);
   # pool.apply_async(func=download_song, args=(each,))
#pool.close()
#pool.join()
#pool = threadpool.ThreadPool(5)
#requests = threadpool.makeRequests(download_song,srcs)
#[pool.putRequest(req) for req in requests]
#pool.wait()

