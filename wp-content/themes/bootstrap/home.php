<?php 
/*
	Template Name: Home
* 
* If Front page displays settings are set via A Static Page, this file needs to be named page-home.php, otherwise just call it home.php
*/
get_header(); ?>


<section class="page">
	<div class="container">
		<div class="row">
			<div class="span12">
				<h1>Bootstrap starter theme</h1>
				<p>Use this theme as a way to quick start any new project.<br> All you get is this message and a barebones HTML document.</p>

				<?php query_posts('showposts=3'); ?>
				<?php get_template_part( 'loop', 'blog' ); ?>
			</div>
		</div>
	</div>
</section><!-- #page -->

<?php get_footer(); ?>
