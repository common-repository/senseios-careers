<?php
// Block direct access to this file to avoid hackers
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class JobImporter {
	private $categoryManager;
	private $locationManager;
	
	private $WS_URL = null;
	
	private $summary;

	private $entryCount = 0;
	
	public function __construct() {
		// Fail if the required options aren't set
		if( !get_option('sensei_url') || !get_option('sensei_token') || !get_option('sensei_vendor') ){
			$this->outputError('Please finish <a href="options-general.php?page=sensei_settings_menu">setting up authentication</a> before importing.');
		}else{
	        $options = array(
	        	'_' => get_option( 'sensei_token' ),
	        	'VendorCode' => get_option( 'sensei_vendor' ));
		    $this->WS_URL = get_option( 'sensei_url' ) . '?' . http_build_query( $options );

			$this->summary = new SummaryData();
		
			$this->categoryManager = new CategoryManager('category');
			$this->locationManager = new CategoryManager('sensei_locations');
			$this->loadCurrentPosts();
			
			$json = $this->loadFeed();
			if($json == null){

				throw new Exception('Failed to read jobs. Make sure your <a href="options-general.php?page=sensei_settings_menu">authentication</a> settings are correct.');
			}

			$this->createPosts($json);
			$this->cleanupDeletedPosts();			
		}
	}

	public function getEntryCount(){
		return $this->entryCount;
	}
	
	/**
	* Load Current Posts
	* Load the all of the current posts and add them to a hash indexed by the jobTypeID.
	*/
	private $existingPosts = array(); // hash of JobPostings indexed by JobTypeID
	private function loadCurrentPosts() {
		$args = array(
			'post_type' => 'career',
			'posts_per_page' => -1, // get all posts
			'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit')
		);
		
		$posts = get_posts($args);
		foreach ($posts as $post) {
		
			// Obtain the custom field values
			$customFields = get_post_custom($post->ID);
			$jobTypeID = $customFields['JobTypeID'][0];
			$JobDescriptionID = $customFields['JobDescriptionID'][0];
			if ($JobDescriptionID != null) {
				$job = new JobPosting();
				$job->id = $post->ID;
				$job->title = $post->post_title;
				$job->fullDescription = $post->post_content;
				$job->shortDescription = $post->post_excerpt;
				
				// custom fields
				$job->jobTypeID = $customFields['JobTypeID'][0];
				$job->JobDescriptionID = $customFields['JobDescriptionID'][0];
				$job->isHiring = $customFields['IsHiring'][0];
				$job->City = $customFields['JobCity'][0];
				
				// categories
				$categories = wp_get_post_categories($post->ID);
				$job->category = $categories[0];

				// Add the job to the existing posts hash, with the jobTypeID as the key
				$this->existingPosts[$JobDescriptionID] = $job;
			}
		}
		
	}

	private function getSSLPage($url) {
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_HEADER, false);
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_SSLVERSION,CURL_SSLVERSION_TLSv1); 
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    $result = curl_exec($ch);
	    curl_close($ch);
	    return $result;
	}

	/**
	* Load Feed
	* Load the JSON feed
	*/
	private function loadFeed() {

		if( !$this->WS_URL || $this->WS_URL == null){
			die('No URL found for SenseiOS web service.');
		}


		$response = $this->getSSLPage($this->WS_URL);

		if( $response === false ){
			$this->writeLog('Failed to load jobs from SenseiOS');
			return null;
		}else{

			$json = json_decode($response, true);

			if( isset($json['NumEntries']) ){
				$this->entryCount = $json['NumEntries'];
			}

			if( isset($json['Entries']) ){
				return $json['Entries'];
			}
		}
	}

	private function createPosts($entries) {
		foreach ($entries as $entry) {
			$this->createPostFromJson($entry);
		}
	}
	
	private function createPostFromJson($entry) {
		// Get Category ID
		$categoryID = $this->categoryManager->getCategoryID($entry['JobTypeCategoryName']);

		if( $categoryID != null){

			// create post object
			$post = array(
				 'post_title'    =>  $entry['Name'],
				 'post_content'  =>  $entry['LongDescriptionHTML'],
				 'post_excerpt'  =>  $entry['ShortDescription'],
				 'post_status'   =>  'publish',
				 'post_type'	 =>  'career',
				 'post_author'   =>  1,
				 'post_category' =>  array($categoryID)
			);
			
			// check if the job exists
			$jobTypeID = $entry['JobDescriptionID'];

			// Get Location IDs
			$locationIDs = array();
			$senseiLocationIDs = array();

			if ($entry['Locations'] != null){	
				foreach ($entry['Locations'] as $key=>$location){								
					$currLocationID = intval($this->locationManager->getCategoryID($location)); 
					$locationIDs[] = $currLocationID;
					$senseiLocationIDs[$currLocationID] = $key;
				}
			}			

			//$locationID = intval($this->locationManager->getCategoryID($entry['LocationName']));
			
			$existingPostData = $this->existingPosts[$entry['JobDescriptionID']];

			$added = false;
			if ($existingPostData == null) {
				// don't add incomplete items
				if (strlen($post['post_title']) == 0
					|| strlen($post['post_content']) == 0
					|| strlen($post['post_excerpt']) == 0
				) {
					$this->summary->ignored[] = $entry['Name'];
				} else {
					$this->summary->added[] = $post['post_title'];
					$postID = wp_insert_post( $post, true );
					add_post_meta($postID, 'JobTypeID', $entry['JobTypeID'], true);
					add_post_meta($postID, 'JobTypeName', $entry['JobTypeName'], true);
					add_post_meta($postID, 'JobTypeCategoryName', $entry['JobTypeCategoryName'], true);
					add_post_meta($postID, 'JobDescriptionID', $entry['JobDescriptionID'], true);
					add_post_meta($postID, 'IsHiring', $entry['IsHiring'] ? '1' : '0', true);
					add_post_meta($postID, 'JobCity', $entry['JobCity'], true);
					add_post_meta($postID, 'LocationID', $entry['LocationID'], true);
					add_post_meta($postID, 'LocationName', $entry['LocationName'], true);
					add_post_meta($postID, 'Locations', $senseiLocationIDs, true);
					if ($locationIDs != null){		
						
						wp_set_object_terms( $postID, $locationIDs, 'sensei_locations' );
					}
					$added = true;
				}
			} else {

				$existingLocations = wp_get_object_terms($existingPostData->id, 'sensei_locations', array('orderby' => 'term_id')); 
				$existingLocationIDs = array();

				if($existingLocations != null){
					foreach($existingLocations as $existingLocation){
						$existingLocationIDs[] = $existingLocation->term_id;
					}
				}

				if ($post['post_title'] != $existingPostData->title
				|| $post['post_content'] != $existingPostData->fullDescription
				|| $post['post_excerpt'] != $existingPostData->shortDescription
				|| $entry['IsHiring'] != $existingPostData->isHiring
				|| $entry['JobCity'] != $existingPostData->jobCity
				|| $categoryID != $existingPostData->category
				|| $existingLocationIDs != $locationIDs
				) {
					$this->summary->updated[] = $post['post_title'];
					$postID = $existingPostData->id;
					$post['ID'] = $postID;
					wp_update_post($post, $wp_error);
					update_post_meta($postID, 'JobTypeID', $entry['JobTypeID']);
					update_post_meta($postID, 'JobTypeName', $entry['JobTypeName']);
					update_post_meta($postID, 'JobTypeCategoryName', $entry['JobTypeCategoryName']);
					update_post_meta($postID, 'JobDescriptionID', $entry['JobDescriptionID']);
					update_post_meta($postID, 'IsHiring', $entry['IsHiring'] ? '1' : '0');
					update_post_meta($postID, 'JobCity', $entry['JobCity']);
					update_post_meta($postID, 'LocationID', $entry['LocationID']);
					update_post_meta($postID, 'LocationName', $entry['LocationName']);
					update_post_meta($postID, 'Locations', $senseiLocationIDs, true);
					if ($locationIDs != null){								
						wp_set_object_terms( $postID, $locationIDs, 'sensei_locations' );
					}
					$added = true;
				} else {
					$this->summary->noChange[] = $post['post_title'];
				}
				
				// add the id to the list of existing posts
				$existingPostData->isCurrent = true;
			}
		}else{
			$this->summary->error[] = $entry['Name'];
		}
	}
	
	private function cleanupDeletedPosts() {
		$deleteQueue = array();
		foreach($this->existingPosts as $post) {
			if (!$post->isCurrent) {
				$deleteQueue[] = $post;
				$this->summary->deleted[] = $post->title;
				
			}
		}
		
		
		// actually delete posts
		foreach($deleteQueue as $post) {
			wp_delete_post( $post->id, true );
		}
		
	}

	/**
	* Output Error
	*/
	
	private function outputError($errorMessage) {
		echo('<div id="sensei_import_message" class="error settings-error notice"><p><strong>Error:</strong> ' . $errorMessage . '</p></div>');
	}
	
	/**
	* Output Summary
	*/
	
	public function outputSummary() {
		$careerWord = 'careers';
		if($this->entryCount == 1)
			$careerWord = 'career';

		echo('<p id="sensei_import_message">Imported ' . $this->entryCount . ' ' . $careerWord . ' from ' . get_option('sensei_url') . '.</p>');

		echo('<div class="sensei_summary_card">');
		echo($this->renderHeader('Added (' . count($this->summary->added) . ')'));
		if (count($this->summary->added) > 0) {
			echo($this->renderList($this->summary->added));
		} else {
			echo($this->renderParagraph("None"));
		}
		echo('</div>');
		
		echo('<div class="sensei_summary_card">');
		echo($this->renderHeader('Updated (' . count($this->summary->updated) . ')'));
		if (count($this->summary->updated) > 0) {
			echo($this->renderList($this->summary->updated));
		} else {
			echo($this->renderParagraph("None"));
		}
		echo('</div>');
		
		echo('<div class="sensei_summary_card">');
		echo($this->renderHeader('Ignored (' . count($this->summary->ignored) . ')'));
		if (count($this->summary->ignored) > 0) {
			echo($this->renderList($this->summary->ignored));
		} else {
			echo($this->renderParagraph("None"));
		}
		echo('</div>');
		
		echo('<div class="sensei_summary_card">');
		echo($this->renderHeader('No Change (' . count($this->summary->noChange) . ')'));
		if (count($this->summary->noChange) > 0) {
			echo($this->renderList($this->summary->noChange));
		} else {
			echo($this->renderParagraph("None"));
		}
		echo('</div>');
		
		echo('<div class="sensei_summary_card">');
		echo($this->renderHeader('Deleted (' . count($this->summary->deleted) . ')'));
		if (count($this->summary->deleted) > 0) {
			echo($this->renderList($this->summary->deleted));
		} else {
			echo($this->renderParagraph("None"));
		}
		echo('</div>');		

		echo('<div class="sensei_summary_card">');
		echo($this->renderHeader('Errors (' . count($this->summary->error) . ')'));
		if (count($this->summary->error) > 0) {
			echo($this->renderList($this->summary->error));
		} else {
			echo($this->renderParagraph("None"));
		}
		echo('</div>');		
	}
	
	private function renderHeader($text) {
		$html = "<h3>$text</h3>";
		return $html;
	}
	
	private function renderParagraph($text) {
		$html = "<p>$text</p>";
		return $html;
	}
	
	private function renderList($items) {
		$html = "<ul>";
		asort($items);
		foreach ($items as $item) {
			$html .= "<li>$item</li>";
		}
		$html .= "</ul>";
		
		return $html;
	
	}

	private function writeLog($message){
		if(get_option('sensei_keep_log') == 'on'){
			file_put_contents(  __DIR__ . '/senseios-import.log'  , date( 'M j Y, g:i:s A' ) . ' - ' . $message . "\n", FILE_APPEND);
		}
	}
		
}

class CategoryManager {
	
	private $categoryList;
	private $categoryType;
	
	public function __construct($catType) {
		$this->categoryType = $catType;
		$this->loadCategories();
	}
	
	private function loadCategories() {
		if( $this->categoryType == 'category'){
			$categories = get_categories(array('hide_empty' => 0));
		}else{
			$categories = get_terms($this->categoryType, array('hide_empty' => 0));
		}

		// create a hash of the categories indexed by slug;
		$this->categoryList = array();
		foreach($categories as $category) {
			$this->categoryList[$category->slug] = $category;
		}
	}
	
	public function getCategoryID($name) {		
		if($name != ''){
			$slug = sanitize_title($name);
			$category = $this->categoryList[$slug];
			
			if ($category == null && $this->categoryType == 'category') {
				$category = array('cat_name' => $name, 'category_description' => '', 'category_nicename' => $slug, 'category_parent' => '');
				$id = wp_insert_category($category);
				$this->loadCategories();
			}else if( $category == null ){
				$newTerm = wp_insert_term( $name, $this->categoryType, array('slug' => $slug));
				if( is_wp_error( $newTerm ) ) {
					if(get_option('sensei_keep_log') == 'on'){
				    	file_put_contents(  __DIR__ . '/senseios-import.log', date( 'M j Y, g:i:s A' ) . ' - Failed to insert term ' . $name . ' into ' . $this->categoryType . ' because ' . $newTerm->get_error_message() . "\n", FILE_APPEND);
				    }
					$id = null;
				}else{
					$id = $newTerm['term_id'];
				}
			}else if( $this->categoryType == 'category') {
				$id = $category->cat_ID;
			}else{
				$id = $category->term_id;
			}
		}else{
			$id = null;
		}

		return $id;
	}
}

class JobPosting {
	public $id;
	public $title;
	public $fullDescription;
	public $shortDescription;
	public $jobTypeID;
	public $JobDescriptionID;
	public $isHiring;
	public $jobCity;
	public $category;
	public $isCurrent = false;
}

class SummaryData {
	public $ignored 	= array();
	public $added 		= array();
	public $updated		= array();
	public $noChange	= array();
	public $deleted		= array();
}
?>