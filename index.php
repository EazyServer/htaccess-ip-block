<?php
/*
  Plugin Name: Htaccess IP block Map
  Version: 1.0
  Plugin URI:
  Description: Block IPs using .htaccess rather than PHP
  Author: Yarob Al-Taay
  Author URI:
 */

function htaccess_rewrite_map_install() {
	$fileName = get_option('HTACCESS_IP_BLOCK_FILE_MAP_NAME');
	if(empty($fileName)){
		$fileName = HtaccessRewriteMap::generateRandomString();
		add_option('HTACCESS_IP_BLOCK_FILE_MAP_NAME', $fileName.'.txt');
	}
	HtaccessRewriteMap::createSqlTables();
}

register_activation_hook( __FILE__, 'htaccess_rewrite_map_install' );


if ( is_admin() ) {
	new HtaccessRewriteMap();
}

class HtaccessRewriteMap {

	const SQL_TABLE_NAME = 'htaccess_map';

	const MANUAL_IP_BLOCK_NONCE_MSG = 'hrm_Manual_IP_Block';
	const MANUAL_IP_BLOCK_ACTION_NAME = 'hrm_manul_Block_ip';

	const IMPORT_WF_IPS_ACTION_NAME = 'hrm_import_wf_ips';
	const IMPORT_WF_IPS_NONCE_MSG = 'hrm_import_wf_ips_sdjfh';

	/**
	 * Holds the values to be used in the fields callbacks
	 */
	private $options;

	/**
	 * Start up
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
		add_action( 'admin_init', [ $this, 'page_init' ] );
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page() {
		add_options_page( 'HtaccessMap',
			'HtaccessMap',
			'manage_options',
			'HtaccessMap',
			array( $this, 'create_admin_page' ) );
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page() {

		{
			?><h1>.htaccess IP block Map</h1>

			<table class="form-table" style="width: 100%;">

				<tr valign="top">
					<th scope="row">This plugin requires:</th>
					<td>
						<ol>
							<li><b>Advanced user!</b> if you are in doubt do not procced!</li>
							<li>Access to Apache server configuration.</li>
							<li>Add these lines to your <b>apache server configuration</b> (make sure to add to the desginated site (virtual host) not the entire server.)
								<pre style="background: darkgray;padding: 15px;">RewriteEngine On
RewriteMap access txt:<?= get_home_path() . self::getFileName(); ?></pre>
								and these line to your <b>.htaccess file</b> in "<?= get_home_path(); ?>"
								<pre style="background: darkgray;padding: 15px;">RewriteEngine On
RewriteCond ${access:%{REMOTE_ADDR}} deny [NC]
RewriteRule ^ - [L,F]</pre>
								<font color="#8b0000">*If you disable this plugin <b>remember</b> to <b>remove</b> these
									configurations!</font>
							</li>
							<li>Click on "Generate" button to create
								"<?= HtaccessRewriteMap::getFileName() ?>"
								file
							</li>
							<li>Now you can restart apache server so the changes on 3 can take effect.</li>
							<li>Enjoy blocking IPs!</li>
						</ol>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row">Manual IP Block</th>
					<td>
						<input type="text" id="manual_ip" placeholder="xxx.xxx.xxx.xxx"/>
						<input type="button" name="manual_block_button" id="manual_block_button" class="button button-primary" value="Block"/>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row">Generate IP blacklist map</th>
					<td>
						<input type="button" name="generate_map" id="generate_map" class="button button-primary" value="Generate"/><br>
						<pre>This will re-generate "<?= ABSPATH.self::getFileName() ?>" file.</pre>
					</td>
				</tr>

				<?php
				if ( is_plugin_active( 'wordfence/wordfence.php' ) ) {

					?><tr valign="top">
						<th scope="row">Import blocked Ips from Wordfence</th>
						<td>
							<input type="button" name="import_wordfence_ips" id="import_wordfence_ips" class="button button-primary" value="Import"/>
							<span id="number_of_imported_ips"></span>
						</td>
					</tr><?php
				}
				?>

				<tr valign="top">
					<td colspan="2">
						<div id="notif">
							<ul>
								<liIMPORT_WF_IPS_NONCE_MSG>#IPs blacklisted:</liIMPORT_WF_IPS_NONCE_MSG>
							</ul>
						</div>
					</td>
				</tr>
			</table>
			<script>
				jQuery( document ).ready( function () {
					jQuery( '#manual_block_button' ).click( function () {
						manualIpBlock();
					} );

					jQuery( '#import_wordfence_ips' ).click( function () {
						importWordfenceIps();
					} );

				} );

				function manualIpBlock() {
					if ( jQuery( '#manual_ip' ).val() != '' ) {
						jQuery( '#manual_block_button' ).prop( 'disabled', true ).val( 'Blocking...' );

						var post_data = {
							ip: jQuery( '#manual_ip' ).val()
						};

						var data = {
							'action'   : '<?=self::MANUAL_IP_BLOCK_ACTION_NAME?>',
							'nonce'    : '<?=wp_create_nonce( self::MANUAL_IP_BLOCK_NONCE_MSG ); ?>',
							'post_data': JSON.stringify( post_data ),
						};
						jQuery.post( ajaxurl, data, function ( response ) {
							var responseObject = JSON.parse( response );
							console.log( responseObject );
							jQuery( '#manual_block_button' ).prop( 'disabled', false ).val( 'Block' );
						} );
					}
					else {
						alert( 'Please add a valid ip address' );
					}
				}

				function importWordfenceIps() {

						jQuery( '#import_wordfence_ips' ).prop( 'disabled', true ).val( 'Importing...' );

						var data = {
							'action'   : '<?=self::IMPORT_WF_IPS_ACTION_NAME?>',
							'nonce'    : '<?=wp_create_nonce( self::IMPORT_WF_IPS_NONCE_MSG ); ?>',
						};
						jQuery.post( ajaxurl, data, function ( counter ) {
							jQuery( '#import_wordfence_ips' ).prop( 'disabled', false ).val( 'Import' );
							jQuery( '#number_of_imported_ips' ).html( '<b>'+counter+'</b> IP(s) imported successfully from Wordfence.' );
						} );
				}
			</script><?php
		}
	}

	/**
	 * Register and add settings
	 */
	public function page_init() {
		register_setting(
			'my_option_group', // Option group
			'my_option_name', // Option name
			array( $this, 'sanitize' ) // Sanitize
		);

		add_settings_section(
			'setting_section_id', // ID
			'HtaccessMap', // Title
			array( $this, 'print_section_info' ), // Callback
			'WF_To_Htaccess' // Page
		);

		add_settings_field(
			'id_number', // ID
			'ID Number', // Title
			array( $this, 'id_number_callback' ), // Callback
			'HtaccessMap', // Page
			'setting_section_id' // Section
		);

		add_settings_field(
			'title',
			'Title',
			array( $this, 'title_callback' ),
			'HtaccessMap',
			'setting_section_id'
		);
	}

	public static function addIpToBlacklistMap( $ip ) {

		global $wpdb;

		$table_name = $wpdb->prefix . self::SQL_TABLE_NAME;
		$results    = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM " . $table_name . " WHERE ip = %s", $ip
		) );

		if ( ! count( $results ) ) {
			$wpdb->insert( $table_name, array( 'ip' => $ip ), array( '%s' ) );
		}

		$results = $wpdb->get_results( "SELECT ip FROM " . $table_name );

		if ( count( $results ) ) {
			$file_name = get_home_path() . self::getFileName();

			if ( file_exists( $file_name ) ) {
				unlink( $file_name );
			}

			$file = fopen( $file_name, 'w' );

			foreach ( $results as $ipObject ) {
				fwrite( $file, $ipObject->ip . " deny\n" );
			}

			fclose( $file );
		}
	}

	public static function importIpsFromWordfence( ) {

		$wfReport = new wfActivityReport();

		$ips = $wfReport->getTopIPsBlocked( 100000000 );

		$counter = 0;
		foreach ( $ips as $ip ) {

			$ipVal = wfUtils::inet_ntop( $ip->IP );
			self::addIpToBlacklistMap( $ipVal );

			$counter++;
		}
		return $counter;
	}

	public static function getFileName(){
		return get_option('HTACCESS_IP_BLOCK_FILE_MAP_NAME');
	}


	public static function createSqlTables() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . self::SQL_TABLE_NAME;

		$sql = "CREATE TABLE $table_name (
  id mediumint(9) NOT NULL AUTO_INCREMENT,
  ip varchar(16) NOT NULL,
  source varchar(10) DEFAULT '' NOT NULL,
  PRIMARY KEY  (id)
) $charset_collate;";

		dbDelta( $sql );
	}

	public static function generateRandomString($length = 10) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
}

add_action( 'wp_ajax_' . HtaccessRewriteMap::MANUAL_IP_BLOCK_ACTION_NAME, 'hbmBlockIp' );

function hbmBlockIp() {

	if ( ! wp_verify_nonce( $_POST[ 'nonce' ], HtaccessRewriteMap::MANUAL_IP_BLOCK_NONCE_MSG ) ) {
		exit( "No dodgy business please" );
	}

	if ( is_admin() ) {
		$postData = json_decode( stripcslashes( $_POST[ 'post_data' ] ), true );
		HtaccessRewriteMap::addIpToBlacklistMap( $postData[ 'ip' ] );
	}
	exit;
}

add_action( 'wp_ajax_' . HtaccessRewriteMap::IMPORT_WF_IPS_ACTION_NAME, 'hmImportWordfenceIps' );

function hmImportWordfenceIps() {

	if ( ! wp_verify_nonce( $_POST[ 'nonce' ], HtaccessRewriteMap::IMPORT_WF_IPS_NONCE_MSG ) ) {
		exit( "No dodgy business please" );
	}

	if ( is_admin() ) {
		$counter = HtaccessRewriteMap::importIpsFromWordfence();
		return $counter;
	}
	exit;
}