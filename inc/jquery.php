<?php
/**
 * Created by yarob with PhpStorm.
 * Date: 10/10/16
 * Time: 12:38
 */
?><script>
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
				ip: jQuery( '#manual_ip' ).val(),
				block_on_wordfence: jQuery( '#block_on_wordfence' ).is(':checked')
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
					jQuery('#htaccess-ip-block-list-table-form').load('#htaccess-ip-block-list-table-form #htaccess-ip-block-list-table-form')
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
			jQuery('#htaccess-ip-block-list-table-form').load('#htaccess-ip-block-list-table-form #htaccess-ip-block-list-table-form')
		});
	}
</script>