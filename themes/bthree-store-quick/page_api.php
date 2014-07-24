<?php
/*
* Template Name: API Output

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
header('Content-Type: application/json');
while ( have_posts() ) : the_post(); ?>				
<?php the_content(); ?>
<?php endwhile; // end of the loop. ?>