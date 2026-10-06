<?php
/**
 * Sidebar.
 *
 * Only rendered when a widget area is populated, so the default layout keeps the
 * source storefront's full-width composition.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_active_sidebar( 'shop-sidebar' ) ) {
	return;
}
?>
<aside id="secondary" class="ah-sidebar widget-area" aria-label="<?php esc_attr_e( 'Sidebar', 'amino-heaven' ); ?>">
	<?php dynamic_sidebar( 'shop-sidebar' ); ?>
</aside>
