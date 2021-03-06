<?php

require_once dirname(__FILE__) . '/../../../../FieldDefinitions/NumberFieldDefinition.php';

/**
 * Test class for NumberFieldDefinition.
 * Generated by PHPUnit on 2011-05-30 at 22:48:16.
 */
class NumberFieldDefinitionTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var NumberFieldDefinition
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

	public function testManufacture() {
		$x = NumberFieldDefinition::manufacture("test");
		$this->assertInstanceOf("NumberFieldDefinition", $x);
		$this->assertInstanceOf("FieldDefinition", $x);
		$this->assertEquals("~~".get_class($x)."~~", $x->getComment());
		$this->assertEquals(null, $x->getDecimals());
		$this->assertEquals(null, $x->getDefault());
		$this->assertEquals(null, $x->getExtra());
		$this->assertEquals(false, $x->getIsAutoIncrement());
		$this->assertEquals(true, $x->getIsInteger());
		$this->assertEquals(true, $x->getIsNullable());
		$this->assertEquals(true, $x->getIsSigned());
		$this->assertEquals(false, $x->getIsZerofill());
		$this->assertEquals(10, $x->getLength());
		$this->assertEquals("test", $x->getName());
		$this->assertEquals("bigint(10)", $x->getType());
		$this->assertEquals("`test` bigint(10) NULL COMMENT '~~NumberFieldDefinition~~'", $x->toSql());
		$x = NumberFieldDefinition::manufacture();
		$this->assertInstanceOf("NumberFieldDefinition", $x);
		$this->assertInstanceOf("FieldDefinition", $x);
		$this->assertEquals("~~".get_class($x)."~~", $x->getComment());
		$this->assertEquals(null, $x->getDecimals());
		$this->assertEquals(null, $x->getDefault());
		$this->assertEquals(null, $x->getExtra());
		$this->assertEquals(false, $x->getIsAutoIncrement());
		$this->assertEquals(true, $x->getIsInteger());
		$this->assertEquals(true, $x->getIsNullable());
		$this->assertEquals(true, $x->getIsSigned());
		$this->assertEquals(false, $x->getIsZerofill());
		$this->assertEquals(10, $x->getLength());
		$this->assertEquals(null, $x->getName());
		$this->assertEquals("bigint(10)", $x->getType());
		$this->setExpectedException("Exception");
		$x->toSql();
	}

	public function testSetIsAutoIncrement() {
		$x = NumberFieldDefinition::manufacture("test");
		$x->setIsSigned(false);
		$x->setIsAutoIncrement(true);
		$this->assertEquals("`test` bigint(10) NULL AUTO_INCREMENT COMMENT '~~NumberFieldDefinition~~'", $x->toSql());
		$this->assertEquals(true, $x->getIsAutoIncrement());
		$x->setIsAutoIncrement(false);
		$this->assertEquals("`test` bigint(10) NULL COMMENT '~~NumberFieldDefinition~~'", $x->toSql());
		$this->assertEquals(false, $x->getIsAutoIncrement());
		$this->setExpectedException("Exception", "Problem(s) setting setIsAutoIncrement on class 'NumberFieldDefinition' : [\"Auto Increment number fields must be integers only - field is now an integer\"]");
		$x->setIsInteger(false);
		$x->setIsAutoIncrement(true);
		// EXCEPTION RELEASED, TEST ENDS
	}

	public function testMatchesSqlType() {
		$x = NumberFieldDefinition::manufacture("test");
		$expectTrue = array(
			"bigint(10)",
			"mediumint(10)",
			"smallint(10)",
			"tinyint(10)",
			"int(10)",
			"integer(10)",
			"float(10,2)",
			"decimal(10,2)",
			"real(10,2)",
			"numeric(10,2)",
			"~~NumberFieldDefinition~~"
		);
		foreach ($expectTrue as $value)
		{
			$this->assertTrue(NumberFieldDefinition::matchesSqlType($value));
		}
		$expectFalse = array(
			"varchar(10)",
			"char(10)",
			"date",
			"datetime",
			"blob"
		);
		foreach ($expectFalse as $value)
		{
			$this->assertFalse(NumberFieldDefinition::matchesSqlType($value));
		}
	}

	/**
	 * @todo Implement testFromSQLCreateFieldLine().
	 */
	public function testFromSQL() {
		$sql = "`UserID` bigint(10) NOT NULL AUTO_INCREMENT COMMENT 'Custom Comment ~~NumberFieldDefinition~~'";
		/* @var $x NumberFieldDefinition */
		$x = NumberFieldDefinition::fromSQL($sql);
		$this->assertEquals($sql, $x->toSql());
	}

	/**
	 * @todo Implement testGetIsAutoIncrement().
	 */
	public function testGetIsAutoIncrement() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testSetIsInteger().
	 */
	public function testSetIsInteger() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetIsInteger().
	 */
	public function testGetIsInteger() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testSetIsZerofill().
	 */
	public function testSetIsZerofill() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetIsZerofill().
	 */
	public function testGetIsZerofill() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetLength().
	 */
	public function testGetLength() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testSetLength().
	 */
	public function testSetLength() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testSetIsSigned().
	 */
	public function testSetIsSigned() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetIsSigned().
	 */
	public function testGetIsSigned() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testGetDecimals().
	 */
	public function testGetDecimals() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testSetDecimals().
	 */
	public function testSetDecimals() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testSetDefault().
	 */
	public function testSetDefault() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testIsValidValue().
	 */
	public function testIsValidValue() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @todo Implement testRepairValue().
	 */
	public function testRepairValue() {
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
	 * @todo Implement testExampleValue().
	 */
	public function testExampleValue() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

}

?>
