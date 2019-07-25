<?php

namespace app\models;

use yii\db\ActiveRecord;

class ProjectDao extends ActiveRecord{

    public static function getDb()
    {
        return \Yii::$app->get('db');
    }

    public static function tableName() {
        return "project";
    }

    //项目阶段
    public static $status = [
        1 => "计划阶段",
        2 => "实施阶段",
        3 => "审理阶段",
        4 => "报告阶段",
        5 => "项目结束",
    ];

    //项目阶段
    public static $statusToName = [
        "计划阶段" => 1,
        "实施阶段" => 2,
        "审理阶段" => 3,
        "报告阶段" => 4,
        "项目结束" => 5,
    ];

    //项目层级
    public static $projlevel = [
        1 => "省厅统一组织",
        2 => "市州本级",
        3 => "市州统一组织",
        4 => "县级",
    ];

    /**
     * 获取项目层级
     *
     * @param $level
     * @return bool|mixed
     */
    public function getProjectLevelMsg($level) {
        if(!array_key_exists($level, static::$projlevel)) {
            return false;
        }
        return static::$projlevel[$level];
    }



    public function addProject($status, $projectnum, $name, $projyear, $plantime, $projdesc,
                               $projorgan, $projtype, $projlevel, $leadorgan,
                               $leadernum, $auditornum, $masternum) {
        $sql=sprintf('INSERT INTO %s (status, projectnum, name, projyear, plantime, projdesc, 
                              projorgan, projtype, projlevel, leadorgan, projstart, projauditcontent,
                              leadernum, auditornum, masternum, ctime, utime)
                              values (:status, :projectnum, :name, :projyear, :plantime, :projdesc, 
                              :projorgan, :projtype, :projlevel, :leadorgan, "0000-00-00 00:00:00", "",
                              :leadernum, :auditornum, :masternum, :ctime, :utime)', self::tableName()
        );
        $curTime = date('Y-m-d H:i:s');
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':status', $status, \PDO::PARAM_INT);
        $stmt->bindParam(':projectnum', $projectnum, \PDO::PARAM_STR);
        $stmt->bindParam(':name', $name, \PDO::PARAM_STR);
        $stmt->bindParam(':projyear', $projyear, \PDO::PARAM_INT);
        $stmt->bindParam(':plantime', $plantime, \PDO::PARAM_INT);
        $stmt->bindParam(':projdesc', $projdesc, \PDO::PARAM_STR);
        $stmt->bindParam(':projorgan', $projorgan, \PDO::PARAM_INT);
        $stmt->bindParam(':projtype', $projtype, \PDO::PARAM_STR);
        $stmt->bindParam(':projlevel', $projlevel, \PDO::PARAM_INT);
        $stmt->bindParam(':leadorgan', $leadorgan, \PDO::PARAM_INT);
        $stmt->bindParam(':leadernum', $leadernum, \PDO::PARAM_INT);
        $stmt->bindParam(':auditornum', $auditornum, \PDO::PARAM_INT);
        $stmt->bindParam(':masternum', $masternum, \PDO::PARAM_INT);
        $stmt->bindParam(':ctime', $curTime, \PDO::PARAM_STR);
        $stmt->bindParam(':utime', $curTime, \PDO::PARAM_STR);
        $ret = $stmt->execute();
        return $ret;
    }

    public function updateProject($status, $projectnum, $name, $projyear, $plantime, $projdesc,
                               $projorgan, $projtype, $projlevel, $leadorgan,
                               $leadernum, $auditornum, $masternum) {
        $sql=sprintf('INSERT INTO %s (status, projectnum, name, projyear, plantime, projdesc, 
                              projorgan, projtype, projlevel, leadorgan, projstart, projauditcontent,
                              leadernum, auditornum, masternum, ctime, utime)
                              values (:status, :projectnum, :name, :projyear, :plantime, :projdesc, 
                              :projorgan, :projtype, :projlevel, :leadorgan, "0000-00-00 00:00:00", "",
                              :leadernum, :auditornum, :masternum, :ctime, :utime)', self::tableName()
        );
        $curTime = date('Y-m-d H:i:s');
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':status', $status, \PDO::PARAM_INT);
        $stmt->bindParam(':projectnum', $projectnum, \PDO::PARAM_STR);
        $stmt->bindParam(':name', $name, \PDO::PARAM_STR);
        $stmt->bindParam(':projyear', $projyear, \PDO::PARAM_INT);
        $stmt->bindParam(':plantime', $plantime, \PDO::PARAM_INT);
        $stmt->bindParam(':projdesc', $projdesc, \PDO::PARAM_STR);
        $stmt->bindParam(':projorgan', $projorgan, \PDO::PARAM_INT);
        $stmt->bindParam(':projtype', $projtype, \PDO::PARAM_STR);
        $stmt->bindParam(':projlevel', $projlevel, \PDO::PARAM_INT);
        $stmt->bindParam(':leadorgan', $leadorgan, \PDO::PARAM_INT);
        $stmt->bindParam(':leadernum', $leadernum, \PDO::PARAM_INT);
        $stmt->bindParam(':auditornum', $auditornum, \PDO::PARAM_INT);
        $stmt->bindParam(':masternum', $masternum, \PDO::PARAM_INT);
        $stmt->bindParam(':ctime', $curTime, \PDO::PARAM_STR);
        $stmt->bindParam(':utime', $curTime, \PDO::PARAM_STR);
        $ret = $stmt->execute();
        return $ret;
    }

    public function updateAuditContent($id, $projstart, $projauditcontent) {
        $sql=sprintf('UPDATE %s SET projstart = :projstart, projauditcontent = :projauditcontent where id = :id', self::tableName());
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
        $stmt->bindParam(':projstart', $projstart, \PDO::PARAM_STR);
        $stmt->bindParam(':projauditcontent', $projauditcontent, \PDO::PARAM_STR);
        $ret = $stmt->execute();
        return $ret;
    }

    public function queryByID($id) {
        $sql=sprintf('SELECT * FROM %s WHERE id = :id',
            self::tableName()
        );
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $ret = $stmt->queryOne();
        return $ret;
    }

    public function queryList($type, $organid, $query, $start, $length) {
        $condition = "";
        if ($type != "") {
            $condition = $condition . " type = :type ";
        }elseif ($organid != "") {
            $condition = $condition . " organid = :organid ";
        }
        if ($query != "") {
            if ($condition != "") {
                $condition = $condition . " and ";
            }
            $condition = $condition . " (name like '%$query%' or pid like '%$query%')";
        }
        if ($condition != "") {
            $sql = sprintf('SELECT * FROM %s WHERE %s ',
                self::tableName(), $condition
            );
        }else {
            $sql = sprintf('SELECT * FROM %s',
                self::tableName()
            );
        }
        $sql = $sql . " order by ctime desc limit $start, $length";
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        if ($type != "") {
            $stmt->bindParam(':type', $type, \PDO::PARAM_INT);
        }elseif ($organid != "") {
            $stmt->bindParam(':organid', $organid, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $ret = $stmt->queryAll();
        return $ret;
    }

    public function countList($type, $organid, $query) {
        $condition = "";
        if ($type != "") {
            $condition = $condition . " type = :type ";
        }elseif ($organid != "") {
            $condition = $condition . " organid = :organid ";
        }
        if ($query != "") {
            if ($condition != "") {
                $condition = $condition . " and ";
            }
            $condition = $condition . " (name like '%$query%' or pid like '%$query%')";
        }
        if ($condition != "") {
            $sql = sprintf('SELECT count(1) as c FROM %s WHERE %s ',
                self::tableName(), $condition
            );
        }else {
            $sql = sprintf('SELECT count(1) as c FROM %s',
                self::tableName()
            );
        }
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        if ($type != "") {
            $stmt->bindParam(':type', $type, \PDO::PARAM_INT);
        }elseif ($organid != "") {
            $stmt->bindParam(':organid', $type, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $ret = $stmt->queryOne();
        if ($ret) {
            return $ret['c'];
        }else {
            return 0;
        }
    }

    public function deleteProject($id) {
        $sql=sprintf('DELETE FROM %s WHERE id = :id', self::tableName());
        $stmt = self::getDb()->createCommand($sql);
        $stmt->prepare();
        $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
        $ret = $stmt->execute();
        return $ret;
    }
}
