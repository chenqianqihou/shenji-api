<?php
/**
 * Created by PhpStorm.
 * User: ryugou
 * Date: 2019/8/2
 * Time: 2:53 PM
 */
namespace app\tasks;

use app\models\AuditGroupDao;
use linslin\yii2\curl\Curl;
use yii\console\Controller;

class ProjectController extends Controller{

    /**
     * 扫描修改审计组的审计状态
     *
     * 1、三天内，审计组没进点，状态变更为“ 该进点而未进点”；
     * 2、最后一个进点的审计组的进点后，项目时长内，没有结束项目实施，状态为“该结束未结束”。
     *
     */
    public function actionScanStatus()
    {
        //1、三天内，审计组没进点，状态变更为“ 该进点而未进点”；
        $timeLimit = strtotime('-3 day');
        $auditGroups = AuditGroupDao::find()
            ->where(['status' => AuditGroupDao::$statusToName['应进点']])
            ->all();
        foreach ($auditGroups as $e) {
            if(strtotime($e->utime) >= $timeLimit){
                $e->status = AuditGroupDao::$statusToName['该进点而未进点'];
                $e->save();
            }
        }

        //2、最后一个进点的审计组的进点后，项目时长内，没有结束项目实施，状态为“该结束未结束”。
        $now = time();
        $elems = (new \yii\db\Query())
            ->from('peopleproject')
            ->select('auditgroup.utime, auditgroup.id, project.plantime, project.id as pid')
            ->innerJoin('auditgroup', 'auditgroup.id = peopleproject.groupid')
            ->innerJoin('project', 'peopleproject.projid = project.id')
            ->where(['auditgroup.status' => AuditGroupDao::$statusToName['已进点']])
            ->groupBy('peopleproject.projid')
            ->all();
        $projectMap = [];
        foreach ($elems as $e){
            $projectMap[$e['pid']][] = $e;
        }
        foreach ($projectMap as $map) {
            $needChangeFlag = false;
            foreach ($map as $m){
                if (strtotime("+{$m['pid']}", strtotime($m['uptime'])) >= $now) {
                    $needChangeFlag = true;
                }
            }
            if ($needChangeFlag) {
                foreach ($map as $m){
                    $audit = AuditGroupDao::findOne($m['id']);
                    $audit->status = AuditGroupDao::$statusToName['该结束未结束'];
                    $audit->save();
                }
            }
        }

    }
}