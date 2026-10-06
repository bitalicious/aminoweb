<?php
/**
 * Content provisioning: pages, menus, front page, site icon.
 *
 * The source storefront hardcoded its editorial copy in React. Here that copy is
 * reproduced as real WordPress pages so an administrator can edit it, and the
 * navigation is created as real WordPress menus so it is administrable.
 *
 * Only wording that already exists on the source storefront is used: the
 * research-use positioning, the compliance statements, the quality claims and the
 * contact details. No therapeutic, diagnostic or consumer-health claims are added.
 *
 * Idempotent: pages are matched by slug, menus by name, and existing content is
 * never overwritten unless `--update-content` is passed.
 *
 * @package Amino_Heaven
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates the storefront's pages and menus.
 */
class Amino_Heaven_Content_Setup {

	/**
	 * Applied changes, for reporting.
	 *
	 * @var string[]
	 */
	private $notes = array();

	/**
	 * Whether to overwrite existing page content.
	 *
	 * @var bool
	 */
	private $update = false;

	/**
	 * Constructor.
	 *
	 * @param bool $update Overwrite existing page content.
	 */
	public function __construct( $update = false ) {
		$this->update = (bool) $update;
	}

	/**
	 * Change notes.
	 *
	 * @return string[]
	 */
	public function get_notes() {
		return $this->notes;
	}

	/**
	 * Run every provisioning step.
	 *
	 * @return string[] Change notes.
	 */
	public function run() {
		$pages = $this->create_pages();

		$this->assign_front_page( $pages );
		$this->assign_privacy_page();
		$this->assign_terms_page( $pages );
		$this->create_menus( $pages );
		$this->set_site_icon();
		$this->clean_default_widgets();

		return $this->notes;
	}

	/**
	 * Block names WordPress uses when it pre-populates sidebars on a fresh install.
	 *
	 * Used only to recognise — and clear — WordPress's own starter widgets, which
	 * are otherwise reassigned into this theme's registered sidebars when the
	 * theme is activated and would add stray columns to a design that has none.
	 *
	 * @var string[]
	 */
	const DEFAULT_WIDGET_BLOCKS = array(
		'group',
		'heading',
		'paragraph',
		'list',
		'columns',
		'column',
		'separator',
		'spacer',
		'buttons',
		'button',
		'image',
		'social-links',
		'search',
		'latest-posts',
		'latest-comments',
		'archives',
		'categories',
		'page-list',
		'meta',
		'calendar',
		'rss',
		'tag-cloud',
		'navigation',
	);

	/**
	 * Move WordPress's starter widgets out of the theme's sidebars, once.
	 *
	 * The source storefront has no sidebar content, so a fresh install should not
	 * invent any. This runs a single time (guarded by an option) so an
	 * administrator's own widget choices are never subsequently disturbed.
	 */
	private function clean_default_widgets() {
		if ( get_option( 'amino_heaven_default_widgets_cleaned' ) ) {
			return;
		}

		$sidebars = get_option( 'sidebars_widgets', array() );
		$blocks   = get_option( 'widget_block', array() );

		if ( ! is_array( $sidebars ) || ! is_array( $blocks ) ) {
			return;
		}

		$targets  = array( 'shop-sidebar', 'footer-extra' );
		$inactive = isset( $sidebars['wp_inactive_widgets'] ) && is_array( $sidebars['wp_inactive_widgets'] )
			? $sidebars['wp_inactive_widgets']
			: array();

		$removed = 0;

		foreach ( $targets as $sidebar ) {
			if ( empty( $sidebars[ $sidebar ] ) || ! is_array( $sidebars[ $sidebar ] ) ) {
				continue;
			}

			$kept = array();

			foreach ( $sidebars[ $sidebar ] as $widget_id ) {
				if ( ! $this->is_default_block_widget( (string) $widget_id, $blocks ) ) {
					$kept[] = $widget_id;
					continue;
				}

				$inactive[] = $widget_id;
				++$removed;
			}

			$sidebars[ $sidebar ] = $kept;
		}

		if ( $removed > 0 ) {
			$sidebars['wp_inactive_widgets'] = array_values( array_unique( $inactive ) );
			wp_set_sidebars_widgets( $sidebars );
			$this->note( sprintf( 'Moved %d WordPress starter widget(s) out of the theme sidebars', $removed ) );
		}

		update_option( 'amino_heaven_default_widgets_cleaned', AMINO_HEAVEN_VERSION );
	}

	/**
	 * Whether a widget instance is one of WordPress's untouched starter blocks.
	 *
	 * @param string $widget_id Widget id, e.g. "block-5".
	 * @param array  $blocks    The `widget_block` option.
	 * @return bool
	 */
	private function is_default_block_widget( $widget_id, $blocks ) {
		if ( 0 !== strpos( $widget_id, 'block-' ) ) {
			return false;
		}

		$index = (int) substr( $widget_id, strlen( 'block-' ) );

		if ( ! isset( $blocks[ $index ]['content'] ) || ! is_string( $blocks[ $index ]['content'] ) ) {
			return false;
		}

		$content = $blocks[ $index ]['content'];

		if ( ! preg_match_all( '/wp:([a-z0-9\-]+)/i', $content, $matches ) ) {
			return false;
		}

		$names = array_unique( array_map( 'strtolower', $matches[1] ) );

		if ( ! $names ) {
			return false;
		}

		foreach ( $names as $name ) {
			if ( ! in_array( $name, self::DEFAULT_WIDGET_BLOCKS, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Record a change.
	 *
	 * @param string $note Note.
	 */
	private function note( $note ) {
		$this->notes[] = $note;
	}

	/**
	 * The page definitions, with copy carried over from the source storefront.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function page_definitions() {
		$contact_email = get_theme_mod( 'amino_heaven_contact_email', 'hello@aminoheaven.com' );

		$compliance = array();

		foreach ( amino_heaven_compliance_statements() as $statement ) {
			$compliance[] = '<p>' . esc_html( $statement ) . '</p>';
		}

		$compliance_html = implode( "\n", $compliance );

		$definitions = array(
			'home'               => array(
				'title'   => __( 'Home', 'amino-heaven' ),
				'content' => implode(
					"\n",
					array(
						'<p class="eyebrow">' . esc_html__( 'Independent U.S. Lab Verification', 'amino-heaven' ) . '</p>',
						'<h1>' . esc_html__( 'Research compounds you can actually verify', 'amino-heaven' ) . '</h1>',
						'<p>' . esc_html__( 'Amino Heaven delivers American-synthesized, analytical-grade research peptides—each batch accompanied by an independent Certificate of Analysis from accredited U.S. laboratories. Explore. Discover. Pioneer.', 'amino-heaven' ) . '</p>',
					)
				),
			),
			'quality-coas'       => array(
				'title'   => __( 'Quality / COAs', 'amino-heaven' ),
				'content' => implode(
					"\n",
					array(
						'<p class="eyebrow">' . esc_html__( 'Documented at every step', 'amino-heaven' ) . '</p>',
						'<h1>' . esc_html__( 'Quality and Certificates of Analysis', 'amino-heaven' ) . '</h1>',
						'<p>' . esc_html__( 'Every lot is analysed by an independent laboratory for identity, purity and endotoxin levels before release. Certificates of Analysis are issued by accredited U.S. laboratories.', 'amino-heaven' ) . '</p>',
						'<h2>' . esc_html__( 'Tested for every batch', 'amino-heaven' ) . '</h2>',
						'<ul>',
						'<li>' . esc_html__( 'Identity — confirmed by mass spectrometry (MS)', 'amino-heaven' ) . '</li>',
						'<li>' . esc_html__( 'Purity — determined by high-performance liquid chromatography (HPLC), reported at ≥99%', 'amino-heaven' ) . '</li>',
						'<li>' . esc_html__( 'Endotoxin levels', 'amino-heaven' ) . '</li>',
						'</ul>',
						'<h2>' . esc_html__( 'Finding the certificate for your lot', 'amino-heaven' ) . '</h2>',
						'<p>' . esc_html__( 'Each product page links to the Certificate of Analysis for that batch. Where a lot-specific certificate has not been published yet, the product link returns to this page — send us your order number or lot reference and we will supply the document.', 'amino-heaven' ) . '</p>',
						'<div class="ah-notice">',
						'<div><strong>' . esc_html__( 'Certificates pending publication', 'amino-heaven' ) . '</strong>',
						'<p>' . esc_html__( 'Batch certificates are published as soon as the independent laboratory releases them. A product page that links here does not yet have a lot-specific certificate attached.', 'amino-heaven' ) . '</p></div>',
						'</div>',
					)
				),
			),
			'about'              => array(
				'title'   => __( 'About', 'amino-heaven' ),
				'content' => implode(
					"\n",
					array(
						'<p class="eyebrow">' . esc_html__( 'About Amino Heaven', 'amino-heaven' ) . '</p>',
						'<h1>' . esc_html__( 'Discover. Innovate. Elevate.', 'amino-heaven' ) . '</h1>',
						'<p>' . esc_html__( 'Amino Heaven delivers American-synthesized, analytical-grade research peptides—each batch accompanied by an independent Certificate of Analysis from accredited U.S. laboratories.', 'amino-heaven' ) . '</p>',
						'<h2>' . esc_html__( 'What we supply', 'amino-heaven' ) . '</h2>',
						'<p>' . esc_html__( 'Research compounds for controlled laboratory, analytical, and academic research use. Inventory is cataloged and sold exclusively for controlled laboratory, analytical, and academic research use by qualified personnel.', 'amino-heaven' ) . '</p>',
						'<h2>' . esc_html__( 'How we operate', 'amino-heaven' ) . '</h2>',
						'<p>' . esc_html__( 'Amino Heaven operates solely as an independent biochemical reagent supplier. Amino Heaven is not a compounding pharmacy or chemical compounding facility as defined under Section 503A of the Federal Food, Drug, and Cosmetic Act (FD&C Act), nor an outsourcing facility as defined under Section 503B of the FD&C Act.', 'amino-heaven' ) . '</p>',
						'<h2>' . esc_html__( 'Shipping', 'amino-heaven' ) . '</h2>',
						'<p>' . esc_html__( 'Ships same day if ordered before 1 PM PST.', 'amino-heaven' ) . '</p>',
						'<h2>' . esc_html__( 'Research support', 'amino-heaven' ) . '</h2>',
						'<p>',
						esc_html__( 'Research support is available Monday–Friday.', 'amino-heaven' ),
						' ',
						sprintf(
							/* translators: %s: contact email address. */
							esc_html__( 'Write to %s.', 'amino-heaven' ),
							'<a href="mailto:' . esc_attr( $contact_email ) . '">' . esc_html( $contact_email ) . '</a>'
						),
						'</p>',
					)
				),
			),
			'contact'            => array(
				'title'   => __( 'Contact', 'amino-heaven' ),
				'content' => implode(
					"\n",
					array(
						'<p class="eyebrow">' . esc_html__( 'Contact', 'amino-heaven' ) . '</p>',
						'<h1>' . esc_html__( 'Research support', 'amino-heaven' ) . '</h1>',
						'<p>',
						esc_html__( 'Research support is available Monday–Friday.', 'amino-heaven' ),
						' ',
						sprintf(
							/* translators: %s: contact email address. */
							esc_html__( 'Email %s.', 'amino-heaven' ),
							'<a href="mailto:' . esc_attr( $contact_email ) . '">' . esc_html( $contact_email ) . '</a>'
						),
						'</p>',
						'<h2>' . esc_html__( 'Before you write', 'amino-heaven' ) . '</h2>',
						'<ul>',
						'<li>' . esc_html__( 'Order and shipping questions — include your order number.', 'amino-heaven' ) . '</li>',
						'<li>' . esc_html__( 'Certificates of Analysis — include the product name and lot reference.', 'amino-heaven' ) . '</li>',
						'</ul>',
						'<div class="ah-notice">',
						'<div><strong>' . esc_html__( 'Scam warning', 'amino-heaven' ) . '</strong>',
						'<p>' . esc_html( get_theme_mod( 'amino_heaven_scam_warning', __( 'Scam warning: anyone who contacts you first to solicit an order is a scammer.', 'amino-heaven' ) ) ) . '</p></div>',
						'</div>',
					)
				),
			),
			'research-use-terms' => array(
				'title'   => __( 'Research Use & Terms', 'amino-heaven' ),
				'content' => implode(
					"\n",
					array(
						'<h1>' . esc_html__( 'Research Use and Terms', 'amino-heaven' ) . '</h1>',
						'<p>' . esc_html__( 'By entering this website and placing an order you confirm that you are 21 years of age or older, that you are qualified to handle laboratory research materials, and that you acknowledge that all products are for laboratory research use only and are not for human consumption.', 'amino-heaven' ) . '</p>',
						'<h2>' . esc_html__( 'Compliance statement', 'amino-heaven' ) . '</h2>',
						$compliance_html,
						'<h2>' . esc_html__( 'Orders', 'amino-heaven' ) . '</h2>',
						'<p>' . esc_html__( 'Orders are accepted exclusively from qualified personnel for controlled laboratory, analytical, and academic research use. Products are not for human or animal consumption, and are not for therapeutic or veterinary use.', 'amino-heaven' ) . '</p>',
					)
				),
			),
			'track-order'        => array(
				'title'   => __( 'Track Order', 'amino-heaven' ),
				'content' => '[woocommerce_order_tracking]',
			),
		);

		/**
		 * Filter the provisioned page definitions.
		 *
		 * @param array<string,array<string,string>> $definitions Page definitions keyed by slug.
		 */
		return apply_filters( 'amino_heaven_page_definitions', $definitions );
	}

	/**
	 * Create the pages.
	 *
	 * @return array<string,int> Slug => post id.
	 */
	private function create_pages() {
		$ids = array();

		foreach ( $this->page_definitions() as $slug => $definition ) {
			$existing = get_page_by_path( $slug );

			if ( $existing instanceof WP_Post ) {
				$ids[ $slug ] = (int) $existing->ID;

				if ( $this->update && ! empty( $definition['content'] ) ) {
					wp_update_post(
						array(
							'ID'           => $existing->ID,
							'post_content' => $definition['content'],
						)
					);
					$this->note( sprintf( 'Refreshed content for page "%s"', $slug ) );
				}

				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $definition['title'],
					'post_name'    => $slug,
					'post_content' => $definition['content'],
					'post_author'  => $this->get_admin_user_id(),
				),
				true
			);

			if ( is_wp_error( $page_id ) ) {
				$this->note( sprintf( 'ERROR creating page "%s": %s', $slug, $page_id->get_error_message() ) );
				continue;
			}

			$ids[ $slug ] = (int) $page_id;
			$this->note( sprintf( 'Created page "%s" (/%s/)', $definition['title'], $slug ) );
		}

		return $ids;
	}

	/**
	 * Point the site's front page at the Home page.
	 *
	 * @param array<string,int> $pages Slug => post id.
	 */
	private function assign_front_page( $pages ) {
		if ( empty( $pages['home'] ) ) {
			return;
		}

		$home_id = (int) $pages['home'];

		if ( 'page' !== get_option( 'show_on_front' ) ) {
			update_option( 'show_on_front', 'page' );
			$this->note( 'Front page set to a static page' );
		}

		if ( (int) get_option( 'page_on_front' ) !== $home_id ) {
			update_option( 'page_on_front', $home_id );
			$this->note( 'Assigned the Home page as the front page' );
		}
	}

	/**
	 * Point WooCommerce's checkout at the research-use terms page.
	 *
	 * This has to happen after the page exists: `setup-store` also tries, but on a
	 * fresh install it runs before this command has created the page.
	 *
	 * @param array<string,int> $pages Slug => post id.
	 */
	private function assign_terms_page( $pages ) {
		if ( empty( $pages['research-use-terms'] ) ) {
			return;
		}

		$terms_id = (int) $pages['research-use-terms'];

		if ( (int) get_option( 'woocommerce_terms_page_id' ) === $terms_id ) {
			return;
		}

		update_option( 'woocommerce_terms_page_id', $terms_id );
		$this->note( 'Assigned the Research Use & Terms page to WooCommerce checkout' );
	}

	/**
	 * Publish and assign WordPress's Privacy Policy page when one exists.
	 */
	private function assign_privacy_page() {
		$current = (int) get_option( 'wp_page_for_privacy_policy' );

		if ( $current > 0 && get_post( $current ) instanceof WP_Post ) {
			return;
		}

		$draft = get_page_by_path( 'privacy-policy', OBJECT, 'page' );

		if ( ! $draft instanceof WP_Post ) {
			return;
		}

		if ( 'publish' !== $draft->post_status ) {
			wp_update_post(
				array(
					'ID'          => $draft->ID,
					'post_status' => 'publish',
				)
			);
		}

		update_option( 'wp_page_for_privacy_policy', $draft->ID );
		$this->note( 'Assigned the WordPress Privacy Policy page' );
	}

	/**
	 * Create and assign the theme's navigation menus.
	 *
	 * @param array<string,int> $pages Slug => post id.
	 */
	private function create_menus( $pages ) {
		$privacy_id = (int) get_option( 'wp_page_for_privacy_policy' );

		// The Shop entry points at WooCommerce's own shop page, not a theme page.
		$shop_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;

		if ( $shop_id <= 0 ) {
			$shop_page = get_page_by_path( 'shop' );
			$shop_id   = $shop_page instanceof WP_Post ? (int) $shop_page->ID : 0;
		}

		$menus = array(
			'primary' => array(
				'name'  => __( 'Primary navigation', 'amino-heaven' ),
				'items' => array_filter(
					array(
						array( 'shop', $shop_id, __( 'Shop', 'amino-heaven' ) ),
						array( 'quality-coas', $pages['quality-coas'] ?? 0, __( 'Quality / COAs', 'amino-heaven' ) ),
						array( 'about', $pages['about'] ?? 0, __( 'About', 'amino-heaven' ) ),
						array( 'contact', $pages['contact'] ?? 0, __( 'Contact', 'amino-heaven' ) ),
					)
				),
			),
			'footer'  => array(
				'name'  => __( 'Footer quick links', 'amino-heaven' ),
				'items' => array_filter(
					array(
						array( 'shop', $shop_id, __( 'Shop Catalog', 'amino-heaven' ) ),
						array( 'quality-coas', $pages['quality-coas'] ?? 0, __( 'Quality Control / COAs', 'amino-heaven' ) ),
						array( 'track-order', $pages['track-order'] ?? 0, __( 'Track Order', 'amino-heaven' ) ),
						array( 'about', $pages['about'] ?? 0, __( 'About Us', 'amino-heaven' ) ),
					)
				),
			),
			'legal'   => array(
				'name'  => __( 'Footer legal links', 'amino-heaven' ),
				'items' => array_filter(
					array(
						array( 'research-use-terms', $pages['research-use-terms'] ?? 0, __( 'Research Use & Terms', 'amino-heaven' ) ),
						array( 'privacy-policy', $privacy_id, __( 'Privacy Policy', 'amino-heaven' ) ),
					)
				),
			),
		);

		$locations = get_theme_mod( 'nav_menu_locations', array() );
		$locations = is_array( $locations ) ? $locations : array();

		foreach ( $menus as $location => $menu ) {
			$menu_id = $this->ensure_menu( $menu['name'], $menu['items'] );

			if ( ! $menu_id ) {
				continue;
			}

			if ( ! isset( $locations[ $location ] ) || (int) $locations[ $location ] !== $menu_id ) {
				$locations[ $location ] = $menu_id;
				$this->note( sprintf( 'Assigned menu "%s" to %s', $menu['name'], $location ) );
			}
		}

		set_theme_mod( 'nav_menu_locations', $locations );
	}

	/**
	 * Create a menu (once) and populate it (once).
	 *
	 * @param string  $name  Menu name.
	 * @param array[] $items List of [slug, page id, label].
	 * @return int Menu id, or 0 on failure.
	 */
	private function ensure_menu( $name, $items ) {
		$menu = wp_get_nav_menu_object( $name );

		if ( $menu ) {
			$menu_id = (int) $menu->term_id;
		} else {
			$menu_id = (int) wp_create_nav_menu( $name );

			if ( ! $menu_id ) {
				$this->note( sprintf( 'ERROR creating menu "%s"', $name ) );
				return 0;
			}

			$this->note( sprintf( 'Created menu "%s"', $name ) );
		}

		$existing_items = wp_get_nav_menu_items( $menu_id );

		if ( ! empty( $existing_items ) ) {
			return $menu_id;
		}

		$position = 0;

		foreach ( $items as $item ) {
			list( $slug, $page_id, $label ) = $item;

			$page_id = (int) $page_id;

			if ( $page_id <= 0 ) {
				continue;
			}

			// The Shop entry points at WooCommerce's shop page.
			++$position;

			$result = wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => $label,
					'menu-item-object'    => 'page',
					'menu-item-object-id' => $page_id,
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
					'menu-item-position'  => $position,
				)
			);

			if ( is_wp_error( $result ) ) {
				$this->note( sprintf( 'ERROR adding menu item "%s": %s', $label, $result->get_error_message() ) );
			}
		}

		return $menu_id;
	}

	/**
	 * Set the site icon from the theme's bundled favicon when none is configured.
	 */
	private function set_site_icon() {
		if ( (int) get_option( 'site_icon' ) > 0 ) {
			return;
		}

		$source = AMINO_HEAVEN_DIR . 'assets/images/favicon.png';

		if ( ! is_readable( $source ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( 'favicon.png' );

		if ( ! $tmp || ! copy( $source, $tmp ) ) {
			$this->note( 'Could not stage the favicon for the site icon' );

			return;
		}

		$attachment_id = media_handle_sideload(
			array(
				'name'     => 'amino-heaven-favicon.png',
				'tmp_name' => $tmp,
			),
			0,
			__( 'Amino Heaven site icon', 'amino-heaven' )
		);

		if ( is_wp_error( $attachment_id ) ) {
			$this->note( sprintf( 'Could not import the site icon: %s', $attachment_id->get_error_message() ) );

			return;
		}

		update_option( 'site_icon', (int) $attachment_id );
		$this->note( 'Set the site icon from the theme favicon' );
	}

	/**
	 * First administrator, used as the author for provisioned pages.
	 *
	 * @return int
	 */
	private function get_admin_user_id() {
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'fields'  => 'ID',
			)
		);

		return $admins ? (int) $admins[0] : 0;
	}
}
