<?php
declare(strict_type=1);

// +----------------------------------------------------------------------
// | Author: jdmake <503425061@qq.com>
// +----------------------------------------------------------------------
// | Date: 2021/3/16
// +----------------------------------------------------------------------


namespace Hamster\Template;

use Hamster\Util\FileUtil;
use phpDocumentor\Reflection\Types\True_;

define('HAMSTER_PATH', true);

/**
 * 模板引擎
 * Class Template
 * @package library\template
 */
class Template
{
    private static $instance;

    private $config = [
        'cache_dir' => '',
        'views_absolute_path' => '',
        'views_path' => 'views',
        'html_suffix' => '.html',
        'function_prefix' => '{%',
        'function_suffix' => '%}',
        'var_prefix' => '{{',
        'var_suffix' => '}}',
    ];

    // 模板变量
    private $vars = [];

    // 模板文件载入记录
    private $includes = [];

    /**
     * 快速创建实体
     * @return Template
     */
    public static function create($config = [])
    {
        if (!self::$instance instanceof Template) {
            self::$instance = new static($config);
        }
        return self::$instance;
    }

    /**
     * 构造函数
     * Template constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 赋值模板变量
     * @param $name
     * @param $val
     */
    public function assign($name, $val)
    {
        $this->vars[$name] = $val;
    }

    /**
     * 渲染模板
     * @param $template
     * @param array $vars
     */
    public function render($template, $vars = [])
    {
        $this->vars = array_merge($this->vars, $vars);

        if (!empty($this->config['views_absolute_path'])) {
            $template = $this->config['views_absolute_path'] . $template . $this->config['html_suffix'];
        }

        if (!file_exists($template)) {
            throw new \Exception(
                sprintf('模板文件不存在, "%s"', $template)
            );
        }

        $template = $this->parseTemplateFile($template);

        $cacheFile = $this->getCacheDir() . md5($template) . '.php';

        if (!$this->checkCache($cacheFile)) {
            $content = file_get_contents($template);
            $this->compiler($content, $cacheFile);
        }

        extract($vars, EXTR_SKIP);
        include $cacheFile;

        // 页面缓存
        ob_start();
        ob_implicit_flush(0);
        // 获取并清空缓存
        $content = ob_get_clean();
        return $content;
    }

    /**
     * 解析模板文件
     * @param $template
     * @return mixed
     */
    private function parseTemplateFile($template)
    {
        $this->includes[$template] = filemtime($template);
        return $template;
    }

    /**
     * 编译模板文件内容
     * @param $content
     * @param $cacheFile
     */
    private function compiler(&$content, $cacheFile)
    {
        $this->parse($content);
        $content = preg_replace('/\?>\s*<\?php\s(?!echo\b)/s', '', $content);
        $content = '<?php if (!defined(\'HAMSTER_PATH\')) exit(); /*' . serialize($this->includes) . '*/ ?>' . "\n" . $content;
        $this->includeFile = [];
        FileUtil::mkdirss(dirname($cacheFile));
        file_put_contents($cacheFile, $content);
        return;
    }

    /**
     * 模板解析
     * @param $content
     */
    private function parse(&$content)
    {
        // 内容为空不解析
        if (empty($content)) {
            return;
        }

        /**
         * 解析Include标签
         */
        $this->parseIncludeTag($content);

        /**
         * 解析默认标签
         */
        $this->parseDefaultTag($content);
    }

    /**
     * 解析默认标签
     * @param $content
     */
    private function parseDefaultTag(&$content)
    {
        $content = preg_replace_callback('/' . "{$this->config['var_prefix']}(.*?){$this->config['var_suffix']}" . '/is', function ($matches) {
            $result = trim($matches[1]);
            if (preg_match('/\$([^\s]+)/is', $result, $matches)) {
                $result = str_replace($matches[0], $this->_setVar($matches[0]), $result);
            }
            return "<?php echo " . $result . "; ?>";
        }, $content);


        $content = preg_replace_callback('/' . "{$this->config['function_prefix']}(.*?){$this->config['function_suffix']}" . '/is', function ($matches) {
            if (!strstr($matches[1], 'end')) {
                $result = trim($matches[1]);
                if (preg_match('/\$([^\s]+)/is', $result, $matches)) {
                    $result = str_replace($matches[0], $this->_setVar($matches[0]), $result);
                }
                return '<?php ' . $result . ': ?>';
            } else {
                return '<?php ' . trim($matches[1]) . '; ?>';
            }
        }, $content);
    }

    /**
     * 解析include标签
     * @param $content
     */
    private function parseIncludeTag(&$content)
    {
        $content = preg_replace_callback('/' . "{$this->config['function_prefix']}(.*?){$this->config['function_suffix']}" . '/is', function ($matches) {
            if (strstr($matches[1], 'include')) {
                if (preg_match('/\'(.*?)\'/is', trim($matches[1]), $matches)) {
                    $include = trim($matches[1]);
                    $cacheFile = $this->includeTmplate($include);
                }
                return "<?php include '" . $cacheFile . "'; ?>";
            }
            return $matches[0];
        }, $content);
    }

    /**
     * include 模板
     * @param $include
     * @return string
     * @throws \Exception
     */
    private function includeTmplate($include)
    {
        ob_start();
        $this->render($include, $this->vars);
        ob_end_clean();
        if (!empty($this->config['views_absolute_path'])) {
            $template = $this->config['views_absolute_path'] . $include . $this->config['html_suffix'];
        }
        return $this->getCacheDir() . md5($template) . '.php';
    }

    /**
     * 获取缓存目录
     * @return string
     */
    private function getCacheDir()
    {
        if (empty($this->config['cache_dir'])) {
            throw new \Exception('缓存目录不能为空');
        }
        return $this->config['cache_dir'];
    }

    /**
     * 检查编译缓存是否有效
     * @param $cacheFile
     */
    private function checkCache($cacheFile)
    {
        // 缓存文件不存在
        if (!is_file($cacheFile)) {
            return false;
        }
        // 读取缓存文件失败
        if (!$handle = @fopen($cacheFile, "r")) {
            return false;
        }

        // 读取第一行
        preg_match('/\/\*(.+?)\*\//', fgets($handle), $matches);
        if (!isset($matches[1])) {
            return false;
        }
        $includeFile = unserialize($matches[1]);
        if (!is_array($includeFile)) {
            return false;
        }
        // 检查模板文件是否被更新
        foreach ($includeFile as $path => $time) {
            if (is_file($path) && filemtime($path) > $time) {
                // 缓存需要更新
                return false;
            }
        }
        return true;
    }

    private function _setVar($str)
    {
        if (strpos('#' . $str, '.')) {
            $result = '';
            $arr = explode('.', $str);
            foreach ($arr as $k => $item) {
                if ($k <= 0) {
                    $result .= "{$item}";
                } else {
                    $result .= "['{$item}']";
                }
            }
            return $result;
        } else {
            return $str;
        }
    }

    /**
     * 替换符号
     * @param $str
     * @return string|string[]|null
     */
    private function replaceSymbol($str)
    {
        return preg_replace(["/'/", '/"/'], '', $str);
    }


}
