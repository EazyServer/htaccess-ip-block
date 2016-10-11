<?php
/*
  Plugin Name: &lt;.htaccess&gt; IP block
  Version: 1.0
  Plugin URI:
  Description: IPs blocking using .htaccess Not PHP! Blocking IPs at Apache level will reduce the load on php!
  Author: Yarob Al-Taay
  Author URI:
 */

define( 'HTACCESS_IP_BLOCK_PATH', plugin_dir_path( __FILE__ ) );


if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( HTACCESS_IP_BLOCK_PATH . 'inc/IpListTable.php' );
}

function htaccess_ip_block_install() {
	$fileName = get_option( 'HTACCESS_IP_BLOCK_FILE_MAP_NAME' );
	if ( empty( $fileName ) ) {
		$fileName = HtaccessIpBlock::generateRandomString();
		add_option( 'HTACCESS_IP_BLOCK_FILE_MAP_NAME', $fileName . '.txt' );
	}
	HtaccessIpBlock::createSqlTables();
	HtaccessIpBlock::createHtaccessMapFile();

}

register_activation_hook( __FILE__, 'htaccess_ip_block_install' );


class HtaccessIpBlock {

	const SQL_TABLE_NAME = 'htaccess_map';

	const MANUAL_IP_BLOCK_NONCE_MSG = 'hrm_Manual_IP_Block';
	const MANUAL_IP_BLOCK_ACTION_NAME = 'hrm_manul_Block_ip';

	const IMPORT_WF_IPS_ACTION_NAME = 'hrm_import_wf_ips';
	const IMPORT_WF_IPS_NONCE_MSG = 'hrm_import_wf_ips_sdjfh';


	public function __construct() {
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', [ $this, 'add_plugin_page_network' ] );
		} else {
			add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
		}

		add_action( 'admin_init', [ $this, 'page_init' ] );
	}

	public function add_plugin_page() {
		add_menu_page( '&lt;.htaccess&gt; IP Block',
			'<.ht> IP Block',
			'manage_options',
			'htaccess_ip_block',
			array( $this, 'create_admin_page' ) );
	}

	public function add_plugin_page_network() {
		add_menu_page( '.htaccess IP Block',
			'<.ht> IP Block',
			'manage_network',
			'htaccess_ip_block',
			array( $this, 'create_admin_page' ) );
	}

	public function create_admin_page() {

		{
			?><h1><.htaccess> IP block</h1>

			<table class="form-table" style="width: 100%;">

				<tr valign="top">
					<th scope="row">This plugin requires:</th>
					<td>
						<ol>
							<li><b>Advanced user!</b> if you are in doubt consult a competent user!</li>
							<li>Access to Apache server configuration.</li>
							<?php if ( ! is_writable( get_home_path() . self::getFileName() ) ) {
								?>
								<li>You need to create this file "<?= get_home_path() . self::getFileName(); ?>"
								manually before proceeding.</li><?php
							}
							?>
							<li>Add these lines to your <b>apache server configuration</b>
<pre style="background: darkgray;padding: 15px;">RewriteEngine On
RewriteMap deny txt:<?= get_home_path() . self::getFileName(); ?></pre>
								<font color="#8b0000">*If you disable the plugin <b>remember</b> to <b>remove</b> these
									configurations!</font>
							</li>
							<li>Add these line to your <b>.htaccess file</b> in <b>"<?= get_home_path(); ?>"</b>
<pre style="background: darkgray;padding: 15px;"># BEGIN .htaccess IP block plugin
&lt;IfModule mod_rewrite.c&gt;
RewriteEngine On
RewriteCond ${deny:%{REMOTE_ADDR}} deny [NC]
RewriteRule ^ - [L,F]
&lt;/IfModule&gt;
# END .htaccess IP block plugin</pre>
							</li>
							<li>Now restart apache server so the changes on 3 takes effect.</li>
							<li>Enjoy blocking IPs!</li>
						</ol>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row">Manual IP Block</th>
					<td>
						<input type="text" id="manual_ip" placeholder="xxx.xxx.xxx.xxx"/>
						<input type="button" name="manual_block_button" id="manual_block_button" class="button button-primary" value="Block"/>
						<br>
						<?php
						if ( is_plugin_active( 'wordfence/wordfence.php' )
						     and method_exists(new wfLog(null, null), 'blockIP') ) {
							?>
							<input type="checkbox" name="block_on_wordfence" id="block_on_wordfence" value="block" checked/>Block on Wordfence too<?php
						}
						?>
						<br>
						<span id="status_of_manual_block"></span>
					</td>
				</tr>

				<?php
				if ( is_plugin_active( 'wordfence/wordfence.php' )
				and method_exists(new wfActivityReport(), 'getTopIPsBlocked') ) {

					?>
					<tr valign="top">
						<th scope="row">Import from Wordfence</th>
						<td>
							<input type="button" name="import_wordfence_ips" id="import_wordfence_ips" class="button button-primary" value="Import"/>
							<br>
							<span id="number_of_imported_ips"></span>
							<br>
							<span><i>*This imports top blocked IPs from wordfence and add them to .htaccess IP block</i></span>

						</td>
					</tr><?php
				}
				?>

				<tr valign="top">
					<td colspan="2">
						<div id="notif">
							<ul>
								<form id="htaccess-ip-block-list-table-form" method="post">
									<?php
									$wp_list_table = new IpListTable();
									$wp_list_table->prepare_items();
									$wp_list_table->display();
									?>
								</form>
							</ul>
						</div>
					</td>
				</tr>
			</table>
			<?php

			require_once( HTACCESS_IP_BLOCK_PATH . 'inc/jquery.php' );
		}
	}

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

	public static function writeIPsMap() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::SQL_TABLE_NAME;
		$results    = $wpdb->get_results( "SELECT ip FROM " . $table_name );

		$file_name = get_home_path() . self::getFileName();

		if ( file_exists( $file_name ) ) {
			unlink( $file_name );
		}

		$file = fopen( $file_name, 'w' );

		if ( count( $results ) ) {
			foreach ( $results as $ipObject ) {
				fwrite( $file, $ipObject->ip . " deny\n" );
			}
		}

		fclose( $file );
	}

	public static function addIpToBlacklistMap( $ip ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::SQL_TABLE_NAME;
		$results    = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM " . $table_name . " WHERE ip = %s", $ip
		) );

		if ( empty( $results ) ) {
			$wpdb->insert( $table_name, array( 'ip' => $ip ), array( '%s' ) );

			return 1; // add ip to the map
		}

		return - 1; // ip already in the map
	}

	public static function deleteIpFromBlacklistMap( $id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::SQL_TABLE_NAME;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM " . $table_name . " WHERE id = %s", $id
		) );

		if ( ! empty( $results ) ) {
			$wpdb->delete( $table_name, array( 'id' => $id ) );

			return 1; // ip deleted from the map
		}

		return - 1; // ip not in the map!
	}

	public static function importIpsFromWordfence() {

		$wfReport = new wfActivityReport();

		$ips = $wfReport->getTopIPsBlocked( 100000000 );

		$counter = 0;
		foreach ( $ips as $ip ) {

			$ipVal = wfUtils::inet_ntop( $ip->IP );
			self::addIpToBlacklistMap( $ipVal );

			$counter ++;
		}
		self::writeIPsMap();

		return $counter;
	}

	public static function getFileName() {
		return get_option( 'HTACCESS_IP_BLOCK_FILE_MAP_NAME' );
	}

	public static function createHtaccessMapFile() {
		$file_name = get_home_path() . self::getFileName();
		if ( is_writable( $file_name ) ) {
			$file = fopen( $file_name, 'w' );
			fclose( $file );
		} else {
			echo $file_name . 'is not writable! try creating the file manually!';
		}
	}

	public static function createSqlTables() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . self::SQL_TABLE_NAME;

		$sql = "CREATE TABLE $table_name (
  `id` mediumint(9) NOT NULL AUTO_INCREMENT,
  `ip` varchar(16) NOT NULL,
  `source` varchar(10) DEFAULT '' NOT NULL,
  `date_added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id)
) $charset_collate;";

		dbDelta( $sql );
	}

	public static function generateRandomString( $length = 10 ) {
		$characters       = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen( $characters );
		$randomString     = '';
		for ( $i = 0; $i < $length; $i ++ ) {
			$randomString .= $characters[ rand( 0, $charactersLength - 1 ) ];
		}

		return $randomString;
	}
}

require_once( HTACCESS_IP_BLOCK_PATH . 'inc/ajax.php' );


if ( is_admin() ) {
	new HtaccessIpBlock();
}
?>
