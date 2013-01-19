<?php
/*
 Plugin Name: Old Core Files
 Plugin URI: http://www.wp-tricks.co.il/cool-slug-goes-here
 Description: Old Core Files notifies the user when old core files which are due removal exist in the filesystem
 Author: Maor Chasen, Rami Yushuvaev
 Author URI: http://maorchasen.com
 Version: 1.0
 License: GPL2+
 */

/**
 * Description:
 * 
 * When core is being upgraded, usually some files are no longer used by WP, and they are set for removal.
 * On some occasions, PHP has no permissions to delete these files, and they stay on the server, possibly
 * exposing your site to attackers.
 *
 * Optional Features TODO:
 *
 * - Email administrator when old files were detected 
 * 	(most probably will happen right after an upgrade)
 */

/**
 * Base OCF class.
 *
 * @since 1.0
 */
class Old_Core_Files {

	private $page;
	private $parent_slug = 'tools.php';
	private $page_slug = 'old-core-files';
	private $view_cap = 'manage_options';

	/**
	 * Hold on to your seats!
	 *
	 * @since 1.0
	 */
	function __construct() {
		// This plugin only runs in the admin, but we need it initialized on init
		add_action( 'init', array( $this, 'action_init' ) );

		/**
		 * @todo load plugin textdomain, is there a need for de/activation hooks?
		 */
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	function action_init() {
		if ( ! is_admin() )
			return;
		
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		// Allow the view to be placed elsewhere than tools.php
		$this->parent_slug = apply_filters( 'ocf_parent_slug', $this->parent_slug );
		// Hijack the default capability for viewing the page
		$this->view_cap = apply_filters( 'ocf_view_cap', $this->view_cap );
	}

	/**
	 * Register menu item for OCF
	 *
	 * @since 1.0
	 */
	function admin_menu() {
		$this->page = add_submenu_page( $this->parent_slug, __( 'Old Core Files', 'oc-files' ), __( 'Old Core Files', 'core-control' ), $this->view_cap, $this->page_slug, array( $this, 'dashboard_page' ) );

		// Add callbacks for this screen only 
		add_action( "load-$this->page", array( $this, 'page_actions' ), 9 );
		add_action( "admin_footer-$this->page", array( $this,'footer_scripts' ) );
	}

	/**
	 * Triggers rendering of our metaboxes plus some layout configuration 
	 *
	 * @since 1.0
	 */
	function page_actions() {
		do_action( "add_meta_boxes_$this->page", null );
		do_action( 'add_meta_boxes', $this->page, null );

		// User can choose between 1 or 2 columns (default 2) 
		add_screen_option( 'layout_columns', array(
			'max' 		=> 2, 
			'default' 	=> 2 
		) );

		// Enqueue WordPress' postbox script for handling the metaboxes 
		wp_enqueue_script( 'postbox' );
	}

	/**
	 * Prints the jQuery script to initiliaze the metaboxes
	 * Called on admin_footer-*
	 *
	 * @since 1.0
	*/
	function footer_scripts() {
		?>
		<script>postboxes.add_postbox_toggles( pagenow );</script>
		<?php
	}

	/**
	 * Add our metaboxes.
	 *
	 * @since 1.0
	 */
	function add_meta_boxes() {
		add_meta_box( 'list-files', __( 'Old Core Files', 'oc-files' ), array( $this, 'metabox_list_files' ), $this->page, 'normal', 'high' );
		add_meta_box( 'about', __( 'About', 'oc-files' ), array( $this, 'metabox_about' ), $this->page, 'side', 'high' );
	}

	/**
	 * Magic happens right here.
	 *
	 * @since 1.0
	 */
	function metabox_list_files() {
		global $wp_filesystem, $wp_version, $_old_files;

		// Require the file that stores $_old_files
		require_once ABSPATH . 'wp-admin/includes/update-core.php';

		// If $wp_filesystem isn't there, make it be there!
		if ( ! $wp_filesystem )
			WP_Filesystem();

		// Not sure why I had to add this. Maybe shuffling through the filesystem
		// can take up more time than usual. Thought maybe we should cache the results
		// but there is really no point in doing so, since data must be real-time.
		@set_time_limit( 300 );

		$path_to_wp = trailingslashit( $wp_filesystem->abspath() );

		$existing_old_files = array();

		// Pile up old, existing files
		foreach ( $_old_files as $old_file ) {
			if ( $wp_filesystem->exists( $path_to_wp . $old_file ) )
				$existing_old_files[] = $old_file;
		}

		if ( ! empty( $existing_old_files ) ) :
			?>
			<p><?php esc_html_e( 'We have found some old files in this WordPress installation. Please review the files below.', 'oc-files' ); ?></p>
			<table class="widefat post-revisions" cellspacing="0">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'File', 'oc-files' ); ?></th>
						<th scope="col" class="action-links"><?php esc_html_e( 'Actions', 'oc-files' ); ?></th>
					</tr>
				</thead>
				<tbody>
			<?php foreach ( $existing_old_files as $existing_file ) : ?>
					<tr>
						<td><code><?php echo esc_html( $existing_file ); ?></code></td>
					</tr>
			<?php endforeach; ?>
				</tbody>
			</table><?php
		else: ?>
			<p><?php esc_html_e( 'Seems like there are no old files in your installation. Hooray!', 'oc-files' ); ?></p>
			<?php
		endif;
	}

	function metabox_about() {
		?>
		<h4><?php esc_html_e( 'What is this about?', 'oc-files' ); ?></h4>
		<p><?php esc_html_e( 'When core is being upgraded, usually some files are no longer used by WP, and they are set for removal. On some occasions, PHP has no permissions to delete these files, and they stay on the server, possibly exposing your site to attackers.', 'oc-files' ); ?></p>
		<?php
	}

	/**
	 * Main dashboard page. Can be found under the "tools" menu.
	 *
	 * @since 1.0
	 */
	function dashboard_page() {
		?>
		<div class="wrap">

			<?php screen_icon(); ?>
			<h2><?php echo esc_html__( 'Old Core Files', 'oc-files' ); ?></h2>
			
			<form name="oldfiles" method="post">
				<input type="hidden" name="action" value="some-action">
				<?php wp_nonce_field( 'some-action-nonce' );

				// Used for saving metaboxes state (close/open) and their order 
				wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
				wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>

				<div id="poststuff">
		
					<div id="post-body" class="metabox-holder columns-<?php echo 1 == get_current_screen()->get_columns() ? '1' : '2'; ?>"> 

						<!-- We had the description of the plugin here, moved to the metabox.
						<div id="post-body-content"></div> -->

						<div id="postbox-container-1" class="postbox-container">
							<?php do_meta_boxes( '', 'side', null ); ?>
						</div>

						<div id="postbox-container-2" class="postbox-container">
							<?php do_meta_boxes( '', 'normal', null ); ?>
							<?php do_meta_boxes( '', 'advanced', null ); ?>
						</div>

					</div> <!-- #post-body -->
				
				 </div> <!-- #poststuff -->

			</form><!-- #oldfiles -->

		 </div><!-- .wrap -->
	<?php
	}

	function activate() {
		// Nothing to do here right now
	}
}
global $old_core_files_instance;
$old_core_files_instance = new Old_Core_Files;