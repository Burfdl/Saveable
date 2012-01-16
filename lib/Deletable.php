<?php
	require_once("Saveable.php");

	abstract class Deletable extends Saveable
	{
		/**
		 * Deletes the object from the database.
		 * Note this does not destroy the object in memory.
		 */
		public function delete($useCache = false)
		{
			//DBM::delete($this);
			
			// First things first, does the object have any housekeeping to do
			// before we start looking at the data?
			if (method_exists($this, "beforeDelete"))
			{
				$this->beforeDelete();
			}

			// Get an escaped version of the full table name ready for SQL
			$tablename = DBM::escape(
				DBM::$tablePrefix.$this->getTableName()
			);

			// Check the key(s) are set and build SQL strings.
			$keys = $this->getUniqueKeyField();
			$whereStrings = array();
			if (is_array($keys))
			{	// Multiple keys to check
				
				// Do we have at least one key to restrict the delete operation 
				// with?
				if (count($keys) === 0) 
				{
					
					// Complain!
					throw new Exception("No keys specified to ".
						"restrict delete operation. Stopping to avoid wiping ".
						"out the entire '".$tablename."' table");
					
				}
				// Build SQL strings with each field
				foreach ($keys as $field)
				{
					
					// Make sure a key hasn't gone missing, so we don't wipe 
					// out extra rows by accident.
					if (!isset($this->$field))
					{
						throw new Exception("Key '".$field."' missing ".
							"when attempting to delete '".get_class($this).
							"' object; Stopping to avoid wiping out multiple ".
							"rows");
					}
					$whereStrings[] = "`".$field."`=\"".
						$this->escape($this->$field)."\"";
					
				}
				
			}	// multiple keys to check
			else
			{	// single key to check
				
				// Make sure we have a sensible key
				if (
					strlen($keys) == 0 || 
					!isset($this->$keys) || 
					strlen($this->$keys) == 0
				)
				{
					
					// Complain!
					throw new Exception("No key-value specified to ".
						"restrict delete operation. Stopping to avoid wiping ".
						"out the entire '".$tablename."' table");
					
				}
				$whereStrings[] = "`".$keys."`=\"".$this->$keys."\"";
				
			}	// single key to check

			// Construct SQL
			$sql = "DELETE FROM ".$tablename." WHERE ".
				implode(" AND ", $whereStrings);
			
			// Execute query!
			DBM::query($sql);

			// Alert the object to perform any cleanup actions
			if (method_exists($this, "afterDelete")) {
				$this->afterDelete();
			}
		}
	}
?>
