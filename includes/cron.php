<?php

use RRule\RRule;

class RS_Schedule_Cron {
	
	public function __construct() {
		
		// Schedule the main dispatcher event, which controls all other cron jobs
		add_action( 'shutdown', array( $this, 'schedule_main_dispatcher' ) );
		
		// When the main dispatcher event runs, runs all active schedule posts that should fire today
		add_action( 'rs_schedule_daily_dispatcher', array( $this, 'run_schedules_for_today' ) );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Utilities
	/**
	 * Get the RRule object for an event's recurrence settings. Returns false for non-recurring events.
	 *
	 * @param int $post_id
	 *
	 * @return RRule|false
	 */
	public static function get_rrule( $post_id ) {
		$rrule_enabled = get_field( 'rrule_enabled', $post_id );
		if ( ! $rrule_enabled ) return false; // Non-recurring event
		
		// Include RRule Library
		require_once RS_SCHED_PATH . '/vendor/autoload.php';
		
		// Build RRule array using RFC-compliant uppercase keys
		$rrule = array(
			'DTSTART'  => get_field( 'event_start', $post_id ),
			'FREQ'     => get_field( 'rrule_freq', $post_id ),
			'INTERVAL' => (int) get_field( 'rrule_interval', $post_id ),
			'UNTIL'    => get_field( 'rrule_until', $post_id ),
		);
		
		$byweekday = get_field( 'rrule_byweekday', $post_id );
		if ( is_array( $byweekday ) && ! empty( $byweekday ) ) {
			$rrule['BYDAY'] = array_map( 'strtoupper', $byweekday );
		}
		
		// Create the RRule object
		try {
			$rule = new RRule( $rrule );
			
			return $rule;
		} catch ( Exception $e ) {
			if ( function_exists( 'rs_debug_log' ) ) {
				rs_debug_log( 'error', 'get_rrule', 'Error creating RRule for post ID ' . $post_id . ': ' . $e->getMessage() );
			}
			return false;
		}
	}
	
	/**
	 * Get the next occurrence of an event in Y-m-d format. Returns false if the event is expired.
	 *
	 * @param int $post_id
	 * @param string|null $today (Optional) The date to start checking from. Defaults to today if null.
	 *
	 * @return string|false
	 */
	public static function get_next_event( $post_id, $today = null ) {
		// Check if the post ID is a schedule post
		if ( get_post_type($post_id) != 'schedule' ) return false;
		
		if ( $today === null ) $today = current_time( 'Y-m-d H:i:s' );
		
		// Convert to Y-m-d format (ignore the time)
		$today = substr( $today, 0, 10 );
		
		// Get recurrence settings
		$rule = self::get_rrule( $post_id );
		
		if ( $rule ) {
			// If recurring, get the next occurrence
			$next = $rule->getNthOccurrenceAfter( new DateTime( $today ), 1 ); // Get the next occurrence
			
			if ( $next ) {
				// Return the date in Y-m-d format
				return $next->format( 'Y-m-d' );
			} else {
				// No future occurrences, return false
				return false;
			}
		} else{
			// If non-recurring: compare to event_start through event_end
			$start_date = get_field( 'event_start', $post_id );
			
			// @TODO multi-day events
			// The scheduling system currently does not support multi-day events (this may be added later)
			$end_date = false; // get_field( 'event_end', $post_id );
			if ( !$start_date ) return false; // Required
			
			// Convert to Y-m-d format (ignore the time)
			$start_date = substr( $start_date, 0, 10 );
			$end_date = $end_date ? substr( $end_date, 0, 10 ) : null;
			
			// Get timestamps
			$start_ts = strtotime( $start_date );
			$end_ts = $end_date ? strtotime( $end_date ) : false;
			
			if ( $end_date ) {
				// Check if today is between start and end dates
				if ( $start_ts < strtotime( $today ) && strtotime( $today ) <= $end_ts ) {
					// Return the next day
					$next_day = strtotime('+1 day', strtotime( $today ));
					return date( 'Y-m-d', $next_day );
				}else{
					return false;
				}
			}else{
				// Non-recurring event with no end date
				if ( $start_ts > strtotime( $today ) && $start_ts <= strtotime( $today ) + DAY_IN_SECONDS ) {
					// Today is the event's start date
					return $start_date;
				}else{
					// Event is not today
					return false;
				}
			}
		}
	}
	
	/**
	 * Determines if a schedule should run on the given date.
	 *
	 * @param int $post_id
	 * @param string $ymd (e.g., '2025-07-01')
	 *
	 * @return bool
	 */
	public static function should_event_run_on_date( $post_id, $ymd ) {
		$rrule_enabled = get_field( 'rrule_enabled', $post_id );
		
		if ( $rrule_enabled ) {
			// Include RRule Library
			require_once RS_SCHED_PATH . '/vendor/autoload.php';
			
			// Build RRule
			$rrule = array(
				'dtstart' => get_field( 'event_start', $post_id ),
				'freq' => get_field( 'rrule_freq', $post_id ),
				'interval' => (int) get_field( 'rrule_interval', $post_id ),
				'until' => get_field( 'rrule_until', $post_id ),
			);
			
			$byweekday = get_field( 'rrule_byweekday', $post_id );
			if ( is_array( $byweekday ) && ! empty( $byweekday ) ) {
				$rrule['byweekday'] = $byweekday;
			}
			
			// Use the RRule PHP library to check if the event occurs on the given date
			try {
				$rule = new RRule( $rrule );
				
				$event_today = $rule->occursAt( new DateTime( $ymd ) );
				
				return $event_today;
			} catch ( Exception $e ) {
				return false;
			}
		} else {
			// Non-recurring: compare to event_start
			$start_date = substr( get_field( 'event_start', $post_id ), 0, 10 );
			return $start_date === $ymd;
		}
	}
	
	
	// Actions
	/**
	 * Schedules the main dispatcher event, which controls all other cron jobs.
	 * This is called on shutdown to ensure it runs after all other processes.
	 */
	public function schedule_main_dispatcher() {
		if ( ! wp_next_scheduled( 'rs_schedule_daily_dispatcher' ) ) {
			// Schedule for the next hour at :00 minutes
			$next_hour = strtotime('+' . (60 - date('i')) . ' minutes');
			wp_schedule_event( $next_hour, 'hourly', 'rs_schedule_daily_dispatcher' );
		}
	}
	
	/**
	 * When the main dispatcher event runs, runs all active schedule posts that should fire today
	 *
	 * @return void
	 */
	public static function run_schedules_for_today() {
		$today = current_time( 'Y-m-d' );
		
		$posts = get_posts( array(
			'post_type'      => 'schedule',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			
			// Only run once per day
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_rs_last_queried',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_rs_last_queried',
					'value'   => $today,
					'compare' => '!=',
				),
			),
		) );
		
		foreach ( $posts as $post ) {
			$post_id = $post->ID;
			
			if ( self::should_event_run_on_date( $post_id, $today ) ) {
				// Add post meta item each time the event is triggered
				add_post_meta( $post_id, '_rs_events', $today );
				
				// Trigger the event, including the schedule post ID
				do_action( 'rs_schedule/event', $post_id, $today );
			}

			// Remember the date of the last run (to prevent re-occurring on the same day)
			update_post_meta( $post_id, '_rs_last_queried', $today );
		}
	}
	
}

RS_Schedule_Cron::get_instance();