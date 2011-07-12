<?php
	require_once("KeyDefinition.php");

	class ForeignKeyDefinition extends KeyDefinition
	{
		private $foreignTableName = null;
		private $foreignFieldNames = array();
		private $constraint = null;
		// TODO: @DG Make foreign keys work!
		// What if the foreign table doesn't exist?
		// What if they want to refer to a Key on the foreign table by name only?
		// What happens on delete?
		// What happens on update?
		
		public function addForeignField($fieldName)
		{
			if (!in_array($fieldName, $this->foreignFieldNames))
			{
				$this->foreignFieldNames[] = $fieldName;
			}
		}
		
		public function setConstraint($constraint)
		{
			$this->constraint = $constraint;
		}
		
		public function getConstraint()
		{
			return $this->constraint;
		}
		
		public function getForeignFields()
		{
			return $this->foreignFieldNames;
		}

		public function matchesSqlType($line) {
			return preg_match("/^( +)?(CONSTRAINT `([^\\]\\`|[^`])+` )?FOREIGN KEY/i", $line);
		}
		
		public static function fromSQLCreateIndexLine($sql)
		{
			$originalSql = $sql = trim($sql);
			$key = new ForeignKeyDefinition("UnnamedForeignKey".time());
			
			$matches = array();
			if (preg_match("/^( +)?CONSTRAINT `(?P<constraint>[^\\]\\`|[^`])+`/", $sql, $matches))
			{
				$key->setConstraint($matches["constraint"]);
				$sql = trim(substr($sql, strlen($matches[0])));
			}
			if (preg_match("/^FOREIGN KEY /i", $sql, $matches))
			{
				$sql = substr($sql, strlen($matches[0]));
			}
			
			if (preg_match("/^`(?P<name>([^\\]\\`|[^`])+)` /i", $sql, $matches))
			{
				$key->setName($matches["name"]);
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
				if (preg_match("/^\)/", $sql, $matches))
				{
					$sql = trim(substr($sql, strlen($matches[0])));
				}
				else
				{
					throw new Exception("Unexpected end of fields in foreign key given sql: '".$originalSql."'");
				}
			}
			
			if (count($key->getFields()) === 0)
			{
				throw new Exception("No field names found for foreign key in given sql: '".$originalSql."'");
			}
			
			if (preg_match("/REFERENCES `(?P<table>([^\\]\\`|[^`])+)` \(/", $sql, $matches))
			{
				$key->setForeignTableName($matches["table"]);
				$sql = trim(substr($sql, strlen($matches[0])));
				while (preg_match("/^(, )?`(?P<field>([^\\]\\`|[^`])+)`/", $sql, $matches))
				{
					$key->addForeignField($matches["field"]);
					$sql = substr($sql, strlen($matches[0]));
				}
				if (preg_match("/^\)/", $sql, $matches))
				{
					$sql = trim(substr($sql, strlen($matches[0])));
				}
				else
				{
					throw new Exception("Unexpected end of foreign fields in foreign key given sql: '".$originalSql."'");
				}
			}
			else
			{
				throw new Exception("Referenced table not found for foreign key in given sql: '".$originalSql."'");
			}
			
			if (count($key->getForeignFields()) === 0)
			{
				throw new Exception("No foreign field names found for foreign key in given sql: '".$originalSql."'");
			}
			
			if (preg_match("/^ON DELETE (?P<action>RESTRICT|CASCADE|SET NULL|NO ACTION)/", $sql, $matches))
			{
				$key->setDeleteAction($matches["action"]);
				$sql = substr($sql, strlen($matches[0]));
			}
			
			if (preg_match("/^ON UPDATE (?P<action>RESTRICT|CASCADE|SET NULL|NO ACTION)/", $sql, $matches))
			{
				$key->setUpdateAction($matches["action"]);
				$sql = substr($sql, strlen($matches[0]));
			}
			
			return $key;
		}
		
		public function toSql() {
			return ($this->constraint !== null ? "CONSTRAINT `".$this->constraint."` " : "").
				"FOREIGN KEY `".$this->name."` (`".implode("`,`", $this->fields)."`) REFERENCES `".$this->foreignTableName."` (`".implode("`,`", $this->foreignFieldNames)."`)".
				($this->deleteAction !== null ? " ON DELETE ".$this->deleteAction : "").
				($this->updateAction !== null ? " ON UPDATE ".$this->updateAction : "");
		}
		
		private $deleteAction = null;
		private $updateAction = null;
		
		public function setDeleteAction($action)
		{
			$action = strtoupper($action);
			$validDeleteActions = array("RESTRICT", "CASCADE", "SET NULL", "NO ACTION");
			if (!in_array($action, $validDeleteActions) && $action !== null)
			{
				throw new Exception("Invalid delete action given to foreign key; action must be one of ".implode(", ", $validDeleteActions));
			}
			$this->deleteAction = $action;
		}
		
		public function setUpdateAction($action)
		{
			$action = strtoupper($action);
			$validUpdateActions = array("RESTRICT", "CASCADE", "SET NULL", "NO ACTION");
			if (!in_array($action, $validUpdateActions) && $action !== null)
			{
				throw new Exception("Invalid Update action given to foreign key; action must be one of ".implode(", ", $validUpdateActions));
			}
			$this->updateAction = $action;
		}
		
		public function getForeignTableName()
		{
			return $this->foreignTableName;
		}
		
		/**
		 * Sets the table name the foreign key refers to
		 *
		 * @param string $newTableName 
		 */
		public function setForeignTableName($newTableName)
		{
			if (
				!is_string($newTableName) && 
				is_object($newTableName) && 
				method_exists($newTableName, "__toString")
			)
			{
				$newTableName = $newTableName->__toString();
			}
			if (!is_string($newTableName) && $newTableName !== null)
			{
				throw new Exception("Expecting table name as a string; given ".
					"'".$newTableName."'");
			}
			
			$this->foreignTableName = $newTableName;
		}
		
		/**
		 * Manufactures a new ForeignKeyDefinition for method chaining.
		 *
		 * @param string $name
		 * @param string $foreignTable
		 */
		public static function manufacture($name = null, $foreignTable = null)
		{
			return new ForeignKeyDefinition($name, $foreignTable);
		}
		
		/**
		 * DISABLED FOR FOREIGN KEYS - throws exception if 'true' is passed.
		 *
		 * @param boolean $primary
		 * @return ForeignKeyDefinition 
		 */
		public function setIsPrimary($primary = false) {
			if ($primary != false)
			{
				throw new Exception("Cannot make foreign keys be primary");
			}
			
			parent::setIsPrimary($primary);
			
			return $this;
		}
		
		/**
		 * Constructs a new ForeignKey
		 *
		 * @param type $name
		 * @param string $foreignTable
		 */
		public function __construct($name, $foreignTable = null)
		{
			parent::__construct($name);
			$this->setForeignTableName($foreignTable);
		}
	}
?>
