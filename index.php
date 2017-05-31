<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<title>Internal Search Testing Ground</title>
</head>

<body>
	<?php 
		include 'scanner.php';
		
		$directory = scan_directory('.\pages');
		
		echo $directory;
	?>
</body>
</html>