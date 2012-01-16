<?php
	require_once("FieldDefinition.php");
	require_once("IndexDefinition.php");

	class TableDefinition
	{
		private $name = "";
		private $fields = array();
		private $indexes = array();
		private $allowExisting = false;
		private static $cachedSql = array();
		private static $cachedObjects = array();
		
		/**
		 * Creates and returns a new TableDefinition for method chaining
		 *
		 * @param string $name
		 * @return TableDefinition 
		 */
		public static function manufacture($name)
		{
			return new TableDefinition($name);
		}
		
		public function allowExistingTable($allow = false)
		{
			$this->allowExisting = $allow == true;
		}
		
		public static function fromSQL($sql)
		{
			if (in_array($sql, self::$cachedSql))
			{
				return self::$cachedObjects[array_search($sql, self::$cachedSql)];
			}
			if (!is_string($sql))
			{
				throw new Exception("Expecting table definition as a SQL ".
					"string; given: '".$sql."'");
			}
			$tableName = array_pop(array_slice(explode("`", $sql), 1, 1));
			if (strpos($tableName, DBM::$tablePrefix) === 0)
			{
				$tableName = substr($tableName, strlen(DBM::$tablePrefix));
			}
			$tableDefinition = new TableDefinition($tableName);
			$lines = explode("\n", $sql);
			$lines = array_slice($lines, 1, count($lines) - 2);
			foreach ($lines as $line)
			{
				$line = trim($line);
				if (strpos($line, "`") === 0)
				{	// Its a field
					$tableDefinition->addField(
						FieldDefinition::fromSQL($line)
					);/**/
				}
				else if (preg_match("/^(PRIMARY |UNIQUE |FOREIGN )?(KEY|INDEX)/i", $line))
				{	// Its an index (or something terrible)
					$tableDefinition->addIndex(
						IndexDefinition::fromSQL($line)
					);
				}
				else
				{
					throw new Exception("Unrecognised SQL Line type: '".$line."'");
				}
			}
			
			self::$cachedObjects[] = $tableDefinition;
			self::$cachedSql[] = $sql;
			
			return $tableDefinition;/**/
		}
		
		/**
		 * Returns the table name
		 *
		 * @return string
		 */
		public function getName()
		{
			return $this->name;
		}
		
		/**
		 * Constructs and returns a TableDefinition for creation
		 *
		 * @param string $name of table
		 * @return TableDefinition 
		 */
		public function __construct($name)
		{
			if (!is_string($name) && method_exists($name, "__toString"))
			{
				$name = $name->__toString();
			}
			if (!is_string($name) || strlen($name) == 0)
			{
				throw new Exception("Attempting to create a table definition ".
					"without a name");
			}
			$this->name = $name;
			
			return $this;
		}
		
		/**
		 * Adds a field to the table
		 *
		 * @param FieldDefinition $newField to add
		 * @return TableDefinition 
		 */
		public function addField(FieldDefinition $newField)
		{
			foreach ($this->fields as $oldField)
			{
				/* @var $oldField FieldDefinition */
				if ($oldField->getName() == $newField->getName())
				{
					throw new Exception("Field name '".$newField->getName().
						"' already exists in table");
				}
			}
			$this->fields[] = $newField;
			$this->cachedFieldNames = null;
			return $this;
		}
		
		/**
		 * Returns all fields currently in the table definition
		 *
		 * @return FieldDefinition[]
		 */
		public function getFields()
		{
			return $this->fields;
		}
		
		/**
		 * Returns a field definition by name.
		 * Retuns null if no field is found by the given name.
		 *
		 * @param string $name 
		 * @return FieldDefinition
		 */
		public function getField($name)
		{
			foreach ($this->fields as $field)
			{	/* @var $field FieldDefinition */ 
				if ($field->getName() == $name)
				{
					return $field;
				}
			}
			return null;
		}
		
		private $cachedFieldNames = null;
		
		/**
		 * Gets all field names currently in the table definition as strings.
		 *
		 * @return string[]
		 */
		public function getFieldNames()
		{
			if ($this->cachedFieldNames !== null)
			{
				return $this->cachedFieldNames;
			}
			$returnable = array();
			foreach ($this->fields as $field)
			{
				/* @var $field FieldDefinition */
				$returnable[] = $field->getName();
			}
			return $this->cachedFieldNames = $returnable;
		}
		
		/**
		 * Adds an index to the table
		 *
		 * @param IndexDefinition $newIndex
		 * @return TableDefinition 
		 */
		public function addIndex(IndexDefinition $newIndex)
		{
			$fieldNames = $this->getFieldNames();
			foreach ($newIndex->getFields() as $field)
			{
				if (!in_array($field, $fieldNames))
				{
					throw new Exception("Field name '".$field."' in index '".
						$newIndex->getName()."' not found in table '".
						$this->getName()."'. Add fields before indexes.");
				}
			}
			foreach ($this->indexes as $oldIndex)
			{
				/* @var $oldIndex IndexDefinition */
				if ($oldIndex->getName() == $newIndex->getName())
				{
					throw new Exception("Index name '".$newIndex->getName().
						"' already exists in table");
				}
			}
			$this->indexes[] = $newIndex;
			return $this;
		}
		
		/**
		 * Returns all indexes currently defined on the array.
		 *
		 * @return IndexDefinition[]
		 */
		public function getIndexes()
		{
			return $this->indexes;
		}
		
		/**
		 * Converts the table definition to SQL
		 * 
		 * @return string sql
		 */
		public function toSql()
		{
			// TODO: @DG Improve TableDefinition::toSql
			$fieldsSql = array();
			foreach ($this->fields as $field)
			{
				/* @var $field FieldDefinition */
				$fieldsSql[] = $field->toSql();
			}
			
			foreach ($this->indexes as $index)
			{
				/* @var $index IndexDefinition */
				$fieldsSql[] = $index->toSql();
			}
			
			return "CREATE TABLE ".($this->allowExisting ? "IF NOT EXISTS " : "")."`".
				DBM::escape(DBM::$tablePrefix.$this->name)."` (\n  ".
				implode(",\n  ", $fieldsSql)."\n)";
		}
		
		public function validate($dataArray)
		{
			if (!is_array($dataArray))
			{
				throw new Exception("Cannot validate given data; expecting ".
					"array(fieldName => fieldValue) :- received ".
					serialize($dataArray));
			}
			$fieldNames = array();
			foreach ($this->fields as $field)
			{
				/* @var $field FieldDefinition */
				$fieldNames[] = $field->getName();
				if (!isset($dataArray[$field->getName()]) && 
					!$field->getIsNullable())
				{
					if ($field->getDefault() !== null)
					{
						$dataArray[$field->getName()] = $field->getDefault();
					}
					else
					{
						throw new Exception("Field '".$field->getName()."' ".
							"missing from the data given while validating ".
							"table '".$this->getName()."'");
					}
				}
				if (isset($dataArray[$field->getName()]) && 
					!$field->isValidValue($dataArray[$field->getName()]))
				{
					throw new Exception(get_class($field)." '".
						$field->getName()."' doesn't accept '".
						$dataArray[$field->getName()]."'; expecting ".
						"something like '".$field->exampleValue()."'");
				}
			}
			
			return count(array_diff(array_keys($dataArray), $fieldNames)) == 0;
		}
	}
?>
