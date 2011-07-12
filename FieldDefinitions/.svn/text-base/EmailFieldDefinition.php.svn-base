<?php
	require_once("TextFieldDefinition.php");

	class EmailFieldDefinition extends TextFieldDefinition
	{
		protected $allowLocalAddresses = false;
		
		public static function manufacture($name = null) {
			return new EmailFieldDefinition($name);
		}
		
		/**
		 * Sets whether local email addresses are allowed ('user' is 
		 * equivalent to 'user@localhost')
		 *
		 * @param boolean $allow
		 * @return EmailFieldDefinition
		 */
		public function setAllowsLocalAddresses($allow = false)
		{
			if ($allow === null)
			{
				$this->allowLocalAddresses = null;
			}
			else
			{
				$this->allowLocalAddresses = ($allow == true);
			}
			
			return $this;
		}
		
		/**
		 * Returns true if local addresses are allowed ('user' is equivalent 
		 * to 'user@localhost')
		 *
		 * @return boolean
		 */
		public function getAllowsLocalAddresses()
		{
			return $this->allowLocalAddresses;
		}
		
		/**
		 * Returns true if the given value is compatible with the Email field
		 *
		 * @param mixed $value 
		 * @return boolean
		 */
		public function isValidValue($value)
		{
			return (
					(
						filter_var($value, FILTER_VALIDATE_EMAIL)
					) &&
					(
						strpos($value, "@") > 0 || 
						$this->allowLocalAddresses
					)
				) ||
				(
					$this->getIsNullable() && 
					$value === null
				);
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
			if (!is_string($value) && 
				is_object($value) && 
				method_exists($value, "__toString"))
			{
				$value = $value->__toString(); // Then do so
			}
			
			// Still not a string?
			if (!is_string($value))
			{
				return null; // Give up
			}
			
			// Sanitize
			$cleanValue = filter_var($value, FILTER_SANITIZE_EMAIL);
			
			// Make sure sanitised value is valid
			return ($this->isValidValue($cleanValue) ? $cleanValue : null);
		}
	}
?>
