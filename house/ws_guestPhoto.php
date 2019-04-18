<?php
	
	if($_GET['action'] == "view" && $_GET['guestID']){
		//get guest photo
	}else{
		$path = realpath('../images/defaultGuestPhoto.png');
		if(file_exists($path)){
			header("Content-Type: " . mime_content_type($path));
			header("Content-Length: " . filesize($path));
			echo file_get_contents($path);
			exit;
		}
	}
?>