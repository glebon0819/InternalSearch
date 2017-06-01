# internal_search
## Description:
This is the code for an internal web scraping tool that scrapes HTML content off of all pages within its directory and all subdirectories and dumps all of that content into a database to create an index for an internal search engine so users can search for web pages within your site. This script would have to be run periodically to keep the content fresh. You could either do that manually or schedule it to run automatically every so often.

## Scheme:
```php
function compiles_a_list_of_all_the_files_in_current_directory_and_all_subdirectories ($path_to_root_directory) {
  $array = scan_directory($path)
  foreach ($item in the $array) {
		if (is_a_directory($item)) {
			// add $item to the end of $path and recursively call the function with the new path as the input
		}
		else {
			// add $item to a final array of files and their paths
		}
	}
	return $final_list
}

function scrapes_all_files_from_above_list ($array_of_paths_to_files) {
  foreach ($file in the $array) {
    $url = 'http://www.example.com/folder/' . $file
    // use curl to retreive the file from $url
    // use DOMDocument and DOMXPath to scrape only the content you want off of the file
    // add the content to an array of scraped content
  }
  return $array_of_scraped_content
}

function to take the scraped content and load them into a local database ($final_list, $array_of_scraped_content) {
  if (database_connect(credentials)) {
    if (array_length($array_of_scraped_content) == array_length($final_list)) {
      foreach ($item in $final_list) {
        // insert $final_list and $array_of_scraped_content into DB as separate columns in the same row
      }
      return "Site indexed successfully.";
    }
  }
  else {
    return "Connection failed.";
  }
}
```

### Implementation
```php
$list = scan('./path/to/root/')

$content = scrape($list)

$result = index($content)

echo $result;
```
