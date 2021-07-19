Database 使用指南
================

创建Db
```php
$db = Db::create([
    'db_type' => 'mysql',
    'host' => '127.0.0.1',
    'port' => '3306',
    'dbname' => 'xima-ot',
    'username' => 'root',
    'password' => '88888888Ab',
    'charset' => 'utf8mb4',
]);
```
指定操作表
```php
$db->table('tableName');
```

链式操作
```php
$db->table()->where()->find();
```

查询条件
```php
$db->where('1=1');

// 使用预编译条件
$db->where('id = :id');

// 设置预编译参数
$db->setParameter([
    'id' => 1
]); ` 
```
获取单条数据
```php
$db->find();
```

获取分页数据
```php
$db->pagination([
    'page' => '当前页码',
    'limit' => '每页显示条数',
    'option' => [
        'return_page' => '是否返回HTML分页代码',
        'query' => [], // 携带额外的GET参数
    ], 
]);
```

插入数据
```php
$db->insert([
    'x' => 'x',
]);
```

更新数据
```php
$db->where('条件')->update([
    'x' => 'x',
]);
```

删除数据
```php
$db->where('条件')->delete();
```

开始事务
```php
$db->beginTransaction();
```

提交事务
```php
$db->commit();
```

回滚事务
```php
$db->rollBack();
```
