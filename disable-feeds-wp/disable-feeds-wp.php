<?php
/*
*  Plugin Name: Disable Feeds WP
*  Plugin URI: https://wordpress.org/plugins/disable-feeds-wp/
*  Description: Disable Feeds WP is a WordPress plugin to disable all RSS/Atom/RDF WordPress feeds on your website/blog. It is very useful if you use WordPress purely as a content management system (and not for blogging). All requests for feeds will be redirected to the corresponding HTML content.
*  Version: 1.2
*  Author: Galaxy Weblinks
*  Author URI: http://galaxyweblinks.com
*  Text Domain: disable-feeds-wp
*  License: GPLv2 or later
*  License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// If this file is called directly, abort.
if( !defined('WPINC') ){ 
    die; 
}

// Absolute path to the WordPress directory. 
if ( !defined('ABSPATH') ){
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Define Plugin URL
if ( !defined('dfwp_plugin_URL') ) {
    define('dfwp_plugin_URL', plugin_dir_url(__FILE__));
}

// Define Plugin Path
if ( !defined('dfwp_plugin_Path') ) {
    define('dfwp_plugin_Path', plugin_dir_path(__FILE__));
}

/**
 * Main class of the plugin
 *
 * @package DFWP_Disable_Feeds
 * @author  Galaxy Weblinks
 */
class DFWP_Disable_Feeds {

    /**
	 * Instance class object
	 *
	 * @var   object
	 * @since 1.0
	 */
    private static $instance = null;

    public static function get_instance(){
        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
	 * Initialize the class sets its properties.
     * 
	 * @since 1.0
	 */
    private function __construct(){
        if ( is_admin() ) {
            add_action( 'admin_init', array( $this, 'dfwp_admin_setup' ) );
            add_filter( 'plugin_action_links', array( $this, 'dfwp_disable_settings_link' ), 10, 2 );
        } else {
            add_action( 'wp_loaded', array( $this, 'dfwp_remove_links' ) );
            add_action( 'template_redirect', array( $this, 'dfwp_filter_feeds' ), 1 );
            add_filter( 'bbp_request', array( $this, 'dfwp_filter_bbp_feeds' ), 9 );
        }
    }

    /**
    * Plugin admin manu into Settings -> Reading
    */
    public function dfwp_admin_setup(){
        add_settings_field( 'dfwp_redirect', 'Disable Feeds', array( $this, 'dfwp_settings_field' ), 'reading' );
        register_setting( 'reading', 'dfwp_redirect' );
    }

    /**
    * Plugin Setting link
    */
    public function dfwp_disable_settings_link( $actions, $plugin_file ){
        static $plugin;

        if ( !isset($plugin) ) {
            $plugin = plugin_basename(__FILE__);
        }

        if ( $plugin == $plugin_file ) {
            $settings = array('settings' => '<a href="' . admin_url('options-reading.php') . '">' . __('Settings', 'dfwp') . '</a>');
            $actions = array_merge($settings, $actions);
        }
        return $actions;
    }

    /**
    * Plugin Settings Form
    */
    public function dfwp_settings_field(){

        $redirect = $this->dfwp_redirect_status();
        
        echo '<p>' . esc_html( 'By default, all feeds are disabled, and all requests for feeds are redirected to the corresponding page content. You can tweak this behaviour below.') . '</p>';
        echo '<p><input type="radio" name="dfwp_redirect" value="on" id="dfwp_redirect_redirect_yes" class="radio" ' . checked( $redirect, 'on', false ) . '/>';
        echo '<label for="dfwp_redirect_redirect_yes">' . esc_html('Redirect feed requests on the home page.') . '</label>';
        echo '<br /><input type="radio" name="dfwp_redirect" value="off" id="dfwp_redirect_redirect_no" class="radio" ' . checked( $redirect, 'off', false ) . '/>';
        echo '<label for="dfwp_redirect_redirect_no">' . esc_html('Redirect feed requests on the 404 page.') . ' </label></p>';
        echo '<br /><h3>' . esc_html('Finding Your Feed URL') . '</h3>';
        echo '<p><strong>' . esc_html('There are four possible URLs for each of your feeds. Any of these will work.') . '</strong></p>';
        echo '<ol>';
        echo '<li>' . esc_url(home_url()) . '/?feed=rss</li>';
        echo '<li>' . esc_url(home_url()) . '/?feed=rss2</li>';
        echo '<li>' . esc_url(home_url()) . '/?feed=rdf</li>';
        echo '<li>' . esc_url(home_url()) . '/?feed=atom</li>';
        echo '</ol>';
        echo '<p><strong>' . esc_html('If you are using custom permalinks, you should be able to reach them through this usage:') . '</strong></p>';
        echo '<ol>';
        echo '<li>' . esc_url(home_url()) . '/feed/</li>';
        echo '<li>' . esc_url(home_url()) . '/feed/rss/</li>';
        echo '<li>' . esc_url(home_url()) . '/feed/rss2/</li>';
        echo '<li>' . esc_url(home_url()) . '/feed/rdf/</li>';
        echo '<li>' . esc_url(home_url()) . '/feed/atom/</li>';
        echo '</ol>';
    }

    /**
    * Remove Links
    */
    public function dfwp_remove_links(){
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
    }

    /**
    * Filter feeds
    */
    public function dfwp_filter_feeds(){
        if (!is_feed() || is_404()) {
            return;
        }

        $this->dfwp_redirect_feed();
    }

    /**
    * BBPress feed detection sourced from bbp_request_feed_trap() in BBPress Core.
    */
    public function dfwp_filter_bbp_feeds( $query_vars ){

        /* Looking at a feed */
        if (isset($query_vars['feed'])) {
            if (isset($query_vars['post_type'])) {
                $post_types = array();
                if (function_exists('bbp_get_forum_post_type') && function_exists('bbp_get_topic_post_type') && function_exists('bbp_get_reply_post_type')) {
                    /*Matched post type*/
                    $post_type = false;
                    /*Post types to check*/
                    $post_types = array(
                        bbp_get_forum_post_type(),
                        bbp_get_topic_post_type(),
                        bbp_get_reply_post_type()
                    );
                    // Rest of your code
                }
                
                /*Cast query vars as array outside of foreach loop*/
                $qv_array = (array) $query_vars['post_type'];

                /*Check if this query is for a bbPress post type*/
                foreach ($post_types as $bbp_pt) {
                    if (in_array($bbp_pt, $qv_array, true)) {
                        $post_type = $bbp_pt;
                        break;
                    }
                }

                /*Looking at a bbPress post type*/
                if ( !empty($post_type) ) {
                    $this->dfwp_redirect_feed();
                }
            }
        }
        /*No feed so continue on*/
        return $query_vars;
    }

    /**
    * Redirect Feed
    */
    private function dfwp_redirect_feed(){

        global $wp_rewrite, $wp_query;

        if ($this->dfwp_redirect_status() == 'on') {
            if (isset($_GET['feed'])) {
                wp_redirect( esc_url_raw(remove_query_arg('feed')), 301 );
                exit;
            }

            /* WP redirects these anyway, and removing the query var will confuse it thoroughly */
            if (get_query_var('feed') !== 'old') {
                set_query_var('feed', '');
            }

            /* Let WP figure out the appropriate redirect URL.*/
            redirect_canonical();

            /* Still here? redirect_canonical failed to redirect, probably because of a filter. Try the hard way. */
            $struct = ( !is_singular() && is_comment_feed() ) ? $wp_rewrite->get_comment_feed_permastruct() : $wp_rewrite->get_feed_permastruct();
            $struct = preg_quote($struct, '#');
            $struct = str_replace('%feed%', '(\w+)?', $struct);
            $struct = preg_replace('#/+#', '/', $struct);
            $requested_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $requested_url = esc_url($requested_url);
            $new_url = esc_url(preg_replace('#' . $struct . '/?$#', '', $requested_url));
            if ($new_url != $requested_url) {
                wp_redirect(esc_url_raw($new_url), 301);
                exit;
            }

        } else {
            $wp_query->is_feed = false;
            $wp_query->set_404();
            status_header(404);
            /*Override the xml+rss header set by WP in send_headers*/
            header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset') );
        }
    }
    
    /**
    * Update Redirect Status
    */
    private function dfwp_redirect_status() {
        $r = get_option('dfwp_redirect', 'on');
        if (is_bool($r)) {
            $r = $r ? 'on' : 'off';
            update_option('dfwp_redirect', $r);
        }
        return $r;
    }

    /**
    * Removed options with Deactivation
    */
    public function dfwp_remove_options_on_deactivation() {
        delete_option('dfwp_redirect');
    }
}

// Register the deactivation hook
register_deactivation_hook( __FILE__ , array( DFWP_Disable_Feeds::get_instance(), 'dfwp_remove_options_on_deactivation' ) );