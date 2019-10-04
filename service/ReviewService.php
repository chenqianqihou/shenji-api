<?php
/**
 * Created by PhpStorm.
 * User: ryugou
 * Date: 2019/8/15
 * Time: 9:01 PM
 */
namespace app\service;

use app\models\PeopleReviewDao;
use app\models\ReviewDao;
use app\models\UserDao;

class ReviewService {


    /**
     * 获取中介审核状态
     *
     */
    public function getMediumStatus($id){
        $rew = ReviewDao::find()
            ->where(['projid' => $id])
            ->andWhere(['ptype' => ReviewDao::ZHONGJIE_TYPE])
            ->one();
        if($rew){
            switch ($rew->status){
                case ReviewDao::STATUS_DEFAULT:
                    return PeopleReviewDao::REVIEW_WAIT_TYPE;
                case ReviewDao::STATUS_SUCCESS:
                    return PeopleReviewDao::REVIEW_SUCCESS_TYPE;
                case ReviewDao::STATUS_FAILED:
                    return PeopleReviewDao::REVIEW_FAILED_TYPE;
            }

        }


        return PeopleReviewDao::REVIEW_NO_NEED_TYPE;


    }

    /**
     * 获取内审机构状态
     *
     */
    public function getInternalStatus($id){
        $rew = ReviewDao::find()
            ->where(['projid' => $id])
            ->andWhere(['ptype' => ReviewDao::NEISHEN_TYPE])
            ->one();
        if($rew){
            switch ($rew->status){
                case ReviewDao::STATUS_DEFAULT:
                    return PeopleReviewDao::REVIEW_WAIT_TYPE;
                case ReviewDao::STATUS_SUCCESS:
                    return PeopleReviewDao::REVIEW_SUCCESS_TYPE;
                case ReviewDao::STATUS_FAILED:
                    return PeopleReviewDao::REVIEW_FAILED_TYPE;
            }
        }



        return PeopleReviewDao::REVIEW_NO_NEED_TYPE;
    }



}