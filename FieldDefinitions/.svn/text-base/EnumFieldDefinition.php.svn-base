<?php

	require_once(dirname(__FILE__)."/../FieldDefinition.php");

	// TODO: @DG Character Set? See MySQL docs at http://tinyurl.com/gwqzl

	class EnumFieldDefinition extends FieldDefinition
	{
		private $exampleValue = null;
		private $allowedValues = array();
		
		public function __construct($name = null)
		{
			parent::__construct($name);
			$this->updateType();
		}
		
		public function addValue($value)
		{
			if (!in_array($value, $this->allowedValues))
			{
				$this->allowedValues[] = $value;
			}
			$this->updateType();
		}
		
		public function getValues()
		{
			return $this->allowedValues;
		}
		
		/**
		 * Manufactures and returns a new EnumFieldDefinition for method 
		 * chaining
		 *
		 * @param string $name 
		 * @return EnumFieldDefinition
		 */
		public static function manufacture($name = null)
		{
			return new EnumFieldDefinition($name);
		}
		
		public function matchesSqlType($fieldDetails)
		{
			return preg_match("/COMMENT '([^\\]\\'|[^'])~~".__CLASS__."~~([^\\]\\'|[^'])'/i", $fieldDetails) ||
				preg_match("/^enum\(['\"].*['\"](,['\"].*['\"])*\)/i", $fieldDetails);
		}
		
		protected function fromSQLCreateFieldLine($name, $line)
		{
			$enumField = new EnumFieldDefinition($name);
			
			$matches = array();
			if (preg_match("/^enum\(/i", $line, $matches))
			{
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			while (preg_match("/^'(?P<value>([^\\\\]\\\\'|[^'])*)'(,|\))/i", $line, $matches))
			{
				$enumField->addValue($matches["value"]);
				$line = trim(substr($line, strlen($matches[0])));
			}			
			
			if (preg_match("/^(?P<not>NOT )?NULL/i", $line, $matches))
			{
				$enumField->setIsNullable(!isset($matches["not"]));
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			if (preg_match("/^DEFAULT '(?P<value>([^\\]\\'|[^'])*)'/i", $line, $matches)) // Get the default value, while handling escaped quotes inside the value
			{
				$enumField->
					setDefault(
						(isset($matches["value"]) ? 
							$matches["value"] 
							: null)
					);
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			if (preg_match("/^COMMENT '(?P<value>([^\\]\\'|[^'])*)'/i", $line, $matches)) // Get the comment value, while handling escaped quotes inside the value
			{
				$enumField->
					setComment((isset($matches["value"]) ?
						$matches["value"] 
						: null));
				$line = trim(substr($line, strlen($matches[0])));
			}/**/
			
			return $enumField;
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
		 * Updates the type variable when something is changed
		 */
		private function updateType()
		{	
			$this->type = count($this->allowedValues) ? "enum('".implode("','", $this->allowedValues)."')" : "enum()";
		}
		
		public function isValidValue($value)
		{
			return in_array($value, $this->allowedValues) || ($value === null && $this->nullable);
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
			return $this->allowedValues[Math.rand(0, count($this->allowedValues)-1)];
		}
	} // class TextFieldDefinition
	
?>
