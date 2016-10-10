<?php
/*
  Plugin Name: Htaccess IP block
  Version: 1.0
  Plugin URI:
  Description: Block IPs using .htaccess rather than PHP
  Author: Yarob Al-Taay
  Author URI:
 */

if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

function htaccess_ip_block_install() {
	$fileName = get_option('HTACCESS_IP_BLOCK_FILE_MAP_NAME');
	if(empty($fileName)){
		$fileName = HtaccessIpBlock::generateRandomString();
		add_option('HTACCESS_IP_BLOCK_FILE_MAP_NAME', $fileName.'.txt');
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

	/**
	 * Holds the values to be used in the fields callbacks
	 */
	private $options;

	/**
	 * Start up
	 */
	public function __construct() {
		if(is_multisite()){
			add_action('network_admin_menu', [$this, 'add_plugin_page_network']);
		}
		else {
			add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
		}

		add_action( 'admin_init', [ $this, 'page_init' ] );
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page() {
		add_menu_page( '.htaccess IP Block',
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

	/**
	 * Options page callback
	 */
	public function create_admin_page() {

		{
			?><h1><.htaccess> IP block Map</h1>

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
							<?php if(!is_writable(get_home_path() . self::getFileName())) {
								?><li>You need to create this file "<?= get_home_path() . self::getFileName(); ?>" manually.</li><?php
							}
							?>
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
						<span id="status_of_manual_block"></span>
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
								<?php
								$wp_list_table = new IpListTable();
								$wp_list_table->prepare_items();
								$wp_list_table->display();
								?>
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
							jQuery( '#manual_block_button' ).prop( 'disabled', false ).val( 'Block' );
							if(response == 1) {
								jQuery( '#status_of_manual_block' ).html(jQuery( '#manual_ip' ).val()+' blocked successfully.');
							}
							else if(response == -1) {
								jQuery( '#status_of_manual_block' ).html(jQuery( '#manual_ip' ).val()+' already blocked!');
							}
							jQuery( '#manual_ip' ).val('');
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

	public static function writeIPsMap() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::SQL_TABLE_NAME;
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
		return -1; // ip already in the map
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
		self::writeIPsMap();
		return $counter;
	}

	public static function getFileName(){
		return get_option('HTACCESS_IP_BLOCK_FILE_MAP_NAME');
	}

	public static function createHtaccessMapFile() {
		$file_name = get_home_path() . self::getFileName();
		if(is_writable($file_name)) {
			$file = fopen( $file_name, 'w' );
			fclose( $file );
		}
		else {
			echo $file_name. 'is not writable! try creating the file manually!';
		}
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


class IpListTable extends WP_List_Table
{
	const SQL_TABLE_NAME = 'htaccess_map';
	/**
	 * Prepare the items for the table to process
	 *
	 * @return Void
	 */
	public function prepare_items()
	{
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$data = $this->table_data();
		usort( $data, array( &$this, 'sort_data' ) );

		$perPage = 20;
		$currentPage = $this->get_pagenum();
		$totalItems = count($data);

		$this->set_pagination_args( array(
			'total_items' => $totalItems,
			'per_page'    => $perPage
		) );

		$data = array_slice($data,(($currentPage-1)*$perPage),$perPage);

		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $data;
	}

	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return Array
	 */
	public function get_columns()
	{
		$columns = array(
			'id'          => 'ID',
			'ip'       => 'IP address',
		);

		return $columns;
	}

	/**
	 * Define which columns are hidden
	 *
	 * @return Array
	 */
	public function get_hidden_columns()
	{
		return array();
	}

	/**
	 * Define the sortable columns
	 *
	 * @return Array
	 */
	public function get_sortable_columns()
	{
		return array(
			'id' => array('id', false),
			'ip' => array('ip', false)
		);
	}

	/**
	 * Get the table data
	 *
	 * @return Array
	 */
	private function table_data()
	{
		$data = array();

		global $wpdb, $_wp_column_headers;

		$query = 'SELECT * FROM '.$wpdb->prefix.self::SQL_TABLE_NAME;
		$data = $wpdb->get_results($query , ARRAY_A);

		return $data;
	}

	/**
	 * Define what data to show on each column of the table
	 *
	 * @param  Array $item        Data
	 * @param  String $column_name - Current column name
	 *
	 * @return Mixed
	 */
	public function column_default( $item, $column_name )
	{
		switch( $column_name ) {
			case 'id':
			case 'ip':
				return $item[ $column_name ];

			default:
				return print_r( $item, true ) ;
		}
	}



	/**
	 * Allows you to sort the data by the variables set in the $_GET
	 *
	 * @return Mixed
	 */
	private function sort_data( $a, $b )
	{
		// Set defaults
		$orderby = 'id';
		$order = 'asc';

		// If orderby is set, use this as the sort column
		if(!empty($_GET['orderby']))
		{
			$orderby = $_GET['orderby'];
		}

		// If order is set use this as the order
		if(!empty($_GET['order']))
		{
			$order = $_GET['order'];
		}


		$result = strnatcmp( $a[$orderby], $b[$orderby] );

		if($order === 'asc')
		{
			return $result;
		}

		return -$result;
	}
}



add_action( 'wp_ajax_' . HtaccessIpBlock::MANUAL_IP_BLOCK_ACTION_NAME, 'hbmBlockIp' );

function hbmBlockIp() {

	if ( ! wp_verify_nonce( $_POST[ 'nonce' ], HtaccessIpBlock::MANUAL_IP_BLOCK_NONCE_MSG ) ) {
		exit( "No dodgy business please" );
	}

	if ( is_admin() ) {
		$postData = json_decode( stripcslashes( $_POST[ 'post_data' ] ), true );
		$flag = HtaccessIpBlock::addIpToBlacklistMap( $postData[ 'ip' ] );
		HtaccessIpBlock::writeIPsMap();
		echo $flag;
	}
	exit(0);
}

add_action( 'wp_ajax_' . HtaccessIpBlock::IMPORT_WF_IPS_ACTION_NAME, 'hmImportWordfenceIps' );

function hmImportWordfenceIps() {

	if ( ! wp_verify_nonce( $_POST[ 'nonce' ], HtaccessIpBlock::IMPORT_WF_IPS_NONCE_MSG ) ) {
		exit( "No dodgy business please" );
	}

	if ( is_admin() ) {
		$counter = HtaccessIpBlock::importIpsFromWordfence();
		echo $counter;
	}
	exit(0);
}

if ( is_admin() ) {
	new HtaccessIpBlock();
}