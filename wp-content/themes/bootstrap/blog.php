<?php 
/*
	Template Name: Blog
*/
get_header(); ?>

<section id="blog-landing" class="page">
	<div class="container">
		<div class="row">
			<div class="span8">
				<?php get_template_part( 'loop', 'page' ); ?>
				
				<?php rewind_posts(); ?>
				
				<?php get_template_part( 'loop', 'blog-landing' ); ?>
				
			</div>
			<div class="span4">
				<?php get_sidebar(); ?>
			</div>
		</div>
	</div>
</section><!-- .page -->

<?php get_footer(); ?>
