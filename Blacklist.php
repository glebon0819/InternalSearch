<?php
	
	class Blacklist{
		public static function blacklistFromJSON($path){
			$JSON = file_get_contents($path);
			$JSONArray = json_decode($JSON, true);
			foreach ($JSONArray as $element){
				if(is_array($element)){
					throw new Exception ("blacklistFromJSON expects one dementional array. None given.");
				}
			}
			return $JSONArray;
		}
		
	
		public static function blacklistFromCSV($path){
			$array = EasyCSV::CSVToArray($path);
			foreach ($array as $element){
				if(is_array($element)){
					throw new Exception ("blacklistFromCSV expects one dementional array. None given.");
				}
			}
			return $array;
		}

}
?>