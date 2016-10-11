<?php
/**
 * Created by yarob with PhpStorm.
 * Date: 10/10/16
 * Time: 12:05
 */

add_action( 'wp_ajax_' . HtaccessIpBlock::MANUAL_IP_BLOCK_ACTION_NAME, 'htaccessBlockIp' );

function htaccessBlockIp() {

	if ( ! wp_verify_nonce( $_POST[ 'nonce' ], HtaccessIpBlock::MANUAL_IP_BLOCK_NONCE_MSG ) ) {
		exit( "No dodgy business please" );
	}

	if ( is_admin() ) {
		$postData = json_decode( stripcslashes( $_POST[ 'post_data' ] ), true );

		$flag = HtaccessIpBlock::addIpToBlacklistMap( $postData[ 'ip' ] );
		if($postData['block_on_wordfence']){
			$wfLog = new wfLog();
			$wfLog->blockIP($postData[ 'ip' ], 'Manual block by administrator', false, true);
		}
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
?>