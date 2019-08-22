<?php

namespace app\models;

use yii\db\ActiveRecord;

class AuditGroupDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "auditgroup";
    }

    //审计组状态
    public static $status = [
        0 => "无",
        1 => "应进点",
        2 => "该进点而未进点",
        3 => "已进点",
        4 => "该结束未结束",
        5 => "实施结束",
        6 => "审理中",
        7 => "审理结束",
        8 => "待报告",
        9 => "报告中",
        10 => "报告结束",
    ];

    public static $statusToName = [
        "无" => 0,
        "应进点" => 1,
        "该进点而未进点" => 2,
        "已进点" => 3,
        "该结束未结束" => 4,
        "实施结束" => 5,
        "审理中" => 6,
        "审理结束" => 7,
        "待报告" => 8,
        "报告中" => 9,
        "报告结束" => 10,
    ];

    //人员在审计组中角色
    public static $roleType = [
        1 => "审计组长",
        2 => "主审",
        3 => "审计组员",
    ];

    //人员在审计组中角色
    public static $roleTypeName = [
        "审计组长" => 1,
        "主审" => 2,
        "审计组员" => 3,
    ];
    
    //是否锁定
    public static $isLock = [
        1 => "锁定",
        2 => "未锁定",
    ];

    //是否锁定
    public static $isLockName = [
        "锁定" => 1,
        "未锁定" => 2,
    ];

    public function addAuditGroup($pid) {
        $sql=sprintf('INSERT INTO %s (status, pid, ctime, utime)
                              values (:status, :pid, :ctime, :utime)', self::tableName());
        $curTime = date('Y-m-d H:i:s');
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':status', self::$statusToName['无'], \PDO::PARAM_INT);
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_INT);
        $stmt->bindParam(':ctime', $curTime, \PDO::PARAM_STR);
        $stmt->bindParam(':utime', $curTime, \PDO::PARAM_STR);
        $stmt->execute();
        $id = $stmt->db->getLastInsertID();
        return $id;
    }

    public function addPeopleProject($pid, $projid, $groupid, $roletype) {
        $sql=sprintf('INSERT INTO peopleproject (pid, projid, groupid, roletype, islock, ctime, utime)
                              values (:pid, :projid, :groupid, :roletype, :islock, :ctime, :utime)');
        $curTime = date('Y-m-d H:i:s');
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_INT);
        $stmt->bindParam(':projid', $projid, \PDO::PARAM_INT);
        $stmt->bindParam(':groupid', $groupid, \PDO::PARAM_INT);
        $stmt->bindParam(':roletype', $roletype, \PDO::PARAM_INT);
        $stmt->bindParam(':islock', self::$isLockName['锁定'], \PDO::PARAM_INT);
        $stmt->bindParam(':ctime', $curTime, \PDO::PARAM_STR);
        $stmt->bindParam(':utime', $curTime, \PDO::PARAM_STR);
        $ret = $stmt->execute();
        return $ret;
    }

    public function updateGroupStatus($groupid, $status) {
        $sql=sprintf('UPDATE %s SET status = :status where id = :id', self::tableName()
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':status', $status, \PDO::PARAM_INT);
        $stmt->bindParam(':id', $groupid, \PDO::PARAM_INT);
        $ret = $stmt->execute();
        return $ret;
    }

    public function updateUnLock($groupid, $pid) {
        $sql=sprintf('UPDATE peopleproject SET islock = %d where pid = :pid and groupid = :groupid', self::$isLockName['未锁定']
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_STR);
        $stmt->bindParam(':groupid', $groupid, \PDO::PARAM_INT);
        $ret = $stmt->execute();
        return $ret;
    }

    public function updateRole($roletype, $groupid, $pid) {
        $sql=sprintf('UPDATE peopleproject SET roletype = :roletype where pid = :pid and groupid = :groupid', self::tableName()
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':roletype', $roletype, \PDO::PARAM_INT);
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_INT);
        $stmt->bindParam(':groupid', $groupid, \PDO::PARAM_INT);
        $ret = $stmt->execute();
        return $ret;
    }

    //查询某个项目的审计组信息
    public function queryByID($projid) {
        $sql=sprintf('SELECT a.status, p.groupid, p.roletype, p.islock, people.* FROM %s as a, peopleproject as p, people 
            WHERE a.id = p.groupid and people.id = p.pid and projid = :projid',
            self::tableName()
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':projid', $projid, \PDO::PARAM_INT);
        $stmt->execute();
        $ret = $stmt->queryAll();
        return $ret;
    }

    //查询某个用户在哪些审计组
    public function queryGroupList($pid) {
        $sql=sprintf('SELECT a.status, p.groupid, p.roletype, p.islock, people.* FROM %s as a, peopleproject as p, people 
            WHERE a.id = p.groupid and people.id = p.pid and a.pid = :pid',
            self::tableName()
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_STR);
        $stmt->execute();
        $ret = $stmt->queryAll();
        return $ret;
    }

    //查询用户正在参与的审计组数量
    public function queryUnEndGroupCount() {
        $sql=sprintf('SELECT people.pid, count(1) as c FROM %s as a, peopleproject as p, people 
            WHERE a.id = p.groupid and people.id = p.pid and status != %d group by people.pid',
            self::tableName(), ProjectDao::$statusToName['项目结束']
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->execute();
        $ret = $stmt->queryAll();
        return $ret;
    }

    //查询用户正在参与的审计组（项目未结束，人员锁定中）
    public function queryJoinGroup($pid) {
        $sql=sprintf('SELECT a.status, p.* as c FROM %s as a, peopleproject as p 
            WHERE a.id = p.groupid and pid = :pid and status != %d and islock = %d',
            self::tableName(), ProjectDao::$statusToName['项目结束'], AuditGroupDao::$isLockName['锁定']
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_STR);
        $stmt->execute();
        $ret = $stmt->queryAll();
        return $ret;
    }

    //查询所有在点状态的人员列表
    public function queryAllJobPeople() {
        $sql=sprintf('SELECT a.status, p.* as c FROM %s as a, peopleproject as p 
            WHERE a.id = p.groupid and status != %d and islock = %d',
            self::tableName(), ProjectDao::$statusToName['项目结束'], AuditGroupDao::$isLockName['锁定']
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->execute();
        $ret = $stmt->queryAll();
        return $ret;
    }

    public function deletePeopleProject($groupid, $pid) {
        $sql=sprintf('DELETE FROM %s WHERE groupid = :groupid and pid = :pid', self::tableName());
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':groupid', $groupid, \PDO::PARAM_INT);
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_INT);
        $ret = $stmt->execute();
        return $ret;
    }
}
