<?php

namespace app\service;

use app\models\OrganizationDao;

class OrganizationService
{

    // 查询机构信息
    public function getOrganizationInfo($oid) {
        return OrganizationDao::find()->where(['id' => $oid])->asArray()->one();
    }

    //添加机构
    public function insertOrganization( $params = []) {
        
    }

    public function checkParams( $params ){
        $result = [
            'res' => true,
            'key' => '',
            'message' => ''
        ];

        if( empty($params['name']) || trim($params['name']) == ''){
            $result = [
                'res' => false,
                'key' => 'name',
                'message' => 'name can not be null!'
            ];

            return $result;
        }    

        if( empty($params['otype']) || !in_array($params['otype'],[1,2,3] ) ){
            $result = [
                'res' => false,
                'key' => 'otype',
                'message' => 'otype error!'
            ];

            return $result;
        }

        if( empty($params['deputy']) ){
            $result = [
                'res' => false,
                'key' => 'deputy',
                'message' => 'deputy can not be null!'
            ];

            return $result;
        }

        if( empty($params['regnum']) || !is_numeric($params['regnum'])){
            $result = [
                'res' => false,
                'key' => 'regnum',
                'message' => 'regnum error!'
            ];

            return $result;
        }

        if( empty($params['regaddress']) ){
            $result = [
                'res' => false,
                'key' => 'regaddress',
                'message' => 'regaddress can not be null!'
            ];

            return $result;
        }

        if( empty($params['category']) ){
            $result = [
                'res' => false,
                'key' => 'category',
                'message' => 'category can not be null!'
            ];

            return $result;
        }

        if( empty($params['level']) ){
            $result = [
                'res' => false,
                'key' => 'level',
                'message' => 'level can not be null!'
            ];

            return $result;
        }

        if( empty($params['capital']) || $params['capital'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'capital',
                'message' => 'capital error!'
            ];

            return $result;
        }

        if( empty($params['workbegin']) ){
            $result = [
                'res' => false,
                'key' => 'workbegin',
                'message' => 'workbegin error'
            ];

            return $result;
        }

        if( empty($params['costeng']) || !is_numeric($params['costeng']) || $params['costeng'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'costeng',
                'message' => 'costeng error'
            ];

            return $result;
        }

        if( empty($params['coster']) || !is_numeric($params['coster']) || $params['coster'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'coster',
                'message' => 'coster error'
            ];

            return $result;
        }

        if( empty($params['accountant']) || !is_numeric($params['accountant']) || $params['accountant'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'accountant',
                'message' => 'accountant error'
            ];

            return $result;
        }

        if( empty($params['highlevel']) || !is_numeric($params['highlevel']) || $params['highlevel'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'highlevel',
                'message' => 'highlevel error'
            ];

            return $result;
        }

        if( empty($params['midlevel']) || !is_numeric($params['midlevel']) || $params['midlevel'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'midlevel',
                'message' => 'midlevel error'
            ];

            return $result;
        }

        if( empty($params['retiree']) || !is_numeric($params['retiree']) || $params['retiree'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'retiree',
                'message' => 'retiree error'
            ];

            return $result;
        }

        if( empty($params['parttimers']) || !is_numeric($params['parttimers']) || $params['parttimers'] < 0 ){
            $result = [
                'res' => false,
                'key' => 'parttimers',
                'message' => 'parttimers error'
            ];

            return $result;
        }

        if( empty($params['contacts']) ){
            $result = [
                'res' => false,
                'key' => 'contacts',
                'message' => 'contacts can not be null'
            ];

            return $result;
        }

        if( empty($params['contactphone']) || !is_numeric($params['contactphone']) ){
            $result = [
                'res' => false,
                'key' => 'contactphone',
                'message' => 'contactphone error'
            ];

            return $result;
        }

        if( empty($params['contactnumber']) || !is_numeric($params['contactnumber']) ){
            $result = [
                'res' => false,
                'key' => 'contactnumber',
                'message' => 'contactnumber error'
            ];

            return $result;
        }

        if( empty($params['officenum']) || !is_numeric($params['officenum']) ){
            $result = [
                'res' => false,
                'key' => 'officenum',
                'message' => 'officenum error'
            ];

            return $result;
        }

        if( empty($params['officeaddress']) ){
            $result = [
                'res' => false,
                'key' => 'officeaddress',
                'message' => 'officeaddress can not be null'
            ];

            return $result;
        }
    }
}
