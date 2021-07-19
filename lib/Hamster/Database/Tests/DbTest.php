<?php
// +----------------------------------------------------------------------
// | Author: jdmake <503425061@qq.com>
// +----------------------------------------------------------------------
// | Date: 2021/7/18
// +----------------------------------------------------------------------

namespace Hamster\Database\Tests;

use Hamster\Database\Db;

require_once __DIR__ . '/../../../../vendor/autoload.php';

class DbTest extends \PHPUnit\Framework\TestCase
{
    /** @var Db */
    private $db;

    public function connect()
    {
        $this->db = Db::create([
            'db_type' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'dbname' => 'xima-ot',
            'username' => 'root',
            'password' => '88888888Ab',
            'charset' => 'utf8mb4',
        ]);
        $this->assertNotNull($this->db);
    }

    public function testQuery()
    {
        $this->connect();
        $this->assertNotNull(
            $this->db->table('admin')
                ->where("username = 'admin'")
                ->find()
        );
        $this->assertNotNull(
            $res = $this->db->table('admin')
                ->where("username = 'admin'")
                ->pagination(1, 15)
        );
    }

    public function testInsert()
    {
        $this->connect();
        $this->assertGreaterThan(0, $this->db->table('admin')->insert([
            'username' => 'test',
            'password' => md5('123')
        ]));
    }

    public function testUpdate()
    {
        $this->connect();
        $this->assertGreaterThan(0, $this->db->table('admin')
            ->where("username='test'")
            ->update([
                'username' => 'test1',
            ]));
    }

    public function testDelete()
    {
        $this->connect();
        $this->assertGreaterThan(0, $this->db->table('admin')
            ->where("username='test1'")
            ->delete());
    }

    public function testCount()
    {
        $this->connect();
        $this->assertIsInt($this->db->table('admin')->count());
    }

    public function testTransactionRollBack()
    {
        $this->connect();
        $this->db->table('admin');
        $this->db->beginTransaction();
        $this->db->insert([
            'username' => 'test2',
        ]);
        $this->db->rollBack();
        $this->assertEquals(
            null,
            $this->db->where("username = 'test2'")
                ->find()
        );
    }

    public function testTransactionCommit()
    {
        $this->connect();
        $this->db->table('admin');
        $this->db->beginTransaction();
        $this->db->insert([
            'username' => 'test2',
        ]);
        $this->db->commit();
        $this->assertNotNull($this->db->where("username = 'test2'")->find());
        $this->db->where("username = 'test2'")->delete();
    }

}
