/**
 * Update the date field to create an integer representing YYYYMMDDHHMM
 */

function create_date_time() {
    // Only do this stuff for the Event post type
    if ( get_post_type() == 'event_post_type') {
        $ID = get_the_id();	
        $fields = get_fields($ID);
        
        // if it's a repeating event we are just going to use the date/time from the first event
        if (!empty($fields['event_repeater'])) {
            $event_time_start 	= $fields['event_repeater']['0']['event_details_repeater_group']['event_time_start'];
            $event_date 		= $fields['event_repeater']['0']['event_details_repeater_group']['event_date'];
        }
        
        // here's how we'll set it for non-repeating events 
        if ( !empty($fields['non-recurring_details']['event_date']) ) {
            $event_time_start 	= $fields['non-recurring_details']['event_time'];
            $event_date 		= $fields['non-recurring_details']['event_date'];
        }
        
        // Convert the time to 24-hour time
        $event_time_start = date('Hi', strtotime($event_time_start));
        $event_date = date('Ymd', strtotime($event_date));
        
        //return intval($event_date . $event_time_start);	
        update_post_meta( $ID, 'date_time_int', $event_date . $event_time_start ); 
    }   
}

add_action( 'save_post', 'create_date_time', 20);

/**
 * Disable the date_time field for users (should not be editable as it is updated dynamically on save).
 * */

function disable_date_time() {
	echo '<script type="text/javascript">';
	echo 'jQuery(document).ready( function(){';
	echo 'var input = jQuery(\'div[data-name="date_time_int"] input\');';
	echo 'input.prop("disabled", "disabled");';
	echo '});';
	echo '</script>';
}

add_action('admin_head', 'disable_date_time');

/**
 * This code grabs the events and echoes them out in a single, giant list.
 * Used on the calendar page
 */

function calendar_shortcode() {

    // If there's a search string, enter it here. 
    if ( !empty($_GET['search']) ) {
        $search_input = $_GET['search'];
        $sanitized_search_array = explode(" ", $search_input);

        //Prepare the meta_query array
        $meta_query_array = array('relation' => 'AND');
        foreach ( $sanitized_search_array as $term) {
            $meta_query_array[] = array(
                'key'     => 'event_description',
                'value'   => $term,
                'compare' => 'LIKE',
                );
        }
    }

	// The Query
	$args = array(
				'post_type' 	 => array( 'Event_post_type' ),
				'order'			 => 'ASC',
				'posts_per_page' => -1,
				'orderby'		 => 'meta_value',
				'meta_key'  	 => 'date_time_int',
			);

    // add the query args
    if ( !empty($meta_query_array) ):
        $args['meta_query'] = $meta_query_array;
    endif;


	$the_query = new WP_Query($args);
	
    // SEARCH CONTROLS  ?>
    <form id="calendar-search" action="/calendar" method="get">
        <input type="text" name="search" value="<?php if (!empty($search_input) && isset($search_input) ) { echo $search_input; } ?>" />
        <input id="calendar-search-submit" type="submit" />
    </form>


	<?php // Begin Wordpress Loop
	if ( $the_query->have_posts() ) {

		$event_array = array();

		while ( $the_query->have_posts() ) {
			$the_query->the_post();
			
			$evt_id		       = get_the_id();
			$fields       	   = get_fields( $evt_id );

            // Set the timezone
            date_default_timezone_set("America/Phoenix");

			// if it's a non-recurring event, do this:

			if ( $fields['recurring_event_checkbox'] === 'no' &&  create_date_time_in_loop( $fields['non-recurring_details']['event_date'], $fields['non-recurring_details']['event_end']) > date( 'YmdHi' ,$_SERVER['REQUEST_TIME']) ) {
				$event_array[] = array(		
					'evt_id'		       => get_the_id(),
					'evt_permalink' 	   => get_permalink(),
					'evt_start_time'	   => $fields['non-recurring_details']['event_time'],	
					'evt_end_time'		   => $fields['non-recurring_details']['event_end'],		
					'evt_title' 	       => get_the_title(),
					'evt_venue'			   => $fields['non-recurring_details']['event_venue']->name,
					'evt_date'			   => date('l, F j, Y', strtotime($fields['non-recurring_details']['event_date']) ), 
					'int_date_time'		   => create_date_time_in_loop( $fields['non-recurring_details']['event_date'], $fields['non-recurring_details']['event_time'] )
				);	
			} elseif ( $fields['recurring_event_checkbox'] === 'yes') { // For recurring events, do this:
				foreach ( $fields['event_repeater'] as $rec_event) {
                    // the if condition here checks to see if the end time of the event is after the current time. If so, show the event.
                    if ( create_date_time_in_loop( $rec_event['event_details_repeater_group']['event_date'], $rec_event['event_details_repeater_group']['event_time_end'] ) > date( 'YmdHi' ,$_SERVER['REQUEST_TIME']) ) {
                        $event_array[] = array(		
                            'evt_id'		       => get_the_id(),
                            'evt_permalink' 	   => get_permalink(),
                            'evt_start_time'	   => $rec_event['event_details_repeater_group']['event_time_start'],	
                            'evt_end_time'		   => $rec_event['event_details_repeater_group']['event_time_end'],	
                            'evt_title' 	       => get_the_title(),
                            'evt_venue'			   => $rec_event['event_details_repeater_group']['event_venue']->name,
                            'evt_date'			   => date('l, F j, Y', strtotime($rec_event['event_details_repeater_group']['event_date']) ),	
                            'int_date_time'		   => create_date_time_in_loop( $rec_event['event_details_repeater_group']['event_date'], $rec_event['event_details_repeater_group']['event_time_start'] )
                        );	
                    }
				}	
			} ?>


		<?php } // end while
	} // end if	

    if ( empty($event_array) and !isset($event_array)): ?>
        <p class="no-results-found">No events found!</p>
        <?php return;
    endif;

	// Sort the array by the date/time

	usort($event_array, function($a, $b) {
		return $a['int_date_time'] - $b['int_date_time'];
	}); ?>

	<?php // Build out the table ?>

	<div class="calendar-list">
        <?php // $time_flag sets the date ?>
        <?php $time_flag = "";
              $day_id    = "";
              $first_date = $event_array[0]['evt_date']; ?>
        <?php foreach ($event_array as $event) {
            if ($event['evt_date'] !== $time_flag ) {
                $time_flag = $event['evt_date']; 
                $day_id  = date( 'Ymd', strtotime($time_flag) );?>
                <div tabindex="0" data-date="<?php echo $day_id; ?>" class="calendar-day-header calendar-tr">
                    <div class="date-header">
                        <h2 class="day-h2"><?php echo $time_flag; ?></h2>				
                    </div>
                </div> <?php
            } ?>	
            <div data-date="<?php echo $day_id; ?>" class="open-this event-wrapper <?php if ($event['evt_date'] === $first_date) { echo "open";} ?>">
                <div class="calendar-tr calendar-event-tr ">
                    <div class="event-time">
                        <a tabindex="-1" href="<?php echo $event['evt_permalink']; ?>"><time class="event-start" itemprop="startDate" datetime=""><?php echo $event['evt_start_time']; ?></time>
                        <span class="evt_to">to </span><time class="event-end" itemprop="endDate" datetime=""><?php echo $event['evt_end_time']; ?></time></a>
                    </div>
                    <div class="event-details">
                        <h3 class="event-title"><a href="<?php echo $event['evt_permalink']; ?>"><?php echo $event['evt_title']; ?></a></h3>		
                        <p itemprop="location"><?php echo $event['evt_venue']; ?></p>
                    </div>	
                    <div class="event-buttons">
                        <a aria-label="Click for more event details!" href="<?php echo $event['evt_permalink']; ?>"><i class="fa fa-angle-right"></i></a>
                    </div>
                </div>
            </div>
        <?php }	?>
	</div> 
<?php
}

add_shortcode('vmg_calendar', 'calendar_shortcode');

/**
 * This function converts event data into a YYYYMMDDHHMM string for sorting. 
 */

function create_date_time_in_loop( $date, $time) {
	
	// Convert the time to 24-hour time
	$event_time_start = date('Hi', strtotime($time));
	$event_date = date('Ymd', strtotime($date));
	
	return $event_date . $event_time_start;
}

/**
 * Enqueue a stylesheet specifically for the calendar page
 */

function calendar_enqueue_styles() {
	if ( is_page(4519) || is_page(4738) ) {	    
		wp_enqueue_style( 'vmg-calendar-style', get_stylesheet_directory_uri() . '/style-calendar.css');
        wp_enqueue_script( 'vmg-calendar-script', get_stylesheet_directory_uri() . '/calendar-js.js', false, true );
	}
}
add_action( 'wp_enqueue_scripts', 'calendar_enqueue_styles' );

/**
 * Shortcode for building out the Concert page (/concerts/)
 * In 2018, concerts were built out using html/visual composer
 * In 2019, we will switch to using this shortcode.
 * @param category
 * Returns a list of events in the category  
 */

function display_event_categories( $category ) {

    // Get the category from the shortcode. If not used properly, inform the user.
    if ( key($category) != "category" ) {
        echo "Fail. Usage: [event_category category=\"category-slug\"]";
    }

    $tax = $category['category'];
    
    // The Query
    $args = array(
                'post_type'      => array( 'Event_post_type' ),
                'meta_key'       => 'date_time_int',
                'order'          => 'ASC',
                'orderby'        => 'meta_value',
                'posts_per_page' => -1,
                'tax_query'      => array( 
                                       array( 
                                           'taxonomy'   => 'type-category',
                                           'field'      => 'slug', 
                                           'terms'      => $tax 
                                       ),
                                    ),
            );
     $the_query = new WP_Query($args);

    //echo "<pre>"; print_r($the_query->posts); echo "</pre>";

   // Begin Loop  
	if ( $the_query->have_posts() ) {

		$event_array = array();

		while ( $the_query->have_posts() ) {
			$the_query->the_post();
			
			$evt_id		       = get_the_id();
			$fields       	   = get_fields( $evt_id );
            $featured_img      = get_the_post_thumbnail_url();
            $url               = get_the_permalink();

            //print_r($fields);
            $event_array[] = array(
                     'evt_id'               => get_the_id(),
                     'evt_permalink'        => get_permalink(),
                     'evt_start_time'       => $fields['non-recurring_details']['event_time'],
                     'evt_end_time'         => $fields['non-recurring_details']['event_end'],
                     'evt_title'            => get_the_title(),
                     'evt_venue'            => $fields['non-recurring_details']['event_venue']->name,
                     'evt_date'             => $fields['non-recurring_details']['event_date'],
                     'int_date_time'        => create_date_time_in_loop( $fields['non-recurring_details']['event_date'], $fields['non-recurring_details']['event_time'] ),
                     'evt_img'              => get_the_post_thumbnail(),
                     'evt_img_id'           => get_post_thumbnail_id( get_the_id() ),   
                     'evt_img_alt'          => get_post_meta( get_post_thumbnail_id( get_the_id() ), '_wp_attachment_image_alt', true),
                     'evt_description'      => $fields['event_description'],
                     'evt_video'            => $fields['event_video'],
                     'evt_link'             => $url
                 ); 

		} // end while
	} // end if	
      //echo "<pre>"; print_r($event_array); echo "</pre>";

    // Echo out the concert rows 
        // First, we must skip the concerts that have already happened.

    $date = date('YmdHi'); ?>

    <div class="event-cat-container">   
        
        <?php foreach ( $event_array as $event ) {
            // Skip events that already happened
            if ( $date > $event['int_date_time'] ) {
                // do nothing
            } else {  // build out HTML ?>
                <div class="single-event-row">
                   <div class="forty-sixty-override-left wpb_column vc_column_container vc_col-sm-6">
                        <div class="vc_column-inner">
                            <div class="wpb_wrapper">
                                <div class="wpb_text_column wpb_content_element ">
                                    <div class="wpb_wrapper">
                                         <p><a href="<?php echo $event['evt_link']; ?>"><?php echo $event['evt_img']; ?></a></p>
                                    </div>  
                                </div>
                            </div>
                        </div>  
                    </div> 
                    <div class="forty-sixty-override-right wpb_column vc_column_container vc_col-sm-6">
                        <div class="vc_column-inner ">
                            <div class="wpb_wrapper">
                                <div class="wpb_text_column wpb_content_element ">
                                     <div class="wpb_wrapper">
                                          <p><?php echo $event['evt_video'];?></p>
                                     </div>
                                 </div>
                                <div class="wpb_text_column wpb_content_element ">
                                      <div class="wpb_wrapper">
                                            <p><?php echo $event['evt_description']; ?> </p>    
                                      </div>    
                                </div>
                            </div>
                        </div>
                    </div>  
                </div>
            <?php }   
        } ?>
    </div>
   <?php 
}

add_shortcode( 'event_category', 'display_event_categories');

/**
* Only Show Posts in the Search Bar 
*/

if (!is_admin()) {
    function vmg_search_filter($query) {
        if ($query->is_search) {
            $query->set('post_type', 'post');
        }
        return $query;
    }
    add_filter('pre_get_posts','vmg_search_filter');
