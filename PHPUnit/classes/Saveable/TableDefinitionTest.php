<?php

require_once dirname(__FILE__) . '/../../../TableDefinition.php';

/**
 * Test class for TableDefinition.
 * Generated by PHPUnit on 2011-05-30 at 22:45:59.
 */
class TableDefinitionTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var TableDefinition
	 */
	protected $object;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() {
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown() {
		
	}

	/**
	 * @todo Implement testManufacture().
	 */
	public function testManufacture() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testFromSQLCreateTable().
	 */
	public function testFromSQLCreateTable() {
		$table = "CREATE TABLE `BudgetGraph_User` (
 `UserID` bigint(10) NOT NULL AUTO_INCREMENT COMMENT 'Custom Comment ~~NumberFieldDefinition~~',
 `Username` varchar(60) NOT NULL COMMENT '~~TextFieldDefinition~~',
 `Password` varchar(32) NOT NULL COMMENT '~~TextFieldDefinition~~',
 `Email` varchar(2000) NOT NULL COMMENT '~~EmailFieldDefinition~~',
 PRIMARY KEY (`UserID`),
 UNIQUE KEY `UserUsernamePasswordIndex` (`Username`,`Password`),
 KEY `SomeKey` (`Email`),
 INDEX `SomeOtherKey` (`Email`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1";
		$x = TableDefinition::fromSQL($table);
		$this->assertEquals($table, $x->toSql());
	}

	public function testGetName()
	{
		$x = TableDefinition::manufacture("name");
		$this->assertEquals("name", $x->getName());
		$this->setExpectedException("PHPUnit_Framework_Error");
		$x = TableDefinition::manufacture();
	}

	/**
	 * @todo Implement testAddField().
	 */
	public function testAddField() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetFields().
	 */
	public function testGetFields() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testAddIndex().
	 */
	public function testAddIndex() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetIndexes().
	 */
	public function testGetIndexes() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testToSql().
	 */
	public function testToSql() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testValidate().
	 */
	public function testValidate() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

}

?>
