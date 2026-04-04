<?php
/*
 * Template Name: Event Marketing Page
 *
 * Full-width page template for auto-generated Grant Writing USA event
 * marketing pages.  Uses the active theme's header and footer; the page
 * body (title + content) is rendered in a centred container without a sidebar.
 */

get_header();
?>

<div id="gwu-event-marketing-wrap" class="gwu-event-marketing-wrap">
	<?php while ( have_posts() ) : the_post(); ?>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'gwu-event-marketing-article' ); ?>>

			<header class="gwu-event-marketing-header">
				<h1 class="gwu-event-marketing-title"><?php the_title(); ?></h1>
			</header>

			<div class="gwu-event-marketing-content entry-content">
				<?php the_content(); ?>
			</div>

		</article>

	<?php endwhile; ?>
</div>

<?php
get_footer();
