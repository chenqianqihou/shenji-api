<?php
namespace app\models;

use yii\db\ActiveRecord;

class QualificationDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "qualification";
    }

    public function addQualification($pid, $info, $time) {
        $sql=sprintf('INSERT INTO %s (pid, info, `time`, ctime, utime) values (:pid, :info, :time, :ctime, :utime)', self::tableName());
        $curTime = date('Y-m-d H:i:s');
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_STR);
        $stmt->bindParam(':info', $info, \PDO::PARAM_STR);
        $stmt->bindParam(':time', $time, \PDO::PARAM_STR);
        $stmt->bindParam(':ctime', $curTime, \PDO::PARAM_STR);
        $stmt->bindParam(':utime', $curTime, \PDO::PARAM_STR);
        $ret = $stmt->execute();
        return $ret;
    }

    public function deleteQualification($pid) {
        $sql=sprintf('DELETE FROM %s where pid = :pid', self::tableName());
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_STR);
        $ret = $stmt->execute();
        return $ret;
    }

    public function queryByPid($pid) {
        $sql=sprintf('SELECT * FROM %s WHERE pid = :pid',
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