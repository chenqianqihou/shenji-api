<?php

namespace app\models;

use yii\db\ActiveRecord;

class OrganizationDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "organization";
    }

    const OTYPE_ZHONGJIE = 1;
    const OTYPE_NEISHEN = 2;
    const OTYPE_JIGUAN = 3;

    public static function getOTypeMsg($type) {
        switch ($type) {
            case self::OTYPE_ZHONGJIE:
                return "中介机构";
            case self::OTYPE_NEISHEN:
                return "内审机构";
            case self::OTYPE_JIGUAN:
                return "审计机关";
        }

        return "";
    }

    const PROJ_LEVEL_PROVINCE = 1;
    const PROJ_LEVEL_CITY_LOCAL = 2;
    const PROJ_LEVEL_CITY_UNIFIED = 3;
    const PROJ_LEVEL_COUNTRY = 4;
}
