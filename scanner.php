<?php
// this function scans the directory it has been given and creates an array of the contents of that directory so that the scraper can go through each file and scrape the contents (more importantly, if the content item is also a directory, it recursively scans that directory and so on so that all files in all subdirectories will also be scraped later)
function scan_directory($path) {
	// create an array to hold a list of all the files in this directory and all the files in all of the subdirectories
	$result = array(); 
	
	// create an array of all of the files and folders in the current directory
	$cdir = scandir($path);
	
	// for each file/folder, 
	foreach ($cdir as $key => $value) 
	{ 
		// if the file's name is not either '.' or '..', proceed
		if (!in_array($value,array(".",".."))) 
		{ 
			// if the current item is a directory, scan it. Otherwise, add the file to the array.
			if (is_dir($path . DIRECTORY_SEPARATOR . $value)) 
			{
				$result[$value] = scan_directory($path . DIRECTORY_SEPARATOR . $value);
			} 
			else 
			{ 
				$result[] = $value; 
			}
		} 
	}
	
	// now that we have the full list of all files in this directory and all subdirectories, process them and return the result
	$result = scrape_files($result, $path);
	
	return $result;
}

// this function scrapes every file in the array above unless it was already scraped (which occurs if the function was called recursively in the case of a subdirectory)
function scrape_files($list, $path) {
	
	$res = "";
	
	foreach ($list as $file) {
		if (substr_count($file, '<h4>') == 0) {
			
			// cleans up the directory's path because CURL can't make requests based on relative URL's
			$path = str_replace('.', 'internal_search', $path);
			$path = str_replace('\\', '/', $path);
			
			$ch = curl_init('http://localhost/' . $path . '/' . $file);
    		$timeout = 5;
			
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
			
			// retreive the HTML from the URL
			$html = curl_exec($ch);
			curl_close($ch);
			
			// we get ready to parse the HTML and disregard any markup syntactical errors
			$dom = new DOMDocument();
			libxml_use_internal_errors(true);
			$dom->loadHTML($html);
			libxml_clear_errors();
			
			$xpath = new DOMXPath($dom);
			
			// scrapes the contents of the description
			$nodes = $xpath->query("//p[@class='description']");
			
			$descriptions = array();
			
			// creates an array of the resulting descriptions (there should only be one)
			foreach ($nodes as $i => $node) {
				$descriptions[] = $node->nodeValue;
			}
			
			$descriptions = array_unique($descriptions);
			$res .= '<h4>Description:</h4><p>';
			foreach ($descriptions as $description) {
				$res .= $description;
			}
			$res .= '</p>';
			$res .= '<p>' . $path . '/' . $file . '</p>';
		}
		else {
			//$res .= '<p>Directory</p>';
			$res .= $file;
		}
	}
	
	return $res;
}


?>
