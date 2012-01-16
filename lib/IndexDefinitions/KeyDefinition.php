<?php
	require_once(dirname(__FILE__)."/../IndexDefinition.php");

	class KeyDefinition extends IndexDefinition
	{
		/**
		 * Manufactures a KeyDefinition for method chaining.
		 *
		 * @param string $name
		 * @return KeyDefinition 
		 */
		public static function manufacture($name = null)
		{
			return new KeyDefinition($name);
		}
		
		/**
		 * DISABLED FOR KEYS - throws exception if 'false' is passed.
		 *
		 * @param boolean $key
		 * @return KeyDefinition
		 */
		public function setIsKey($key = true)
		{
			if ($key != true)
			{
				throw new Exception("Cannot set a key index to not be a key");
			}
			
			parent::setIsKey($key);
			
			return $this;
		}
		
		/**
		 * Sets up a Key.
		 *
		 * @param string $name 
		 */
		public function __construct($name = null)
		{
			parent::__construct($name);
			
			$this->setIsKey(true);
		}
		
		public function matchesSqlType($line)
		{
			return preg_match("/^(UNIQUE )?KEY/i", trim($line, " ,")) == 1;
		}
		
		protected static function fromSQLCreateIndexLine($sql)
		{
			$originalSql = $sql;
			$sql = trim($sql, " ,");
			
			$x = KeyDefinition::manufacture();
			
			$matches = array();
			if (preg_match("/^UNIQUE /i", $sql, $matches))
			{
				$x->setIsUnique(true);
				$sql = substr($sql, strlen($matches[0]));
			}
			
			$sql = substr($sql, strlen("KEY "));
			
			if (preg_match("/^`(?P<name>([^\\]\\`|[^`])+)` /i", $sql, $matches))
			{
				$x->setName($matches["name"]);
				$sql = substr($sql, strlen($matches[0]));
			}
			
			if (preg_match("/^\(/", $sql, $matches))
			{
				$sql = substr($sql, strlen($matches[0]));
				while (preg_match("/^`(?P<field>([^\\]\\`|[^`])+)`(,( )?)?/i", $sql, $matches))
				{
					$x->addField($matches["field"]);
					$sql = substr($sql, strlen($matches[0]));
				}
			}
			if (preg_match ("/^\)/i", $sql, $matches))
			{
				$sql = substr($sql, strlen($matches[0]));
			}
			else
			{
				throw new Exception("Unexpected end of field list for index; ".
					"sql given: ".$originalSql);
			}
			if (count($x->getFields()) === 0)
			{
				throw new Exception("Unexpected lack of fields for index; ".
					"sql given: ".$originalSql);
			}
			
			return $x;
			
		}
		
		
	}
?>
