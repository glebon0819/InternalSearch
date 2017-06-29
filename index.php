<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<title>Internal Search Testing Ground</title>
</head>

<body>
	<?php 
		include 'InternalSearch.php';
		include 'database.php';
		
		$db_credentials = array(
			'type' => 'mysql',
			'host' => 'localhost',
			'database' => 'internal_search',
			'username' => 'root',
			'password' => '',
			'table' => 'search_index',
			'columns' => array(
				'0' => 'path',
				'1' => 'content'
			)
		);
		
		$list = InternalSearch::scan_directory('.\\pages');
		$content = InternalSearch::scrape_files($list);
		$message = InternalSearch::load_content($content, $db_credentials);
		var_dump($list);
		var_dump($content);
		var_dump($message);
	?>
</body>
</html>