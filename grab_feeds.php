<?php
	ini_set('max_execution_time', 0);
	ini_set("memory_limit", "-1");
	set_time_limit(0);
	ini_set('display_errors',0);
	error_reporting(E_ERROR);
	date_default_timezone_set('Asia/Jakarta');
	
	$urlmedia = "https://www.instagram.com/ickokid";
	$html = file_get_contents($urlmedia);
	$result = explode('window._sharedData = ',$html);
	$arr = explode(';</script>',$result[1]);
	$result_photo = json_decode($arr[0] , true);
	
	if(count($result_photo) > 0){
		$arrPhotos = $result_photo['entry_data']['ProfilePage'][0]['graphql']['user']['edge_owner_to_timeline_media']['edges'];
		
		$photos = array();
		foreach ($arrPhotos as $key => $arrPhotosNode) {
			$photos[$key] = $arrPhotosNode['node'];
		}
		
		$created = array();
		foreach ($photos as $key => $row) {
			$created[$key] = $row['taken_at_timestamp'];
		}
		
		array_multisort($created, SORT_ASC, $photos);
		
		$photoCounter = 0;
		
		foreach($photos AS $photo) {
			echo "<pre>";
			print_r($photo);
			echo "</pre>";
		}	
	}	
?>