<?php 
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit ();

delete_option('wooparc_orderstatus');
delete_option('wooparc_actions');
delete_option('wooparc_actions_price');
delete_option('wooparc_last_export');
delete_option('wooparc_datefrom');
delete_option('wooparc_dateto');
delete_option('wooparc_submitted');
delete_option('wooparc_type');
delete_option('wooparc_address2');