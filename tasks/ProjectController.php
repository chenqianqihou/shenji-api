﻿<?phpnamespace app\tasks;use app\models\AuditGroupDao;use app\models\PeopleProjectDao;use app\models\ProjectDao;use app\models\UserDao;use linslin\yii2\curl\Curl;use yii\console\Controller;class ProjectController extends Controller{    /**     * 扫描修改审计组的审计状态     *     * 1、三天内，审计组没进点，状态变更为“ 该进点而未进点”；     * 2、最后一个进点的审计组的进点后，项目时长内，没有结束项目实施，状态为“该结束未结束”。     *     */    public function actionScanStatus()    {        //1、三天内，审计组没进点，状态变更为“ 该进点而未进点”；        $timeLimit = strtotime('-3 day');        $auditGroups = AuditGroupDao::find()            ->where(['status' => AuditGroupDao::$statusToName['应进点']])            ->all();        foreach ($auditGroups as $e) {            if(strtotime($e->utime) >= $timeLimit){                $e->status = AuditGroupDao::$statusToName['该进点而未进点'];                $e->save();            }        }        //2、最后一个进点的审计组的进点后，项目时长内，没有结束项目实施，状态为“该结束未结束”。        $now = time();        $elems = (new \yii\db\Query())            ->from('peopleproject')            ->select('auditgroup.utime, auditgroup.id, project.plantime, project.id as pid')            ->innerJoin('auditgroup', 'auditgroup.id = peopleproject.groupid')            ->innerJoin('project', 'peopleproject.projid = project.id')            ->where(['auditgroup.status' => AuditGroupDao::$statusToName['已进点']])            ->groupBy('peopleproject.projid')            ->all();        $projectMap = [];        foreach ($elems as $e){            $projectMap[$e['pid']][] = $e;        }        foreach ($projectMap as $map) {            $needChangeFlag = false;            foreach ($map as $m){                if (strtotime("+{$m['pid']}", strtotime($m['uptime'])) >= $now) {                    $needChangeFlag = true;                }            }            if ($needChangeFlag) {                foreach ($map as $m){                    $audit = AuditGroupDao::findOne($m['id']);                    $audit->status = AuditGroupDao::$statusToName['该结束未结束'];                    $audit->save();                }            }        }    }    /**     * 计算审计人员=》客观分+主观分     * 1、项目结束时计算     */    public function actionAuditpeoplescore()    {        $peopleProjects = (new \yii\db\Query())            ->from('peopleproject')            ->innerJoin('project', 'peopleproject.projid = project.id')            ->select('peopleproject.*')            ->where(['status' => ProjectDao::$statusToName['项目结束']])            ->andWhere(['subjectscore' => -1])            ->all();        $projectIds = [];        foreach ($peopleProjects as $one) {            if (!in_array($one['projid'], $projectIds)) {                $projectIds[] = $one['projid'];            }        }        if (count($projectIds) == 0) {            return;        }        $qanswers = (new \yii\db\Query())            ->from('qanswer')            ->innerJoin('people', 'people.pid = qanswer.objpid')            ->select('qanswer.*, people.type')            ->where(['in', 'projectid', $projectIds])            ->all();        $peopleProjectScore = [];        foreach ($qanswers as $answer) {            if (!isset($peopleProjectScore[$answer['projectid']])) {                $peopleProjectScore[$answer['projectid']] = [];            }            if (!isset($peopleProjectScore[$answer['projectid']][$answer['objpid']])) {                $peopleProjectScore[$answer['projectid']][$answer['objpid']] = [];            }            $peopleProjectScore[$answer['projectid']][$answer['objpid']]['role'] = $answer['type'];            if (!isset($peopleProjectScore[$answer['projectid']][$answer['objpid']]['score'])) {                $peopleProjectScore[$answer['projectid']][$answer['objpid']]['score'] = [];            }            $peopleProjectScore[$answer['projectid']][$answer['objpid']]['score'][] = $answer['score'];        }        //计算主观分数        foreach ($peopleProjects as $one) {            if (isset($peopleProjectScore[$one['projid']])                && isset($peopleProjectScore[$one['projid']][$one['pid']])) {                $score = $peopleProjectScore[$one['projid']][$one['pid']]['role']['score'];                if ($peopleProjectScore[$one['projid']][$one['pid']]['role']                    == UserDao::$typeToName['审计机关']) {                    $subjectScore = intval(array_sum($score) / count($score));                }else {                    $subjectScore = array_sum($score);                }                $peopleProjectDao = PeopleProjectDao::find()->where(                    ['projid' => $one['projid'], 'pid' => $one['pid']])->one();                $peopleProjectDao->subjectscore = $subjectScore;                $peopleProjectDao->save();            }        }        //计算客观分数        $objectiveScores = (new \yii\db\Query())            ->from('objectivescore')            ->innerJoin('objectivetype', 'objectivescore.typeid = objectivetype.id')            ->select('objectivescore.*, objectivetype.onetype, objectivetype.twotype')            ->all();        $problemScore = [];        $projectLevelScore = [];        $projectRoleScore = [];        $havereportScore = [];        foreach ($objectiveScores as $one) {            if ($one['kindid'] == 2) {                $tmp = json_decode($one['nameone']);                $problemScore[$tmp[2]] = [];                $problemScore[$tmp[2]][$tmp[3]] = $one['score'];            }elseif ($one['kindid'] == 1) {                switch ($one['nameone']) {                    case '省厅统一组织':                        $projectLevelScore[ProjectDao::$projLevelName['省厅统一组织']] = $one['score'];                        break;                    case '市州本级':                        $projectLevelScore[ProjectDao::$projLevelName['市州本级']] = $one['score'];                        break;                    case '市州统一组织':                        $projectLevelScore[ProjectDao::$projLevelName['市州统一组织']] = $one['score'];                        break;                    case '县级':                        $projectLevelScore[ProjectDao::$projLevelName['县级']] = $one['score'];                        break;                    case '审计组长':                        $projectRoleScore[AuditGroupDao::$roleTypeName['审计组长']] = $one['score'];                        break;                    case '主审':                        $projectRoleScore[AuditGroupDao::$roleTypeName['主审']] = $one['score'];                        break;                    case '审计成员':                        $projectRoleScore[AuditGroupDao::$roleTypeName['审计成员']] = $one['score'];                        break;                    default:                        break;                }                if ($one['twotype'] == '报告撰写' && $one['nameone'] == '是') {                    $havereportScore[1] = $one['score'];                }                if ($one['twotype'] == '报告撰写' && $one['nameone'] == '否') {                    $havereportScore[2] = $one['score'];                }                if ($one['twotype'] == '是否单独查出' && $one['nameone'] == '是') {                    $havereportScore[1] = $one['score'];                }                if ($one['twotype'] == '是否单独查出' && $one['nameone'] == '否') {                    $havereportScore[2] = $one['score'];                }                if ($one['twotype'] == '是否有移送事项' && $one['nameone'] == '是') {                    $havereportScore[1] = $one['score'];                }                if ($one['twotype'] == '是否有移送事项' && $one['nameone'] == '否') {                    $havereportScore[2] = $one['score'];                }                if ($one['twotype'] == '移送处理机关' && $one['nameone'] == '司法机关') {                    $havereportScore[1] = $one['score'];                }                if ($one['twotype'] == '移送处理机关' && $one['nameone'] == '纪检监察机关') {                    $havereportScore[2] = $one['score'];                }                if ($one['twotype'] == '移送处理机关' && $one['nameone'] == '有关部门') {                    $havereportScore[2] = $one['score'];                }                if ($one['twotype'] == '送处理人员情况' && $one['nameone'] == '地厅级以上') {                    $havereportScore[1] = $one['score'];                }                if ($one['twotype'] == '送处理人员情况' && $one['nameone'] == '县处级') {                    $havereportScore[2] = $one['score'];                }                if ($one['twotype'] == '送处理人员情况' && $one['nameone'] == '乡科级') {                    $havereportScore[1] = $one['score'];                }                if ($one['twotype'] == '送处理人员情况' && $one['nameone'] == '其他') {                    $havereportScore[2] = $one['score'];                }                if ($one['twotype'] == '是否纳入本级工作报告' && $one['nameone'] == '是') {                    $havereportScore[1] = $one['score'];                }                if ($one['twotype'] == '是否纳入本级工作报告' && $one['nameone'] == '否') {                    $havereportScore[2] = $one['score'];                }                if ($one['twotype'] == '是否纳入上级审计工作报告' && $one['nameone'] == '是') {                    $havereportScore[1] = $one['score'];                }                if ($one['twotype'] == '是否纳入上级审计工作报告' && $one['nameone'] == '否') {                    $havereportScore[2] = $one['score'];                }                if ($one['twotype'] == '评优' && $one['nameone'] == '署优秀') {                    $havereportScore[1] = $one['score'];                }                if ($one['twotype'] == '评优' && $one['nameone'] == '署表彰') {                    $havereportScore[2] = $one['score'];                }                if ($one['twotype'] == '评优' && $one['nameone'] == '省表彰') {                    $havereportScore[1] = $one['score'];                }                if ($one['twotype'] == '评优' && $one['nameone'] == '市表彰') {                    $havereportScore[2] = $one['score'];                }                if ($one['twotype'] == '评优' && $one['nameone'] == '县优秀') {                    $havereportScore[1] = $one['score'];                }                if ($one['twotype'] == '评优' && $one['nameone'] == '省优秀') {                    $havereportScore[2] = $one['score'];                }                if ($one['twotype'] == '评优' && $one['nameone'] == '市优秀') {                    $havereportScore[1] = $one['score'];                }                if ($one['twotype'] == '评优' && $one['nameone'] == '县表彰') {                    $havereportScore[2] = $one['score'];                }            }elseif($one['kindid'] == 3) {            }        }    }}