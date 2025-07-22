<?php

class RS_Schedule_Settings {
	
	public function __construct() {
		
		// Add a Calendar page within the Schedules post type menu
		add_action( 'admin_menu', array( $this, 'add_calendar_page' ) );
		
		// Enqueue assets on the admin
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Utilities
	
	/**
	 * Returns an array of events for FullCalendar
	 *
	 * @return array
	 */
	public function get_display_events() {
		// Get all schedule events
		$events = RS_Schedule_Post_Type::get_schedule_events();
		
		// Allow plugins to modify the events displayed
		$events = apply_filters( 'rs_schedule/get_calendar_events', $events );
		
		return $events;
	}
	
	/**
	 * Returns the default view for the calendar.
	 *
	 * @return string
	 */
	public function get_calendar_view_mode() {
		$view_mode = 'dayGridMonth';
		
		// Allow plugins to modify the view mode
		$view_mode = apply_filters( 'rs_schedule/get_view_mode', $view_mode );
		
		return $view_mode;
	}
	
	// Actions
	/**
	 * Add a Calendar page within the Schedules post type menu
	 * @return void
	 */
	public function add_calendar_page() {
		$parent_slug = apply_filters( 'rs_schedule/calendar_parent_slug', 'edit.php?post_type=schedule' );
		
		add_submenu_page(
			$parent_slug,
			'Calendar',
			'Calendar',
			'edit_posts',
			'rs_schedule_calendar',
			array( $this, 'render_calendar_page' )
		);
	}
	
	/**
	 * Renders the content for the custom 'Calendar' admin page.
	 *
	 * @return void
	 */
	public function render_calendar_page() {
		?>
		<div class="wrap">
		<h1>Schedule Calendar</h1>
			
			<div class="rs-calendar-controls">
				<div class="control control-view" id="calendar-control--view">
					<div class="control-input">
						<?php
						// Grids: dayGridMonth, dayGridWeek, timeGridDay
						// Lists: listDay, listWeek, listMonth, and listYear
						$view_groups = array(
							array(
								'group_name' => 'Grid',
								'slug' => 'grid',
								'views' => array(
									array(
										'name' => 'Month',
										'value' => 'dayGridMonth',
									),
									array(
										'name' => 'Week',
										'value' => 'dayGridWeek',
									),
									array(
										'name' => 'Day',
										'value' => 'timeGridDay',
									),
								),
							),
							array(
								'group_name' => 'List',
								'slug' => 'list',
								'views' => array(
									array(
										'name' => 'Year',
										'value' => 'listYear',
									),
									array(
										'name' => 'Month',
										'value' => 'listMonth',
									),
									array(
										'name' => 'Week',
										'value' => 'listWeek',
									),
									array(
										'name' => 'Day',
										'value' => 'listDay',
									),
								),
							),
						);
					
						// The default view
						$default_view = $this->get_calendar_view_mode();
						
						// Display radio buttons to select the view
						foreach( $view_groups as $g ) {
							$group_name = $g['group_name'];
							$slug = $g['slug'];
							$views = $g['views'];
							
							$first_view = reset( $views );
							
							echo '<div class="view-group view-group-'. esc_attr( $slug ).'">';
							
							echo sprintf(
								'<label class="view-label" for="control-view-%s">%s:</label>',
								esc_attr( $first_view['value'] ),
								esc_html( $group_name )
							);
							
							echo '<div class="view-buttons radio-button-row">';
							
							foreach( $views as $v ) {
								$name = $v['name'];
								$value = $v['value'];
								
								$checked = $value === $default_view;
								
								echo '<label class="button '. ($checked ? 'button-primary' : 'button-secondary') .'">';
								echo sprintf(
									'<input type="radio" name="rs-calendar-control--view" id="control-view-%s" value="%s" %s> %s',
									esc_attr( $value ),
									esc_attr( $value ),
									( $checked ? 'checked' : '' ),
									esc_html( $name )
								);
								echo '</label>';
							}
							
							echo '</div>'; // .radio-button-row
							
							echo '</div>'; // .view-group
						}
						?>
					</div>
				</div>
			</div>
			
			<?php
			// Calendar object will be rendered here (see schedule-calendar.js)
			?>
			<div id="calendar"></div>
			
			<?php
			do_action( 'rs_schedule/calendar_page_after' );
			?>
		</div>
		<?php
	}
	
	/**
	 * Enqueue assets on the admin
	 */
	public function enqueue_admin_assets() {
		$page = $_GET['page'] ?? '';
		
		// Only load assets on the calendar page
		if ( $page === 'rs_schedule_calendar' ) {
			wp_enqueue_style( 'rs-schedule-admin', RS_SCHED_URL . '/assets/admin.css', array(), RS_SCHED_VERSION );
			$this->enqueue_fullcalendar_assets();
		}
	}
	
	/**
	 * Enqueues FullCalendar, RRule, and related inline calendar JS.
	 *
	 * @return void
	 */
	public function enqueue_fullcalendar_assets() {
		$use_cdn = true;
		$use_min = true;
		
		$min = $use_min ? '.min' : '';
		
		if ( ! $use_cdn ) {
			// wp_enqueue_style( 'fullcalendar', RS_SCHED_URL . '/assets/fullcalendar/core/main.css', array(), RS_SCHED_VERSION );
			wp_enqueue_script( 'fullcalendar', RS_SCHED_URL . '/assets/fullcalendar/fullcalendar'. $min .'.js', array(), RS_SCHED_VERSION, true );
			wp_enqueue_script( 'rrule', RS_SCHED_URL . '/assets/fullcalendar/rrule'. $min .'.js', array(), RS_SCHED_VERSION, true );
			wp_enqueue_script( 'rrule-fullcalendar', RS_SCHED_URL . '/assets/fullcalendar/rrule-fullcalendar'. $min .'.js', array('rrule'), RS_SCHED_VERSION, true );
		} else {
			// Use CDN
			// wp_enqueue_style( 'fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global.min.css', array(), null );
			wp_enqueue_script( 'fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global'. $min .'.js', array(), null, true );
			wp_enqueue_script( 'rrule', 'https://cdn.jsdelivr.net/npm/rrule@2.6.4/dist/es5/rrule'. $min .'.js', array(), null, true );
			wp_enqueue_script( 'rrule-fullcalendar', 'https://cdn.jsdelivr.net/npm/@fullcalendar/rrule@6.1.17/index.global'. $min .'.js', array( 'rrule' ), null, true );
		}
		
		wp_enqueue_script( 'rs-schedule-calendar', RS_SCHED_URL . '/assets/schedule-calendar.js', array( 'fullcalendar', 'rrule', 'rrule-fullcalendar' ), RS_SCHED_VERSION );
		
		// Add script data to the rs-schedule-calendar script
		wp_localize_script( 'rs-schedule-calendar', 'rsScheduleCalendar', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'rs_schedule_calendar_nonce' ),
			'events' => $this->get_display_events(),
			'view_mode' => $this->get_calendar_view_mode(),
		));
		
	}
	
	
}

RS_Schedule_Settings::get_instance();