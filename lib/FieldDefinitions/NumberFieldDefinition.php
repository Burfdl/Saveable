<?php

	require_once(dirname(__FILE__)."/../FieldDefinition.php");

	class NumberFieldDefinition extends FieldDefinition
	{
		protected $isInteger = true;
		protected $isSigned = true;
		protected $isZerofill = false;
		protected $isAutoIncrement = false;
		
		protected $length = 10;
		protected $decimalPlaces = 0;
		
		private $exampleValue = null;
		
		/**
		 * Manufactures a new NumberFieldDefinition for method chaining
		 *
		 * @return NumberFieldDefinition 
		 */
		public static function manufacture($name = null)
		{
			return new NumberFieldDefinition($name);
		}
		
		/**
		 *
		 * @return NumberFieldDefinition 
		 */
		public function __construct($name)
		{
			parent::__construct();
			$this->setName($name);
			$this->setDecimals();
		}
		
		/**
		 * Determines if the number will be automatically incremented on each
		 * tuple insert.
		 * Only positive integers may be automatically incremented.
		 *
		 * @param boolean $increment
		 * @return NumberFieldDefinition 
		 */
		public function setIsAutoIncrement($increment = false)
		{
			$this->isAutoIncrement = ($increment == true);
			
			$problems = array();
			
			if (
				$this->isAutoIncrement && 
				(
					$this->getIsInteger() !== true
				)
			)
			{
				$this->setDecimals(null);
				$this->setIsInteger(true);
				$this->updateType();
				$problems[] = "Auto Increment number fields must be integers ".
					"only - field is now an integer";
			}
			
			$this->updateType();
			
			if (!empty($problems))
			{
				throw new Exception("Problem(s) setting ".__FUNCTION__." on ".
					"class '".get_class($this)."' : ".json_encode($problems));
			}
			
			return $this;
		}
		
		public function matchesSqlType($fieldDetails)
		{
			return strpos($fieldDetails, "~~".__CLASS__."~~") !== false || 
				preg_match("/^(((TINY|SMALL|MEDIUM|BIG)?INT)(EGER)?)|REAL|DOUBLE|FLOAT|DECIMAL|NUMERIC/i", $fieldDetails);
		}
		
		protected function fromSQLCreateFieldLine($name, $line)
		{
			$number = new NumberFieldDefinition($name);
			$matches = array();
			if (preg_match("/^REAL|DOUBLE|FLOAT|DECIMAL|NUMERIC/i", $line, $matches))
			{
				$line = trim(substr($line, strlen($matches[0])));
				$number->setIsInteger(false);
			}
			else if (preg_match("/^(TINY|SMALL|MEDIUM|BIG)?(INT)(EGER)?/i", $line, $matches))
			{
				$line = trim(substr($line, strlen($matches[0])));
				$number->setIsInteger(true);
			}
			else
			{
				throw new Exception("Unrecognised numeric type in SQL: '".$line."'");
			}
			
			if (preg_match("/^\((?P<digits>[0-9]+)(,(?P<decimalPlaces>[0-9]+))?\)/i", $line, $matches))
			{
				$number->
					setLength($matches["digits"])->
					setDecimals((
						isset($matches["decimalPlaces"]) ? 
							$matches["decimalPlaces"] 
							: null
						)
					);
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			if (preg_match("/^UNSIGNED/i", $line, $matches))
			{
				$number->setIsSigned(false);
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			if (preg_match("/^ZEROFILL/i", $line, $matches))
			{
				$number->setIsZerofill(true);
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			if (preg_match("/^(?P<not>NOT )?NULL/i", $line, $matches))
			{
				$number->setIsNullable(!isset($matches["not"]));
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			if (preg_match("/^DEFAULT '(?P<value>([^\\]\\'|[^'])*)'/i", $line, $matches)) // Get the default value, while handling escaped quotes inside the value
			{
				$number->
					setDefault(
						(isset($matches["value"]) ? 
							$matches["value"] 
							: null)
					);
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			if (preg_match("/^AUTO_INCREMENT/i", $line, $matches))
			{
				$number->setIsAutoIncrement(true);
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			if (preg_match("/^COMMENT '(?P<value>([^\\]\\'|[^'])*)'/i", $line, $matches)) // Get the comment value, while handling escaped quotes inside the value
			{
				$number->
					setComment(
						(
							isset($matches["value"]) ?
								$matches["value"] 
								: null
						)
					);/**/
				$line = trim(substr($line, strlen($matches[0])));
			}
			
			
			return $number;/**/
		}
		
		/**
		 * Returns true if this field is auto incremented on inserts
		 *
		 * @return boolean 
		 */
		public function getIsAutoIncrement()
		{
			return $this->isAutoIncrement;
		}
		
		/**
		 * Sets if the number decimal or not
		 *
		 * @param boolean $integer
		 * @return NumberFieldDefinition 
		 */
		public function setIsInteger($integer = true)
		{
			$this->isInteger = ($integer == true);
			$problems = array();
			if ($this->isInteger)
			{
				if ($this->decimalPlaces !== null)
				{
					$this->setDecimals(null);
					$problems[] = "Integers must not have any decimal places; ".
						"decimal places reset to null";
				}
			}
			else
			{
				if ($this->isAutoIncrement)
				{
					$this->setIsAutoIncrement(false);
					$problems[] = "Auto increment fields must be integers; ".
						"field is no longer auto incrementing";
				}
			}
			$this->updateType();
			if (!empty($problems))
			{
				throw new Exception("Problem(s) while trying to set isInteger ".
					"on class '".get_class($this)."' : ".
					json_encode($problems));
			}
			return $this;
		}
		
		public function getIsInteger()
		{
			return $this->isInteger;
		}
		
		/**
		 * Sets whether or not a number is padded with zeroes to meet the
		 * length (digits) defined by the field
		 *
		 * @param boolean $zerofill
		 * @return NumberFieldDefinition 
		 */
		public function setIsZerofill($zerofill = false)
		{
			$this->isZerofill = ($zerofill == true);
			$this->updateType();
			return $this;
		}
		
		/**
		 * Returns true if the number is padded to the length with zeroes
		 *
		 * @return boolean
		 */
		public function getIsZerofill()
		{
			return $this->isZerofill;
		}
		
		private function updateType()
		{
			$this->type = (!$this->isInteger ? 
				($this->decimalPlaces == null ? 
					"float" 
					: "decimal") 
				: $this->getIntType()).
				($this->length != null ? 
					"(".$this->length.
						(
							$this->decimalPlaces ? 
								",".$this->decimalPlaces.")" 
								: ")"
						)
					: "").($this->isSigned ? "" : " unsigned");
		}
		
		private function getIntType()
		{
			$lengths = array(
				array("maxwidth" => 2, "label" => "tinyint"),
				array("maxwidth" => 4, "label" => "smallint"),
				array("maxwidth" => 6, "label" => "mediumint"),
				array("maxwidth" => 9, "label" => "int"),
				array("maxwidth" => 18, "label" => "bigint")
			);
			foreach ($lengths as $length)
			{
				if ($length["maxwidth"] > $this->length)
				{
					return $length["label"];
				}
			}
			return "bigint";
		}
		
		public function getLength()
		{
			return $this->length;
		}
		
		/**
		 * Sets the expected max length (digits) of the number
		 *
		 * @param int $newLength
		 * @return NumberFieldDefinition 
		 */
		public function setLength($newLength = 10)
		{
			if (is_string($newLength) && is_numeric($newLength))
			{
				$newLength = (int)$newLength;
			}
			if (is_int($newLength) || $newLength === null)
			{
				$this->length = $newLength;
			}
			else
			{
				throw new Exception("Number field lengths must be integers, ".
					"or null for unlimited; given '".$newLength."'");
			}
			
			$this->updateType();
			
			return $this;
		}
		
		/**
		 * Sets whether or not the number is signed (can be negative)
		 *
		 * @param boolean $newSign
		 * @return NumberFieldDefinition 
		 */
		public function setIsSigned($newSign = true)
		{
			$this->isSigned = ($newSign == true);
			$this->updateType();
			return $this;
		}
		
		/**
		 * Returns true if the number may be negative
		 *
		 * @return boolean
		 */
		public function getIsSigned()
		{
			return $this->isSigned;
		}
		
		/**
		 * Returns the number of decimal places allowed/expected
		 *
		 * @return null|int
		 */
		public function getDecimals()
		{
			return $this->decimalPlaces;
		}
		
		/**
		 * Sets the number of decimal places expected for float/decimal numbers
		 *
		 * @param int $decimals
		 * @return NumberFieldDefinition 
		 */
		public function setDecimals($decimals = null)
		{
			if ($decimals === null)
			{
				$this->decimalPlaces = null;
			}
			else if (is_int($decimals+0) && $decimals < $this->length)
			{
				$this->decimalPlaces = $decimals;
			}
			else
			{
				throw new Exception("Decimals for Number fields must be ".
					"integers, and smaller than the total length; '".
					$decimals."' given.");
			}
			$this->updateType();
			return $this;
		}
		
		/**
		 * Sets the default value of the field
		 *
		 * @param int|float $default integer or float
		 * @return NumberFieldDefinition 
		 */
		public function setDefault($default = null)
		{
			if ($default === null)
			{
				$this->default = null;
			}
			else if (
				(
					is_int($default) || 
					is_float ($default) || 
					is_numeric($default)
				) && 
				strlen($default) <= $this->length && 
				($default >= 0 || $this->isSigned)
			)
			{
				$this->default = $default;
			}
			else
			{
				$this->setDefault(null);
				throw new Exception("'".$default."' is not numeric for a ".
					"number field's default - default is now null");
			}
			$this->updateType();
			if (is_float($default) && $this->isInteger)
			{
				$this->setIsInteger(false);
				throw new Exception("Default value '".$default."' is not an ".
					"integer - number field is now ".
					($this->decimalPlaces > 0 ? "decimal" : "float"));
			}
			return $this;
		}
		
		/**
		 * Returns true if the given value is valid.
		 *
		 * @param mixed $value 
		 * @return boolean
		 */
		public function isValidValue($value)
		{
			return ($this->nullable && $value === null) || is_numeric($value) &&
				strlen(str_replace(".", "", $value)) <= $this->getLength() &&
				($value >= 0 || !$this->getIsSigned()) && 
				(
					strpos($value, ".") === false ? 
						true 
						: !$this->getIsInteger() &&
							(
								$this->getDecimals() === null ||
								$this->getDecimals() === 0 ||
								(strlen($value)-strpos($value, ".")-1) <= $this->getDecimals()
							)
				);
		}
		
		/**
		 * Attempts to repair a value. Returns null if not reperable.
		 *
		 * @param mixed $value
		 * @return int|float|null
		 */
		public function repairValue($value)
		{
			return ($this->isValidValue($value) ? $value : null);
		}
		
		public function toSql() 
		{
			if (strlen($this->getName()) === 0)
			{
				throw new Exception("No field name defined for '".
					get_class($this)."' field");
			}
			// `name` INT(10) NOT NULL DEFAULT "" AUTO_INCREMENT COMMENT
			return "`".$this->getName()."` ".
				$this->getType()." ".
				($this->getIsNullable() ? "" : "NOT ")."NULL ".
				($this->getDefault() !== null ? 
					"DEFAULT \"".DBM::escape($this->getDefault())."\" " 
					: "").
				($this->getIsAutoIncrement() ? 
					"AUTO_INCREMENT " 
					: "").
				"COMMENT '".DBM::escape($this->getComment())."'";
		}
		
		public function exampleValue()
		{
			if ($this->exampleValue == null)
			{
				$this->exampleValue = ($this->getIsSigned() ? "-" : "");
				for ($i = 0; $i < $this->getLength(); $i++)
				{
					$this->exampleValue .= ($i === $this->getDecimals() ? 
						"." 
						: "").(($i+1)%10);
				}
			}
			return $this->exampleValue;
		}
	}
	
?>
