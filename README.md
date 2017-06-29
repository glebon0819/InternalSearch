# Internal Search Package/Library
# !! Attention !! This is currently under development. You are welcome to take the code and use it but it is not finished. Check back in soon and it will probably be done.
## Description:
This is the code for an internal web scraping package that provides tools for scraping HTML content off of pages within its directory and all subdirectories and dumps all of that content into a database to create an index for an internal search engine so users can search for pages within your site. This script would have to be run periodically to keep the content fresh, but that is not directly addressed by this package. You could either do that manually or schedule it to run automatically every so often.

## Scheme:
(This is a summary and does not directly represent the library or its functions)
```php
// first, you scan a folder, such as the root directory of your website, to create a list of paths to each file
function scan_directory ($path_to_folder_you_want_to_scan) {
	// scans folder to create a list of files within that folder
	// if it finds a folder, it recursively scans that folder and adds the result to the final array of paths
	return $array_of_paths_to_files
}

// then you use that list to tell the scraper which files to scrape
function scrapes_all_files ($array_of_paths_to_files) {
	foreach ($file in the $array_of_paths_to_files) {
		// scrape file for specific content
	}
	return $array_of_scraped_content
}

// lastly, you load the content and the paths into an index (a database table) 
function load_content ($array_of_paths_to_files, $array_of_scraped_content) {
	if (database_connect(credentials)) {
		if (array_length($array_of_scraped_content) == array_length($array_of_paths_to_files)) {
			foreach ($item in $array_of_paths_to_files) {
				// insert $array_of_paths_to_files and $array_of_scraped_content into DB as separate columns in the same row
			}
			return "Site indexed successfully.";
		}
	}
	else {
		return "Connection failed.";
	}
}
```

## Implementation:
```php
$list = scan_directory('../path/to/root/')
$content = scrapes_all_files($list)
$message = load_content($content)
echo $message;
```
