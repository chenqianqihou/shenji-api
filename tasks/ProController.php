<?php
/**
 * Created by PhpStorm.
 * User: ryugou
 * Date: 2019/10/17
 * Time: 11:51 PM
 */
namespace app\tasks;

use app\models\AuditGroupDao;
use app\models\OrganizationDao;
use app\models\PeopleProjectDao;
use app\models\ProjectDao;
use app\models\UserDao;
use yii\console\Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use app\service\ProjectService;


class ProController extends Controller {

    //将项目表中的信息导出
    public function actionProjectexport() {
        $proj = ProjectDao::find()->asArray()->all();
        echo "id,项目编号,项目名\n";
        foreach( $proj as $p) {
            echo $p['id'].',\''.$p['projectnum'].','.$p['name']."\n";    
        }
    }

    //历史项目导入
    public function actionHistoryimport( $pf ) {
        $nowdir = getcwd().'/';
        $pfile = $nowdir.$pf;
        if(! file_exists( $pfile ) ) {
            echo "文件不存在！";    
            return;
        }
        
        echo "当前处理文件为：".$pfile."\n";
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $pfile );
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        print_r( $sheetData[1]);
        print_r($sheetData[2]);


        $elems = [];

        foreach ($sheetData as $key => $value){
            if($key >= 2 ) {
                $organs = explode($value['G'], "-");
                $org = OrganizationDao::find()
                    ->where(['name' => $organs[0]])
                    ->asArray()
                    ->one();
                if(!$org) {
                   echo "未找到相关的项目单位！";
                   return;
                }

                $leaderOrg = OrganizationDao::find()
                    ->where(['parentid' => $org['id']])
                    ->andWhere(['name' => $organs[1]])
                    ->asArray()
                    ->one();

                if(!$leaderOrg){
                    echo "未找到{$organs[0]}的牵头业务部门{$organs[1]}!";
                    return;
                }

                $level = explode($value['E'], ':');
                $leaders = explode($value['J'], "\n");
                $masters = explode($value['K'], "\n");
                $auditors = explode($value['L'], "\n");

                $leaderIds = [];
                $masterIds = [];
                $auditorIds = [];

                foreach ($leaders as $e){
                    $user = UserDao::find()
                        ->where(['name' => $e])
                        ->asArray()
                        ->one();
                    if(!$user){
                        echo "未找到员工{$e}";
                        return;
                    }
                    $leaderIds[] = $user['id'];
                }

                foreach ($masters as $e){
                    $user = UserDao::find()
                        ->where(['name' => $e])
                        ->asArray()
                        ->one();
                    if(!$user){
                        echo "未找到员工{$e}";
                        return;
                    }
                    $masterIds[] = $user['id'];
                }

                foreach ($auditors as $e){
                    $user = UserDao::find()
                        ->where(['name' => $e])
                        ->asArray()
                        ->one();
                    if(!$user){
                        echo "未找到员工{$e}";
                        return;
                    }
                    $auditorIds[] = $user['id'];
                }

                $location = explode($value['T'], ":");

                $elem = [
                    "projectnum" => strtotime('now'),
                    "name" => $value['A'],
                    "projyear" => $value['C'],
                    "plantime" => $value['B'],
                    "projdesc" => $value['D'],
                    "projorgan" => $org['id'],
                    "projtype" => $org['H'],
                    "projlevel" => $level[0],
                    "leadorgan" => $leaderOrg['id'],
                    "leaders" => $leaderIds,
                    "masters" => $masterIds,
                    "auditors" => $auditorIds,
                    "location" => $location[0]
                ];
                $elems[] = $elem;
            }
        }


        foreach ($elems as $e){
            $service = new ProjectService();
            $projectId = $service->createProject(
                ProjectDao::$statusToName['项目结束'],
                $e['projectnum'],
                $e['name'],
                $e['projyear'],
                $e['plantime'],
                $e['projdesc'],
                $e['projorgan'],
                json_encode($e['projtype'], JSON_UNESCAPED_UNICODE),
                $e['projlevel'],
                $e['leadorgan'],
                count($e['leaders']),
                count($e['masters']),
                count($e['auditors']),
                $e['location']
            );

            $group = [];
            foreach ($e['leaders'] as $leader) {
                $auditGroup = new AuditGroupDao();
                $group[$auditGroup->addAuditGroup($projectId)] = [
                    "leader" => $leader
                ];
            }

            while (count($e['masters']) > 0) {
                foreach ($group as $key => $value) {
                    $group[$key]['master'][] = array_pop($e['masters']);
                }
            }

            while (count($e['auditors']) > 0) {
                foreach ($group as $key => $value) {
                    $group[$key]['auditor'][] = array_pop($e['auditors']);
                }
            }

            foreach ($group as $key => $value){
                //leader
                $pepProject = new PeopleProjectDao();
                $pepProject->pid = $value['leader'];
                $pepProject->groupid = $key;
                $pepProject->roletype = PeopleProjectDao::ROLE_TYPE_GROUP_LEADER;
                $pepProject->islock = $pepProject::NOT_LOCK;
                $pepProject->projid = $projectId;
                $pepProject->save();



                //master
                foreach ($value['master'] as $m){
                    $pepProject = new PeopleProjectDao();
                    $pepProject->pid = $m;
                    $pepProject->groupid = $key;
                    $pepProject->roletype = PeopleProjectDao::ROLE_TYPE_MASTER;
                    $pepProject->islock = $pepProject::NOT_LOCK;
                    $pepProject->projid = $projectId;
                    $pepProject->save();
                }


                //auditors
                foreach ($value['auditor'] as $m){
                    $pepProject = new PeopleProjectDao();
                    $pepProject->pid = $m;
                    $pepProject->groupid = $key;
                    $pepProject->roletype = PeopleProjectDao::ROLE_TYPE_GROUPER;
                    $pepProject->islock = $pepProject::NOT_LOCK;
                    $pepProject->projid = $projectId;
                    $pepProject->save();
                }

            }



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
