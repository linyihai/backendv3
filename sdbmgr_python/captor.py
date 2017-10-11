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
logfile     = path + os.sep + ".." + os.sep + "logs" + os.sep + "captor.log";
logger      = logging.getLogger("sdbmgr");
logger.setLevel(logging.DEBUG);
fh          = logging.FileHandler(logfile);
fh.setLevel(logging.DEBUG);
formatter   = logging.Formatter('%(asctime)s <%(name)s> %(levelname)s: %(message)s');
fh.setFormatter(formatter);
logger.addHandler(fh);

def isexist_download(conn, songid) :
    cur = conn.cursor( );
    sql = "select lxsongid from t_runtime_download where lxsongid=" + str(songid);
    res = cur.execute(sql);
    conn.commit();
    cur.close();
    return res;

def compute_md5(src) :
    value = '';
    if (src and src != '') :
        myMd5=hashlib.md5();
        myMd5.update(src);
        value = myMd5.hexdigest();
    return value;

def querysongnum(c, kw):
    buff = StringIO( );
    curl = pycurl.Curl();
    curl.setopt(curl.URL, 'http://dx.yxkstar.com:8088/client/s2.php?c=' + str(c) + '&kw=' + kw.encode('gbk') + '&c1=0&pp=2');#query list
    curl.setopt(curl.HTTPHEADER, ["USERINFO: username=yx_mdyy;password=46d3d32f012cc48a88e746eb496a9d29;"]);
    #curl.setopt(curl.VERBOSE, True);
    curl.setopt(curl.WRITEDATA, buff);
    curl.setopt(curl.ACCEPT_ENCODING,'gzip');
    curl.perform( );
    curl.close( );
    body        = buff.getvalue();
    ctx         = str(body.decode('gbk'));
    #print(ctx);
    m           = re.search(r"(\[CNT\])([0-9]*)(\[0\])", ctx);
    num         = 0;
    if m :
        num  = m.group(2);
    else :
        logger.debug(ctx);
        logger.warning("get song num failed");
    return num;

def getdownload_addr(songid):
    buff = StringIO( );
    curl = pycurl.Curl();
    curl.setopt(curl.URL, 'http://dx.yxkstar.com:8088/getd3.php?mid=' + str(songid));#query download address
    curl.setopt(curl.HTTPHEADER, ["USERINFO: username=yx_mdyy;password=46d3d32f012cc48a88e746eb496a9d29;"]);
    #curl.setopt(curl.VERBOSE, True);
    curl.setopt(curl.WRITEDATA, buff);
    curl.setopt(curl.ACCEPT_ENCODING,'gzip');
    curl.perform( );
    curl.close( );
    body        = buff.getvalue();
    ctx         = str(body.decode('gbk'));
    m           = re.search('(\[IP\])(.*)(\[0\]\[OK\])', ctx);
    res         = ""
    if m :
        res = m.group(2);
    else :
        logger.warning("get download addr(ip) failed");
    return res;

def getfilesize(ip, songid) :
    url = "http://" + str(ip) + ":8088/getm3.php?mid=" + str(songid);
    buff = StringIO( );
    curl = pycurl.Curl();
    curl.setopt(curl.URL, url);
    curl.setopt(curl.HTTPHEADER, ["USERINFO: username=yx_mdyy;password=46d3d32f012cc48a88e746eb496a9d29;"]);
    #curl.setopt(curl.VERBOSE, True);
    curl.setopt(curl.WRITEDATA, buff);
    curl.setopt(curl.ACCEPT_ENCODING,'gzip');
    curl.perform( );
    curl.close( );
    ctx = buff.getvalue();
    m = re.search("(\[FSIZE\])([0-9]*)(\[0\])", ctx);
    filesize = 0;
    if m:
        filesize = int(m.group(2));
    else:
        logger.error("[captor] get filesize(" + url + " failed" + ctx);
        print(ctx);
    return filesize;
def querysong(c, kw) :
    totalnum      = querysongnum(c, kw);
    pagesize      = 30;
    pagenum  = (int)((int(totalnum) + pagesize)/pagesize);
    songlist = [];
    for  page in range(1, pagenum + 1):
        c1   = (page - 1) * pagesize;
        pp   = pagesize;
        msg  ="[captor] get range[" + str(c1) + "-" + str(c1 + pp) + "/" + str(totalnum) + "] songs in(" + str(c) + "," + str(kw) + ")";
        print(msg);
        logger.info(msg);
        buff = StringIO( );
        curl = pycurl.Curl();
        curl.setopt(curl.URL, 'http://dx.yxkstar.com:8088/client/s2.php?c=' + str(c) + '&kw=' + str(kw).encode('gbk') + '&cl=' + str(c1) + '&pp=' + str(pp));#query list
        curl.setopt(curl.HTTPHEADER, ["USERINFO: username=yx_mdyy;password=46d3d32f012cc48a88e746eb496a9d29;"]);
        #curl.setopt(curl.VERBOSE, True);
        curl.setopt(curl.WRITEDATA, buff);
        curl.setopt(curl.ACCEPT_ENCODING,'gzip');
        curl.perform( );
        curl.close( );
        body        = buff.getvalue();
        ctx         = str(body.decode('gbk'));
        m           = re.search("(\[CNT2\])([0-9]*)(\[0\])", ctx);
        itemsize = 0;
        if m:
            itemsize = (int)(m.group(2));
        else:
            print("error, no song");
            continue;
        for i  in range(1, int(itemsize) + 1) :
            rule = '(\\[m' + str(i) + '\\])(.*?)(\[0\])';
            m    = re.search(rule, ctx);
            if not m:
                logger.debug("http respone:\n" + ctx);
                logger.warning("parse http respone(lxapi) failed: get line failed" + rule);
                continue;
            ss   = m.group(2);
            ss   = ss.encode('utf-8');
            songinfo    = ss.split(',,');
            songid      = songinfo[0];
            name        = songinfo[1].encode('utf-8');
            m           = re.search('(`)(.*)(`)', name);
            if not m :
                logger.debug(name);
                logger.warning("pase http resone(lxapi) failed: get song name failed" + name);
            name        = m.group(2);
            m           = re.search(r'(\(高清\))(.*)', name);
            ishd        = songinfo[14].replace('`','');
            if m :
                name = m.group(2);
            name        = name.encode('utf-8');
            singer      = songinfo[2].replace('`','');
            lang        = songinfo[3].replace('`','');
            videotype   = songinfo[8].replace('`','');
            singtype    = songinfo[9].replace('`','');
            pubtime     = songinfo[11].replace('`','');
            album       = songinfo[13].replace('`','');
            src_ip      = getdownload_addr(songid);
            filesize    = getfilesize(src_ip, songid);
            if filesize <= 0:
                continue;
            src         = "http://" + str(src_ip) + ":8088/downl.php/12358/" + str(songid) + ".mmpg";
            entry = {'songid': songid, 'name': name, "singer":singer, "language":lang, "videotype": videotype, "pubtime":pubtime, "singtype":singtype, 'ishd': ishd, "src": src, "album":album,"filesize":filesize};
            songlist.append(entry);
    return songlist;

def get_all_albums( ):
    buff = StringIO( ); 
    curl = pycurl.Curl();
    curl.setopt(curl.URL, "http://dx.yxkstar.com:8088/client/get_albums.php");
    curl.setopt(curl.HTTPHEADER, ["USERINFO: username=yx_mdyy;password=46d3d32f012cc48a88e746eb496a9d29;"]);
    #curl.setopt(curl.VERBOSE, True);
    curl.setopt(curl.WRITEDATA, buff);
    curl.setopt(curl.ACCEPT_ENCODING,'gzip');
    curl.perform( );
    curl.close( );
    body        = buff.getvalue();
    ctx         = str(body.decode('gbk'));
    albums = [];    
    m = re.search("(\[ALBUMS\])(.*)(\[/ALBUMS\])", ctx);
    s = m.group(2);
    albums = s.split(",");
    return albums;

def save_capturesong(songlist):
    counter = 0;
    try:
        conn= MySQLdb.connect(host=dbhost, port = dbport, user = dbuser, passwd = dbpasswd, db = songdb);
        cur = conn.cursor()
        cur.execute("set names utf8");
        conn.commit();
        for i in range(0, len(songlist)):
            song        = songlist[i];
            songid      = song['songid'];
            name        = song['name'];
            src         = song['src'];
            singer      = song['singer'];
            singertype  = song['singtype'];
            language    = song['language'];
            pubtime     = song['pubtime'];
            ishd        = song['ishd'];
            videotype   = song['videotype'];
            album       = song['album'];
            filesize    = song['filesize'];
            albumid     = compute_md5(album);
            isexist     = isexist_download(conn, songid);
            if isexist > 0 :
                logger.warning("The song(" + str(songid) + ") was exist in download list, skip");
                continue;
            sql = ("insert into t_runtime_download(lxsongid, src, name, pubtime, singer,  singertype, language, videotype, ishd, albumname, albumid, filesize, copyright, phase, status) value(%s,%s,%s,%s,%s, %s,%s,%s,%s,%s,'" + str(albumid) + "', '" + str(filesize) + "', '乐心歌库', 1, 0)");
            logger.info("capture(" + songid +") name:<<" + name + ">> singer:" + singer + ",ishd:" + ishd + ",video:" + videotype + ",src:" + src);
            cur.execute(sql, (MySQLdb.Binary(songid), MySQLdb.Binary(src), MySQLdb.Binary(name),MySQLdb.Binary(pubtime), MySQLdb.Binary(singer), MySQLdb.Binary(singertype), MySQLdb.Binary(language), MySQLdb.Binary(videotype), MySQLdb.Binary(ishd), MySQLdb.Binary(album)));
            conn.commit();
            counter = counter + 1;
        cur.close();
        conn.close();
        logger.info("[captor] step4 update " + str(len(songlist)) + " songs is finish");
    except Exception,e:
        logger.error(e);
        print(e);
    return counter;

def captor_download_songinfo(month):
    try:
        songlist = [];
        logger.info("[captor] step1 query new songs in(" + month + ")");
        songlist = querysong(2, month); #new songs
        save = save_capturesong(songlist);
        logger.info("[captor] stop2 uery inc songs in(" + month + ")");
        songlist  = querysong(3, month); #inc songs
        save = save_capturesong(songlist);
        #songlist = songlist + tmplist;
        albums   = get_all_albums( );
        for i in range(0, len(albums)) :
            album   = albums[i];
            logger.info("query album[" + str(i) + "/" + str(len(albums)) + "] " + album);
            songlist =  querysong(4, album);
            save = save_capturesong(songlist);
            print("save:" + str(save));
    except Exception,e:
        logger.error(e);
        print("captor failed");
    return 0;

def main(argc, argv) :
    try :
        captor_download_songinfo(date);
    except Exception,e:
        print(e);
        logger.error(e);
    return 0;

main(1, "captor.py");
