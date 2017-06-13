<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<title>Internal Search Testing Ground</title>
</head>

<body>
	<?php 
		include 'functions.php';
		
		// includes the database class that we can use to connect to the local MySQL DB
		include('..' . DIRECTORY_SEPARATOR . 'database.php');
		
		$paths = scan_directory('..' . DIRECTORY_SEPARATOR . 'internal_search' . DIRECTORY_SEPARATOR . 'pages');
		
		$content = scrape_files($paths);
		
		$message = load_content($content);
		
		echo $message;
		
	?>
</body>
</html>