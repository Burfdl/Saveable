<?php

require_once dirname(__FILE__) . '/../../../DBM.php';
require_once dirname(__FILE__) . '/../../../Deletable.php';
require_once dirname(__FILE__) . '/../../../TableDefinition.php';
require_once dirname(__FILE__) . '/../../../FieldDefinitions/NumberFieldDefinition.php';
require_once dirname(__FILE__) . '/../../../FieldDefinitions/TextFieldDefinition.php';
require_once dirname(__FILE__) . '/../../../IndexDefinitions/PrimaryKeyDefinition.php';

/**
 * Test class for DBM.
 * Generated by PHPUnit on 2011-05-30 at 22:46:01.
 */
class DBMTest extends PHPUnit_Framework_TestCase
{
	
	private $connection;
	
	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp()
	{
		$this->connection = new PDO("mysql:host=localhost;port=8889;dbname=test;unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock", "testing", "fCPreFuUjKeeLfWS");
		DBM::setConnection($this->connection);
		foreach ($this->connection->query("SHOW TABLES")->fetchAll(PDO::FETCH_ASSOC) as $row)
		{
			$this->connection->query("DROP TABLE `".DBM::escape(array_shift($row))."`");
		}
		DBM::resetCache();
		DBM::setCacheIsEnabled(true);
		DBM::resetCache();
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown() {
		DBM::resetCache();
	}

	/**
	 * @todo Implement testCacheIsDisabled().
	 */
	public function testCacheIsDisabled() {
		// Remove the following lines when you implement this test.
		DBM::cacheIsDisabled(false);
		DBM::query("CREATE TABLE Test(ID int(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY)");
		DBM::resetCache();
		DBM::query("SHOW TABLES", true);
		DBM::query("SHOW TABLES", true);
		$firstCacheDebug = DBM::queryCacheDebug();
		$this->assertEquals("SHOW TABLES", $firstCacheDebug[0]["query"]);
		$this->assertEquals(1, $firstCacheDebug[0]["hits"]);
		DBM::cacheIsDisabled(true);
		DBM::query("SHOW TABLES", true);
		DBM::query("SHOW TABLES", true);
		$secondCacheDebug = DBM::queryCacheDebug();
		$this->assertEmpty($secondCacheDebug);
	}

	public function testSetConnection() {
		DBM::setConnection($this->connection);
		$this->setExpectedException("Exception");
		DBM::setConnection(new mysqli());
	}

	/**
	 * @todo Implement testResetCache().
	 */
	public function testResetCache() {
		// Remove the following lines when you implement this test.
		DBM::query("SHOW TABLES");
		$this->assertEquals(1, count(DBM::queryCacheDebug()));
		DBM::resetCache();
		$this->assertEmpty(DBM::queryCacheDebug());
	}

	/**
	 * @todo Implement testSave().
	 */
	public function testSave() {
		$test = new TestSaveable();
		$test->Label = "Test Object";
		DBM::save($test);
		/* @var $statement PDOStatement */
		$statement = $this->connection->query("SELECT * FROM TestSaveable");
		$this->assertEquals(array(array("TestSaveableID" => "1", "Label" => "Test Object")), $statement->fetchAll(PDO::FETCH_ASSOC));
	}

	/**
	 * @todo Implement testDelete().
	 */
	public function testDelete() {
		$test = new TestSaveable();
		$test->Label = "Test Object";
		DBM::save($test);
		/* @var $statement PDOStatement */
		$this->assertEquals(array(array("TestSaveableID" => "1", "Label" => "Test Object")), $this->connection->query("SELECT * FROM TestSaveable")->fetchAll(PDO::FETCH_ASSOC));
		DBM::delete($test);
		$this->assertEquals(array(), $this->connection->query("SELECT * FROM TestSaveable")->fetchAll(PDO::FETCH_ASSOC));
	}

	/**
	 * @todo Implement testTableExists().
	 */
	public function testTableExists() {
		self::assertFalse(DBM::tableExists("MissingTable"));
		$this->connection->query("CREATE TABLE `FoundTable` (ID INT(10))");
		self::assertTrue(DBM::tableExists("FoundTable"));
		DBM::query("DROP TABLE `FoundTable`");
		self::assertFalse(DBM::tableExists("FoundTable"));
	}

	/**
	 * @todo Implement testDescribe().
	 */
	public function testDescribe()
	{
		$expectedTable = array(
			array(
				"Field" => "TestSaveableID", 
				"Type" => "bigint(10)", 
				"Null" => "NO", 
				"Key" => "PRI", 
				"Default" => "", 
				"Extra" => "auto_increment"
			),
			array(
				"Field" => "Label",
				"Type" => "varchar(60)",
				"Null" => "NO",
				"Key" => "",
				"Default" => "",
				"Extra" => ""
			)
		);
		self::assertEquals(DBM::describe(new TestSaveable()), $expectedTable);
	}

	/**
	 * @todo Implement testGetTableList().
	 */
	public function testGetTableList()
	{
		//self::assertEmpty(DBM::getTableList());
		DBM::query("CREATE TABLE `testing` (ID int(10))");
		//self::assertEquals(DBM::getTableList(), array("testing"));
		DBM::query("CREATE TABLE `testalot` (ID int(10))");
		self::assertEquals(DBM::getTableList(), array("testalot", "testing"));
		DBM::query("DROP TABLE `testing`");
		DBM::query("DROP TABLE `testalot`");
		self::assertEquals(DBM::getTableList(), array());
	}

	/**
	 * @todo Implement testQuery().
	 */
	public function testQuery()
	{
		/* @var PDO $this->connection */
		$this->connection->query("create table test (ID int(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY, Label VARCHAR(60))");
		$this->connection->query("INSERT INTO test (Label) VALUES (\"one\")");
		$this->connection->query("INSERT INTO test (Label) VALUES (\"two\")");
		$this->connection->query("INSERT INTO test (Label) VALUES (\"three\")");
		$this->assertEquals(array(array("ID" => 1, "Label" => "one"), array("ID" =>2, "Label" => "two"), array("ID" => 3, "Label" => "three")), DBM::query("SELECT * FROM test"));
		DBM::query("DELETE FROM test WHERE ID=3");
		$this->assertEquals(array(array("ID" => 1, "Label" => "one"), array("ID" =>2, "Label" => "two")), DBM::query("SELECT * FROM test"));
		DBM::query("INSERT INTO test (Label) VALUES (\"four\")");
		$this->assertEquals(array(array("ID" => 1, "Label" => "one"), array("ID" =>2, "Label" => "two"), array("ID" => 4, "Label" => "four")), DBM::query("SELECT * FROM test"));
		DBM::query("DROP TABLE test");
		/* @var $statement PDOStatement */
		$statement = $this->connection->query("SHOW TABLES");
		$this->assertEquals(array(), $statement->fetchAll());
	}

	/**
	 * @todo Implement testInflateIfExists().
	 */
	public function testInflateIfExists() {
		$test = new TestSaveable();
		$test->Label = "Test object";
		DBM::save($test);
		unset($test->Label);
		DBM::inflateIfExists($test);
		$this->assertEquals("Test object", $test->Label);
	}

	/**
	 * @todo Implement testEscape().
	 */
	public function testEscape() {
		$this->assertEquals("Hello there", DBM::escape("Hello there"));
		$this->assertNotEquals("'Fishing'", DBM::escape("'Fishing'"));
	}

	/**
	 * @todo Implement testLoadObjectsByQuery().
	 */
	public function testLoadObjectsByQuery() {
		$test = new TestSaveable();
		$test->Label = "Test object";
		DBM::save($test);
		$test = new TestSaveable();
		$test->Label = "Test object 2";
		DBM::save($test);
		$result = DBM::loadObjectsByQuery("TestSaveable", "SELECT * FROM TestSaveable");
		foreach ($result as $object)
		{
			$this->assertInstanceOf("TestSaveable", $object);
			$this->assertGreaterThan(0, $object->TestSaveableID);
			$this->assertNotEmpty($object->Label);
		}
		$this->assertEquals("Test object", $result[0]->Label);
		$this->assertEquals("Test object 2", $result[1]->Label);
	}

	/**
	 * @todo Implement testLoad().
	 */
	public function testLoad() {
		$test = new TestSaveable();
		$test->Label = "Test object";
		DBM::save($test);
		$test2 = new TestSaveable();
		$test2->TestSaveableID = $test->TestSaveableID;
		DBM::load($test2);
		$this->assertEquals("Test object", $test2->Label);
	}

	/**
	 * @todo Implement testDiagnostics().
	 */
	public function testDiagnostics() {
		// Remove the following lines when you implement this test.
		$expected = array(
			"queries" => 0,
			"cachemisses" => 0,
			"cachehits" => 0,
			"cachepercent" => "0%"
		);
		$this->assertEquals($expected, DBM::diagnostics());
		DBM::query("SHOW TABLES");
		DBM::query("SHOW TABLES", true);
		$expected = array(
			"queries" => 2,
			"cachemisses" => 1,
			"cachehits" => 1,
			"cachepercent" => "50%"
		);
		$this->assertEquals($expected, DBM::diagnostics());
	}

	/**
	 * @todo Implement testQueryCacheDebug().
	 */
	public function testQueryCacheDebug() {
		DBM::query("SHOW TABLES");
		DBM::query("SHOW TABLES", true);
		$qcd = DBM::queryCacheDebug();
		$this->assertEquals(1, count($qcd));
		$this->assertEquals("SHOW TABLES", $qcd[0]["query"]);
		$this->assertInternalType("float", $qcd[0]["time"]);
		$this->assertLessThan(1, $qcd[0]["time"]);
		$this->assertEquals(1, $qcd[0]["hits"]);
	}

	/**
	 * @todo Implement testQueryToCSV().
	 */
	public function testQueryToCSV() {
		// Remove the following lines when you implement this test.
		DBM::query("CREATE TABLE Testing(ID INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY, Label VARCHAR(60))");
		DBM::query("INSERT INTO Testing (Label) VALUES (\"Value1\")");
		DBM::query("INSERT INTO Testing (Label) VALUES (\"Value ,2\")");
		$this->assertEquals(array("ID,Label", "1,Value1", "2,\"Value ,2\""), DBM::queryToCSV("SELECT * FROM Testing"));
	}

	/**
	 * @todo Implement testTotalQueryTime().
	 */
	public function testTotalQueryTime() {
		$total = DBM::totalQueryTime();
		DBM::query("SHOW TABLES");
		$this->assertGreaterThan($total, DBM::totalQueryTime());
	}

	/**
	 * @todo Implement testLastQueryTime().
	 */
	public function testLastQueryTime() {
		$quickTime = 0;
		$slowTime = 0;
		for ($i = 0; $i < 10; $i++)
		{
			DBM::query("CREATE TABLE test (ID INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY, Label VARCHAR(60) NOT NULL DEFAULT \"testing\")");
			$slowTime += DBM::lastQueryTime();
			DBM::query("SHOW TABLES");
			$quickTime += DBM::lastQueryTime();
			DBM::query("DROP TABLE test");
		}
		$this->assertGreaterThan($quickTime, $slowTime);
	}

}

?>
