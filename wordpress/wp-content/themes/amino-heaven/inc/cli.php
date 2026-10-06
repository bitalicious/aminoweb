<?php
/**
 * WP-CLI commands.
 *
 * These are the migration and provisioning entry points for the Amino Heaven
 * theme. They are registered only under WP-CLI, so they add nothing to normal
 * front-end or admin requests.
 *
 *   wp amino-heaven setup-store             configure WooCommerce for this store
 *   wp amino-heaven setup-content           create pages, menus, front page
 *   wp amino-heaven import-products         import the catalog as native products
 *   wp amino-heaven recalculate-tiers       re-derive 2/5-vial prices
 *   wp amino-heaven catalog-status          report on the imported catalog
 *   wp amino-heaven all                     run everything in order
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage the Amino Heaven storefront.
 */
class Amino_Heaven_CLI {

	/**
	 * Whether WooCommerce is available and active.
	 *
	 * @return bool
	 */
	private static function require_woocommerce() {
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		WP_CLI::error( 'WooCommerce is not active. Run: wp plugin activate woocommerce' );

		return false;
	}

	/**
	 * Configure WooCommerce for this store: US/USD, bank transfer, cash on
	 * delivery, a United States flat-rate shipping zone, taxes off, store pages.
	 *
	 * Idempotent — safe to run repeatedly.
	 *
	 * ## EXAMPLES
	 *
	 *     wp amino-heaven setup-store
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @subcommand setup-store
	 */
	public function setup_store( $args, $assoc_args ) {
		self::require_woocommerce();

		$setup = new Amino_Heaven_Store_Setup();
		$setup->run();

		foreach ( $setup->get_notes() as $note ) {
			WP_CLI::log( '  ' . $note );
		}

		WP_CLI::success( 'Store configuration applied.' );
	}

	/**
	 * Create the storefront pages, navigation menus, front page and site icon.
	 *
	 * Idempotent. Existing page content is only overwritten with --update-content.
	 *
	 * ## OPTIONS
	 *
	 * [--update-content]
	 * : Overwrite the content of pages the command created previously.
	 *
	 * ## EXAMPLES
	 *
	 *     wp amino-heaven setup-content
	 *     wp amino-heaven setup-content --update-content
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @subcommand setup-content
	 */
	public function setup_content( $args, $assoc_args ) {
		$update = isset( $assoc_args['update-content'] );

		$setup = new Amino_Heaven_Content_Setup( $update );
		$setup->run();

		foreach ( $setup->get_notes() as $note ) {
			WP_CLI::log( '  ' . $note );
		}

		WP_CLI::success( 'Pages and menus provisioned.' );
	}

	/**
	 * Import the source storefront catalog as native WooCommerce products.
	 *
	 * Every catalog item becomes a real `product` post of type `variable`, with a
	 * real variation per 1/2/5-vial pack size. Products are matched by SKU.
	 *
	 * ## OPTIONS
	 *
	 * [--update]
	 * : Refresh products that already exist (fields and variations). Without it,
	 * existing products are skipped and never modified.
	 *
	 * [--dry-run]
	 * : Report what would happen without writing anything.
	 *
	 * [--images-dir=<path>]
	 * : Directory holding the source imagery. Auto-detected when omitted.
	 *
	 * [--catalog=<path>]
	 * : Path to the catalog JSON. Defaults to the bundled seed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp amino-heaven import-products --dry-run
	 *     wp amino-heaven import-products
	 *     wp amino-heaven import-products --update
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @subcommand import-products
	 */
	public function import_products( $args, $assoc_args ) {
		self::require_woocommerce();

		$update  = isset( $assoc_args['update'] );
		$dry_run = isset( $assoc_args['dry-run'] );

		try {
			$importer = new Amino_Heaven_Product_Importer(
				(string) ( $assoc_args['catalog'] ?? '' ),
				(string) ( $assoc_args['images-dir'] ?? '' )
			);
		} catch ( RuntimeException $exception ) {
			WP_CLI::error( $exception->getMessage() );
			return;
		}

		$stats = $importer->run( $update, $dry_run );

		WP_CLI::log( '' );
		WP_CLI::log( 'Summary' );
		WP_CLI::log( sprintf( '  products created   : %d', $stats['products_created'] ) );
		WP_CLI::log( sprintf( '  products updated   : %d', $stats['products_updated'] ) );
		WP_CLI::log( sprintf( '  products skipped   : %d', $stats['products_skipped'] ) );
		WP_CLI::log( sprintf( '  variations created : %d', $stats['variations_created'] ) );
		WP_CLI::log( sprintf( '  variations deleted : %d', $stats['variations_deleted'] ) );
		WP_CLI::log( sprintf( '  images imported    : %d', $stats['images_imported'] ) );
		WP_CLI::log( sprintf( '  images reused      : %d', $stats['images_reused'] ) );
		WP_CLI::log( sprintf( '  categories created : %d', $stats['categories_created'] ) );

		if ( $stats['image_failures'] > 0 ) {
			WP_CLI::warning( sprintf( '%d image(s) could not be imported — see warnings above.', $stats['image_failures'] ) );
		}

		if ( $dry_run ) {
			WP_CLI::success( 'Dry run complete. Nothing was written.' );

			return;
		}

		WP_CLI::success( 'Catalog imported as native WooCommerce products.' );
	}

	/**
	 * Re-derive the 2-vial and 5-vial prices from each product's 1-vial price.
	 *
	 * Use this after changing a single-vial price in the WooCommerce product
	 * editor, so the bulk tiers stay consistent with the documented policy.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show the changes without saving them.
	 *
	 * ## EXAMPLES
	 *
	 *     wp amino-heaven recalculate-tiers --dry-run
	 *     wp amino-heaven recalculate-tiers
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @subcommand recalculate-tiers
	 */
	public function recalculate_tiers( $args, $assoc_args ) {
		self::require_woocommerce();

		$dry_run = isset( $assoc_args['dry-run'] );

		try {
			$importer = new Amino_Heaven_Product_Importer();
		} catch ( RuntimeException $exception ) {
			WP_CLI::error( $exception->getMessage() );
			return;
		}

		$result = $importer->recalculate_tiers( $dry_run );

		foreach ( $result['changed'] as $change ) {
			WP_CLI::log(
				sprintf(
					'  %s %s ×%d: %s → %s (list %s)',
					$change['sku'],
					$change['dose'],
					(int) $change['pack'],
					wc_format_decimal( $change['from'] ),
					wc_format_decimal( $change['to'] ),
					wc_format_decimal( $change['regular'] )
				)
			);
		}

		if ( empty( $result['changed'] ) ) {
			WP_CLI::success( 'All tier prices already match the documented policy.' );
		} else {
			WP_CLI::success(
				sprintf(
					'%d variation price(s) %s.',
					count( $result['changed'] ),
					$dry_run ? 'would change' : 'updated'
				)
			);
		}

		if ( ! empty( $result['missing'] ) ) {
			WP_CLI::warning(
				sprintf(
					'No matching product for: %s. Run `wp amino-heaven import-products` first.',
					implode( ', ', $result['missing'] )
				)
			);
		}
	}

	/**
	 * Report on the state of the imported catalog.
	 *
	 * ## EXAMPLES
	 *
	 *     wp amino-heaven catalog-status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @subcommand catalog-status
	 */
	public function catalog_status( $args, $assoc_args ) {
		self::require_woocommerce();

		try {
			$importer = new Amino_Heaven_Product_Importer();
		} catch ( RuntimeException $exception ) {
			WP_CLI::error( $exception->getMessage() );
			return;
		}

		$catalog = json_decode( file_get_contents( AMINO_HEAVEN_DIR . 'inc/data/catalog.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.

		$rows          = array();
		$missing       = 0;
		$price_mismatch = 0;

		foreach ( $catalog['products'] as $definition ) {
			$product = wc_get_product( wc_get_product_id_by_sku( $definition['sku'] ) );

			if ( ! $product instanceof WC_Product ) {
				++$missing;
				$rows[] = array(
					'sku'        => $definition['sku'],
					'name'       => $definition['name'],
					'status'     => 'MISSING',
					'variations' => 0,
					'from'       => '—',
				);
				continue;
			}

			$prices = array();

			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );

				if ( $variation instanceof WC_Product_Variation ) {
					$prices[] = (float) $variation->get_price();
				}
			}

			$rows[] = array(
				'sku'        => $definition['sku'],
				'name'       => $definition['name'],
				'status'     => $product->get_status(),
				'variations' => count( $prices ),
				'from'       => $prices ? wc_price( min( $prices ) ) : '—',
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'sku', 'name', 'status', 'variations', 'from' ) );

		$total = count( $catalog['products'] );

		WP_CLI::log(
			sprintf(
				'%d of %d catalog products present. Categories: %d. Imagery directory: %s',
				$total - $missing,
				$total,
				count( $catalog['categories'] ),
				$importer->has_local_images() ? $importer->image_dir_candidates()[0] : 'not found (images will be downloaded)'
			)
		);

		if ( $missing > 0 ) {
			WP_CLI::warning( sprintf( '%d product(s) missing. Run: wp amino-heaven import-products', $missing ) );
		} else {
			WP_CLI::success( 'Catalog is fully imported.' );
		}
	}

	/**
	 * Run the full provisioning pipeline: store, content, catalog.
	 *
	 * ## OPTIONS
	 *
	 * [--update]
	 * : Refresh existing products as well as creating missing ones.
	 *
	 * ## EXAMPLES
	 *
	 *     wp amino-heaven all
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function all( $args, $assoc_args ) {
		self::require_woocommerce();

		WP_CLI::log( '→ Configuring the store' );
		$this->setup_store( array(), array() );

		WP_CLI::log( '→ Provisioning content' );
		$this->setup_content( array(), array() );

		WP_CLI::log( '→ Importing the catalog' );
		$this->import_products( array(), isset( $assoc_args['update'] ) ? array( 'update' => true ) : array() );

		WP_CLI::success( 'Amino Heaven storefront provisioned.' );
	}
}

WP_CLI::add_command( 'amino-heaven', 'Amino_Heaven_CLI' );
