<?php
declare(strict_types=1);

// +----------------------------------------------------------------------
// | Author: jdmake <503425061@qq.com>
// +----------------------------------------------------------------------
// | Date: 2021/7/18
// +----------------------------------------------------------------------


namespace Hamster\Util;


final class FileUtil
{

    /**
     * 递归创建文件
     * @param string $dir   创建路径
     * @param int $mode     权限
     * @return bool
     */
    static function mkdirss($dir, $mode = 0777)
    {
        if (!is_dir($dir)) {
            static::mkdirss(dirname($dir), $mode);
            return @mkdir($dir, $mode);
        }
        return true;
    }


}
