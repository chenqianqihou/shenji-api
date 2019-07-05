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

    public function queryById($id) {
        $sql=sprintf('SELECT * FROM %s WHERE id = :id',
            self::tableName()
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':id', $id, \PDO::PARAM_STR);
        $stmt->execute();
        $ret = $stmt->queryOne();
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