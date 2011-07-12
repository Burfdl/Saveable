<?php
	require_once("NumberFieldDefinition.php");

	class CurrencyFieldDefinition extends NumberFieldDefinition
	{
		
		public static function manufacture($name = null) {
			return new CurrencyFieldDefinition($name);
		}
		
		public function __construct($name) {
			parent::__construct($name);
			$this->
				setIsInteger(false)->
				setDecimals(2);
		}
		
		/**
		 * Returns true if the given value is compatible with the Currency field
		 *
		 * @param mixed $value 
		 * @return boolean
		 */
		public function isValidValue($value)
		{
			return (filter_var($value, FILTER_VALIDATE_FLOAT) !== false && 
					round($value, 2) == $value) || 
				($this->getIsNullable() && $value === null);
		}
		
		/**
		 * Attempts to repair a value to conform with the field definition.
		 * Returns null if a value is irreperable.
		 *
		 * @param string $value
		 * @return string|null
		 */
		public function repairValue($value)
		{
			// Is it already valid? 
			if ($this->isValidValue($value))
			{
				return $value; // Then shortcut.
			}
			
			// Can we convert it to a string?
			if (!is_numeric($value) && 
				is_object($value) && 
				method_exists($value, "__toString"))
			{
				$value = $value->__toString(); // Then do so
			}
			
			// Still not numeric?
			if (!is_numeric($value))
			{
				return null; // Give up
			}
			
			// Sanitize
			$cleanValue = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT);
			
			// Make sure sanitised value is valid
			return ($this->isValidValue($cleanValue) ? $cleanValue : null);
		}
	}
?>
