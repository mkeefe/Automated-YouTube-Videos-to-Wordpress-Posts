<?php

// This script uses the YouTube V3 API to get a channel (yours) last 10 videos
// it then checks Wordpress and creates posts for any videos not previously
// added to the Wordpress site.

// Note: Requires Wordpress & Google API Key (recommend environment var "YOUTUBE_API_KEY")
// Note: Create category 'YouTube' to assign to your video posts


// Config vars

$channel_id     = "";
$max_results    = 10;
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
				"https://i.ytimg.com/vi/{$video_item->id->videoId}/maxresdefault.jpg",
				$video_item->snippet->publishedAt
			);
		
			// Add video object to new $videos array
			array_push($videos, $obj);
		
		}
	
		// Take found videos, look for entries in Wordpress DB or add them
		print "<pre>";
		foreach($videos as $video) {
	
			// See if post entry already exists, add if new
			// Note: "edgtf_post_video_id_meta" is a custom field for my theme, use another field in your post meta
			$checker = videoEntryExistsInWordpress($video->video_id, 'edgtf_post_video_id_meta');
			if(!$checker) {
				
				// Grab specific video details
				$video_search_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet&id={$video->video_id}&key={$auth_key}";
				$description_json = file_get_contents($video_search_url);
				$description_raw = json_decode($description_json, true);
				$description = $description_raw['items'][0]['snippet']['description'];
				$video->video_description = $description;
				
				// Add video object as post entry (includes title and description)
				$post_id = addVideoToWorpdress($video);
				if (!empty($video->video_thumbnail)) {
					// Add YouTube thumbnail as featured image
					addFeaturedImageToPost($video->video_thumbnail, $post_id);
				}
				
			} else {
				if(OUTPUT_DEBUG) print "Did not add video: " . $video->video_title . NEW_LINE;
			}
	
		}
		print "</pre>";
		
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

function addFeaturedImageToPost($image_url, $post_id) {
	
	// Construct image path and image name
	$image_name       = "{$post_id}_" . basename($image_url);
	$upload_dir       = wp_upload_dir();
	$image_data       = file_get_contents($image_url);
	$unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name );
	$filename         = basename( $unique_file_name );

	// Check folder permission and define file upload location
	if( wp_mkdir_p( $upload_dir['path'] ) ) {
	  $file = $upload_dir['path'] . '/' . $filename;
	} else {
	  $file = $upload_dir['basedir'] . '/' . $filename;
	}

	// Create the image on the server
	file_put_contents( $file, $image_data );

	// Check image file type
	$wp_filetype = wp_check_filetype( $filename, null );

	// Set attachment data
	$attachment = array(
	    'post_mime_type' => $wp_filetype['type'],
	    'post_title'     => sanitize_file_name( $filename ),
	    'post_content'   => '',
	    'post_status'    => 'inherit'
	);

	// Create the attachment
	$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
	require_once(ABSPATH . 'wp-admin/includes/image.php');
	$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
	wp_update_attachment_metadata( $attach_id, $attach_data );

	// And finally assign featured image to post
	set_post_thumbnail( $post_id, $attach_id );
	
	if(OUTPUT_DEBUG) print " - Add thumbnail: " . $attachment['post_title'] . NEW_LINE;
	
}

function addVideoToWorpdress($video) {
	
	// Create post array, passed into wp_insert_post()
	$post_data = array(
		'post_type'		=> 'post' ,
		'post_status'	=> 'publish',
		'post_author'	=> 1,
		'post_date'		=> date("Y-m-d H:i:s", strtotime($video->publish_date)), // video upload date
		'post_title'	=> $video->video_title,
		'post_content'	=> $video->video_description, 
		'post_category'	=> array(get_cat_ID('YouTube'))
	);

	// Insert the post into Wordpress
	$post_id = wp_insert_post($post_data);
	if($post_id) {
		
		wp_set_post_terms($post_id, 'video', 'post_format');
		
		// Add meta (youtube video_id) to post data
		// Note: Custom fields, check your theme for proper field names for video ID
		add_post_meta($post_id, 'edgtf_video_type_meta', 'youtube');
		add_post_meta($post_id, 'edgtf_post_video_id_meta', $video->video_id);
		
		if(OUTPUT_DEBUG) print "Adding video: " . $video->video_title . NEW_LINE;
		
		// Finally, return post_id
		return $post_id;
	   
	}
	
	// Issues? Let's return null to handle
	return null;
	
}

function videoEntryExistsInWordpress($video_id, $flag='edgtf_post_video_id_meta') {
	
	// Create search $args array
	$args = array(
	   'meta_query' => array(
	       array(
	           'key' => $flag,
	           'value' => $video_id,
	           'compare' => '='
	       )
	   ),
	   'post_status' => array('publish','future', 'private')
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
