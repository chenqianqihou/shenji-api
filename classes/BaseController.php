<?php

namespace app\classes;

use Yii;
use yii\web\Controller;
use yii\base\Exception;
use yii\web\Response;
use \Lcobucci\JWT\Parser;
use \Lcobucci\JWT\Signer\Hmac\Sha256;

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
            $userData = $parse->getClaims();
            $this->data['ID'] = $userData['ID'];
            $this->data['name'] = $userData['name'];
        } catch (Exception $e) {
            Log::addLogNode('Invalid token', '');
            $this->noLogin();
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
        $error = ErrorDict::getError(ErrorDict::G_PARAM, '', '用户未登录');
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

        // 请求参数校验
        if (empty($this->params)) {
            return true;
        }

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
} 
