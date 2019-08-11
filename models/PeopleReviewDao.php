<?php
/**
 * Created by PhpStorm.
 * User: ryugou
 * Date: 2019/8/11
 * Time: 11:38 AM
 */
namespace app\models;

use yii\db\ActiveRecord;

class PeopleReviewDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "peoplereview";
    }

    const REVIEW_NO_NEED_TYPE = 1;
    const REVIEW_NOT_SURE_TYPE = 2;
    const REVIEW_WAIT_TYPE = 3;
    const REVIEW_SUCCESS_TYPE = 4;
    const REVIEW_FAILED_TYPE = 5;
    public static $reviewMsg = [
      self::REVIEW_NO_NEED_TYPE => '无需审核',
      self::REVIEW_NOT_SURE_TYPE => '待提审',
      self::REVIEW_WAIT_TYPE => '待审核',
      self::REVIEW_SUCCESS_TYPE => '审核成功',
      self::REVIEW_FAILED_TYPE => '审核失败'
    ];
}