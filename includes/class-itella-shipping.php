<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the dashboard.
 *
 * @link
 * @since      1.0.0
 *
 * @package    Itella_Woocommerce
 * @subpackage Itella_Woocommerce/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, dashboard-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Itella_Woocommerce
 * @subpackage Itella_Woocommerce/includes
 * @author     Your Name <email@example.com>
 */
class Itella_Shipping
{

  /**
   * The loader that's responsible for maintaining and registering all hooks that power
   * the plugin.
   *
   * @since    1.0.0
   * @access   protected
   * @var      Itella_Shipping_Loader $loader Maintains and registers all hooks for the plugin.
   */
  protected $loader;

  /**
   * The unique identifier of this plugin.
   *
   * @since    1.0.0
   * @access   protected
   * @var      string $plugin_name The string used to uniquely identify this plugin.
   */
  protected $plugin_name;

  /**
   * The current version of the plugin.
   *
   * @since    1.0.0
   * @access   protected
   * @var      string $version The current version of the plugin.
   */
  protected $version;

  /**
   * Define the core functionality of the plugin.
   *
   * Set the plugin name and the plugin version that can be used throughout the plugin.
   * Load the dependencies, define the locale, and set the hooks for the Dashboard and
   * the public-facing side of the site.
   *
   * @since    1.0.0
   */
  public function __construct()
  {

    $this->plugin_name = 'itella-shipping';
    $this->version = '1.0.0';

    add_action('plugins_loaded', array($this, 'run'));
    add_action('admin_notices', array($this, 'notify_on_activation'));

  }

  /**
   * Load the required dependencies for this plugin.
   *
   * Include the following files that make up the plugin:
   *
   * - Itella_Shipping_Loader. Orchestrates the hooks of the plugin.
   * - Itella_Shipping_i18n. Defines internationalization functionality.
   * - Itella_Shipping_Admin. Defines all hooks for the dashboard.
   * - Itella_Shipping_Public. Defines all hooks for the public side of the site.
   * - Itella_Shipping_Cron. Orchestrates scheduling and un-scheduling cron jobs.
   *
   * Create an instance of the loader which will be used to register the hooks
   * with WordPress.
   *
   * @since    1.0.0
   * @access   private
   */
  private function load_dependencies()
  {

    if (!class_exists('WooCommerce')) {
      return;
    }

    /**
     * The class responsible for orchestrating the actions and filters of the
     * core plugin.
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-itella-shipping-loader.php';

    /**
     * The class responsible for defining internationalization functionality
     * of the plugin.
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-itella-shipping-i18n.php';

    /**
     * The class responsible for defining all actions that occur in the Dashboard.
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-itella-shipping-method.php';

    /**
     * The class responsible for defining all actions that occur in the public-facing
     * side of the site.
     */
    require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-itella-shipping-public.php';

    /**
     * The class responsible for scheduling and un-scheduling events (cron jobs).
     */
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-itella-shipping-cron.php';

    /**
     * Load Itella API
     */

    require_once plugin_dir_path(dirname(__FILE__)) . 'libs/itella-api/vendor/autoload.php';

    Itella_Shipping_Cron::schedule();

    $this->loader = new Itella_Shipping_Loader();

    $this->set_locale();
    $this->define_admin_hooks();
    $this->define_public_hooks();
    $this->loader->run();

  }

  /**
   * Define the locale for this plugin for internationalization.
   *
   * Uses the Itella_Shipping_i18n class in order to set the domain and to register the hook
   * with WordPress.
   *
   * @since    1.0.0
   * @access   private
   */
  private function set_locale()
  {

    $plugin_i18n = new Itella_Shipping_i18n();
    $plugin_i18n->set_domain($this->get_plugin_name());

    $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');

  }

  /**
   * Register all of the hooks related to the dashboard functionality
   * of the plugin.
   *
   * @since    1.0.0
   * @access   private
   */
  private function define_admin_hooks()
  {

    $plugin_admin = new Itella_Shipping_Method($this->get_version());

    $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
    $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

    // Save settings in admin
    $this->loader->add_action('woocommerce_update_options_shipping_' . $plugin_admin->id, $plugin_admin, 'process_admin_options');
    // Update terminals daily
    $this->loader->add_action( Itella_Shipping_Cron::ITELLA_SHIPPING_EVENT_DAILY, $plugin_admin, 'update_locations' );

    $this->loader->add_filter('woocommerce_shipping_methods', $this, 'add_itella_shipping_method');


  }

  /**
   * Register all of the hooks related to the public-facing functionality
   * of the plugin.
   *
   * @since    1.0.0
   * @access   private
   */
  private function define_public_hooks()
  {

    $plugin_public = new Itella_Shipping_Public($this->get_plugin_name(), $this->get_version());

    $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
    $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
//        $this->loader->add_action( 'wp_ajax_nopriv_add_terminal_to_session', $plugin_public, 'add_terminal_to_session' );
//        $this->loader->add_action( 'wp_ajax_add_terminal_to_session', $plugin_public, 'add_terminal_to_session' );
    $this->loader->add_action('woocommerce_order_details_after_order_table', $plugin_public, 'show_pp_details', 10, 1);

    $this->loader->add_action('woocommerce_checkout_update_order_meta', $plugin_public, 'add_pp_id_to_order');
    $this->loader->add_action('woocommerce_order_status_completed', $plugin_public, 'show_pp');

  }

  /**
   * Run the loader to execute all of the hooks with WordPress.
   *
   * @since    1.0.0
   */
  public function run()
  {
    $this->load_dependencies();
  }

  /**
   * The name of the plugin used to uniquely identify it within the context of
   * WordPress and to define internationalization functionality.
   *
   * @return    string    The name of the plugin.
   * @since     1.0.0
   */
  public function get_plugin_name()
  {
    return $this->plugin_name;
  }

  /**
   * The reference to the class that orchestrates the hooks with the plugin.
   *
   * @return    Itella_Shipping_Loader    Orchestrates the hooks of the plugin.
   * @since     1.0.0
   */
  public function get_loader()
  {
    return $this->loader;
  }

  /**
   * Retrieve the version number of the plugin.
   *
   * @return    string    The version number of the plugin.
   * @since     1.0.0
   */
  public function get_version()
  {
    return $this->version;
  }

  public function notify_on_activation()
  {

    if (get_transient('itella-shipping-activated')) : ?>
        <div class="updated notice is-dismissible">
            <p>Setup Itella Shipping
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping&section=itella-shipping'); ?>">here</a>.
            </p>
        </div>
      <?php
      delete_transient('itella-shipping-activated');
    endif;
  }

  public function add_itella_shipping_method($methods)
  {

    $methods['itella-shipping'] = 'Itella_Shipping_Method';

    return $methods;
  }

}
