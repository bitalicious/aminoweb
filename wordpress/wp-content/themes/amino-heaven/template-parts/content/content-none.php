<?php
/**
 * "Nothing found" content part.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ah-no-results">
	<h2><?php esc_html_e( 'Nothing found', 'amino-heaven' ); ?></h2>
	<p><?php esc_html_e( 'No matching content was found. Try a different search term.', 'amino-heaven' ); ?></p>
	<?php get_search_form(); ?>
</div>
