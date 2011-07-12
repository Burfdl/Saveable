<?php
	require_once("KeyDefinition.php");
	
	class PrimaryKeyDefinition extends KeyDefinition
	{
		/**
		 * Returns a new PrimaryKeyDefinition for method chaining.
		 *
		 * @param string $name
		 * @return PrimaryKeyDefinition 
		 */
		public static function manufacture($name = null)
		{
			return new PrimaryKeyDefinition($name);
		}
		
		public function setName($newName = null) {
			if ($newName !== null)
			{
				throw new Exception("Primary keys do not accept names");
			}
			
			parent::setName($newName);
		}
		
		public static function fromSQLCreateIndexLine($sql)
		{
			$originalSql = $sql = trim($sql);
			$key = new PrimaryKeyDefinition();
			
			$matches = array();
			if (!preg_match("/^PRIMARY KEY /i", $sql, $matches))
			{
				throw new Exception("Cannot create primary key from given sql: '".$originalSql."'");
			}
			$sql = substr($sql, strlen($matches[0]));
			
			if (preg_match("/^`(?P<name>([^\\]\\`|[^`])+)` /i", $sql, $matches))
			{
				throw new Exception("Primary keys may not be named; name found in given sql: '".$originalSql."'");
				$sql = substr($sql, strlen($matches[0]));
			}
			
			if (preg_match("/^\(/", $sql, $matches))
			{
				$sql = substr($sql, strlen($matches[0]));
				while (preg_match("/^(, )?`(?P<field>([^\\]\\`|[^`])+)`/", $sql, $matches))
				{
					$key->addField($matches["field"]);
					$sql = substr($sql, strlen($matches[0]));
				}
				if (!preg_match("/^\)/", $sql, $matches))
				{
					throw new Exception("Unexpected end of fields in primary key given sql: '".$originalSql."'");
				}
				$sql = substr($sql, strlen($matches[0]));
			}
			
			if (count($key->getFields()) === 0)
			{
				throw new Exception("No field names found for primary key in given sql: '".$originalSql."'");
			}
			
			return $key;
		}
		
		/**
		 * DISABLED FOR PRIMARY KEYS - Throws exception if 'false' passed
		 *
		 * @param boolean $primary
		 * @return PrimaryKeyDefinition 
		 */
		public function setIsPrimary($primary = true) {
			if ($primary != true)
			{
				throw new Exception("Cannot set a primary key to not be ".
					"primary");
			}
			
			parent::setIsPrimary($primary);
			
			return $this;
		}
		
		/**
		 * Constructs a primary key.
		 *
		 * @param string $name 
		 */
		public function __construct($name = null)
		{
			parent::__construct($name);
			$this->setIsPrimary(true);
		}
		
		public function matchesSqlType($line)
		{
			return preg_match("/^PRIMARY KEY (`(?P<name>.+)` )?\((?P<fields>.*)\)/i", trim($line)) == 1;
		}
	}
?>
