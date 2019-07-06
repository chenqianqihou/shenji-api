<?php
//角色表
namespace app\models;

use yii\db\ActiveRecord;

class RoleDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "role";
    }

    public function addRole($name) {
        $sql=sprintf('INSERT INTO %s (`name`, ctime, utime) values (:name, :ctime, :utime)', self::tableName());
        $curTime = date('Y-m-d H:i:s');
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':name', $name, \PDO::PARAM_STR);
        $stmt->bindParam(':ctime', $curTime, \PDO::PARAM_STR);
        $stmt->bindParam(':utime', $curTime, \PDO::PARAM_STR);
        $stmt->execute();
        $id = $stmt->db->getLastInsertID();
        return $id;
    }

    public function addPeopleRole($pid, $rid) {
        $sql=sprintf('INSERT INTO peoplerole (pid, rid, ctime, utime) values (:pid, :rid, :ctime, :utime)');
        $curTime = date('Y-m-d H:i:s');
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_STR);
        $stmt->bindParam(':rid', $rid, \PDO::PARAM_INT);
        $stmt->bindParam(':ctime', $curTime, \PDO::PARAM_STR);
        $stmt->bindParam(':utime', $curTime, \PDO::PARAM_STR);
        $ret = $stmt->execute();
        return $ret;
    }

    //查询人员有哪些角色
    public function queryByPid($pid) {
        $sql=sprintf('SELECT * FROM %s as r , peoplerole as p WHERE r.id = p.rid and pid = :pid',
            self::tableName()
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_STR);
        $stmt->execute();
        $ret = $stmt->queryAll();
        return $ret;
    }
}