<?php

	require_once(dirname(__FILE__)."/../FieldDefinition.php");

	class DateFieldDefinition extends FieldDefinition
	{
		public function __construct($name = null) {
			parent::__construct($name);
			$this->type = "date";
		}
		
		public static function manufacture($name = null) {
			return new DateFieldDefinition($name);
		}
		
		public function repairValue($value) {
			return ($this->isValidValue($value) ? $value : null);
		}
		
		public function toSql() {
			return "`".DBM::escape($this->getName())."` ".
				$this->getType()." ".
				($this->getIsNullable() ? "" : "NOT ")."NULL ".
				($this->getDefault() !== null ? 
					"DEFAULT \"".DBM::escape($this->getDefault())."\" " 
					: "").
				"COMMENT '".DBM::escape($this->comment)."'";
		}
		
		public function exampleValue() {
			return "2000-12-31 23:59:59";
		}
		
		public function matchesSqlType($fieldDetails) {
			return preg_match("/COMMENT '([^\\]\\'|[^'])*~~".__CLASS__."~~([^\\]\\'|[^'])*'/", $fieldDetails) ||
				preg_match("/^date( |,)/i", $fieldDetails);
		}
		
		public function fromSQLCreateFieldLine($name, $line) {
			$field = new DateFieldDefinition($name);
			
			$matches = array();
			if (preg_match("/^date/i", $line, $matches))
			{
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
		
		public function isValidValue($value) {
			return preg_match("/^(([0-9]{4,4}|[0-9]{2,2})[^0-9][0-9]{1,2}[^0-9][0-9]{1,2} [0-9]{1,2}[^0-9][0-9]{1,2}[^0-9][0-9]{1,2})|([0-9]{14,14}|[0-9]{12,12})|([0-9]{8,8}|[0-9]{6,6})|(NOW\(\)|CURRENT_DATE)$/i", $value);
		}


		public function setDefault($value)
		{
			if (!$this->isValidValue($value))
			{
				throw new Exception("Invalid value given as default for ".__CLASS__." object");
			}
			$this->default = $value;
		}
	}
?>
