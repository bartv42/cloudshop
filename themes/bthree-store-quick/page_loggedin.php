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

				<article  class="page type-page status-publish hentry">
					<header class="entry-header">
					    <h1 class="entry-title">
					        Checkout
					    </h1>
					</header>
					<div class="entry-content">
						<div class="woocommerce">
							<?php include( 'woocommerce/myaccount/form-login.php' ); ?>
						</div>
					</div>
				</article>			

			<?php endif ?>

		</div><!-- #content -->
	</div><!-- #primary -->

<?php get_footer(); ?>
