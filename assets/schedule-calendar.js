(function() {

	// Get settings from RS_Schedul_Settings->enqueue_fullcalendar_assets()
	const s = window.rsScheduleCalendar || {};

	const ajax_url = s.ajax_url || false;
	const nonce = s.nonce || false;
	const events = s.events || false;
	const view_mode = s.view_mode || false;

	// Enable debug mode if console.log is available
	const debug_mode = !!(console && console.log);

	// Global variables
	let calendar = null; // FullCalendar instance
	let calendarEl = null; // The calendar HTML element
	let controlViewEl = null; // The control element to change the view mode

	// Initialize the calendar on domReady
	const init = function() {

		if ( ! ajax_url || ! nonce ) {
			if ( debug_mode ) console.error('RS Schedule: Missing AJAX URL or nonce, cannot initialize calendar.');
			return;
		}

		if ( ! window.FullCalendar ) {
			if ( debug_mode ) console.error('RS Schedule: FullCalendar library not found, cannot initialize calendar.');
			return;
			}

		if ( debug_mode ) console.log('RS Schedule: Initializing.', { ajax_url: ajax_url, nonce: nonce, events: events, view_mode: view_mode });

		// Prepare the calendar
		calendarEl = document.getElementById('calendar');
		controlViewEl = document.getElementById('calendar-control--view');

		if ( calendarEl && controlViewEl ) {

			// Initialize the FullCalendar instance to the global `calendarEl` element
			initialize_calendar();

			// Initialize the control view element, allowing to change the calendar view mode
			initialize_control_view();

		}else{
			if ( debug_mode ) console.warn('RS Schedule: A required calendar element was not found, cannot initialize.', {
				calendar: calendarEl,
				controlView: controlViewEl
			});
		}

	};

	/**
	 * Decodes HTML entities in a string.
	 * @param input
	 * @returns {string|any}
	 */
	const decode_html_entities = function (input) {
		if ( typeof this.element === 'undefined' ) this.element = document.createElement('textarea');

		this.element.innerHTML = input;
		console.log('decode_html_entities', input, this.element.childNodes);

		return this.element.childNodes.length === 0 ? "" : this.element.childNodes[0].nodeValue;
	}

	/**
	 * Initialize the FullCalendar instance to the global `calendarEl` element
	 */
	const initialize_calendar = function() {
		if ( ! calendarEl ) return;

		// Decode URLs in events. For some reason they get escaped by wp_localize_script()
		if ( events && Array.isArray(events) ) {
			events.forEach(event => {
				if ( typeof event.url !== 'undefined' ) {
					if ( event.url ) {
						// Decode HTML entities
						event.url = decode_html_entities(event.url);
					}else {
						// Unset the url if it's empty
						delete event.url;
					}
				}
			});
		}

		// Display the calendar
		calendar = new window.FullCalendar.Calendar(calendarEl, {
			timeZone: 'UTC',
			initialView: view_mode, // dayGridMonth, listYear...
			events: events,
			/*
			eventDidMount: function(info) {
				if ( debug_mode ) console.log( 'RS Schedule: eventDidMount: ', info.event); // info.event.extendedProps
			}
			*/
		});

		calendar.render();

		if ( debug_mode ) console.log('RS Schedule: Calendar initialized.', { element: calendarEl, calendar: calendar });
	};

	/**
	 * Initialize the control view element, allowing to change the calendar view mode
	 */
	const initialize_control_view = function() {
		if ( ! controlViewEl ) return;

		const onRadioChange = function(event) {
			const selectedView = event.target.value;
			if ( debug_mode ) console.log('RS Schedule: Changing view mode to:', selectedView);
			calendar.changeView(selectedView);

			// Make the parent .button element have .button-primary class, and remove from other buttons
			const buttons = controlViewEl.querySelectorAll('.button');
			buttons.forEach(button => {
				if ( button.classList.contains('button-primary') ) {
					button.classList.remove('button-primary');
				}
			});
			const selectedButton = event.target.closest('.button');
			if ( selectedButton ) {
				selectedButton.classList.add('button-primary');
			}
		};

		controlViewEl.querySelectorAll('input[type="radio"]').forEach(radioEl => {
			radioEl.addEventListener('change', onRadioChange);
		});

		if ( debug_mode ) console.log('RS Schedule: Control view initialized.', { element: controlViewEl });
	};

	/**
	 * Initialize the calendar on domReady
	 */
	document.addEventListener('DOMContentLoaded', function() {
		setTimeout( init, 100 ); // Brief delay for performance
	});

})();