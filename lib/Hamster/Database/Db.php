<?php
declare(strict_types=1);

// +----------------------------------------------------------------------
// | Author: jdmake <503425061@qq.com>
// +----------------------------------------------------------------------
// | Date: 2021/3/17
// +----------------------------------------------------------------------


namespace Hamster\Database;

class Db
{
    /** @var Db 实例对象 */
    private static $instance;

    /** @var DbDrive */
    private $connect;

    /** @var array 参数表 */
    private $options = [
        'table' => '',
        'alias' => '',
        'join' => '',
        'where' => [],
        'field' => '*',
        'limit' => '',
        'order' => '',
        'parameter' => [],
        'insert_field' => [],
        'insert_values' => [],
        'update_set' => [],
        'sql' => '',
    ];

    /** @var int 操作方式 */
    private $action_type = 0;
    const ACTION_TYPE_SELECT = 1;
    const ACTION_TYPE_INSERT = 2;
    const ACTION_TYPE_UPDATE = 3;
    const ACTION_TYPE_DELETE = 4;


    /** @var string SQL语句 */
    private $query = '';


    /**
     * 构造函数
     * Db constructor.
     * @param DbDrive $connect
     */
    public function __construct(DbDrive $connect)
    {
        $this->connect = $connect;
    }

    public function query($sql)
    {
        $this->query = $sql;
        return $this;
    }

    /**
     * 创建实体
     */
    public static function create(array $config)
    {
        return new Db(new DbDrive($config));
    }

    /**
     * 创建原生SQL
     * @param $sql
     * @return $this
     */
    public function createNativeSql($sql)
    {
        $this->options['sql'] = $sql;
        return $this;
    }

    /**
     * 设置查询的表名称
     * @param $table_name
     * @return $this
     */
    public function table($table_name)
    {
        $this->options['table'] = $table_name;

        return $this;
    }

    /**
     * 设置主表别名
     * @param string $alias
     * @return $this
     */
    public function alias($alias = '')
    {
        $this->options['alias'] = $alias;

        return $this;
    }

    /**
     * 设置字段
     * @param string $field
     */
    public function field($field = "*")
    {
        $this->options['field'] = $field;

        return $this;
    }

    /**
     * 设置查询条件
     * @param string $field
     * @return $this
     */
    public function where($where)
    {
        $this->options['where'][] = $where;

        return $this;
    }

    /**
     * 设置查询预处理参数
     * @param array $parameter
     * @return Db
     */
    public function setParameter(array $parameter = [])
    {
        $this->options['parameter'] = array_merge($this->options['parameter'], $parameter);
        return $this;
    }

    /**
     * 添加查询预处理参数
     * @param array $parameter
     * @return Db
     */
    public function addParameter($name, $value)
    {
        $this->options['parameter'][$name] = $value;
        return $this;
    }

    /**
     * 设置排序
     * @param string $order
     */
    public function order($order)
    {
        $this->options['order'] = 'order by ' . str_replace('order by ', '', $order);

        return $this;
    }

    /**
     * 设置查询数量
     * @param string $order
     */
    public function limit($limit)
    {
        $this->options['limit'] = 'limit ' . $limit;

        return $this;
    }

    /**
     * 返回结果集
     */
    public function getResult()
    {
        $this->action_type = Db::ACTION_TYPE_SELECT;
        $sql = $this->buildQuery();

        // 执行查询
        $this->connect->query($this->query, $this->options['parameter']);
        $this->clear();
        $res = $this->connect->getfetchAll();

        return $res;
    }

    /**
     * 原始查询
     */
    public function select()
    {
        // 执行查询
        $this->connect->query($this->query, $this->options['parameter']);
        $this->clear();
        $res = $this->connect->getfetchAll();
        return $res;
    }

    /**
     * 查找一行数据
     */
    public function find()
    {
        $result = $this->getResult();
        return count($result) > 0 ? $result[0] : null;
    }

    /**
     * 关联查询
     * @param $join
     * @param $condition
     * @param string $type
     * @return $this
     */
    public function join($join, $condition, $type = 'inner')
    {
        $this->options['join'] = "{$type} join {$join} on {$condition}";
        return $this;
    }

    /**
     * 插入数据
     * @param array $array
     */
    function insert(array $data = [])
    {
        $schema = $this->querySchema();
        // 设置参数
        $parameter = [];
        foreach ($data as $name => $value) {
            $parameter[$name] = "{$value}::{$schema[$name]}";
            $this->options['insert_values'][] = ':' . $name;
        }
        $this->setParameter($parameter);

        $this->options['insert_field'] = array_keys($data);

        $this->action_type = Db::ACTION_TYPE_INSERT;
        $this->buildQuery();

        // 执行插入
        $this->connect->query($this->query, $this->options['parameter']);
        $this->clear();
        $res = $this->connect->insert();
        return $res;
    }

    /**
     * 更新数据
     * @param array $data
     * @return string
     * @throws \Exception
     */
    public function update(array $data = [])
    {
        $schema = $this->querySchema();
        // 设置参数
        $parameter = [];
        foreach ($data as $name => $value) {
            $parameter[$name] = "{$value}::{$schema[$name]}";
            $this->options['update_set'][] = "{$name}=:{$name}";
        }

        $this->setParameter($parameter);

        $this->action_type = Db::ACTION_TYPE_UPDATE;
        $this->buildQuery();

        // 执行更新
        $this->connect->query($this->query, $this->options['parameter']);
        $this->clear();
        return $this->connect->update();
    }

    /**
     * 删除数据
     */
    public function delete()
    {
        $this->action_type = Db::ACTION_TYPE_DELETE;
        $this->buildQuery();

        // 执行更新
        $this->connect->query($this->query, $this->options['parameter']);
        $this->clear();
        return $this->connect->update();
    }

    /**
     * 获取数量
     */
    public function count()
    {
        $this->options['limit'] = '';
        $this->options['field'] = 'count(' . $this->options['field'] . ') as total';
        return $this->getResult()[0]['total'];
    }

    /**
     * 分页
     */
    public function pagination($page, $limit = 15, array $option = [], $style)
    {
        $option['return_page'] = isset($option['return_page']) ? $option['return_page'] : true;

        $path_info = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'])['path'] : '/';

        $path = isset($option['path']) ? $option['path'] : $path_info . '?' . http_build_query(isset($option['query']) ?: []);

        $limit_page = ($page == 1 ? 0 : $page - 1) * $limit;

        $this->action_type = Db::ACTION_TYPE_SELECT;
        $this->limit("{$limit_page},{$limit}");
        $this->buildQuery();
        // 执行查询
        $this->connect->query($this->query, $this->options['parameter']);
        $results = $this->connect->getfetchAll();
        $total = $this->count();

        $styleClass = 'Hamster\\Model\\Pagination\\' . ucfirst($style) . 'StylePagination';
        $styleClass = new $styleClass();

        return [
            'items' => $results,
            'pageSize' => ceil($total / $limit),
            'total' => $total,
            'limit' => $limit,
            'page' => $styleClass->getPage($path, $page, $total, $limit, $option)
        ];
    }

    /**
     * 执行SQL语句
     * @param $sql
     */
    public function exec($sql)
    {
        $this->connect->exec($sql);
    }

    /**
     * 编译SQL语句
     */
    public function buildQuery()
    {
        switch ($this->action_type) {
            case self::ACTION_TYPE_SELECT:
                if (empty($this->options['sql'])) {
                    $this->query = sprintf(
                        'select %s from `%s` %s %s where %s %s %s',
                        $this->options['field'],
                        $this->options['table'],
                        $this->options['alias'],
                        $this->options['join'],
                        str_replace('and or', 'or', join(' and ', $this->options['where']) ?: '1=1'),
                        $this->options['order'],
                        $this->options['limit']
                    );
                } else {
                    $this->query = $this->options['sql'] . ' ' . $this->options['order'] . ' ' . $this->options['limit'];
                }
                break;
            case self::ACTION_TYPE_INSERT:
                $this->query = sprintf(
                    'insert into `%s`(%s) values(%s)',
                    $this->options['table'],
                    join(',', $this->options['insert_field']),
                    join(',', $this->options['insert_values'])
                );
                break;
            case self::ACTION_TYPE_UPDATE:
                $this->query = sprintf(
                    'update `%s` set %s where %s',
                    $this->options['table'],
                    join(',', $this->options['update_set']),
                    str_replace('and or', 'or', join(' and ', $this->options['where']) ?: '1=1')
                );
                break;
            case self::ACTION_TYPE_DELETE:
                if (count($this->options['where']) <= 0) {
                    throw new \Exception('删除时必须附加条件');
                }
                $this->query = sprintf(
                    'delete from `%s` where %s',
                    $this->options['table'],
                    str_replace('and or', 'or', join(' and ', $this->options['where']) ?: '1=1')
                );
                break;
            case 0:
            default:
                throw new \Exception('查询方式不正确');
        }
        $this->action_type = 0;
        return $this->query;
    }

    private function clear()
    {
        $this->options = array_merge($this->options, [
            'where' => [],
            'join' => '',
            'field' => '*',
            'limit' => '',
            'order' => '',
            'parameter' => [],
            'insert_field' => [],
            'insert_values' => [],
            'update_set' => []
        ]);
    }

    public function getQuery()
    {
        return $this->buildQuery();
    }

    public function querySchema()
    {
        $cache = APP_ROOT . '/resources/cache/data/' . 'query_schema_' . $this->options['table'] . '.php';
        if (is_file($cache)) {
            return unserialize(file_get_contents($cache));
        }
        $this->connect->query("select `COLUMN_NAME`,`DATA_TYPE` from information_schema.columns where table_name='{$this->options['table']}'");
        $res = $this->connect->getfetchAll();
        $result = [];

        foreach ($res as $re) {
            $result[$re['COLUMN_NAME']] = $re['DATA_TYPE'];
        }

        if (!empty($this->options['join'])) {
            // 获取关联表名称
            if (preg_match('/join\s+(.*?)\s+on/is', $this->options['join'], $matches)) {
                list($table) = explode(' ', $matches[1]);
                $this->connect->query("select `COLUMN_NAME`,`DATA_TYPE` from information_schema.columns where table_name='{$table}'");
                $res = $this->connect->getfetchAll();
                foreach ($res as $re) {
                    $result[$re['COLUMN_NAME']] = $re['DATA_TYPE'];
                }
            }
        }
        if (!is_dir(APP_ROOT . '/resources/cache/data/')) {
            mkdirss(APP_ROOT . '/resources/cache/data/');
        }
        file_put_contents($cache, serialize($result));
        return $result;
    }


    /**
     * 开始事务
     * @return bool
     */
    public function beginTransaction()
    {
        return $this->connect->beginTransaction();
    }

    /**
     * 提交事务
     * @return bool
     */
    public function commit()
    {
        return $this->connect->commitTransaction();
    }

    /**
     * 事务回滚
     * @return bool
     */
    public function rollBack()
    {
        return $this->connect->rollBackTransaction();
    }


}
