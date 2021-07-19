<?php
declare(strict_types=1);

// +----------------------------------------------------------------------
// | Author: jdmake <503425061@qq.com>
// +----------------------------------------------------------------------
// | Date: 2021/4/23
// +----------------------------------------------------------------------


namespace Hamster\Log;

/**
 * 日志记录器
 * Class logger
 * @package library
 */
class logger
{
    private static $instance;

    const LOGGER_LEVEL = [
        1 => 'error',
        2 => 'warning',
        3 => 'notice',
        4 => 'info',
        5 => 'debug',
    ];

    private $config = [
        'dir' => '',
        'max_size' => 10240,
        'level' => 5
    ];

    /**
     * logger constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }


    /**
     * 创建实例
     * @return logger
     */
    static public function create(array $config = [])
    {
        if (!self::$instance instanceof Logger) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * 打印日志
     * @param $data
     */
    public function log($data)
    {
        $file = date('Y-m-d', time());

        if(!is_dir($this->config['dir'])) {
            mkdir($this->config['dir'], 0755);
        }

        file_put_contents($this->config['dir'] . '/' . $file . '.log', '['. date('Y-m-d H:i:s', time()) .'] ' . $data . "\n", FILE_APPEND);
    }

}
