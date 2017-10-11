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
curl = pycurl.Curl();
curl.mytaskid = 20;
num = 3;
pagesize = 20;
pagenum = (int)((num + pagesize)/pagesize);
print(pagenum);
