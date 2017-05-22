<?php


/**
 * Class Tribe__Events__Integrations__WPML__Linked_Posts
 *
 * Handles linked posts fetching taking WPML managed translations into account.
 */
class Tribe__Events__Integrations__WPML__Linked_Posts {

	/**
	 * @var Tribe__Events__Integrations__WPML__Linked_Posts
	 */
	protected static $instance;

	/**
	 * @var Tribe__Cache
	 */
	protected $cache;

	/**
	 * @return Tribe__Events__Integrations__WPML__Linked_Posts
	 */
	public static function instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Tribe__Events__Integrations__WPML__Linked_Posts constructor.
	 *
	 * @param Tribe__Cache|null $cache
	 */
	public function __construct( Tribe__Cache $cache = null ) {
		$this->cache = null !== $cache ? $cache : tribe( 'cache' );
	}

	/**
	 * Assign linked posts managed by The Events Calendar a language.
	 *
	 * We use the filter as an action to assign linked posts a language.
	 * WPML will not "see" posts that have not a language assigned: here we make sure that linked posts like
	 * venues and organizers will be assigned the language of the event they are being linked to.
	 *
	 * @param int    $id               The linked post ID; this would be `null` by default but we know TEC is inserting
	 *                                 the post at priority 10.
	 * @param array  $data             Unused, an array of data representing the linked post submission.
	 * @param string $linked_post_type The linked post type, e.g. `tribe_venue` or `tribe_organizer`.
	 * @param string $post_status      Unused, the linked post type post status.
	 * @param int    $event_id         The post ID of the event this post is linked to; this will be null for newly created events.
	 *
	 * @return int The untouched linked post ID.
	 */
	public function filter_tribe_events_linked_post_create( $id, $data, $linked_post_type, $post_status, $event_id ) {
		if ( empty( $id ) || empty( $event_id ) ) {
			return $id;
		}

		$event_language_info = wpml_get_language_information( null, $event_id );

		$language_code = ! empty( $event_language_info['language_code'] ) ? $event_language_info['language_code'] :
			ICL_LANGUAGE_CODE;

		$added = wpml_add_translatable_content( 'post_' . $linked_post_type, $id, $language_code );

		if ( WPML_API_ERROR === $added ) {
			$log   = new Tribe__Log();
			$entry = "Could not set language for linked post type '{$linked_post_type}' with id '{$id}' to '{$language_code}'";
			$log->log_error( $entry, __CLASS__ );
		}

		return $id;
	}

	/**
	 * Filters the query for linked posts to return an array that will contain the translated version of linked
	 * posts or the original one if a translation is missing.
	 *
	 * @param array $results  An array of linked post types results; comes `null` from the filter but other plugins
	 *                        might set it differently.
	 * @param array $args     An array of WP_Query args
	 *
	 * @return array|null An array of linked posts populated taking WPML managed translations into account or `null` if
	 *                    WPML is not active or the current language is the default one.
	 */
	public function filter_tribe_events_linked_posts_query( $results = null, array $args = array() ) {
		$cache_key = $this->cache->make_key( func_get_args(), 'filtered_linked_post_query' );
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		if ( isset( $args['post__not_in'] ) ) {
			return $results;
		}

		// some other function is already filtering this, let's bail
		if ( null !== $results ) {
			return $results;
		}

		/** @var SitePress $sitepress */
		global $sitepress;

		if ( empty( $sitepress ) || ! $sitepress instanceof SitePress ) {
			return $results;
		}

		if ( $sitepress->get_default_language() === ICL_LANGUAGE_CODE ) {
			return $results;
		}

		// IDs only and drop the order to avoid wasting time on something we'll account for later
		$sub_query_args = array_merge( $args, array( 'fields' => 'ids', 'order' => false ) );

		$linked_posts_ids = $this->get_current_language_linked_posts_ids( $sub_query_args );

		$default_lang_linked_posts_ids = $this->get_default_language_linked_post_ids( $sub_query_args );

		$linked_posts_ids = array_merge( $default_lang_linked_posts_ids, $linked_posts_ids );

		// run this query to keep the specified `orderby`
		$linked_posts = get_posts( array_merge( $args, array( 'post__in' => $linked_posts_ids ) ) );

		$this->cache[$cache_key] = $linked_posts;

		return $linked_posts;
	}

	/**
	 * Whether a post ID has a translation in the current language or not.
	 *
	 * @param int $id The post ID
	 *
	 * @return bool `true` if the post lacks a WPML managed translation, `false` if the post has a WPML managed translation.
	 */
	protected function is_not_translated( $id ) {
		/** @var SitePress $sitepress */
		global $sitepress;
		$translation_id = $sitepress->get_object_id( $id, 'post', true, ICL_LANGUAGE_CODE );

		return empty( $translation_id ) || $translation_id == $id;
	}

	/**
	 * Returns a list of post IDs of linked posts for the current language.
	 *
	 * @param array $args An array WP_Query arguments
	 *
	 * @return array An array of linked posts filtered by the current language
	 */
	protected function get_current_language_linked_posts_ids( array $args ) {
		$cache_key = $this->cache->make_key( func_get_args(), 'current_language_linked_post_ids' );
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		/** @var SitePress $sitepress */
		global $sitepress;
		$sitepress->switch_lang( ICL_LANGUAGE_CODE );

		// run the query using the current language (WPML does it under the hood)
		// the user might have posts that are *only* translated and none in the default language
		$query = new WP_Query( $args );

		$linked_post_ids = $query->have_posts() ? $query->posts : array();

		$this->cache[$cache_key] = $linked_post_ids;

		return $linked_post_ids;
	}

	/**
	 * Returns a list of linked post IDs for the default language.
	 *
	 *
	 * @param array $args An array WP_Query arguments
	 *
	 * @return array An array of linked posts filtered by the default language
	 */
	protected function get_default_language_linked_post_ids( array $args ) {
		$cache_key = $this->cache->make_key( func_get_args(), 'default_language_linked_post_ids' );
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		/** @var SitePress $sitepress */
		global $sitepress;

		$sitepress->switch_lang( $sitepress->get_default_language() );

		$query = new WP_Query( $args );

		$posts = $query->have_posts() ? $query->posts : array();

		$sitepress->switch_lang( $sitepress->get_current_language() );

		$not_translated = array_filter( $posts, array( $this, 'is_not_translated' ) );
		$assigned = $this->get_linked_post_assigned_to_current( $args );

		$linked_post_ids = ! empty( $assigned ) ? array_merge( $not_translated, (array) $assigned ) : $not_translated;

		$this->cache[$cache_key] = $linked_post_ids;

		return $linked_post_ids;
	}

	/**
	 * Returns the post ID(s) of post(s) of the type specified in the args linked to the current event.
	 *
	 * @param array $args An array of arguments in the format supported by `WP_Query`
	 *
	 * @return array An array of linked post IDs or an empty array if no post types, more than one post type
	 *               is specified in the args, or the current post is not an event.
	 */
	protected function get_linked_post_assigned_to_current( array $args ) {
		$post_type = (array) Tribe__Utils__Array::get( $args, 'post_type', array() );
		$current_post_id = Tribe__Main::post_id_helper();

		if ( ! tribe_is_event( $current_post_id ) ) {
			return array();
		}

		if ( count( $post_type ) !== 1 || empty( $current_post_id ) ) {
			return array();
		}

		$post_type = reset( $post_type );

		$map = array(
			Tribe__Events__Main::VENUE_POST_TYPE     => '_EventVenueID',
			Tribe__Events__Main::ORGANIZER_POST_TYPE => '_EventOrganizerID',
		);

		if ( empty( $map[ $post_type ] ) ) {
			return array();
		}

		$assigned = get_post_meta( $current_post_id, $map[ $post_type ], false );

		return ! empty( $assigned ) ? $assigned : array();
	}
}
