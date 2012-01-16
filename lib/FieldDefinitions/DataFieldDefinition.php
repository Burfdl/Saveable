<?php

	require_once(dirname(__FILE__)."/../FieldDefinition.php");

	class DataFieldDefinition extends FieldDefinition
	{
		private $length = 60;
		private $fixedWidth = false;
		private $exampleValue = null;
		private $dataType = "BLOB";
		
		public function __construct($name = null)
		{
			parent::__construct($name);
			$this->updateType();
		}
		
		/**
		 * Manufactures and returns a new DataFieldDefinition for method 
		 * chaining
		 *
		 * @param string $name 
		 * @return DataFieldDefinition
		 */
		public static function manufacture($name = null)
		{
			return new DataFieldDefinition($name);
		}
		
		/**
		 * Returns the maximum character length of the text.
		 * 'null' represents unlimited.
		 *
		 * @return int length
		 */
		public function getLength()
		{
			return $this->length;
		}
		
		public function matchesSqlType($fieldDetails)
		{
			return preg_match("/COMMENT '([^\\]\\'|[^'])~~".__CLASS__."~~([^\\]\\'|[^'])*'/", $fieldDetails) || 
				preg_match("/^((TINY|MEDIUM|LONG)?BLOB|(VAR)?BINARY)/i", $fieldDetails);
		}
		
		protected function fromSQLCreateFieldLine($name, $line)
		{
			$field = new DataFieldDefinition($name);
			$matches = array();
			if (preg_match("/^(?P<type>(TINY|MEDIUM|LONG)?BLOB|(VAR)?BINARY)/i", $line, $matches))
			{
				$field->setIsFixedWidth($matches["type"] == "BINARY");
				$field->dataType = $matches["type"];
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			if (preg_match("/^\((?P<digits>[0-9]+)\)/i", $line, $matches))
			{
				$field->setLength($matches["digits"]);
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			
			if (preg_match("/^(?P<not>NOT )?NULL/i", $line, $matches))
			{
				$field->setIsNullable(!isset($matches["not"]));
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			if (preg_match("/^DEFAULT '(?P<value>([^\\]\\'|[^'])*)'/i", $line, $matches)) // Get the default value, while handling escaped quotes inside the value
			{
				$field->
					setDefault(
						(isset($matches["value"]) ? 
							$matches["value"] 
							: null)
					);
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			if (preg_match("/^COMMENT '(?P<value>([^\\]\\'|[^'])*)'/i", $line, $matches)) // Get the comment value, while handling escaped quotes inside the value
			{
				$field->
					setComment((isset($matches["value"]) ?
						$matches["value"] 
						: null));
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			return $field;
		}
		
		public function setDefault($default)
		{
			
			if (is_object($default) && method_exists($default, "__toString"))
			{
				$default = $default->__toString();
			}
			
			if (is_string($default) || $default === null)
			{
				$this->default = $default;
			}
			else
			{
				throw new Exception("Defaults for ".get_class($this)." must ".
					"be strings");
			}
			
			return $this;
		}
		
		/**
		 * Sets the maximum character length of the text.
		 * Use 'null' for unlimited.
		 *
		 * @param int $newLength
		 * @return TextFieldDefinition
		 */
		public function setLength($newLength = 60)
		{
			if (is_string($newLength) && is_numeric($newLength))
			{
				$newLength = (int)$newLength;
			}
			if ((is_int($newLength) && $newLength > 0) || $newLength === null)
			{
				$this->length = $newLength;
			}
			else
			{
				throw new Exception("Text field lengths must be positive ".
					"integers, or null for unlimited; given '".$newLength."'");
			}
			
			$this->updateType();
			
			return $this;
		}
		
		/**
		 * Updates the type variable when something is changed
		 */
		private function updateType()
		{	
			
			$this->type = ($this->length === null ? 
				$this->dataType
				: $this->dataType."(".$this->length.")"
			);
		}
		
		/**
		 * Returns whether shorter strings are padded to the maximum length
		 *
		 * @return boolean
		 */
		public function getIsFixedWidth()
		{
			return $this->fixedWidth;
		}
		
		/**
		 * Sets whether shorter strings are padded to the maximum length
		 *
		 * @param boolean $fixedWidth 
		 * @return TextFieldDefinition
		 */
		public function setIsFixedWidth($fixedWidth = false)
		{
			if ($fixedWidth == null)
			{
				$this->fixedWidth = null;
			}
			$this->fixedWidth = ($fixedWidth == true);
			
			$this->updateType();
			
			return $this;
		}
		
		public function isValidValue($value)
		{
			return true;
		}
		
		/**
		 * Attempts to repair a value to meet the text field definition.
		 * If it cannot be repaired, returns null.
		 * 
		 * @param mixed $value
		 * @return string|null
		 */
		public function repairValue($value) {
			if (!is_string($value) && 
				is_object($value) && 
				method_exists($value, "__toString"))
			{
				$value = $value->__toString();
			}
			return ($this->isValidValue($value) ? $value : null);
		}
		
		/**
		 * Converts to an SQL definition.
		 *
		 * @return string sql
		 */
		public function toSql()
		{
			return "`".DBM::escape($this->getName())."` ".
				$this->getType()." ".
				($this->getIsNullable() ? "" : "NOT ")."NULL ".
				($this->getDefault() !== null ? 
					"DEFAULT \"".DBM::escape($this->getDefault())."\" " 
					: "").
				"COMMENT '".DBM::escape($this->comment)."'";
		}
		
		public function exampleValue()
		{
			if ($this->exampleValue !== null)
			{
				$characters = array("a", "1", "#");
				for ($i = 0; $i < $this->getLength(); $i++)
				{
					$this->exampleValue .= 
						$characters[($i % count($characters))];
				}
			}
			return $this->exampleValue;
		}
	} // class TextFieldDefinition
	
?>
