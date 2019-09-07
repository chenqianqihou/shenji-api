<?php
namespace app\service;

use app\classes\Log;
use app\models\RoleAuthDao;
use yii\db\Exception;

class AuthService {


    //查询权限列表
    public function getAuthList(){
        $authList = (new \yii\db\Query())
            ->from('auth')
            ->all();
        return $authList;
    }

    //查询角色列表
    public function getRoleList(){
        $roleList = (new \yii\db\Query())
            ->from('role')
            ->all();
        return $roleList;
    }

    //查询角色权限列表
    public function getRoleAuthList(){
        $roleAuthList = (new \yii\db\Query())
            ->from('roleauth')
            ->all();
        return $roleAuthList;
    }

    //修改权限
    public function updateRoleAuth($roleAuthList){
        $tr = \Yii::$app->get('db')->beginTransaction();
        try {
            $roleAuthDao = new RoleAuthDao();
            $roleAuthDao::deleteAll();
            foreach ($roleAuthList as $roleId => $authIdList) {
                foreach ($authIdList as $authId) {
                    $roleAuthDao->rid = $roleId;
                    $roleAuthDao->authid = $authId;
                    $roleAuthDao->save();
                }
            }
        }catch (Exception $e) {
            $tr->rollBack();
            Log::addLogNode('addException', serialize($e->errorInfo));
            return false;
        }
        return true;
    }

}