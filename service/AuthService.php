<?php
namespace app\service;

use app\classes\Log;
use app\models\RoleAuthDao;
use app\models\RoleDao;
use yii\db\Exception;

class AuthService {


    //查询权限列表
    public function getAuthList(){
        $authList = (new \yii\db\Query())
            ->from('auth')
            ->all();
        return $authList;
    }

    //查询权限信息
    public function getAuthInfo($authId){
        $authInfo = (new \yii\db\Query())
            ->from('auth')
            ->where(['id' => $authId])
            ->one();
        return $authInfo;
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

    //判断某URL是否需要进行权限控制
    public function judgeUrlNeedAuth($url){
        $ret = (new \yii\db\Query())
            ->from('auth')
            ->where('url like "%' . $url . '%"')
            ->all();
        return $ret;
    }

    //查询某些角色是否拥有某个url的权限
    public function getAuthListByRole($pid, $url){
        $roleDao = new RoleDao();
        $roleInfo = $roleDao->queryByPid($pid);
        $roleIds = [];
        foreach ($roleInfo as $one) {
            $roleIds[] = $one['rid'];
        }
        $roleAuthList = (new \yii\db\Query())
            ->from('auth')
            ->leftJoin('roleauth', 'auth.id = roleauth.authid')
            ->where(['in', 'rid', $roleIds])
            ->andWhere('url like "%' . $url . '%"')
            ->all();
        return $roleAuthList;
    }

    //修改权限
    public function updateRoleAuth($roleAuthList){
        $tr = \Yii::$app->get('db')->beginTransaction();
        try {
            $roleAuthDao = new RoleAuthDao();
            $roleAuthDao::deleteAll();
            foreach ($roleAuthList as $roleAuth) {
                $roleId = $roleAuth['roleid'];
                foreach ($roleAuth['authid'] as $authId) {
                    $roleAuthDao = new RoleAuthDao();
                    $roleAuthDao->rid = $roleId;
                    $roleAuthDao->authid = $authId;
                    $roleAuthDao->save();
                }
            }
            $tr->commit();
        }catch (Exception $e) {
            $tr->rollBack();
            Log::addLogNode('addException', serialize($e->errorInfo));
            return false;
        }
        return true;
    }

}