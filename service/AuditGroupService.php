<?php

namespace app\service;

use app\models\AuditGroupDao;
use Yii;

class AuditGroupService
{
    //查询某个项目下的审计组列表信息
    function listByProjectId($id) {
        $ret = [];
        $auditGroups = AuditGroupDao::find()
            ->where(['pid' => $id])
            ->asArray()
            ->all();
        foreach ($auditGroups as $e){
            $tmp = [
                'id' => $e['id'],
                'status' => $e['status'],
            ];

            $peoples = (new \yii\db\Query())
                ->from('peopleproject')
                ->innerJoin('people', 'peopleproject.pid = people.id')
                ->select('people.id, people.pid, people.name, people.sex, peopleproject.roletype, people.address as location, peopleproject.roletype as role, people.level, peopleproject.islock')
                ->where(['peopleproject.groupid' => $e['id']])
                ->andWhere(['peopleproject.projid' => $id])
                ->all();

            foreach ($peoples as $p){
                $tmp['group'][] = $p;
            }
            $ret[] = $tmp;
        }
        return $ret;
    }
}