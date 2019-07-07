<?php
//专业技术职称表
namespace app\models;

use yii\db\ActiveRecord;

class TechtitleDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "techtitle";
    }

    public function addTechtitle($name) {
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

    public function addPeopletitle($pid, $tid) {
        $sql=sprintf('INSERT INTO peopletitle (pid, tid, ctime, utime) values (:pid, :tid, :ctime, :utime)');
        $curTime = date('Y-m-d H:i:s');
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_STR);
        $stmt->bindParam(':tid', $tid, \PDO::PARAM_INT);
        $stmt->bindParam(':ctime', $curTime, \PDO::PARAM_STR);
        $stmt->bindParam(':utime', $curTime, \PDO::PARAM_STR);
        $ret = $stmt->execute();
        return $ret;
    }

    public function deletePeopletitle($pid) {
        $sql=sprintf('DELETE FROM peopletitle where pid = :pid');
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_STR);
        $ret = $stmt->execute();
        return $ret;
    }

    public function queryByPid($pid) {
        $sql=sprintf('SELECT * FROM %s as t, peopletitle as p WHERE t.id = p.tid and pid = :pid',
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