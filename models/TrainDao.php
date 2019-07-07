<?php
namespace app\models;

use yii\db\ActiveRecord;

class TrainDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "train";
    }

    public function addTrain($pid, $train) {
        $sql=sprintf('INSERT INTO %s (pid, train, ctime, utime) values (:pid, :train, :ctime, :utime)', self::tableName());
        $curTime = date('Y-m-d H:i:s');
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_STR);
        $stmt->bindParam(':train', $train, \PDO::PARAM_STR);
        $stmt->bindParam(':ctime', $curTime, \PDO::PARAM_STR);
        $stmt->bindParam(':utime', $curTime, \PDO::PARAM_STR);
        $ret = $stmt->execute();
        return $ret;
    }

    public function deleteTrain($pid) {
        $sql=sprintf('DELETE FROM %s WHERE pid = :pid', self::tableName());
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