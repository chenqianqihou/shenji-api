<?php

namespace app\classes;

use app\models\RoleDao;
use app\models\UserDao;
use app\service\AuthService;
use app\service\OrganizationService;
use Yii;
use yii\web\Controller;
use yii\base\Exception;
use yii\web\Response;
use \Lcobucci\JWT\Parser;
use \Lcobucci\JWT\Signer\Hmac\Sha256;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class BaseController extends Controller {
    protected $requestCookie;
    protected $responseCookie;
    protected $data = [
        'ID'      => '',
        'name'   => '',
    ];
    protected $method;
    protected $defineMethod = 'GET';
    protected $params;
    protected $userInfo;
    protected $defineParams;
    protected $beginTime;
    protected $err;

    public function init() {
        parent::init();
        date_default_timezone_set('PRC');
        $this->initParams();
        $this->initLog();
    }

    protected function initParams() {
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $this->method = $_SERVER['REQUEST_METHOD'];
        }
        switch ($this->method) {
            case 'POST':
                $content = file_get_contents('php://input');
                $this->params = (array)json_decode($content, true);
                break;
            case 'GET':
                $this->params = Yii::$app->request->get();
                break;
            default:
                $this->params = [];
        }
    }

    // 初始化日志打印
    protected function initLog() {
        global $logDir;
        Log::init(APP_PROJECT_NAME, $logDir);
        Log::addLogNode("ip",       Yii::$app->request->userIP);
        Log::addLogNode("method",  $this->__server_get_data('REQUEST_METHOD') .":". $this->__server_get_data('SERVER_PROTOCOL'));
        Log::addLogNode("uri",      Yii::$app->request->pathInfo);
        Log::addLogNode("rtime",    $this->__server_get_data('REQUEST_TIME_FLOAT')/1000);
        Log::addLogNode("params",    json_encode($this->params));
    }

    /**
     * 在action 执行之前的准备工作
     * @param $action
     * @return bool|void
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    public function beforeAction($action) {
        //for debug
        //$this->userInfo = json_decode('{"id":"401","pid":"sj16625","name":"\u6d4b\u8bd5\u55ef\u55ef\u55ef","sex":"1","type":"3","organid":"1012","department":"1202","level":"2","phone":"18627879721","email":"2137@qq.com","passwd":"25d55ad283aa400af464c76d713c07ad","cardid":"43098119960304661X","address":"\u5317\u4eac\u5e02\u6d77\u6dc0\u533a\u897f\u4e8c\u65d7\u897f\u8def2\u53f7\u966235\u53f7\u697c","education":"1","school":"\u897f\u5317\u5de5\u4e1a\u5927\u5b66","major":"\u8f6f\u4ef6\u5de5\u7a0b","political":"2","nature":"1","specialties":"","achievements":"","position":"3","location":"520000,520200","workbegin":"1970-01-19","auditbegin":"2019-07-02","comment":"\u8fd9\u662f\u6d4b\u8bd5","isaudit":"1","isjob":"1","ctime":"2019-07-08 23:36:18","utime":"2020-01-10 18:01:59","organinfo":{"id":"1012","name":"\u8d35\u5dde\u7701\u5ba1\u8ba1\u5385","otype":"3","deputy":"-","regtime":"20190101","regnum":"520000","regaddress":"-","category":"-","level":"-","capital":"1","workbegin":"20190101","costeng":"1","coster":"1","accountant":"1","highlevel":"1","midlevel":"1","retiree":"1","parttimers":"1","contactor":"-","contactphone":"","contactnumber":"","officenum":"520000","officeaddress":"","ctime":"2019-07-06 14:53:09","utime":"2019-07-09 10:41:24","qualiaudit":"0","parentid":"0"}}',true);
        //$this->data = ['ID'=>'sj16625'];
        //return parent::beforeAction( $action );

        $this->beginTime = microtime(true);
        $url = Yii::$app->request->getPathInfo();
        if ($url == 'api/user/login') {
            return parent::beforeAction( $action );
        }
        // 检测用户是否登录
        $signer  = new Sha256();
        $secret = Yii::$app->params['secret'];
        //获取token
        $token = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        if (!$token) {
            Log::addLogNode('Invalid token', '');
            $this->noLogin();
            return false;
        }
        try {
            //解析token
            $parse = (new Parser())->parse($token);
            //验证token合法性
            if (!$parse->verify($signer, $secret)) {
                Log::addLogNode('Invalid token', '');
                $this->noLogin();
                return;
            }
            //验证是否已经过期
            if ($parse->isExpired()) {
                Log::addLogNode('Already expired', '');
                $this->noLogin();
                return;
            }
            //获取数据

            $this->data = [
                'ID' => $parse->getClaim('ID'),
                'name' => $parse->getClaim('name')
            ];

            $userDao = new UserDao();
            $organService = new OrganizationService();
            $this->userInfo = $userDao->queryByID( $parse->getClaim('ID') );
            $this->userInfo['organinfo'] = $organService->getOrganizationInfo( $this->userInfo['organid'] );
        } catch (Exception $e) {
            Log::addLogNode('Invalid token', '');
            $this->noLogin();
            return;
        }
        $urlAuth = self::authControl($this->data['ID'], $url);
        if (!$urlAuth) {
            $this->noPower();
            return;
        }
        return parent::beforeAction( $action );
    }

    public function afterAction($action, $result)
    {
        //记录日志
        Log::notice('');
        $url = Yii::$app->request->pathInfo;
        if ($url) {
            $end = microtime(true);
            $exectime = $end - $this->beginTime;
        }
        return parent::afterAction($action, $result); // TODO: Change the autogenerated stub
    }

    protected function noLogin() {
        $error = ErrorDict::getError(ErrorDict::ERR_NO_LOGIN, '用户未登录', '请您先登录');
        $ret = $this->outputJson('', $error);
        Yii::$app->response->data = $ret;
        Yii::$app->end();
    }

    protected function noPower() {
        $error = ErrorDict::getError(ErrorDict::ERR_NOPERMISSION);
        $ret = $this->outputJson('', $error);
        Yii::$app->response->data = $ret;
        Yii::$app->end();
    }

    /**
     * json输出统一出口
     * @param $data
     * @param $error
     * @param int $statusCode
     * @return array
     */
    public function outputJson($data, $error, $statusCode = 200) {
        $ret = array(
                'error' => $error,
                'data'  => $data,
                );

        // 如果返回的状态码不是成功的，打印错误日志
        if (isset($error['returnCode']) && $error['returnCode'] != ErrorDict::SUCCESS) {
            Log::addLogNode('error', json_encode($ret, JSON_UNESCAPED_UNICODE));
        }

        if (empty($statusCode)) {
            $statusCode = 200;
        }
        Yii::$app->response->statusCode = $statusCode;
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->headers->add('Access-Control-Allow-Origin', '*');
        Yii::$app->response->headers->add('Access-Control-Allow-Headers', 'content-type,AUTHORIZATION');
        Yii::$app->response->headers->add('Access-Control-Allow-Methods', 'POST, GET, OPTIONS, DELETE');
        return $ret;
    }

    /**
     * 获取参数值
     * @param $strKey
     * @param null $default
     * @return null
     */
    public function getParam($strKey, $default=null) {
        if (isset($this->params[$strKey])) {
            //todo 过滤参数值
            return $this->params[$strKey];
        }
        return $default;
    }

    public function getParams() {
        return $this->params;    
    }

    /**
     * 请求参数校验
     * @return bool
     */
    protected function check()
    {
        // 请求方法校验
        if ($this->method != $this->defineMethod) {
            $msg = 'req method should be '.$this->defineMethod;
            $this->err = ErrorDict::getError(ErrorDict::G_METHOD, $msg, '');
            return false;
        }

//        // 请求参数校验
//        if (empty($this->params)) {
//            return true;
//        }

        foreach ($this->defineParams as $name => $conf) {
            $value = $this->getParam($name);
            if ($conf['require'] == true && (!isset($value) || $value === '')) {
                $this->err = ErrorDict::getError(ErrorDict::G_PARAM, $name.'必填参数为null', '必选项未填');
                return false;
            }
            if ($conf['require'] == false && empty($value)) {
                continue ;
            }
            if ('noCheck' == $conf['checker']) {
                continue;
            }
            $extra = isset($conf['extra']) ? $conf['extra'] : '';
            call_user_func_array('app\\classes\\Checker::'.$conf['checker'], array($name, $value, $extra));
            if (false === Checker::getRet()) {
                $this->err = Checker::getError();
                return false;
            }
            $this->params[$name] = $value;
        }
        return true;
    }

    /**
     * 获取 $_SERVER 数组中数据
     * @param string $key
     * @return string
     */
    function __server_get_data($key = ""){
        if($key == ""){
            return $_SERVER;
        }
        if(! isset( $_SERVER[$key] )){
            return "";
        }
        return $_SERVER[$key];
    }

    protected function getDistricts() {
        $dists = Yii::$app->params['districts'];
        return json_decode( $dists,true);
    }

    /*
       protected function getDistrictRervMap( $provinceid = 0) {
       $dists = Yii::$app->params['districts'];
       $distArr = json_decode( $dists,true);
       ksort( $distArr );
       $res = ['100000' => ['name'=>'中国','id'=>'100000','parent'=> []]];
       foreach( $distArr as $k=>$v ){
       if( intval($k) != 100000 && ( intval($k) < $provinceid || abs(intval($k) - $provinceid) >= 10000 ) ){
       continue;    
       }
       ksort($v);
       foreach( $v as $vk=>$vv){
       $res[$vk] = ['name'=>$vv,'id'=>$vk,'parent'=> $res[$k]];    
       }    
       }
       return $res;
       }
     */

    protected function getDistrictRervMap( $provinceid = 0) {
        $dists = Yii::$app->params['districts'];

        $distArr = json_decode( $dists,true);
        krsort( $distArr );
        //$res = ['100000' => ['name'=>'中国','id'=>'100000','type'=>'parent','data'=> [],'list'=>[] ]];
        $res = [];
        foreach( $distArr['100000'] as $k=>$v ){
            if( !isset($res[$k]) ){
                $res[$k] =  ['name'=>$v,'id'=>$k,'type'=>'parent','data'=> [],'list'=>[] ];
            }
            if( !isset($distArr[$k]) ){
                continue;    
            }
            foreach( $distArr[$k] as $vk=>$vv){
                $res[$k]['list'][$vk] =  ['name'=>$vv,'id'=>$vk,'type'=>'parent','data'=> [],'list'=>[] ];
            }
        }
        /*
        foreach( $distArr as $k=>$v ){
            krsort($v);
            if( !isset($res[$k]) ){
                $res[$k] =  ['name'=>'','id'=>$k,'type'=>'parent','data'=> [],'list'=>[] ];
            }
            foreach( $v as $vk=>$vv){
                if( isset($res[$vk]) ){
                    $res[$vk]['name'] = $vv;
                }else{
                    $res[$vk] =  ['name'=>$vv,'id'=>$vk,'type'=>'parent','data'=> [],'list'=>[] ];
                }
                $res[$k]['list'][$vk] = $res[$vk];
                unset( $res[$vk] );
            }
        }
        */

        return $res[$provinceid];
    }

    //url级别权限
    function authControl($pid, $url) {
        if( in_array( $pid, Yii::$app->params['adminlist']) ) {
            return true;
        }

        $authService = new AuthService();
        //判断此url是否需要权限校验
        $needJudge = $authService->judgeUrlNeedAuth($url);
        if (count($needJudge) > 0) {
            //判断此用户是否有此URL的权限
            $haveAuth = $authService->getAuthListByRole($pid, $url);
            if (count($haveAuth) == 0) {
                return false;
            }
            return true;
        }
        return true;
    }

    function downloadExcel($newExcel, $filename)
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename="
                . $filename . date('Y-m-d') . '.Xlsx');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');
        // If you're serving to IE over SSL, then the following may be needed
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Expose-Headers: Content-Disposition');
        $objWriter = IOFactory::createWriter($newExcel, 'Xlsx');

        $objWriter->save('php://output');

        //通过php保存在本地的时候需要用到
        //$objWriter->save($dir.'/demo.xlsx');

        //以下为需要用到IE时候设置
        // If you're serving to IE 9, then the following may be needed
        //header('Cache-Control: max-age=1');
        // If you're serving to IE over SSL, then the following may be needed
        //header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        //header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        //header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        //header('Pragma: public'); // HTTP/1.0
        Yii::$app->end();
    }
} 
