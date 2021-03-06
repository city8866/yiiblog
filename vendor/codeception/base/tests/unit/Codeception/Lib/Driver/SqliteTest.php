<?php

use \Codeception\Lib\Driver\Db;

class SqliteTest extends \PHPUnit_Framework_TestCase
{
    protected static $config = array(
        'dsn' => 'sqlite:tests/data/sqlite.db',
        'user' => 'root',
        'password' => ''
    );

    protected static $sqlite;
    protected static $sql;
    
    public static function setUpBeforeClass()
    {
        if (version_compare(PHP_VERSION, '5.5.0', '<')) {
            $dumpFile = '/dumps/sqlite-54.sql';
        } else {
            $dumpFile = '/dumps/sqlite.sql';
        }

        $sql = file_get_contents(\Codeception\Configuration::dataDir() . $dumpFile);
        $sql = preg_replace('%/\*(?:(?!\*/).)*\*/%s', "", $sql);
        self::$sql = explode("\n", $sql);
        try {
            self::$sqlite = Db::create(self::$config['dsn'], self::$config['user'], self::$config['password']);
            self::$sqlite->cleanup();
        } catch (\Exception $e) {
        }
    }

    public function setUp()
    {
        if (!isset(self::$sqlite)) {
            $this->markTestSkipped('Coudn\'t establish connection to database');
        }
        self::$sqlite->load(self::$sql);
    }
    
    public function tearDown()
    {
        if (isset(self::$sqlite)) {
            self::$sqlite->cleanup();
        }
    }
    
    public function testCleanupDatabase()
    {
        $this->assertGreaterThan(
            0,
            count(self::$sqlite->getDbh()->query('SELECT name FROM sqlite_master WHERE type = "table";')->fetchAll())
        );
        self::$sqlite->cleanup();
        $this->assertEmpty(
            self::$sqlite->getDbh()->query('SELECT name FROM sqlite_master WHERE type = "table";')->fetchAll()
        );
    }
    
    public function testLoadDump()
    {
        $res = self::$sqlite->getDbh()->query("select * from users where name = 'davert'");
        $this->assertNotEquals(false, $res);
        $this->assertNotEmpty($res->fetchAll());

        $res = self::$sqlite->getDbh()->query("select * from groups where name = 'coders'");
        $this->assertNotEquals(false, $res);
        $this->assertNotEmpty($res->fetchAll());
    }

    public function testGetPrimaryKeyReturnsRowIdIfTableHasIt()
    {
        $this->assertEquals(['_ROWID_'], self::$sqlite->getPrimaryKey('groups'));
    }

    public function testGetPrimaryKeyReturnsRowIdIfTableHasNoPrimaryKey()
    {
        $this->assertEquals(['_ROWID_'], self::$sqlite->getPrimaryKey('no_pk'));
    }

    public function testGetSingleColumnPrimaryKeyWhenTableHasNoRowId()
    {
        if (version_compare(PHP_VERSION, '5.5.0', '<')) {
            $this->markTestSkipped('Sqlite does not support WITHOUT ROWID on travis');
        }
        $this->assertEquals(['id'], self::$sqlite->getPrimaryKey('order'));
    }

    public function testGetCompositePrimaryKeyWhenTableHasNoRowId()
    {
        if (version_compare(PHP_VERSION, '5.5.0', '<')) {
            $this->markTestSkipped('Sqlite does not support WITHOUT ROWID on travis');
        }
        $this->assertEquals(['group_id', 'id'], self::$sqlite->getPrimaryKey('composite_pk'));
    }

    public function testGetPrimaryColumnOfTableUsingReservedWordAsTableNameWhenTableHasNoRowId()
    {
        if (version_compare(PHP_VERSION, '5.5.0', '<')) {
            $this->markTestSkipped('Sqlite does not support WITHOUT ROWID on travis');
        }
        $this->assertEquals('id', self::$sqlite->getPrimaryColumn('order'));
    }

    public function testGetPrimaryColumnThrowsExceptionIfTableHasCompositePrimaryKey()
    {
        if (version_compare(PHP_VERSION, '5.5.0', '<')) {
            $this->markTestSkipped('Sqlite does not support WITHOUT ROWID on travis');
        }
        $this->setExpectedException(
            '\Exception',
            'getPrimaryColumn method does not support composite primary keys, use getPrimaryKey instead'
        );
        self::$sqlite->getPrimaryColumn('composite_pk');
    }

    public function testThrowsExceptionIfInMemoryDatabaseIsUsed()
    {
        $this->setExpectedException(
            '\Codeception\Exception\ModuleException',
            ':memory: database is not supported'
        );

        Db::create('sqlite::memory:', '', '');
    }
}
