<?php
class InternalSearch {
	public static function scan_directory($root_dir, array $blacklist = NULL){
		// create an array to hold a list of all the files in this directory and all the files in all of the subdirectories
		$paths_to_files = array(); 
		
		// create an array of all of the files and folders in the current directory
		$cdir = scandir($root_dir);
		
		// for each file/folder, 
		foreach ($cdir as $key => $value) 
		{ 
			// if the file/folder's name is not either '.' or '..', proceed
			if (!in_array($value,array(".",".."))) 
			{ 
				// if the current item is a directory, scan it. Otherwise, add the file to the list of file paths
				if (is_dir($root_dir . DIRECTORY_SEPARATOR . $value)) 
				{
					// old version (didn't work correctly; I kept this for my own use): 
					//$result[$value] = scan_directory($path . DIRECTORY_SEPARATOR . $value);
					$paths_to_files = array_merge($paths_to_files, self::scan_directory($root_dir . DIRECTORY_SEPARATOR . $value, $blacklist));
				} 
				else 
				{ 
					$path = $root_dir . DIRECTORY_SEPARATOR . $value;
					// pits the current path against the blacklist of paths; if a match is found, the path is not added to the output array
					$match_found = false;
					if($blacklist != NULL){
						foreach($blacklist as $blackpath){
							if($blackpath == $path){
								$match_found = true;
							}
						}
						if($match_found == false){
							$paths_to_files[] = $path;
						}
					} else {
						$paths_to_files[] = $path;
					}
				}
			} 
		}
		return $paths_to_files;
	}

	public static function scrape_files($list, array $queries) {
	
		$content_array = array();
		$used_paths_array = array();
		
		$result = array();
		
		foreach ($list as $file) {
			
			$handle = fopen($file, 'r');
			$html = fread($handle, filesize($file));
			
			// we get ready to parse the HTML and disregard any markup syntactical errors
			$dom = new DOMDocument();
			libxml_use_internal_errors(true);
			$dom->loadHTML($html);
			libxml_clear_errors();
			
			$xpath = new DOMXPath($dom);
			
			$final_array = array();
			
			foreach($queries as $query){
				
				$content_array = array();
				$contents = array();
				
				// scrapes the contents of the description area in each file
				$nodes = $xpath->query($query);
				
				// creates an array of the resulting descriptions (there should only be one)
				foreach ($nodes as $i => $node) {
					$contents[] = $node->nodeValue;
				}
				
				if (empty($contents)){
					$contents[] = NULL;
				}
				
				$contents = implode(' | ', $contents);
				
				if(strlen($contents) == 0){
					$contents = NULL;
				}
				
				$final_array[] = $contents;
			}
			
			// adds the file's path to the array of file paths that actually produced content; this is done to
			// prevent any mismatch later between the length of the array containing the paths to the files and
			// the length of the array containing the actual content.
			
			if  (!self::check_if_all_null($final_array)) {
				array_unshift($final_array, $file);
				$result[] = $final_array;
			}
			
		} 
		
		// return array($used_paths_array, $content_array);
		return $result;
	}
	
	public static function check_if_all_null(array $array_of_arrays){
		// return true unless told otherwise
		$result = true;
		
		// foreach element in the array, check if it's an array. If so, call the function recursively. If not, check if its value is null
		foreach($array_of_arrays as $element){
			if($result){
				if(is_array($element)){
					$result = self::check_if_all_null($element);
				}
				else {
					if(!is_null($element)){
						$result = false;
					}
				}
			}
		}
		return $result;
	}
	
	public static function load_content(array $arrays, array $credentials) {
		// sets the default message type to an error, that way we can just add onto this later if needed
		$message = 'Error: ';
		
		// this prepares the SQL statement for the incoming data
		try {
			$final_sql = self::prepare_sql($arrays);
		} catch (Exception $e) {
			return $message . 'SQL Preparation failed. Message: ' . $e->getMessage();
		}
		
		// this prepares the data for injection into the prepared statement
		try {
			$final_data = self::prepare_data($arrays);
		} catch (Exception $e) {
			return $message . 'Data Preparation failed. Message: ' . $e->getMessage();
		}
		
		try {
			// tries to connect to DB using credentials array
			$pdo = new PDO($credentials['type'] . ':host=' . $credentials['host'] . ';dbname=' . $credentials['database'], $credentials['username'], $credentials['password']);
		
		} catch(PDOException $d) {
			return $message . 'Database failed to connect. Message: ' . $d->getMessage();
		} catch(Exception $e) {
			return $message . $e->getMessage();
		}
		try {
			// push data into DB
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$sql = 'INSERT INTO ' . $credentials['table'] . '(' . implode(',', $credentials['columns']) . ') VALUES ' . $final_sql;
			$q = $pdo->prepare($sql);
			$q->execute($final_data);
		} catch(PDOException $e) {
			// disconnect from the database
			$pdo = NULL;
			return $message . 'Database failed to load data.' . $e->getMessage();
		}
		
		$pdo = NULL;
		
		$message = 'Success! Database submitted successfully!';
		
		return $message;
	}
	
	// input = array( array(memes) , array(are) , array(lit) )
	public static function check_lengths(array $array_of_arrays) {
		$num_of_arrays = count($array_of_arrays); // 3
		$array_of_counts = array();
		
		foreach($array_of_arrays as $array) {
			$array_of_counts[] = count($array); // array( 1 , 1 , 1 )
		}
		$index = 0;
		while ($index <= ($num_of_arrays - 1)) {
			if ($index == 0) {
				$last = $array_of_counts[0];
			}
			else {
				$current = $array_of_counts[$index];
				if ($last != $current) {
					return false;
				}
				$last = $current;
			}
			$index++;
		}
		
		return true;
	}
	
	// this function takes in an array of arrays of content so that it can create the right SQL statement for insertion
	// it generates the prepared statements syntax necessary for this job in the format: '(?, ?, ...),(?, ?, ...), ...'
	public static function prepare_sql(array $array_of_rows){
		// make sure that the input arrays all have the same lengths
		if(!self::check_lengths($array_of_rows)){
			throw new Exception('Input arrays do not have the same lengths');
		}
		// declare the array that will hold the groups (each in the format: (?, ?, ...))
		$groups = array();
		foreach($array_of_rows as $row){
			$group = '(';
			// verify that the row is indeed an array, otherwise throw an exception
			if (is_array($row) || is_object($row)){
				// declare array that will hold a '?' for each column
				$columns = array();
				foreach($row as $column){
					// verify that the column is not an array, otherwise throw an exception
					if(!is_array($column) && !is_object($column)){
						// add a '?' to the columns array for each column it finds in the row
						$columns[] = '?';
					} else {
						throw new Exception('Input array must be a two dimensional array, no more. Values cannot be arrays or objects.');
					}
				}
			} else {
				throw new Exception('Input array must be a two dimensional array, no less.');
			}
			// implode the columns array into the current group
			$group .= implode(',', $columns);
			$group .= ')';
			// add the current group to the array of groups
			$groups[] = $group;
		}
		// implode the groups into one coherent SQL-compatible string
		$sql = implode(',', $groups);
		return $sql;
	}
	
	// this function differs from the one in the above in that it is designed to prepare the actual text of the arrays
	// for loading, rather than the SQL statement
	public static function prepare_data(array $array_of_arrays) {
		// make sure that the input arrays all have the same lengths
		if(!self::check_lengths($array_of_arrays)){
			throw new Exception('Input arrays do not have the same lengths');
		}
		// declare the array that will hold the groups (each in the format: x,x,x,...)
		$groups = array();
		foreach($array_of_arrays as $row){
			// verify that the row is indeed an array, otherwise throw an exception
			if (is_array($row) || is_object($row)){
				// declare array that will hold the value for each column
				$columns = array();
				foreach($row as $column){
					// verify that the column is not an array, otherwise throw an exception
					if(!is_array($column) && !is_object($column)){
						// add cloumn's value to the columns array
						$columns[] = $column;
					} else {
						throw new Exception('Input array must be a two dimensional array, no more.');
					}
				}
			} else {
				throw new Exception('Input array must be a two dimensional array, no less.');
			}
			// add the current column to the array of groups
			$groups = array_merge($groups, $columns);
		}
		return $groups;
	}
	public static function parse_config($file_path){
		$json = file_get_contents($file_path);
		$json = str_replace('\\', '\\\\', $json);
		$array = json_decode($json, true);
		if(is_array($array)){
			if(!array_key_exists('root_dir', $array) || !is_string($array['root_dir'])){
				throw new Exception('Root directory not properly defined in configuration file at "' . $file_path . '".');
			} else {
				$array['root_dir'] = self::convert_separators($array['root_dir']);
			}
			// this variable stores whether a blacklist array has been specified
			$blacklist = false;
			// allow blacklist to not exist because the implementor may not want a blacklist
			if(array_key_exists('blacklist', $array)){
				// make sure that blacklist is, in fact, an array
				if(is_array($array['blacklist'])){
					$blacklist = true;
					foreach($array['blacklist'] as &$element){
						// make sure that each element within the blacklist is a string
						if(!is_string($element)){
							throw new Exception('Blacklist not properly defined in configuration file at "' . $file_path . '". Blacklist must be a one-dimensional array of strings or NULL.');
						}
						$element = self::convert_separators($element);
					}
				} 
				elseif(is_null($array['blacklist'])){}
				else {
					throw new Exception('Blacklist not properly defined in configuration file at "' . $file_path . '". Blacklist must be a one-dimensional array of strings or NULL.');
				}
			}
			// allow whitelist to not exist because the implementor may not want a whitelist
			if(array_key_exists('whitelist', $array)){
				if(is_array($array['whitelist'])){
					// if blacklist is already defined, throw exception
					if($blacklist){
						throw new Exception('Both a blacklist and a whitelist cannot be defined.');
					}
					foreach($array['whitelist'] as &$element){
						// make sure that each element within the blacklist is a string
						if(!is_string($element)){
							throw new Exception('Whitelist not properly defined in configuration file at "' . $file_path . '". Whitelist must be a one-dimensional array of strings or NULL.');
						}
						$element = self::convert_separators($element);
					}
				}
				// allow whitelist to be NULL
				elseif(is_null($array['whitelist'])){}
				else {
					throw new Exception('Whitelist not properly defined in configuration file at "' . $file_path . '". Whitelist must be a one-dimensional array of strings or NULL.');
				}
			}
			if(array_key_exists('queries', $array)){
				if(is_array($array['queries'])){
					foreach($array['queries'] as $query){
						if(!is_string($query)){
							throw new Exception('Queries not properly defined. Queries must be a one-dimensional array of strings.');
						}
					}
				} 
				else {
					throw new Exception('Queries not properly defined. Queries must be a one-dimensional array of strings.');
				}
			}
			if(array_key_exists('db_creds', $array)){
				if(is_array($array['db_creds'])){
					foreach($array['db_creds'] as $query){
						if(!is_string($query)){
							throw new Exception('Database credentials not properly defined. Database credentials must be made up of a one-dimensional array of strings.');
						}
					}
					if(array_key_exists('username', $array['db_creds'])){
						if(!is_string($array['db_creds']['username'])){
							throw new Exception('Database credentials not properly defined. Username must be a string.');
						}
					}
					else {
						throw new Exception('Database credentials not properly defined. No username specified.');
					}
					if(array_key_exists('password', $array['db_creds'])){
						if(!is_string($array['db_creds']['password'])){
							throw new Exception('Database credentials not properly defined. Password must be a string.');
						}
					}
					else {
						throw new Exception('Database credentials not properly defined. No password specified.');
					}
					if(array_key_exists('type', $array['db_creds'])){
						if(!is_string($array['db_creds']['type'])){
							throw new Exception('Database credentials not properly defined. Type must be a string.');
						}
					}
					else {
						throw new Exception('Database credentials not properly defined. No type specified.');
					}
					if(array_key_exists('database', $array['db_creds'])){
						if(!is_string($array['db_creds']['database'])){
							throw new Exception('Database credentials not properly defined. Database must be a string.');
						}
					}
					else {
						throw new Exception('Database credentials not properly defined. No database specified.');
					}
					if(array_key_exists('host', $array['db_creds'])){
						if(!is_string($array['db_creds']['host'])){
							throw new Exception('Database credentials not properly defined. Host must be a string.');
						}
					}
					else {
						throw new Exception('Database credentials not properly defined. No host specified.');
					}
				} 
				else {
					throw new Exception('Database credentials not properly defined. Database credentials must be made up of a one-dimensional array of strings.');
				}
			}
		} else {
			throw new Exception('Configuration file at "' . $file_path . '" could not be parsed. Check your syntax.');
		}
		return $array;
	}
	
	public static function convert_separators($path){
		$path = str_replace('/', DIRECTORY_SEPARATOR, $path);
		$path = str_replace('\\', DIRECTORY_SEPARATOR, $path);
		return $path;
	}
}
?>