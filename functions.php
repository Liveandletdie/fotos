<?php

// Load Framework //
require_once( dirname(__FILE__) . '/setup.php' );

class baFotosTheme {

	const version = '0.9.1';

	function __construct() {

		// Constants
		$this->url = sprintf('%s', PL_CHILD_URL);
		$this->dir = sprintf('/%s', PL_CHILD_DIR);

		// Includes
		include('libs/custom-meta-boxes/custom-meta-boxes.php' );
		include('inc/contact.php');
		include('inc/unset.php');
		include('inc/support-tab.php');
		include('inc/meta.php');
		include('inc/options.php');
		include('inc/post.php');
		include('inc/partials.php');
		include('inc/gallery.php');
		include('inc/fotos-shortcodes.php');

		define( 'BA_FOTOS_STORE_URL', 'http://nickhaskins.co' );
		define( 'BA_FOTOS_THEME_NAME', 'Fotos' );

		// Load Updates API
		if ( !class_exists( 'EDD_SL_Theme_Updater' ) ) {
			include( dirname( __FILE__ ) . '/EDD_SL_Theme_Updater.php' );
		}


		$this->init();

	}

	// Initialize
	function init() {

		// bypass posix check
		add_filter( 'render_css_posix_', '__return_true' );

		// Fotos Utilities
		add_action( 'wp_enqueue_scripts',array($this,'scripts'));

		// Get license Key
		$license = trim( get_option( 'ba_fotos_license_key' ) );

		// Pass to Updater
		$edd_updater = new EDD_SL_Theme_Updater( array(
				'remote_api_url' 	=> BA_FOTOS_STORE_URL,
				'version' 			=> self::version,
				'license' 			=> $license,
				'item_name' 		=> BA_FOTOS_THEME_NAME,
				'author'			=> 'Nick Haskins'
			)
		);

		// Updater Filters
		add_action('admin_menu', array($this,'license_menu'));
		add_action('admin_init', array($this,'register_option'));
		add_action('admin_init', array($this,'activate_license'));
		add_action('admin_init', array($this,'deactivate_license'));

	}


	function scripts(){

		wp_register_script('fotos', PL_CHILD_URL.'/assets/js/fotos.js', array('jquery'), self::version, true);
		wp_enqueue_script('fotos');
	}


	function license_menu() {
		add_theme_page( 'Fotos License', 'Fotos License', 'manage_options', 'fotos-license', array($this,'license_page' ));
	}

	function license_page() {
		$license 	= get_option( 'ba_fotos_license_key' );
		$status 	= get_option( 'ba_fotos_license_key_status' );
		?>
		<div class="wrap">
			<h2><?php _e('Fotos License','fotos'); ?></h2>
			<form method="post" action="options.php">

				<?php settings_fields('ba_fotos_theme_license'); ?>

				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row" valign="top">
								<?php _e('License Key'); ?>
							</th>
							<td>
								<input id="ba_fotos_license_key" name="ba_fotos_license_key" type="text" class="regular-text" value="<?php esc_attr( $license ); ?>" />
								<label class="description" for="ba_fotos_license_key"><?php _e('Enter your license key to enable automatic updates.','fotos'); ?></label>
							</td>
						</tr>
						<?php if( false !== $license ) { ?>
							<tr valign="top">
								<th scope="row" valign="top">
									<?php _e('Activate License'); ?>
								</th>
								<td>
									<?php if( $status !== false && $status == 'valid' ) { ?>
										<span style="color:green;"><?php _e('active'); ?></span>
										<?php wp_nonce_field( 'ba_fotos_nonce', 'ba_fotos_nonce' ); ?>
										<input type="submit" class="button-secondary" name="ba_fotos_license_deactivate" value="<?php _e('Deactivate License','fotos'); ?>"/>
									<?php } else {
										wp_nonce_field( 'ba_fotos_nonce', 'ba_fotos_nonce' ); ?>
										<input type="submit" class="button-secondary" name="ba_fotos_license_activate" value="<?php _e('Activate License','fotos'); ?>"/>
									<?php } ?>
								</td>
							</tr>
						<?php } ?>
					</tbody>
				</table>
				<?php submit_button(); ?>

			</form>
		<?php
	}

	function register_option() {
		// creates our settings in the options table
		register_setting('ba_fotos_theme_license', 'ba_fotos_license_key', array($this,'sanitize_license' ));
	}

	function sanitize_license( $new ) {
		$old = get_option( 'ba_fotos_license_key' );
		if( $old && $old != $new ) {
			delete_option( 'ba_fotos_license_key_status' ); // new license has been entered, so must reactivate
		}
		return $new;
	}

	function activate_license() {

		if( isset( $_POST['ba_fotos_license_activate'] ) ) {
		 	if( ! check_admin_referer( 'ba_fotos_nonce', 'ba_fotos_nonce' ) )
				return; // get out if we didn't click the Activate button

			global $wp_version;

			$license = trim( get_option( 'ba_fotos_license_key' ) );

			$api_params = array(
				'edd_action' => 'activate_license',
				'license' => $license,
				'item_name' => urlencode( BA_FOTOS_THEME_NAME )
			);

			$response = wp_remote_get( add_query_arg( $api_params, BA_FOTOS_STORE_URL ), array( 'timeout' => 15, 'sslverify' => false ) );

			if ( is_wp_error( $response ) )
				return false;

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			// $license_data->license will be either "active" or "inactive"

			update_option( 'ba_fotos_license_key_status', $license_data->license );

		}
	}

	function deactivate_license() {

		// listen for our activate button to be clicked
		if( isset( $_POST['ba_fotos_license_deactivate'] ) ) {

			// run a quick security check
		 	if( ! check_admin_referer( 'ba_fotos_nonce', 'ba_fotos_nonce' ) )
				return; // get out if we didn't click the Activate button

			// retrieve the license from the database
			$license = trim( get_option( 'ba_fotos_license_key' ) );


			// data to send in our API request
			$api_params = array(
				'edd_action'=> 'deactivate_license',
				'license' 	=> $license,
				'item_name' => urlencode( BA_FOTOS_THEME_NAME ) // the name of our product in EDD
			);

			// Call the custom API.
			$response = wp_remote_get( add_query_arg( $api_params, BA_FOTOS_STORE_URL ), array( 'timeout' => 15, 'sslverify' => false ) );

			// make sure the response came back okay
			if ( is_wp_error( $response ) )
				return false;

			// decode the license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			// $license_data->license will be either "deactivated" or "failed"
			if( $license_data->license == 'deactivated' )
				delete_option( 'ba_fotos_license_key_status' );

		}
	}

	function edd_sample_theme_check_license() {

		global $wp_version;

		$license = trim( get_option( 'ba_fotos_license_key' ) );

		$api_params = array(
			'edd_action' => 'check_license',
			'license' => $license,
			'item_name' => urlencode( BA_FOTOS_THEME_NAME )
		);

		$response = wp_remote_get( add_query_arg( $api_params, BA_FOTOS_STORE_URL ), array( 'timeout' => 15, 'sslverify' => false ) );

		if ( is_wp_error( $response ) )
			return false;

		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		if( $license_data->license == 'valid' ) {
			echo 'valid'; exit;
			// this license is still valid
		} else {
			echo 'invalid'; exit;
			// this license is no longer valid
		}
	}
}

new baFotosTheme;