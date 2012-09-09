<?php
/**
 * Forking administrative functions
 * @package fork
 */

class Fork_Admin {

	/**
	 * Hook into WordPress API on init
	 */
	function __construct( &$parent ) {

		$this->parent = &$parent;
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_init', array( $this, 'fork_callback' ) );
		add_action( 'admin_init', array( $this, 'merge_callback' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_ajax_fork', array( $this, 'ajax' ) );
		add_action( 'admin_ajax_fork_merge', array( $this, 'ajax' ) );

	}


	/**
	 * Add metaboxes to post edit pages
	 */
	function add_meta_boxes() {
		global $post;

		if ( $post->post_status == 'auto-draft' )
			return;

		foreach ( $this->parent->get_post_types() as $post_type => $status )
			add_meta_box( 'fork', 'Fork', array( $this, 'post_meta_box' ), $post_type, 'side', 'high' );

		add_meta_box( 'fork', 'Fork', array( $this, 'fork_meta_box' ), 'fork', 'side', 'high' );

	}


	/**
	 * Callback to listen for the primary fork action
	 */
	function fork_callback() {

		if ( !isset( $_GET['fork'] ) )
			return;

		$fork = $this->parent->fork( (int) $_GET['fork'] );

		if ( !$fork )
			return;

		wp_redirect( admin_url( "post.php?post=$fork&action=edit" ) );
		exit();

	}


	/**
	 * Callback to listen for the primary merge action
	 */
	function merge_callback() {

		if ( !isset( $_GET['merge'] ) )
			return;

		$this->parent->merge->merge( (int) $_GET['merge'] );

		exit();

	}


	/**
	 * Callback to render post meta box
	 */
	function post_meta_box( $post ) {

		$this->parent->branches->branches_dropwdown( $post );

		if ( $this->parent->branches->can_branch( $post ) )
			$this->parent->template( 'author-post-meta-box', compact( 'post' ) );

		else
			$this->parent->template( 'post-meta-box', compact( 'post' ) );

	}


	/**
	 * Callback to render fork meta box
	 */
	function fork_meta_box( $post ) {

		$parent = $this->parent->revisions->get_previous_revision( $post );

		$this->parent->template( 'fork-meta-box', compact( 'post', 'parent' ) );
	}


	/**
	 * Registers update messages
	 * @param array $messages messages array
	 * @returns array messages array with fork messages
	 */
	function update_messages( $messages ) {
		global $post, $post_ID;

		$messages['fork'] = array(
			1 => __( 'Fork updated.', 'fork' ),
			2 => __( 'Custom field updated.', 'fork' ),
			3 => __( 'Custom field deleted.', 'fork' ),
			4 => __( 'Fork updated.', 'fork' ),
			5 => isset($_GET['revision']) ? sprintf( __( 'Fork restored to revision from %s', 'fork' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => __( 'Fork published. <a href="%s">Download Fork</a>', 'fork' ),
			7 => __( 'Fork saved.', 'fork' ),
			8 => __( 'Fork submitted.', 'fork' ),
			9 => __( 'Fork scheduled for:', 'fork' ),
			10 => __( 'Fork draft updated.', 'fork' ),
		);

		return $messages;
	}


	/**
	 * Enqueue javascript and css assets on backend
	 */
	function enqueue() {

		$post_types = $this->parent->get_post_types( true );
		$post_types[] = 'fork';

		if ( !in_array( get_current_screen()->post_type, $post_types ) )
			return;

		//js
		$suffix = ( WP_DEBUG ) ? '.dev' : '';
		wp_enqueue_script( 'post-forking', plugins_url( "/js/admin{$suffix}.js", dirname( __FILE__ ) ), 'jquery', $this->parent->version, true );

		//css
		wp_enqueue_style( 'post-forking', plugins_url( '/css/admin.css', dirname( __FILE__ ) ), null, $this->parent->version );

	}


	/**
	 * Add additional actions to the post row view
	 */
	function row_actions( $actions, $post ) {

		$label = ( $this->parent->branches->can_branch ( $post ) ) ? __( 'Create new branch', 'fork' ) : __( 'Fork', 'fork' );

		if ( get_post_type( $post ) != 'fork' )
			$actions[] = '<a href="' . admin_url( "?fork={$post->ID}" ) . '">' . $label . '</a>';

		$parent = $this->parent->revisions->get_previous_revision( $post );

		if ( get_post_type( $post ) == 'fork' )
			$actions[] = '<a href="' . admin_url( "revision.php?action=diff&left={$parent}&right={$post->ID}" ) . '">' . __( 'Compare', 'fork' ) . '</a>';

		return $actions;

	}


	/**
	 * Callback to handle ajax forks
	 * Note: Will output 0 on failure,
	 */
	function ajax() {

		foreach ( array( 'post', 'author', 'action' ) as $var )
			$$var = ( isset( $_GET[$var] ) ) ? $_GET[$var] : null;

		if ( $action == 'merge' )
			$result = $this->parent->merge->merge( $post, $author );
		else
			$result = $this->parent->fork( $post, $author );

		if ( $result == false )
			$result = -1;

		die( $result );

	}



}