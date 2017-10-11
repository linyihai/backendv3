#!/usr/bin/python
#coding=utf-8
import os
import sys
import time
import uuid
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
import base64
from ctypes import *
from io import StringIO
from sys import argv
import threadpool
import xml.dom.minidom
from multiprocessing import Process,Pool
import sys
reload(sys)
sys.setdefaultencoding('utf-8')
date = "";
text_factory = str;
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
logfile     = path + os.sep + ".." + os.sep + "logs" + os.sep + "publisher.log";
logger      = logging.getLogger("sdbmgr");
logger.setLevel(logging.DEBUG);
fh          = logging.FileHandler(logfile);
fh.setLevel(logging.DEBUG);
formatter   = logging.Formatter('%(asctime)s <%(name)s> %(levelname)s: %(message)s');
fh.setFormatter(formatter);
logger.addHandler(fh);

#global config
storeage_srv = "http://videosrc.song.singworld.cn/";

def upload2clod(localfile, serverpath, dstfilename) :
    #print(localfile);
    #print(serverpath);
    #print(dstfilename);
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

def getmids_from_mset(conn, mset_id):
    cur = conn.cursor();
    sql = "select machset from t_config_machset where id = " + str(mset_id);
    cur.execute(sql);
    conn.commit( );
    row = cur.fetchone();
    mids = [];
    if (row) :
        machset = row[0];
        mids = demjson.decode(machset);
    else :
        print("error, machineset(" + str(mset_id) + ") is not exist\n");
    cur.close();
    return mids;

def map_sex(sex) :
    table = {"男歌手":1, "女歌手":2, "乐队组合":3, "未知":0};
    if (table.has_key(sex)):
        return table[sex];
    else :
        return 0;

def map_country(country) :
    table = {"中国":1, "其他":6, "印尼":5, "台湾":2, "新加坡":5, "日本":4, "未知":5, "欧美":3, "泰国":5, "澳大利亚":3, "菲律宾":5, "越南":5, "韩国":4, "香港":2, "马来西亚":5};
    if table.has_key(country):
        return table[country];
    else :
        return 5;

def map_language(lang):
    table = {"国语":1,"粤语":2,"闽南语":3,"英语":4,"日语":5,"韩语":5,"越南语":6,"菲律宾语":6,"印尼语":6,"泰国语":6,"马来西亚语":6,"意大利语":7,"客家语":3,"澳大利亚":4,"印度语":7,"法语":7,"其它":0,"方言":3,"西班牙语":7}
    if table.has_key(lang) :
        return table[lang];
    else :
        return 0;

def map_videotype(style) :
    table = {"MTV":1,"演唱会":2,"配置画面":7,"消音版":7,"现场版":5,"动画版":6};
    if table.has_key(style) :
        return table[style];
    else :
        return 0;

def map_videoquality(quality):
    table = {"SD":1, "HD":2,"FHD":3};
    if table.has_key(quality) :
        return table[quality];
    else :
        return 0;

def snap_songdb(conn, songlistid) :
    swsong = "/opt/sdbmgr/temp/swsong_" + str(songlistid) + ".db";
    conn_dst     =  sqlite3.connect(swsong);
    cur_dst      =  conn_dst.cursor( );
    cur_dst.execute("drop table if exists version");
    cur_dst.execute("create table version(ID INTEGER, ver text)");
    cur_dst.execute("insert into version values(1, '1.0.0')");
    conn_dst.commit();
    cur_dst.execute("drop table if exists singer");
    sql = ("CREATE TABLE if not exists singer(\
            SeqNo               INTEGER PRIMARY KEY AUTOINCREMENT,\
            SingerNo            INTEGER,\
            SingerName          TEXT    NOT NULL,\
            PYStr               TEXT,\
            Pinyin              TEXT,\
            SongCount           INTEGER,\
            IsHot               INTEGER,\
            IsNew               INTEGER,\
            HotSeqNo            INTEGER,\
            HotValue            INTEGER,\
            HotChangeValue      INTEGER,\
            HitCount            INTEGER,\
            SearchLevel         INTEGER,\
            singerType          INTEGER,\
            Gender              INTEGER,\
            Country             INTEGER);");
    cur_dst.execute(sql);
    conn_dst.commit( );
    cur_dst.execute("CREATE INDEX [Singer_Country_Index] ON [Singer]([Country] COLLATE [BINARY] ASC);");
    cur_dst.execute("CREATE INDEX [Singer_Pinyin_Index] ON [Singer]([Pinyin] COLLATE [NOCASE] ASC);");
    cur_dst.execute("CREATE UNIQUE INDEX [Singer_index] ON [singer]([SingerNo]);");
    cur_dst.execute("CREATE INDEX [Singer_Gender_Index] ON [Singer]([Gender] COLLATE [BINARY] ASC);");
    cur_dst.execute("CREATE INDEX [Singer_hotValue_Index] ON [Singer]([HotValue] COLLATE [BINARY] DESC);");
    cur_dst.execute("CREATE INDEX [Singer_PYStr_Index] ON [Singer]([PYStr] COLLATE [NOCASE] ASC);");
    cur_dst.execute("CREATE INDEX [Singer_SearchLevel_Index] ON [Singer]([SearchLevel] COLLATE [BINARY] ASC);");
    conn_dst.commit();
    cur_dst.execute("drop table if exists song");
    sql = ("CREATE TABLE if not exists song(\
                            SeqNo               INTEGER PRIMARY KEY AUTOINCREMENT,\
                            SongNo              TEXT    NOT NULL,\
                            Name                TEXT,\
                            PYStr1              TEXT,\
                            Pinyin              TEXT,\
                            Length              INTEGER,\
                            TrackCount          INTEGER,\
                            ChannelA            INTEGER,\
                            ChannelD            INTEGER,\
                            ChannelAVolume      INTEGER,\
                            ChannelDVolume      INTEGER,\
                            Singer1             TEXT,\
                            Singer2             TEXT,\
                            Singer1ID           INTEGER,\
                            Singer2ID           INTEGER,\
                            Librettist          TEXT,\
                            Songwriter          TEXT,\
                            album               TEXT,\
                            Product             TEXT,\
                            SongWord            TEXT,\
                            Path1               TEXT,\
                            HotValue            INTEGER,\
                            HotChangeValue      INTEGER,\
                            HitCount            INTEGER,\
                            SearchLevel         INTEGER,\
                            Valid               INTEGER,\
                            IsHot               INTEGER,\
                            IsNew               INTEGER,\
                            VideoType           INTEGER,\
                            VideoQaulity        INTEGER,\
                            Language            INTEGER,\
                            audioQaulity        INTEGER,\
                            accompanyQaulity    INTEGER,\
                            useType             INTEGER,\
                            rightType           INTEGER,\
                            qenreType           INTEGER,\
                            feelingType         INTEGER,\
                            themeType           INTEGER,\
                            holidayType         INTEGER,\
                            categoryType        INTEGER,\
                            varietyType         INTEGER,\
                            duration            INTEGER,\
                            InputTime           TEXT);");
    cur_dst.execute(sql);
    conn_dst.commit();
    cur_dst.execute("CREATE INDEX [Song_PYStr_Index] ON [song]([PYStr1] COLLATE [NOCASE] ASC);");
    cur_dst.execute("CREATE INDEX [song_searchlevel] ON [song]([SearchLevel] COLLATE [BINARY] ASC);");
    cur_dst.execute("CREATE INDEX [Song_HotValue_index] ON [song]([HotValue] COLLATE [BINARY] DESC);");
    cur_dst.execute("CREATE INDEX [Song_Name] ON [song]([Name] COLLATE [NOCASE] ASC);");
    cur_dst.execute("CREATE INDEX [Song_Pinyin_Index] ON [song]([Pinyin] COLLATE [NOCASE] ASC);");
    cur_dst.execute("CREATE INDEX [Song_Language_index] ON [song]([Language] COLLATE [BINARY] ASC)");
    cur_dst.execute("CREATE INDEX [Song_Singer1ID_index] ON [song]([Singer1ID] COLLATE [BINARY] ASC)");
    cur_dst.execute("CREATE INDEX [Song_album_index] ON [song]([album] COLLATE [NOCASE] ASC);");
    cur_dst.execute("CREATE INDEX [Song_Singer2ID_index] ON [song]([Singer2ID] COLLATE [BINARY] ASC);");
    cur_dst.execute("CREATE INDEX [Song_Singer2_index] ON [song]([Singer2] COLLATE [NOCASE] ASC);");
    cur_dst.execute("CREATE UNIQUE INDEX [Song_index] ON [song]([SongNo]);");
    cur_dst.execute("CREATE INDEX [Song_Singer1_index] ON [song]([Singer1] COLLATE [NOCASE] ASC);");
    conn_dst.commit();
    sql = "select singerid, name, pinyin_first, pinyin, song_count, sex, country from t_data_singer";
    cur = conn.cursor();
    cur.execute(sql);
    conn.commit();
    row = cur.fetchone();
    i = 0;
    while (row) :
        singerid        = row[0];
        name            = row[1].decode('utf-8');
        pinyin_first    = row[2].decode('utf-8');
        pinyin          = row[3].decode('utf-8');
        song_count      = row[4];
        sex             = map_sex(row[5]);
        country         = map_country(row[6]);
        i = i + 1;
        sql_ = "insert into singer(singerNo, singerName, PYStr, Pinyin, SongCount,  IsHot, IsNew, HotSeqNo, HotValue, HotChangeValue,  SearchLevel, singerType, Gender, Country) values(?, ?, ?, ?, ?,  0,0,0,0,0, 0,0, ?, ?)";
        print(singerid);
        print(sql_);
        print unicode(name);
        print(type(name))
        print(pinyin_first);
        print(pinyin);
    	print(type(pinyin))
        print(song_count);
        print(sex);
        print(country);
       
        cur_dst.execute(sql_, (singerid, buffer(name.encode('utf8')), pinyin_first, pinyin, song_count, sex, country));
        row = cur.fetchone();
    conn_dst.commit();

    sql = "select songid, name, pinyin_first, pinyin, language, album, singer1, singer2, singer1ID, singer2ID,  duration, channela, channeld, channelavolume, channeldvolume, trackcount, videotype, videoquality  from t_data_song";
    cur.execute(sql);
    conn.commit();
    i = 0;
    row = cur.fetchone();
    while (row) :
        songid = row[0];
        name   = row[1];
        pinyin_first = row[2];
        pinyin = row[3];
        lang   = map_language(row[4]);
        album  = row[5];
        singer1  = row[6];
        singer2  = row[7];
        singer1id= row[8];
        singer2id= row[9];
        duration = row[10];
        channela = row[11];
        channeld = row[12];
        vol_a    = row[13];
        vol_d    = row[14];
        print(vol_a);
        print(vol_d);
        trackcount= row[15];
        videotype   = map_videotype(row[16]);
        videoquality= map_videoquality(row[17]);
        i = i + 1;
        sql_     = "insert into song(SongNo, Name, PYStr1, Pinyin, Length,   Singer1, Singer1ID, album, language, Valid,   IsHot, IsNew, VideoType, VideoQaulity, ChannelA,    ChannelD, ChannelAVolume, ChannelDVolume, TrackCount) values(?, ?, ?, ?, ?,   ?, ?, ?, ?, 1,    0, 0, ?, ?, ?,   ?, ?, ?, ?)";
        print(i);
        print(singer2);
        if (not singer1) :
            singer1 = "";

        cur_dst.execute(sql_, (songid, buffer(name), buffer(pinyin_first), buffer(pinyin),int(duration),  buffer(singer1), singer1id, buffer(album),int(lang),  videotype, videoquality,  channela, channeld, vol_a, vol_d, trackcount));
        row = cur.fetchone();
    conn_dst.commit();

    cur_dst.close();
    conn_dst.close();
    store_path = str(storeage_srv) + "upload?path=/swsong/";
    dstfile    = "swsong_" + str(songlistid) + ".db";
    return upload2clod(swsong, store_path, dstfile);

def snap_songdb_v2(conn, songlistid):
    swsong = "/opt/sdbmgr/temp/swsong_" + str(songlistid) + "_v2.db";
    conn_dst     =  sqlite3.connect(swsong);
    cur_dst      =  conn_dst.cursor( );
    cur_dst.execute("drop table if exists singer");
    cur_dst.execute("drop table if exists song");
    cur_dst.execute("drop table if exists CVideoFormat");
    cur_dst.execute("CREATE TABLE CVideoFormat (SeqNO INTEGER, Id TEXT, CString TEXT, EString TEXT, Tag INTEGER)");
    cur_dst.execute("insert into CVideoFormat values(5, 'M', 'MTV', 'MTV', 0)");
    cur_dst.execute("insert into CVideoFormat values(6, 'C', '演唱会', 'Concert', 0)");
    cur_dst.execute("insert into CVideoFormat values(7, 'S', '视听版', '視聽版', 0)");
    cur_dst.execute("insert into CVideoFormat values(8, 'D', 'DVD版', 'D version', 0)");
    cur_dst.execute("insert into CVideoFormat values(9, 'X', '现场版', '现场版', 0)");
    cur_dst.execute("insert into CVideoFormat values(10, 'F', '动画版', '动画版', 0)");

    cur_dst.execute("CREATE TABLE singer ( \
    SeqNo       INTEGER PRIMARY KEY AUTOINCREMENT,\
    Type        INTEGER,\
    Singer      TEXT    NOT NULL,\
    Gender      TEXT,\
    Country     TEXT,\
    SongCount   INTEGER,\
    HotSeqNo    INTEGER,\
    SingerNo    INTEGER,\
    PYStr       TEXT,\
    Pinyin      TEXT,\
    SearchLevel INTEGER,\
    IsHot       INTEGER,\
    IsNew       INTEGER \
);");
    cur_dst.execute("CREATE TABLE song ( \
    SeqNo          INTEGER PRIMARY KEY,\
    Type           INTEGER,\
    SongNo         TEXT    NOT NULL,\
    Name           TEXT,\
    Length         TEXT,\
    Language       TEXT,\
    Singer1        TEXT,\
    Singer2        TEXT,\
    Singer1ID      INTEGER,\
    Singer2ID      INTEGER,\
    Librettist     TEXT,\
    Songwriter     TEXT,\
    album          TEXT,\
    Product        TEXT,\
    SongWord       TEXT,\
    PYStr1         TEXT,\
    Pinyin         TEXT,\
    VideoFormat    TEXT,\
    HitCount       INTEGER,\
    SearchLevel    INTEGER,\
    Path1          TEXT,\
    InputTime      TEXT,\
    TrackCount     INTEGER,\
    ChannelA       INTEGER,\
    ChannelD       INTEGER,\
    ChannelAVolume INTEGER,\
    ChannelDVolume INTEGER,\
    Resolution     INTEGER,\
    Valid          INTEGER,\
    IsHot          INTEGER,\
    IsNew          INTEGER \
);");
    conn_dst.commit();
    cur_dst.close();
    conn_dst.close();
    store_path = storeage_srv + "upload?path=/swsong/";
    dst_file   = "swsong_v2_1024.db";
    return upload2clod(swsong, store_path, dst_file);
def add_songlist2mid(conn,songlistid, mid) :
    cur = conn.cursor();
    sql = "select  dst_songlist from t_runtime_songlist_sync where mid=" + str(mid);
    cur.execute(sql);
    conn.commit();
    row = cur.fetchone();
    if (row) :
        songlist = demjson.decode(row[0]);
        songlist.append(songlistid);
        tmp = set(songlist);
        songlist = [i for i in tmp];
        songlist.sort();
        s        = demjson.encode(songlist);
        sql = "update t_runtime_songlist_sync set dst_songlist='%s' where mid=%d" %(s, mid)
        cur.execute(sql);
        conn.commit();
        print("mid[%d] add songlist(%d) to sync\n" %(mid, songlistid));
    else :
        print("mid[%d] add songlist(%d) to sync failed, mid no in sync table\n"%(mid, songlistid));
    return 0;

def update_sync(conn, songlistid, inc, excep) :
    incs = demjson.decode(inc);
    mids = [];
    for msetid in incs :
        mids_ = getmids_from_mset(conn, msetid);
        mids  = mids + mids_;
    tmp = set(mids);
    mids= [i for i in tmp];
    for mid in mids :
        add_songlist2mid(conn, songlistid, mid);
    return mids;

def publish_songlist(conn, row) :
    cur         = conn.cursor();
    pubid       = row[0];
    songlistid  = row[1];
    im_set      = row[2];
    em_set      = row[3];
    puber       = row[4];
    #create swsong.db
    (swurl, swmd5)   = snap_songdb(conn, songlistid);
    (swurl_v2, swmd5_v2) = snap_songdb_v2(conn, songlistid);
    sql         = "update t_runtime_songlist set swsong_url='%s', swsong_md5='%s', swsong_v2_url='%s', swsong_v2_md5='%s' where id=%d" %(swurl, swmd5, swurl_v2, swmd5_v2, songlistid);
    cur.execute(sql);
    conn.commit();
    #update sync 
    update_sync(conn, songlistid, im_set, em_set);
    print("[%s] publish songlist(%d), pubid=%d\n" %(puber, songlistid, pubid));

    sql = "update t_publish_songlist set status = 1";
    cur.execute(sql);
    conn.commit();
    return 0;

conn = MySQLdb.connect(host=dbhost, port=dbport, user=dbuser, passwd=dbpasswd, db=songdb)
cur  = conn.cursor();
cur.execute("set names utf8");
conn.commit();
cur.close();
while (1) :
    cur = conn.cursor()
    cur.execute("select pubid, songlistid, include_mset, except_mset, publisher from t_publish_songlist where status = 0")
    rows = cur.fetchall()
    conn.commit()
    for row in rows:
        try :
            publish_songlist(conn, row);
        except Exception as e:
            print(e);
    cur.close();
    time.sleep(10);

#test function
#getmids_from_mset(conn, 2);

#src, md5 = snap_songdb(conn, 1024);

#print(src);
#print(md5);

#update_sync(conn, 1024, '[2]', '[]');
add_songlist2mid(conn, 1024, 1);
conn.close();

