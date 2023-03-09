<?php
declare(strict_types=1);

// +----------------------------------------------------------------------
// | Author: jdmake <503425061@qq.com>
// +----------------------------------------------------------------------
// | Date: 2021/3/17
// +----------------------------------------------------------------------

namespace Hamster\Database;


class DbDrive
{
    /** @var array 数据库配置信息 */
    private $config = [
        'db_type' => 'mysql',
        'host' => '127.0.0.1',
        'port' => '3306',
        'dbname' => '',
        'username' => 'root',
        'password' => '88888888Ab',
        'charset' => 'utf8mb4',
    ];

    /** @var \PDO */
    private $pdo;

    /** @var \PDOStatement */
    private $statement;


    /**
     * 构造函数
     * DbDrive constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        $conf = $this->config;
        $dsn = "{$conf['db_type']}:host={$conf['host']};port={$conf['port']};dbname={$conf['dbname']};charset={$conf['charset']}";
        $this->pdo = new \PDO($dsn, $this->config['username'], $this->config['password'], [
            \PDO::ATTR_PERSISTENT => true
        ]);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }


    /**
     * 执行SQL查询语句
     * @param $sql
     * @param array $parameter
     * @return $this
     */
    public function query($sql, $parameter = [])
    {

        // 准备SQL模板
        $this->statement = $this->pdo->prepare($sql);
        // 绑定参数
        foreach ($parameter as $k => $item) {
            if (is_int($k)) {
                $k = $k + 1;
            }
            if (strpos('#' . $item, '::')) {
                list($value, $type) = explode('::', $item);
                switch ($type) {
                    case 'int':
                    case 'bigint':
                    case 'smallint':
                    case 'tinyint':
                        $type = \PDO::PARAM_INT;
                        break;
                    case 'datetime':
                        $type = \PDO::PARAM_STR;
                        break;
                    default:
                        $type = \PDO::PARAM_STR;
                        break;
                }
                $this->statement->bindValue($k, $value, $type);
            } else {
                $this->statement->bindValue($k, $item);
            }

        }

        return $this;
    }

    /**
     * 执行SQL语句
     * @param $sql
     */
    public function exec($sql)
    {
        return $this->pdo->exec($sql);
    }

    /**
     * 从结果集中获取一行
     * @return mixed
     */
    public function getfetch()
    {
        //执行预处理语句
        $this->statement->execute();

        $result = $this->statement->fetch(\PDO::FETCH_ASSOC);
        //释放查询结果
        $stmt = null;
        //关闭连接
        $pdo = null;
        return $result;
    }

    /**
     * 获取结果集
     * @return array
     */
    public function getfetchAll()
    {
        //执行预处理语句
        $this->statement->execute();

        $result = $this->statement->fetchAll(\PDO::FETCH_ASSOC);
        //释放查询结果
        $stmt = null;
        //关闭连接
        $pdo = null;
        return $result;
    }

    /**
     * 执行插入
     * @return string
     */
    public function insert()
    {
        //执行预处理语句
        $this->statement->execute();

        $id = $this->getLastInsertId();
        //释放查询结果
        $stmt = null;
        //关闭连接
        $pdo = null;

        return $id;
    }

    /**
     * 执行更新
     * @return string
     */
    public function update()
    {
        //执行预处理语句
        $this->statement->execute();

        $id = $this->getRowCount();
        //释放查询结果
        $stmt = null;
        //关闭连接
        $pdo = null;

        return $id;
    }

    /**
     * 获取上次插入的ID
     * @return string
     */
    public function getLastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * 获取受影响的行数
     * @return int
     */
    public function getRowCount()
    {
        return $this->statement->rowCount();
    }

    /**
     * 开始事务
     * @return bool
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * 提交事务
     * @return bool
     */
    public function commitTransaction()
    {
        return $this->pdo->commit();
    }

    /**
     * 事务回滚
     * @return bool
     */
    public function rollBackTransaction()
    {
        return $this->pdo->rollBack();
    }

}
