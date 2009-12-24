<?php
/**
 * Copyright 2007 Maintainable Software, LLC
 * Copyright 2008-2009 The Horde Project (http://www.horde.org/)
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @license    http://opensource.org/licenses/bsd-license.php
 * @category   Horde
 * @package    Horde_Db
 * @subpackage UnitTests
 */

/**
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @license    http://opensource.org/licenses/bsd-license.php
 * @group      horde_db
 * @category   Horde
 * @package    Horde_Db
 * @subpackage UnitTests
 */
class Horde_Db_Adapter_Pdo_MysqlTest extends PHPUnit_Framework_TestCase
{
    // @todo - add logger instance
    protected function setUp()
    {
        list($this->_conn, $this->_cache) = $this->sharedFixture->getConnection();

        // clear out detritus from any previous test runs.
        $this->_dropTestTables();

        $table = $this->_conn->createTable('unit_tests');
          $table->column('integer_value',   'integer',  array('limit' => 11, 'default' => 0));
          $table->column('string_value',    'string',   array('limit' => 255, 'default' => ''));
          $table->column('text_value',      'text',     array('null' => false, 'default' => ''));
          $table->column('float_value',     'float',    array('precision' => 2, 'scale' => 1, 'default' => 0.0));
          $table->column('decimal_value',   'decimal',  array('precision' => 2, 'scale' => 1, 'default' => 0.0));
          $table->column('datetime_value',  'datetime', array('default' => '0000-00-00 00:00:00'));
          $table->column('date_value',      'date',     array('default' => '0000-00-00'));
          $table->column('time_value',      'time',     array('default' => '00:00:00'));
          $table->column('blob_value',      'binary',   array('null' => false, 'default' => ''));
          $table->column('boolean_value',   'boolean',  array('default' => false));
          $table->column('email_value',     'string',   array('limit' => 255, 'default' => ''));
        $table->end();
        $this->_conn->addIndex('unit_tests', 'string_value', array('name' => 'string_value'));
        $this->_conn->addIndex('unit_tests', 'integer_value', array('name' => 'integer_value', 'unique' => true));
        $this->_conn->addIndex('unit_tests', array('integer_value', 'string_value'), array('name' => 'integer_string'));

        // read sql file for statements
        $statements = array();
        $current_stmt = '';
        $fp = fopen(dirname(__FILE__) . '/../../fixtures/unit_tests.sql', 'r');
        while ($line = fgets($fp, 8192)) {
            $line = rtrim(preg_replace('/^(.*)--.*$/s', '\1', $line));
            if (!$line) {
                continue;
            }

            $current_stmt .= $line;

            if (substr($line, -1) == ';') {
                // leave off the ending ;
                $statements[] = substr($current_stmt, 0, -1);
                $current_stmt = '';
            }
        }

        // run statements
        foreach ($statements as $stmt) {
            $this->_conn->execute($stmt);
        }
    }

    protected function tearDown()
    {
        // clean up
        $this->_dropTestTables();

        // close connection
        $this->_conn->disconnect();
    }


    /*##########################################################################
    # Connection
    ##########################################################################*/

    public function testConnect()
    {
        $this->assertTrue($this->_conn->isActive());
    }

    public function testDisconnect()
    {
        $this->_conn->disconnect();
        $this->assertFalse($this->_conn->isActive());

        $this->_conn->connect();
        $this->assertTrue($this->_conn->isActive());
    }

    public function testReconnect()
    {
        $this->_conn->reconnect();
        $this->assertTrue($this->_conn->isActive());
    }


    /*##########################################################################
    # Accessor
    ##########################################################################*/

    public function testAdapterName()
    {
        $this->assertEquals('PDO_MySQL', $this->_conn->adapterName());
    }

    public function testSupportsMigrations()
    {
        $this->assertTrue($this->_conn->supportsMigrations());
    }

    public function testSupportsCountDistinct()
    {
        $this->assertTrue($this->_conn->supportsCountDistinct());
    }

    public function testGetCharset()
    {
        $this->assertEquals('utf8', strtolower($this->_conn->getCharset()));
    }


    /*##########################################################################
    # Database Statements
    ##########################################################################*/

    public function testExecute()
    {
        $sql = "SELECT * FROM unit_tests WHERE id='1'";
        $result = $this->_conn->execute($sql);
        $row = $result->fetch();
        $this->assertEquals(1, $row['id']);
    }

    public function testSelect()
    {
        $sql = "SELECT * FROM unit_tests WHERE id='1'";
        $result = $this->_conn->select($sql);
        $this->assertType('Traversable', $result);
        $this->assertGreaterThan(0, count($result));

        foreach ($result as $row) break;
        $this->assertType('array', $row);
        $this->assertEquals(1, $row['id']);
    }

    public function testSelectWithBoundParameters()
    {
        $sql = "SELECT * FROM unit_tests WHERE id=?";
        $result = $this->_conn->select($sql, array(1));
        $this->assertType('Traversable', $result);
        $this->assertGreaterThan(0, count($result));

        foreach ($result as $row) break;
        $this->assertType('array', $row);
        $this->assertEquals(1, $row['id']);
    }

    public function testSelectWithBoundParametersQuotesString()
    {
        $sql = "SELECT * FROM unit_tests WHERE string_value=?";
        $result = $this->_conn->select($sql, array('name a'));
        $this->assertType('Traversable', $result);
        $this->assertGreaterThan(0, count($result));

        foreach ($result as $row) break;
        $this->assertType('array', $row);
        $this->assertEquals(1, $row['id']);
    }

    public function testSelectAll()
    {
        $sql = "SELECT * FROM unit_tests WHERE id='1'";
        $result = $this->_conn->selectAll($sql);
        $this->assertType('array', $result);
        $this->assertGreaterThan(0, count($result));
        $this->assertEquals(1, $result[0]['id']);
    }

    public function testSelectOne()
    {
        $sql = "SELECT * FROM unit_tests WHERE id='1'";
        $result = $this->_conn->selectOne($sql);
        $this->assertEquals(1, $result['id']);
    }

    public function testSelectValue()
    {
        $sql = "SELECT * FROM unit_tests WHERE id='1'";
        $result = $this->_conn->selectValue($sql);
        $this->assertEquals(1, $result);
    }

    public function testSelectValues()
    {
        $sql = "SELECT * FROM unit_tests";
        $result = $this->_conn->selectValues($sql);
        $this->assertEquals(array(1, 2, 3, 4, 5, 6), $result);
    }

    public function testInsert()
    {
        $sql = "INSERT INTO unit_tests (id, integer_value) VALUES (7, 999)";
        $result = $this->_conn->insert($sql);

        $this->assertEquals(7, $result);
    }

    public function testUpdate()
    {
        $sql = "UPDATE unit_tests SET integer_value=999 WHERE id IN (1)";
        $result = $this->_conn->update($sql);

        $this->assertEquals(1, $result);
    }

    public function testDelete()
    {
        $sql = "DELETE FROM unit_tests WHERE id IN (1,2)";
        $result = $this->_conn->delete($sql);

        $this->assertEquals(2, $result);
    }

    public function testTransactionStarted()
    {
        $this->assertFalse($this->_conn->transactionStarted());
        $this->_conn->beginDbTransaction();

        $this->assertTrue($this->_conn->transactionStarted());
        $this->_conn->commitDbTransaction();

        $this->assertFalse($this->_conn->transactionStarted());
    }

    public function testTransactionCommit()
    {
        $this->_conn->beginDbTransaction();
        $sql = "INSERT INTO unit_tests (id, integer_value) VALUES (7, 999)";
        $this->_conn->insert($sql);
        $this->_conn->commitDbTransaction();

        // make sure it inserted
        $sql = "SELECT integer_value FROM unit_tests WHERE id='7'";
        $this->assertEquals('999', $this->_conn->selectValue($sql));
    }

    public function testTransactionRollback()
    {
        $this->_conn->beginDbTransaction();
         $sql = "INSERT INTO unit_tests (id, integer_value) VALUES (7, 999)";
         $this->_conn->insert($sql);
         $this->_conn->rollbackDbTransaction();

         // make sure it inserted
         $sql = "SELECT integer_value FROM unit_tests WHERE id='7'";
         $this->assertEquals(null, $this->_conn->selectValue($sql));
    }


    /*##########################################################################
    # Quoting
    ##########################################################################*/

    public function testQuoteNull()
    {
        $this->assertEquals('NULL', $this->_conn->quote(null));
    }

    public function testQuoteTrue()
    {
        $this->assertEquals('1', $this->_conn->quote(true));
    }

    public function testQuoteFalse()
    {
        $this->assertEquals('0', $this->_conn->quote(false));
    }

    public function testQuoteString()
    {
        $this->assertEquals("'my string'", $this->_conn->quote('my string'));
    }

    public function testQuoteDirtyString()
    {
        $this->assertEquals("'derek\'s string'", $this->_conn->quote('derek\'s string'));
    }

    public function testQuoteColumnName()
    {
        $col = new Horde_Db_Adapter_Mysql_Column('age', 'NULL', 'int(11)');
        $this->assertEquals('1', $this->_conn->quote(true, $col));
    }


    /*##########################################################################
    # Schema Statements
    ##########################################################################*/

    /**
     * We specifically do a manual INSERT here, and then test only the SELECT
     * functionality. This allows us to more easily catch INSERT being broken,
     * but SELECT actually working fine.
     */
    public function testNativeDecimalInsertManualVsAutomatic()
    {
        $table = $this->_conn->createTable('users');
          $table->column('company_id',  'integer',  array('limit' => 11));
          $table->column('name',        'string',   array('limit' => 255, 'default' => ''));
          $table->column('first_name',  'string',   array('limit' => 40, 'default' => ''));
          $table->column('approved',    'boolean',  array('default' => true));
          $table->column('type',        'string',   array('limit' => 255, 'default' => ''));
          $table->column('created_at',  'datetime', array('default' => '0000-00-00 00:00:00'));
          $table->column('created_on',  'date',     array('default' => '0000-00-00'));
          $table->column('updated_at',  'datetime', array('default' => '0000-00-00 00:00:00'));
          $table->column('updated_on',  'date',     array('default' => '0000-00-00'));
        $table->end();

        $correctValue = 12345678901234567890.0123456789;

        $this->_conn->addColumn("users", "wealth", 'decimal', array('precision' => 30, 'scale' => 10));

        // do a manual insertion
        $this->_conn->execute("INSERT INTO users (wealth) VALUES ('12345678901234567890.0123456789')");

        // SELECT @todo - type cast attribute values
        $user = (object)$this->_conn->selectOne('SELECT * FROM users');
        // assert_kind_of BigDecimal, row.wealth

        // If this assert fails, that means the SELECT is broken!
        $this->assertEquals($correctValue, $user->wealth);

        // Reset to old state
        $this->_conn->delete('DELETE FROM users');

        // Now use the Adapter insertion
        $this->_conn->insert('INSERT INTO users (wealth) VALUES (12345678901234567890.0123456789)');

        // SELECT @todo - type cast attribute values
        $user = (object)$this->_conn->selectOne('SELECT * FROM users');
        // assert_kind_of BigDecimal, row.wealth

        // If these asserts fail, that means the INSERT (create function, or cast to SQL) is broken!
        $this->assertEquals($correctValue, $user->wealth);

        $this->_conn->dropTable('users');
    }

    public function testNativeTypes()
    {
        $table = $this->_conn->createTable('users');
          $table->column('company_id',  'integer',  array('limit' => 11));
          $table->column('name',        'string',   array('limit' => 255, 'default' => ''));
          $table->column('first_name',  'string',   array('limit' => 40, 'default' => ''));
          $table->column('approved',    'boolean',  array('default' => true));
          $table->column('type',        'string',   array('limit' => 255, 'default' => ''));
          $table->column('created_at',  'datetime', array('default' => '0000-00-00 00:00:00'));
          $table->column('created_on',  'date',     array('default' => '0000-00-00'));
          $table->column('updated_at',  'datetime', array('default' => '0000-00-00 00:00:00'));
          $table->column('updated_on',  'date',     array('default' => '0000-00-00'));
        $table->end();

        $this->_conn->addColumn("users", "last_name",       'string');
        $this->_conn->addColumn("users", "bio",             'text');
        $this->_conn->addColumn("users", "age",             'integer');
        $this->_conn->addColumn("users", "height",          'float');
        $this->_conn->addColumn("users", "wealth",          'decimal', array('precision' => '30', 'scale' => '10'));
        $this->_conn->addColumn("users", "birthday",        'datetime');
        $this->_conn->addColumn("users", "favorite_day",    'date');
        $this->_conn->addColumn("users", "moment_of_truth", 'datetime');
        $this->_conn->addColumn("users", "male",            'boolean');

        $this->_conn->insert('INSERT INTO USERS (first_name, last_name, bio, age, height, wealth, birthday, favorite_day, moment_of_truth, male, company_id) ' .
                             "VALUES ('bob', 'bobsen', 'I was born ....', 18, 1.78, 12345678901234567890.0123456789, '2005-01-01 12:23:40', '1980-03-05', '1582-10-10 21:40:18', 1, 1)");

        $bob = (object)$this->_conn->selectOne('SELECT * FROM users');
        $this->assertEquals('bob',             $bob->first_name);
        $this->assertEquals('bobsen',          $bob->last_name);
        $this->assertEquals('I was born ....', $bob->bio);
        $this->assertEquals(18,                $bob->age);

        // Test for 30 significent digits (beyond the 16 of float), 10 of them
        // after the decimal place.
        $this->assertEquals('12345678901234567890.0123456789', $bob->wealth);
        $this->assertEquals(1,                                 $bob->male);

        // @todo - type casting
    }

    public function testNativeDatabaseTypes()
    {
        $types = $this->_conn->nativeDatabaseTypes();
        $this->assertEquals(array('name' => 'int', 'limit' => 11), $types['integer']);
    }

    public function testUnabstractedDatabaseDependentTypes()
    {
        $this->_conn->delete('DELETE FROM users');

        $this->_conn->addColumn('users', 'intelligence_quotient', 'tinyint');
        $this->_conn->insert('INSERT INTO users (intelligence_quotient) VALUES (300)');

        $jonnyg = (object)$this->_conn->selectOne('SELECT * FROM users');
        $this->assertEquals('127', $jonnyg->intelligence_quotient);
    }

    public function testTableAliasLength()
    {
        $len = $this->_conn->tableAliasLength();
        $this->assertEquals(255, $len);
    }

    public function testTableAliasFor()
    {
        $alias = $this->_conn->tableAliasFor('my_table_name');
        $this->assertEquals('my_table_name', $alias);
    }

    public function testTables()
    {
        $tables = $this->_conn->tables();
        $this->assertTrue(count($tables) > 0);
        $this->assertContains('unit_tests', $tables);
    }

    public function testPrimaryKey()
    {
        $pk = $this->_conn->primaryKey('unit_tests');
        $this->assertEquals('id', (string)$pk);
        $this->assertEquals(1, count($pk->columns));
        $this->assertEquals('id', $pk->columns[0]);
    }

    public function testIndexes()
    {
        $indexes = $this->_conn->indexes('unit_tests');
        $this->assertEquals(3, count($indexes));

        // unique index
        $col = array('integer_value');
        $this->assertEquals('unit_tests',    $indexes[0]->table);
        $this->assertEquals('integer_value', $indexes[0]->name);
        $this->assertEquals(true,            $indexes[0]->unique);
        $this->assertEquals($col,            $indexes[0]->columns);

        // normal index
        $col = array('string_value');
        $this->assertEquals('unit_tests',   $indexes[1]->table);
        $this->assertEquals('string_value', $indexes[1]->name);
        $this->assertEquals(false,          $indexes[1]->unique);
        $this->assertEquals($col,           $indexes[1]->columns);

        // multi-column index
        $col = array('integer_value', 'string_value');
        $this->assertEquals('unit_tests',     $indexes[2]->table);
        $this->assertEquals('integer_string', $indexes[2]->name);
        $this->assertEquals(false,            $indexes[2]->unique);
        $this->assertEquals($col,             $indexes[2]->columns);
    }

    public function testColumns()
    {
        $columns = $this->_conn->columns('unit_tests');
        $this->assertEquals(12, count($columns));

        $col = $columns['id'];
        $this->assertEquals('id',      $col->getName());
        $this->assertEquals('integer', $col->getType());
        $this->assertEquals(false,     $col->isNull());
        $this->assertEquals(10,        $col->getLimit());
        $this->assertEquals('',        $col->getDefault());
        $this->assertEquals('int(10) unsigned', $col->getSqlType());
        $this->assertEquals(false,     $col->isText());
        $this->assertEquals(true,      $col->isNumber());
    }

    public function testCreateTable()
    {
        $this->_createTestTable('sports');

        $sql = "SELECT id FROM sports WHERE id = 1";
        $this->assertEquals(1, $this->_conn->selectValue($sql));
    }

    public function testCreateTableNoPk()
    {
        $this->_createTestTable('sports', array('primaryKey' => false));

        try {
            $sql = "SELECT id FROM sports WHERE id = 1";
            $this->assertNull($this->_conn->selectValue($sql));
        } catch (Exception $e) {
            return;
        }
        $this->fail("Expected exception for no pk");
    }

    public function testCreateTableForce()
    {
        $this->_createTestTable('sports');
        $this->_createTestTable('sports', array('force' => true));

        $sql = "SELECT id FROM sports WHERE id = 1";
        $this->assertEquals(1, $this->_conn->selectValue($sql));
    }

    public function testCreateTableTemporary()
    {
        $this->_createTestTable('sports', array('temporary' => true));

        $sql = "SELECT id FROM sports WHERE id = 1";
        $this->assertEquals(1, $this->_conn->selectValue($sql));
    }

    public function testRenameTable()
    {
        $this->_createTestTable('sports');
        $this->_conn->renameTable('sports', 'my_sports');

        $sql = "SELECT id FROM my_sports WHERE id = 1";
        $this->assertEquals("1", $this->_conn->selectValue($sql));
    }

    public function testDropTable()
    {
        $this->_createTestTable('sports');
        $this->_conn->dropTable('sports');

        try {
            $sql = "SELECT id FROM sports WHERE id = 1";
            $this->_conn->execute($sql);
        } catch (Exception $e) {
            return;
        }
        $this->fail("Table exists where it shouldn't have");
    }

    public function testAddColumn()
    {
        $this->_createTestTable('sports');
        $this->_conn->addColumn('sports', 'modified_at', 'date');
        $this->_conn->update("UPDATE sports SET modified_at = '2007-01-01'");

        $sql = "SELECT modified_at FROM sports WHERE id = 1";
        $this->assertEquals("2007-01-01", $this->_conn->selectValue($sql));
    }

    public function testRemoveColumn()
    {
        $this->_createTestTable('sports');
        $sql = "SELECT name FROM sports WHERE id = 1";
        $this->assertEquals("mlb", $this->_conn->selectValue($sql));

        $this->_conn->removeColumn('sports', 'name');

        try {
            $sql = "SELECT name FROM sports WHERE id = 1";
            $this->_conn->execute($sql);
        } catch (Exception $e) {
            return;
        }
        $this->fail("Column exists where it shouldn't have");
    }

    public function testChangeColumnDefault()
    {
        $this->_createTestTable('sports');
        $beforeChange = $this->_getColumn('sports', 'name');
        $this->assertEquals('', $beforeChange->getDefault());

        $this->_conn->changeColumnDefault('sports', 'name', 'test');

        $afterChange = $this->_getColumn('sports', 'name');
        $this->assertEquals('test', $afterChange->getDefault());
    }

    public function testChangeColumnType()
    {
        $this->_createTestTable('sports');
        $beforeChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('tinyint(1)', $beforeChange->getSqlType());

        $this->_conn->changeColumn('sports', 'is_college', 'string');

        $afterChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('varchar(255)', $afterChange->getSqlType());
    }

    public function testChangeColumnLimit()
    {
        $this->_createTestTable('sports');
        $beforeChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('tinyint(1)', $beforeChange->getSqlType());

        $this->_conn->changeColumn('sports', 'is_college', 'string',
                                    array('limit' => '40'));

        $afterChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('varchar(40)', $afterChange->getSqlType());
    }

    public function testChangeColumnPrecisionScale()
    {
        $this->_createTestTable('sports');
        $beforeChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('tinyint(1)', $beforeChange->getSqlType());

        $this->_conn->changeColumn('sports', 'is_college', 'decimal',
                                    array('precision' => '5', 'scale' => '2'));

        $afterChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('decimal(5,2)', $afterChange->getSqlType());
    }

    public function testRenameColumn()
    {
        $this->_createTestTable('sports');

        $beforeChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('tinyint(1)', $beforeChange->getSqlType());

        $this->_conn->renameColumn('sports', 'is_college', 'is_renamed');

        $afterChange = $this->_getColumn('sports', 'is_renamed');
        $this->assertEquals('tinyint(1)', $afterChange->getSqlType());
    }

    public function testAddIndexDefault()
    {
        $this->_createTestTable('sports');
        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNull($index);

        $this->_conn->addIndex('sports', 'is_college');

        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNotNull($index);
    }

    public function testAddIndexMultiColumn()
    {
        $this->_createTestTable('sports');
        $index = $this->_getIndex('sports', array('name', 'is_college'));
        $this->assertNull($index);

        $this->_conn->addIndex('sports', array('name', 'is_college'));

        $index = $this->_getIndex('sports', array('name', 'is_college'));
        $this->assertNotNull($index);
    }

    public function testAddIndexUnique()
    {
        $this->_createTestTable('sports');
        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNull($index);

        $this->_conn->addIndex('sports', 'is_college', array('unique' => true));

        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNotNull($index);
        $this->assertTrue($index->unique);
    }

    public function testAddIndexName()
    {
        $this->_createTestTable('sports');
        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNull($index);

        $this->_conn->addIndex('sports', 'is_college', array('name' => 'test'));

        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNotNull($index);
        $this->assertEquals('test', $index->name);
    }

    public function testRemoveIndexSingleColumn()
    {
        $this->_createTestTable('sports');

        // add the index
        $this->_conn->addIndex('sports', 'is_college');
        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNotNull($index);

        // remove it again
        $this->_conn->removeIndex('sports', array('column' => 'is_college'));
        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNull($index);
    }

    public function testRemoveIndexMultiColumn()
    {
        $this->_createTestTable('sports');

        // add the index
        $this->_conn->addIndex('sports', array('name', 'is_college'));
        $index = $this->_getIndex('sports', array('name', 'is_college'));
        $this->assertNotNull($index);

        // remove it again
        $this->_conn->removeIndex('sports', array('column' => array('name', 'is_college')));
        $index = $this->_getIndex('sports', array('name', 'is_college'));
        $this->assertNull($index);
    }

    public function testRemoveIndexByName()
    {
        $this->_createTestTable('sports');

        // add the index
        $this->_conn->addIndex('sports', 'is_college', array('name' => 'test'));
        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNotNull($index);

        // remove it again
        $this->_conn->removeIndex('sports', array('name' => 'test'));
        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNull($index);
    }

    public function testIndexNameInvalid()
    {
        try {
            $name = $this->_conn->indexName('sports');
        } catch (Horde_Db_Exception $e) {
            return;
        }
        $this->fail("Adding an index with crappy options worked where it shouldn't have");
    }

    public function testIndexNameBySingleColumn()
    {
        $name = $this->_conn->indexName('sports', array('column' => 'is_college'));
        $this->assertEquals('index_sports_on_is_college', $name);
    }

    public function testIndexNameByMultiColumn()
    {
        $name = $this->_conn->indexName('sports', array('column' =>
                                                array('name', 'is_college')));
        $this->assertEquals('index_sports_on_name_and_is_college', $name);
    }

    public function testIndexNameByName()
    {
        $name = $this->_conn->indexName('sports', array('name' => 'test'));
        $this->assertEquals('test', $name);
    }

    public function testStructureDump()
    {
        $this->_createTestTable('sports');
        // Avoid AUTO_INCREMENT being a part of the dump
        $this->_conn->execute('TRUNCATE TABLE sports');

        // single table
        $structure = $this->_conn->structureDump('sports');

        $expected = "CREATE TABLE `sports` (\n".
        "  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,\n".
        "  `name` varchar(255) DEFAULT NULL,\n".
        "  `is_college` tinyint(1) DEFAULT NULL,\n".
        "  PRIMARY KEY (`id`)\n".
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8";

        // MySQL differs in how it dumps table structure between versions, so do
        // some normalization.
        $expected = strtolower(preg_replace('/\s+/', ' ', $expected));
        $structure = strtolower(preg_replace('/\s+/', ' ', $structure));

        $this->assertContains($expected, $structure);

        // entire structure
        $structure = $this->_conn->structureDump();
        $structure = strtolower(preg_replace('/\s+/', ' ', $structure));

        // contains, but doesn't match only sports table
        $this->assertContains($expected, $structure);
        $this->assertNotEquals($expected, $structure);
    }

    public function testInitializeSchemaInformation()
    {
        $this->_conn->initializeSchemaInformation();

        $sql = "SELECT version FROM schema_info";
        $this->assertEquals(0, $this->_conn->selectValue($sql));
    }

    public function testTypeToSqlTypePrimaryKey()
    {
        $result = $this->_conn->typeToSql('primaryKey');
        $this->assertEquals('int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $result);
    }

    public function testTypeToSqlTypeString()
    {
        $result = $this->_conn->typeToSql('string');
        $this->assertEquals('varchar(255)', $result);
    }

    public function testTypeToSqlTypeText()
    {
        $result = $this->_conn->typeToSql('text');
        $this->assertEquals('text', $result);
    }

    public function testTypeToSqlTypeBinary()
    {
        $result = $this->_conn->typeToSql('binary');
        $this->assertEquals('blob', $result);
    }

    public function testTypeToSqlTypeFloat()
    {
        $result = $this->_conn->typeToSql('float');
        $this->assertEquals('float', $result);
    }

    public function testTypeToSqlTypeDatetime()
    {
        $result = $this->_conn->typeToSql('datetime');
        $this->assertEquals('datetime', $result);
    }

    public function testTypeToSqlTypeTimestamp()
    {
        $result = $this->_conn->typeToSql('timestamp');
        $this->assertEquals('datetime', $result);
    }

    public function testTypeToSqlInt()
    {
        $result = $this->_conn->typeToSql('integer', '11');
        $this->assertEquals('int(11)', $result);
    }

    public function testTypeToSqlIntLimit()
    {
        $result = $this->_conn->typeToSql('integer', '1');
        $this->assertEquals('int(1)', $result);
    }

    public function testTypeToSqlDecimalPrecision()
    {
        $result = $this->_conn->typeToSql('decimal', null, '5');
        $this->assertEquals('decimal(5)', $result);
    }

    public function testTypeToSqlDecimalScale()
    {
        $result = $this->_conn->typeToSql('decimal', null, '5', '2');
        $this->assertEquals('decimal(5, 2)', $result);
    }

    public function testTypeToSqlBoolean()
    {
        $result = $this->_conn->typeToSql('boolean');
        $this->assertEquals('tinyint(1)', $result);
    }

    public function testAddColumnOptions()
    {
        $result = $this->_conn->addColumnOptions("test", array());
        $this->assertEquals("test", $result);
    }

    public function testAddColumnOptionsDefault()
    {
        $options = array('default' => '0');
        $result = $this->_conn->addColumnOptions("test", $options);
        $this->assertEquals("test DEFAULT '0'", $result);
    }

    public function testAddColumnOptionsNull()
    {
        $options = array('null' => true);
        $result = $this->_conn->addColumnOptions("test", $options);
        $this->assertEquals("test", $result);
    }

    public function testAddColumnOptionsNotNull()
    {
        $options = array('null' => false);
        $result = $this->_conn->addColumnOptions("test", $options);
        $this->assertEquals("test NOT NULL", $result);
    }

    public function testDistinct()
    {
        $result = $this->_conn->distinct("test");
        $this->assertEquals("DISTINCT test", $result);
    }

    public function testAddOrderByForAssocLimiting()
    {
        $result = $this->_conn->addOrderByForAssocLimiting("SELECT * FROM documents ",
                                array('order' => 'name DESC'));
        $this->assertEquals("SELECT * FROM documents ORDER BY name DESC", $result);
    }


    /*##########################################################################
    # Table cache
    ##########################################################################*/

    public function testCachedTableIndexes()
    {
        // remove any current cache.
        $this->_cache->set('tables/indexes/cache_table', '');
        $this->assertEquals('', $this->_cache->get('tables/indexes/cache_table'));

        $this->_createTestTable('cache_table');
        $idxs = $this->_conn->indexes('cache_table');

        $this->assertNotEquals('', $this->_cache->get('tables/indexes/cache_table'));
    }

    public function testCachedTableColumns()
    {
        // remove any current cache.
        $this->_cache->set('tables/columns/cache_table', '');
        $this->assertEquals('', $this->_cache->get('tables/columns/cache_table'));

        $this->_createTestTable('cache_table');
        $cols = $this->_conn->columns('cache_table');

        $this->assertNotEquals('', $this->_cache->get('tables/columns/cache_table'));
    }


    /*##########################################################################
    # Protected
    ##########################################################################*/

    /**
     * Create table to perform tests on
     */
    protected function _createTestTable($name, $options=array())
    {
        $table = $this->_conn->createTable($name, $options);
          $table->column('name',       'string');
          $table->column('is_college', 'boolean');
        $table->end();

        try {
            // make sure table was created
            $sql = "INSERT INTO $name (id, name, is_college)
                    VALUES (1, 'mlb', 0)";
            $this->_conn->insert($sql);
        } catch (Exception $e) {}
    }

    /**
     * drop test tables
     */
    protected function _dropTestTables()
    {
        try {
            $this->_conn->dropTable('unit_tests');
        } catch (Exception $e) {}
        try {
            $this->_conn->dropTable('sports');
        } catch (Exception $e) {}
        try {
            $this->_conn->dropTable('my_sports');
        } catch (Exception $e) {}
        try {
            $this->_conn->dropTable('schema_info');
        } catch (Exception $e) {}
        try {
            $this->_conn->dropTable('cache_table');
        } catch (Exception $e) {}
    }

    /**
     * Get a column by name
     */
    protected function _getColumn($table, $column)
    {
        foreach ($this->_conn->columns($table) as $col) {
            if ($col->getName() == $column) return $col;
        }
    }

    /**
     * Get an index by columns
     */
    protected function _getIndex($table, $indexes)
    {
        $indexes = (array) $indexes;
        sort($indexes);

        foreach ($this->_conn->indexes($table) as $index) {
            $columns = $index->columns;
            sort($columns);
            if ($columns == $indexes) return $index;
        }
    }

}
