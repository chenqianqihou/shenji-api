<?php
/**
 * Created by PhpStorm.
 * User: ryugou
 * Date: 2019/8/14
 * Time: 10:55 PM
 */

namespace app\models;

use yii\db\ActiveRecord;

class JugeProjectDao extends ActiveRecord{
    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "jugeproject";
    }
}