<?php

namespace Home\Model;

use Think\Model\RelationModel;

/**
 * CommonModel
 * 数据库、数据表信息操作
 */
class CommonModel extends RelationModel {
    /**
     * 更新session的键值
     */
    const UPDATE_SESSION_KEY = 'ea_record_update';

    /**
     * 为模型创建数据表
     * 自动生成created_at、updated_at字段
     * @param  string  $tableName 数据表名称
     * @param  boolean $hasPk     是否含有主键
     * @param  string  $engine    引擎类型
     * @return boolean            是否创建成功
     */
    public function createTable($tableName,
                                $hasPk = true,
                                $engine = 'InnoDB',
                                $comment = '') {
        if (empty($tableName)) {
            return false;
        }

        $pkSql = '';
        if ($hasPk) {
            // id主键的sql
            $pkSql = "`id` int PRIMARY KEY NOT NULL "
                     . "AUTO_INCREMENT COMMENT '表主键',";
        }

        $sql = "CREATE TABLE `{$tableName}` ("
                . $pkSql
                . "`created_at` int NOT NULL COMMENT '创建时间',"
                . "`updated_at` int NOT NULL COMMENT '更新时间'"
                . ") ENGINE={$engine} CHARSET=utf8 COMMENT='{$comment}'";
        // 创建数据表
        if (false === M()->query($sql)) {
            return false;
        }

        return true;
    }

    /**
     * 删除数据表
     * @param  string  $tableName 表名
     * @return boolean
     */
    public function dropTable($tableName) {
        $sql = "DROP TABLE IF EXISTS `{$tableName}`";
        if (false === $this->query($sql)) {
            return false;
        }

        return true;
    }

    /**
     * 得到数据表表信息
     * @params $tableName 数据表名称
     * @return array
     */
    public function getTablesInfo($tableName) {
        if (!isset($tableName)) {
            return $this->query('SHOW TABLE STATUS');
        }

        $tableInfo = $this->query("SHOW TABLE STATUS LIKE '{$tableName}'");
        return $tableInfo[0];
    }

    /**
     * 得到数据表的行数
     * @param  string $tableName 数据表名称
     * @return int               行数
     */
    public function getTableRows($tableName) {
        if (!isset($tableName)) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM {$tableName}";
        $result = $this->query($sql);
        return $result[0]['COUNT(*)'];
    }

    /**
     * 得到重建数据表的sql
     * @param  string $tableName
     * @return string
     */
    public function getRebuildTableSql($tableName) {
        $sql = $this->getDropTableSql($tableName) . "\r\n";
        $sql .= $this->getCreateTableSql($tableName) . "\r\n";

        return $sql;
    }

    /**
     * 得到建表信息
     * @param  string $tableName
     * @return string
     */
    public function getCreateTableSql($tableName) {
        if (!isset($tableName) || empty($tableName)) {
        	return '';
        }

        // 设置字段名加上`
        $this->query('SET SQL_QUOTE_SHOW_CREATE = 1');
        $createTableSql = $this->query("SHOW CREATE TABLE `{$tableName}`");
        return $createTableSql[0]['Create Table'] . ";";
    }

    /**
     * 数据表是否有记录
     * @param  string  $tableName
     * @return boolean
     */
    public function hasRecord($tableName) {
        $result = $this->query("SELECT COUNT(*) FROM {$tableName}");

        if ($result[0]['COUNT(*)']) {
            return true;
        }

        return false;
    }

    /**
     * 修改表名
     * @param  string  $tableName    需要修改的表名
     * @param  string  $newTableName 新表名
     * @return boolean
     */
    public function updateTableName($tableName, $newTableName) {
        $sql = "ALTER TABLE `{$tableName}` RENAME TO `{$newTableName}`";
        return $this->query($sql);
    }

    /**
     * 修改表注释
     * @param  string  $tableName 需要修改的表名
     * @param  string  $comment   注释
     * @return boolean
     */
    public function updateTableComment($tableName, $comment) {
        $sql = "ALTER TABLE `{$tableName}` COMMENT '{$comment}'";
        return $this->query($sql);
    }

    /**
     * 优化数据表
     * @param  string $tableName 数据表名称
     * @return boolean           是否优化成功
     */
    public function optimizeTables($tableName) {
        if (!isset($tableName)) {
            return false;
        }

        $this->query("OPTIMIZE TABLE {$tableName}");
        return true;
    }

    /**
     * 修复数据表
     * @param  string $tableName 数据表名称
     * @return boolean           是否修复成功
     */
    public function repairTables($tableName) {
        if (!isset($tableName)) {
            return false;
        }

        $this->query("REPAIR TABLE {$tableName}");
        return true;
    }

    /**
     * 得到删除数据库的sql
     * @param  string $tableName
     * @return string
     */
    private function getDropTableSql($tableName) {
        return "DROP TABLE IF EXISTS `{$tableName}`;";
    }

    /**
     * 验证条件
     * @param  array   $conditions 验证条件
     * @param  array   $marray     模型数组
     * @param  int     $id         需要更新字段的id
     * @return boolean             是否可用
     */
    protected function validateConditions(array $conditions, $marray, $id) {
        $this->preUpdate($marray, $id);
        $result =  $this->validate($conditions)->create($marray);
        $this->afterUpdate($marray, $id);

        return $result;
    }

    /**
     * 验证字段值是否唯一
     * @param  string $fieldName 需要检查的字段名
     * @param  string $value     字段值
     * @return boolean           是否唯一
     */
    public function isUnique($fieldName, $value) {
        $where = array($fieldName => $value);
        $updateId = $this->getUpdateSession('update_id');
        if (isset($updateId)) {
            $where['id'] = array('neq', $updateId);
        }

        if (0 == $this->where($where)->count()) {
            return true;
        }

        return false;
    }

    /**
     * 设置更新外键或者id
     * @param String $key
     * @param  mixed $value
     * @return
     */
    protected function setUpdateSession($key, $value) {
        if (isset($key) && !is_null($key) && !is_null($value)) {
            $_SESSION[self::UPDATE_SESSION_KEY][$key] = $value;
        }
    }

    /**
     * 得到更新外键或者id的值
     * @param  String $key
     * @return
     */
    protected function getUpdateSession($key) {
        return $_SESSION[self::UPDATE_SESSION_KEY][$key];
    }

    /**
     * 销毁更新外键或者id
     * @param String $key
     * @return
     */
    protected function unsetUpdateSession($key) {
        unset($_SESSION[self::UPDATE_SESSION_KEY][$key]);
    }

    /**
     * 更新前的操作
     * 
     * @return
     */
    protected function preUpdate($marray, $id) {
        // to do something...
    }

    /**
     * 更新完成后的操作
     * @return
     */
    protected function afterUpdate($marray, $id) {
        // to do something...
    }
}
