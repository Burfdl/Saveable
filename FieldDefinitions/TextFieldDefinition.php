<?php

	require_once(dirname(__FILE__)."/../FieldDefinition.php");

	// TODO: @DG Character Set? See MySQL docs at http://tinyurl.com/gwqzl

	class TextFieldDefinition extends FieldDefinition
	{
		private $length = 64;
		private $fixedWidth = false;
		private $exampleValue = null;
		
		public function __construct($name = null)
		{
			parent::__construct($name);
			$this->updateType();
		}
		
		/**
		 * Manufactures and returns a new TextFieldDefinition for method 
		 * chaining
		 *
		 * @param string $name 
		 * @return TextFieldDefinition
		 */
		public static function manufacture($name = null)
		{
			return new TextFieldDefinition($name);
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
				preg_match("/^((VAR)?CHAR)|((TINY|MEDIUM|LONG)?TEXT)/i", $fieldDetails);
		}
		
		protected function fromSQLCreateFieldLine($name, $line)
		{
			$textField = new TextFieldDefinition($name);
			$matches = array();
			if (preg_match("/^(?P<type>(TINY|MEDIUM|LONG)?TEXT|(VAR)?CHAR)/i", $line, $matches))
			{
				$textField->setIsFixedWidth($matches["type"] == "CHAR");
				if ($matches["type"] == "text")
				{
					$textField->setLength(null);	
				}
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			if (preg_match("/^\((?P<digits>[0-9]+)\)/i", $line, $matches))
			{
				$textField->setLength($matches["digits"]);
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			
			if (preg_match("/^(?P<not>NOT )?NULL/i", $line, $matches))
			{
				$textField->setIsNullable(!isset($matches["not"]));
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			if (preg_match("/^DEFAULT '(?P<value>([^\\]\\'|[^'])*)'/i", $line, $matches)) // Get the default value, while handling escaped quotes inside the value
			{
				$textField->
					setDefault(
						(isset($matches["value"]) ? 
							$matches["value"] 
							: null)
					);
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			if (preg_match("/^COMMENT '(?P<value>([^\\]\\'|[^'])*)'/i", $line, $matches)) // Get the comment value, while handling escaped quotes inside the value
			{
				$textField->
					setComment((isset($matches["value"]) ?
						$matches["value"] 
						: null));
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			return $textField;
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
		public function setLength($newLength = 64)
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
			if ($this->length > 254 && $this->fixedWidth)
			{
				$this->setIsFixedWidth(false);
				throw new Exception("Cannot set a text field to be both fixed ".
					"width and over 254 characters - field is now variable ".
					"width");
			}
			if ($this->length > 32767) // 32767*2=65535 - MySQL max row length
			{
				$this->setLength(null);
				throw new Exception("Cannot set length greater than 32767 on ".
					"a text field - field length is now unlimited");
			}
			
			$this->type = ($this->length === null ? 
				"TEXT"
				: ($this->fixedWidth ? "" : "var")."char(".$this->length.")"
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
			return (
					$value === null && $this->getIsNullable()
				) || 
				(
					is_string($value) && 
					(
						strlen($value) <= $this->getLength() || 
						$this->getLength() === null
					) && 
					(
						!$this->getIsFixedWidth() || 
						strlen($value) == $this->getLength()
					)
				);
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
