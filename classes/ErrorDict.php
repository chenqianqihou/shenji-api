<?php
namespace app\classes;

class ErrorDict {

    // 成功
    const SUCCESS = 0;

    // 通用错误号
    const G_SYS_ERR = 10000; // 系统错误
    const G_METHOD = 10001; // 请求方法错误
    const G_PARAM = 10002; // 参数错误

    // 200
    const SUCC_OK = 20000;
    // 400
    const ERR_BODY_FORMAT_INVALID = 40001;

    // 401
    const ERR_AUTH_REQUEST_INVALID = 40101;

    // 403
    const ERR_NOPERMISSION = 40301;

    // 404
    const ERR_VERSION_NON_EXIST = 40401;

    // 405
    const ERR_UNSUPPORT_METHOD = 40501;

    // 409
    const ERR_SOURCE_ALREADY_EXIST = 40901;

    // 421
    const ERR_TOOMANEY_IPCONNECTIONS = 42101;

    // 500
    const ERR_INTERNAL = 50001;


    // 错误信息
    protected static $returnMessage = array(
        // 成功
        self::SUCCESS => 'success',

        // 通用错误
        self::G_SYS_ERR => '内部系统错误',
        self::G_METHOD => '请求方法错误',
        self::G_PARAM => '请求参数不合法',
    );

    // 返回给用户的错误信息
    protected static $returnUserMessage = array(
        // 成功
        self::SUCCESS => '操作成功',

        // 通用错误
        self::G_SYS_ERR => '系统错误，请稍后再试',
        self::G_METHOD => '非法访问',
        self::G_PARAM => '参数错误',
    );

    protected static $defaultMsg = '未知错误';

    protected static $defaultUserMsg = '未知错误';

    public static function getError($no, $msg = '', $userMsg = '') {
        if ($msg == '') {
            if (isset(self::$returnMessage[$no])) {
                $msg = self::$returnMessage[$no];
            } else {
                $msg = self::$defaultMsg;
            }
        }

        if ($userMsg == '') {
            if (isset(self::$returnUserMessage[$no])) {
                $userMsg = self::$returnUserMessage[$no];
            } else {
                $userMsg = self::$defaultUserMsg;
            }
        }

        return array(
            'returnCode' => $no,
            'returnMessage' => $msg,
            'returnUserMessage' => $userMsg,
        );
    }
}