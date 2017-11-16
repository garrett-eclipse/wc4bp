<?php
/**
 * @package        WordPress
 * @subpackage        BuddyPress, Woocommerce
 * @author            Boris Glumpler
 * @copyright        2011, Themekraft
 * @link            https://github.com/Themekraft/BP-Shop-Integration
 * @license            http://www.opensource.org/licenses/gpl-2.0.php GPL License
 */
// No direct access is allowed
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC4BP_Component extends BP_Component {
	/**
	 * Holds the ID of the component
	 *
	 * @var        string
	 * @since   1.0
	 */
	public $id = 'shop';
	public $template_directory;

	/**
	 * Start the shop component creation process
	 *
	 * @since     1.0
	 */
	function __construct() {
		parent::start( $this->id, __( 'Shop', 'wc4bp' ), WC4BP_ABSPATH );
		$this->includes();
		add_action( 'bp_register_activity_actions', array( $this, 'register_activity_actions' ) );
		add_filter( 'bp_located_template', array( $this, 'wc4bp_members_load_template_filter' ), 10, 2 );
	}

	/**
	 * Include files
	 *
	 * @since     1.0
	 *
	 * @param array $includes
	 *
	 */
	function includes( $includes = array() ) {
		try {
			$wc4bp_options = get_option( 'wc4bp_options' );
			$includes      = array(
				'wc4bp-helpers',
				'wc4bp-conditionals',
				'wc4bp-screen',
				'wc4bp-redirect',
				'wc4bp-deprecated',
			);
			foreach ( $includes as $file ) {
				require( WC4BP_ABSPATH . 'class/core/' . $file . '.php' );
			}
			if ( ! class_exists( 'BP_Theme_Compat' ) ) {
				require( WC4BP_ABSPATH . 'class/core/wc4bp-template-compatibility.php' );
			}
			if ( ! isset( $wc4bp_options['tab_sync_disabled'] ) || class_exists( 'WC4BP_xProfile' ) ) {
				require( WC4BP_ABSPATH . 'class/core/wc4bp-sync.php' );
				new wc4bp_Sync();
			}
			new wc4bp_redirect();
		} catch ( Exception $exception ) {
			WC4BP_Loader::get_exception_handler()->save_exception( $exception->getTrace() );
		}
	}

	/**
	 * Register acctivity actions
	 *
	 * @since     1.0.4
	 */
	function register_activity_actions() {
		try {
			if ( ! bp_is_active( 'activity' ) ) {
				return false;
			}
			bp_activity_set_action( $this->id, 'new_shop_review', __( 'New review created', 'wc4bp' ) );
			bp_activity_set_action( $this->id, 'new_shop_purchase', __( 'New purchase made', 'wc4bp' ) );
			do_action( 'wc4bp_register_activity_actions' );
		} catch ( Exception $exception ) {
			WC4BP_Loader::get_exception_handler()->save_exception( $exception->getTrace() );
		}
	}

	/**
	 * Setup globals
	 *
	 * @since     1.0
	 *
	 * @param array $globals
	 *
	 * @global    object $bp
	 */
	function setup_globals( $globals = array() ) {
		global $bp;
		try {
			$globals = array(
				'path'          => WC4BP_ABSPATH . 'core',
				'slug'          => 'shop',
				'has_directory' => false,
			);
			parent::setup_globals( $globals );
		} catch ( Exception $exception ) {
			WC4BP_Loader::get_exception_handler()->save_exception( $exception->getTrace() );
		}
	}

	public function get_nav_item( $shop_link, $key, $title ) {
		$id = str_replace( '-', '_', $key );

		return array(
			'name'            => $title,
			'slug'            => $key,
			'parent_url'      => $shop_link,
			'parent_slug'     => $this->slug,
			'screen_function' => 'wc4bp_screen_' . $id,
			'position'        => 10,
			'item_css_id'     => 'shop-' . $id,
			'user_has_access' => bp_is_my_profile(),
		);
	}

	/**
	 * Setup BuddyBar navigation
	 *
	 * @since    1.0
	 *
	 * @param array $main_nav
	 * @param array $sub_nav
	 *
	 * @return bool
	 * @global   object $bp
	 */
	function setup_nav( $main_nav = array(), $sub_nav = array() ) {
		try {
			global $woocommerce;
			if ( ! function_exists( 'bp_get_settings_slug' ) ) {
				return false;
			}
			$wc4bp_options = get_option( 'wc4bp_options' );
			if ( ! empty( $wc4bp_options['tab_activity_disabled'] ) ) {
				return false;
			}
			$wc4bp_pages_options = get_option( 'wc4bp_pages_options' );
			if ( ! empty( $wc4bp_pages_options ) && is_string( $wc4bp_pages_options ) ) {
				$wc4bp_pages_options = json_decode( $wc4bp_pages_options, true );
			}
			// Add 'Shop' to the main navigation
			if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
				$name = apply_filters( 'bp_shop_link_label', __( 'Shop', 'wc4bp' ) );
			} else {
				$name = __( 'Shop', 'wc4bp' );
			}
			$main_nav  = array(
				'name'                    => $name,
				'slug'                    => $this->slug,
				'position'                => 70,
				'screen_function'         => 'wc4bp_screen_plugins',
				'default_subnav_slug'     => 'home',
				'item_css_id'             => $this->id,
				'show_for_displayed_user' => false,
			);
			$shop_link = trailingslashit( bp_loggedin_user_domain() . $this->slug );

			//All endpoints
			$endpoints = wc4bp_Manager::available_endpoint();
			foreach ( $endpoints as $key => $title ) {
				if ( in_array( $key, array( 'cart', 'checkout', 'track', 'history' ), true ) ) {
					if ( ! isset( $wc4bp_options[ 'tab_' . $key . '_disabled' ] ) ) {
						switch ( $key ) {
							case 'checkout':
								global $woocommerce;
								// Add the checkout nav item, if cart empty do not add.
								/** @var WC_Session_Handler $wc_session_data */
								$wc_session_data = $woocommerce->session;
								if ( ! empty( $wc_session_data ) ) {
									$session_cart = $wc_session_data->get( 'cart' );
									if ( ! is_admin() && ! empty( $session_cart ) ) {
										$sub_nav[] = $this->get_nav_item( $shop_link, $key, $title );
									}
								}
								break;
							case 'cart':
							case 'history':
							case 'track':
								$sub_nav[] = $this->get_nav_item( $shop_link, $key, $title );
								break;
						}
					}
				} elseif ( in_array( $key, array( 'orders', 'downloads', 'edit-address', 'payment-methods', 'edit-account' ), true ) ) {
					if ( empty( $wc4bp_options[ 'wc4bp_endpoint_' . $key ] ) ) {
						$sub_nav[] = $this->get_nav_item( $shop_link, $key, $title );
					}
				}
			}

			// Add the cart nav item
//			if ( ! isset( $wc4bp_options['tab_cart_disabled'] ) ) {
//				if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
//					$name = apply_filters( 'bp_cart_link_label', __( 'Shopping Cart', 'wc4bp' ) );
//				} else {
//					$name = __( 'Shopping Cart', 'wc4bp' );
//				}
//				$sub_nav[] = array(
//					'name'            => $name,
//					'slug'            => 'cart',
//					'parent_url'      => $shop_link,
//					'parent_slug'     => $this->slug,
//					'screen_function' => 'wc4bp_screen_cart',
//					'position'        => 10,
//					'item_css_id'     => 'shop-cart',
//					'user_has_access' => bp_is_my_profile(),
//				);
//			}
//			global $woocommerce;
//			// Add the checkout nav item, if cart empty do not add.
//			/** @var WC_Session_Handler $wc_session_data */
//			$wc_session_data = $woocommerce->session;
//			if ( ! empty( $wc_session_data ) ) {
//				$session_cart = $wc_session_data->get( 'cart' );
//				if ( ! is_admin() && ! empty( $session_cart ) && ! isset( $wc4bp_options['tab_checkout_disabled'] ) ) {
//					if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
//						$name = apply_filters( 'bp_checkout_link_label', __( 'Checkout', 'wc4bp' ) );
//					} else {
//						$name = __( 'Checkout', 'wc4bp' );
//					}
//					$sub_nav[] = array(
//						'name'            => $name,
//						'slug'            => 'checkout',
//						'parent_url'      => $shop_link,
//						'parent_slug'     => $this->slug,
//						'screen_function' => 'wc4bp_screen_checkout',
//						'position'        => 10,
//						'item_css_id'     => 'shop-checkout',
//						'user_has_access' => bp_is_my_profile(),
//					);
//				}
//			}
			// Add the history nav item
//			if ( ! isset( $wc4bp_options['tab_history_disabled'] ) ) {
//				if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
//					$name = apply_filters( 'bp_history_link_label', __( 'History', 'wc4bp' ) );
//				} else {
//					$name = __( 'History', 'wc4bp' );
//				}
//				$sub_nav[] = array(
//					'name'            => $name,
//					'slug'            => 'history',
//					'parent_url'      => $shop_link,
//					'parent_slug'     => $this->slug,
//					'screen_function' => 'wc4bp_screen_history',
//					'position'        => 30,
//					'item_css_id'     => 'shop-history',
//					'user_has_access' => bp_is_my_profile(),
//				);
//			}
//			// Add the Track nav item
//			if ( ! isset( $wc4bp_options['tab_track_disabled'] ) ) {
//				if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
//					$name = apply_filters( 'bp_track_order_link_label', __( 'Track your order', 'wc4bp' ) );
//				} else {
//					$name = __( 'Track your order', 'wc4bp' );
//				}
//				$sub_nav[] = array(
//					'name'            => $name,
//					'slug'            => 'track',
//					'parent_url'      => $shop_link,
//					'parent_slug'     => $this->slug,
//					'screen_function' => 'wc4bp_screen_track',
//					'position'        => 30,
//					'item_css_id'     => 'shop-track',
//					'user_has_access' => bp_is_my_profile(),
//				);
//			}


			//************************************************************
			// Add the Order nav item
//			if ( ! isset( $wc4bp_options['tab_track_disabled'] ) ) {
//			if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
//				$name = apply_filters( 'bp_orders_link_label', __( 'Order', 'wc4bp' ) );
//			} else {
//				$name = __( 'Order', 'wc4bp' );
//			}
//			$sub_nav[] = array(
//				'name'            => $name,
//				'slug'            => 'orders',
//				'parent_url'      => $shop_link,
//				'parent_slug'     => $this->slug,
//				'screen_function' => 'wc4bp_screen_orders',
//				'position'        => 30,
//				'item_css_id'     => 'shop-track',
//				'user_has_access' => bp_is_my_profile(),
//			);
//
//			if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
//				$name = apply_filters( 'bp_downloads_link_label', __( 'Downloads', 'wc4bp' ) );
//			} else {
//				$name = __( 'Downloads', 'wc4bp' );
//			}
//			$sub_nav[] = array(
//				'name'            => $name,
//				'slug'            => 'downloads',
//				'parent_url'      => $shop_link,
//				'parent_slug'     => $this->slug,
//				'screen_function' => 'wc4bp_screen_downloads',
//				'position'        => 30,
//				'item_css_id'     => 'shop-track',
//				'user_has_access' => bp_is_my_profile(),
//			);
//
//			if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
//				$name = apply_filters( 'bp_edit_account_link_label', __( 'Edit Account', 'wc4bp' ) );
//			} else {
//				$name = __( 'Edit Account', 'wc4bp' );
//			}
//			$sub_nav[] = array(
//				'name'            => $name,
//				'slug'            => 'edit-account',
//				'parent_url'      => $shop_link,
//				'parent_slug'     => $this->slug,
//				'screen_function' => 'wc4bp_screen_edit_account',
//				'position'        => 30,
//				'item_css_id'     => 'shop-track',
//				'user_has_access' => bp_is_my_profile(),
//			);
//
//			if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
//				$name = apply_filters( 'bp_edit_address_link_label', __( 'Edit Address', 'wc4bp' ) );
//			} else {
//				$name = __( 'Edit Address', 'wc4bp' );
//			}
//			$sub_nav[] = array(
//				'name'            => $name,
//				'slug'            => 'edit-address',
//				'parent_url'      => $shop_link,
//				'parent_slug'     => $this->slug,
//				'screen_function' => 'wc4bp_screen_edit_address',
//				'position'        => 30,
//				'item_css_id'     => 'shop-track',
//				'user_has_access' => bp_is_my_profile(),
//			);
//
//			if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
//				$name = apply_filters( 'bp_payment_method_link_label', __( 'Payment Method', 'wc4bp' ) );
//			} else {
//				$name = __( 'Payment Method', 'wc4bp' );
//			}
//			$sub_nav[] = array(
//				'name'            => $name,
//				'slug'            => 'payment-methods',
//				'parent_url'      => $shop_link,
//				'parent_slug'     => $this->slug,
//				'screen_function' => 'wc4bp_screen_edit_payment_methods',
//				'position'        => 30,
//				'item_css_id'     => 'shop-track',
//				'user_has_access' => bp_is_my_profile(),
//			);

//			} ************************************************************
			// Add shop settings subpage
			if ( ! isset( $wc4bp_options['disable_shop_settings_tab'] ) ) {
				if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
					$name = apply_filters( 'bp_shop_settings_link_label', __( 'Shop', 'wc4bp' ) );
				} else {
					$name = __( 'Shop', 'wc4bp' );
				}
				$sub_nav[] = array(
					'name'            => $name,
					'slug'            => 'shop',
					'parent_url'      => trailingslashit( bp_loggedin_user_domain() . bp_get_settings_slug() ),
					'parent_slug'     => bp_get_settings_slug(),
					'screen_function' => 'wc4bp_screen_settings',
					'position'        => 30,
					'item_css_id'     => 'shop-settings',
					'user_has_access' => bp_is_my_profile(),
				);
			}
			$position = 40;
			if ( isset( $wc4bp_pages_options['selected_pages'] ) && is_array( $wc4bp_pages_options['selected_pages'] ) ) {
				foreach ( $wc4bp_pages_options['selected_pages'] as $key => $attached_page ) {
					$position ++;
					$post      = get_post( $attached_page['page_id'] );
					$sub_nav[] = array(
						'name'            => $attached_page['tab_name'],
						'slug'            => esc_html( $post->post_name ),
						'parent_url'      => $shop_link,
						'parent_slug'     => $this->slug,
						'screen_function' => 'wc4bp_screen_plugins',
						'position'        => $position,
						'item_css_id'     => 'shop-cart',
						'user_has_access' => bp_is_my_profile(),
					);
				}
			}
			$sub_nav = apply_filters( 'bp_shop_sub_nav', $sub_nav, $shop_link, $this->slug );
			do_action( 'bp_shop_setup_nav' );
			parent::setup_nav( $main_nav, $sub_nav );
		} catch ( Exception $exception ) {
			WC4BP_Loader::get_exception_handler()->save_exception( $exception->getTrace() );
		}
	}

	/**
	 * Set up the Toolbar
	 *
	 * @param array $wp_admin_nav
	 *
	 * @return bool|void
	 * @global BuddyPress $bp The one true BuddyPress instance
	 */
	function setup_admin_bar( $wp_admin_nav = array() ) {
		try {
			global $bp;
			$wc4bp_options = get_option( 'wc4bp_options' );
			if ( ! empty( $wc4bp_options['tab_activity_disabled'] ) ) {
				return false;
			}
			$wc4bp_pages_options = get_option( 'wc4bp_pages_options' );
			if ( ! empty( $wc4bp_pages_options ) && is_string( $wc4bp_pages_options ) ) {
				$wc4bp_pages_options = json_decode( $wc4bp_pages_options, true );
			}
			$wp_admin_nav = array();
			if ( is_user_logged_in() ) {
				$user_domain   = bp_loggedin_user_domain();
				$settings_link = trailingslashit( $user_domain . BP_SETTINGS_SLUG );
				if ( ! isset( $wc4bp_options['disable_shop_settings_tab'] ) ) {
					if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
						$title = apply_filters( 'bp_shop_settings_nav_link_label', __( 'Shop', 'wc4bp' ) );
					} else {
						$title = __( 'Shop', 'wc4bp' );
					}
					// Shop settings menu
					$wp_admin_nav[] = array(
						'parent' => 'my-account-settings',
						'id'     => 'my-account-settings-shop',
						'title'  => $title,
						'href'   => trailingslashit( $settings_link . 'shop' ),
					);
				}
				$shop_link = trailingslashit( $user_domain . $this->id );
				if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
					$title = apply_filters( 'bp_shop_nav_link_label', __( 'Shop', 'wc4bp' ) );
				} else {
					$title = __( 'Shop', 'wc4bp' );
				}
				// Shop menu items
				$wp_admin_nav[] = array(
					'parent' => $bp->my_account_menu_id,
					'id'     => 'my-account-' . $this->id,
					'title'  => $title,
					'href'   => trailingslashit( $shop_link ),
					'meta'   => array(
						'class' => 'menupop',
					),
				);
				if ( ! isset( $wc4bp_options['tab_cart_disabled'] ) ) {
					if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
						$title = apply_filters( 'bp_shop_cart_nav_link_label', __( 'Shopping Cart', 'wc4bp' ) );
					} else {
						$title = __( 'Shopping Cart', 'wc4bp' );
					}
					$wp_admin_nav[] = array(
						'parent' => 'my-account-' . $this->id,
						'id'     => 'my-account-' . $this->id . '-cart',
						'title'  => $title,
						'href'   => trailingslashit( $shop_link . 'cart' ),
					);
				}
				if ( ! is_admin() && is_object( WC()->cart ) && ! WC()->cart->is_empty() && ! isset( $wc4bp_options['tab_checkout_disabled'] ) ) {
					if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
						$title = apply_filters( 'bp_checkout_nav_link_label', __( 'Checkout', 'wc4bp' ) );
					} else {
						$title = __( 'Checkout', 'wc4bp' );
					}
					$wp_admin_nav[] = array(
						'parent' => 'my-account-' . $this->id,
						'id'     => 'my-account-' . $this->id . '-checkout',
						'title'  => $title,
						'href'   => trailingslashit( $shop_link . 'checkout' ),
					);
				}
				if ( ! isset( $wc4bp_options['tab_history_disabled'] ) ) {
					if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
						$title = apply_filters( 'bp_history_nav_link_label', __( 'History', 'wc4bp' ) );
					} else {
						$title = __( 'History', 'wc4bp' );
					}
					$wp_admin_nav[] = array(
						'parent' => 'my-account-' . $this->id,
						'id'     => 'my-account-' . $this->id . '-history',
						'title'  => $title,
						'href'   => trailingslashit( $shop_link . 'history' ),
					);
				}
				if ( ! isset( $wc4bp_options['tab_track_disabled'] ) ) {
					if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
						$title = apply_filters( 'bp_track_order_nav_link_label', __( 'Track your order', 'wc4bp' ) );
					} else {
						$title = __( 'Track your order', 'wc4bp' );
					}
					$wp_admin_nav[] = array(
						'parent' => 'my-account-' . $this->id,
						'id'     => 'my-account-' . $this->id . '-track',
						'title'  => $title,
						'href'   => trailingslashit( $shop_link . 'track' ),
					);
				}
				//******************************************************
				if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
					$title = apply_filters( 'bp_order_nav_link_label', __( 'Order', 'wc4bp' ) );
				} else {
					$title = __( 'Order', 'wc4bp' );
				}
				$wp_admin_nav[] = array(
					'parent' => 'my-account-' . $this->id,
					'id'     => 'my-account-' . $this->id . '-orders',
					'title'  => $title,
					'href'   => trailingslashit( $shop_link . 'orders' ),
				);

				if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
					$title = apply_filters( 'bp_downloads_nav_link_label', __( 'Downloads', 'wc4bp' ) );
				} else {
					$title = __( 'Downloads', 'wc4bp' );
				}
				$wp_admin_nav[] = array(
					'parent' => 'my-account-' . $this->id,
					'id'     => 'my-account-' . $this->id . '-downloads',
					'title'  => $title,
					'href'   => trailingslashit( $shop_link . 'downloads' ),
				);

				if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
					$title = apply_filters( 'bp_edit_account_nav_link_label', __( 'Edit Account', 'wc4bp' ) );
				} else {
					$title = __( 'Edit Account', 'wc4bp' );
				}
				$wp_admin_nav[] = array(
					'parent' => 'my-account-' . $this->id,
					'id'     => 'my-account-' . $this->id . '-edit-account',
					'title'  => $title,
					'href'   => trailingslashit( $shop_link . 'edit-account' ),
				);

				if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
					$title = apply_filters( 'bp_edit_address_nav_link_label', __( 'Edit Address', 'wc4bp' ) );
				} else {
					$title = __( 'Edit Address', 'wc4bp' );
				}
				$wp_admin_nav[] = array(
					'parent' => 'my-account-' . $this->id,
					'id'     => 'my-account-' . $this->id . '-edit-address',
					'title'  => $title,
					'href'   => trailingslashit( $shop_link . 'edit-address' ),
				);

				if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
					$title = apply_filters( 'bp_payment_methods_nav_link_label', __( 'Payment Method', 'wc4bp' ) );
				} else {
					$title = __( 'Payment Method', 'wc4bp' );
				}
				$wp_admin_nav[] = array(
					'parent' => 'my-account-' . $this->id,
					'id'     => 'my-account-' . $this->id . '-payment-methods',
					'title'  => $title,
					'href'   => trailingslashit( $shop_link . 'payment-methods' ),
				);
				//******************************************************
				if ( isset( $wc4bp_pages_options['selected_pages'] ) && is_array( $wc4bp_pages_options['selected_pages'] ) ) {
					foreach ( $wc4bp_pages_options['selected_pages'] as $key => $attached_page ) {
						$wp_admin_nav[] = array(
							'parent' => 'my-account-' . $this->id,
							'id'     => 'my-account-' . $this->id . '-' . $attached_page['tab_slug'],
							'title'  => $attached_page['tab_name'],
							'href'   => trailingslashit( $shop_link . $attached_page['tab_slug'] ),
						);
					}
				}
				parent::setup_admin_bar( $wp_admin_nav );
			}
		} catch ( Exception $exception ) {
			WC4BP_Loader::get_exception_handler()->save_exception( $exception->getTrace() );
		}
	}

	/**
	 * WC4BP template loader.
	 * @since 1.0
	 *
	 * @param $found_template
	 * @param $templates
	 *
	 * @return mixed
	 */
	function wc4bp_members_load_template_filter( $found_template, $templates ) {
		try {
			global $bp;
			if ( ! bp_is_current_component( 'shop' ) ) {
				return $found_template;
			}
			$path                     = 'shop/member/plugin';
			$this->template_directory = apply_filters( 'wc4bp_members_get_template_directory', constant( 'WC4BP_ABSPATH_TEMPLATE_PATH' ) );
			$path                     = apply_filters( 'wc4bp_load_template_path', $path, $this->template_directory );
			bp_register_template_stack( array( $this, 'wc4bp_members_get_template_directory' ), 14 );
			if ( in_array( $bp->current_action, array_keys( wc4bp_Manager::available_endpoint() ), true ) ) {
				$found_template = locate_template( 'members/single/plugins.php', false, false );
				$wc4bp_options  = get_option( 'wc4bp_options' );
				$cart_page_id   = wc_get_page_id( 'cart' );
				$cart_page      = get_post( $cart_page_id, ARRAY_A );
				$cart_slug      = $cart_page['post_name'];
				switch ( $bp->current_action ) {
					case 'home':
						if ( $wc4bp_options['tab_shop_default'] != 'default' ) {
							$bp->current_action = $wc4bp_options['tab_shop_default'];
							switch ( $bp->current_action ) {
								case 'cart':
									$path = 'shop/member/cart';
									break;
								case 'checkout':
									$path = 'shop/member/checkout';
									break;
								case 'history':
									$path = 'shop/member/history';
									break;
								case 'track':
									$path = 'shop/member/track';
									break;
								case 'orders':
									$page                = 'orders';
									$bp_action_variables = $bp->action_variables;
									if ( ! empty( $bp_action_variables ) ) {
										foreach ( $bp_action_variables as $var ) {
											if ( 'view-order' === $var ) {
												$page = 'view-order';
												break;
											}
										}
									}
									$path = 'shop/member/' . $page;
									break;
								case 'downloads':
									$path = 'shop/member/downloads';
									break;
								case 'edit-account':
									$path = 'shop/member/edit-account';
									break;
								case 'edit-address':
									$path = 'shop/member/edit-address';
									break;
								case 'payment-methods':
									$path = 'shop/member/payment-methods';
									break;
							}
						} else {
							if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
								if ( empty( $wc4bp_options['tab_cart_disabled'] ) ) {
									$bp->current_action = $cart_slug;
									$path               = 'shop/member/cart';
								} else {
									$wc_active_endpoints = WC4BP_MyAccount::get_active_endpoints__premium_only();
									if ( ! empty( $wc_active_endpoints ) && count( $wc_active_endpoints ) > 0 ) {
										reset( $wc_active_endpoints );
										$page_name          = key( $wc_active_endpoints );
										$bp->current_action = $page_name;
									} else {
										if ( empty( $wc4bp_options['tab_checkout_disabled'] ) ) {
											$path = 'shop/member/checkout';
										}
										if ( empty( $wc4bp_options['tab_history_disabled'] ) ) {
											$path = 'shop/member/history';
										}
										if ( empty( $wc4bp_options['tab_track_disabled'] ) ) {
											$path = 'shop/member/track';
										}
										$wc4bp_pages_options = get_option( 'wc4bp_pages_options' );
										if ( ! empty( $wc4bp_pages_options ) && is_string( $wc4bp_pages_options ) ) {
											$wc4bp_pages_options = json_decode( $wc4bp_pages_options, true );
										}
										if ( isset( $wc4bp_pages_options['selected_pages'] ) && is_array( $wc4bp_pages_options['selected_pages'] ) ) {
											foreach ( $wc4bp_pages_options['selected_pages'] as $key => $attached_page ) {
												$bp->current_action = $attached_page['tab_slug'];
												break;
											}
										}
									}
								}
							} else {
								if ( empty( $wc4bp_options['tab_cart_disabled'] ) ) {
									$bp->current_action = $cart_slug;
									$path               = 'shop/member/cart';
								} else {
									if ( WC4BP_Loader::getFreemius()->is_plan__premium_only( wc4bp_base::$professional_plan_id ) ) {
										$wc_active_endpoints = WC4BP_MyAccount::get_active_endpoints__premium_only();
										if ( ! empty( $wc_active_endpoints ) && count( $wc_active_endpoints ) > 1 ) {
											reset( $wc_active_endpoints );
											$page_name          = key( $wc_active_endpoints );
											$bp->current_action = $page_name;
										}
									}
								}
							}
						}
						break;
					case 'cart':
						$path = 'shop/member/cart';
						break;
					case 'checkout':
						$path = 'shop/member/checkout';
						break;
					case 'history':
						$path = 'shop/member/history';
						break;
					case 'track':
						$path = 'shop/member/track';
						break;
					case 'orders':
						$page                = 'orders';
						$bp_action_variables = $bp->action_variables;
						if ( ! empty( $bp_action_variables ) ) {
							foreach ( $bp_action_variables as $var ) {
								if ( 'view-order' === $var ) {
									$page = 'view-order';
									break;
								}
							}
						}
						$path = 'shop/member/' . $page;
						break;
					case 'downloads':
						$path = 'shop/member/downloads';
						break;
					case 'edit-account':
						$path = 'shop/member/edit-account';
						break;
					case 'edit-address':
						$path = 'shop/member/edit-address';
						break;
					case 'payment-methods':
						$path = 'shop/member/payment-methods';
						break;
				}
			}
			add_action( 'bp_template_content',
				create_function( '', "bp_get_template_part( '" . $path . "' );" )
			);

			return apply_filters( 'wc4bp_members_load_template_filter_founded', $found_template );
		} catch ( Exception $exception ) {
			WC4BP_Loader::get_exception_handler()->save_exception( $exception->getTrace() );
		}
	}

	/**
	 * Get the WC4BP template directory
	 *
	 * @package WC4BP
	 * @since 0.1 beta
	 *
	 * @uses apply_filters()
	 * @return string
	 */
	public function wc4bp_members_get_template_directory() {
		return $this->template_directory;
	}
}
