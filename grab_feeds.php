<?php
ini_set('max_execution_time', 0);
ini_set("memory_limit", "-1");
set_time_limit(0);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Jakarta');

define("PATH_PROJECT", "C:\\wamp64\\www\\test\\instagram");

//require 'vendor/autoload.php';
require 'include/uuid.php';
require 'include/globalFunc.php';

$user_agent = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:52.0.1) Gecko/20100101 Firefox/52.0.1';
$username = isset($_GET['username'])?$_GET['username']:"juventus";
$urlmedia = "https://www.instagram.com/".$username."";
$html = @file_get_contents($urlmedia);

if($html === FALSE){
	die('Error Grab HTML Instagram : $username is Private Account');
} else {
	$html = utf8_decode($html);
	$result = explode('window._sharedData = ',$html);
	$arr = explode(';</script>',$result[1]);
	
	if(isset($arr[0])){
		$result_photo = json_decode($arr[0] , true);
	
		if(count($result_photo) > 0){
			$arrProfile = isset($result_photo['entry_data']['ProfilePage'][0]['graphql']['user'])?$result_photo['entry_data']['ProfilePage'][0]['graphql']['user']:array();
			
			$id = isset($arrProfile['id'])?$arrProfile['id']:"";
			$username = isset($arrProfile['id'])?$arrProfile['username']:"";
			$full_name = isset($arrProfile['full_name'])?$arrProfile['full_name']:"";
			$biography = isset($arrProfile['biography'])?$arrProfile['biography']:"";
			$profile_pic_url = isset($arrProfile['profile_pic_url'])?$arrProfile['profile_pic_url']:"";
			$profile_pic_url_hd = isset($arrProfile['profile_pic_url_hd'])?$arrProfile['profile_pic_url_hd']:"";
			$follower = isset($arrProfile['edge_followed_by']['count'])?$arrProfile['edge_followed_by']['count']:0;
			$following = isset($arrProfile['edge_follow']['count'])?$arrProfile['edge_follow']['count']:0;
			
			if(!empty($id) && !empty($username)){
				$arrMedia = isset($arrProfile['edge_owner_to_timeline_media']['edges'])?$arrProfile['edge_owner_to_timeline_media']['edges']:array();
				
				$newVal = array();
				
				if(count($arrMedia)){
					$photos = array();
					foreach ($arrMedia as $key => $arrPhotosNode) {
						$photos[$key] = $arrPhotosNode['node'];
					}
					
					$created = array();
					foreach ($photos as $key => $row) {
						$created[$key] = $row['taken_at_timestamp'];
					}
					
					array_multisort($created, SORT_ASC, $photos);
					
					$photoCounter = 0;
					
					foreach($photos AS $photo) {
						//$instaId		= $photo['id']."_c";
						$instaId		= $photo['id'];
						$mediaId		= $instaId . '_' . $id;
						$codeId			= $photo['shortcode'];
						$mediaType		= $photo['__typename']; //Video, Image & Slider
						$title			= $photo['edge_media_to_caption']['edges'][0]['node']['text'];
						$hashtags 		= array();findHashtag($title, $hashtags);
						$ownerId		= $photo['owner']['id'];
						$ownerUsername	= $photo['owner']['username'];
						$timestamp		= $photo['taken_at_timestamp'];
						
						if($mediaType=="GraphSidecar"){
							$urlmediaSlider = "https://www.instagram.com/p/".$codeId."/?__a=1";
							
							$opts = array('http' =>
										array(
											'method'  => 'GET',
											'timeout' => 3,
											"header" => "Content-type: text/html \r\n",
											'user_agent'  => $user_agent
										)
									);
							
							$contextDetail = stream_context_create($opts);
							$resultDetail = @file_get_contents($urlmediaSlider, false, $contextDetail);
							
							$result_slider = json_decode($resultDetail,TRUE);
							$carousel_media = isset($result_slider['graphql']['shortcode_media']['edge_sidecar_to_children']['edges'])?$result_slider['graphql']['shortcode_media']['edge_sidecar_to_children']['edges']:array();
							
							if(count($carousel_media) > 0 ){
								$photoCounterCaraousel = 0;
								foreach($carousel_media as $keycarousel => $carousel){
									$mediaNewId 		= $mediaId ."_". $keycarousel;
									$mediaTypeCarousel	= $carousel['node']['__typename'];
									$codeIdCarousel		= $carousel['node']['shortcode'];
									$titleCarousel		= $title." Part ".($keycarousel+1);
									
									if($mediaTypeCarousel=="GraphImage"){
										$photoUrl	= $carousel['node']['display_url'];

										$fileName	= $mediaNewId.'.jpg';
										$fileDest	= PATH_PROJECT . '\\tmp\\' . $fileName;
										
										$saved = file_put_contents($fileDest, file_get_contents($photoUrl));
										
										$imgSize 	= filesize($fileDest);
										$info		= getimagesize($fileDest);
										$extension	= image_type_to_extension($info[2], true);
										$mime		= $info['mime'];
										$imgWidth	= (int) $info[0];
										$imgHeight	= (int) $info[1];
										$created	= date("Ymdhis"); //new MongoDate();
										
										if($imgSize > 0){
											$arrVal	= array();
											$arrVal["title"] = $titleCarousel;
											if( count($hashtags) > 0 ) {
												$arrVal["hashtag"] = $hashtags;	
											}
											$arrVal["photo"] = $photoUrl;	
											$arrVal["photo_size"] = $imgSize;	
											$arrVal["photo_width"] = $imgWidth;	
											$arrVal["photo_height"] = $imgHeight;
											$arrVal["type"] = "photo";	
											$arrVal["created"] = $created;	

											array_push($newVal, $arrVal);
										}
									} else if($mediaTypeCarousel=="GraphVideo"){
										$photoUrl		= $carousel['node']['display_url'];
										$videoUrl 		= isset($carousel['node']['video_url'])?$carousel['node']['video_url']:"";
										$videoUrlWidth 	= isset($carousel['node']['dimensions']['width'])?$carousel['node']['dimensions']['width']:0;
										$videoUrlHeight = isset($carousel['node']['dimensions']['height'])?$carousel['node']['dimensions']['height']:0;
										
										if(!empty($videoUrl)){
											$fileVideoName		= $mediaNewId.'.mp4';
											$fileDestVideo		= PATH_PROJECT . '\\tmp\\' . $fileVideoName;
									
											$saved = file_put_contents($fileDestVideo, file_get_contents($videoUrl));
											$videoSize 	= filesize($fileDestVideo);
											
											if($videoSize > 0){
												$fileName		= $mediaId.'.jpg';
												$fileDest		= PATH_PROJECT.'\\tmp\\'.$fileName;
												
												$saved = file_put_contents($fileDest, file_get_contents($photoUrl));
												
												/* $ffmpeg = FFMpeg\FFMpeg::create(array(
													'ffmpeg.binaries'  => 'C:\wamp64\www\test\instagram\ffmpeg\bin\ffmpeg.exe',
													'ffprobe.binaries' => 'C:\wamp64\www\test\instagram\ffmpeg\bin\ffprobe.exe',
													'timeout'          => 3600, // The timeout for the underlying process
													'ffmpeg.threads'   => 12,   // The number of threads that FFMpeg should use
												));
												$video = $ffmpeg->open($fileDestVideo);
												$video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(2))->save($fileDest); */ 
												
												//$command = "C:\ffmpeg\bin\ffmpeg.exe ffmpeg -i ".$fileDestVideo." -ss 00:00:02 -r 1/1 ".$fileDest;
												//exec($command, $output);
												
												if(file_exists($fileDest)) {
													$imgSize 	= filesize($fileDest);
													$info		= getimagesize($fileDest);
													$extension	= image_type_to_extension($info[2], true);
													$mime		= $info['mime'];
													$imgWidth	= (int) $info[0];
													$imgHeight	= (int) $info[1];
													
													$arrVal	= array();
													$arrVal["title"] = $title;
													if( count($hashtags) > 0 ) {
														$arrVal["hashtag"] = $hashtags;	
													}
													$arrVal["photo"] = $photoUrl;	
													$arrVal["photo_width"] = $imgWidth;	
													$arrVal["photo_height"] = $imgHeight;	
													$arrVal["created"] = $created;	
													$arrVal["photo_size"] = $imgSize;	
													$arrVal["type"] = "video";	
													$arrVal["video"] = $videoUrl;
													$arrVal["video_size"] = $videoSize;
													
													array_push($newVal, $arrVal);
												}
											}
										} 
									}	
								}	
							}
						} else {
							if($mediaType=="GraphImage"){
								$photoUrl		= $photo['display_url']; 
								
								//$fileName	= UUID::mint(1, MAC_ADDR).'.jpg';
								$fileName	= $mediaId.'.jpg';
								$fileDest	= PATH_PROJECT . '\\tmp\\' . $fileName;
								
								$saved = file_put_contents($fileDest, file_get_contents($photoUrl));
														
								$imgSize 	= filesize($fileDest);
								$info		= getimagesize($fileDest);
								$extension	= image_type_to_extension($info[2], true);
								$mime		= $info['mime'];
								$imgWidth	= (int) $info[0];
								$imgHeight	= (int) $info[1];
								$created	= date("Ymdhis"); //new MongoDate();
								
								if($imgSize > 0){
									$arrVal	= array();
									$arrVal["title"] = $title;
									if( count($hashtags) > 0 ) {
										$arrVal["hashtag"] = $hashtags;	
									}
									$arrVal["photo"] = $photoUrl;	
									$arrVal["photo_size"] = $imgSize;	
									$arrVal["photo_width"] = $imgWidth;	
									$arrVal["photo_height"] = $imgHeight;
									$arrVal["type"] = "photo";	
									$arrVal["created"] = $created;	

									array_push($newVal, $arrVal);
								}	
							} else if($mediaType=="GraphVideo"){
								$urlmediaDetail = "https://www.instagram.com/p/".$codeId."/?__a=1";

								$opts = array('http' =>
											array(
												'method'  => 'GET',
												'timeout' => 3,
												"header" => "Content-type: text/html \r\n",
												'user_agent'  => $user_agent
											)
										);

								$contextDetail = stream_context_create($opts);
								$resultDetail = @file_get_contents($urlmediaDetail, false, $contextDetail);
								$result_video_detail = json_decode($resultDetail,TRUE);
								$videos = isset($result_video_detail['graphql']['shortcode_media'])?$result_video_detail['graphql']['shortcode_media']:array();

								$photoUrl		= $photo['display_url'];
								$videoUrl 		= isset($videos['video_url'])?$videos['video_url']:"";
								$videoUrlWidth 	= isset($videos['dimensions']['width'])?$videos['dimensions']['width']:0;
								$videoUrlHeight = isset($videos['dimensions']['height'])?$videos['dimensions']['height']:0;
								
								if(!empty($videoUrl)){
									//$fileSourceName		= UUID::mint(1, MAC_ADDR).'.mp4';
									$fileVideoName			= $mediaId.'.mp4';
									$fileDestVideo			= PATH_PROJECT . '\\tmp\\' . $fileVideoName;
									
									$saved = file_put_contents($fileDestVideo, file_get_contents($videoUrl));
									$videoSize 	= filesize($fileDestVideo);
									
									if($videoSize > 0){
										$fileName		= $mediaId.'.jpg';
										$fileDest		= PATH_PROJECT.'\\tmp\\'.$fileName;
										
										$saved = file_put_contents($fileDest, file_get_contents($photoUrl));
										
										/* $ffmpeg = FFMpeg\FFMpeg::create(array(
											'ffmpeg.binaries'  => 'C:\wamp64\www\test\instagram\ffmpeg\bin\ffmpeg.exe',
											'ffprobe.binaries' => 'C:\wamp64\www\test\instagram\ffmpeg\bin\ffprobe.exe',
											'timeout'          => 3600, // The timeout for the underlying process
											'ffmpeg.threads'   => 12,   // The number of threads that FFMpeg should use
										));
										$video = $ffmpeg->open($fileDestVideo);
										$video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(2))->save($fileDest); */ 
										
										//$command = "C:\ffmpeg\bin\ffmpeg.exe ffmpeg -i ".$fileDestVideo." -ss 00:00:02 -r 1/1 ".$fileDest;
										//exec($command, $output);
										
										if(file_exists($fileDest)) {
											$imgSize 	= filesize($fileDest);
											$info		= getimagesize($fileDest);
											$extension	= image_type_to_extension($info[2], true);
											$mime		= $info['mime'];
											$imgWidth	= (int) $info[0];
											$imgHeight	= (int) $info[1];
											
											$arrVal	= array();
											$arrVal["title"] = $title;
											if( count($hashtags) > 0 ) {
												$arrVal["hashtag"] = $hashtags;	
											}
											$arrVal["photo"] = $photoUrl;	
											$arrVal["photo_width"] = $imgWidth;	
											$arrVal["photo_height"] = $imgHeight;	
											$arrVal["created"] = $created;	
											$arrVal["photo_size"] = $imgSize;	
											$arrVal["type"] = "video";	
											$arrVal["video"] = $videoUrl;
											$arrVal["video_size"] = $videoSize;
											
											array_push($newVal, $arrVal);
										}
									}
								} 
							}
						}	
					}
					
					echo "<pre>";
					print_r($newVal);
					echo "</pre>";
				} else {
					die('No Content Media');
				}
			} else {
				die('Error Grab HTML Instagram : Structure Field (entry_data) changed');
			}
		} else {
			die('Error Grab HTML Instagram : Structure Field changed');
		}
	} else {
		die('Error Grab HTML Instagram : Structure HTML changed');
	}
}

?>