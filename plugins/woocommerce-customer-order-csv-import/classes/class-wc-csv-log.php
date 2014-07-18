<?php
/**
 * WooCommerce Customer/Order CSV Import Suite
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Customer/Order CSV Import Suite to newer
 * versions in the future. If you wish to customize WooCommerce Customer/Order CSV Import Suite for your
 * needs please refer to http://docs.woothemes.com/document/customer-order-csv-import-suite/ for more information.
 *
 * @package     WC-Customer-CSV-Import-Suite/Classes
 * @author      SkyVerge
 * @copyright   Copyright (c) 2012-2014, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * WooCommerce CSV Log class.  Renamed to avoid clashes with the one from
 * the product import suite.
 *
 * @package WooCommerce_Customer_CSV_Import_Suite
 * @subpackage Log
 */
class WC_CSV_Customer_Log {

	private $log;

	public function __construct() {
		$this->log = array();
	}

	function add( $message, $echo = false ) {
		$this->log[] = $message;
		if ( $echo ) {
			echo $message . '<br/>';
			@ob_flush();
			@flush();
		}
	}

	function show_log() {

		?>
		<div class="postbox" style="margin:1em 0 0 0;">
			<div class="inside">
				<textarea id="installation_log" rows="10" cols="30" style="width: 100%; height: 200px;" readonly="readonly"><?php
					foreach ( $this->log as $log ) {
						echo $log . "\n";
					}
				?></textarea>
			</div>
		</div>
		<?php

		$this->log = array();
	}

}
