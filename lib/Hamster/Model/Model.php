<?php
// +----------------------------------------------------------------------
// | Author: jdmake <503425061@qq.com>
// +----------------------------------------------------------------------
// | Date: 2021/3/18
// +----------------------------------------------------------------------


namespace Database\Model;


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

    /**
     * 构造函数
     * Model constructor.
     * @param array $attribute
     */
    public function __construct(array $attribute = [])
    {
        $this->attribute = $attribute;
        $this->db = Db::create();
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
        if($result) {
            return intval($result[0]['count']);
        }
        return 0;
    }

    /**
     * 分页查询
     * @param $page
     * @param int $limit
     */
    public function pagination($page, $limit = 10)
    {
        $collection = [];
        $result = $this->db->pagination($page, $limit > 100 ? 100 : $limit, ['return_page' => false]);
        if ($result) {
            foreach ($result['items'] as $item) {
                $collection[] = $this->newInstance($item);
            }
        }
        $result['items'] = $collection;
        return $result;
    }

    /**
     * 获取属性值
     */
    private function getAttrValue($name)
    {
        $method = 'getAttr' . ucfirst($name);
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
                    $or[] = "{$kk} {$condition} ?";
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
                $this->db->where("{$k} {$condition} ?");
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
            $parameter[] = "{$value['value']}::{$schema[$value['name']]}";
        }
        $this->db->setParameter($parameter);

        return $this;
    }

    /**
     * 获取模型对象
     * @param null $pk_value
     */
    public function find($pk_value = null)
    {
        if (!empty($pk_value)) {
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
            $result[$k] = $this->$k;
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
