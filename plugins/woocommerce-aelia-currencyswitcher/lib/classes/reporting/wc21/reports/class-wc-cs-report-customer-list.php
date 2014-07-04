<?php
namespace Aelia\CurrencySwitcher\WC21;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

use \WC_Order;

/**
 * Overrides standard WC_Report_Customer_List class.
 * This class is an almost exact clone of its parent, with the exception that it
 * takes amounts in base currency, rather than the ones in which orders were
 * placed, to ensure that totals are consistent (original reports only take
 * absolute values, thus mixing up various multiple currencies and producing a
 * single, incorrect total).
 */
class WC_CS_Report_Customer_List extends \WC_Report_Customer_List {
	/**
	 * column_default function.
	 * @access public
	 * @param mixed  $user
	 * @param string $column_name
	 * @return int|string
	 * @todo Inconsistent return types, and void return at the end. Needs a rewrite.
	 */
	function column_default( $user, $column_name ) {
		global $wpdb;

		switch( $column_name ) {
			case 'customer_name' :
				if ( $user->last_name && $user->first_name ) {
					return $user->last_name . ', ' . $user->first_name;
				} else {
					return '-';
				}
			case 'username' :
				return $user->user_login;
			break;
			case 'location' :

				$state_code   = get_user_meta( $user->ID, 'billing_state', true );
				$country_code = get_user_meta( $user->ID, 'billing_country', true );

				$state = isset( WC()->countries->states[ $country_code ][ $state_code ] ) ? WC()->countries->states[ $country_code ][ $state_code ] : $state_code;
				$country = isset( WC()->countries->countries[ $country_code ] ) ? WC()->countries->countries[ $country_code ] : $country_code;

				$value = '';

				if ( $state ) {
					$value .= $state . ', ';
				}

				$value .= $country;

				if ( $value ) {
					return $value;
				} else {
					return '-';
				}
			break;
			case 'email' :
				return '<a href="mailto:' . $user->user_email . '">' . $user->user_email . '</a>';
			case 'spent' :
				if ( ! $spent = get_user_meta( $user->ID, '_money_spent', true ) ) {

					$spent = $wpdb->get_var( "SELECT SUM(meta2.meta_value)
						FROM $wpdb->posts as posts

						LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
						LEFT JOIN {$wpdb->postmeta} AS meta2 ON posts.ID = meta2.post_id
						LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
						LEFT JOIN {$wpdb->term_taxonomy} AS tax USING( term_taxonomy_id )
						LEFT JOIN {$wpdb->terms} AS term USING( term_id )

						WHERE 	meta.meta_key 		= '_customer_user'
						AND 	meta.meta_value 	= $user->ID
						AND 	posts.post_type 	= 'shop_order'
						AND 	posts.post_status 	= 'publish'
						AND 	tax.taxonomy		= 'shop_order_status'
						AND		term.slug			IN ( 'completed' )
						AND     meta2.meta_key 		= '_order_total_base_currency'
					" );

					update_user_meta( $user->ID, '_money_spent', $spent );
				}

				return wc_price( $spent );
			break;
			case 'orders' :
				if ( ! $count = get_user_meta( $user->ID, '_order_count', true ) ) {

					$count = $wpdb->get_var( "SELECT COUNT(*)
						FROM $wpdb->posts as posts

						LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
						LEFT JOIN {$wpdb->term_relationships} AS rel ON posts.ID=rel.object_ID
						LEFT JOIN {$wpdb->term_taxonomy} AS tax USING( term_taxonomy_id )
						LEFT JOIN {$wpdb->terms} AS term USING( term_id )

						WHERE 	meta.meta_key 		= '_customer_user'
						AND 	posts.post_type 	= 'shop_order'
						AND 	posts.post_status 	= 'publish'
						AND 	tax.taxonomy		= 'shop_order_status'
						AND		term.slug			IN ( 'completed' )
						AND 	meta_value 			= $user->ID
					" );

					update_user_meta( $user->ID, '_order_count', $count );
				}

				return absint( $count );
			break;
			case 'last_order' :

				$order_ids = get_posts( array(
					'posts_per_page' => 1,
					'post_type'      => 'shop_order',
					'orderby'        => 'date',
					'order'          => 'desc',
					'meta_query' => array(
						array(
							'key'     => '_customer_user',
							'value'   => $user->ID
						)
					),
					'fields' => 'ids'
				) );

				if ( $order_ids ) {
					$order = new WC_Order( $order_ids[0] );

					echo '<a href="' . admin_url( 'post.php?post=' . $order->id . '&action=edit' ) . '">' . $order->get_order_number() . '</a> &ndash; ' . date_i18n( get_option( 'date_format' ), strtotime( $order->order_date ) );
				} else echo '-';

			break;
			case 'user_actions' :
				?><p>
					<?php
						do_action( 'woocommerce_admin_user_actions_start', $user );

						$actions = array();

						$actions['edit'] = array(
							'url' 		=> admin_url( 'user-edit.php?user_id=' . $user->ID ),
							'name' 		=> __( 'Edit', 'woocommerce' ),
							'action' 	=> "edit"
						);

						$actions['view'] = array(
							'url' 		=> admin_url( 'edit.php?post_type=shop_order&_customer_user=' . $user->ID ),
							'name' 		=> __( 'View orders', 'woocommerce' ),
							'action' 	=> "view"
						);

						$order_ids = get_posts( array(
							'posts_per_page' => 1,
							'post_type'      => 'shop_order',
							'meta_query' => array(
								array(
									'key'     => '_customer_user',
									'value'   => array( 0, '' ),
									'compare' => 'IN'
								),
								array(
									'key'     => '_billing_email',
									'value'   => $user->user_email
								)
							),
							'fields' => 'ids'
						) );

						if ( $order_ids ) {
							$actions['link'] = array(
								'url' 		=> wp_nonce_url( add_query_arg( 'link_orders', $user->ID ), 'link_orders' ),
								'name' 		=> __( 'Link previous orders', 'woocommerce' ),
								'action' 	=> "link"
							);
						}

						$actions = apply_filters( 'woocommerce_admin_user_actions', $actions, $user );

						foreach ( $actions as $action ) {
							printf( '<a class="button tips %s" href="%s" data-tip="%s">%s</a>', esc_attr( $action['action'] ), esc_url( $action['url'] ), esc_attr( $action['name'] ), esc_attr( $action['name'] ) );
						}

						do_action( 'woocommerce_admin_user_actions_end', $user );
					?>
				</p><?php
			break;
		}
	}
}
