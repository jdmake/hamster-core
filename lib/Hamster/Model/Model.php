<?php
// +----------------------------------------------------------------------
// | Author: jdmake <503425061@qq.com>
// +----------------------------------------------------------------------
// | Date: 2021/3/18
// +----------------------------------------------------------------------


namespace Hamster\Model;


use Hamster\Database\Db;

/**
 * Class Model
 * @package library\model
 *
 */
abstract class Model implements \JsonSerializable
{
    /** @var DB 数据操作对象 */
    private $db;

    protected $table = '';

    protected $pk = 'id';

    /** @var array 模型属性 */
    protected $attribute = [];

    private $pageStyle = 'default';

    /**
     * 构造函数
     * Model constructor.
     * @param array $attribute
     */
    public function __construct(array $attribute = [])
    {
        global $app;

        $config = $app['database'];

        $this->attribute = $attribute;
        $this->db = Db::create($config);
        $this->db->table($this->table);

        // 类型转换
        $schema = $this->db->querySchema();
        foreach ($this->attribute as $name => $value) {
            if (isset($schema[$name])) {
                switch ($schema[$name]) {
                    case 'int':
                    case 'smallint':
                    case 'bigint':
                        $value = intval($value);
                        break;
                    case 'double':
                        $value = doubleval($value);
                        break;
                    case 'tinyint':
                        $value = $value > 0 ? true : false;
                        break;
                    case 'datetime':
                        $value = date('Y-m-d H:i:s', strtotime($value));
                        break;
                    default:
                        break;
                }
                $this->attribute[$name] = $value;
            }
        }

    }

    /**
     * 获取表名
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * 获取DB对象
     * @return Db
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * 设置模型别名
     * @param string $alias
     * @return $this
     */
    public function alias($alias = '')
    {
        $this->db->alias($alias);
        return $this;
    }

    /**
     * 关联查询
     * @param $join
     * @param $condition
     * @param $type
     * @return $this
     */
    public function join($join, $condition, $type = 'inner')
    {
        $this->db->join($join, $condition, $type);
        return $this;
    }

    /**
     * 新建实例
     */
    public function newInstance(array $attribute = [])
    {
        $class = get_called_class();
        return new $class($attribute);
    }

    /**
     * 排序
     * @param $string
     */
    public function order($order)
    {
        $this->db->order($order);
        return $this;
    }

    /**
     * 获取全部数据
     */
    public function findAll()
    {
        $collection = [];
        $result = $this->db->getResult();
        if ($result) {
            foreach ($result as $item) {
                $collection[] = $this->newInstance($item);
            }
        }
        return $collection;
    }

    /**
     * 查询数量
     */
    public function count()
    {
        $this->db->field('count(*) as count');
        $result = $this->db->getResult();
        if ($result) {
            return intval($result[0]['count']);
        }
        return 0;
    }

    /**
     * 设置模型显示字段
     */
    public function field($field)
    {
        $this->db->field($field);
        return $this;
    }

    /**
     * 设置分页样式
     * @param $name
     */
    public function setPageStyle($name)
    {
        $this->pageStyle = $name;
        return $this;
    }

    /**
     * 分页查询
     * @param $page
     * @param int $limit
     */
    public function pagination($page, $limit = 10, $return_page = true, $script = false, array $option = [])
    {
        $option = array_merge([
            'return_page' => $return_page,
            'script' => $script,
        ], $option);

        $collection = [];
        $result = $this->db->pagination($page, $limit > 100 ? 100 : $limit, $option, $this->pageStyle);
        if ($result) {
            foreach ($result['items'] as $item) {
                $collection[] = $this->newInstance($item);
            }
        }
        if($result['total'] <= 0) {
            $result['page'] = '';
        }
        $result['items'] = new Collection($collection);
        return $result;
    }

    /**
     * 获取属性值
     */
    private function getAttrValue($name)
    {
        $new = $name;
        if (strpos('#' . $new, '_')) {
            $arr = explode('_', $new);
            $new = '';
            foreach ($arr as $item) {
                $new .= ucfirst($item);
            }
        } else {
            $new = ucfirst($new);
        }

        $method = 'getAttr' . $new;
        if (method_exists($this, $method)) {
            return $this->$method(isset($this->attribute[$name]) ? $this->attribute[$name] : null, $this->attribute);
        }

        return $this->attribute[$name];
    }

    /**
     * 返回 attribute 中的键值
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getAttrValue($name);
    }

    /**
     * 设置 attribute 中的键值
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->attribute[$name] = $value;
    }

    /**
     * where 语句
     * @param array $where
     * @return Model
     */
    public function where(array $where = [])
    {
        $values = [];
        foreach ($where as $k => $item) {
            $condition = '=';
            if (is_array($item) && count($item) == 2) {
                $condition = $item[0];
                $item = $item[1];
            }

            if (strtolower($k) == 'or') {
                $or = [];
                foreach ($item as $kk => $value) {
                    $or[] = "{$kk} {$condition} :{$kk}";
                    $values[] = [
                        'name' => $kk,
                        'value' => $value
                    ];
                }
                $this->db->where('or (' . join(' and ', $or) . ')');
            } else {
                $values[] = [
                    'name' => $k,
                    'value' => $item
                ];
                $this->db->where("{$k} {$condition} :{$k}");
            }
        }

        // 查询schema
        $schema = $this->db->querySchema();

        // 设置参数
        $parameter = [];
        foreach ($values as $value) {
            if (strpos('#' . $value['name'], '.')) {
                $value['name'] = explode('.', $value['name'])[1];
            }
            @$parameter[$value['name']] = "{$value['value']}::{$schema[$value['name']]}";
        }
        $this->db->setParameter($parameter);

        return $this;
    }

    public function likeWhere($field, $value)
    {
        $whereStr = '(';

        if (!\is_array($value)) {
            $value = explode(',', $value);
        }

        $values = [];
        foreach ($value as $k => $val) {
            $whereStr .= "{$field} like :{$field}{$k} or ";
            $values["{$field}{$k}"] = $val;
        }
        $whereStr = trim($whereStr, 'or ') . ')';

        $this->db->where($whereStr);

        $schema = $this->db->querySchema();

        // 设置参数
        $parameter = [];
        foreach ($values as $k => $value) {
            $parameter[$k] = "{$value}::{$schema[$field]}";
        }
        $this->db->setParameter($parameter);

        return $this;
    }

    public function inWhere($field, $value)
    {
        if (!\is_array($value)) {
            $value = explode(',', $value);
        }

        $whereStr = "{$field} in(";
        $values = [];
        foreach ($value as $k => $val) {
            $whereStr .= ":{$field}{$k},";
            $values["{$field}{$k}"] = $val;
        }
        $whereStr = trim($whereStr, ',');
        $whereStr .= ')';
        $this->db->where($whereStr);

        $schema = $this->db->querySchema();

        // 设置参数
        $parameter = [];
        foreach ($values as $k => $value) {
            $parameter[$k] = "{$value}::{$schema[$field]}";
        }
        $this->db->setParameter($parameter);

        return $this;
    }
    
    public function whereNotInSelect($field, $select_field, $select_table, $where)
    {
        $this->db->where("{$field} Not In(SELECT {$select_field} FROM {$select_table} $where)");
        return $this;
    }

    /**
     * 时间戳查询
     */
    public function whereTimestamp($field, $condition, $time) {
        $this->db->where("UNIX_TIMESTAMP(`{$field}`) {$condition} {$time}");
        return $this;
    }

    /**
     * 获取模型对象
     * @param null $pk_value
     */
    public function find($pk_value = null)
    {
        if (null != $pk_value) {
            $this->where([$this->pk => $pk_value]);
        }

        $result = $this->db->find();
        if ($result) {
            return $this->newInstance($result);
        }

        return null;
    }

    /**
     * 模型是否存在
     * @param $id
     * @return bool
     */
    public function exists($id)
    {
        return $this->where([$this->pk => $id])->find();
    }

    /**
     * 快速更新
     * @param array $data
     * @return string
     */
    public function update(array $data)
    {
        return $this->db->update($data);
    }

    /**
     * 保存模型
     */
    public function save()
    {
        if (empty($this->attribute[$this->pk])) {
            // 插入
            $this->db->beginTransaction();
            $result = $this->db->insert($this->attribute);
            $this->db->commit();
            $this->attribute[$this->pk] = $result;
            return $result;
        } else {
            // 更新
            $data = $this->attribute;
            unset($data[$this->pk]);
            $this->db->beginTransaction();
            $result = $this->db
                ->where("{$this->pk}=:id")
                ->setParameter(['id' => $this->attribute[$this->pk]])
                ->update($data);
            $this->db->commit();
            return $result;
        }
    }

    /**
     * 删除模型
     */
    public function delete()
    {
        $this->db->beginTransaction();
        $result = $this->db->delete();
        $this->db->commit();
        return $result;
    }

    /**
     * 根据PK字段值获取模型
     * @param $value
     * @return Model
     */
    public static function get($value)
    {
        $model = get_called_class();

        /** @var Model $model */
        $model = new $model;

        return $model->find($value);
    }

    /**
     * JSON 序列化
     * @return array|mixed
     * @throws \ReflectionException
     */
    public function jsonSerialize()
    {
        $result = [];
        foreach ($this->attribute as $k => $item) {
            $result[$k] = $this->getAttrValue($k);
        }
        return $result;
    }

    public function toArray()
    {
        $result = [];
        foreach ($this->attribute as $k => $item) {
            $result[$k] = $this->$k;
        }
        return $result;
    }

}
