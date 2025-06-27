<?php

class RS_Schedule_Post_Type {
	
	public function __construct() {
		
		// Register the "Schedule" post type.
		add_action( 'init', array( $this, 'register_post_type_schedule' ) );
		
		// Registers the RRule configuration fields for the 'schedule' post type.
		add_action( 'acf/init', array( $this, 'register_rrule_fields' ) );
		
		// Add a meta box to display a history of when the event was triggered
		add_action( 'add_meta_boxes', array( $this, 'add_event_history_meta_box' ) );
		
		// Display an admin notice if a schedule starts in the past and was never triggered
		add_action( 'admin_notices', array( $this, 'check_past_schedules' ) );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Utilities
	/**
	 * Gets all event settings for the 'schedule' post type.
	 *
	 * @return array {
	 *     @param string $id                - Unique identifier based for the event. Default: "schedule_" . $post_id
	 *     @param string $title             - The title of the event.
	 *     @param array|null $rrule         - Recurrence rule settings, if the event is recurring. {
	 *         @param string $freq          -     - Frequency of recurrence (e.g., 'daily', 'weekly', 'monthly', 'yearly').
	 *         @param int $interval         -     - Interval for recurrence (e.g., every 2 weeks).
	 *         @param string $dtstart       -     - Start date/time of the event in ISO format (Y-m-d\TH:i:s).
	 *         @param array|null $byweekday -     - Days of the week for recurrence (e.g., ['mo', 'we']).
	 *         @param string|null $until    -     - End date for recurrence in ISO format (Y-m-d), if applicable.
	 *     }
	 *     @param string|null $start        - Start date/time of the event in ISO format (Y-m-d\TH:i:s), if the event is non-recurring.
	 *     @param bool $allDay              - Whether the event is an all-day event.
	 * }
	 */
	public static function get_schedule_events() {
		$events = array();
		
		$posts = get_posts( array(
			'post_type'      => 'schedule',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		) );
		
		foreach ( $posts as $post ) {
			$post_id     = $post->ID;
			$title       = get_field( 'event_title', $post_id ) ?: get_the_title( $post_id );
			$all_day     = (bool) get_field( 'event_all_day', $post_id );
			$rrule_on    = (bool) get_field( 'rrule_enabled', $post_id );
			$start       = get_field( 'event_start', $post_id );
			$end         = get_field( 'event_end', $post_id );
			$url         = current_user_can( 'manage_options' ) ? get_edit_post_link( $post_id ) : false;
			
			if ( $rrule_on ) {
				// Recurring event via RRule
				$rrule = array(
					'freq' => get_field( 'rrule_freq', $post_id ),
					'interval' => (int) get_field( 'rrule_interval', $post_id ),
					'dtstart' => $start,
				);
				
				$byweekday = get_field( 'rrule_byweekday', $post_id );
				if ( is_array( $byweekday ) && ! empty( $byweekday ) ) {
					$rrule['byweekday'] = $byweekday;
				}
				
				$until = get_field( 'rrule_until', $post_id );
				if ( $until ) {
					$rrule['until'] = $until;
				}
				
				$events[] = array(
					'id'    => 'schedule_' . $post_id,
					'title' => $title,
					'rrule' => $rrule,
					'allDay' => $all_day,
					'url' => $url,
				);
				
			} elseif ( $start ) {
				// One-time event
				$event = array(
					'id'    => 'schedule_' . $post_id,
					'title' => $title,
					'start' => $start,
					'allDay' => $all_day,
					'url' => $url,
				);
				
				if ( $end ) {
					$event['end'] = $end;
				}
				
				$events[] = $event;
			}
		}
		
		return $events;
	}
	
	// Actions
	/**
	 * Register the "Schedule" post type.
	 *
	 * @return void
	 */
	function register_post_type_schedule() {
		$labels = array(
			'name'                  => _x( 'Schedules', 'Post type general name', 'rs-schedule' ),
			'singular_name'         => _x( 'Schedule', 'Post type singular name', 'rs-schedule' ),
			'menu_name'             => _x( 'Schedules', 'Admin Menu text', 'rs-schedule' ),
			'name_admin_bar'        => _x( 'Schedule', 'Add New on Toolbar', 'rs-schedule' ),
			'add_new'               => __( 'Add New', 'rs-schedule' ),
			'add_new_item'          => __( 'Add New Schedule', 'rs-schedule' ),
			'new_item'              => __( 'New Schedule', 'rs-schedule' ),
			'edit_item'             => __( 'Edit Schedule', 'rs-schedule' ),
			'view_item'             => __( 'View Schedule', 'rs-schedule' ),
			'all_items'             => __( 'All Schedules', 'rs-schedule' ),
			'search_items'          => __( 'Search Schedules', 'rs-schedule' ),
			'parent_item_colon'     => __( 'Parent Schedules:', 'rs-schedule' ),
			'not_found'             => __( 'No schedules found.', 'rs-schedule' ),
			'not_found_in_trash'    => __( 'No schedules found in Trash.', 'rs-schedule' ),
		);
		
		register_post_type( 'schedule', array(
			'label'               => 'Schedule',
			'labels'              => $labels,
			'supports'            => array( 'title' ),
			'menu_position'       => 10.1,
			'menu_icon'           => 'dashicons-calendar-alt',
			
			// Access
			'can_export'          => true,
			'exclude_from_search' => true,
			'capability_type'     => 'page',
			
			// Permalinks
			'rewrite'             => true,
			
			// Archives
			'has_archive'         => false,
			'query_var'           => false,
			'hierarchical'        => false,
			
			// Visibility
			'public'              => false,
			'show_ui'             => true,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'show_in_menu'        => true,
			'publicly_queryable'  => false,
			
			// Block editor?
			'show_in_rest'        => false,
		) );
		
	}
	
	/**
	 * Registers the ACF field group for event and recurrence options.
	 *
	 * @return void
	 */
	public function register_rrule_fields() {
		
		if ( ! function_exists( 'acf_add_local_field_group' ) ) return;
		
		/**
		 * Field            Type             Purpose
		 * ------------------------------------------------------------------
		 * event_title      text             Optional override for post title
		 * event_start      datetime_picker  Required start of event
		 * event_end        datetime_picker  Optional end time
		 * event_all_day    true_false       Marks the event as "all day"
		 * rrule_enabled    true_false       Show/hide recurrence fields
		 * rrule_freq       select           Frequency of recurrence
		 * rrule_interval   number           e.g., every 2 weeks
		 * rrule_byweekday  checkbox         Repeats on selected weekdays
		 * rrule_until      date_picker      End recurrence by date
		 */
		
		acf_add_local_field_group( array(
			'key' => 'group_schedule_rrule',
			'title' => 'Event Settings',
			'fields' => array(
				
				// Basic Event Fields (always shown)
				array(
					'key' => 'field_event_title',
					'label' => 'Event Title',
					'name' => 'event_title',
					'type' => 'text',
					'instructions' => 'Overrides the post title (optional).',
				),
				
				array(
					'key' => 'field_event_start',
					'label' => 'Event Date',
					'name' => 'event_start',
					'type' => 'date_time_picker',
					'required' => 1,
					'display_format' => 'Y-m-d H:i:s',
					'return_format' => 'Y-m-d\TH:i:s',
					'first_day' => 0,
				),
				
				// @TODO multi-day events
				/*
				array(
					'key' => 'field_event_start',
					'label' => 'Start Date',
					'name' => 'event_start',
					'type' => 'date_time_picker',
					'required' => 1,
					'display_format' => 'Y-m-d H:i:s',
					'return_format' => 'Y-m-d\TH:i:s',
					'first_day' => 0,
				),
				
				array(
					'key' => 'field_event_end',
					'label' => 'End Date',
					'name' => 'event_end',
					'type' => 'date_time_picker',
					'required' => 0,
					'display_format' => 'Y-m-d H:i:s',
					'return_format' => 'Y-m-d\TH:i:s',
					'first_day' => 0,
				),
				*/
				
				array(
					'key' => 'field_event_all_day',
					'label' => 'All Day',
					'name' => 'event_all_day',
					'type' => 'true_false',
					'default_value' => 0,
					'ui' => 1,
				),
				
				// Recurrence Toggle
				array(
					'key' => 'field_rrule_enabled',
					'label' => 'Enable Recurrence',
					'name' => 'rrule_enabled',
					'type' => 'true_false',
					'default_value' => 0,
					'ui' => 1,
				),
				
				// RRule fields (only if recurrence is enabled)
				array(
					'key' => 'field_rrule_freq',
					'label' => 'Recurrence Frequency',
					'name' => 'rrule_freq',
					'type' => 'select',
					'choices' => array(
						'daily'   => 'Daily',
						'weekly'  => 'Weekly',
						'monthly' => 'Monthly',
						'yearly'  => 'Yearly',
					),
					'default_value' => 'weekly',
					'allow_null' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_rrule_enabled',
								'operator' => '==',
								'value' => '1',
							),
						),
					),
				),
				
				array(
					'key' => 'field_rrule_interval',
					'label' => 'Interval',
					'name' => 'rrule_interval',
					'type' => 'number',
					'min' => 1,
					'default_value' => 1,
					'instructions' => 'Repeat every X units (e.g. every 2 weeks)',
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_rrule_enabled',
								'operator' => '==',
								'value' => '1',
							),
						),
					),
				),
				
				array(
					'key' => 'field_rrule_byweekday',
					'label' => 'Days of the Week',
					'name' => 'rrule_byweekday',
					'type' => 'checkbox',
					'choices' => array(
						'mo' => 'Monday',
						'tu' => 'Tuesday',
						'we' => 'Wednesday',
						'th' => 'Thursday',
						'fr' => 'Friday',
						'sa' => 'Saturday',
						'su' => 'Sunday',
					),
					'layout' => 'horizontal',
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_rrule_enabled',
								'operator' => '==',
								'value' => '1',
							),
						),
					),
				),
				
				array(
					'key' => 'field_rrule_until',
					'label' => 'Repeat Until',
					'name' => 'rrule_until',
					'type' => 'date_picker',
					'return_format' => 'Y-m-d',
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_rrule_enabled',
								'operator' => '==',
								'value' => '1',
							),
						),
					),
				),
			
			),
			'location' => array(
				array(
					array(
						'param' => 'post_type',
						'operator' => '==',
						'value' => 'schedule',
					),
				),
			),
		) );
	}
	
	/**
	 * Adds a meta box to display the event history.
	 *
	 * @return void
	 */
	public function add_event_history_meta_box() {
		add_meta_box(
			'rs_event_history',
			'Event History',
			array( $this, 'render_event_history_meta_box' ),
			'schedule',
			'side'
		);
	}
	
	/**
	 * Renders the event history meta box.
	 *
	 * @param WP_Post $post The current post object.
	 * @return void
	 */
	public function render_event_history_meta_box( $post ) {
		$date_format = 'F j, Y';
		
		// Next occurrence
		$next_ymd = RS_Schedule_Cron::get_next_event( $post->ID );
		if ( $next_ymd ) {
			$display_date = date( $date_format, strtotime( $next_ymd ) );
			echo '<p><strong>Next Event:</strong> ' . esc_html( $display_date ) . '</p>';
		}else{
			echo '<p><strong>Next Event:</strong> <em>None</em></p>';
		}
		
		// Event History
		$history = get_post_meta( $post->ID, '_rs_events', false );
		if ( ! is_array( $history ) ) $history = array();
		
		if ( empty( $history ) ) {
			echo '<p><strong>Event History:</strong> <em>None</em></p>';
		}else{
			echo '<p><strong>Event History:</strong></p>';
			
			echo '<ul class="ul-disc">';
			foreach( $history as $event_date ) {
				$display_date = date( $date_format, strtotime( $event_date ) );
				echo '<li>' . esc_html( $display_date ) . '</li>';
			}
			echo '</ul>';
		}
		
	}
	
	/**
	 * Checks for past schedules that were never triggered and displays an admin notice.
	 *
	 * @return void
	 */
	public function check_past_schedules() {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if ( $screen->id !== 'schedule' ) return;
		
		$post_id = get_the_ID();
		if ( ! $post_id ) return;
		
		// Ignore recurring events, they should eventually occur
		$recurring = get_field( 'rrule_enabled', $post_id );
		if ( $recurring ) return;
		
		// Check the start date compared to history
		$start = get_field( 'event_start', $post_id );
		$history = (array) get_post_meta( $post_id, '_rs_events', false );
		if ( ! $start || $history ) return; // No start date, or history already exists, so no need to show notice
		
		$start_date = strtotime( $start );
		$today = current_time( 'timestamp' );
		if ( $start_date >= $today ) return; // Event starts today or in the future, ignore notice
		
		// The event started in the past but was never triggered
		$message = '<strong>RS Schedule:</strong> This event expired and was not triggered. It will not be triggered going forward. If this event should have run, it may indicate a problem with the WP-Cron system.';
		echo '<div class="notice notice-warning is-dismissible">' . wpautop( $message ) . '</div>';
	}
	
}

RS_Schedule_Post_Type::get_instance();