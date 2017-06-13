<?php
// this function scans the directory it has been given and creates an array of the paths to each file in that 
// directory so that the scraper can go through each file and scrape the contents. It works recursively so if one
// of the 'files' in the directory is a directory, it updates the path and calls on itself to scan that directory
function scan_directory($path) {
	// create an array to hold a list of all the files in this directory and all the files in all of the subdirectories
	$paths_to_files = array(); 
	
	// create an array of all of the files and folders in the current directory
	$cdir = scandir($path);
	
	// for each file/folder, 
	foreach ($cdir as $key => $value) 
	{ 
		// if the file/folder's name is not either '.' or '..', proceed
		if (!in_array($value,array(".",".."))) 
		{ 
			// if the current item is a directory, scan it. Otherwise, add the file to the list of file paths
			if (is_dir($path . DIRECTORY_SEPARATOR . $value)) 
			{
				// old version (didn't work correctly; I kept this for my own use): 
				//$result[$value] = scan_directory($path . DIRECTORY_SEPARATOR . $value);
				$paths_to_files = array_merge($paths_to_files, scan_directory($path . DIRECTORY_SEPARATOR . $value));
			} 
			else 
			{ 
				$paths_to_files[] = $path . DIRECTORY_SEPARATOR . $value; 
			}
		} 
	}
	
	return $paths_to_files;
}
// this function scrapes every file in the array above unless it was already scraped 
//(which occurs if the function was called recursively in the case of a subdirectory)
function scrape_files($list) {
	
	$content_array = array();
	$used_paths_array = array();
	
	foreach ($list as $file) {
		
		$handle = fopen($file, 'r');
		$html = fread($handle, filesize($file));
		
		// we get ready to parse the HTML and disregard any markup syntactical errors
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($html);
		libxml_clear_errors();
		
		$xpath = new DOMXPath($dom);
		
		// scrapes the contents of the description area in each file
		$nodes = $xpath->query("//p[@class='description']");
		
		$descriptions = array();
		
		// creates an array of the resulting descriptions (there should only be one)
		foreach ($nodes as $i => $node) {
			$descriptions[] = $node->nodeValue;
		}
		
		// loads each description into the array of content
		foreach ($descriptions as $description) {
			$content_array[] = $description;
		}
		
		// adds the file's path to the array of file paths that actually produced content; this is done to
		// prevent any mismatch later between the length of the array containing the paths to the files and
		// the length of the array containing the actual content.
		if  (count($descriptions) > 0) {
			$used_paths_array[] = $file;
		}
	} 
	
	return array($used_paths_array, $content_array);
}

// this function takes an array containing two arrays as its input and loads the contents of those arrays
// into a local MySQL DB. one of the arrays should be a list of paths to each file that the scraper scraped
// and the second array should be the contents that the scraper scraped from the files of the first array.
// order does not really matter. This will return a message reporting if the load was a success or not.

//function load_content(array $content, array $paths) {
function load_content(array $arrays) {
	// sets the default message type to an error, that way we can just add onto this later if needed
	$message = 'Error: ';
	
	/*
	try {
		// checking if input arrays have the same lengths
		check_lengths($content , $paths);
	} catch(Exception $e) {
		return $message . 'Inputs failed to meet standards. Message: ' . $e->getMessage();
	}
	*/
	
	
	// this prepares the SQL statement for the incoming data
	try {
		$final_sql = prepare_sql($arrays);
	} catch (Exception $e) {
		return $message . 'SQL Preparation failed. Message: ' . $e->getMessage();
	}
	
	// this prepares the data for injection into the prepared statement
	try {
		$final_data = prepare_data($arrays);
	} catch (Exception $e) {
		return $message . 'Data Preparation failed. Message: ' . $e->getMessage();
	}
	
	try {
		// tries to connect to DB using Database class that was included earlier
		$pdo = Database::connect();
	} catch(PDOException $d) {
		return $message . 'Database failed to connect. Message: ' . $d->getMessage();
	}
	try {
		// push data into DB
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$sql = 'INSERT INTO search_index (path, content) VALUES ' . $final_sql;
		$q = $pdo->prepare($sql);
		$q->execute($final_data);
	} catch(PDOException $e) {
		Database::disconnect();
		return $message . 'Database failed to load data.' . $e->getMessage();
	} /*catch(Exception $e) {
		Database::disconnect();
		return $message . 'Database failed to load data.' . $e->getMessage();
	}*/
	Database::disconnect();
	$message = 'Success! Database submitted successfully!';
	
	return $message;
}

// input = array( array(memes) , array(are) , array(lit) )
function check_lengths_new(array $array_of_arrays) {
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
				throw new Exception('Array inputs do not have the same lengths.');
				//return false;
			}
			$last = $current;
		}
		$index++;
	}
	
	return true;
}

// this function takes in an array of arrays of content so that it can create the right SQL statement for insertion
// it generates the prepared statements syntax necessary for this job in the format: '(?, ?, ...),(?, ?, ...), ...'
function prepare_sql(array $content_arrays) { // array($content, $paths) -- 2 items
	// check lengths of arrays to be sure
	check_lengths_new($content_arrays);
	
	$number_of_columns = 0;
	$number_of_rows = 0;
	
	foreach ($content_arrays as $content) {
		$number_of_columns++;
		foreach ($content as $damn) {
			$number_of_rows++;
		}
	}
	
	$number_of_rows = $number_of_rows/$number_of_columns;
	
	$number_of_rows_temp = $number_of_rows;
	
	$sql = array();
	
	while ($number_of_rows_temp > 0) { // 5
		// creates a group in this format: (?, ?, ... )
		$number_of_columns_temp = $number_of_columns;
		$columns_array = array();
		$cluster = '(';
		while ($number_of_columns_temp > 0) { // 2
			$columns_array[] = '?';
			$number_of_columns_temp--;
		}
		$cluster .= implode(',', $columns_array);
		$cluster .= ')';
		
		// adds the group to the list of groups
		$sql[] = $cluster;
		
		$number_of_rows_temp--;
	}
	// consolidates all of the groups into one
	$final_sql = implode(',', $sql);
	
	return $final_sql;
}

// this function differs from the one in the above in that it is designed to prepare the actual text of the arrays
// for loading, rather than the SQL statement
function prepare_data(array $arrays) {
	check_lengths_new($arrays);
	
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
?>