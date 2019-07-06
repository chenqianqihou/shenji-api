<?php

namespace app\models;

use yii\db\ActiveRecord;

class UserDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "people";
    }

    //性别
    public static $sex = [
        1 => "男",
        2 => "女",
    ];

    //人员类别
    public static $type = [
        1 => "审计机关",
        2 => "内审机构",
        3 => "中介机构",
    ];

    //人员类别
    public static $typeToName = [
        "审计机关" => 1,
        "内审机构" => 2,
        "中介机构" => 3,
    ];
    
    //学历
    public static $education = [
        1 => "大学本科",
        2 => "硕士研究生",
        3 => "博士研究生",
        4 => "大专",
        5 => "中专",
        6 => "其他",
    ];

    //政治面貌
    public static $political = [
        1 => '党员',
        2 => '团员',
        3 => '群众',
        4 => '其他',
    ];

    //能力等级
    public static $level = [
        1 => 'A',
        2 => 'B',
        3 => 'C',
        4 => 'D',
    ];

    //现任职务
    public static $position = [
        1 => '厅长',
        2 => '局长',
        3 => '处长',
        4 => '科长',
        5 => '股长',
        6 => '副主任科员',
        7 => '科员',
        8 => '其他',
    ];

    //现任职务
    public static $positionToName = [
        '厅长' => 1,
        '局长' => 2,
        '处长' => 3,
        '科长' => 4,
        '股长' => 5,
        '副主任科员' => 6,
        '科员' => 7,
        '其他' => 8,
    ];

    //岗位性质
    public static $nature = [
        1 => '业务岗位',
        2 => '综合岗位',
    ];

    public function addPeople($pid, $name, $sex, $type, $organId, $department, $level, $phone, $email,
                              $passwd, $cardid, $address, $education, $school, $major, $political, $nature,
                              $specialties, $achievements, $position, $location, $workbegin, $auditbegin, $comment) {
        $sql=sprintf('INSERT INTO %s (pid, `name`, sex, `type`, organid, department, `level`, phone, email,
                              passwd, cardid, address, education, school, major, political, nature,
                              specialties, achievements, `position`, location, workbegin, auditbegin, comment,
                              ctime, utime)
                              values (:pid, :name, :sex, :type, :organid, :department, :level, :phone, :email, :passwd,
                              :cardid, :address, :education, :school, :major, :political, :nature,
                              :specialties, :achievements, :position, :location, :workbegin, :auditbegin, :comment,
                              :ctime, :utime)', self::tableName()
        );
        $curTime = date('Y-m-d H:i:s');
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_STR);
        $stmt->bindParam(':name', $name, \PDO::PARAM_STR);
        $stmt->bindParam(':sex', $sex, \PDO::PARAM_INT);
        $stmt->bindParam(':type', $type, \PDO::PARAM_INT);
        $stmt->bindParam(':organid', $organId, \PDO::PARAM_INT);
        $stmt->bindParam(':department', $department, \PDO::PARAM_STR);
        $stmt->bindParam(':level', $level, \PDO::PARAM_INT);
        $stmt->bindParam(':phone', $phone, \PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, \PDO::PARAM_STR);
        $stmt->bindParam(':passwd', $passwd, \PDO::PARAM_STR);
        $stmt->bindParam(':cardid', $cardid, \PDO::PARAM_STR);
        $stmt->bindParam(':address', $address, \PDO::PARAM_STR);
        $stmt->bindParam(':education', $education, \PDO::PARAM_INT);
        $stmt->bindParam(':school', $school, \PDO::PARAM_STR);
        $stmt->bindParam(':major', $major, \PDO::PARAM_STR);
        $stmt->bindParam(':political', $political, \PDO::PARAM_INT);
        $stmt->bindParam(':nature', $nature, \PDO::PARAM_INT);
        $stmt->bindParam(':specialties', $specialties, \PDO::PARAM_STR);
        $stmt->bindParam(':achievements', $achievements, \PDO::PARAM_STR);
        $stmt->bindParam(':position', $position, \PDO::PARAM_STR);
        $stmt->bindParam(':location', $location, \PDO::PARAM_STR);
        $stmt->bindParam(':workbegin', $workbegin, \PDO::PARAM_STR);
        $stmt->bindParam(':auditbegin', $auditbegin, \PDO::PARAM_STR);
        $stmt->bindParam(':comment', $comment, \PDO::PARAM_STR);
        $stmt->bindParam(':ctime', $curTime, \PDO::PARAM_STR);
        $stmt->bindParam(':utime', $curTime, \PDO::PARAM_STR);
        $ret = $stmt->execute();
        return $ret;
    }

    //查询用户信息通过员工ID
    public function queryByID($pid) {
        $sql=sprintf('SELECT * FROM %s WHERE pid = :pid',
            self::tableName()
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':pid', $pid, \PDO::PARAM_STR);
        $stmt->execute();
        $ret = $stmt->queryOne();
        return $ret;
    }
}