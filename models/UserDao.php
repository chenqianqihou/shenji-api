<?php

namespace app\models;

use yii\db\ActiveRecord;

class UserDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('shenji');
    }

    public static function tableName() {
        return "user";
    }

    /**
     * 通过用户名查询用户信息
     * @param $name
     * @return array|false
     * @throws \yii\db\Exception
     */
    public function queryByName($name) {
        $sql=sprintf('SELECT * FROM %s WHERE name = :name',
            self::tableName()
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':name', $name, \PDO::PARAM_STR);
        $stmt->execute();
        $ret = $stmt->queryOne();
        return $ret;
    }
}