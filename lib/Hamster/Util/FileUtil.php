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
     * @param string $dir 创建路径
     * @param int $mode 权限
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

    /**
     * 文件上传
     * @param $maxsize
     * @param array $types
     * @param $file
     * @return bool
     * @throws \Exception
     */
    static function fileUpload($maxsize, array $types, $file = '')
    {
        $files = array_values($_FILES)[0];

        if ($files['size'] > $maxsize) {
            throw new \Exception(sprintf('上传的文件大小超过：%sM',
                $maxsize / 1024 / 1024
            ));
        }

        $type = explode('/', $files['type'])[1];
        if (!in_array($type, $types)) {
            throw new \Exception(sprintf('上传的文件类型不正确,支持的格式：%s',
                join('、', $types)
            ));
        }

        $config = include APP_ROOT . '/config.php';
        $save_dir = $config['upload_save_dir'] . '/' . date('Y-m-d', time());

        if (empty($file)) {
            $file = md5($files['name']) . '.' . explode('.', $files['name'])[1];
        }
        if (!is_dir($save_dir)) {
            static::mkdirss($save_dir);
        }

        if (move_uploaded_file($files['tmp_name'], $save_dir . '/' . $file)) {
            return '/upload/' . date('Y-m-d', time()) . '/' . $file;
        }

        return false;
    }

}
