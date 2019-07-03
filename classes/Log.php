<?php

namespace app\classes;

class Log
{

    // 常量定义
    const XH_LOG_FATAL                  = 0;
    const XH_LOG_WARNING                = 2;
    const XH_LOG_NOTICE                 = 4;
    const XH_LOG_TRACE                  = 6;
    const XH_LOG_DEBUG                  = 8;
    const XH_LOG_ALL                    = 10;

    private static $_log_max_file_size  = 10240000000;
    private static $_default_log        = array();
    private static $_log_dir            = '';
    private static $_log_id             = 0;
    private static $_log_project        = 'default';
    private static $_log_max_style;
    private static $_log_file_name      = array(
        self::XH_LOG_FATAL    => 'error',
        self::XH_LOG_WARNING  => 'error',
        self::XH_LOG_NOTICE   => 'notice',
        self::XH_LOG_TRACE    => 'debug',
        self::XH_LOG_DEBUG    => 'debug',
    );

    private static $_log_level          = array(
        self::XH_LOG_FATAL    => 'ERROR',
        self::XH_LOG_WARNING  => 'WARNING',
        self::XH_LOG_NOTICE   => 'NOTICE',
        self::XH_LOG_TRACE    => 'TRACE',
        self::XH_LOG_DEBUG    => 'DEBUG',
    );

    /**
     * 初始化日志所在项目、日志目录和需要记录的必要节点
     * @param $project
     * @param $log_dir
     * @param array $nodes
     * @param int $log_max_style
     */
    public static function init($project, $log_dir, $nodes=array(), $log_max_style=self::XH_LOG_ALL) {
        self::$_log_id = self::getLogId();

        if(!empty($nodes) && is_array($nodes)) {
            foreach($nodes as $node_name => $node_val) {
                self::$_default_log[$node_name] = $node_val;
            }
        }

        self::$_log_dir 	  = $log_dir;
        self::$_log_project   = $project;
        self::$_log_max_style = $log_max_style;
    }


    /**
     * 添加一个日志节点 (key=>value)
     * @param $node_name
     * @param $node_val
     */
    public static function addLogNode($node_name, $node_val) {
        self::$_default_log[$node_name] = $node_val;
    }

    /**
     * 打印notice日志
     * @param $log_str
     * @param string $file_name_ext
     */
    public static function notice($log_str, $file_name_ext='')  {
        $file = self::getLogFileName(self::XH_LOG_NOTICE, $file_name_ext);
        self::writeLog(self::XH_LOG_NOTICE, $file, $log_str);
    }

    /**
     * 打印fatal日志
     * @param $log_str
     */
    public static function fatal($log_str) {
        $traces = self::getMethodInfo();
        $ext_info = array();
        if(!empty($traces['class']) && !empty($traces['function']))  {
            $ext_info['method'] = $traces['class'].".".$traces['function'];
        }

        if(!empty($traces['file'])) {
            $ext_info['file'] = $traces['file'];
        }

        if(!empty($traces['line']))  {
            $ext_info['line'] = $traces['line'];
        }

        $file = self::getLogFileName(self::XH_LOG_FATAL);
        self::writeLog(self::XH_LOG_FATAL, $file, $log_str, $ext_info);
    }


    /**
     * 打印warning日志
     * @param $log_str
     */
    public static function warning($log_str) {
        $traces = self::getMethodInfo();
        $ext_info = array();
        if(!empty($traces['class']) && !empty($traces['function'])) {
            $ext_info['method'] = $traces['class']."::".$traces['function'];
        }

        if(!empty($traces['file'])) {
            $ext_info['file'] = $traces['file'];
        }

        if(!empty($traces['line'])) {
            $ext_info['line'] = $traces['line'];
        }

        $file = self::getLogFileName(self::XH_LOG_WARNING);
        self::writeLog(self::XH_LOG_WARNING, $file, $log_str, $ext_info);
    }


    /**
     * 打印trace日志
     * @param $log_str
     */
    public static function trace($log_str) {
        $file = self::getLogFileName(self::XH_LOG_TRACE);
        self::writeLog(self::XH_LOG_TRACE, $file, $log_str);
    }

    /**
     * 打印debug日志
     * @param $log_str
     */
    public static function debug($log_str) {
        $file = self::getLogFileName(self::XH_LOG_DEBUG);
        self::writeLog(self::XH_LOG_DEBUG, $file, $log_str);
    }

    /**
     * 获取日志ID (生成日志ID)
     * @access public
     * @return int
     */
    public static function getLogId() {
        if(!empty(self::$_log_id)) {
            return self::$_log_id;
        }

        if(!empty($_SERVER['XH_REQUEST_ID'])) {
            self::$_log_id = $_SERVER['XH_REQUEST_ID'];
            return self::$_log_id;
        }
        elseif(!empty($_SERVER['HTTP_XH_REQUEST_ID'])) {
            self::$_log_id = $_SERVER['HTTP_XH_REQUEST_ID'];
            return self::$_log_id;
        }
        if(!empty($_SERVER['XH_LOG_ID'])) {
            self::$_log_id = $_SERVER['XH_LOG_ID'];
            return self::$_log_id;
        }
        elseif(!empty($_SERVER['HTTP_XH_LOG_ID'])) {
            self::$_log_id = $_SERVER['HTTP_XH_LOG_ID'];
            return self::$_log_id;
        }

        self::$_log_id = self::createLogId();
        return self::$_log_id;
    }

    /**
     * 生成Logid
     * @access private
     * @return int
     */
    private static function createLogId()  {
        return intval(microtime(true) * 1000) . posix_getpid();
    }


    /**
     * 获取日志操作文件名获取日志操作文件名
     * @param $log_style
     * @param string $file_name_ext
     * @return string
     */
    private static function getLogFileName($log_style, $file_name_ext='') {
        $prefix = empty(self::$_log_project) ? 'default' : self::$_log_project;
        $prefix .= '.';
        $prefix .= empty(self::$_log_file_name[$log_style]) ? 'all' : self::$_log_file_name[$log_style];
        if($file_name_ext != '') {
            $prefix .= '.'.$file_name_ext;
        }

        return $prefix.".log.".date("Ymd");
    }


    /**
     * 写日志到文件
     * @param $log_style
     * @param $file_name
     * @param $log_str
     * @param array $options
     * @return bool|void
     */
    private static function writeLog($log_style, $file_name, $log_str, $options=array()) {
        if(empty(self::$_log_dir) || !is_dir(self::$_log_dir)) {
            return;
        }

        if($log_style > self::$_log_max_style) {
            return;
        }

        $log_level = self::$_log_level[$log_style] ? self::$_log_level[$log_style] : $log_style;
        $log_file = self::$_log_dir."/".$file_name;

        $msg_str = '';
        if(!empty($options)) {
            foreach($options as $ext_key => $ext_val) {
                $msg_str .= $ext_key."=\"".$ext_val."\"\t";
            }
        }

        $outside_str = $log_str;
        if(is_array($log_str)) {
            $outside_str = '';
            foreach($log_str as $log_key => $log_val) {
                $outside_str .= is_array($log_val) ? $log_key."=".json_encode($log_val) : $log_key."=".$log_val;
                $outside_str .= " ";
            }
        }

        $msg_str .= $outside_str;

        $str = sprintf("[%s] [%s] [%s] [%s] [%s]\n", $log_level, date("Y-m-d H:i:s"), self::getLogId(), self::getDefaultLogStr(), trim($msg_str));

        $file_exit = true;
        if(!file_exists($log_file)) {
            $file_exit = false;
        }

        @clearstatcache();
        $file_stats = @stat($log_file);
        if (is_array($file_stats) && floatval($file_stats['size']) > self::$_log_max_file_size) {
            unlink($log_file);
            $file_exit = false;
        }

        $result = error_log($str, 3, $log_file);
        if(!$file_exit && file_exists($log_file)) {
            chmod($log_file, 0777);
        }

        return $result;
    }

    /**
     * 获取生成好的日志消息字符串
     * @access private
     * @return string
     */
    private static function getDefaultLogStr() {
        $str = '';
        $default_arr = array();
        if(!empty(self::$_default_log)) {
            foreach(self::$_default_log as $default_key => $default_val) {
                $default_arr[] = $default_key."=".$default_val;
            }
        }

        if(!empty($default_arr)) {
            $str = implode(" ", $default_arr);
        }

        return $str;
    }


    /**
     * 获取当前操作的堆栈信息
     * @param int $level
     * @return mixed
     */
    private static function getMethodInfo($level=0) {
        $back_trace = debug_backtrace();
        $depth = $level + 2;
        $trace_depth = count($back_trace);
        if ($depth >= $trace_depth) {
            $depth = $trace_depth;
        }

        $result = $back_trace[$depth];
        if (isset($result['file'])) {
            $result['file'] = basename($result['file']);
        }

        return $result;
    }

    /**
     * 调用外部api接口日志打印
     * @param $prefix
     * @param $message
     * @param $fileName
     */
    public static function extApiLog($prefix, $message, $fileName) {
        global $logDir;
        $logFile = $logDir . DIRECTORY_SEPARATOR . $fileName . '.' . $prefix . '.log.' . date('Ymd');
        $logMsg = sprintf("[%s] [%s] [%s] %s", self::getLogId(), date('Y-m-d H:i:s'), $prefix, $message);
        $logMsg = str_replace(PHP_EOL, '', $logMsg);
        file_put_contents($logFile, $logMsg."\n", FILE_APPEND);
    }

}