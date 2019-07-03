<?php
namespace app\classes;

class Util {
    // 校验日期格式XXXX-XX-XX
    public static function checkDate($date) {
        $newDate = date('Y-m-d', strtotime($date));
        if ($date == $newDate) {
            return true;
        } else {
            return false;
        }
    }
} 
