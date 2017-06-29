# Coming Soon
## Design:
- Currently, we aim to make the library a class in order to unify all of the functions (which can all be called statically) into one place
- We also want to provide a way for implementors to quickly define the many variables that are necessary to run these functions by creating an *index()* function that parses a JSON configuration file, which has been written by the implementor based on a template that we will provide. *Index()* will then use the values it has gathered from the configuration file to execute the necessary static functions automatically. This design allows users to call each static function individually for more customization or quickly define one or more configuration files to speed up the process.

**Without JSON configuration file:**
```php
$root_directory = '.\\pages';

$blacklist = array(
  [0] => '.\\pages\\forum\\script.php'
);

$content_to_scrape = array(
  [0] => '//p[@class='description']',
  [1] => '//title'
);

$database_credentials = array(
  ['username'] => 'root',
  ['password'] => '',
  ['host'] => 'localhost',
  ['database'] => 'internal_search',
  ['type'] => 'mysql'
);

$list = InternalSearch::scan_directory($root_directory, $blacklist);
$content = InternalSearch::scrape_content($list, $content_to_scrape);
$message = InternalSearch::loadContents($content, $database_credentials);
InternalSearch::dumpMessage($message);
```
**With JSON configuration file:**
```php
InternalSearch::index('config.json');
```
config.json:
```json
{
  "root_directory":".\\pages",
  "dumpMessage":false,
  "blacklist":{
    "0":".\\pages\\forum\\script.php"
  },
  "whitelist":null,
  "content":{
    "XPathForm":true,
    "0":"//p[@class='description']",
    "1":"//title"
  },
  "database":{
    "host":"localhost",
    "username":"root",
    "password":"",
    "type":"mysql",
    "database":"internal_search",
    "mergeColumns":true,
    "columns":"paths, content"
  }
```

## Functions to make:
- **Make check_array_lengths() flexible:** Currently *check_array_lengths()* only accepts two parameters. The end-goal is to have *check_array_lengths()* accept as many parameters as we want and still work.
- **(Maybe) Make check_array_lengths() return a boolean**
- **create dumpMessage():** This function would check if a file named "message_log.csv" exists in the current directory. If it does, it dumps the current message into the CSV file. If it does not, it creates the file and then adds the message to it.
- <s>**struck out function():**</s> Description of *function()*
