<?php
class InternalSearch {
	public static function scan_directory($root_dir){
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
					$paths_to_files = array_merge($paths_to_files, self::scan_directory($root_dir . DIRECTORY_SEPARATOR . $value));
				} 
				else 
				{ 
					$paths_to_files[] = $root_dir . DIRECTORY_SEPARATOR . $value; 
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
			if (is_array($row)){
				// declare array that will hold a '?' for each column
				$columns = array();
				foreach($row as $column){
					// verify that the column is not an array, otherwise throw an exception
					if(is_string($column)){
						// add a '?' to the columns array for each column it finds in the row
						$columns[] = '?';
					} else {
						throw new Exception('Input array must be a two dimensional array, no more.');
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
	public static function prepare_data(array $arrays) {
		self::check_lengths($arrays);
		
		$number_of_columns = 0;
		$number_of_rows = 0;
		
		$final_data = array();
		
		$number_of_rows = count($arrays[0]);
		
		$rows = 0;
		
		while ($rows <= ($number_of_rows - 1)) {
			foreach ($arrays as $array) {
				$final_data[] = $array[$rows];
			}
			$rows++;
		}
		
		return $final_data;
	}
	
}
?>