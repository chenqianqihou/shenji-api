<?php

namespace app\models;

use yii\db\ActiveRecord;

class ObjectivetypeDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "objectivetype";
    }
}
