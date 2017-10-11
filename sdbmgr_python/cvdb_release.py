#!/usr/bin/python
#coding=utf-8
import os
import sys
import time
import ConfigParser
import uuid
import httplib
import demjson
import logging
import string
import zipfile
import re
import MySQLdb
import sqlite3
import hashlib
from ctypes import *

import sys
reload(sys)
sys.setdefaultencoding('utf-8')

#lilingyun 2015/11/10
src="/tmp/swsong.db"
dst="/tmp/swsong_v3.db";

def sqlstrip(string):
    if string is None:
        return "";
    else :
        return string;
    
def map_country2to3(v2):
    map_table = {"C":1,   #china
                 "H":2,   #hongkong
                 "T":2,   #taiwan
                 "F":3,   #欧美
                 "J":4,   #janpan
                 "K":4,   #韩国
                 "S":5,   #马来西亚
                 "L":5,   #泰国
                 "P":5,   #菲律宾
                 "Y":5,  #印尼
                 "X":5,  #新加波
                 "V":5,  #越南
                 "Q":6}; #others
    if (map_table.has_key(v2)) :
        return map_table[v2];
    else :
        return 0;

def map_language(v2) :
    map_table = {"M":1,    #国语Mandarin                              
                 "C":2,    #粤语Cantonese                             
                 "T":3,    #闽南语Taiwanese                          
                 "E":5,    #英语English                               
                 "J":6,    #日语Japanese                              
                 "K":9,    #韩语Korean                                
                 "V":16,   #越南语Vietnamese                         
                 "P":23,   #菲律宾语Philippinese
                 "Y":23,   #印尼语Indonesian
                 "H":18,   #泰国语Thailand
                 "S":17,   #马来西亚语Malaysian
                 "I":13,   #意大利语Italy
                 "N":4,    #客家语HaGa
                 "X":4,    #潮语Chao
                 "A":23,   #印度语India
                 "F":10,   #法语France
                 "Q":23};  #其它others
    if (map_table.has_key(v2)) :
        return map_table[v2];
    else :
        return 0;

def map_sex(v2):
    map_table = {"M" :1, #男歌手
                 "F" :2, #女歌手
                 "B" :3} #乐队组合
    if (map_table.has_key(v2)):
        return map_table[v2];
    else :
        return 0;

#def getduration(conn, songid):
#    sql     = "select duration from mk_song where number='" + str(songid) + "'";
#    cur     = conn.cursor( );
#    cur.execute(sql);
#    conn.commit();
#    duration = cur.fetchone()[0];
#    cur.close();
#    return duration;
    
def conver(myconn, src, dst) :
    try :
        conn_src     =  sqlite3.connect(src);
        cur_src      =  conn_src.cursor( );
        conn_dst     =  sqlite3.connect(dst);
        cur_dst      =  conn_dst.cursor( );
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
        cur_dst.execute("drop table if exists song");
        sql = ("CREATE TABLE if not exists song(\
                            SeqNo               INTEGER PRIMARY KEY AUTOINCREMENT,\
                            SongNo              TEXT    NOT NULL,\
                            Name                TEXT,\
                            PYStr1              TEXT,\
                            Pinyin              TEXT,\
                            Length              TEXT,\
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
        sql = "select Type, Singer, Gender, Country, SongCount, HotSeqNo, SingerNo, PYStr, Pinyin, SearchLevel, IsHot, IsNew from singer";
        state= cur_src.execute(sql);
        record = cur_src.fetchone( );
        while (record) :
            typ     = record[0];
            name    = record[1];
            gender  = record[2];
            country = record[3];
            songcount=record[4];
            hotseqno= record[5];
            singerno= record[6];
            pystr   = record[7];
            pinyin  = record[8];
            searchlevel=record[9];
            ishot   = record[10];
            isnow   = record[11];

            sex     = map_sex(gender);
            sql = ("insert into singer(singerNo, singerName,PYStr,Pinyin,SongCount,IsHot,IsNew,HotSeqNo,HotValue,HotChangeValue,SearchLevel,singerType,Gender,Country) values("
                + str(singerno) + ",\"" + name + "\""
                +",'" + str(pystr) + "'"
                +",'" + str(pinyin) +"'"
                +","  + str(songcount)
                +","  + str(0) # //isHot
                +","  + str(0) # //isNew
                +","  + str(0) #isHotSeqno
                +","  + str(0) #Hot value
                +","  + str(0) #Hot changeValue
                +","  + str(searchlevel) #searchlevel
                +","  + str(typ)
                +","  + str(sex)
                +","  + str(map_country2to3(country))
                + ")");
            #print(sql);
            cur_dst.execute(sql);
            record = cur_src.fetchone( ); #end while
        conn_dst.commit();
        recordset = cur_src.execute("select Type, SongNo, Name, Length, Language, Singer1, Singer2, Singer1ID, Singer2ID, Librettist, Songwriter, album, Product, SongWord, PYStr1, Pinyin, VideoFormat, HitCount, SearchLevel, path1, InputTime, TrackCount, ChannelA, ChannelD, ChannelAVolume, ChannelDVolume, Resolution, Valid, IsHot, IsNew from song");
        record = cur_src.fetchone();
        i = 0;
        while (record) :
            typ     = record[0];
            songno  = record[1];
            name    = record[2];
            duration= record[3];
            lang    = record[4];
            singer1 = record[5];
            singer2 = record[6];
            singer1_id=record[7];
            singer2_id=record[8];
            librettist=record[9];
            songwriter= record[10];
            album     = record[11];
            product   = record[12];
            songword  = record[13];
            pystr     = record[14];
            pinyin    = record[15];
            vformat   = record[16];
            hitcount   = record[17];
            searchlevel= record[18];
            path1      = record[19];
            inputtime  = record[20];
            trackcount = record[21];
            channela   = record[22];
            channeld   = record[23];
            channelavol= record[24];
            channeldvol= record[25];
            resolution = record[26];
            valid      = record[27];
            ishot      = record[28];
            isnew      = record[29];
            sql = ("insert into song(SongNo, Name, PYStr1, Pinyin, Length, TrackCount, ChannelA, ChannelD, ChannelAVolume, ChannelDVolume, Singer1, Singer2, Singer1ID, Singer2ID,Librettist, SongWriter, album, Product, SongWord, Path1, HotValue, HotChangeValue, HitCount, SearchLevel, Valid, IsHot, IsNew, VideoType, VideoQaulity, Language, audioQaulity, accompanyQaulity, useType, rightType, qenreType, feelingType, themeType, holidayType, categoryType, varietyType, duration, InputTime) values(?,?,?,?,?,?,?,?,?,?,  ?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?,?,?, ?,?)");
            v3_lang = map_language(lang);
            cur_dst.execute(sql, (songno, name, pystr, pinyin, duration, trackcount, channela, channeld, channelavol, channeldvol,  singer1, singer2, singer1_id, singer2_id, librettist, songwriter, album, product, songword, path1, 0, 0, 0, searchlevel, valid, ishot, isnew, 0, 0, v3_lang, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, duration, inputtime));
            i = i + 1;
            print(i);
            record = cur_src.fetchone( );
        conn_dst.commit( );
        cur_dst.close( );  #close db
        cur_src.close( );
        conn_src.close( );
        conn_dst.close( );
    except Exception,e:
        print(e);
    return 0;

def main(argc, argv) :
    conn= MySQLdb.connect(host="120.132.0.183", port = 3308, user = "song_op_w", passwd = "cBMlz1y]8yug", db = "minik_song_db");
    conver(conn, src, dst);
    return 0;

main(1, "cvdb.py");
