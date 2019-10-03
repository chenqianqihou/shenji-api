<?php

namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\Checker;
use app\classes\ErrorDict;
use app\classes\Log;
use app\classes\Pinyin;
use app\models\AuditGroupDao;
use app\models\ExpertiseDao;
use app\models\JugeProjectDao;
use app\models\OrganizationDao;
use app\models\PeopleProjectDao;
use app\models\PeopleReviewDao;
use app\models\ProjectDao;
use app\models\QualificationDao;
use app\models\ReviewDao;
use app\models\RoleDao;
use app\models\TechtitleDao;
use app\models\TrainDao;
use app\models\UserDao;
use app\service\OrganizationService;
use app\service\UserService;
use app\service\ProjectService;
use Yii;
use \Lcobucci\JWT\Builder;
use \Lcobucci\JWT\Signer\Hmac\Sha256;
use yii\db\Exception;
use app\service\ReviewService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class ProjectController extends BaseController
{

    public function actionCreate()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'name' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'plantime' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projyear' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projdesc' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projorgan' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projtype' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projlevel' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'leadorgan' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'leadernum' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'leader_projtype' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'leader_filternum' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'masternum' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'master_projtype' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'master_filternum' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'auditornum' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'location' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),

        );
        if (!$this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $name = $this->getParam('name', '');
        $plantime = intval($this->getParam('plantime', 0));
        $projyear = intval($this->getParam('projyear', 0));
        $projorgan = intval($this->getParam('projorgan', 0));
        $projdesc = $this->getParam('projdesc', '');
        $projtype = $this->getParam('projtype', '');
        $projlevel = intval($this->getParam('projlevel', 0));
        $leadorganId = intval($this->getParam('leadorgan', 0));
        $leadernum = intval($this->getParam('leadernum', 0));
        $leaderProjType = $this->getParam('leader_projtype', []);
        $leaderFilternum = intval($this->getParam('', 0));
        $masternum = intval($this->getParam('masternum', 0));
        $masterProjType = $this->getParam('master_projtype', []);
        $masterFilternum = intval($this->getParam('master_filternum', 0));
        $auditornum = intval($this->getParam('auditornum', 0));
        $location = $this->getParam('location', '');



        //如果单位组织不存在，则返回异常
        $organ = OrganizationDao::find()->where(['id' => $projorgan])->one();
        if(!$organ){
            return $this->outputJson(
                '',
                ErrorDict::getError(ErrorDict::G_PARAM, "不存在的项目单位！")
            );
        }
        if(empty($projtype)){
            return $this->outputJson(
                '',
                ErrorDict::getError(ErrorDict::G_PARAM, "项目类型错误!")
            );
        }
        if(!in_array($projlevel, [1, 2, 3, 4])){
            return $this->outputJson(
                '',
                ErrorDict::getError(ErrorDict::G_PARAM, "项目层级输入有误！")
            );
        }
        $leadorgan = OrganizationDao::find()->where(['id' => $leadorganId])->one();
        if(!$leadorgan){
            return $this->outputJson(
                '',
                ErrorDict::getError(ErrorDict::G_PARAM, "不存在的牵头业务部门！")
            );
        }

        $transaction = ProjectDao::getDb()->beginTransaction();

        try{
            $service = new ProjectService();
            $projectId = $service->createProject(
                ProjectDao::$statusToName['未开始'],
                strtotime('now'),
                $name,
                $projyear,
                $plantime,
                $projdesc,
                $projorgan,
                json_encode($projtype, JSON_UNESCAPED_UNICODE),
                $projlevel,
                $leadorganId,
                $leadernum,
                $auditornum,
                $masternum,
                $location
            );

            //预分配人员
            if(!$leadernum){
                $transaction->commit();
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::SUCCESS)
                );
            }
            // 包含groupid => [ pid、以及 leader 字段]
            $group = [];
            for($i = 0; $i < $leadernum; $i++){
                $auditGroup = new AuditGroupDao();
                $group[$auditGroup->addAuditGroup($projectId)] = [];
            }
            $leaders = [];
            $masters = [];
            $members = [];



            $orgIds = (new OrganizationService)->getSubordinateIds($projorgan);
            $matchMembers = UserDao::find()
                ->where(['isjob' => UserDao::$isJobToName['不在点']])
                ->andWhere(['in', 'organid', $orgIds])
                ->andWhere(['type' => UserDao::$typeToName['审计机关']]);


            if($projlevel === ProjectDao::$projLevelName['省厅本级']){
                $organization = OrganizationDao::find()
                    ->where(["name" => "贵州省审计厅"])
                    ->one();
                if($organization){
                    $allMatchPeoples = $matchMembers->andWhere(["organid" => $organization->id])->all();
                }

                if($leadernum ){
                    if($leaderProjType && $leaderFilternum){
                        $peoples = (new \yii\db\Query())
                            ->from("peopleproject")
                            ->innerJoin("project", "peopleproject.projid = project.id")
                            ->where(["project.projtype" => json_encode($leaderProjType, JSON_UNESCAPED_UNICODE)])
                            ->andWhere(["project.projorgan" => $organization->id])
                            ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_GROUP_LEADER])
                            ->groupBy("peopleproject.pid")
                            ->having([">=", "pidnum", $leaderFilternum])
                            ->select(["count(*) as pidnum", "peopleproject.pid"])
                            ->all();

                        if(count($peoples) > 0){
                            $tmpLeaderIds = array_map(function($e){
                                return $e['pid'];
                            }, $peoples);

                            $tmpMatchPeoples = clone $matchMembers;
                            $leaders = $tmpMatchPeoples
                                ->andWhere(["in", "id", $tmpLeaderIds])
                                ->asArray()
                                ->all();
                        }

                    } else {
                        $tmpMatchPeoples = clone $matchMembers;
                        $leaders = $tmpMatchPeoples
                            ->limit($leadernum)
                            ->asArray()
                            ->all();
                    }
                }
                $members = array_values(array_filter($allMatchPeoples, function($e) use ($leaders){
                    foreach ($leaders as $le){
                        if($le['id'] == $e['id']){
                            return false;
                        }
                    }
                    return true;
                }));

                if($masternum){
                    if($masterProjType && $masterFilternum){
                        $peoples = (new \yii\db\Query())
                            ->from("peopleproject")
                            ->innerJoin("project", "peopleproject.projid = project.id")
                            ->where(["project.projtype" => json_encode($masterProjType, JSON_UNESCAPED_UNICODE)])
                            ->andWhere(["project.projorgan" => $organization->id])
                            ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_MASTER])
                            ->groupBy("peopleproject.pid")
                            ->having([">=", "pidnum", $masterFilternum])
                            ->select(["count(*) as pidnum", "peopleproject.pid"])
                            ->all();

                        if(count($peoples) > 0){
                            $tmpMasterIds = array_map(function($e){
                                return $e['pid'];
                            }, $peoples);

                            $leaderLocations = array_values(array_unique(array_map(function($e){
                                $locatin = explode($e['location'], ",");
                                if(count($locatin) === 3){
                                    unset($locatin[2]);
                                }
                                $locatin = join($locatin, ",");
                                return $locatin;
                            }, $leaders)));

                            foreach ($leaderLocations as $location){
                                $tmpMatchPeoples = clone $matchMembers;
                                $masters = array_merge($tmpMatchPeoples
                                    ->andWhere(["in", "id", $tmpMasterIds])
                                    ->andWhere(["like", "location", "%$location%"])
                                    ->all(), $masters);
                            }

                        }

                    } else {
                        $leaderLocations = array_values(array_unique(array_map(function($e){
                            $locatin = explode($e['location'], ",");
                            if(count($locatin) === 3){
                                unset($locatin[2]);
                            }
                            $locatin = join($locatin, ",");
                            return $locatin;
                        }, $leaders)));

                        foreach ($leaderLocations as $location){
                            $tmpMatchPeoples = clone $matchMembers;
                            $masters = array_merge($tmpMatchPeoples
                                ->andWhere(["like", "location", "%$location%"])
                                ->all(), $masters);
                        }
                    }
                }
                $members = array_values(array_filter($members, function($e) use ($masters){
                    foreach ($masters as $me){
                        if($me['id'] == $e['id']){
                            return false;
                        }
                    }
                    return true;
                }));


            }elseif($projlevel === ProjectDao::$projLevelName['市州本级']){
                $organization = OrganizationDao::findOne($projorgan);

                if($organization){
                    $allMatchPeoples = $matchMembers->andWhere(["organid" => $organization->id])->all();
                }

                if($leadernum ){
                    if($leaderProjType && $leaderFilternum){
                        $peoples = (new \yii\db\Query())
                            ->from("peopleproject")
                            ->innerJoin("project", "peopleproject.projid = project.id")
                            ->where(["project.projtype" => $leaderProjType])
                            ->andWhere(["project.projorgan" => $organization->id])
                            ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_GROUP_LEADER])
                            ->groupBy("peopleproject.pid")
                            ->having([">=", "pidnum", $leaderFilternum])
                            ->select(["count(*) as pidnum", "peopleproject.pid"])
                            ->all();

                        if(count($peoples) > 0){
                            $tmpLeaderIds = array_map(function($e){
                                return $e['pid'];
                            }, $peoples);

                            $tmpMatchPeoples = clone $matchMembers;
                            $leaders = $tmpMatchPeoples
                                ->andWhere(["in", "id", $tmpLeaderIds])
                                ->asArray()
                                ->all();
                        }

                    } else {
                        $tmpMatchPeoples = clone $matchMembers;
                        $leaders = $tmpMatchPeoples
                            ->limit($leadernum)
                            ->asArray()
                            ->all();
                    }
                }
                $members = array_values(array_filter($allMatchPeoples, function($e) use ($leaders){
                    foreach ($leaders as $le){
                        if($le['id'] == $e['id']){
                            return false;
                        }
                    }
                    return true;
                }));

                if($masternum){
                    if($masterProjType && $masterFilternum){
                        $peoples = (new \yii\db\Query())
                            ->from("peopleproject")
                            ->innerJoin("project", "peopleproject.projid = project.id")
                            ->where(["project.projtype" => $masterProjType])
                            ->andWhere(["project.projorgan" => $organization->id])
                            ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_MASTER])
                            ->groupBy("peopleproject.pid")
                            ->having([">=", "pidnum", $masterFilternum])
                            ->select(["count(*) as pidnum", "peopleproject.pid"])
                            ->all();

                        if(count($peoples) > 0){
                            $tmpMasterIds = array_map(function($e){
                                return $e['pid'];
                            }, $peoples);

                            $leaderLocations = array_values(array_unique(array_map(function($e){
                                $locatin = explode($e['location'], ",");
                                if(count($locatin) === 3){
                                    unset($locatin[2]);
                                }
                                $locatin = join($locatin, ",");
                                return $locatin;
                            }, $leaders)));

                            foreach ($leaderLocations as $location){
                                $tmpMatchPeoples = clone $matchMembers;
                                $masters = array_merge($tmpMatchPeoples
                                    ->andWhere(["in", "id", $tmpMasterIds])
                                    ->andWhere(["like", "location", "%$location%"])
                                    ->all(), $masters);
                            }
                        }

                    } else {
                        $leaderLocations = array_values(array_unique(array_map(function($e){
                            $locatin = explode($e['location'], ",");
                            if(count($locatin) === 3){
                                unset($locatin[2]);
                            }
                            $locatin = join($locatin, ",");
                            return $locatin;
                        }, $leaders)));

                        foreach ($leaderLocations as $location){
                            $tmpMatchPeoples = clone $matchMembers;
                            $masters = array_merge($tmpMatchPeoples
                                ->andWhere(["like", "location", "%$location%"])
                                ->all(), $masters);
                        }
                    }
                }
                $members = array_values(array_filter($members, function($e) use ($masters){
                    foreach ($masters as $me){
                        if($me['id'] == $e['id']){
                            return false;
                        }
                    }
                    return true;
                }));


            }elseif($projlevel === ProjectDao::$projLevelName['省厅统一组织']){
                if(!$location){
                    return $this->outputJson('',
                        ErrorDict::getError(ErrorDict::G_PARAM, '项目实施地未选择!')
                    );
                }
                $locatin = explode($location, ",");
                if(count($locatin) === 3){
                    unset($locatin[2]);
                }
                $locatin = join($locatin, ",");

                $tmp = clone $matchMembers;
                $allMatchPeoples = $tmp->andWhere(['organid' => $projorgan])->asArray()->all();
                if(count($allMatchPeoples) < $leadernum + $masternum + $auditornum){
                    $tmp = clone $matchMembers;
                    $tmpPeoples = $tmp
                        ->andWhere(["not like", "location", "%$locatin%"])
                        ->asArray()
                        ->all();
                    $allMatchPeoples = array_merge($allMatchPeoples, $tmpPeoples);
                }

                if($leadernum ){
                    if($leaderProjType && $leaderFilternum){
                        $peoples = (new \yii\db\Query())
                            ->from("peopleproject")
                            ->innerJoin("project", "peopleproject.projid = project.id")
                            ->where(["project.projtype" => $leaderProjType])
                            ->andWhere(["project.projorgan" => $projorgan])
                            ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_GROUP_LEADER])
                            ->groupBy("peopleproject.pid")
                            ->having([">=", "pidnum", $leaderFilternum])
                            ->select(["count(*) as pidnum", "peopleproject.pid"])
                            ->all();

                        if(count($peoples) > 0){
                            $tmpLeaderIds = array_map(function($e){
                                return $e['pid'];
                            }, $peoples);

                            $tmpMatchPeoples = clone $matchMembers;
                            $leaders = $tmpMatchPeoples
                                ->andWhere(["in", "id", $tmpLeaderIds])
                                ->asArray()
                                ->all();
                        }

                    } else {
                        $tmpMatchPeoples = clone $matchMembers;
                        $leaders = $tmpMatchPeoples
                            ->limit($leadernum)
                            ->asArray()
                            ->all();
                    }
                }
                $members = array_values(array_filter($allMatchPeoples, function($e) use ($leaders){
                    foreach ($leaders as $le){
                        if($le['id'] == $e['id']){
                            return false;
                        }
                    }
                    return true;
                }));

                if($masternum){
                    if($masterProjType && $masterFilternum){
                        $peoples = (new \yii\db\Query())
                            ->from("peopleproject")
                            ->innerJoin("project", "peopleproject.projid = project.id")
                            ->where(["project.projtype" => $masterProjType])
                            ->andWhere(["project.projorgan" => $projorgan])
                            ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_MASTER])
                            ->groupBy("peopleproject.pid")
                            ->having([">=", "pidnum", $masterFilternum])
                            ->select(["count(*) as pidnum", "peopleproject.pid"])
                            ->all();

                        if(count($peoples) > 0){
                            $tmpMasterIds = array_map(function($e){
                                return $e['pid'];
                            }, $peoples);


                            $leaderLocations = array_values(array_unique(array_map(function($e){
                                $locatin = explode($e['location'], ",");
                                if(count($locatin) === 3){
                                    unset($locatin[2]);
                                }
                                $locatin = join($locatin, ",");
                                return $locatin;
                            }, $leaders)));

                            foreach ($leaderLocations as $location){
                                $tmpMatchPeoples = clone $matchMembers;
                                $masters = array_merge($tmpMatchPeoples
                                    ->andWhere(["in", "id", $tmpMasterIds])
                                    ->andWhere(["like", "location", "%$location%"])
                                    ->all(), $masters);
                            }
                        }

                    } else {
                        $leaderLocations = array_values(array_unique(array_map(function($e){
                            $locatin = explode($e['location'], ",");
                            if(count($locatin) === 3){
                                unset($locatin[2]);
                            }
                            $locatin = join($locatin, ",");
                            return $locatin;
                        }, $leaders)));

                        foreach ($leaderLocations as $location){
                            $tmpMatchPeoples = clone $matchMembers;
                            $masters = array_merge($tmpMatchPeoples
                                ->andWhere(["like", "location", "%$location%"])
                                ->all(), $masters);
                        }
                    }
                }
                $members = array_values(array_filter($members, function($e) use ($masters){
                    foreach ($masters as $me){
                        if($me['id'] == $e['id']){
                            return false;
                        }
                    }
                    return true;
                }));

            }elseif($projlevel === ProjectDao::$projLevelName['市州统一组织']){
                if(!$location){
                    return $this->outputJson('',
                        ErrorDict::getError(ErrorDict::G_PARAM, '项目实施地未选择!')
                    );
                }
                $locatin = explode($location, ",");
                if(count($locatin) === 3){
                    unset($locatin[2]);
                }
                $locatin = join($locatin, ",");

                $tmp = clone $matchMembers;
                $allMatchPeoples = $tmp->andWhere(['organid' => $projorgan])->asArray()->all();
                if(count($allMatchPeoples) < $leadernum + $masternum + $auditornum){
                    $tmp = clone $matchMembers;
                    $tmpPeoples = $tmp
                        ->andWhere(["not like", "location", "%$locatin%"])
                        ->asArray()
                        ->all();
                    $allMatchPeoples = array_merge($allMatchPeoples, $tmpPeoples);
                }

                if($leadernum ){
                    if($leaderProjType && $leaderFilternum){
                        $peoples = (new \yii\db\Query())
                            ->from("peopleproject")
                            ->innerJoin("project", "peopleproject.projid = project.id")
                            ->where(["project.projtype" => $leaderProjType])
                            ->andWhere(["project.projorgan" => $projorgan])
                            ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_GROUP_LEADER])
                            ->groupBy("peopleproject.pid")
                            ->having([">=", "pidnum", $leaderFilternum])
                            ->select(["count(*) as pidnum", "peopleproject.pid"])
                            ->all();

                        if(count($peoples) > 0){
                            $tmpLeaderIds = array_map(function($e){
                                return $e['pid'];
                            }, $peoples);

                            $tmpMatchPeoples = clone $matchMembers;
                            $leaders = $tmpMatchPeoples
                                ->andWhere(["in", "id", $tmpLeaderIds])
                                ->asArray()
                                ->all();
                        }

                    } else {
                        $tmpMatchPeoples = clone $matchMembers;
                        $leaders = $tmpMatchPeoples
                            ->limit($leadernum)
                            ->asArray()
                            ->all();
                    }
                }
                $members = array_values(array_filter($allMatchPeoples, function($e) use ($leaders){
                    foreach ($leaders as $le){
                        if($le['id'] == $e['id']){
                            return false;
                        }
                    }
                    return true;
                }));

                if($masternum){
                    if($masterProjType && $masterFilternum){
                        $peoples = (new \yii\db\Query())
                            ->from("peopleproject")
                            ->innerJoin("project", "peopleproject.projid = project.id")
                            ->where(["project.projtype" => $masterProjType])
                            ->andWhere(["project.projorgan" => $projorgan])
                            ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_MASTER])
                            ->groupBy("peopleproject.pid")
                            ->having([">=", "pidnum", $masterFilternum])
                            ->select(["count(*) as pidnum", "peopleproject.pid"])
                            ->all();

                        if(count($peoples) > 0){
                            $tmpMasterIds = array_map(function($e){
                                return $e['pid'];
                            }, $peoples);


                            $leaderLocations = array_values(array_unique(array_map(function($e){
                                $locatin = explode($e['location'], ",");
                                if(count($locatin) === 3){
                                    unset($locatin[2]);
                                }
                                $locatin = join($locatin, ",");
                                return $locatin;
                            }, $leaders)));

                            foreach ($leaderLocations as $location){
                                $tmpMatchPeoples = clone $matchMembers;
                                $masters = array_merge($tmpMatchPeoples
                                    ->andWhere(["in", "id", $tmpMasterIds])
                                    ->andWhere(["like", "location", "%$location%"])
                                    ->all(), $masters);
                            }
                        }

                    } else {
                        $leaderLocations = array_values(array_unique(array_map(function($e){
                            $locatin = explode($e['location'], ",");
                            if(count($locatin) === 3){
                                unset($locatin[2]);
                            }
                            $locatin = join($locatin, ",");
                            return $locatin;
                        }, $leaders)));

                        foreach ($leaderLocations as $location){
                            $tmpMatchPeoples = clone $matchMembers;
                            $masters = array_merge($tmpMatchPeoples
                                ->andWhere(["like", "location", "%$location%"])
                                ->all(), $masters);
                        }
                    }
                }
                $members = array_values(array_filter($members, function($e) use ($masters){
                    foreach ($masters as $me){
                        if($me['id'] == $e['id']){
                            return false;
                        }
                    }
                    return true;
                }));

            }elseif($projlevel === ProjectDao::$projLevelName['县级']){
                $tmp = clone $matchMembers;
                $allMatchPeoples = $tmp->andWhere(['organid' => $projorgan])->asArray()->all();

                if($leadernum ){
                    if($leaderProjType && $leaderFilternum){
                        $peoples = (new \yii\db\Query())
                            ->from("peopleproject")
                            ->innerJoin("project", "peopleproject.projid = project.id")
                            ->where(["project.projtype" => $leaderProjType])
                            ->andWhere(["project.projorgan" => $projorgan])
                            ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_GROUP_LEADER])
                            ->groupBy("peopleproject.pid")
                            ->having([">=", "pidnum", $leaderFilternum])
                            ->select(["count(*) as pidnum", "peopleproject.pid"])
                            ->all();

                        if(count($peoples) > 0){
                            $tmpLeaderIds = array_map(function($e){
                                return $e['pid'];
                            }, $peoples);

                            $tmpMatchPeoples = clone $matchMembers;
                            $leaders = $tmpMatchPeoples
                                ->andWhere(["in", "id", $tmpLeaderIds])
                                ->asArray()
                                ->all();
                        }

                    } else {
                        $tmpMatchPeoples = clone $matchMembers;
                        $leaders = $tmpMatchPeoples
                            ->limit($leadernum)
                            ->asArray()
                            ->all();
                    }
                }
                $members = array_values(array_filter($allMatchPeoples, function($e) use ($leaders){
                    foreach ($leaders as $le){
                        if($le['id'] == $e['id']){
                            return false;
                        }
                    }
                    return true;
                }));

                if($masternum){
                    if($masterProjType && $masterFilternum){
                        $peoples = (new \yii\db\Query())
                            ->from("peopleproject")
                            ->innerJoin("project", "peopleproject.projid = project.id")
                            ->where(["project.projtype" => $masterProjType])
                            ->andWhere(["project.projorgan" => $projorgan])
                            ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_MASTER])
                            ->groupBy("peopleproject.pid")
                            ->having([">=", "pidnum", $masterFilternum])
                            ->select(["count(*) as pidnum", "peopleproject.pid"])
                            ->all();

                        if(count($peoples) > 0){
                            $tmpMasterIds = array_map(function($e){
                                return $e['pid'];
                            }, $peoples);

                            $leaderLocations = array_values(array_unique(array_map(function($e){
                                $locatin = explode($e['location'], ",");
                                if(count($locatin) === 3){
                                    unset($locatin[2]);
                                }
                                $locatin = join($locatin, ",");
                                return $locatin;
                            }, $leaders)));

                            foreach ($leaderLocations as $location){
                                $tmpMatchPeoples = clone $matchMembers;
                                $masters = array_merge($tmpMatchPeoples
                                    ->andWhere(["in", "id", $tmpMasterIds])
                                    ->andWhere(["like", "location", "%$location%"])
                                    ->all(), $masters);
                            }
                        }

                    } else {
                        $leaderLocations = array_values(array_unique(array_map(function($e){
                            $locatin = explode($e['location'], ",");
                            if(count($locatin) === 3){
                                unset($locatin[2]);
                            }
                            $locatin = join($locatin, ",");
                            return $locatin;
                        }, $leaders)));

                        foreach ($leaderLocations as $location){
                            $tmpMatchPeoples = clone $matchMembers;
                            $masters = array_merge($tmpMatchPeoples
                                ->andWhere(["like", "location", "%$location%"])
                                ->all(), $masters);
                        }
                    }
                }
                $members = array_values(array_filter($members, function($e) use ($masters){
                    foreach ($masters as $me){
                        if($me['id'] !== $e['id']){
                            return false;
                        }
                    }
                    return true;
                }));

            }

            $group = $this->distribute($group, $leaders, $masters, $members, $auditornum, $masternum);

            foreach ($group as $key => $e ){
                foreach ($e as $user){
                    $pepProject = new PeopleProjectDao();
                    $pepProject->pid = $user['user']['id'];
                    $pepProject->groupid = $key;
                    $pepProject->roletype = $user['role'];
                    $pepProject->islock = $pepProject::NOT_LOCK;
                    $pepProject->projid = $projectId;
                    $pepProject->save();
                    $person = UserDao::findOne($user['user']['id']);
                    $person->isaudit = UserDao::IS_AUDIT;
                    $person->isjob = UserDao::IS_JOB;
                    $person->save();
                }
            }

            $transaction->commit();
        } catch(\Exception $e){
            Log::fatal(printf("创建错误！%s, %s", $e->getMessage(), $e->getTraceAsString()));
            $transaction->rollBack();
            return $this->outputJson('',
                ErrorDict::getError(ErrorDict::ERR_INTERNAL, "创建项目内部错误！")
            );
        }


        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
        return $ret;
    }





    private function distribute($group, $leaders, $masters, $members, $membersNum, $masterNum) {
        $tmpGroup = $group;
        foreach ($tmpGroup as $key => $e){
            if(!empty($leaders)){
                $group[$key][] = [
                    'user' => array_pop($leaders),
                    'role' => PeopleProjectDao::ROLE_TYPE_GROUP_LEADER
                ];
            }else{
                break;
            }

        }

        $tmpGroup = $group;

        while ($masterNum > 0) {
            foreach ($tmpGroup as $key => $e){
                if(!empty($masters)){
                    if(isset($group[$key][0])){
                        $locatin = explode($group[$key][0]['location'], ",");
                        if(count($locatin) === 3){
                            unset($locatin[2]);
                        }
                        $locatin = join($locatin, ",");
                        $tmp = array_pop(array_values(array_filter($masters, function($e) use ($locatin){
                            return strstr($e['location'], $locatin);
                        })));
                        if($tmp){
                            $group[$key][] = [
                                'user' => $tmp,
                                'role' => PeopleProjectDao::ROLE_TYPE_MASTER
                            ];
                            $masters = array_values(array_filter($masters ,function($e) use ($tmp){
                                return $e['id'] !== $tmp['id'];
                            }));
                            $masterNum--;
                        }
                    }else{
                        $masters = array_pop($masters);
                        $group[$key][] = $masters;
                        $masterNum--;
                    }
                }else{
                    $masterNum = 0;
                    break;
                }
            }
        }

        $members = array_merge($masters, $members);
        $membersIdMap = [];
        foreach ($members as $e){
            $membersIdMap[$e['id']] = $e;
        }
        $tmpGroup = $group;
        while ($membersNum > 0){
            foreach ($tmpGroup as $key => $value){
                if(!empty($members)){
                    $girls = array_filter($group[$key], function($e){
                        return $e['user']['sex'] === UserDao::$sexName['女'];
                    });

                    if(count($girls) > 0 && count($girls) % 2 !== 0){
                        $girlMembers = array_filter($members, function($e){
                            return $e['user']['sex'] === UserDao::$sexName['女'];
                        });
                        $tmp = array_pop($girlMembers);
                        if(!$tmp){
                            $group[$key] = array_values(array_filter($group[$key], function($e) use($tmp){
                                return $e['user']['id'] !== $tmp['user']['id'];
                            }));
                            $membersNum++;
                            continue;
                        }
                        $group[$key][] = [
                            'user' => $tmp,
                            'role' => PeopleProjectDao::ROLE_TYPE_GROUPER
                        ];
                        $members = array_values(array_filter($members ,function($e) use ($tmp){
                            if(!isset($tmp['id']) || !isset($e['id'])){
                                return false;
                            }
                            return $e['id'] !== $tmp['id'];
                        }));
                        $membersNum--;
                    }else{
                        if($membersNum >= count($group) ){
                            $tmp = array_pop($members);
                        }else{
                            $menMembers = array_filter($members, function($e){
                                return $e['user']['sex'] === UserDao::$sexName['男'];
                            });
                            $tmp = array_pop($menMembers);
                        }

                        $group[$key][] = [
                            'user' => $tmp,
                            'role' => PeopleProjectDao::ROLE_TYPE_GROUPER
                        ];
                        $members = array_values(array_filter($members ,function($e) use ($tmp){
                            if(!isset($tmp['id']) || !isset($e['id'])){
                                return false;
                            }
                            return $e['id'] !== $tmp['id'];
                        }));
                        $membersNum--;
                    }
                } else {
                    $membersNum = 0;
                    break;
                }
            }
        }
        return $group;

    }

    /**
     * 删除项目接口（批量）
     *
     * @return array
     * @throws Exception
     * @throws \Throwable
     */
    public function actionDelete()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $idArr = $this->getParam('id', '');
        if (!$idArr) {
            return $this->outputJson('',
                ErrorDict::getError(ErrorDict::G_PARAM, '参数格式不对！')
            );
        }

        $service = new ProjectService();
        if(!$service->deletePro($idArr)){
            return $this->outputJson('',
                ErrorDict::getError(ErrorDict::ERR_INTERNAL)
            );
        }

        return $this->outputJson('',
            ErrorDict::getError(ErrorDict::SUCCESS)
        );
    }

    /**
     * 编辑项目接口
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function actionUpdate()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'name' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'plantime' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projyear' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projdesc' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projorgan' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projtype' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projlevel' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'leadorgan' => array (
                'require' => true,
                'checker' => 'noCheck',
            )

        );
        if (!$this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $name = $this->getParam('name', '');
        $plantime = intval($this->getParam('plantime', 0));
        $projyear = intval($this->getParam('projyear', 0));
        $projorgan = intval($this->getParam('projorgan', 0));
        $projdesc = $this->getParam('projdesc', '');
        $projtype = $this->getParam('projtype', '');
        $projlevel = intval($this->getParam('projlevel', 0));
        $leadorganId = intval($this->getParam('leadorgan', 0));

        $pro = new ProjectDao();
        $instance = $pro::findOne($id);
        if(!$instance) {
            return $this->outputJson(
                '',
                ErrorDict::getError(ErrorDict::G_PARAM)
            );
        }
        $instance->name = $name;
        $instance->plantime = $plantime;
        $instance->projyear = $projyear;
        $instance->projorgan = $projorgan;
        $instance->projdesc = $projdesc;
        $instance->projtype = json_encode($projtype, JSON_UNESCAPED_UNICODE);
        $instance->projlevel = $projlevel;
        $instance->leadorgan = $leadorganId;
        $instance->save();


        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
        return $ret;
    }

    /**
     * 项目列表接口
     *
     * @return array
     */
    public function actionList() {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'projyear' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'projlevel' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
            'medium' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
            'internal' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
            'projstage' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
            'query' => array (
                'require' => false,
                'checker' => 'noCheck',
            ),
            'length' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
            'page' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $projyear = $this->getParam('projyear', '');
        $projlevel = intval($this->getParam('projlevel', 0));
        $medium = intval($this->getParam('medium', 0));
        $internal = intval($this->getParam('internal', 0));
        $projstage = intval($this->getParam('projstage', 0));
        $query = $this->getParam('query', '');
        $length = intval($this->getParam('length', 0));
        $page = intval($this->getParam('page', 0));

        $prj = new ProjectDao();
        $con = $prj::find();

        if ($projyear) {
            //if (date('Y', strtotime($projyear)) !== $projyear) {
            if ( !is_numeric( $projyear) ) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "项目年份 输入格式不对！应为年份格式!")
                );
            }
            $con = $con->andwhere(['projyear' => $projyear]);
        }

        if ($projlevel) {
            if (!in_array($projlevel, [1, 2, 3, 4])) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "项目层级 输入格式不对！")
                );
            }
            $con = $con->andwhere(['projlevel' => $projlevel]);
        }

        if ($medium) {
            if (!in_array($medium, [1, 2, 3, 4, 5])) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "是否是中介机构 输入格式不对！")
                );
            }
            if($medium == 1){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['中介机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();

                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);

                if(count($projs) !== 0){
                    $con = $con->andWhere(['not in', 'id', $projs]);
                }
            }else if($medium == 2){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['中介机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);

                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);

                $projs = array_diff($projs, $rews);

                $con = $con->andWhere(['in', 'id', $projs]);

            }else if($medium == 3){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['中介机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);

                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->andWhere(['in', 'projid', $projs])
                    ->andWhere(['status' => 0])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);

                $con = $con->andWhere(['in', 'id', $rews]);


            }else if($medium == 4){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['中介机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);
                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->andWhere(['in', 'projid', $projs])
                    ->andWhere(['status' => 1])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);

                $con = $con->andWhere(['in', 'id', $rews]);

            }else if($medium == 5){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['中介机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);
                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->andWhere(['in', 'projid', $projs])
                    ->andWhere(['status' => 2])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);

                $con = $con->andWhere(['in', 'id', $rews]);
            }


        }

        if ($internal) {
            if (!in_array($internal, [1, 2, 3, 4, 5])) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "是否是中介机构 输入格式不对！")
                );
            }
            if($internal == 1){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['内审机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);

                if(count($projs) !== 0){
                    $con = $con->andWhere(['not in', 'id', $projs]);
                }
            }else if($internal == 2){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['内审机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);
                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);

                $projs = array_diff($projs, $rews);

                $con = $con->andWhere(['in', 'id', $projs]);
            }else if($internal == 3){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['内审机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);
                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->andWhere(['in', 'projid', $projs])
                    ->andWhere(['status' => 0])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);

                $con = $con->andWhere(['in', 'id', $rews]);
            }else if($internal == 4){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['内审机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);
                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->andWhere(['in', 'projid', $projs])
                    ->andWhere(['status' => 1])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);

                $con = $con->andWhere(['in', 'id', $rews]);
            }else if($internal == 5){
                $projs = (new \yii\db\Query())
                    ->from('peopleproject')
                    ->innerJoin('people', 'peopleproject.pid = people.id')
                    ->andWhere(['people.type' => UserDao::$typeToName['内审机构']])
                    ->select('peopleproject.projid')
                    ->groupBy('peopleproject.projid')
                    ->all();
                $projs = array_map(function($e){
                    return $e['projid'];
                }, $projs);

                $rews = ReviewDao::find()
                    ->andWhere(['ptype' => 1])
                    ->andWhere(['in', 'projid', $projs])
                    ->andWhere(['status' => 2])
                    ->groupBy('projid')
                    ->select('projid')
                    ->all();
                $rews = array_map(function($e){
                    return $e['projid'];
                }, $rews);
                $con = $con->andWhere(['in', 'id', $rews]);
            }

        }

        if ($projstage) {
            if (!in_array($projstage, [1, 2, 3, 4, 5])) {
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::G_PARAM, "projstage 输入格式不对！")
                );
            }
            $con = $con->andwhere(['status' => $projstage]);
        }


        if ($query) {
            $con = $con->andwhere(['or', ['like', 'projectnum', $query], ['like', 'name', $query]]);
        }
        if (!$length) {
            $length = 20;
        }
        if (!$page) {
            $page = 1;
        }
        $countCon = clone $con;
        $list = $con->orderBy(['id' => SORT_DESC])->limit($length)->offset(($page - 1) * $length)->asArray()->all();
        $rewService = new ReviewService();
        $pro = new ProjectDao();
        $list = array_map(function($e )use ($rewService, $pro){
            $e['medium'] = $rewService->getMediumStatus($e['id']);
            $e['internal'] = $rewService->getInternalStatus($e['id']);

            switch ($e['status']){
                case ProjectDao::$statusToName['未开始']:
                    $e['operate'] = 1;
                    break;
                case ProjectDao::$statusToName['计划阶段']:
                    $e['operate'] = 2;
                    break;
                case ProjectDao::$statusToName['实施阶段']:
                    $e['operate'] = 3;
                    break;
                case ProjectDao::$statusToName['审理阶段']:
                    $e['operate'] = 4;
                    break;
                default:
                    $e['operate'] = 0;
                    break;
            }


            return $e;
        }, $list);
        $total = $countCon->count();

        return $this->outputJson([
            'list' => $list,
            'total' => $total,
        ], ErrorDict::SUCCESS);
    }

    /**
     * 新建页&编辑页配置接口
     *
     * @return array
     */
    public function actionSelectconfig() {
        $data = [];
        $organizationService = new OrganizationService();
        $organList = $organizationService->getDeparts();
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $data['organlist'] = $organList;
        $data['type'] = Yii::$app->params['project_type'];
        $ret = $this->outputJson($data, $error);
        return $ret;
    }

    //修改角色
    public function actionUpdaterole()
    {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'pid' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'role' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $pid = $this->getParam('pid', '');
        $role = $this->getParam('role', '');
        $tr = Yii::$app->get('db')->beginTransaction();
        try {
            //先删除，后添加
            $roleDao = new RoleDao();
            $roleDao->deletePeopleRole($pid);
            $roleIdArr = explode(',', $role);
            foreach ($roleIdArr as $rid) {
                $roleDao->addPeopleRole($pid, $rid);
            }
            $tr->commit();
        }catch (Exception $e) {
            $tr->rollBack();
            Log::addLogNode('update role exception', serialize($e->errorInfo));
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }
        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
        return $ret;
    }

    /**
     * 项目编辑页详情接口
     *
     * @return array
     */
    public function actionEditinfo() {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $project = new ProjectDao();
        $data = $project->queryByID($id);
        if(!$data){
            $error = ErrorDict::getError(ErrorDict::G_PARAM);
            $ret = $this->outputJson("", $error);
            return $ret;
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($data, $error);
        return $ret;
    }


    /**
     * 项目详情接口一列表部分
     *
     * @return array
     */
    public function actionInfolist() {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $projectDao = new ProjectDao();
        $data = $projectDao->queryByID($id);
        if(!$data){
            $error = ErrorDict::getError(ErrorDict::G_PARAM);
            $ret = $this->outputJson("", $error);
            return $ret;
        }

        $rew = new ReviewService();

        $ret['auditgroup'] = [
            'medium' => $rew->getMediumStatus($id),
            'internal' => $rew->getInternalStatus($id), //需要审核模块
        ];

        $auditGroups = AuditGroupDao::find()
            ->where(['pid' => $id])
            ->asArray()
            ->all();
        foreach ($auditGroups as $e){
            $tmp = [
                'id' => $e['id'],
                'status' => $e['status'],
            ];

            if(in_array($e['status'], [AuditGroupDao::$statusToName['无'], AuditGroupDao::$statusToName['应进点'], AuditGroupDao::$statusToName['该进点而未进点']])){
                $tmp['operate'] = 1;
            }else if(in_array($e['status'], [AuditGroupDao::$statusToName['已进点'], AuditGroupDao::$statusToName['该结束未结束']])){
                $tmp['operate'] = 2;

            }else if(in_array($e['status'], [AuditGroupDao::$statusToName['审理中'], AuditGroupDao::$statusToName['实施结束']])){
                $tmp['operate'] = 3;

            }else if(in_array($e['status'], [AuditGroupDao::$statusToName['审理中']])){
                $tmp['operate'] = 4;
            }else if(in_array($e['status'], [AuditGroupDao::$statusToName['审理结束']])){
                $tmp['operate'] = 5;
            }else if(in_array($e['status'], [AuditGroupDao::$statusToName['报告中']])){
                $tmp['operate'] = 6;
            }


            $peoples = (new \yii\db\Query())
                ->from('peopleproject')
                ->innerJoin('people', 'peopleproject.pid = people.id')
                ->select('people.id, people.pid, people.name, people.sex, people.type, peopleproject.roletype, peopleproject.roletype as role, people.level, people.location, peopleproject.islock')
                ->where(['peopleproject.groupid' => $e['id']])
                ->andWhere(['peopleproject.projid' => $id])
                ->all();

            foreach ($peoples as $p){
                $tmp['group'][] = $p;
            }
            $ret['auditgroup']['list'][] = $tmp;
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($ret, $error);
        return $ret;
    }

    /**
     * 项目详情接口一无列表部分
     *
     * @return array
     */
    public function actionInfo() {
        $this->defineMethod = 'GET';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $projectDao = new ProjectDao();
        $data = $projectDao->queryByID($id);
        if(!$data){
            $error = ErrorDict::getError(ErrorDict::G_PARAM);
            $ret = $this->outputJson("", $error);
            return $ret;
        }
        $ret['head'] = [
            'projectnum' => $data['projectnum'],
            'projyear' => $data['projyear'],
            'projtype' => $data['projtype'],
            'projlevel' => $projectDao->getProjectLevelMsg($data['projlevel']),
            'leadernum' => $data['leadernum'],
            'auditornum' => $data['auditornum'],
            'masternum' => $data['masternum'],
            'plantime' => $data['plantime'],
            'operate' => $projectDao->getOperator($data['status']),
        ];
        $orgDao = new OrganizationDao();
        $org = $orgDao::find()
            ->where(['id' => $data['leadorgan']])->one();
        $ret['head']['leadorgan'] = $org['name'] ?? "";


        $org = $orgDao::find()
            ->where(['id' => $data['projorgan']])->one();
        $ret['head']['projorgan'] = $org['name'] ?? "";

        $ret['basic'] = [
            'projdesc' => $data['projdesc'],
            'projstart' => $data['projstart'],
            'projauditcontent' => $data['projauditcontent'],
            'projectname' => $data['name'],
            'projectstatus' => $data['status'],
        ];

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson($ret, $error);
        return $ret;
    }



    /**
     * 变更审计信息接口
     *
     * @return array
     */
    public function actionAuditinfo() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'projstart' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'projauditcontent' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $projstart = intval($this->getParam('projstart', ''));
        $projstart = date('Y-m-d H:i:s', $projstart);
        $projauditcontent = $this->getParam('projauditcontent', '');


        $projectDao = new ProjectDao();
        $data = $projectDao->queryByID($id);
        if(!$data){
            $error = ErrorDict::getError(ErrorDict::G_PARAM);
            $ret = $this->outputJson("", $error);
            return $ret;
        }
        $projectDao->updateAuditContent($id, $projstart, $projauditcontent);

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        $ret = $this->outputJson('', $error);
        return $ret;
    }



    /**
     * 项目列表查询下拉选接口
     *
     * @return array
     */
    public function actionListselect() {
        $years = ProjectDao::find()->groupBy("projyear")->asArray()->all();
        $years = array_map(function($e){
            return $e['projyear'];
        }, $years);


        $data = [
            'projyear' => $years, //项目年度
            'projlevel' => [
                [ '1' => '省厅统一组织'],
                [ '2' => '市州本级'],
                [ '3' => '市州统一组织' ],
                [ '4' => '县级'],
                [ '5' => '省厅本级'],
            ],
            'medium' => [
                [ '1' => '无需审核'],
                [ '2' => '待提审'],
                [ '3' => '待审核'],
                [ '4' => '审核通过'],
                [ '5' => '审核未通过'],
            ],
            'internal' => [
                [ '1' => "无需审核"],
                [ '2' => '待提审'],
                [ '3' => '审核通过'],
                [ '4' => '审核通过'],
                [ '5' => '审核未通过'],
            ],
            'projstage' => [
                [ '1' => '计划阶段'],
                [ '2' => '实施阶段'],
                [ '3' => '审理阶段'],
                [ '4' => '报告阶段'],
                [ '5' => '项目结束']
            ]
        ];

        return $this->outputJson($data, ErrorDict::getError(ErrorDict::SUCCESS));

    }


    /**
     * 项目状态变更接口
     *
     */
    public function actionUpdatestatus() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'operate' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'num' => array (
                'require' => false,
                'checker' => 'isNumber',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));
        $operate = intval($this->getParam('operate', 0));
        $num = intval($this->getParam('num', 0));

        if($operate == 3 && $num == 0){
            return $this->outputJson('',
                ErrorDict::getError(ErrorDict::G_PARAM, '预审理人数不对！')
            );
        }

        if(!in_array($operate, array_keys(ProjectDao::$operatorStatus))) {
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, '状态不正确！'));
        }

        //todo 注意！可能后续会加上权限相关的东西

        $transaction = ProjectDao::getDb()->beginTransaction();

        try{
            $pro = ProjectDao::findOne($id);
            switch ($operate) {
                case ProjectDao::OPERATOR_STATUS_SURE:
                    $pro->status = ProjectDao::$statusToName['计划阶段'];
                    $pro->save();
                    $pepProGroups = PeopleProjectDao::find()
                        ->where(["projid" => $id])
                        ->groupBy('groupid')
                        ->all();
                    $groupIds = array_map(
                        function($e)
                        {
                            return $e->groupid;
                        },
                        $pepProGroups
                    );

                    $groups = AuditGroupDao::findAll($groupIds);
                    foreach ($groups as $e){
                        $e->status = AuditGroupDao::$statusToName['应进点'];
                        $e->save();
                    }
                    $transaction->commit();
                    break;
                case ProjectDao::OPERATOR_STATUS_START:
                    $pro->status = ProjectDao::$statusToName['实施阶段'];
                    $pro->save();

//                    $pepProGroups = PeopleProjectDao::find()
//                        ->where(["projid" => $id])
//                        ->groupBy('groupid')
//                        ->all();
//                    $groupIds = array_map(
//                        function($e)
//                        {
//                            return $e->groupid;
//                        },
//                        $pepProGroups
//                    );
//
//                    $groups = AuditGroupDao::findAll($groupIds);
//                    foreach ($groups as $e){
//                        $e->status = AuditGroupDao::$statusToName['应进点'];
//                        $e->save();
//                    }
                    $transaction->commit();
                    break;
                case ProjectDao::OPERATOR_STATUS_AUDIT:
                    $pro->status = ProjectDao::$statusToName['审理阶段'];
                    $pro->jugenum = $num;
                    $pro->save();

                    $pepProGroups = PeopleProjectDao::find()
                        ->where(["projid" => $id])
                        ->groupBy('groupid')
                        ->all();
                    $groupIds = array_map(
                        function($e)
                        {
                            return $e->groupid;
                        },
                        $pepProGroups
                    );

                    $groups = AuditGroupDao::findAll($groupIds);
//                    foreach ($groups as $e){
//                        $e->status = AuditGroupDao::$statusToName['审理中'];
//                        $e->save();
//                    }

                    $orgIds = (new OrganizationService)->getSubordinateIds($pro->projorgan);

                    $idGroupMap = [];
                    $users = UserDao::find()
                        ->where(['isjob' => UserDao::IS_NOT_JOB])
                        ->andWhere(['in', 'organid', $orgIds])
                        ->asArray()
                        ->all();
                    foreach ($users as $e){
                        $idGroupMap[] = [
                            "id" => $e['id'],
                            "group" => 0
                        ];
                    }
                    $userGroups = PeopleProjectDao::find()
                        ->where(["projid" => $id])
                        ->groupBy('pid')
                        ->asArray()
                        ->all();
                    foreach ($userGroups as $e){
                        $idGroupMap[] = [
                            "id" => $e['pid'],
                            "group" => $e['groupid']
                        ];
                    }

                    $flag = count($idGroupMap);

                    while ($flag > 0){
                        foreach ($groups as $e){
                            foreach ($idGroupMap as $key => $v){
                                if($v['group'] !== $e['id']){
                                    $judge = new JugeProjectDao();
                                    $judge->projid = $id;
                                    $judge->groupid = $e['id'];
                                    $judge->pid = $v['id'];
                                    $judge->save();
                                    unset($idGroupMap[$key]);
                                    break;
                                }
                            }
                        }
                    }

                    $transaction->commit();
                    break;
                case ProjectDao::OPERATOR_STATUS_END:
                    $pro->status = ProjectDao::$statusToName['项目结束'];
                    $pro->save();

                    $pepProGroups = PeopleProjectDao::find()
                        ->where(["projid" => $id])
                        ->groupBy('groupid')
                        ->all();
                    $groupIds = array_map(
                        function($e)
                        {
                            return $e->groupid;
                        },
                        $pepProGroups
                    );

                    $groups = AuditGroupDao::findAll($groupIds);
                    foreach ($groups as $e){
                        $e->status = AuditGroupDao::$statusToName['报告中'];
                        $e->save();
                    }
                    $transaction->commit();
                    break;
            }
        }catch(\Exception $e){
            $transaction->rollBack();
            Log::addLogNode('状态变更错误！', serialize($e->errorInfo));
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }


        return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));

    }

    public function actionExcel() {
        $this->defineMethod = 'GET';

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(APP_PATH."/static/projectexcel.xlsx");

        $organizationService = new OrganizationService();
        $organList = $organizationService->getDepartsByType(3);
        $departlist = [];
        foreach($organList as $org){
            if( count($org['partment']) <= 0 ){
                $departlist[] = $org['id'].':'.$org['name'];    
            }
            foreach( $org['partment'] as $pm ){
                $departlist[] = array_keys($pm)[0].':'.$org['name'].'-'.array_values($pm)[0];    
            }    
        }
        $nowdk = 0;
        foreach( $departlist as $dk=>$dv){
            $nowdk = $dk+1;
            $spreadsheet->getActiveSheet()->getCell('AA'.$nowdk)->setValue( $dv );    
        }

        //生成所有贵州省市区列表
        $districts = json_decode(Yii::$app->params['districts'],true);
        $guizhou = $districts['520000'];
        $districts_arr = [];
        foreach( $guizhou as $gk=>$gv ){
            foreach( $districts[$gk] as $sk=>$sv ) {
                $districts_arr[] = $sk.':'.$gv.'_'.$sv;    
            }
        }

        $dnowdk = 0;
        foreach( $districts_arr as $dk=>$dv){
            $dnowdk = $dk+1;
            $spreadsheet->getActiveSheet()->getCell('AB'.$dnowdk)->setValue( $dv );    
        }


        $lnowdk = 0;
        $larr = ["_1.财政类","_2.社会保险基金类","_3.企业金融类","_4.经济责任类","_5.政策跟踪"];
        foreach($larr as $lk=>$lv) {
            $lnowdk = $lk+1;
            $spreadsheet->getActiveSheet()->getCell('AC'.$lnowdk)->setValue( $lv );    
        }

        $ss = 2;
        $se = 1140;
        for($ss = 2; $ss < $se;$ss++){
            $validation = $spreadsheet->getActiveSheet()->getCell('G'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('=$AA$1:$AA$'.$nowdk);

            $validation = $spreadsheet->getActiveSheet()->getCell('F'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('=$AB$1:$AB$'.$dnowdk);

            $validation = $spreadsheet->getActiveSheet()->getCell('H'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('=$AC$1:$AC$'.$lnowdk);

            $validation = $spreadsheet->getActiveSheet()->getCell('I'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('=INDIRECT(H'.$ss.')');

            $validation = $spreadsheet->getActiveSheet()->getCell('K'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('=$AC$1:$AC$'.$lnowdk);

            $validation = $spreadsheet->getActiveSheet()->getCell('L'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('=INDIRECT(K'.$ss.')');

            $validation = $spreadsheet->getActiveSheet()->getCell('O'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('=$AC$1:$AC$'.$lnowdk);

            $validation = $spreadsheet->getActiveSheet()->getCell('P'.$ss)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Input error');
            $validation->setError('Value is not in list.');
            $validation->setPromptTitle('Pick from list');
            $validation->setPrompt('Please pick a value from the drop-down list.');
            $validation->setFormula1('=INDIRECT(O'.$ss.')');

        }

        // Redirect output to a client’s web browser (Xlsx)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="项目计划批量导入.xlsx"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');
        // If you're serving to IE over SSL, then the following may be needed
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Expose-Headers: Content-Disposition');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        Yii::$app->end();
    }

    public function actionExcelupload() {
        if( empty($_FILES["file"]) ){
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }


        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file']['tmp_name']);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        unset( $sheetData[1]);

        $insertData = [];
        foreach( $sheetData as $data) {
            $tmpdata = [];
            if( empty($data['A']) ){
                continue;    
            }
            $tmpdata['name'] = $data['A'];
            $tmpdata['plantime'] = intval( $data['B'] );
            $tmpdata['projyear'] = intval( $data['C'] );
            $tmpdata['projdesc'] = intval( $data['D'] );
            $tmpdata['projlevel'] = $data['E'];
            $tmpdata['location'] = $data['F'];
            $tmpdata['leadorgan'] = $data['G'];
            $tmpdata['projtype'] = json_encode([$data['H'], $data['I']], JSON_UNESCAPED_UNICODE);
            $tmpdata['leadernum'] = $data['J'];
            $tmpdata['leader_projtype'] = json_encode([$data['K'], $data['L']], JSON_UNESCAPED_UNICODE);
            $tmpdata['leader_filternum'] = $data['M'];
            $tmpdata['masternum'] = $data['N'];
            $tmpdata['master_projtype'] = json_encode([$data['O'], $data['P']], JSON_UNESCAPED_UNICODE);
            $tmpdata['master_filternum'] = $data['Q'];
            $tmpdata['auditornum'] = $data['R'];

            $insertData[] = $tmpdata;
        }



        foreach ($insertData as $e){

            $name = $e['name'];
            $plantime = $e['plantime'];
            $projyear = $e['projyear'];
            $projorgan = $e['projorgan'] ?? '';
            $projdesc = $e['projdesc'];
            $projtype = $e['projtype'];
            $projlevel = $e['projlevel'];
            $leadorganId = $e['leadorgan'];
            $leadernum = $e['leadernum'];
            $leaderProjType = $e['leader_projtype'];
            $leaderFilternum = $e['leader_filternum'];
            $masternum = $e['masternum'];
            $masterProjType = $e['master_projtype'];
            $masterFilternum = $e['master_filternum'];
            $auditornum = $e['auditornum'];
            $location = $e['location'];


            //如果单位组织不存在，则返回异常
            $organ = OrganizationDao::find()->where(['id' => $projorgan])->one();
            if(!$organ){
                return $this->outputJson(
                    '',
                    ErrorDict::getError(ErrorDict::G_PARAM, "不存在的项目单位！")
                );
            }
            if(empty($projtype)){
                return $this->outputJson(
                    '',
                    ErrorDict::getError(ErrorDict::G_PARAM, "项目类型错误!")
                );
            }
            if(!in_array($projlevel, [1, 2, 3, 4])){
                return $this->outputJson(
                    '',
                    ErrorDict::getError(ErrorDict::G_PARAM, "项目层级输入有误！")
                );
            }
            $leadorgan = OrganizationDao::find()->where(['id' => $leadorganId])->one();
            if(!$leadorgan){
                return $this->outputJson(
                    '',
                    ErrorDict::getError(ErrorDict::G_PARAM, "不存在的牵头业务部门！")
                );
            }

            $transaction = ProjectDao::getDb()->beginTransaction();

            try{
                $service = new ProjectService();
                $projectId = $service->createProject(
                    ProjectDao::$statusToName['未开始'],
                    strtotime('now'),
                    $name,
                    $projyear,
                    $plantime,
                    $projdesc,
                    $projorgan,
                    json_encode($projtype, JSON_UNESCAPED_UNICODE),
                    $projlevel,
                    $leadorganId,
                    $leadernum,
                    $auditornum,
                    $masternum,
                    $location
                );

                //预分配人员
                if(!$leadernum){
                    $transaction->commit();
                    return $this->outputJson('',
                        ErrorDict::getError(ErrorDict::SUCCESS)
                    );
                }
                // 包含groupid => [ pid、以及 leader 字段]
                $group = [];
                for($i = 0; $i < $leadernum; $i++){
                    $auditGroup = new AuditGroupDao();
                    $group[$auditGroup->addAuditGroup($projectId)] = [];
                }
                $leaders = [];
                $masters = [];
                $members = [];



                $orgIds = (new OrganizationService)->getSubordinateIds($projorgan);
                $matchMembers = UserDao::find()
                    ->where(['isjob' => UserDao::$isJobToName['不在点']])
                    ->andWhere(['in', 'organid', $orgIds])
                    ->andWhere(['type' => UserDao::$typeToName['审计机关']]);


                if($projlevel === ProjectDao::$projLevelName['省厅本级']){
                    $organization = OrganizationDao::find()
                        ->where(["name" => "贵州省审计厅"])
                        ->one();
                    if($organization){
                        $allMatchPeoples = $matchMembers->andWhere(["organid" => $organization->id])->all();
                    }

                    if($leadernum ){
                        if($leaderProjType && $leaderFilternum){
                            $peoples = (new \yii\db\Query())
                                ->from("peopleproject")
                                ->innerJoin("project", "peopleproject.projid = project.id")
                                ->where(["project.projtype" => json_encode($leaderProjType, JSON_UNESCAPED_UNICODE)])
                                ->andWhere(["project.projorgan" => $organization->id])
                                ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_GROUP_LEADER])
                                ->groupBy("peopleproject.pid")
                                ->having([">=", "pidnum", $leaderFilternum])
                                ->select(["count(*) as pidnum", "peopleproject.pid"])
                                ->all();

                            if(count($peoples) > 0){
                                $tmpLeaderIds = array_map(function($e){
                                    return $e['pid'];
                                }, $peoples);

                                $tmpMatchPeoples = clone $matchMembers;
                                $leaders = $tmpMatchPeoples
                                    ->andWhere(["in", "id", $tmpLeaderIds])
                                    ->asArray()
                                    ->all();
                            }

                        } else {
                            $tmpMatchPeoples = clone $matchMembers;
                            $leaders = $tmpMatchPeoples
                                ->limit($leadernum)
                                ->asArray()
                                ->all();
                        }
                    }
                    $members = array_values(array_filter($allMatchPeoples, function($e) use ($leaders){
                        foreach ($leaders as $le){
                            if($le['id'] == $e['id']){
                                return false;
                            }
                        }
                        return true;
                    }));

                    if($masternum){
                        if($masterProjType && $masterFilternum){
                            $peoples = (new \yii\db\Query())
                                ->from("peopleproject")
                                ->innerJoin("project", "peopleproject.projid = project.id")
                                ->where(["project.projtype" => json_encode($masterProjType, JSON_UNESCAPED_UNICODE)])
                                ->andWhere(["project.projorgan" => $organization->id])
                                ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_MASTER])
                                ->groupBy("peopleproject.pid")
                                ->having([">=", "pidnum", $masterFilternum])
                                ->select(["count(*) as pidnum", "peopleproject.pid"])
                                ->all();

                            if(count($peoples) > 0){
                                $tmpMasterIds = array_map(function($e){
                                    return $e['pid'];
                                }, $peoples);

                                $leaderLocations = array_values(array_unique(array_map(function($e){
                                    $locatin = explode($e['location'], ",");
                                    if(count($locatin) === 3){
                                        unset($locatin[2]);
                                    }
                                    $locatin = join($locatin, ",");
                                    return $locatin;
                                }, $leaders)));

                                foreach ($leaderLocations as $location){
                                    $tmpMatchPeoples = clone $matchMembers;
                                    $masters = array_merge($tmpMatchPeoples
                                        ->andWhere(["in", "id", $tmpMasterIds])
                                        ->andWhere(["like", "location", "%$location%"])
                                        ->all(), $masters);
                                }

                            }

                        } else {
                            $leaderLocations = array_values(array_unique(array_map(function($e){
                                $locatin = explode($e['location'], ",");
                                if(count($locatin) === 3){
                                    unset($locatin[2]);
                                }
                                $locatin = join($locatin, ",");
                                return $locatin;
                            }, $leaders)));

                            foreach ($leaderLocations as $location){
                                $tmpMatchPeoples = clone $matchMembers;
                                $masters = array_merge($tmpMatchPeoples
                                    ->andWhere(["like", "location", "%$location%"])
                                    ->all(), $masters);
                            }
                        }
                    }
                    $members = array_values(array_filter($members, function($e) use ($masters){
                        foreach ($masters as $me){
                            if($me['id'] == $e['id']){
                                return false;
                            }
                        }
                        return true;
                    }));


                }elseif($projlevel === ProjectDao::$projLevelName['市州本级']){
                    $organization = OrganizationDao::findOne($projorgan);

                    if($organization){
                        $allMatchPeoples = $matchMembers->andWhere(["organid" => $organization->id])->all();
                    }

                    if($leadernum ){
                        if($leaderProjType && $leaderFilternum){
                            $peoples = (new \yii\db\Query())
                                ->from("peopleproject")
                                ->innerJoin("project", "peopleproject.projid = project.id")
                                ->where(["project.projtype" => $leaderProjType])
                                ->andWhere(["project.projorgan" => $organization->id])
                                ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_GROUP_LEADER])
                                ->groupBy("peopleproject.pid")
                                ->having([">=", "pidnum", $leaderFilternum])
                                ->select(["count(*) as pidnum", "peopleproject.pid"])
                                ->all();

                            if(count($peoples) > 0){
                                $tmpLeaderIds = array_map(function($e){
                                    return $e['pid'];
                                }, $peoples);

                                $tmpMatchPeoples = clone $matchMembers;
                                $leaders = $tmpMatchPeoples
                                    ->andWhere(["in", "id", $tmpLeaderIds])
                                    ->asArray()
                                    ->all();
                            }

                        } else {
                            $tmpMatchPeoples = clone $matchMembers;
                            $leaders = $tmpMatchPeoples
                                ->limit($leadernum)
                                ->asArray()
                                ->all();
                        }
                    }
                    $members = array_values(array_filter($allMatchPeoples, function($e) use ($leaders){
                        foreach ($leaders as $le){
                            if($le['id'] == $e['id']){
                                return false;
                            }
                        }
                        return true;
                    }));

                    if($masternum){
                        if($masterProjType && $masterFilternum){
                            $peoples = (new \yii\db\Query())
                                ->from("peopleproject")
                                ->innerJoin("project", "peopleproject.projid = project.id")
                                ->where(["project.projtype" => $masterProjType])
                                ->andWhere(["project.projorgan" => $organization->id])
                                ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_MASTER])
                                ->groupBy("peopleproject.pid")
                                ->having([">=", "pidnum", $masterFilternum])
                                ->select(["count(*) as pidnum", "peopleproject.pid"])
                                ->all();

                            if(count($peoples) > 0){
                                $tmpMasterIds = array_map(function($e){
                                    return $e['pid'];
                                }, $peoples);

                                $leaderLocations = array_values(array_unique(array_map(function($e){
                                    $locatin = explode($e['location'], ",");
                                    if(count($locatin) === 3){
                                        unset($locatin[2]);
                                    }
                                    $locatin = join($locatin, ",");
                                    return $locatin;
                                }, $leaders)));

                                foreach ($leaderLocations as $location){
                                    $tmpMatchPeoples = clone $matchMembers;
                                    $masters = array_merge($tmpMatchPeoples
                                        ->andWhere(["in", "id", $tmpMasterIds])
                                        ->andWhere(["like", "location", "%$location%"])
                                        ->all(), $masters);
                                }
                            }

                        } else {
                            $leaderLocations = array_values(array_unique(array_map(function($e){
                                $locatin = explode($e['location'], ",");
                                if(count($locatin) === 3){
                                    unset($locatin[2]);
                                }
                                $locatin = join($locatin, ",");
                                return $locatin;
                            }, $leaders)));

                            foreach ($leaderLocations as $location){
                                $tmpMatchPeoples = clone $matchMembers;
                                $masters = array_merge($tmpMatchPeoples
                                    ->andWhere(["like", "location", "%$location%"])
                                    ->all(), $masters);
                            }
                        }
                    }
                    $members = array_values(array_filter($members, function($e) use ($masters){
                        foreach ($masters as $me){
                            if($me['id'] == $e['id']){
                                return false;
                            }
                        }
                        return true;
                    }));


                }elseif($projlevel === ProjectDao::$projLevelName['省厅统一组织']){
                    if(!$location){
                        return $this->outputJson('',
                            ErrorDict::getError(ErrorDict::G_PARAM, '项目实施地未选择!')
                        );
                    }
                    $locatin = explode($location, ",");
                    if(count($locatin) === 3){
                        unset($locatin[2]);
                    }
                    $locatin = join($locatin, ",");

                    $tmp = clone $matchMembers;
                    $allMatchPeoples = $tmp->andWhere(['organid' => $projorgan])->asArray()->all();
                    if(count($allMatchPeoples) < $leadernum + $masternum + $auditornum){
                        $tmp = clone $matchMembers;
                        $tmpPeoples = $tmp
                            ->andWhere(["not like", "location", "%$locatin%"])
                            ->asArray()
                            ->all();
                        $allMatchPeoples = array_merge($allMatchPeoples, $tmpPeoples);
                    }

                    if($leadernum ){
                        if($leaderProjType && $leaderFilternum){
                            $peoples = (new \yii\db\Query())
                                ->from("peopleproject")
                                ->innerJoin("project", "peopleproject.projid = project.id")
                                ->where(["project.projtype" => $leaderProjType])
                                ->andWhere(["project.projorgan" => $projorgan])
                                ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_GROUP_LEADER])
                                ->groupBy("peopleproject.pid")
                                ->having([">=", "pidnum", $leaderFilternum])
                                ->select(["count(*) as pidnum", "peopleproject.pid"])
                                ->all();

                            if(count($peoples) > 0){
                                $tmpLeaderIds = array_map(function($e){
                                    return $e['pid'];
                                }, $peoples);

                                $tmpMatchPeoples = clone $matchMembers;
                                $leaders = $tmpMatchPeoples
                                    ->andWhere(["in", "id", $tmpLeaderIds])
                                    ->asArray()
                                    ->all();
                            }

                        } else {
                            $tmpMatchPeoples = clone $matchMembers;
                            $leaders = $tmpMatchPeoples
                                ->limit($leadernum)
                                ->asArray()
                                ->all();
                        }
                    }
                    $members = array_values(array_filter($allMatchPeoples, function($e) use ($leaders){
                        foreach ($leaders as $le){
                            if($le['id'] == $e['id']){
                                return false;
                            }
                        }
                        return true;
                    }));

                    if($masternum){
                        if($masterProjType && $masterFilternum){
                            $peoples = (new \yii\db\Query())
                                ->from("peopleproject")
                                ->innerJoin("project", "peopleproject.projid = project.id")
                                ->where(["project.projtype" => $masterProjType])
                                ->andWhere(["project.projorgan" => $projorgan])
                                ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_MASTER])
                                ->groupBy("peopleproject.pid")
                                ->having([">=", "pidnum", $masterFilternum])
                                ->select(["count(*) as pidnum", "peopleproject.pid"])
                                ->all();

                            if(count($peoples) > 0){
                                $tmpMasterIds = array_map(function($e){
                                    return $e['pid'];
                                }, $peoples);


                                $leaderLocations = array_values(array_unique(array_map(function($e){
                                    $locatin = explode($e['location'], ",");
                                    if(count($locatin) === 3){
                                        unset($locatin[2]);
                                    }
                                    $locatin = join($locatin, ",");
                                    return $locatin;
                                }, $leaders)));

                                foreach ($leaderLocations as $location){
                                    $tmpMatchPeoples = clone $matchMembers;
                                    $masters = array_merge($tmpMatchPeoples
                                        ->andWhere(["in", "id", $tmpMasterIds])
                                        ->andWhere(["like", "location", "%$location%"])
                                        ->all(), $masters);
                                }
                            }

                        } else {
                            $leaderLocations = array_values(array_unique(array_map(function($e){
                                $locatin = explode($e['location'], ",");
                                if(count($locatin) === 3){
                                    unset($locatin[2]);
                                }
                                $locatin = join($locatin, ",");
                                return $locatin;
                            }, $leaders)));

                            foreach ($leaderLocations as $location){
                                $tmpMatchPeoples = clone $matchMembers;
                                $masters = array_merge($tmpMatchPeoples
                                    ->andWhere(["like", "location", "%$location%"])
                                    ->all(), $masters);
                            }
                        }
                    }
                    $members = array_values(array_filter($members, function($e) use ($masters){
                        foreach ($masters as $me){
                            if($me['id'] == $e['id']){
                                return false;
                            }
                        }
                        return true;
                    }));

                }elseif($projlevel === ProjectDao::$projLevelName['市州统一组织']){
                    if(!$location){
                        return $this->outputJson('',
                            ErrorDict::getError(ErrorDict::G_PARAM, '项目实施地未选择!')
                        );
                    }
                    $locatin = explode($location, ",");
                    if(count($locatin) === 3){
                        unset($locatin[2]);
                    }
                    $locatin = join($locatin, ",");

                    $tmp = clone $matchMembers;
                    $allMatchPeoples = $tmp->andWhere(['organid' => $projorgan])->asArray()->all();
                    if(count($allMatchPeoples) < $leadernum + $masternum + $auditornum){
                        $tmp = clone $matchMembers;
                        $tmpPeoples = $tmp
                            ->andWhere(["not like", "location", "%$locatin%"])
                            ->asArray()
                            ->all();
                        $allMatchPeoples = array_merge($allMatchPeoples, $tmpPeoples);
                    }

                    if($leadernum ){
                        if($leaderProjType && $leaderFilternum){
                            $peoples = (new \yii\db\Query())
                                ->from("peopleproject")
                                ->innerJoin("project", "peopleproject.projid = project.id")
                                ->where(["project.projtype" => $leaderProjType])
                                ->andWhere(["project.projorgan" => $projorgan])
                                ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_GROUP_LEADER])
                                ->groupBy("peopleproject.pid")
                                ->having([">=", "pidnum", $leaderFilternum])
                                ->select(["count(*) as pidnum", "peopleproject.pid"])
                                ->all();

                            if(count($peoples) > 0){
                                $tmpLeaderIds = array_map(function($e){
                                    return $e['pid'];
                                }, $peoples);

                                $tmpMatchPeoples = clone $matchMembers;
                                $leaders = $tmpMatchPeoples
                                    ->andWhere(["in", "id", $tmpLeaderIds])
                                    ->asArray()
                                    ->all();
                            }

                        } else {
                            $tmpMatchPeoples = clone $matchMembers;
                            $leaders = $tmpMatchPeoples
                                ->limit($leadernum)
                                ->asArray()
                                ->all();
                        }
                    }
                    $members = array_values(array_filter($allMatchPeoples, function($e) use ($leaders){
                        foreach ($leaders as $le){
                            if($le['id'] == $e['id']){
                                return false;
                            }
                        }
                        return true;
                    }));

                    if($masternum){
                        if($masterProjType && $masterFilternum){
                            $peoples = (new \yii\db\Query())
                                ->from("peopleproject")
                                ->innerJoin("project", "peopleproject.projid = project.id")
                                ->where(["project.projtype" => $masterProjType])
                                ->andWhere(["project.projorgan" => $projorgan])
                                ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_MASTER])
                                ->groupBy("peopleproject.pid")
                                ->having([">=", "pidnum", $masterFilternum])
                                ->select(["count(*) as pidnum", "peopleproject.pid"])
                                ->all();

                            if(count($peoples) > 0){
                                $tmpMasterIds = array_map(function($e){
                                    return $e['pid'];
                                }, $peoples);


                                $leaderLocations = array_values(array_unique(array_map(function($e){
                                    $locatin = explode($e['location'], ",");
                                    if(count($locatin) === 3){
                                        unset($locatin[2]);
                                    }
                                    $locatin = join($locatin, ",");
                                    return $locatin;
                                }, $leaders)));

                                foreach ($leaderLocations as $location){
                                    $tmpMatchPeoples = clone $matchMembers;
                                    $masters = array_merge($tmpMatchPeoples
                                        ->andWhere(["in", "id", $tmpMasterIds])
                                        ->andWhere(["like", "location", "%$location%"])
                                        ->all(), $masters);
                                }
                            }

                        } else {
                            $leaderLocations = array_values(array_unique(array_map(function($e){
                                $locatin = explode($e['location'], ",");
                                if(count($locatin) === 3){
                                    unset($locatin[2]);
                                }
                                $locatin = join($locatin, ",");
                                return $locatin;
                            }, $leaders)));

                            foreach ($leaderLocations as $location){
                                $tmpMatchPeoples = clone $matchMembers;
                                $masters = array_merge($tmpMatchPeoples
                                    ->andWhere(["like", "location", "%$location%"])
                                    ->all(), $masters);
                            }
                        }
                    }
                    $members = array_values(array_filter($members, function($e) use ($masters){
                        foreach ($masters as $me){
                            if($me['id'] == $e['id']){
                                return false;
                            }
                        }
                        return true;
                    }));

                }elseif($projlevel === ProjectDao::$projLevelName['县级']){
                    $tmp = clone $matchMembers;
                    $allMatchPeoples = $tmp->andWhere(['organid' => $projorgan])->asArray()->all();

                    if($leadernum ){
                        if($leaderProjType && $leaderFilternum){
                            $peoples = (new \yii\db\Query())
                                ->from("peopleproject")
                                ->innerJoin("project", "peopleproject.projid = project.id")
                                ->where(["project.projtype" => $leaderProjType])
                                ->andWhere(["project.projorgan" => $projorgan])
                                ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_GROUP_LEADER])
                                ->groupBy("peopleproject.pid")
                                ->having([">=", "pidnum", $leaderFilternum])
                                ->select(["count(*) as pidnum", "peopleproject.pid"])
                                ->all();

                            if(count($peoples) > 0){
                                $tmpLeaderIds = array_map(function($e){
                                    return $e['pid'];
                                }, $peoples);

                                $tmpMatchPeoples = clone $matchMembers;
                                $leaders = $tmpMatchPeoples
                                    ->andWhere(["in", "id", $tmpLeaderIds])
                                    ->asArray()
                                    ->all();
                            }

                        } else {
                            $tmpMatchPeoples = clone $matchMembers;
                            $leaders = $tmpMatchPeoples
                                ->limit($leadernum)
                                ->asArray()
                                ->all();
                        }
                    }
                    $members = array_values(array_filter($allMatchPeoples, function($e) use ($leaders){
                        foreach ($leaders as $le){
                            if($le['id'] == $e['id']){
                                return false;
                            }
                        }
                        return true;
                    }));

                    if($masternum){
                        if($masterProjType && $masterFilternum){
                            $peoples = (new \yii\db\Query())
                                ->from("peopleproject")
                                ->innerJoin("project", "peopleproject.projid = project.id")
                                ->where(["project.projtype" => $masterProjType])
                                ->andWhere(["project.projorgan" => $projorgan])
                                ->andWhere(["peopleproject.roletype" => PeopleProjectDao::ROLE_TYPE_MASTER])
                                ->groupBy("peopleproject.pid")
                                ->having([">=", "pidnum", $masterFilternum])
                                ->select(["count(*) as pidnum", "peopleproject.pid"])
                                ->all();

                            if(count($peoples) > 0){
                                $tmpMasterIds = array_map(function($e){
                                    return $e['pid'];
                                }, $peoples);

                                $leaderLocations = array_values(array_unique(array_map(function($e){
                                    $locatin = explode($e['location'], ",");
                                    if(count($locatin) === 3){
                                        unset($locatin[2]);
                                    }
                                    $locatin = join($locatin, ",");
                                    return $locatin;
                                }, $leaders)));

                                foreach ($leaderLocations as $location){
                                    $tmpMatchPeoples = clone $matchMembers;
                                    $masters = array_merge($tmpMatchPeoples
                                        ->andWhere(["in", "id", $tmpMasterIds])
                                        ->andWhere(["like", "location", "%$location%"])
                                        ->all(), $masters);
                                }
                            }

                        } else {
                            $leaderLocations = array_values(array_unique(array_map(function($e){
                                $locatin = explode($e['location'], ",");
                                if(count($locatin) === 3){
                                    unset($locatin[2]);
                                }
                                $locatin = join($locatin, ",");
                                return $locatin;
                            }, $leaders)));

                            foreach ($leaderLocations as $location){
                                $tmpMatchPeoples = clone $matchMembers;
                                $masters = array_merge($tmpMatchPeoples
                                    ->andWhere(["like", "location", "%$location%"])
                                    ->all(), $masters);
                            }
                        }
                    }
                    $members = array_values(array_filter($members, function($e) use ($masters){
                        foreach ($masters as $me){
                            if($me['id'] !== $e['id']){
                                return false;
                            }
                        }
                        return true;
                    }));

                }

                $group = $this->distribute($group, $leaders, $masters, $members, $auditornum, $masternum);

                foreach ($group as $key => $e ){
                    foreach ($e as $user){
                        $pepProject = new PeopleProjectDao();
                        $pepProject->pid = $user['user']['id'];
                        $pepProject->groupid = $key;
                        $pepProject->roletype = $user['role'];
                        $pepProject->islock = $pepProject::NOT_LOCK;
                        $pepProject->projid = $projectId;
                        $pepProject->save();
                        $person = UserDao::findOne($user['user']['id']);
                        $person->isaudit = UserDao::IS_AUDIT;
                        $person->isjob = UserDao::IS_JOB;
                        $person->save();
                    }
                }

                $transaction->commit();
            } catch(\Exception $e){
                Log::fatal(printf("创建错误！%s, %s", $e->getMessage(), $e->getTraceAsString()));
                $transaction->rollBack();
                return $this->outputJson('',
                    ErrorDict::getError(ErrorDict::ERR_INTERNAL, "创建项目内部错误！")
                );
            }
        }

        $error = ErrorDict::getError(ErrorDict::SUCCESS);
        return $this->outputJson('', $error);
    }


    /**
     * 确认计划（为了权限暂时拆分）
     */
    public function actionSure() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'operate' => array (
                'require' => true,
                'checker' => 'noCheck',
            )
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));

        $transaction = ProjectDao::getDb()->beginTransaction();

        try{
            $pro = ProjectDao::findOne($id);
            $pro->status = ProjectDao::$statusToName['计划阶段'];
            $pro->save();
            $pepProGroups = PeopleProjectDao::find()
                ->where(["projid" => $id])
                ->groupBy('groupid')
                ->all();
            $groupIds = array_map(
                function($e)
                {
                    return $e->groupid;
                },
                $pepProGroups
            );

            $groups = AuditGroupDao::findAll($groupIds);
            foreach ($groups as $e){
                $e->status = AuditGroupDao::$statusToName['应进点'];
                $e->save();
            }
            $transaction->commit();
        }catch(\Exception $e){
            $transaction->rollBack();
            Log::addLogNode('状态变更错误！', serialize($e->errorInfo));
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }


        return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));
    }

    /**
     * 项目启动（为了权限暂时拆分）
     *
     */
    public function actionBegin() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            )
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));

        //todo 注意！可能后续会加上权限相关的东西

        $transaction = ProjectDao::getDb()->beginTransaction();

        try{
            $pro = ProjectDao::findOne($id);
            $pro->status = ProjectDao::$statusToName['实施阶段'];
            $pro->save();
            $transaction->commit();
        }catch(\Exception $e){
            $transaction->rollBack();
            Log::addLogNode('状态变更错误！', serialize($e->errorInfo));
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }


        return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));

    }

    /**
     * 开始审理（为了权限暂时拆分）
     *
     */
    public function actionJudge() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'num' => array (
                'require' => false,
                'checker' => 'noCheck',
            )
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }

        $id = intval($this->getParam('id', 0));
        $num = intval($this->getParam('num', 0));

        if($num == 0){
            return $this->outputJson('',
                ErrorDict::getError(ErrorDict::G_PARAM, '预审理人数不对！')
            );
        }

        //todo 注意！可能后续会加上权限相关的东西

        $transaction = ProjectDao::getDb()->beginTransaction();

        try{
            $pro = ProjectDao::findOne($id);
            if(!$pro){
                return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));
            }

            $pro->status = ProjectDao::$statusToName['审理阶段'];
            $pro->jugenum = $num;
            $pro->save();

            $pepProGroups = PeopleProjectDao::find()
                ->where(["projid" => $id])
                ->groupBy('groupid')
                ->asArray()
                ->all();
            $groupIds = array_map(
                function($e)
                {
                    return $e->groupid;
                },
                $pepProGroups
            );

            $groups = AuditGroupDao::findAll($groupIds);
//                    foreach ($groups as $e){
//                        $e->status = AuditGroupDao::$statusToName['审理中'];
//                        $e->save();
//                    }

            $orgIds = (new OrganizationService)->getSubIds($pro->projorgan);
            $orgIds[] = $pro->projorgan;

            $idGroupMap = [];
            $users = UserDao::find()
                ->where(['isjob' => UserDao::IS_NOT_JOB])
                ->andWhere(['in', 'organid', $orgIds])
                ->asArray()
                ->all();
            foreach ($users as $e){
                $idGroupMap[] = [
                    "id" => $e['id'],
                    "group" => 0
                ];
            }
            $userGroups = PeopleProjectDao::find()
                ->where(["projid" => $id])
                ->groupBy('pid')
                ->asArray()
                ->all();
            foreach ($userGroups as $e){
                $idGroupMap[] = [
                    "id" => $e['pid'],
                    "group" => $e['groupid']
                ];
            }

            $flag = count($idGroupMap);

            while ($flag > 0){
                foreach ($groups as $e){
                    foreach ($idGroupMap as $key => $v){
                        if($v['group'] !== $e['id']){
                            $judge = new JugeProjectDao();
                            $judge->projid = $id;
                            $judge->groupid = $e['id'];
                            $judge->pid = $v['id'];
                            $judge->save();
                            unset($idGroupMap[$key]);
                            break;
                        }
                    }
                }
            }

            $transaction->commit();
        }catch(\Exception $e){
            $transaction->rollBack();
            Log::addLogNode('状态变更错误！', serialize($e->errorInfo));
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }


        return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));

    }

    /**
     * 审理结束（为了权限暂时拆分）
     *
     */
    public function actionFinishjudge() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );
        if (false === $this->check()) {
            $ret = $this->outputJson(array(), $this->err);
            return $ret;
        }
        $id = intval($this->getParam('id', 0));

        //todo 注意！可能后续会加上权限相关的东西
        $transaction = ProjectDao::getDb()->beginTransaction();

        try{
            $pro = ProjectDao::findOne($id);
            $pro->status = ProjectDao::$statusToName['项目结束'];
            $pro->save();

            $pepProGroups = PeopleProjectDao::find()
                ->where(["projid" => $id])
                ->groupBy('groupid')
                ->all();
            $groupIds = array_map(
                function($e)
                {
                    return $e->groupid;
                },
                $pepProGroups
            );

            $groups = AuditGroupDao::findAll($groupIds);
            foreach ($groups as $e){
                $e->status = AuditGroupDao::$statusToName['报告中'];
                $e->save();
            }
            $transaction->commit();
        }catch(\Exception $e){
            $transaction->rollBack();
            Log::addLogNode('状态变更错误！', serialize($e->errorInfo));
            $error = ErrorDict::getError(ErrorDict::G_SYS_ERR);
            $ret = $this->outputJson('', $error);
            return $ret;
        }


        return $this->outputJson('', ErrorDict::getError(ErrorDict::SUCCESS));

    }


    /**
     * 获取项目类型的人数
     *
     */
    public function actionProjtypenum() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'projtype' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'type' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'filterNum' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
        );

        $projtype = $this->getParam('projtype', []);
        $type = intval($this->getParam('type', 0));
        $filterNum = intval($this->getParam('filterNum', 0));

        if(!in_array($type, [PeopleProjectDao::ROLE_TYPE_MASTER, PeopleProjectDao::ROLE_TYPE_GROUP_LEADER])){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, "类型不对!"));
        }


        $peoples = (new \yii\db\Query())
            ->from("peopleproject")
            ->innerJoin("project", "peopleproject.projid = project.id")
            ->where(["project.projtype" => json_encode($projtype, JSON_UNESCAPED_UNICODE)])
            ->andWhere(["peopleproject.roletype" => $type])
            ->groupBy("peopleproject.pid")
            ->having([">=", "pidnum", $filterNum])
            ->select(["count(*) as pidnum"])
            ->all();

        return $this->outputJson(count($peoples), ErrorDict::getError(ErrorDict::SUCCESS));

    }


    public function actionLocationorgan() {
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'projlevel' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
            'location' => array (
                'require' => true,
                'checker' => 'noCheck',
            ),
        );

        $projlevel = $this->getParam('projlevel', 0);
        $location = $this->getParam('location', 0);


        if(!in_array($projlevel, [ProjectDao::$projLevelName['省厅统一组织'], ProjectDao::$projLevelName['省厅本级'], ProjectDao::$projLevelName['县级'],
            ProjectDao::$projLevelName['市州本级'], ProjectDao::$projLevelName['市州统一组织']]) || empty($location)){
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM, "类型不对!"));
        }

        $service = new OrganizationService();
        $all = $service->getShortOrgans(UserDao::$typeToName['审计机关'], false);

        switch ($projlevel){
            case ProjectDao::$projLevelName['省厅本级']:
                $ret = array_values(array_filter($all, function($e){
                    return $e['name'] == "贵州省审计厅";
                }));
                break;
            case ProjectDao::$projLevelName['市州本级']:
                $ret = array_values(array_filter($all, function($e){
                    return count(explode(",", $e['regnum'])) == 2;
                }));
                break;
            case ProjectDao::$projLevelName['省厅统一组织']:
                $ret = array_values(array_filter($all, function($e) use ($location){
                    $location = explode(",", $location);
                    $location = [$location[0], $location[1] ?? ''];
                    $location = join(",", $location);

                    return !strpos($e['regnum'], $location);
                }));
                break;
            case ProjectDao::$projLevelName['市州统一组织']:
                $ret = array_values(array_filter($all, function($e) use ($location){
                    $location = explode(",", $location);
                    $location = [$location[0], $location[1] ?? ''];
                    $location = join(",", $location);

                    return !strpos($e['regnum'], $location);
                }));
                break;
            case ProjectDao::$projLevelName['县级']:
                $ret = array_values(array_filter($all, function($e) use ($location){
                    $location = explode(",", $location);
                    $location = [$location[0], $location[1] ?? ''];
                    $location = join(",", $location);

                    return !strpos($e['regnum'], $location);
                }));
                break;
        }

        $data = [];
        foreach ($ret as $e){
            $data[] = [
                $e['id'] => $e['name']
            ];
        }

        return $this->outputJson($data, ErrorDict::getError(ErrorDict::SUCCESS));

    }
}
