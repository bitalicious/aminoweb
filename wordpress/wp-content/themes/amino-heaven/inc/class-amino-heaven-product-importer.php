<?php
/**
 * Catalog importer: source storefront data -> native WooCommerce products.
 *
 * ARCHITECTURAL CONTRACT
 * ----------------------
 * Every sellable item becomes a real `product` post of type `variable`, with real
 * `product_variation` children, real prices, real SKUs, real media-library images
 * and real product categories. WooCommerce owns the catalog: nothing in the theme
 * reads `inc/data/catalog.json` at runtime, and no custom post type, custom table
 * or parallel pricing system is introduced.
 *
 * WHY VARIATIONS FOR THE 1/2/5-VIAL TIERS
 * ---------------------------------------
 * The source storefront renders a pack selector (1 Vial / 2 Vials / 5 Vials) and
 * a separate quantity stepper. The pack is therefore a genuine purchasable
 * option, not a quantity-discount rule, so the native WooCommerce representation
 * is a variation per pack size. That gives:
 *   - server-authoritative pricing (WooCommerce recalculates the cart/order),
 *   - administrator-editable prices per pack size in the normal product editor,
 *   - no custom pricing code, no bespoke cart logic, no JS-trusted totals.
 * `wp amino-heaven recalculate-tiers` re-derives the 2/5-vial prices from the
 * 1-vial price when an administrator changes the base price.
 *
 * IDEMPOTENCY
 * -----------
 * SKU is the stable identifier. A product is looked up by SKU:
 *   - not found            -> created
 *   - found, no --update   -> skipped (never overwritten)
 *   - found, with --update -> its seeded fields and variations are refreshed
 * Products whose SKU is not in the seed are never read or written.
 * Media imports are deduplicated through the `_amino_heaven_source_image` meta.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Imports the Amino Heaven catalog into WooCommerce.
 */
class Amino_Heaven_Product_Importer {

	/**
	 * Attachment meta recording the original source filename, used to keep media
	 * imports idempotent.
	 */
	const META_SOURCE_IMAGE = '_amino_heaven_source_image';

	/**
	 * Decoded catalog definition.
	 *
	 * @var array<string,mixed>
	 */
	private $catalog = array();

	/**
	 * Directory containing the source imagery.
	 *
	 * @var string
	 */
	private $images_dir = '';

	/**
	 * Whether to report only.
	 *
	 * @var bool
	 */
	private $dry_run = false;

	/**
	 * Whether to refresh existing products.
	 *
	 * @var bool
	 */
	private $update = false;

	/**
	 * Counters for the run summary.
	 *
	 * @var array<string,int>
	 */
	private $stats = array(
		'products_created'    => 0,
		'products_updated'    => 0,
		'products_skipped'    => 0,
		'variations_created'  => 0,
		'variations_deleted'  => 0,
		'images_imported'     => 0,
		'images_reused'       => 0,
		'categories_created'  => 0,
		'categories_updated'  => 0,
		'image_failures'      => 0,
	);

	/**
	 * Non-fatal problems, surfaced at the end of the run.
	 *
	 * @var string[]
	 */
	private $warnings = array();

	/**
	 * Constructor.
	 *
	 * @param string $catalog_file Path to the catalog JSON. Defaults to the bundled seed.
	 * @param string $images_dir   Directory holding the source imagery. Auto-detected when empty.
	 */
	public function __construct( $catalog_file = '', $images_dir = '' ) {
		if ( '' === $catalog_file ) {
			$catalog_file = AMINO_HEAVEN_DIR . 'inc/data/catalog.json';
		}

		$this->catalog    = $this->load_catalog( $catalog_file );
		$this->images_dir = '' !== $images_dir ? $images_dir : $this->detect_images_dir();
	}

	/**
	 * Candidate locations for the source imagery, most specific first.
	 *
	 * The importer works from local files so the migration never depends on the
	 * Lovable CDN being online; the catalog's `imageUrl` is only a fallback.
	 *
	 * @return string[]
	 */
	public static function image_dir_candidates() {
		$candidates = array();

		$env = getenv( 'AMINO_HEAVEN_SEED_IMAGES' );
		if ( is_string( $env ) && '' !== $env ) {
			$candidates[] = $env;
		}

		// Mounted read-only by docker-compose for the `cli` service.
		$candidates[] = '/seed-assets/product-images';

		// Running WP-CLI outside Docker from the repository root.
		$candidates[] = dirname( ABSPATH, 1 ) . '/assets-source/product-images';

		// Optional in-theme copy, for a fully self-contained theme ZIP.
		$candidates[] = AMINO_HEAVEN_DIR . 'inc/data/images';

		return array_values( array_filter( $candidates ) );
	}

	/**
	 * Locate the first existing images directory.
	 *
	 * @return string
	 */
	private function detect_images_dir() {
		foreach ( self::image_dir_candidates() as $candidate ) {
			if ( is_dir( $candidate ) ) {
				return rtrim( $candidate, '/' );
			}
		}

		return '';
	}

	/**
	 * Read and validate the catalog definition.
	 *
	 * @param string $file Path to the JSON file.
	 * @return array<string,mixed>
	 */
	private function load_catalog( $file ) {
		if ( ! is_readable( $file ) ) {
			throw new RuntimeException( sprintf( 'Catalog file not readable: %s', $file ) );
		}

		$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.

		if ( false === $raw ) {
			throw new RuntimeException( sprintf( 'Could not read catalog file: %s', $file ) );
		}

		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) || empty( $data['products'] ) ) {
			throw new RuntimeException( sprintf( 'Catalog file is not valid JSON or contains no products: %s', $file ) );
		}

		return $data;
	}

	/**
	 * Whether a source image directory was found.
	 *
	 * @return bool
	 */
	public function has_local_images() {
		return '' !== $this->images_dir;
	}

	/**
	 * Summary of the last run.
	 *
	 * @return array<string,int>
	 */
	public function get_stats() {
		return $this->stats;
	}

	/**
	 * Non-fatal problems collected during the last run.
	 *
	 * @return string[]
	 */
	public function get_warnings() {
		return $this->warnings;
	}

	/**
	 * Execute the import.
	 *
	 * @param bool $update  Refresh products that already exist.
	 * @param bool $dry_run Report only; change nothing.
	 * @return array<string,int>
	 */
	public function run( $update = false, $dry_run = false ) {
		$this->update  = (bool) $update;
		$this->dry_run = (bool) $dry_run;

		if ( ! $this->dry_run ) {
			// media_handle_sideload() and friends live in wp-admin.
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$this->say( sprintf( 'Catalog   : %d products, %d categories', count( $this->catalog['products'] ), count( $this->catalog['categories'] ) ) );
		$this->say( sprintf( 'Imagery   : %s', $this->has_local_images() ? $this->images_dir : '(no local directory found — will download)' ) );
		$this->say( sprintf( 'Mode      : %s%s', $this->dry_run ? 'dry run' : 'write', $this->update ? ', update existing' : ', create only' ) );

		$category_ids = $this->sync_categories();

		foreach ( $this->catalog['products'] as $index => $definition ) {
			$this->import_product( $definition, (int) $index, $category_ids );
		}

		return $this->stats;
	}

	/**
	 * Re-derive the 2-vial and 5-vial prices from each product's 1-vial price.
	 *
	 * Intended for administrators who change a single-vial price in the
	 * WooCommerce product editor and want the bulk tiers recalculated with the
	 * documented policy. Only variations belonging to seeded products are touched.
	 *
	 * @param bool $dry_run Report only.
	 * @return array<string,mixed>
	 */
	public function recalculate_tiers( $dry_run = false ) {
		$tiers    = $this->get_tiers();
		$factor   = (float) $this->catalog['pricePolicy']['listPriceFactor'];
		$changed  = array();
		$missing  = array();

		foreach ( $this->catalog['products'] as $definition ) {
			$product = $this->find_by_sku( $definition['sku'] );

			if ( ! $product instanceof WC_Product_Variable ) {
				$missing[] = $definition['sku'];
				continue;
			}

			$dose_slug = $this->dose_slug();
			$pack_slug = $this->pack_slug();

			/** @var WC_Product_Variation[] $variations */
			$variations = array();

			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );

				if ( ! $variation instanceof WC_Product_Variation ) {
					continue;
				}

				$attributes = $variation->get_variation_attributes();
				$dose       = isset( $attributes[ 'attribute_' . $dose_slug ] ) ? (string) $attributes[ 'attribute_' . $dose_slug ] : '';

				if ( '' === $dose ) {
					$dose = $this->product_dose_label( $product );
				}

				$pack_label = isset( $attributes[ 'attribute_' . $pack_slug ] ) ? (string) $attributes[ 'attribute_' . $pack_slug ] : '';

				$variations[] = array(
					'variation'  => $variation,
					'dose'       => $dose,
					'pack_count' => $this->pack_count_from_label( $pack_label ),
				);
			}

			// Index the 1-vial price per dose.
			$unit_prices = array();
			foreach ( $variations as $entry ) {
				if ( 1 === $entry['pack_count'] ) {
					$unit_prices[ $entry['dose'] ] = (float) $entry['variation']->get_price();
				}
			}

			foreach ( $variations as $entry ) {
				$dose = $entry['dose'];

				if ( ! isset( $unit_prices[ $dose ] ) || $unit_prices[ $dose ] <= 0 ) {
					continue;
				}

				$unit_price = $unit_prices[ $dose ];
				$tier       = null;

				foreach ( $tiers as $candidate ) {
					if ( (int) $candidate['count'] === (int) $entry['pack_count'] ) {
						$tier = $candidate;
						break;
					}
				}

				if ( null === $tier ) {
					continue;
				}

				$selling = $this->tier_price( $unit_price, (int) $tier['count'], (float) $tier['factor'] );
				$regular = $this->list_price( $selling, $factor );

				$before = (float) $entry['variation']->get_price();

				if ( abs( $before - $selling ) < 0.0001 && (string) $regular === (string) $entry['variation']->get_regular_price() ) {
					continue;
				}

				$changed[] = array(
					'sku'     => $definition['sku'],
					'dose'    => $dose,
					'pack'    => $entry['pack_count'],
					'from'    => $before,
					'to'      => $selling,
					'regular' => $regular,
				);

				if ( ! $dry_run ) {
					$entry['variation']->set_regular_price( (string) $regular );
					$entry['variation']->set_sale_price( (string) $selling );
					$entry['variation']->save();
				}
			}

			if ( ! $dry_run && $changed ) {
				WC_Product_Variable::sync( $product->get_id() );
				wc_delete_product_transients( $product->get_id() );
			}
		}

		return array(
			'changed' => $changed,
			'missing' => $missing,
			'dry_run' => (bool) $dry_run,
		);
	}

	/* ---------------------------------------------------------------------
	 * Categories
	 * ------------------------------------------------------------------ */

	/**
	 * Create or update the product categories defined in the catalog.
	 *
	 * @return array<string,int> Slug => term id.
	 */
	private function sync_categories() {
		$map = array();

		// Parents first so children can reference their parent id.
		$definitions = $this->catalog['categories'];
		usort(
			$definitions,
			static function ( $a, $b ) {
				return ( '' === $a['parent'] ? 0 : 1 ) <=> ( '' === $b['parent'] ? 0 : 1 );
			}
		);

		foreach ( $definitions as $definition ) {
			$slug    = (string) $definition['slug'];
			$name    = (string) $definition['name'];
			$parent  = (string) ( $definition['parent'] ?? '' );
			$parent_id = ( '' !== $parent && isset( $map[ $parent ] ) ) ? (int) $map[ $parent ] : 0;

			$existing = term_exists( $slug, 'product_cat' );

			$args = array(
				'description' => (string) ( $definition['description'] ?? '' ),
				'slug'        => $slug,
				'parent'      => $parent_id,
			);

			if ( $existing ) {
				$term_id = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );

				if ( $this->update ) {
					$result = wp_update_term( $term_id, 'product_cat', $args );

					if ( ! is_wp_error( $result ) ) {
						++$this->stats['categories_updated'];
					}
				}

				$map[ $slug ] = $term_id;
				continue;
			}

			if ( $this->dry_run ) {
				$this->say( sprintf( '  + category: %s (dry run)', $name ) );
				// Reserve a placeholder so children resolve during a dry run.
				$map[ $slug ] = 0;
				++$this->stats['categories_created'];
				continue;
			}

			$result = wp_insert_term( $name, 'product_cat', $args );

			if ( is_wp_error( $result ) ) {
				$this->warn( sprintf( 'Could not create category "%s": %s', $name, $result->get_error_message() ) );
				continue;
			}

			$map[ $slug ] = (int) $result['term_id'];
			++$this->stats['categories_created'];
			$this->say( sprintf( '  + category: %s', $name ) );
		}

		return $map;
	}

	/* ---------------------------------------------------------------------
	 * Products
	 * ------------------------------------------------------------------ */

	/**
	 * Import one product definition.
	 *
	 * @param array<string,mixed> $definition   Product definition.
	 * @param int                 $index        Position in the catalog (menu order).
	 * @param array<string,int>   $category_ids Slug => term id.
	 */
	private function import_product( $definition, $index, $category_ids ) {
		$sku      = (string) $definition['sku'];
		$existing = $this->find_by_sku( $sku );

		if ( $existing instanceof WC_Product && ! $this->update ) {
			++$this->stats['products_skipped'];
			$this->say( sprintf( '  = exists, skipping: %s (%s)', $definition['name'], $sku ) );
			return;
		}

		$tiers     = $this->get_tiers();
		$factor    = (float) $this->catalog['pricePolicy']['listPriceFactor'];
		$dose_slug = $this->dose_slug();
		$pack_slug = $this->pack_slug();

		$dose_labels = array();
		$pack_labels = array();

		foreach ( $definition['doses'] as $dose ) {
			$dose_labels[] = (string) $dose['dose'];
		}

		foreach ( $tiers as $tier ) {
			$pack_labels[] = (string) $tier['pack'];
		}

		$is_multi_dose = count( $dose_labels ) > 1;

		if ( $this->dry_run ) {
			++$this->stats['products_created'];
			$this->say(
				sprintf(
					'  + %s [%s] doses=%s packs=%d',
					$definition['name'],
					$sku,
					implode( ', ', $dose_labels ),
					count( $pack_labels )
				)
			);
			return;
		}

		// --- media ---------------------------------------------------------
		$image_ids = array();

		foreach ( $definition['doses'] as $dose ) {
			$image_id = $this->import_image(
				(string) $dose['image'],
				(string) ( $dose['imageUrl'] ?? '' ),
				sprintf(
					/* translators: 1: compound name, 2: strength. */
					__( '%1$s %2$s research vial', 'amino-heaven' ),
					(string) $definition['name'],
					(string) $dose['dose']
				)
			);

			$image_ids[ (string) $dose['dose'] ] = $image_id;
		}

		$featured_image_id = 0;
		foreach ( $image_ids as $image_id ) {
			if ( $image_id ) {
				$featured_image_id = (int) $image_id;
				break;
			}
		}

		// --- product -------------------------------------------------------
		$product = $existing instanceof WC_Product_Variable ? $existing : new WC_Product_Variable();

		$product->set_name( (string) $definition['name'] );
		$product->set_slug( (string) $definition['slug'] );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_sku( $sku );
		$product->set_menu_order( $index );
		$product->set_featured( ! empty( $definition['featured'] ) );
		$product->set_description( $this->build_description( $definition ) );
		$product->set_short_description( $this->build_short_description( $definition ) );
		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );
		$product->set_reviews_allowed( false );
		$product->set_attributes( $this->build_attributes( $definition, $dose_slug, $pack_slug, $is_multi_dose ) );

		$category_slug = (string) $definition['category'];
		if ( isset( $category_ids[ $category_slug ] ) && $category_ids[ $category_slug ] ) {
			$product->set_category_ids( array( (int) $category_ids[ $category_slug ] ) );
		}

		if ( $featured_image_id ) {
			$product->set_image_id( $featured_image_id );
		}

		$product_id = $product->save();

		if ( ! $product_id ) {
			$this->warn( sprintf( 'Failed to save product %s', $sku ) );
			return;
		}

		// Re-read so variation attribute values match exactly what WooCommerce
		// persisted (custom attribute options can be normalised on save).
		$product = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product_Variable ) {
			$this->warn( sprintf( 'Product %s did not save as a variable product', $sku ) );
			return;
		}

		if ( $existing instanceof WC_Product ) {
			++$this->stats['products_updated'];
		} else {
			++$this->stats['products_created'];
		}

		$saved_doses = $this->saved_attribute_values( $product, $dose_slug );
		$saved_packs = $this->saved_attribute_values( $product, $pack_slug );

		// --- variations ----------------------------------------------------
		$this->delete_variations( $product );

		$order      = 0;
		$defaults   = array();
		$first_pack = isset( $saved_packs[0] ) ? $saved_packs[0] : ( $pack_labels[0] ?? '' );

		foreach ( $definition['doses'] as $dose ) {
			$dose_label = (string) $dose['dose'];
			$unit_price = (float) $dose['unitPrice'];
			$dose_value = $this->match_saved_value( $dose_label, $saved_doses );
			$image_id   = isset( $image_ids[ $dose_label ] ) ? (int) $image_ids[ $dose_label ] : 0;

			if ( ! isset( $defaults[ $dose_slug ] ) && $is_multi_dose && '' !== $dose_value ) {
				$defaults[ $dose_slug ] = $dose_value;
			}

			foreach ( $tiers as $tier ) {
				$pack_label = (string) $tier['pack'];
				$pack_value = $this->match_saved_value( $pack_label, $saved_packs );

				$selling = $this->tier_price( $unit_price, (int) $tier['count'], (float) $tier['factor'] );
				$regular = $this->list_price( $selling, $factor );

				$attributes = array();

				if ( $is_multi_dose && '' !== $dose_value ) {
					$attributes[ $dose_slug ] = $dose_value;
				}

				if ( '' !== $pack_value ) {
					$attributes[ $pack_slug ] = $pack_value;
				}

				$variation = new WC_Product_Variation();
				$variation->set_parent_id( $product_id );
				$variation->set_attributes( $attributes );
				$variation->set_regular_price( $this->format_amount( $regular ) );
				$variation->set_sale_price( $this->format_amount( $selling ) );
				$variation->set_status( 'publish' );
				$variation->set_manage_stock( false );
				$variation->set_stock_status( 'instock' );
				$variation->set_menu_order( $order );
				$variation->set_sku( $this->variation_sku( $sku, $dose_label, (int) $tier['count'] ) );

				if ( $image_id ) {
					$variation->set_image_id( $image_id );
				}

				$variation_id = $variation->save();

				if ( $variation_id ) {
					++$this->stats['variations_created'];
				}

				++$order;
			}
		}

		if ( ! isset( $defaults[ $pack_slug ] ) && '' !== $first_pack ) {
			$defaults[ $pack_slug ] = $first_pack;
		}

		if ( $defaults ) {
			$product->set_default_attributes( $defaults );
			$product->save();
		}

		WC_Product_Variable::sync( $product_id );
		wc_delete_product_transients( $product_id );

		$this->say(
			sprintf(
				'  %s %s (%s) — %d variations',
				$existing instanceof WC_Product ? '~' : '+',
				$definition['name'],
				$sku,
				$order
			)
		);
	}

	/**
	 * Build the `WC_Product_Attribute` objects for a product.
	 *
	 * @param array<string,mixed> $definition    Product definition.
	 * @param string              $dose_slug     Dose attribute slug.
	 * @param string              $pack_slug     Pack attribute slug.
	 * @param bool                $is_multi_dose Whether the strength varies.
	 * @return WC_Product_Attribute[]
	 */
	private function build_attributes( $definition, $dose_slug, $pack_slug, $is_multi_dose ) {
		$dose_options = array();
		foreach ( $definition['doses'] as $dose ) {
			$dose_options[] = (string) $dose['dose'];
		}

		$pack_options = array();
		foreach ( $this->get_tiers() as $tier ) {
			$pack_options[] = (string) $tier['pack'];
		}

		$dose = new WC_Product_Attribute();
		$dose->set_id( 0 );
		$dose->set_name( $dose_slug );
		$dose->set_options( $dose_options );
		$dose->set_position( 0 );
		$dose->set_visible( true );
		$dose->set_variation( (bool) $is_multi_dose );

		$pack = new WC_Product_Attribute();
		$pack->set_id( 0 );
		$pack->set_name( $pack_slug );
		$pack->set_options( $pack_options );
		$pack->set_position( 1 );
		$pack->set_visible( true );
		$pack->set_variation( true );

		return array( $dose, $pack );
	}

	/**
	 * Read the option values WooCommerce actually persisted for an attribute.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $slug    Attribute slug.
	 * @return string[]
	 */
	private function saved_attribute_values( $product, $slug ) {
		$attributes = $product->get_attributes();

		if ( ! isset( $attributes[ $slug ] ) || ! $attributes[ $slug ] instanceof WC_Product_Attribute ) {
			return array();
		}

		return array_values( array_map( 'strval', $attributes[ $slug ]->get_options() ) );
	}

	/**
	 * Match a seed label against the values WooCommerce stored for the attribute.
	 *
	 * Custom (non-taxonomy) attribute options can be normalised on save, so the
	 * variation's attribute value must be taken from the saved parent attribute
	 * rather than assumed to equal the seed label.
	 *
	 * @param string   $label  Seed label, e.g. "2 Vials".
	 * @param string[] $values Saved attribute option values.
	 * @return string
	 */
	private function match_saved_value( $label, $values ) {
		foreach ( $values as $value ) {
			if ( $value === $label ) {
				return $value;
			}
		}

		$needle = sanitize_title( $label );

		foreach ( $values as $value ) {
			if ( sanitize_title( $value ) === $needle ) {
				return $value;
			}
		}

		return (string) $label;
	}

	/**
	 * Remove the product's existing variations (used by --update).
	 *
	 * @param WC_Product $product Product.
	 */
	private function delete_variations( $product ) {
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );

			if ( $variation instanceof WC_Product_Variation ) {
				$variation->delete( true );
				++$this->stats['variations_deleted'];
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Media
	 * ------------------------------------------------------------------ */

	/**
	 * Import an image into the media library, reusing it when already imported.
	 *
	 * @param string $filename Source filename.
	 * @param string $url      Fallback download URL.
	 * @param string $alt      Attachment alt text.
	 * @return int Attachment id, or 0 on failure.
	 */
	private function import_image( $filename, $url, $alt ) {
		$existing = $this->find_attachment( $filename );

		if ( $existing ) {
			++$this->stats['images_reused'];
			$this->set_alt_text( $existing, $alt );

			return $existing;
		}

		$source = '';

		if ( $this->has_local_images() ) {
			$candidate = $this->images_dir . '/' . $filename;

			if ( is_readable( $candidate ) ) {
				$source = $candidate;
			}
		}

		if ( '' === $source && '' === $url ) {
			$this->warn( sprintf( 'No local file or fallback URL for image "%s"', $filename ) );
			++$this->stats['image_failures'];

			return 0;
		}

		// media_handle_sideload() consumes the file, so work on a copy.
		$tmp = wp_tempnam( $filename );

		if ( ! $tmp ) {
			$this->warn( sprintf( 'Could not allocate a temporary file for "%s"', $filename ) );
			++$this->stats['image_failures'];

			return 0;
		}

		if ( '' !== $source ) {
			if ( ! copy( $source, $tmp ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort cleanup.
				$this->warn( sprintf( 'Could not copy "%s" into the temporary file', $source ) );
				++$this->stats['image_failures'];

				return 0;
			}
		} else {
			$downloaded = $this->download_to( $url, $tmp );

			if ( ! $downloaded ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort cleanup.
				$this->warn( sprintf( 'Could not download "%s"', $url ) );
				++$this->stats['image_failures'];

				return 0;
			}
		}

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload(
			$file_array,
			0,
			$alt,
			array(
				'post_title'   => pathinfo( $filename, PATHINFO_FILENAME ),
				'post_content' => '',
			)
		);

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort cleanup.
			$this->warn( sprintf( 'Media import failed for "%s": %s', $filename, $attachment_id->get_error_message() ) );
			++$this->stats['image_failures'];

			return 0;
		}

		$attachment_id = (int) $attachment_id;

		update_post_meta( $attachment_id, self::META_SOURCE_IMAGE, $filename );
		$this->set_alt_text( $attachment_id, $alt );

		++$this->stats['images_imported'];

		return $attachment_id;
	}

	/**
	 * Download a URL into an open file path.
	 *
	 * @param string $url  Source URL.
	 * @param string $path Destination path.
	 * @return bool
	 */
	private function download_to( $url, $path ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'  => 30,
				'stream'   => true,
				'filename' => $path,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return $code >= 200 && $code < 300 && file_exists( $path ) && filesize( $path ) > 0;
	}

	/**
	 * Find an attachment previously imported from this source filename.
	 *
	 * @param string $filename Source filename.
	 * @return int
	 */
	private function find_attachment( $filename ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded, indexed lookup on import.
				'meta_query'     => array(
					array(
						'key'   => self::META_SOURCE_IMAGE,
						'value' => $filename,
					),
				),
			)
		);

		return $query->posts ? (int) $query->posts[0] : 0;
	}

	/**
	 * Set an attachment's alt text.
	 *
	 * @param int    $attachment_id Attachment id.
	 * @param string $alt           Alt text.
	 */
	private function set_alt_text( $attachment_id, $alt ) {
		if ( '' === trim( $alt ) ) {
			return;
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
	}

	/* ---------------------------------------------------------------------
	 * Pricing
	 * ------------------------------------------------------------------ */

	/**
	 * Tier definitions from the catalog.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_tiers() {
		$tiers = $this->catalog['pricePolicy']['tiers'] ?? array();

		usort(
			$tiers,
			static function ( $a, $b ) {
				return (int) $a['count'] <=> (int) $b['count'];
			}
		);

		return $tiers;
	}

	/**
	 * Selling price for a multi-vial pack.
	 *
	 * Mirrors the source storefront's `Math.round(price * count * factor * 100) / 100`.
	 *
	 * @param float $unit_price 1-vial price.
	 * @param int   $count      Number of vials.
	 * @param float $factor     Tier multiplier.
	 * @return float
	 */
	private function tier_price( $unit_price, $count, $factor ) {
		return round( $unit_price * $count * $factor * 100 ) / 100;
	}

	/**
	 * Struck-through "list" price for a selling price.
	 *
	 * Mirrors the source storefront's `Math.round(price * 1.3 * 100) / 100`.
	 *
	 * @param float $selling Selling price.
	 * @param float $factor  List-price factor.
	 * @return float
	 */
	private function list_price( $selling, $factor ) {
		return round( $selling * $factor * 100 ) / 100;
	}

	/**
	 * Format an amount the way WooCommerce stores prices.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private function format_amount( $amount ) {
		return wc_format_decimal( $amount, wc_get_price_decimals() );
	}

	/* ---------------------------------------------------------------------
	 * Metadata helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Dose attribute slug.
	 *
	 * @return string
	 */
	private function dose_slug() {
		return (string) ( $this->catalog['attributes']['dose']['slug'] ?? 'dose' );
	}

	/**
	 * Pack attribute slug.
	 *
	 * @return string
	 */
	private function pack_slug() {
		return (string) ( $this->catalog['attributes']['pack']['slug'] ?? 'pack' );
	}

	/**
	 * Build a variation SKU.
	 *
	 * @param string $product_sku Parent SKU.
	 * @param string $dose        Strength label.
	 * @param int    $count       Vial count.
	 * @return string
	 */
	private function variation_sku( $product_sku, $dose, $count ) {
		$dose_key = strtoupper( preg_replace( '/[^A-Za-z0-9]+/', '-', $dose ) );
		$dose_key = trim( $dose_key, '-' );

		return sprintf( '%s-%s-%dV', $product_sku, $dose_key, (int) $count );
	}

	/**
	 * Number of vials represented by a pack label.
	 *
	 * @param string $label Pack label, e.g. "5 Vials".
	 * @return int
	 */
	private function pack_count_from_label( $label ) {
		if ( preg_match( '/\d+/', (string) $label, $matches ) ) {
			return max( 1, (int) $matches[0] );
		}

		return 1;
	}

	/**
	 * The single strength of a product whose strength is not a variation.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	private function product_dose_label( $product ) {
		$attributes = $product->get_attributes();
		$slug       = $this->dose_slug();

		if ( isset( $attributes[ $slug ] ) && $attributes[ $slug ] instanceof WC_Product_Attribute ) {
			$options = $attributes[ $slug ]->get_options();

			if ( ! empty( $options ) ) {
				return (string) $options[0];
			}
		}

		return '';
	}

	/**
	 * Product long description.
	 *
	 * Built only from wording that already exists on the source storefront, and
	 * limited to research-use positioning: no therapeutic, diagnostic or
	 * consumer-health claims are introduced. Administrators can edit it freely
	 * afterwards in the normal product editor.
	 *
	 * @param array<string,mixed> $definition Product definition.
	 * @return string
	 */
	private function build_description( $definition ) {
		$name = (string) $definition['name'];

		$paragraphs = array(
			sprintf(
				/* translators: %s: compound name. */
				__( '%s is supplied as a research compound for controlled laboratory, analytical, and academic research use. Each batch is accompanied by an independent Certificate of Analysis from an accredited U.S. laboratory.', 'amino-heaven' ),
				$name
			),
			__( 'All products distributed through this platform are designated strictly for in vitro research, laboratory analysis, and development purposes. Under no circumstances are these compounds intended, formulated, or approved for human consumption, veterinary administration, or therapeutic application of any kind.', 'amino-heaven' ),
		);

		/**
		 * Filter the generated product description.
		 *
		 * @param string[]            $paragraphs Description paragraphs.
		 * @param array<string,mixed> $definition Product definition.
		 */
		$paragraphs = apply_filters( 'amino_heaven_import_product_description', $paragraphs, $definition );

		return implode( "\n\n", array_map( 'wp_kses_post', $paragraphs ) );
	}

	/**
	 * Product short description.
	 *
	 * @param array<string,mixed> $definition Product definition.
	 * @return string
	 */
	private function build_short_description( $definition ) {
		$short = __( 'Supplied for controlled laboratory, analytical and academic research use only. Not for human or animal consumption.', 'amino-heaven' );

		/**
		 * Filter the generated short description.
		 *
		 * @param string              $short      Short description.
		 * @param array<string,mixed> $definition Product definition.
		 */
		return wp_kses_post( apply_filters( 'amino_heaven_import_product_short_description', $short, $definition ) );
	}

	/**
	 * Look up a product by SKU without touching unrelated products.
	 *
	 * @param string $sku SKU.
	 * @return WC_Product|null
	 */
	private function find_by_sku( $sku ) {
		if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
			return null;
		}

		$product_id = wc_get_product_id_by_sku( $sku );

		if ( ! $product_id ) {
			return null;
		}

		$product = wc_get_product( $product_id );

		return $product instanceof WC_Product ? $product : null;
	}

	/* ---------------------------------------------------------------------
	 * Output
	 * ------------------------------------------------------------------ */

	/**
	 * Emit a progress line.
	 *
	 * @param string $message Message.
	 */
	private function say( $message ) {
		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI::log( $message );
		}
	}

	/**
	 * Record (and immediately surface) a non-fatal problem.
	 *
	 * @param string $message Message.
	 */
	private function warn( $message ) {
		$this->warnings[] = $message;

		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI::warning( $message );
		}
	}
}
