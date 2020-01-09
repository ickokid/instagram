<?php
function findHashtag($text, &$hashtags) {
	$text = strtolower($text);
	$content = preg_split("/[\s]+/", $text);
	for ($i = 0; $i < sizeof($content); $i++) {
		if (strlen($content[$i]) > 3 && strlen($content[$i]) < 51 && strcmp(substr($content[$i], 0, 1), "#") == 0) {
			$content[$i] = substr($content[$i], 1);
			if( $content[$i] ) {
				$validHashtag = TRUE;
				for ($j = 0; $j < strlen($content[$i]); $j++) {
					$char = substr($content[$i], $j, 1);
					if (!(($char >= 'a' && $char <= 'z') || ($char >= '0' && $char <= '9') || ($char=='_') )) {
						$validHashtag = FALSE;
						break;
					}
				}
				if ($validHashtag && !in_array($content[$i], $hashtags)) {
					array_push($hashtags, $content[$i]);
				}
			}
		}
	}
	return $hashtags;
}	
?>