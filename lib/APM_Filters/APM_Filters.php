<?php

class Tribe__Events__Pro__APM_Filters__APM_Filters {
	
	/**
	 * Class constructor, adds the actions and filters.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array($this, 'ecp_filters') );
		add_action( 'tribe_cpt_filters_after_init', array($this, 'default_columns') );
		add_filter( 'tribe_query_options', array( $this, 'query_options_for_date' ), 10, 3 );
	}
	
	/**
	 * Set the default columns if a custom set has not been created/being used.
	 *
	 * @param Tribe_APM $apm The passed APM instance.
	 * @return void
	 */
	public function default_columns($apm) {
		global $ecp_apm;
		if ( $ecp_apm === $apm ) {
			// Fallback is the order the columns fall back to if nothing was explicitly set
			// An array of column header IDs
			$ecp_apm->columns->set_fallback(array('title', 'ecp_organizer_filter_key', 'ecp_venue_filter_key', 'events-cats', 'recurring', 'start-date', 'end-date'));
		}
	}
	
	/**
	 * Create the events APM with the additional APM filters that TEC uses.
	 *
	 * @return void
	 */
	public function ecp_filters() {
		$filter_args = array(
			'ecp_venue_filter_key'=>array(
				'name' => tribe_get_venue_label_singular(),
				'custom_type' => 'ecp_venue_filter',
				'sortable' => 'true'
			),
			'ecp_organizer_filter_key'=>array(
				'name' => tribe_get_organizer_label_singular(),
				'custom_type' => 'ecp_organizer_filter',
				'sortable' => 'true'
			),
			'ecp_start_date' => array(
				'name' => __('Start Date', 'tribe-events-calendar-pro'),
				'custom_type' => 'custom_date',
				'disable' => 'columns'
			),
			'ecp_end_date' => array(
				'name' => __('End Date', 'tribe-events-calendar-pro'),
				'custom_type' => 'custom_date',
				'disable' => 'columns'
			),
			'ecp_cost' => array(
				'name' => __('Event Cost', 'tribe-events-calendar-pro'),
				'meta' => '_EventCost',
				'cast' => 'NUMERIC'
			),
			'ecp_cat' => array(
				'name' => __('Event Cats', 'tribe-events-calendar-pro'),
				'taxonomy' => Tribe__Events__Events::TAXONOMY,
				'disable' => 'columns'
			),
			'ecp_title' => array(
				'name' => __('Title', 'tribe-events-calendar-pro'),
				'custom_type' => 'title',
				'disable' => 'columns'
			),
			'ecp_recur' => array(
				'name' => __('Recurring', 'tribe-events-calendar-pro'),
				'custom_type' => 'recur',
				'disable' => 'columns'
			),
			'ecp_content' => array(
				'name' => __('Description', 'tribe-events-calendar-pro'),
				'custom_type' => 'content',
				'disable' => 'columns'
			)
		);
		
		global $ecp_apm;
		$ecp_apm = tribe_setup_apm( Tribe__Events__Events::POSTTYPE, $filter_args );
		$ecp_apm->do_metaboxes = false;
		$ecp_apm->add_taxonomies = false;
	}

	/**
	 * Comparison operators for comparing dates that TEC will need to use.
	 *
	 * @param array $options the current options.
	 * @param string $key
	 * @param mixed $filter
	 * @return array The options with the additional operators.
	 */
	function query_options_for_date( $options, $key, $filter ) {
		if ( 'ecp_start' == $key ) {
			$options = array( 'gte' => '>=', 'lte' => '<=' );
		}
		
		return $options;
	}
}
