<?php
/**
 * Created by PhpStorm.
 * User: ryugou
 * Date: 2019/9/6
 * Time: 12:16 AM
 */

namespace app\models;

use yii\db\ActiveRecord;

class PeopleExpertiseDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "peopleexpertise";
    }

}