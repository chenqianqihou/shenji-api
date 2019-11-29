<?php
/**
 * Created by PhpStorm.
 * User: ryugou
 * Date: 2019/10/17
 * Time: 11:51 PM
 */
namespace app\tasks;

use app\models\AuditGroupDao;
use app\models\ProjectDao;
use yii\console\Controller;


class ProController extends Controller {

    //将项目表中的信息导出
    public function actionProjectexport() {
        $proj = ProjectDao::find()->asArray()->all();
        echo "id,项目编号,项目名\n";
        foreach( $proj as $p) {
            echo $p['id'].',\''.$p['projectnum'].','.$p['name']."\n";    
        }
    }


    /**
     * 扫描修改审计组的审计状态
     *
     * 1、三天内，审计组没进点，状态变更为“ 该进点而未进点”；
     * 2、最后一个进点的审计组的进点后，项目时长内，没有结束项目实施，状态为“该结束未结束”。
     *
     */
    public function actionScanstatus()
    {
        //1、三天内，审计组没进点，状态变更为“ 该进点而未进点”；
        $timeLimit = strtotime('-3 day');
        $auditGroups = AuditGroupDao::find()
            ->where(['in', 'status', [AuditGroupDao::$statusToName['无'], AuditGroupDao::$statusToName['应进点']]])
            ->all();

        foreach ($auditGroups as $e) {
            if($timeLimit >= strtotime($e->utime)){
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
