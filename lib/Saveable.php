<?php
	require_once("DBM.php");
	require_once("TableDefinition.php");

    //class TableNotFoundException extends DatabaseException {}
    class RelationNotFoundException extends Exception {}

    /**
     * Gives useful save/load functions by inheritance and forces relevant
     * methods to be declared in children of the object.
     * 
     * See PHPDoc comments for required an optional functions. Throws
	 * descriptive exceptions if anything required is missing, or doesn't match
	 * the database.
     */
    abstract class Saveable
    {
        // Data is used to store the object's data by default.
        protected $data = array();
        
        // ====================================================================
        //                               You must implement the functions below
        // ====================================================================
        
        // ====================================================================
        //                     You may override the functions below if you want
        // ====================================================================
		
		/**
         * Determines if the entirety of data currently stored in the object is
         * valid.
         *
         * Please make sure you include a comment for each field that is
         * validated explaining what it will accept, preferrably with an
         * example.
		 * 
         * You may also wish to throw exceptions to show exactly which field is
         * invalid.
		 * 
         * Note that a failure when validating does not guarantee bad data will
         * not be inserted into the database. Saveable cannot protect against
		 * direct SQL inserts.
         *
         * To aid validation, you may wish to use the PHP function filter_var()
         *
         * @return boolean
         */
		public function validates()
		{
			return (method_exists($this, "getTableDefinition") && 
				$this->getTableDefinition() instanceof TableDefinition ?
					$this->getTableDefinition()->validate($this->getData())
					: true);
		}
		
		/**
		 * Returns true if this class is a child of the given object class
		 *
		 * @param Saveable $otherClass
		 * @return type 
		 */
		public function isChildRelationOf(Saveable $otherClass)
		{
			self::refreshAllSaveableRelations();
			return in_array(get_class($otherClass), self::$allRelations[get_class($this)]["parents"]);
		}
		
		/**
		 * Returns true if this class is a parent of the given object class
		 *
		 * @param Saveable $otherClass
		 * @return type 
		 */
		public function isParentRelationOf(Saveable $otherClass)
		{
			self::refreshAllSaveableRelations();
			return in_array(get_class($otherClass), self::$allRelations[get_class($this)]["children"]);
		}
		
		/**
		 * Returns a path from $a to $b for the purposes of relation queries.
		 * In progress.
		 *
		 * @param Saveable $a
		 * @param Saveable $b 
		 * @return array
		 */
		public static function getRelationPath(Saveable $a, Saveable $b)
		{
			// TODO: @DG Implement getRelationPath
			
		}
		
		/**
		 * Attempts to inflate the class, assuming the currently set fields
		 * will be unique enough to return only one result from the table.
		 *
		 * @param boolean $useCache
		 * @return Saveable
		 */
		public function inflate($useCache = false)
		{
			if (!$this->exists(false))
			{
				$given = array();
				foreach ($this->data as $key => $val)
				{
					if (strlen($val) > 0)
					{
						$given[] = $key;
					}
				}
				throw new Exception("Could not find one and only one ".
						get_class($this)." matching the given values (".preg_replace("/, ([a-z0-9_-]+)$/i", " and $1", implode(", ", $given)).")");
			}
			
			return $this;
		}
		
		/**
		 * Determines if this object already exists in the database by matching
		 * against the values that have been set on the object
		 *
		 * @return boolean
		 */
		public function exists($useCache = false)
		{
			//return DBM::inflateIfExists($this);
			
			// Make sure the table at least exists
			if (!DBM::tableExists($this->getTableName(), $useCache))
			{	// Table missing
				
				// Give up.
				return false;
				
				// Even if we rebuild the table here it would still be 
				// empty, and therefore no matches would be found. 
				// Better to avoid unintended side-effects.
				
			}	// Table missing
			
			// Warn the saveable object to consolidate all values to ->getData 
			// so we can get at them
			$this->beforeSave();
			
			// Create an array of all the `x`="a" strings
			$sqlBits = array();
			foreach ($this->getData() as $key => $val)
			{
				if ($val !== null)
				{
					$sqlBits[] = "`".DBM::escape($key)."`=\"".DBM::escape($val).
						"\"";
				}
			}
			
			// Query to find matching row(s)
			$sql = "SELECT * FROM `".DBM::escape(
					DBM::$tablePrefix.$this->getTableName()
				)."` WHERE ".implode(" AND ", $sqlBits);
			$found = DBM::query($sql);
			
			// Must be one result, otherwise which values do we use?
			$matchFound = (count($found) == 1);
			
			if ($matchFound)
			{
				
				// Give the data we found to the Saveable object
				if (method_exists($this, "beforeLoad"))
				{
					$this->beforeLoad();
				}
				$this->setData($found[0]);
				$this->afterLoad();
				
			}	// match found
			
			return $matchFound;
		}
		
		private static $cachedReadableFieldNames = array();
		
		/**
         * Determines if a given field is readable.
         * EG: "ID" might be hidden to avoid relying on a key which may change.
         *
         * @return boolean
         */
		public function isReadable($fieldName)
		{
			if (!isset(Saveable::$cachedReadableFieldNames[get_class($this)]))
			{
				
				// If we have a tabledefinition inside the object, lets use it
				// in stead of bothering the database
				if (self::getTableDefinition() instanceof TableDefinition)
				{
					Saveable::$cachedReadableFieldNames[get_class($this)] = 
						self::getTableDefinition()->getFieldNames();
				}
				else if (PHP_SAPI != "cli" || DBM::isConnected())
				{
					
					// Make sure the table exists to begin with
					if (!DBM::tableExists($this->getTableName(), true))
					{
						self::checkClassTable($this, true);
					}

					// If we don't have a tabledefinition inside the object, 
					// check the table definition in the database
					Saveable::$cachedReadableFieldNames[get_class($this)] = 
						$this->collect(DBM::describe($this, true), "Field");
					
				}
				else
				{

					// Running from CLI, so no connection to the database.
					return true;

				}
			
			}
			
			//echo get_class($this)."\n";
			//print_r(Saveable::$cachedReadableFieldNames[get_class($this)]);
			
			return in_array(
				$fieldName, 
				Saveable::$cachedReadableFieldNames[get_class($this)]
			);
		}
		
		/**
         * Determines if a given field is writable.
         * EG: "ID" might be write-protected to avoid having two objects with 
		 * the same ID but different values.
		 * By default all fields that are readable are also writable.
         *
         * @return boolean
         */
		public function isWritable($fieldName)
		{
			return self::isReadable($fieldName);
		}
		
		/**
		 * Returns true if a value is autocompletable. 
		 * This also means the value will not be available in the class for 
		 * actions on get/set.
		 *
		 * @param string $fieldName
		 * @return boolean
		 */
		public static function isAutocompletable($fieldName)
		{
			return true;
		}

		/**
		 * Warns the DBM to serialise/unserialise a value before saving/loading.
		 * Only multidimensional arrays are guaranteed to reinflate properly.
		 *
		 * @param $fieldName to report on
		 * @return boolean
		 */
		public function isSerializable($fieldName)
		{
			return false;
		}
        
        /**
         * Returns the unique-key field used to identify the object in a
         * database/set.
         *
         * @return string
         */
        public function getUniqueKeyField()
        {
			// Before we start talking to getTableDefinition, deal with 
			// unfortunate table definitions that ask *us* for the table name
			$backtrace = debug_backtrace(true);
			// Only want to know if we've recursed, so ignore that we're 
			// currently at the top of the call stack
			array_shift($backtrace);
			// Go back through the call stack to make sure we're not in there 
			// twice
			foreach ($backtrace as $trace)
			{
				if (isset($trace["object"]) && $trace["object"] === $this &&
					isset($trace["function"]) && $trace["function"] === __FUNCTION__)
				{
					// Rut-roh.. Better give up and just use the class name or 
					// we could be here all day.
					return get_class($this)."ID";
				}
			}
			
			if (($tabledef = $this->getTableDefinition()) instanceof TableDefinition)
			{
				foreach ($tabledef->getIndexes() as $index)
				{
					if ($index instanceof PrimaryKeyDefinition)
					{
						$fields = $index->getFields();
						if (count($fields) === 1)
						{
							return array_shift($fields);
						}
					}
				}
			}
            return get_class($this)."ID";
        }

		/**
		 * Returns the relation key name for this table, if different from the 
		 * ID key
		 *
		 * @return string
		 */
		public function getRelationKeyField(Saveable $object = null)
		{
			$field = $this->getUniqueKeyField();
			if ($object !== null)
			{
				foreach ($object->getTableDefinition()->getIndexes() as $index)
				{
					/* @var $index IndexDefinition */
					if ($index instanceof ForeignKeyDefinition)
					{
						if (
							$index->getForeignTableName() === 
								$this->getTableName() &&
							count($fields = $index->getFields()) === 1
						)
						{
							return $fields[0];
						}
					}
				}
			}
			return $field;
		}

		/**
		 * Returns the relation key(s) to look for in another table.
		 * Returns single keys as a single value, returns multiple keys in
		 * an array of the form array(keyname=>keyval)
		 *
		 * @return mixed 
		 */
		public function getRelationKey(Saveable $object = null)
		{
			$relationKey = $this->getRelationKeyField($object);
			if (is_string($relationKey))
			{
				if ($object !== null)
				{
					if (!isset($object->$relationKey))
					{
						throw new Exception("Relation key '".$relationKey.
						"' not set for ".get_class($object));
					}
					return $object->data[$relationKey];
				}
				if (!isset($this->$relationKey))
				{
					throw new Exception("Relation key '".$relationKey."' not ".
						"set for ".get_class($this));
				}
				return $this->data[$relationKey];
			}
			else if (is_array($relationKey))
			{
				$returnable = array();
				foreach ($relationKey as $key) {
					$returnable[$key] = $this->$key;
				}
				return $returnable;
			}
			else
			{
				throw new Exception("Unrecognised data type when attempting ".
					"to get RelationKey for ".get_class($this)." object");
			}
		}

		/**
		 * Returns the table name to load/save the object in
		 *
		 * @return string
		 */
		public function getTableName()
		{
			
			// Before we start talking to getTableDefinition, deal with 
			// unfortunate table definitions that ask *us* for the table name
			$backtrace = debug_backtrace(true);
			// Only want to know if we've recursed, so ignore that we're 
			// currently at the top of the call stack
			array_shift($backtrace);
			// Go back through the call stack to make sure we're not in there 
			// twice
			foreach ($backtrace as $trace)
			{
				if (isset($trace["object"]) && $trace["object"] === $this &&
					isset($trace["function"]) && $trace["function"] === __FUNCTION__)
				{
					// Rut-roh.. Better give up and just use the class name or 
					// we could be here all day.
					return get_class($this);
				}
			}
			
			// If they provide a TableDefinition, we might as well use the 
			// table name contained within
			if (($tabledef = $this->getTableDefinition()) instanceof TableDefinition)
			{
				if (strlen($tablename = $tabledef->getName()) > 0)
				{
					return $tablename;
				}
			}
			
			// No other option, use the class name.
			return get_class($this);
			
		} // ->getTableName

        /**
         * Attempts to repair invalid data stored in the object.
		 * Not guaranteed to be able to repair.
         */
        public function repairData()
        {
            /* Optional function
             *
             * This is up to the implementer but it is probably inadvisable to
             * change things too wildly (eg: for month values "November" isn't
             * numeric, but resetting to  "1" is obviously not correct and
             * could confuse the issue)
             *
             * Do not worry if the data cannot be repaired. $this->save() is
             * aware of both validates() and repairData().
             */
        }

        /**
         * Initialises the object based on the ID given.
         * If an ID is given, will attempt to load the rest of the data from
         * the database.
		 * If no ID is given, will create an empty object.
         *
         * @param int $id
         */
        public function __construct($id = false, $useCache = false)
        {
			// TODO: @DG Add call to static function to check if table has changed?
            if ($id !== false) {
				if (is_numeric($id))
				{
					if (is_array($this->getUniqueKeyField()))
					{
						throw new Exception("Expecting array of IDs, received integer");
					}
					$this->data[$this->getUniqueKeyField()] = $id;
					$this->load($useCache);
				}
				else if (is_array($id)) {
					foreach ($this->getUniqueKeyField() as $field) {
						$field = $field;
						$this->data[$field] = $id[$field];
					}
					$this->load($useCache);
				}
				else
				{
					throw new Exception("Attempting to use unrecognised type ".
						"to initialise '".get_class($this)."' class. Given: '".
						$id."'");
				}
			}
			$this->checkClassTable($this, true);
        }

        /**
         * Attempts to get the object's version to facilitate versioned
         * databases.
         * Versioning is opt-in; by default this function returns null.
		 * 
		 * The version number will be compared as integers, then strings 
		 * if no inequalities are found as integers. 
		 * 
		 * A version number numerically/logically 'bigger' than another will 
		 * be considered 'newer' and override any database definitions in the 
		 * older version. Avoid using codenames as version numbers.
         *
         * @return null|string in version format (eg: 10.6.3)
         */
        public function getObjectVersion()
        {
           return null;
        }

		/**
		 * Modifies data after loading, but before working with the data.
		 * 
		 * If you intend to overload this function, it is recommended you
		 * call `parent::afterLoad();` at the start, to ensure features like 
		 * $this->isSerializable() work as expected.
		 */
		public function afterLoad()
		{
			foreach ($this->data as $key => $val) {
				if ($this->isSerializable($key)) {
					$this->data[$key] = json_decode($val, true);
				}
			}
		}
		
		public static function loadByQuery($sql, $classname = "Saveable")
		{
			
			// Make sure the given class actually exists
			if (! class_exists($classname))
			{	// not a known object
				
				// Complain!
				throw new Exception("The class '".$classname."' doesn't ".
					"exist!");
				
			}	// not a known object
			
			// Is the object a Saveable object?
			$reflection = new ReflectionClass($classname);
            if (!$reflection->isSubclassOf("Saveable"))
            {	// not saveable
				
				// Complain!
                throw new Exception("I don't know how to load '".$classname.
                    "' objects; they don't implement Saveable.");
				
            }	// not saveable
			
			// Attempt to load the results
			$returnable = array();
			foreach (DBM::query($sql) as $row)
			{	// for each row..
				
				// Make a new object
				/* @var $object Saveable */
				$object = new $classname();
				
				// Give it the data
				if (method_exists($object, "beforeLoad"))
				{
					$object->beforeLoad();
				}
                $object->setData($row);
				if (method_exists($object, "afterLoad"))
				{
					$object->afterLoad();
				}
				$returnable[] = $object;
			}

			// Return the result(s)
			return $returnable;
		}

		/**
		 * Modifies data just before saveing
		 * 
		 * If you intend to overload this function, it is recommended you
		 * call `parent::beforeSave();` at the start, to ensure features like 
		 * $this->isSerializable() work as expected.
		 */
		public function beforeSave()
		{
			foreach ($this->data as $key => $val) {
				if ($this->isSerializable($key)) {
					$this->data[$key] = json_encode($val);
				}
			}
		}

		/**
		 * Modifies data after saveing, but before working with the data
		 * 
		 * If you intend to overload this function, it is recommended you
		 * call `parent::afterSave();` at the start, to ensure features like 
		 * $this->isSerializable() work as expected.
		 */
		public function afterSave()
		{
			foreach ($this->data as $key => $val) {
				if ($this->isSerializable($key)) {
					$this->data[$key] = json_decode($val, true);
				}
			}
		}
        
        // ====================================================================
        //       You shouldn't override the functions below without good reason
        // ====================================================================

		private static $tableDescriptions = array();
		
		/**
		 * Collects a sub-array key from a multidimensional array in the format
		 * array(array(key => val1), array(key => val2)) and returns as an 
		 * array in the format array(val1, val2).
		 * Throws exceptions on errors.
		 *
		 * @param array $array
		 * @param string $key
		 * @return array 
		 */
		private function collect($array, $key)
		{
			if ($array instanceof ArrayObject)
			{
				$array = $array->getArrayCopy();
			}
			if (!is_array($array))
			{
				throw new Exception("Cannot collect from non-array object!");
			}
			$returnable = array();
			foreach ($array as $index => $row)
			{
				if ($row instanceof ArrayObject)
				{
					$row = $row->getArrayCopy();
				}
				if (!is_array($row))
				{
					throw new Exception("Attempting to collect from malformed ".
						"array");
				}
				if (!isset($row[$key]))
				{
					throw new Exception("Key '".$key."' not found for element ".
						"'".$index."' when attempting to collect");
				}
				$returnable[] = $row[$key];
			}
			return $returnable;
		}
		

		/**
		 * Returns the data store's table definition for this object if
		 * available.
		 * Returns field name / type / length / nullable tuples if available.
		 *
		 * @return array
		 */
		public function getTableDescription()
		{
			return (!isset(self::$tableDescriptions[get_class($this)]) ? 
				self::$tableDescriptions[get_class($this)] = 
					DBM::describe($this)
				: self::$tableDescriptions[get_class($this)]);
		}
		
		/**
		 * If a tabledefinition is available, returns it.
		 * Otherwise returns null.
		 *
		 * @return TableDefinition|null
		 */
		public static function getTableDefinition()
		{
			return null;
		}

        /**
         * Returns all known data associated with the object.
		 * 
		 * You may wish to call $this->beforeSave() before and 
		 * $this->afterSave() afterwards in order to ensure all data is 
		 * saved centrally for access here.
		 * 
		 * Beware of side-effects of calling 'beforeSave'.
         *
         * @return array
         */
        public function getData()
        {
            return $this->data;
        }

        /**
         * Replaces all data stored in the object with new values specified
         * in an associative array.
		 * 
		 * You may wish to call $this->afterSave() afterwards to ensure any 
		 * new data is pushed out to class variables.
         *
         * @param array $newData to replace with
         */
        protected function setData($newData)
        {
            if (!is_array($newData))
            {
                throw new Exception("Non-array new data given to class ".
                    get_class($this).".");
            }
            $oldData = $this->data;
            $this->data = $newData;
            if (!$this->validates())
            {
                $this->data = $oldData;
                throw new Exception("Invalid data given to (and ignored by) ".
                    "class ".get_class($this).": ".json_encode($newData));
            }
        }

        /**
         * Magic function to allow access to object's data from outside the
         * object.
         * Makes use of isReadable to hide fields from the outside world.
         * Accessed in the form: $anObject->fieldName
         *
         * @param string $fieldName to request
         * @return mixed
         */
        public function  __get($fieldName) {
            if (!$this->isReadable($fieldName)) {
				throw new Exception("Variable '".$fieldName."' not readable");
			}
	        else if (!isset($this->data[$fieldName]))
            {
                return null;
            }
            return $this->data[$fieldName];
        }

		/**
		 * Magic function to allow access to object's data from outside the
		 * object.
		 * Makes use of isReadable to hide fields from the outside world.
		 * Accessed in the form: isset($anObject->name)
		 *
		 * @param string $name of variable
		 * @return boolean
		 */
		public function  __isset($name) {
			return $this->isReadable($name) && isset($this->data[$name]);
		}

		/**
		 * Magic function to allow access to object's data from outside the
		 * object.
		 * Makes use of isWritable to hide fields from the outside world.
		 * Accessed in the form: unset($anObject->name)
		 *
		 * @param string $name
		 */
		public function  __unset($name) {
			if ($this->isWritable($name))
			{
				$this->beforeSave();
				unset($this->data[$name]);
				$this->afterSave();
			}
			else
			{
				throw new Exception("'".$name."' is not writable, cannot ".
					"unset");
			}
		}

        /**
         * Magic function to allow writing to object's data from outside the 
         * object.
         * Makes use of isWritable to hide fields from the outside world.
         * Accessed in the form: $anObject->fieldName = "value"
         *
         * @param string $fieldName to set
         * @param mixed $value to set field to
         */
        public function  __set($fieldName, $value) {
            if (!$this->isWritable($fieldName))
            {
                throw new Exception("Cannot set read-only variable '".
                    $fieldName."'");
            }
			if ($this->isSerializable($fieldName) && !is_array($value))
			{
				$value = array($value);
			}
			if (is_array($value))
			{
				$value = new ArrayObject($value);
			}
            $this->data[$fieldName] = $value;
        }

        /**
         * Gets all children who have foreign-keys pointing to this object's
         * ID. This function relies on $this->isRelated()
         *
         * @param string $className
		 * @param boolean $useCache
         */
        public function getChildren($className, $useCache = false)
        {
			
            /* @var $object Saveable */
            $object = false;
			if (! class_exists($className))
			{
				throw new Exception("Class '".$className."' not loaded, I ".
					"don't know where to find them. Please require_once() the ".
					"relevant file.");
			}
            if (! ($object = new $className) instanceof Saveable)
            {
                throw new Exception("I don't know how to load '".$className.
                    "' objects; they don't implement Saveable.");
            }

			// Determine if we can find this in the DB at all
            $dbTable = $object->getTableName();
            $this->checkClassTable($object, $useCache);
			$this->checkClassTable($this, $useCache);
            
            // Are they actually related?			
            if (!$this->isParentRelationOf($object))
            {
                throw new RelationNotFoundException("Cannot find child '".
                    $className."' objects for '".get_class($this)."' objects- ".
                    "I don't know if they're related! Add it to ".
                    get_class($this)."->relations[\"children\"] if you're ".
                    "sure this is right");
            }

            $esc = array(
                "FTable" => DBM::$tablePrefix.$dbTable,
                "LTable" => DBM::$tablePrefix.$this->getTableName(),
				"LKey" => $this->getUniqueKey(),
                "LKeyField" => $this->getRelationKeyField($object)
            );
			foreach ($esc as $key => $val)
            {
				if (is_array($val)) {
					foreach ($val as $k => $v) {
						$val[DBM::escape($k)] = DBM::escape($v);
					}
					$esc[$key] = $val;
				}
				else if (is_string($val) || is_int($val)) {
					$esc[$key] = DBM::escape($val);
				}
				else if (is_object($val))
				{
					if (method_exists($val, "__toString"))
					{
						$esc[$key] = DBM::escape("".$val."");
					}
					else
					{
						throw new Exception("Not sure how to convert '".
							get_class($val)."' to string. You may wish to ".
							"implement __toString for ".get_class($val));
					}
				}
				else
				{
					throw new Exception("Unrecognised value type when ".
						"attempting to convert to key '".$key."' to find ".
						"children '".$className."' objects for '".
						get_class($this)."' - Value was: '".$val."'");
				}
            }

            $sql = "SELECT * FROM `".$esc["FTable"]."` WHERE `".
                $esc["LKeyField"]."`=\"".$esc["LKey"]."\"";

			$results = DBM::query($sql, $useCache);

			if (count($results)  == 0 ) {
				return array();
			}

            $returnables = array();
            foreach ($results as $newObj)
            {
                $obj = new $className();
				if (method_exists($obj, "beforeLoad")) {
					$obj->beforeLoad();
				}
                $obj->setData($newObj);
				if (method_exists($obj, "afterLoad")) {
					$obj->afterLoad();
				}
                $returnables[] = $obj;
            }

            return $returnables;
        }
		
		/**
		 * Compiles a table definition to SQL. 
		 * Intended for internal usage; likely to change.
		 *
		 * @param Saveable $object
		 * @return string 
		 */
		private function compileTableDefinitionToSQL(Saveable $object)
		{
			$definition = $object->getTableDefinition();
			
			if ($definition instanceof TableDefinition)
			{
				return $definition->toSql();
			}
			
			if ($definition === null)
			{
				throw new Exception("Missing table definition");
			}
			
			$sql = "CREATE TABLE IF NOT EXISTS ".
				DBM::escape(DBM::$tablePrefix.
					$object->getTableName());

			$lines = array();

			foreach ($definition["Columns"] as $columnDefinition)
			{
				$lines[] = "`".$columnDefinition["Name"]."` ".
					$columnDefinition["Definition"];
			}
			if (isset($definition["Indexes"]))
			{
				foreach ($definition["Indexes"] as $indexDefinition)
				{
					$lines[] = $indexDefinition["Type"]."(`".
						implode("`,`", $indexDefinition["Columns"])."`)";
				}
			}
			if (isset($definition["UnformattedLines"]))
			{
				foreach ($definition["UnformattedLines"] as $line)
				{
					$lines[] = $line;
				}
			}

			$sql .= "(".implode(", ", $lines).")";
			
			return $sql;
		}
		
		/**
		 * Checks if a class's table exists, attempting to create the table
		 * if the class provides a getTableDefinition method.
		 *
		 * @param Saveable $object to inspect
		 * @param boolean $useCache 
		 */
		public function checkClassTable(Saveable $object = null, $useCache = false)
		{
			if ($object === null)
			{
				$object = $this;
			}
            // Determine if we can find the table in the DB at all
			$className = get_class($object);
			$tableName = $object->getTableName();
            if (!DBM::tableExists($tableName, $useCache))
            {
				$newClassName = $tableName . "s";
                if (substr($tableName, strlen($tableName) - 1) == "s")
                {
                    $newClassName = substr($tableName, 0, strlen($tableName)-1);
                }
                
                if (DBM::tableExists($newClassName, $useCache))
                {
                    throw new TableNotFoundException("Cannot find the table '".
                        $tableName."' in the database to load parent ".
						$className." for ".get_class($this)." from. I've ".
						"tried switching plurality to '".$newClassName."'".
						" and that table exists. Are you sure '".$tableName.
						"' is right? Override ".$className."->getTableName() ".
						"to change it.");
                }
                else
                {
					if (method_exists($object, "getTableDefinition"))
					{
						try
						{	
							DBM::query(
								$this->compileTableDefinitionToSQL($object));
						}
						catch (Exception $e)
						{
							throw new TableNotFoundException("Cannot find the ".
									"table '".$tableName."' in the database ".
									"to load ".$className." from for ".
									get_class($this).". Tried creating the ".
									"table from column definitions, but ".
									"encountered an exception. Are you sure ".
									"it exists? Override ".$className.
									"->getTableName() to change it. The ".
									"database exception was: ".$e->getMessage(),
								500);
						}
					}
					else
					{
						throw new TableNotFoundException("Cannot find the ".
							"table '".$tableName."' in the database to load ".
							"parent ".$className." for ".get_class($this).
							" from. I've tried switching plurality to '".
							$newClassName."' too to no avail. Are you sure it ".
							"exists? Override ".$className."->getTableName() ".
							"to change it.");
					}
                }
            }	
		}

        /**
         * Gets one parent who's ID matches a foreign-key in this object.
         * Relies on $this->isRelated().
		 * 
		 * Caching is per remote-key. Never caches on unidentifiable objects 
		 * (must have a valid getUniqueKey())
         *
         * @param string $className
		 * @param boolean $useCache
         */
        public function getParent($className, $useCache = false)
        {
			// Is this a class at all?
			if (!class_exists($className))
			{
				throw new Exception("Class '".$className."' not loaded, I ".
					"don't know where to find them. Please require_once() ".
					"the relevant file.");
			}

			// Determine if we already know about this object
            /* @var $object Saveable */
            $object = new $className;

            if (! $object instanceof Saveable)
            {
                throw new Exception("I don't know how to load '".$className.
                    "' objects; they don't implement Saveable.");
            }
            
            // Determine if we can find their table in the DB at all
            $this->checkClassTable($object, $useCache);
			$dbTable = $object->getTableName();
			$this->checkClassTable($this, $useCache);
            
            // Are they actually related?			
            if (!$this->isChildRelationOf($object))
            {
                throw new RelationNotFoundException("Cannot find parent '".
                    $className."' objects for '".get_class($this)."' objects- ".
                    "I don't know if they're related! Add it to ".
                    get_class($this)."->relations[\"parents\"] if you're ".
                    "sure this is right");
            }

            $esc = array(
                "FTable" => DBM::$tablePrefix.$dbTable,
                "LTable" => DBM::$tablePrefix.$this->getTableName(),
                "FKey" => $object->getRelationKeyField($this),
                "FKeyField" => $object->getUniqueKeyField(),
                "LKey" => $this->getUniqueKey(),
                "LKeyField" => $this->getUniqueKeyField()
            );
            foreach ($esc as $key => $val)
            {
				// Have to deal with compound keys too
				if (is_array($val)) {
					foreach ($val as $k => $v) {
						$val[DBM::escape($k)] = DBM::escape($v);
					}
					$esc[$key] = $val;
				}
				else if (is_string($val)) {
					$esc[$key] = DBM::escape($esc[$key]);
				}
            }


            $sql = "SELECT `".$esc["FTable"]."`.* FROM `".
                $esc["LTable"]."` JOIN `".$esc["FTable"].
                "` ON `".$esc["LTable"]."`.`".
                $esc["FKey"]."`=`".$esc["FTable"]."`.`".
                $esc["FKeyField"]."` WHERE ";

			if (is_array($esc["LKeyField"])) {
				$keys = array();
				foreach ($esc["LKeyField"] as $key) {
					$keys[] = "`".$esc["LTable"]."`.`".$key."`=\"".$esc["LKey"][$key]."\"";
				}
				$sql .= implode(" AND ", $keys);
			}
			else if (is_string($esc["LKeyField"])) {
				$sql .= "`".$esc["LTable"]."`.`".$esc["LKeyField"]."`=\"".$esc["LKey"]."\"";
			}

            $ids = DBM::query($sql, $useCache);
            if (count($ids) > 1)
            {
                throw new Exception("Cannot return parent '".$className."' ".
                    "object as too many results returned for ".
                    get_class($this)." ID '".$this->getUniqueKey()."'");
            }
            if (count($ids) < 1)
            {
                throw new Exception("Cannot return parent '".$className."' ".
                    "object as no results returned for ".get_class($this).
                    " ID '".$this->getUniqueKey()."' - ".$sql);
            }

			if (method_exists($object, "beforeLoad"))
			{
				$object->beforeLoad();
			}

            $object->setData($ids[0]);

			if (method_exists($object, "afterLoad"))
			{
				$object->afterLoad();
			}

            return $object;
        }

        /**
         * Returns a string, integer or array uniquely identifying an instance 
         * of a Savable object.
         *
         * @return mixed
         */
        public function getUniqueKey()
        {
			$uniqueKey = $this->getUniqueKeyField();
			if (is_string($uniqueKey)) {
				if (isset($this->data[$uniqueKey]))
				{
					return $this->data[$uniqueKey];
				}
				else
				{
					return null;
				}
			}
			else
			{
				$returnable = array();
				foreach ($uniqueKey as $key) {
					if (isset($this->data[$key]))
					{
						$returnable[$key] = $this->data[$key];
					}
					else
					{
						$returnable[$key] = null;
					}
				}
				return $returnable;
			}
        }
		
		/**
		 * Returns the class name of a saveable object matching the given
		 * table name.
		 * 
		 * Returns null if no matching object is found.
		 * 
		 * @return string|null Saveable class name
		 */
		protected static function getClassNameFromTableName($tableName)
		{	
			if (self::$allRelationsTableNames === null)
			{
				self::refreshAllSaveableRelations();
			}
			
			if (in_array($tableName, self::$allRelationsTableNames))
			{
				return array_search($tableName, self::$allRelationsTableNames);
			}
			
			if (!isset(DBM::$tablePrefix) || 
				!is_string(DBM::$tablePrefix) ||
				strlen(DBM::$tablePrefix) == 0 || 
				strpos($tableName, DBM::$tablePrefix) !== 0)
			{
				$tableName = DBM::$tablePrefix.$tableName;
			}
			
			if (in_array($tableName, self::$allRelationsTableNames))
			{
				return array_search($tableName, self::$allRelationsTableNames);
			}
			
			return null;
		}
		
		// All relations between known classes
		public static $allRelations = null;
		// The classes visible last time self::$allRelations was last updated
		private static $allRelationsClassesVisible = null;
		// The tables associated with classes :- array($class => $table)
		private static $allRelationsTableNames = null;
		
		/**
		 * Updates $allSaveableRelations with all declared saveable classes' 
		 * relations to eachother.
		 */
		public static function refreshAllSaveableRelations()
		{
			$nowDeclared = get_declared_classes();
			
			// Before we start, have the classes even changed? Can we skip this?
			if (is_array(self::$allRelationsClassesVisible) && 
				count($nowDeclared) === 
					count(self::$allRelationsClassesVisible) &&
				count(
					array_diff(
						$nowDeclared, 
						self::$allRelationsClassesVisible
					)
				) > 0)
			{
				return true;
			}
			
			// First off update with the classes visible this time around
			self::$allRelationsClassesVisible = $nowDeclared;
			
			// The tables need updating, so reset them first
			self::$allRelationsTableNames = array();
			
			// Get a list of saveable classes we know about
			$saveables = array();
			foreach (self::$allRelationsClassesVisible as $className)
			{
				$reflection = new ReflectionClass($className);
				if (is_subclass_of($className, "Saveable") && 
					$reflection->isInstantiable())
				{
					$saveables[] = $className;
					$object = new $className;
					self::$allRelationsTableNames[$className] = $object->getTableName();
						//call_user_func(array($className, "getTableName"));
				}
			}
			
			// Get all 'parents' relations of all known saveable classes
			self::$allRelations = array();
			foreach ($saveables as $saveable)
			{
				
				$myRelations = array("parents"=>array(), "children"=>array());
				
				// Using TableDefinition objects?
				$tableDefinition = null;
				if (
					method_exists(
						$saveable, 
						"getTableDefinition"
					) && 
					(
						$tableDefinition = call_user_func(
							array(
								$saveable, 
								"getTableDefinition"
							)
						)
					) instanceof TableDefinition
				)
				{
					/* @var $tableDefinition TableDefinition */
					foreach ($tableDefinition->getIndexes() as $index)
					{
						if ($index instanceof ForeignKeyDefinition)
						{
							$className = self::getClassNameFromTableName(
									$index->getForeignTableName()
								);
							if ($className !== null)
							{
								$myRelations["parents"][] = $className;
							}
								
						}
					}
				}
				
				self::$allRelations[$saveable] = $myRelations;
			}
			
			foreach (self::$allRelations as $myName => $myRelations)
			{
				foreach (self::$allRelations as $theirName => $theirRelations)
				{
					if (in_array($myName, $theirRelations["parents"]) &&
						!in_array($theirName, $myRelations["children"]))
					{
						$myRelations["children"][] = $theirName;
					}
				}
				self::$allRelations[$myName] = $myRelations;
			}
			
			return true;
		}

        /**
         * Attempts to save the object's current state to the database.
         */
        public function save($useCache = false)
        {
            //DBM::save($this);
			
			// Before we begin, do they have any housekeeping to do?
			$this->beforeSave();
			
			// Does the table we're saving to even exist?
			if (!DBM::tableExists($this->getTableName(), true))
			{
				throw new Exception("Table '".$this->getTableName()."' not ".
					"found when attempting to save '".get_class($this).
					"' object");
			}

			// Get, and escape, all keys and values ready for converting to SQL
            $data = array();
			foreach ($this->getData() as $key => $val) {
				if ($val !== null)
				{
					$data[DBM::escape($key)] = DBM::escape($val);
				}
			}
			
			// Get all the ID fields 
            $idFields = $this->getUniqueKeyField();
			if (!is_array($idFields))
			{
				$idFields = array($idFields);
			}
			// And escape them too, for safety
			foreach ($idFields as $index => $field)
			{
				$idFields[$index] = DBM::escape($field);
			}
			
			// Get all the ID values
			$ids = $this->getUniqueKey();
			if (!is_array($ids))
			{
				$ids = array($ids);
			}
			// And escape them too, for safety
			foreach ($ids as $index => $key) {
				$ids[$index] = DBM::escape($key);
			}
			
			// Generate the 'select' keys to find if the object already exists
			$selectKeys = array();
			$idMissing = false;
			foreach (array_values($ids) as $index => $id)
			{
				$idMissing = $idMissing || !isset($data[$idFields[$index]]);
				$selectKeys[] = "`".$idFields[$index]."`=\"".$id."\"";
			}
			
			// If compound keys are used, inserts cannot guess.
			// Complain if there are missing keys for compound key objects
			if ($idMissing && count($idFields)>1)
			{
				throw new Exception("Missing ID fields in class '".
						get_class($this)."'");
			}

			// Prepare the SQL
			$table = DBM::escape(DBM::$tablePrefix.$this->getTableName());
            $sql = "";
            $wasInsert = false;
			// Is this an update or an insert?
            if (
				!$idMissing &&
				count(
					DBM::query("SELECT * FROM `".$table."` WHERE ".
							implode(" AND ", $selectKeys)
					) // Using a select here because you might want to insert with keys already set
				) > 0
			)
            {   // Its an update!

				// Build strings for the update query
                $updateStrings = array();
                foreach ($data as $field => $value)
                {
                    $updateStrings[] = "`".$field."`=\"".$value."\"";
                }
				// Construct the query
                $sql = "UPDATE `".$table."` SET ".implode(", ", $updateStrings).
						" WHERE ".implode(" AND ", $selectKeys);
				
            }
            else
            {   // Its an insert!
				
				/*$unfilteredData = $data;
				$data = array();
				foreach ($unfilteredData as $field => $value)
				{
					if ($value !== null)
					{
						$data[$field] = $value;
					}
				}*/

				// Construct the query with values and keys given
                $sql = "INSERT INTO `".$table."` (`".
						implode("`, `", array_keys($data))."`) VALUES (\"".
						implode("\", \"", array_values($data))."\")";

				// We have to do extra work for inserts later
                $wasInsert = true;

            }

			// Execute query!
			$querySucceeded = DBM::query($sql, $useCache);
            
			// Was there a problem?
			// self::query would throw an exception if there was an error or 
			// warning. Theres not much we can do to recover from this.
            if ($querySucceeded === false)
            {
				
				// Complain!
                throw new Exception("Problem saving ".get_class($this).
						" with sql: ".$sql);
				
            }

			// If it was an insert and we only have one key to worry about, 
			// get the new ID and add it to the object.
            if ($wasInsert && count($idFields) == 1)
            {
                $result = DBM::query("SELECT LAST_INSERT_ID()");
				$data[$idFields[0]] = array_shift($result[0]);
                $this->setData($data);
            }

			// Last things last, let the object know it can scatter data back 
			// out again and react to the new values.
			$this->afterSave();
			
			return $this;
        }

        /**
         * Attempts to load data about the object from the database.
         * Overwrites any changes made since last load.
         * Requires the IDField to be already set.
         */
        public function load($useCache = false)
        {
            //DBM::load($this);
			
			// Before we do anything, consolidate data where we can see it.
            if (method_exists($this, "beforeLoad"))
            {
                $this->beforeLoad();
            }

			// Collect information about the object
            $class = get_class($this);
			$tableName = $this->getTableName();
            $idField = $this->getUniqueKeyField();
            $data = $this->getData();
			
			// Check which ID(s) are found/missing
			$idFields = array();
			$allIdsFound = true;
			if (is_array($idField))
			{	// Multiple IDs required
				
				// Are there any missing?
				if (
					count(
						array_intersect($idField, array_keys($data))
					) < count($idField)
				)
				{
					$allIdsFound = false;
				}
				else
				{	// All IDs found
					
					// Copy to array
					foreach ($idField as $field) {
						$idFields[$field] = $data[$field];
					}
				}
				
			}	// multiple IDs required
			else
            {	// Only one ID required
				
				if (
					!isset($data[$idField]) ||   // missing
					$data[$idField] === null ||  // not set
					strlen($data[$idField]) == 0 // empty
				)
				{
					$allIdsFound = false;
				}
				else
				{
					$idFields[$idField] = $data[$idField];
				}
				
            }	// only one ID required

			// Are any IDs missing?
            if ($allIdsFound == false)
            {	// IDs missing
				
				// Complain!
                throw new DatabaseException(
					"ID Field(s) not set for object '".get_class($this).
					"'. Expecting field(s): '".(
						is_array($idField) ? 
							implode("', '", $idField) 
							: $idField
					)."'."
				);
				
            }	// IDs missing

			// Build comparison strings for query
			$whereStrings = array();
			foreach ($idFields as $key => $val) {
				$whereStrings[] = "`".DBM::escape($key)."`=\"".DBM::escape($val)."\"";
			}

			// Run query to load the data
            $sql = "SELECT * FROM `".DBM::escape(DBM::$tablePrefix.
				$tableName)."` WHERE ".implode(" AND ", $whereStrings);
            $result = DBM::query($sql, $useCache);

			// Did the query return a single result?
            if (count($result) > 1)
            {	// More than one result found
				
				// Complain!
                throw new DatabaseException("ID(s) '".
					implode("', '", array_keys($idFields))."' not unique for ".
                    $class." in table ".DBM::$tablePrefix.$tableName.
					" with SQL: ".$sql.".");
				
            }	// more than one result found
			else if (count($result) == 0)
			{	// No results found
				
				// Complain!
				throw new DatabaseException("ID(s) '".
					implode("', '", array_keys($idFields))."' not found for ".
                    $class." in table ".DBM::$tablePrefix.$tableName.
					" with SQL: ".$sql.".");
				
			}	// no results found
            else
            {	// one result found
				
				// Give it to the Saveable object
                $this->setData($result[0]);
				
            }	// one result found

			// If the object handles their data differently, let them know
			// we're done with them and they can scatter the data out again.
            $this->afterLoad();
        }
    }
?>
