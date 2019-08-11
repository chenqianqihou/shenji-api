<?php
/**
 * Created by PhpStorm.
 * User: ryugou
 * Date: 2019/8/11
 * Time: 11:37 AM
 */
namespace app\models;

use yii\db\ActiveRecord;

class ReviewDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "review";
    }

    const PEOPLE_TYPE = 1;
    const RESULT_TYPE = 2;

    public static $typeMsg = [
      self::PEOPLE_TYPE => "人员调配审核",
      self::RESULT_TYPE => "审计成果审核"
    ];

    const STATUS_SUCCESS = 1;
    const STATUS_FAILED = 2;
    public static $statusMsg = [
        self::STATUS_SUCCESS => '成功！',
        self::STATUS_FAILED => '失败!'
    ];

    const ZHONGJIE_TYPE = 1;
    const NEISHEN_TYPE = 2;
    public static $ptypeMsg = [
        self::ZHONGJIE_TYPE => '中介',
        self::NEISHEN_TYPE => '内审'
    ];
}