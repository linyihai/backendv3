#!/usr/bin/python
# -*- coding: utf-8 -*-

"""
@author: linyihai

@prerequisite:
    based on Python 2.7

@description
    数据库操作公共模块

@usage:
    1) get_data: 指定SQL语句获取数据库数据
    2) exec_sql：执行指定的SQL语句,执行正确返回True

"""

import MySQLdb.cursors
import MySQLdb as Database
import db_config
from DBUtils.PooledDB import PooledDB
from warnings import filterwarnings
from logger import Logger
filterwarnings('ignore', category=Database.Warning)

logger = Logger('dbutil')

DBConnection = None
_conn_lock = None

sql_settings = {'mysql': {'host': db_config.dbhost, 'port': db_config.dbport, 'user': db_config.dbuser,
                          'passwd': db_config.dbpasswd, 'db': db_config.songdb}}


class DBUtil():
    __pool = {}

    def __init__(self, conf_name='mysql'):
        self.conf_name = conf_name
        self._conn = DBUtil.connect_db(self.conf_name)
        self._cursor = self._conn.cursor()

        # Enforce UTF-8 for the connection.
        self._cursor.execute('SET NAMES utf8mb4')
        self._cursor.execute("SET CHARACTER SET utf8mb4")
        self._cursor.execute("SET character_set_connection=utf8mb4")

    @classmethod
    def connect_db(self, conf_name):
        if conf_name not in DBUtil.__pool:
            logger.debug('create pool for %s' % conf_name)
            DBUtil.__pool[conf_name] = PooledDB(creator=MySQLdb,
                                                mincached=1,
                                                maxcached=300,
                                                use_unicode=True,
                                                charset='utf8',
                                                maxshared=150,
                                                blocking=True,
                                                cursorclass=MySQLdb.cursors.DictCursor,
                                                **sql_settings[conf_name])

        return DBUtil.__pool[conf_name].connection()

    def get_data(self, sqlString):

        try:
            self._cursor.execute(sqlString)
            returnData = self._cursor.fetchall()
            self.close_db()
            return returnData
        except MySQLdb.Error, e:
            self.close_db()
            logger.error("GetData Error Info: [%d]-[%s]" % (e.args[0], e.args[1]))
            logger.info('GetData Error SQL: %s' % sqlString)
            return ()

    def exec_sql(self, sqlString):
        try:
            self._cursor.execute(sqlString)
            self._conn.commit()
            self.close_db()
            return True
        except MySQLdb.Error, e:
            self._conn.rollback()
            self.close_db()
            logger.error("ExecSQL Error Info: [%d]-[%s]" % (e.args[0], e.args[1]))
            logger.info('ExecSQL Error SQL: %s' % sqlString)
            return False

    # 以参数化的方式，执行SQL语句，可以插入特殊字符
    def exec_sql_t(self, sqlString, parm1, parm2):
        try:
            self._cursor.execute(sqlString, (parm1, parm2))
            self._conn.commit()
            self.close_db()
            return True
        except MySQLdb.Error, e:
            self._conn.rollback()
            self.close_db()
            logger.error("ExecSQL Error Info: [%d]-[%s]" % (e.args[0], e.args[1]))
            logger.info('ExecSQL Error SQL: %s' % sqlString)
            return False

    def close_db(self):
        try:
            if self._cursor:
                self._cursor.close()
            self._conn.close()
        except MySQLdb.Error, e:
            logger.error("Close MySQL Connection Error %d: %s" % (e.args[0], e.args[1]))
