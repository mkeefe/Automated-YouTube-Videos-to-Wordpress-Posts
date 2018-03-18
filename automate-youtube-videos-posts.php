<?php

// This script uses the YouTube V3 API to get a channel (yours) last 5 videos
// it then checks Wordpress and creates posts for any videos not previously
// added to the Wordpress site.

// Note: Requires Wordpress & Google API Key (recommend environment var "YOUTUBE_API_KEY")

// @author - mkeefe (http://coderman.com)


// Config vars

$channel_id     = "XXXXXXXXXXXXXXXXXXXXXXXX";
$max_results    = 5;
$auth_key       = "";
$order          = "date";

// End Config vars




// Set error reporting, not required
ini_set("display_errors", 1);
error_reporting(E_ALL);

define("NEW_LINE", "\n\r");
define("OUTPUT_DEBUG", true);

// Load the wordpress core, no UI
include 'wp-load.php';
if(!class_exists('WP_Query')) {
	throw new Exception('Need Wordpress in order to continue. Make sure wp-load.php is found.' . NEW_LINE);
}

// Check for valid API key from above, otherwise look in environment vars,
// if not found in either, exit script.
if(empty($auth_key)) {
	$tmp_api = getenv('YOUTUBE_API_KEY');
	if(!empty($tmp_api)) {
		$auth_key = $tmp_api;
	} else {
		exit("Valid YouTube API Key required" . NEW_LINE);
	}
}

// YouTube API Path and endpoint (we are using /search)
$yt_api_url       = "https://www.googleapis.com/%s?channelId=%s&order=%s&type=video&maxResults=%d&part=snippet&key=%s";
$yt_api_endpoint  = "youtube/v3/search/";

// Craft API URL
$url = sprintf($yt_api_url,
	$yt_api_endpoint,
	$channel_id,
	$order,
	$max_results,
	$auth_key
);

try {

	if(OUTPUT_DEBUG) print "Making YouTube API call..." . NEW_LINE;

	// Make call to server, returns string of data
	$json = file_get_contents($url);

	// Convert string to JSON object for further processing
	$json_decoded = json_decode($json);
	
	// Confirm we have valid data before proceeding
	if(!empty($json_decoded->items)) {
	
		// Create new array to hold video objects
		$videos = array();
	
		// Loop over each video item
		foreach($json_decoded->items as $video_item) {
		
			// Create data object to keep things clean
			$obj = new YouTubeObject(
				$video_item->id->videoId, 
				$video_item->snippet->title,
				$video_item->snippet->description,
				$video_item->snippet->thumbnails->high->url,
				$video_item->snippet->publishedAt
			);
		
			// Add video object to new $videos array
			array_push($videos, $obj);
		
		}
	
		// Take found videos, look for entries in Wordpress DB or add them
		foreach($videos as $video) {
	
			$checker = videoEntryExistsInWordpress('edgtf_post_video_id_meta', $video->video_id);
			if(!$checker) {
				addVideoToWorpdress($video);
			}
	
		}
		
		if(OUTPUT_DEBUG) print "Finished processing any new videos..." . NEW_LINE;
	
	} else {
		
		throw new Exception("JSON did not contain valid 'items'. Cannot continue." . NEW_LINE);
		
	}
	
} catch (Exception $e) {
	exit($e->getMessage());
}

//
// Function and Class definitions
//

function addVideoToWorpdress($video) {
	
	// Create post array, passed into wp_insert_post()
	$post_data = array(
		'post_type'     => 'post' ,
		'post_status'   => 'publish',
		'post_author'   => 1,
		'post_date'     => date("Y-m-d H:i:s", strtotime($video->publish_date)),
		'post_title'    => $video->video_title,
		'post_content'  => $video->video_description, 
		'post_category' => array(get_cat_ID('YouTube')),
		'tax_input'     => array('post_format' => 'video')
	);

	// Insert the post into Wordpress
	$post_id = wp_insert_post($post_data);
	if($post_id) {
		
		// Add meta (youtube video_id) to post data
		add_post_meta($post_id, 'edgtf_video_type_meta', 'youtube');
		add_post_meta($post_id, 'edgtf_post_video_id_meta', $video->video_id);
		
		if(OUTPUT_DEBUG) print "Adding video: " . $video->video_title . NEW_LINE;
	   
	}
	
}

function videoEntryExistsInWordpress($flag='edgtf_post_video_id_meta', $video_id) {
	
	// Create search $args array
	$args = array(
	   'meta_query' => array(
	       array(
	           'key' => $flag,
	           'value' => $video_id,
	           'compare' => '=',
	       )
	   )
	);
	
	// Do Wordpress query
	$query = new WP_Query($args);

	// Check for previously added video, we don't want duplicates
	if(isset($query->posts) && count($query->posts) > 0) return true;
	return false;
	
}

class YouTubeObject {
	
	// TODO: Make protected and add getter/setter
	public $video_id;
	public $video_title;
	public $video_description;
	public $video_thumbnail;	
	public $publish_date;
	
	public function __construct($id, $title, $description, $thumbnail, $date) {
		$this->video_id = $id;
		$this->video_title = $title;
		$this->video_description = $description;
		$this->video_thumbnail = $thumbnail;
		$this->publish_date = $date;
	}
	
}
