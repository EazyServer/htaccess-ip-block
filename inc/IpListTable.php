<?php

/**
 * Created by yarob with PhpStorm.
 * Date: 10/10/16
 * Time: 11:26
 */

if(!class_exists('WP_List_Table')){
	require_once( HTACCESS_IP_BLOCK_PATH . 'inc/WP_List_Table_Htaccess_Ip_Block.php' );
}

if(!class_exists('HtaccessIpBlock')){
	require_once( HTACCESS_IP_BLOCK_PATH . 'index.php' );
}

class IpListTable extends WP_List_Table_Htaccess_Ip_Block
{
	const SQL_TABLE_NAME = 'htaccess_map';
	/**
	 * Prepare the items for the table to process
	 *
	 * @return Void
	 */

	function __construct(){
		global $status, $page;

		//Set parent defaults
		parent::__construct( array(
			'singular'  => 'IP',     //singular name of the listed records
			'plural'    => 'IPs',    //plural name of the listed records
			'ajax'      => false        //does this table support ajax?
		) );
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
			case 'date_added':
				return $item[ $column_name ];

			default:
				return print_r( $item, true ) ;
		}
	}

	function column_ip($item){

		//Build row actions
		$actions = array(
			//'edit'      => sprintf('<a href="?page=%s&action=%s&movie=%s">Edit</a>',$_REQUEST['page'],'edit',$item['ID']),
			'delete'    => sprintf('<a href="?page=%s&action=%s&id=%s">Delete</a>',$_REQUEST['page'],'delete',$item['id']),
		);

		//Return the title contents
		return sprintf('%1$s <span style="color:silver">(ID:%2$s)</span>%3$s',
			/*$1%s*/ $item['ip'],
			/*$2%s*/ $item['id'],
			/*$3%s*/ $this->row_actions($actions)
		);
	}

	function column_cb($item){
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			/*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
			/*$2%s*/ $item['id']                //The value of the checkbox should be the record's id
		);
	}

	function get_bulk_actions() {
		$actions = array(
			'delete'    => 'Delete'
		);
		return $actions;
	}

	function process_bulk_action() {

		if( $this->current_action() === 'delete' ) {
			if(!empty(intval($_GET['id']))) {
				HtaccessIpBlock::deleteIpFromBlacklistMap(intval($_GET['id']));
				HtaccessIpBlock::writeIPsMap();
			}
		}
	}

	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return Array
	 */
	public function get_columns()
	{
		$columns = array(
			//'id'          => 'ID',
			'cb'          => 'Select',
			'ip'       => 'IP address',
			'date_added'       => 'Date/Time added',
		);

		return $columns;
	}

	/**
	 * Define the sortable columns
	 *
	 * @return Array
	 */
	public function get_sortable_columns() {
		return array(
			'date_added' => array('date_added', false),
			'ip' => array('ip', false)
		);
	}

	public function prepare_items()
	{
		$perPage = 20;

		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();


		$this->_column_headers = array($columns, $hidden, $sortable);


		$this->process_bulk_action();

		$data = $this->table_data();
		usort( $data, array( &$this, 'sort_data' ) );

		$currentPage = $this->get_pagenum();

		$totalItems = count($data);

		$data = array_slice($data,(($currentPage-1)*$perPage),$perPage);

		$this->items = $data;

		$this->set_pagination_args( array(
			'total_items' => $totalItems,
			'per_page'    => $perPage,
			'total_pages' => ceil($totalItems/$perPage)
		) );

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