<?php

	// Also includes all IndexDefinitions found in ./IndexDefinitions/*.php


	class IndexDefinition
	{
		protected $name = null;
		protected $type = "INDEX";
		protected $fields = array();
		protected $isUnique = false;
		protected $isPrimary = false;
		protected $isKey = false;
		
		/**
		 * Returns the index name
		 *
		 * @return string
		 */
		public function getName()
		{
			return $this->name;
		}
		
		/**
		 * Returns true if the index is a relational key as well as a 
		 * search index.
		 *
		 * @return boolean
		 */
		public function getIsKey()
		{
			return $this->isKey;
		}
		
		/**
		 * Sets if the index is a relational key as well as a search index.
		 *
		 * @param boolean $key
		 * @return IndexDefinition 
		 */
		public function setIsKey($key = false)
		{
			$this->isKey = ($key == true);
			
			$this->updateType();
			
			return $this;
		}
		
		public function matchesSqlType($line)
		{
			return preg_match("/^(UNIQUE )?INDEX (`(?P<name>[A-Z0-9]+)` )?\((?P<fields>.*)\)/i", trim($line)) == 1;
		}
		
		protected static function fromSQLCreateIndexLine($sql)
		{
			$originalSql = $sql;
			$sql = trim($sql, " ,");
			
			$x = IndexDefinition::manufacture();
			
			$matches = array();
			if (preg_match("/^UNIQUE /i", $sql, $matches))
			{
				$x->setIsUnique(true);
				$sql = substr($sql, strlen($matches[0]));
			}
			
			$sql = substr($sql, strlen("INDEX "));
			
			if (preg_match("/^`(?P<name>([^\\\\]\\\\`|[^\\\\])+)` /i", $sql, $matches))
			{
				$x->setName($matches["name"]);
				$sql = substr($sql, strlen($matches[0]));
			}
			
			if (preg_match("/^\(/", $sql, $matches))
			{
				$sql = substr($sql, strlen($matches[0]));
				while (preg_match("/^`(?P<field>[a-z0-9]+)`(,( )?)?/i", $sql, $matches))
				{
					echo "found field '".$matches["field"]."'\n";
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
		
		/**
		 * Sets if the index is a primary key or not
		 *
		 * @param boolean $primary
		 * @return IndexDefinition 
		 */
		public function setIsPrimary($primary = false)
		{
			$this->isPrimary = ($primary == true);
			$this->isUnique = ($this->isPrimary ? true : $this->isUnique);
			
			$this->updateType();
			
			return $this;
		}
		
		/**
		 * Returns true if the index is a primary-key index
		 *
		 * @return boolean
		 */
		public function getIsPrimary()
		{
			return $this->isPrimary;
		}
		
		/**
		 * Returns a new IndexDefinition for method chaining.
		 *
		 * @param string $name
		 * @return IndexDefinition 
		 */
		public static function manufacture($name = null)
		{
			return new IndexDefinition($name);
		}
		
		/**
		 * Constructs a new IndexDefinition
		 *
		 * @param string $name
		 */
		public function __construct($name = null)
		{
			$this->setName($name);
		}
		
		/**
		 * Sets the index name (accepted but not used for primary key indexes)
		 * 
		 * @param string $newName
		 * @return IndexDefinition 
		 */
		public function setName($newName = null)
		{
			if (is_string($newName) || $newName === null)
			{
				$this->name = $newName;
			}
			else
			{
				throw new Exception("Index names must be strings");
			}
			
			$this->updateType();
			
			return $this;
		}
		
		/**
		 * Sets if the index has a uniqueness constraint.
		 *
		 * @param boolean $unique
		 * @return IndexDefinition 
		 */
		public function setIsUnique($unique = false)
		{
			if ($this->isPrimary && $unique == false)
			{
				throw new Exception("Primary keys must also be unique - ".
					"index is still unique");
			}
			
			$this->isUnique = ($unique == true);
			
			$this->updateType();
			
			return $this;
		}
		
		/**
		 * Returns true if the index has a uniqueness constraint
		 *
		 * @return boolean
		 */
		public function getIsUnique()
		{
			return $this->isUnique;
		}
		
		public function getType()
		{
			return $this->type;
		}
		
		protected function updateType()
		{
			$this->type = ($this->isPrimary ? 
					"PRIMARY ".($this->isKey ? 
							"KEY" 
							: "INDEX")
					: ($this->isUnique ? 
							"UNIQUE " 
							: "").($this->isKey ? 
									"KEY" 
									: "INDEX").
								" `".$this->name."`").
				(isset($this->isUsingBTree) && $this->isUsingBTree ? 
						" USING BTREE" 
						: (isset($this->isUsingHash) && $this->isUsingHash ? 
								" USING HASH" 
								: ""));
		}
		
		/**
		 * Converts the index definition to MySQL compatible SQL
		 * 
		 * @return string sql
		 */
		public function toSql()
		{
			return $this->type." (`".implode("`, `", $this->fields)."`)";
		}
		
		/**
		 * Returns array of field names in index
		 *
		 * @return string[]
		 */
		public function getFields()
		{
			return $this->fields;
		}
		
		/**
		 * Removes a field from the index, if it existed.
		 *
		 * @param string $field
		 * @return IndexDefinition 
		 */
		public function removeField($field)
		{
			if (in_array($field, $this->fields))
			{
				$newFields = array();
				foreach ($this->fields as $oldField)
				{
					if ($oldField != $field)
					{
						$newFields[] = $oldField;
					}
				}
				$this->fields = $newFields;
			}
			
			return $this;
		}
		
		/**
		 * Adds a field to the index
		 *
		 * @param string $newField
		 * @return IndexDefinition 
		 */
		public function addField($newField)
		{
			if (in_array($newField, $this->fields))
			{
				throw new Exception("Cannot add field '".
					$newField."' to index as it already exists in ".
					"'".$this->getName()."'");
			}

			$this->fields[] = $newField;

			return $this;
		}
		
		/**
		 *
		 * @param string $line
		 * @return IndexDefinition
		 */
		public static function fromSQL($line)
		{
			foreach (get_declared_classes() as $className)
			{
				$reflection = new ReflectionClass($className);
				if (
					$reflection->isInstantiable() &&
					$reflection->isSubclassOf("IndexDefinition") &&
					call_user_func(array($className, "matchesSqlType"), $line)
				)
				{
					return call_user_func(array($className, "fromSQLCreateIndexLine"), $line);
				}
			}
			throw new Exception("Unknown index '".$line."' from SQL '".$line."'");
		}
	}
	
	if (file_exists(dirname(__FILE__)."/IndexDefinitions"))
	{
		$handle = opendir(dirname(__FILE__)."/IndexDefinitions");
		$includables = array();
		while ($indexDefinitionFile = readdir($handle))
		{
			if (strpos(strrev($indexDefinitionFile), "php.") === 0)
			{
				$includables[] = $indexDefinitionFile;
			}
		}
		foreach ($includables as $include)
		{
			require_once(dirname(__FILE__)."/IndexDefinitions/".$include);
		}
	}
	
?>
