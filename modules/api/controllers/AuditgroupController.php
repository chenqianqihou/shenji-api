<?php
/**
 * Created by PhpStorm.
 * User: ryugou
 * Date: 2019/7/27
 * Time: 11:42 PM
 */
namespace app\modules\api\controllers;

use app\classes\BaseController;
use app\classes\ErrorDict;
use app\classes\Log;
use app\models\PeopleProjectDao;
use app\models\ProjectDao;


class AuditgroupController extends BaseController {

    /**
     * 审计组操作：增加人员
     *
     */
    public function actionAdd(){
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'pid' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
        );
        $id = intval($this->getParam('id', 0));
        $pid = intval($this->getParam('pid', 0));

        $proDao = new PeopleProjectDao();
        $peoPros = $proDao::find()->where(['groupid' => $id])->groupBy('projid')->all();

        $transaction = $proDao::getDb()->beginTransaction();
        try{
            foreach ($peoPros as $e) {
                $newPeop = new PeopleProjectDao();
                $newPeop->pid = $pid;
                $newPeop->projid = $e->projid;
                $newPeop->groupid = $id;
                $newPeop->roletype = $newPeop::ROLE_TYPE_GROUPER;
                $newPeop->islock = 1;
                $newPeop->save();
            }
            $transaction->commit();
        }catch (\Exception $e){
            $transaction->rollBack();
            Log::fatal($e->getMessage());
            return $this->outputJson('', ErrorDict::getError(ErrorDict::ERR_INTERNAL));
        }

        return $this->outputJson('',
            ErrorDict::getError(ErrorDict::SUCCESS)
        );
    }

    /**
     * 审计组操作：更改角色
     *
     */
    public function actionUpdaterole(){
        $this->defineMethod = 'POST';
        $this->defineParams = array (
            'id' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'pid' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
            'role' => array (
                'require' => true,
                'checker' => 'isNumber',
            ),
        );
        $id = intval($this->getParam('id', 0));
        $pid = intval($this->getParam('pid', 0));
        $role = intval($this->getParam('role', 0));

        if(!in_array($role, [PeopleProjectDao::ROLE_TYPE_GROUPER,
            PeopleProjectDao::ROLE_TYPE_GROUP_LEADER,
            PeopleProjectDao::ROLE_TYPE_MASTER])) {
            return $this->outputJson('', ErrorDict::getError(ErrorDict::G_PARAM));
        }

        $proDao = new PeopleProjectDao();
        $peoPro = $proDao::find()
            ->where(['groupid' => $id])
            ->andwhere(['pid' => $pid])
            ->one();
        $peoPro->roletype = $role;
        $peoPro->save();


        return $this->outputJson('',
            ErrorDict::getError(ErrorDict::SUCCESS)
        );
    }

}