<?php
    require_once("Deletable.php");

    class DatabaseException extends Exception {}
	class TableNotFoundException extends DatabaseException {}

	/**
	 * DBM is a class to simplify interacting with a database, and provide an 
	 * optional cache to protect the database server from unnecessary queries.
	 * 
	 * Note that many functions have "useCache" parameters. By default these
	 * parameters, if omitted, will be set to false. Using the cache is highly
	 * recommended; if all queries go through DBM those that would change the 
	 * results of older cached queries will invalidate them automatically.
	 * 
	 * The aim is to 
	 * 
	 * @author David Godfrey <davidmatthewgodfrey@gmail.com>
	 */
    class DBM
    {
		/* @var self::$connection PDO */
		private static $connection = false;

        private static $tableList = array();
		private static $tableDescriptions = array();

        private static $queryCache = array();
		private static $invalidatedQueryCache = array();
		private static $queryCacheResults = array();
		private static $queryCacheResultsSize = array();
		private static $queryCacheTimes = array();
		private static $queryCacheHits = array();
		private static $queryCacheMemoryRatioLimit = 0.5;
		private static $queryCacheMemoryPurges = array();
		
		public  static $tablePrefix = "";

		public  static $numQueries = 0;
		private static $totalQueryTime = 0;
		private static $lastQueryTime = 0;
		
		private static $cacheActive = true;
		
		/**
		 * Sets whether the instance cache is enabled.
		 *
		 * @param boolean $isEnabled 
		 */
		public static function setCacheIsEnabled($isEnabled = true)
		{
			
			// Cast to boolean
			self::$cacheActive = ($isEnabled == true);
			
			if (!$isEnabled)
			{
				// If disabled, we can clear some memory by resetting the cache
				self::resetCache();
			}
			
		}	// ::setCacheIsEnabled
		
		/**
		 * Returns true if the instance cache is enabled.
		 *
		 * @return boolean 
		 */
		public static function isCacheEnabled()
		{
			return self::$cacheActive;
		}	// ::isCacheEnabled
		
		/**
		 * Sets the connection to be used for queries.
		 * 
		 * Currently only supports MySQLi connections.
		 *
		 * @param mysqli $connection to use for queries
		 */
		public static function setConnection(PDO $connection)
		{
			$old = self::$connection;
			self::$connection = $connection;
			return $old;
		}
		
		/**
		 * Resets the DBM's cache, excluding total query time, last query time 
		 * and number of queries.
		 */
		public static function resetCache() 
		{
			self::$queryCache = array();
			self::$invalidatedQueryCache = array();
			self::$queryCacheResults = array();
			self::$queryCacheTimes = array();
			self::$queryCacheHits = array();
			self::$tableList = array();
			self::$tableDescriptions = array();
			self::$numQueries = 0;
		}

        /**
         * Saves object details to database
         *
         * @param Saveable $object
		 * @deprecated use $saveable->save()
         */
        /*public static function save(Saveable $object)
        {
			// Before we begin, do they have any housekeeping to do?
            if (method_exists($object, "beforeSave"))
            {
                $object->beforeSave();
            }
			
			// Does the table we're saving to even exist?
			if (!self::tableExists($object->getTableName(), true))
			{
				throw new Exception("Table '".$object->getTableName()."' not ".
					"found when attempting to save '".get_class($object).
					"' object");
			}

			// Get, and escape, all keys and values ready for converting to SQL
            $data = array();
			foreach ($object->getData() as $key => $val) {
				$data[self::escape($key)] = self::escape($val);
			}
			
			// Get all the ID fields 
            $idFields = $object->getUniqueKeyField();
			if (!is_array($idFields))
			{
				$idFields = array($idFields);
			}
			// And escape them too, for safety
			foreach ($idFields as $index => $field)
			{
				$idFields[$index] = self::escape($field);
			}
			
			// Get all the ID values
			$ids = $object->getUniqueKey();
			if (!is_array($ids))
			{
				$ids = array($ids);
			}
			// And escape them too, for safety
			foreach ($ids as $index => $key) {
				$ids[$index] = self::escape($key);
			}
			
			// Generate the 'select' keys to find if the object already exists
			$selectKeys = array();
			$idMissing = false;
			foreach (array_values($ids) as $index => $id) {
				$idMissing = $idMissing || !isset($data[$idFields[$index]]);
				$selectKeys[] = "`".$idFields[$index]."`=\"".$id."\"";
			}
			
			// If compound keys are used, inserts cannot guess.
			// Complain if there are missing keys for compound key objects
			if ($idMissing && count($idFields)>1)
			{
				throw new Exception("Missing ID fields in class '".
					get_class($object)."'");
			}

			// Prepare the SQL
			$table = self::escape(self::$tablePrefix.$object->getTableName());
            $sql = "";
            $wasInsert = false;
			// Is this an update or an insert?
            if (!$idMissing &&
				count(DBM::query("SELECT * FROM `".$table."` WHERE ".
					implode(" AND ", $selectKeys))) > 0) // Using a select because you might want to insert with keys already set
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

				// Construct the query with values and keys given
                $sql = "INSERT INTO `".$table."` (`".
					implode("`, `", array_keys($data))."`) VALUES (\"".
                    implode("\", \"", array_values($data))."\")";

				// We have to do extra work for inserts later
                $wasInsert = true;

            }

			// Execute query!
			$querySucceeded = self::query($sql);
            
			// Was there a problem?
			// self::query would throw an exception if there was an error or 
			// warning. Theres not much we can do to recover from this.
            if ($querySucceeded === false)
            {
				
				// Complain!
                throw new DatabaseException("Problem saving ".
					get_class($object)." in class DBM:  - ".$sql);
				
            }

			// If it was an insert and we only have one key to worry about, 
			// get the new ID and add it to the object.
            if ($wasInsert && count($idFields) == 1)
            {
                $result = self::query("SELECT LAST_INSERT_ID()");
				$data[$idFields[0]] = array_shift($result[0]);
                $object->setData($data);
            }

			// Last things last, let the object know it can scatter data back 
			// out again and react to the new values.
            if (method_exists($object, "afterSave"))
            {
                $object->afterSave();
            }
			
        }	// ::save /**/

		/**
		 * Attempts to delete the object.
		 * Throws exceptions if the object is too vague (keys are missing) or
		 * there is an error while attempting the delete query.
		 *
		 * @param Deletable $object 
		 * @deprecated use $deletable->Delete()
		 */
		/*public static function delete(Deletable $object) 
		{
			
			// First things first, does the object have any housekeeping to do
			// before we start looking at the data?
			if (method_exists($object, "beforeDelete"))
			{
				$object->beforeDelete();
			}

			// Get an escaped version of the full table name ready for SQL
			$tablename = self::escape(
				self::$tablePrefix.$object->getTableName()
			);

			// Check the key(s) are set and build SQL strings.
			$keys = $object->getUniqueKeyField();
			$whereStrings = array();
			if (is_array($keys))
			{	// Multiple keys to check
				
				// Do we have at least one key to restrict the delete operation 
				// with?
				if (count($keys) === 0) 
				{
					
					// Complain!
					throw new DatabaseException("No keys specified to ".
						"restrict delete operation. Stopping to avoid wiping ".
						"out the entire '".$tablename."' table");
					
				}
				// Build SQL strings with each field
				foreach ($keys as $field)
				{
					
					// Make sure a key hasn't gone missing, so we don't wipe 
					// out extra rows by accident.
					if (!isset($object->$field))
					{
						throw new DatabaseException("Key '".$field."' missing ".
							"when attempting to delete '".get_class($object).
							"' object; Stopping to avoid wiping out multiple ".
							"rows");
					}
					$whereStrings[] = "`".$field."`=\"".
						$this->escape($object->$field)."\"";
					
				}
				
			}	// multiple keys to check
			else
			{	// single key to check
				
				// Make sure we have a sensible key
				if (
					strlen($keys) == 0 || 
					!isset($object->$keys) || 
					strlen($object->$keys) == 0
				)
				{
					
					// Complain!
					throw new DatabaseException("No key/val specified to ".
						"restrict delete operation. Stopping to avoid wiping ".
						"out the entire '".$tablename."' table");
					
				}
				$whereStrings[] = "`".$keys."`=\"".$object->$keys."\"";
				
			}	// single key to check

			// Construct SQL
			$sql = "DELETE FROM ".$tablename." WHERE ".
				implode(" AND ", $whereStrings);
			
			// Execute query!
			self::query($sql);

			// Alert the object to perform any cleanup actions
			if (method_exists($object, "afterDelete")) {
				$object->afterDelete();
			}
		}/**/

		/**
		 * Returns true if a table exists in the list of known tables.
		 * Beware of SQL running elsewhere; the table may have been dropped 
		 * outside of DBM.
		 *
		 * @param string $tableName
		 * @param boolean $useCache
		 * @return boolean
		 */
        public static function tableExists($tableName, $useCache = false)
        {
            return in_array(self::$tablePrefix.$tableName, self::getTableList($useCache));
        }

		/**
		 * Returns a description of the field in MySQL format
		 *
		 * @param Saveable $class
		 * @param boolean $useCache
		 * @return array
		 */
		public static function describe(Saveable $class, $useCache = false)
		{
			
			// Make sure the table exists, for a start
			$tablename = self::escape($class->getTableName());
			if (!self::tableExists($tablename, $useCache))
			{
				
				// Complain!
				throw new Exception("Table '".$tablename."' not found for ".
					"class '".get_class($class)."'");
				
			}
			
			// Is this table description already cached?
			$tablename = self::escape(self::$tablePrefix).$tablename;
			if (isset(self::$tableDescriptions[$tablename]))
			{	// description cached
				
				// Good! Just return then.
				return self::$tableDescriptions[$tablename];
				
			}	// description cached
			else
			{	// description missing
				
				// Okay, get the description and cache the result too.
				return self::$tableDescriptions[$tablename] =
					self::query("DESCRIBE ".$tablename);
				
			}	// description missing
			
		}	// ::describe

		/**
		 * Returns a list of table names in the current database
		 *
		 * @return array of table names
		 */
        public static function getTableList()
        {
			
			// Is the table list not yet cached?
            if (
				!is_array(self::$tableList) || 
				count(self::$tableList) < 1 || 
				self::$cacheActive == false
			)
            {	// Not cached
				
				// Okay, use show tables and copy the table names across
                self::$tableList = array();
				$result = self::query("SHOW TABLES"); 
                foreach ($result as $row)
                {
                    foreach ($row as $val)
                    {
                        self::$tableList[] = $val;
                    }
                }
				
            }	// not cached
			
			// Return the (cached?) result
            return self::$tableList;
			
        }	// ::getTableList
		
		/**
		 * Updates query time and query time totals with the given values.
		 * If parameter $microtimeStop is missing, assumes the query is 
		 * finishing now.
		 *
		 * @param float $microtimeStart
		 * @param float $microtimeStop 
		 */
		private static function updateQueryTimes($microtimeStart, 
			$microtimeStop = null)
		{
			if ($microtimeStop == null)
			{
				$microtimeStop = microtime(true);
			}
			self::$lastQueryTime = $microtimeStop-$microtimeStart;
			self::$totalQueryTime += self::$lastQueryTime;
		}
		
		private static function updateCache($sql, $result, $size)
		{
			// Cache the SQL
			self::$queryCache[] = $sql;
			// Set time-per-cache
			self::$queryCacheTimes[] = self::$lastQueryTime;
			// Note that it hasn't been hit yet
			self::$queryCacheHits[] = 0;
			// Cache the result
			self::$queryCacheResults[] = $result;
			// Cache size of result
			self::$queryCacheResultsSize[] = $size;
			// Note that it hasn't fought for memory yet
			self::$queryCacheMemoryPurges[] = 0;
			
			self::checkCacheMemoryUsage();
		}
		
		private static function checkCacheMemoryUsage()
		{	
			if (self::getMemoryUsageRatio() > self::$queryCacheMemoryRatioLimit)
			{
				// How much memory do I have to free up?
				$memoryTarget = self::getMemoryLimit() * self::$queryCacheMemoryRatioLimit * 0.75;
				
				// 'Weigh' each entry, factoring in how long its been in memory, 
				// how much memory it takes up and how many times the result 
				// has been used/hit and how long the data took to load
				$cacheEntryWeights = array();
				foreach (self::$queryCache as $index => $sql)
				{
					$sql = $sql;
					$index = $index;
					$cacheEntryWeights[$index] = self::$queryCacheResultsSize[$index] / (self::$queryCacheTimes[$index] * (self::$queryCacheHits[$index] / (self::$queryCacheMemoryPurges[$index]+1))+1);
				}
				
				// Now generate candidates for invalidation
				$candidates = array_keys($cacheEntryWeights);
				array_multisort($candidates, $candidates);
				
				// While we're still using too much memory, invalidate entries
				$index = 0;
				while (memory_get_usage() > $memoryTarget)
				{
					self::invalidateCachedQuery($candidates[$index++], "Memory ratio limit reached");
				}
				
				// Record that each still-valid entry survived another purge
				foreach (array_keys(self::$queryCache) as $index)
				{
					self::$queryCacheMemoryPurges[$index]++;
				}
				
			}
			
			// Still no good? Okay, get rid of the invalidated queries too.
			if (self::getMemoryUsageRatio() > self::$queryCacheMemoryRatioLimit)
			{
				self::$invalidatedQueryCache = array();
			}
		}
		
		/**
		 * Invalidates a cached query, moving it to the invalidatedQueryCache 
		 * array.
		 * The query invalidation comment includes the cause given, if one was 
		 * supplied.
		 * Once invalidated, a query's results are forgotten. If an identical 
		 * query is received later, it will be run against the datastore again.
		 *
		 * @param int $index
		 * @param string $cause 
		 */
		private static function invalidateCachedQuery($index, $cause = "")
		{
			
			// Only bother if this query is currently valid
			if (isset(self::$queryCache[$index]))
			{
				
				self::$invalidatedQueryCache[$index] = "/* CACHED RESULT ".
					"INVALIDATED".(strlen($cause) ? " BY: ".$cause : "")." */".
					self::$queryCache[$index];
				unset(self::$queryCache[$index]);
				unset(self::$queryCacheResults[$index]);
				
			}
			
		}	// ::invalidateCachedQuery
		
		/**
		 * Extracts table names mentioned in the given SQL string.
		 * Currenly done naively as a simple text search.
		 *
		 * @param string $sql
		 * @return string[]
		 */
		private static function getTablesFromSql($sql)
		{
			
			$tablesFound = array();
			foreach (self::getTableList() as $table)
			{
				if (preg_match("/(^|[\W])".$table."[\W]/i", $sql))
				{
					$tablesFound[] = $table;
				}
			}
			return $tablesFound;
			
		}	// ::getTablesFromSql
		
		private static function getMemoryLimit()
		{
			$memlimit = strtoupper(ini_get("memory_limit"));
			if (strpos($memlimit, "G"))
			{
				$memlimit = substr($memlimit, 0, strlen($memlimit)-1);
				$memlimit = $memlimit * pow(1024, 3);
			}
			else if (strpos($memlimit, "M"))
			{
				$memlimit = substr($memlimit, 0, strlen($memlimit)-1);
				$memlimit = $memlimit * pow(1024, 2);
			}
			else if (strpos($memlimit, "K"))
			{
				$memlimit = substr($memlimit, 0, strlen($memlimit)-1);
				$memlimit = $memlimit * 1024;
			}
			return $memlimit;
		}
		
		private static function getMemoryUsageRatio()
		{
			return memory_get_usage() / self::getMemoryLimit();
		}
		
		/**
		 * Inspects SQL for actions that break cached results.
		 *
		 * @param string $sql 
		 */
		private static function inspectSql($sql)
		{
			
			// Only spend time checking if the cache is currently active
			if (self::$cacheActive)
			{	// cache active
				
				$matchToRemove = array();
				if (preg_match("/(^|[\W])(CREATE|DROP|RENAME|ALTER)(( )+TEMPORARY)?( )+(TABLE|DATABASE)[\W]/i", $sql) > 0)
				{	// Affects table definitions (everything could be invalid)
					
					// Invalidate explicit checks of table definitions
					$matchToRemove[] = "SHOW TABLES";
					$matchToRemove[] = "DESCRIBE";
					
					// Which tables might have changed?
					$tables = self::getTablesFromSql($sql);
					foreach ($tables as $table)
					{
						$matchToRemove[] = $table;
					}
					
					self::$tableList = false;
					
					// Delete any internal definitions, if we've inspected the
					// table already
					foreach (array_keys(self::$tableDescriptions) as $table)
					{
						if (in_array($table, $tables))
						{
							unset(self::$tableDescriptions[$table]);
						}
					}
					
				}	// affects table definitions
				else if (preg_match("/(^|[\W])(INSERT|UPDATE|DELETE|CALL|TRUNCATE)[\W]/i", $sql) > 0)
				{	// Affects table rows (specific tables could be invalid)
					
					// Which tables might have changed?
					foreach (self::getTablesFromSql($sql) as $table)
					{
						$matchToRemove[] = $table;
					}
					
				}	// affects caching

				// Does anything need invalidating?
				if (count($matchToRemove))
				{	// have things to remove
					$regex = "/".implode("|", $matchToRemove)."/i";
					foreach (self::$queryCache as $index => $cachedSql)
					{
						$matches = array();
						if (preg_match($regex, $cachedSql, $matches))
						{
							self::invalidateCachedQuery($index, json_encode(array("sql" => $sql, "matchToRemove" => $matchToRemove, "matches" => $matches)));
						}
					}
				}	// have things to remove
				
			}	// cache active
			
		} // ::inspectSql()
		
		public static function isConnected()
		{
			return self::$connection instanceof PDO;
		}
		
		/**
		 * Make sure there is a connection to pass queries through.
		 * Throws exception if no details set.
		 */
		private static function ensureConnection()
		{
			if (!(self::$connection instanceof PDO))
			{
				throw new DatabaseException("No connection details given to ".
					"DBM. Call DBM::setConnection() to set up DBM before ".
					"running any queries");
			}
		}
		
		/**
		 * Determines if the result is cacheable.
		 * Currently this means if the SQL is suspected of changing state
		 * on the server side.
		 *
		 * @param string $sql 
		 * @return boolean
		 */
		private static function isCacheable($sql)
		{
			return (0 == preg_match("/(^|[\W])((INSERT|UPDATE|DELETE|CALL|TRUNCATE)|((CREATE|DROP|RENAME|ALTER)(( )+TEMPORARY)?( )+(TABLE|DATABASE)))/i", $sql));
		}

		/**
		 * Attempts to query the database with the given SQL.
		 * Returns an array of associative arrays when results are generated.
		 * Throws database warnings/errors as exceptions
		 *
		 * @param string $sql
		 * @param boolean $useCache
		 * @return array[]|boolean 
		 */
        public static function query($sql, $useCache = false)
        {
			// Firstly, make sure we're connected
			self::ensureConnection();
			/* @var $connection PDO */
			$connection = self::$connection;
			
			// Start timing the query
			$queryStartTime = microtime(true);
			// Record the extra query
			self::$numQueries += 1;
			
			// Check to see if we can use the cache
			$queryCacheIndex = false;
			if (
				$useCache && 
				self::$cacheActive &&
				self::isCacheable($sql) &&
				(
					$queryCacheIndex = array_search(
						$sql,
						self::$queryCache
					)
				) !== false
			)
			{ // If using cache and found..
				
				// Log that the query finished very quickly..
				self::updateQueryTimes($queryStartTime);
				// Note that the cache was hit..
				++self::$queryCacheHits[$queryCacheIndex];
				// Update the time-per-cache
				self::$queryCacheTimes[$queryCacheIndex] += self::$lastQueryTime;
				// and return the cached result.
				return self::$queryCacheResults[$queryCacheIndex];
				
			}	// using cache and cached result found
			else
			{ // Not using cache..
				
				// Returnable result
				$result = array();
				
				// Run the query!
				/* @var $query PDOStatement */
				$query = $connection->query($sql);
				
				// Update the cache (if active) in case it is used in future
				self::inspectSql($sql);
				
				// Check for warnings
				if 
				(
					( // Get warnings
						(
							$warningQuery = $connection->
								query("SHOW WARNINGS")
						) && 
						(
							$result = $warningQuery->
								fetchAll(PDO::FETCH_NUM)
						)
					) &&
					count($result) > 0 && // Warning found?
					$result[0][0] != "Note" // Warning, not note?
				)
				{	// Warnings found!
										
					// Well, there was a problem, but we /have/ finished with 
					// the query.
					self::updateQueryTimes($queryStartTime);
					
					// Complain, giving details in the message
					throw new DatabaseException(
						"Warning while executing query '".$result[0][0]." (".
						$result[0][1]."): ".$result[0][2]."'\nFor SQL: '".$sql.
						"'"
					);
					
				}
				if ($query instanceof PDOStatement)
				{	// Did the query complete successfully?
					if ($query->columnCount() > 0)
					{	// Do we have rows of results to return?
						
						// Record how much memory is in use first
						$beforeResult = memory_get_usage();
						
						// Fetch them in
						$result = $query->fetchAll(PDO::FETCH_ASSOC);
						
						// Calculate how much more memory is now in use
						$resultSize = memory_get_usage() - $beforeResult;
						
						// Log that the query finished
						self::updateQueryTimes($queryStartTime);
						
						// Check if we should update the cache
						if (self::$cacheActive)
						{	// Update cache
							self::updateCache($sql, $result, $resultSize);
						}
						
						// Finally, return the result.
						return $result;
					}
					
					// If successful, but no results, still returns an empty 
					// array.
					self::updateQueryTimes($queryStartTime);
					return $result;
				}
				else if ($query === false)
				{	// Did the query fail?
				
					// Get the error message
					$error = array_pop($connection->errorInfo());
					
					// Can we recover from a missing table?
					if (preg_match("/^Table '[\w]+\.[\w]+' doesn't exist/", $error))
					{	// Theres a missing table
						
						// Extract the table name
						$tableName = substr($error, strpos($error, ".")+1);
						$tableName = substr($tableName, 0, strpos($tableName, "'"));
						
						// Is there a class that uses this table?
						$className = false;
						if (($className = self::getClassNameFromTableName($tableName)) !== null)
						{	// A Saveable class uses this table
							
							// Get an instance of the class
							/* @var $object Saveable */
							$object = new $className();
							// Try and rebuild the table from the class
							$object->checkClassTable($object);
							
							// Has the table been rebuilt?
							if (self::tableExists($tableName))
							{	// Table rebuilt
								
								// Try the query again
								return self::query($sql, $useCache);
								
								// We don't reset the query time here as the 
								// table-rebuilding queries will have accounted 
								// for the intermediary time.
								
							}	// table exists
						}	// saveable class uses this table
					}	// missing table
					
					// We can't deal with this error, give up..
					self::updateQueryTimes($queryStartTime);
					// .. and complain about it.
					throw new DatabaseException("Error while executing query:".
						"\n".$error."\nFor SQL:\n".$sql);
					
				}	// query failed
				
				// PDO->query should only return PDOStatement or FALSE; this 
				// /should/ never execute. Complaining here just in case.
				throw new Exception("Unexpected return type from PDO::query : ".$query);
				
			}	// not using cache
			
        }	// ::query
		
		/**
		 * Inflates missing values from a Saveable object, if a unique matching 
		 * row can be found from the given values.
		 * Does not require keys, any (accidentally?) unique field will do.
		 * Returns true if the inflation succeeded.
		 *
		 * @param Saveable $saveable
		 * @param boolean $useCache
		 * @return boolean
		 */
		/*public static function inflateIfExists(Saveable $saveable, $useCache = false)
		{
			
			// Make sure the table at least exists
			if (!self::tableExists($saveable->getTableName(), $useCache))
			{	// Table missing
				
				// Give up.
				return false;
				
				// Even if we rebuild the table here it would still be 
				// empty, and therefore no matches would be found. 
				// Better to avoid unintended side-effects.
				
			}	// Table missing
			
			// Warn the saveable object to consolidate all values to ->getData 
			// so we can get at them
			$saveable->beforeSave();
			
			// Create an array of all the `x`="a" strings
			$sqlBits = array();
			foreach ($saveable->getData() as $key => $val)
			{
				if ($val !== null)
				{
					$sqlBits[] = "`".self::escape($key)."`=\"".self::escape($val).
						"\"";
				}
			}
			
			// Okay, the Saveable can scatter data back out to implementation 
			// specific places again now.
			$saveable->afterSave();
			
			// Query to find matching row(s)
			$sql = "SELECT * FROM `".self::escape(DBM::$tablePrefix.$saveable->getTableName()).
				"` WHERE ".implode(" AND ", $sqlBits);
			$found = DBM::query($sql);
			
			// Must be one result, otherwise which values do we use?
			$matchFound = (count($found) == 1);
			
			if ($matchFound)
			{
				
				// Give the data we found to the Saveable object
				if (method_exists($saveable, "beforeLoad"))
				{
					$saveable->beforeLoad();
				}
				$saveable->setData($found[0]);
				$saveable->afterLoad();
				
			}	// match found
			
			return $matchFound;
			
		}	// ::inflateIfExists /**/

		/**
		 * Escapes a value to be safely used in a query.
		 * If given an object, will attempt to use a __toString 
		 * method.
		 * Throws exception on unrecognised types.
		 *
		 * @param string $string
		 * @return string
		 */
        public static function escape($string)
        {
			
			// Firstly make sure we're connected at all
			self::ensureConnection();
			/* @var $connection PDO */
			$connection = self::$connection;
			
			// Is this a string at all?
			if (!is_string($string) && !is_numeric($string) && $string !== null)
			{	// Not string, but also not null
				
				// Can we convert this back to a string?
				if (is_object($string) && method_exists($string, "__toString"))
				{	// Has a toString method
					
					// Handle exceptions just in case..
					try
					{
						
						// Okay, lets do it..
						$string = $string->__toString();
						// Did it work?
						if (!is_string($string))
						{	// If still not a string
							
							// Complain!
							throw new Exception("DBM::escape expects a ".
								"string; object had a toString method, but ".
								"failed to return a string; returned '".
								$string."'");
							
						}	// if not string
						
					}	// try
					catch (Exception $e)
					{	// oops, couldn't use toString
						
						// Complain!
						throw new Exception("DBM::escape expects a string; ".
							"received '".$string."'");
						
					}	// catch exception
				}	// has a toString method
				else
				{	// no toString method
					
					// Complain!
					throw new Exception("DBM::escape expects a string; ".
						"received '".$string."'");
					
				}	// no toString method
				
			}	// not string, but not null
			
			// Okay, we've made it through the exceptions, now actually quote 
			// the thing and return it.
			// Trimming quotes as DBO wraps values in quotes unnecessarily.
            return trim($connection->quote($string), "'");
			
        }	// ::escape

		/**
		 * Returns an array of the given classes, one for each row returned
		 * by the given SQL.
		 * Throws exception if attempting to load an object that does not 
		 * exist, or does not implement Saveable.
		 *
		 * @param string $classname
		 * @param string $sql
		 * @return Saveable[] 
		 */
		/*public static function loadObjectsByQuery($classname, $sql)
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
			foreach (self::query($sql) as $row)
			{	// for each row..
				
				// Make a new object
				/* @var $object Saveable * /
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
			
		}	// ::loadObjectsByQuery /**/

        /**
         * Attempts to load missing information from the database
         *
         * @param Saveable $object to load
		 * @deprecated use $saveable->load()
         */
        /*public static function load(Saveable $object, $useCache = false)
        {
			
			// Before we do anything, get the Saveable object to consolidate 
			// their data where we can see it.
            if (method_exists($object, "beforeLoad"))
            {
                $object->beforeLoad();
            }

			// Collect information about the object
            $class = get_class($object);
			$tableName = $object->getTableName();
            $idField = $object->getUniqueKeyField();
            $data = $object->getData();
			
			// Check which ID(s) are found/missing
			$idFields = array();
			$allIdsFound = true;
			if (is_array($idField))
			{	// Multiple IDs required
				
				// Are there any missing?
				if (
					count(array_intersect($idField, array_keys($data))) < 
					count($idField)
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
					$data[$idField] === null ||  // unset
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
					"ID Field(s) not set for object '".get_class($object).
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
				$whereStrings[] = "`".self::escape($key)."`=\"".self::escape($val)."\"";
			}

			// Run query to load the data
            $sql = "SELECT * FROM `".self::escape(self::$tablePrefix.
				$tableName)."` WHERE ".implode(" AND ", $whereStrings);
            $result = self::query($sql, $useCache);

			// Did the query return a single result?
            if (count($result) > 1)
            {	// More than one result found
				
				// Complain!
                throw new DatabaseException("ID(s) '".
					implode("', '", array_keys($idFields))."' not unique for ".
                    $class." in table ".self::$tablePrefix.$tableName.
					" with SQL: ".$sql.".");
				
            }	// more than one result found
			else if (count($result) == 0)
			{	// No results found
				
				// Complain!
				throw new DatabaseException("ID(s) '".
					implode("', '", array_keys($idFields))."' not found for ".
                    $class." in table ".self::$tablePrefix.$tableName.
					" with SQL: ".$sql.".");
				
			}	// no results found
            else
            {	// one result found
				
				// Give it to the Saveable object
                $object->setData($result[0]);
				
            }	// one result found

			// If the object handles their data differently, let them know
			// we're done with them and they can scatter the data out again.
            if (method_exists($object, "afterLoad"))
            {
                $object->afterLoad();
            }
			
        } // ::load /**/
		
		/**
		 * Returns an array detailing how often a query was cached and how much
		 * total time was spent on the query.
		 * Useful for highlighting repetitive queries which could benefit from
		 * caching.
		 *
		 * @return array
		 */
		public static function queryCacheDebug()
		{
			
			$returnable = array();
			
			// Get all valid cached queries..
			foreach (self::$queryCache as $index => $sql)
			{
				$returnable[$index] = array(
					"query" => $sql,
					"time" => self::$queryCacheTimes[$index],
					"hits" => self::$queryCacheHits[$index]
				);
			}
			
			// ..And also fold in the invalidated queries
			foreach (self::$invalidatedQueryCache as $index => $sql)
			{
				$returnable[$index] = array(
					"query" => $sql, 
					"time" => self::$queryCacheTimes[$index],
					"hits" => self::$queryCacheHits[$index]
				);
			}
			
			// Done!
			return $returnable;
			
		}	// ::queryCacheDebug
		
		/**
		 * Converts the results of a query to CSV format.
		 * If a string is passed in the result parameter, attempts to run
		 * it as a query and work on the results given back.
		 *
		 * @deprecated - to be abstracted away
		 * @param array $result
		 * @param boolean $useCache
		 * @return string[] 
		 */
		public static function queryToCSV($result, $useCache = false)
		{
			
			// Have we been handed a query string in stead of a result array?
			if (!is_array($result))
			{
				// Pass it through ::query and use the result
				$result = DBM::query($result, $useCache);	
			}
			
			$returnable = array();
			
			// Use the array keys to make the legend/header of the CSV file
			array_unshift($result, array_keys($result[0]));
			
			// Escape each value as necessary and add to returnable
			foreach ($result as $row)
			{
				foreach ($row as $index => $value) 
				{
					$row[$index] = addslashes($value);
					if (
						$row[$index] != $value || 
						strpos($row[$index], ",") !== false
					)
					{
						$row[$index] = "\"".$row[$index]."\"";
					}
				}
				$returnable[] = implode(",", $row);
			}
			
			// Return array of strings
			return $returnable;
			
		}	// ::queryToCSV
		
		/**
		 * Returns the total amount of time spent responding to queries.
		 * Contains time for both cached and database results.
		 *
		 * @return float
		 */
		public static function totalQueryTime()
		{
			return self::$totalQueryTime;
		}
		
		/**
		 * Returns the amount of time spent responding to the last query.
		 *
		 * @return float
		 */
		public static function lastQueryTime()
		{
			return self::$lastQueryTime;
		}

    }
?>
