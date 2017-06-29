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
		
		$list = InternalSearch::scan_directory('.\\pages');
		$content = InternalSearch::scrape_files($list);
		$message = InternalSearch::load_content($content);
		var_dump($list);
		var_dump($content);
		var_dump($message);
	?>
</body>
</html>