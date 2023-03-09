<?php
declare(strict_types=1);

// +----------------------------------------------------------------------
// | Author: jdmake <503425061@qq.com>
// +----------------------------------------------------------------------
// | Date: 2021/7/18
// +----------------------------------------------------------------------

namespace Hamster\Util;

class DateUtil
{
    /**
     * 生成某个范围内的随机时间
     */
    static public function randomDate($begintime, $endtime="") {
        $begin = strtotime($begintime);
        $end = strtotime($endtime);
        $timestamp = rand($begin, $end);
        return date("Y-m-d H:i:s", $timestamp);
    }

    /**
     * 生成某个范围内的随机时间数组
     */
    static public function create_date_array($num = 2000 , $begintime, $endtime){
        $i=0;
        $date_array = array();
        while ($i < $num){
            $date = static::randomDate($begintime,$endtime);
            $date_array[$i]['time'] = $date;
            $i++;
        }
        sort($date_array);
        return $date_array;
    }

    /**
     * 统计数组中某字段的个数
     */
    static public function countArr($arr,$field = 'time'){
        $arr2 = array();
        foreach($arr as $k=>$v){
            foreach($v as $k2=>$v2){
                // d($k2);
                if($k2!=$field && $field != null){
                    continue;
                }
                if(!isset($arr2[$k2][$v2])){
                    $arr2[$k2][$v2] = 1;
                }else{
                    ++$arr2[$k2][$v2];
                }
            }
        }
        return $arr2;
    }
}
