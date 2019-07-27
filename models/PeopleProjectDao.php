<?php
/**
 * Created by PhpStorm.
 * User: ryugou
 * Date: 2019/7/27
 * Time: 5:49 PM
 */

namespace app\models;

use yii\db\ActiveRecord;

class PeopleProjectDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "peopleproject";
    }
}