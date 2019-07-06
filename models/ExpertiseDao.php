<?php
//审计特长表
namespace app\models;

use yii\db\ActiveRecord;

class ExpertiseDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "expertise";
    }

    public function addExpertise($name) {
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

    public function addPeopleExpertise($pid, $eid) {
        $sql=sprintf('INSERT INTO peopleexpertise (pid, eid, ctime, utime) values (:pid, :eid, :ctime, :utime)');
        $curTime = date('Y-m-d H:i:s');
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_STR);
        $stmt->bindParam(':eid', $eid, \PDO::PARAM_INT);
        $stmt->bindParam(':ctime', $curTime, \PDO::PARAM_STR);
        $stmt->bindParam(':utime', $curTime, \PDO::PARAM_STR);
        $ret = $stmt->execute();
        return $ret;
    }

    public function queryByPid($pid) {
        $sql=sprintf('SELECT * FROM %s as e, peopleexpertise as p WHERE e.id = p.eid and pid = :pid',
            self::tableName()
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_STR);
        $stmt->execute();
        $ret = $stmt->queryAll();
        return $ret;
    }

    public function queryAll() {
        $sql=sprintf('SELECT * FROM %s',
            self::tableName()
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->execute();
        $ret = $stmt->queryAll();
        return $ret;
    }
}