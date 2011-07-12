<?php
	require_once("DBM.php");

	// Also includes all FieldDefinitions found in ./FieldDefinitions/*.php

	abstract class FieldDefinition
	{
		protected $name = null;
		protected $type = null;
		protected $nullable = true;
		protected $default = null;
		protected $extra = null;
		protected $comment = null;
		
		/**
		 * Manufactures a new FieldDefinition for method chaining
		 * 
		 * @return FieldDefinition
		 */
		public static function manufacture($name = null)
		{
			throw new Exception("I don't know what kind of field to manufacture; all field definitions must override 'public static function manufacture'");
		}
		
		/**
		 * Constructs a new Field
		 */
		public function __construct($name = null)
		{
			if ($name !== null)
			{
				$this->setName($name);
			}
			$this->comment = "~~".get_class($this)."~~";
		}
		
		/**
		 * Returns the field comment (if applicable)
		 *
		 * @return string
		 */
		public function getComment()
		{
			return $this->comment;
		}
		
		/**
		 * Sets the field comment
		 *
		 * @param string $comment
		 * @return FieldDefinition 
		 */
		public function setComment($comment)
		{
			if (
				!is_string($comment) && 
				is_object($comment) && 
				method_exists($comment, "__toString")
			)
			{
				$comment = $comment->__toString();
			}
			
			$commentSuffix = "~~".__CLASS__."~~";
			
			if (!is_string($comment))
			{
				throw new Exception("Comments must be strings");
			}
			
			if ($comment == get_class($this))
			{
				$comment = "";
			}
			
			$matches = array();
			if (preg_match("/~~(?P<class>[A-Z0-9]+FieldDefinition)~~/i", $comment, $matches))
			{
				$class = get_class($this);
				$lineage = array();
				while (
					($class = ($lineage[] = get_parent_class($class))) && 
					$class != "stdClass"
				);
				if (in_array($matches["class"], $lineage))
				{
					$comment = str_replace("~~".$matches["class"]."~~", "", $comment);
				}
				else
				{
					$commentSuffix = "";
				}
					
			}
			
			$this->comment = $comment.$commentSuffix;
			
			return $this;
		}
		
		/**
		 * Returns the name of the field
		 *
		 * @return string
		 */
		public function getName()
		{
			return $this->name;
		}
		
		/**
		 * Sets the field name
		 *
		 * @param string $newName
		 * @return FieldDefinition 
		 */
		public function setName($newName)
		{
			$this->name = $newName;
			return $this;
		}
		
		/**
		 * Returns a type definition
		 *
		 * @return string
		 */
		public function getType()
		{
			return $this->type;
		}
		
		/**
		 * Returns true if the field will accept null values
		 * (undefined/empty)
		 *
		 * @return boolean
		 */
		public function getIsNullable()
		{
			return $this->nullable;
		}
		
		/**
		 * Sets whether or not the field can be skipped on inserts
		 *
		 * @param boolean $isNullable
		 * @return FieldDefinition 
		 */
		public function setIsNullable($isNullable = true)
		{
			$this->nullable = ($isNullable == true);
			return $this;
		}
		
		/**
		 * Returns the default initial field value
		 *
		 * @return mixed
		 */
		public function getDefault()
		{
			return $this->default;
		}
		
		/**
		 * Returns the extra-definition parts of the field definition
		 *
		 * @return string
		 */
		public function getExtra()
		{
			return $this->extra;
		}
		
		/**
		 * Returns true if this field is capable of defining the given line of
		 * SQL.
		 * Expects the SQL to be a field-defining line from a SHOW CREATE TABLE
		 * query.
		 * 
		 * @param $fieldDetails SQL Field definition from SHOW CREATE TABLE
		 * @returns boolean
		 */
		abstract function matchesSqlType($fieldDetails);
		
		/**
		 * Create and return a FieldDefinition based on the SQL line given
		 * 
		 * Expects the SQL to be a field-defining line from a SHOW CREATE TABLE
		 * query.
		 * 
		 * @param $fieldDetails SQL Field definition from SHOW CREATE TABLE
		 * @return FieldDefinition
		 */
		abstract protected function fromSQLCreateFieldLine($name, $line);
		
		public static function fromSQL($line)
		{
			$fieldName = substr($line, strpos($line, "`") + 1);
			$fieldName = substr($fieldName, 0, strpos($fieldName, "`"));
			$fieldDetails = trim(substr($line, strlen($fieldName)+2));
			$typeBit = array_shift(explode(" ", $fieldDetails));
			foreach (get_declared_classes() as $className)
			{
				$reflection = new ReflectionClass($className);
				if (
					$reflection->isInstantiable() &&
					$reflection->isSubclassOf("FieldDefinition") &&
					$reflection->hasMethod("matchesSqlType") &&
					/* @var $className FieldDefinition */
					call_user_func(array($className, "matchesSqlType"), $fieldDetails) &&
					$reflection->hasMethod("fromSQLCreateFieldLine")
				)
				{
					/* @var $className FieldDefinition */
					return call_user_func(array($className, "fromSQLCreateFieldLine"), $fieldName, $fieldDetails);
				}/**/
			}
			throw new Exception("Unknown data type '".$typeBit."' from SQL '".$line."'");/**/
		}
		
		/**
		 * Returns true if the given value is compatible with the field 
		 * definition
		 * 
		 * @return boolean
		 */
		abstract public function isValidValue($value);
		
		/**
		 * Attempts to repair a value to match the field definition. If the 
		 * value is irreperable, will return null.
		 * 
		 * @return null|mixed
		 */
		abstract public function repairValue($value);
		
		// TODO: @DG Check out "references" in MySQL http://tinyurl.com/z4mn4
		/**
		 * Converts the field definition to SQL
		 * 
		 * @return string SQL
		 */
		abstract public function toSql();
		
		/**
		 * Returns an expected value to give an idea of legal values.
		 * 
		 * 
		 * @return string expected value, or explanation
		 */
		abstract public function exampleValue();
	}
	
	if (file_exists(dirname(__FILE__)."/FieldDefinitions"))
	{
		$handle = opendir(dirname(__FILE__)."/FieldDefinitions");
		$includables = array();
		while ($fieldDefinitionFile = readdir($handle))
		{
			if (strpos(strrev($fieldDefinitionFile), "php.") === 0)
			{
				$includables[] = $fieldDefinitionFile;
			}
		}
		foreach ($includables as $include)
		{
			require_once(dirname(__FILE__)."/FieldDefinitions/".$include);
		}
	}
?>
