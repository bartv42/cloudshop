<?php
/*
* Template Name: Logged-In Users Page

 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site will use a
 * different template.
 *
 * @package WordPress
 * @subpackage Twenty_Twelve
 * @since Twenty Twelve 1.0
 */


// source: http://www.rlmseo.com/blog/require-login-for-wordpress-pages/

get_header(); ?>

	<div id="primary" class="site-content">
		<div id="content" role="main">

			<?php if(is_user_logged_in()):?>

				<?php while ( have_posts() ) : the_post(); ?>				
					<?php get_template_part( 'content', 'page' ); ?>
					<?php comments_template( '', true ); ?>
				<?php endwhile; // end of the loop. ?>
			<?php else: ?>
		
				<?php

					woocommerce_login_form(
						array(
							'message'  => __( 'If you have shopped with us before, please enter your details in the boxes below. If you are a new customer please proceed to the Billing &amp; Shipping section.', 'woocommerce' ),
							'redirect' => get_permalink( wc_get_page_id( 'checkout' ) ),
							'hidden'   => false
						)
					);

					/*
					$args = array(
				        'echo'           => true,
				        'redirect'       => get_permalink( wc_get_page_id( 'checkout' ) ), 
				        'form_id'        => 'loginform',
				        'label_username' => 'Your Blender ID',
				        'label_password' => __( 'Password' ),
				        'label_remember' => __( 'Remember Me' ),
				        'label_log_in'   => __( 'Log In' ),
				        'id_username'    => 'user_login',
				        'id_password'    => 'user_pass',
				        'id_remember'    => 'rememberme',
				        'id_submit'      => 'wp-submit',
				        'remember'       => true,
				        'value_username' => NULL,
				        'value_remember' => false
					); 
					*/
								
				?>				
				
			<?php endif ?>

		</div><!-- #content -->
	</div><!-- #primary -->

<?php get_footer(); ?>
