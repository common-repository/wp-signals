<?php

declare(strict_types=1);

namespace WpSignals\Includes;

use WpSignals\Includes\I18n;
use WpSignals\Admin\Admin;
use WpSignals\Admin\Updater;
use WpSignals\Admin\Settings;
use WpSignals\Frontend\Frontend;

// If this file is called directly, abort.
if (!defined('ABSPATH')) exit;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @link       https://signalresiliency.com
 * @since      1.0.0
 * @package    WpSignals
 * @subpackage WpSignals/Includes
 * @author     Signal Resiliency <contact@signalresiliency.com>
 */
class Main
{
    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     */
    protected $pluginSlug;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        $this->version = WP_SIGNALS_VERSION;
        $this->pluginSlug = WP_SIGNALS_SLUG;
    }

    /**
     * Create the objects and register all the hooks of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function defineHooks(): void
    {
        $isAdmin = is_admin();

        /**
         * Includes objects - Register all of the hooks related both to the admin area and to the public-facing functionality of the plugin.
         */
        $i18n = new I18n($this->pluginSlug);
        $i18n->initializeHooks();

        // The Settings' hook initialization runs on Admin area only.
        $settings = new Settings($this->pluginSlug);

        /**
         * Admin objects - Register all of the hooks related to the admin area functionality of the plugin.
         */
        if ($isAdmin)
        {
            $admin = new Admin($this->pluginSlug, $this->version, $settings);
            $admin->initializeHooks($isAdmin);

            $settings->initializeHooks($isAdmin);
        }
        /**
         * Frontend objects - Register all of the hooks related to the public-facing functionality of the plugin.
         */
        else
        {
            $frontend = new Frontend($this->pluginSlug, $this->version, $settings);
            $frontend->initializeHooks($isAdmin);
        }
    }

    private function serverName() {
        $ret = substr( strtolower($_SERVER['SERVER_PROTOCOL']), 0, strpos( strtolower($_SERVER['SERVER_PROTOCOL']), "/") ); // Add protocol (like HTTP)
        $ret .= ( empty($_SERVER['HTTPS']) ? NULL : ( ($_SERVER['HTTPS'] == "on") ? "s" : NULL) ); // Add 's' if protocol is secure HTTPS

        if (isset($_SERVER['X-FORWARDED-HOST'])) {
            $ret .= "://" . $_SERVER['X-FORWARDED-HOST']; // Add domain name/IP address
        } else {
            $ret .= "://" . $_SERVER['SERVER_NAME']; // Add domain name/IP address
        }

        $ret .= ( ($_SERVER['SERVER_PORT'] == 80) || ($_SERVER['SERVER_PORT'] == 443 && strpos($ret, 'https://') !== false) ? "" : ":".$_SERVER['SERVER_PORT'] ); // Add port directive if port is not 80 (default www port)
        return $ret;
    }

    private function selfURL()
    {
        $ret = $this->serverName();
        $ret .= $_SERVER['REQUEST_URI']; // Add the rest of the URL

        return $ret; // Return the value
    }

    public function triggerFacebookSSE() {
        $events = get_option(WP_SIGNALS_SLUG . '-configuration-app-sse-events', false);
        Common::sendFacebookSSE($events);
    }

    /**
     * Run the plugin.
     *
     * @since    1.0.0
     */
    public function run(): void
    {

        $isAdmin = is_admin();

        $batchSendEvents = false /*get_option(WP_SIGNALS_SLUG . '-configuration-app-batch-sse-events', false)*/;
        $latest_sse_events = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-last-sse-event-timestamp', false);

        if ($batchSendEvents && (!$latest_sse_events || $latest_sse_events + 3*60 < time() || $isAdmin)) {
            $this->triggerFacebookSSE();
        }


        $this->defineHooks();
        define('WP_SIGNALS_SERVER_DOMAIN', $this->serverName());
        define('WP_SIGNALS_FULL_PAGE_URL', $this->selfURL());


        if ($isAdmin && strpos(WP_SIGNALS_FULL_PAGE_URL, 'page=wp-signals') !== false) {
            $appSetup = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-setup', 'none');

            if ($appSetup === 'none' && (strpos(WP_SIGNALS_FULL_PAGE_URL, 'tab=dashboard') !== false || strpos(WP_SIGNALS_FULL_PAGE_URL, 'tab=') === false)) {
                $new_qs = (strpos(WP_SIGNALS_FULL_PAGE_URL, 'tab=') === false) ? '&tab=setup' : '';
                header('Location: ' . str_replace('tab=dashboard', 'tab=setup', WP_SIGNALS_FULL_PAGE_URL) . $new_qs);
                exit;
            }

            if ($appSetup === 'none' && (strpos(WP_SIGNALS_FULL_PAGE_URL, 'tab=analytics') !== false)) {
                header('Location: ' . str_replace('tab=analytics', 'tab=setup', WP_SIGNALS_FULL_PAGE_URL));
                exit;
            }

            if ($appSetup === 'none' && (strpos(WP_SIGNALS_FULL_PAGE_URL, 'tab=events') !== false)) {
                header('Location: ' . str_replace('tab=events', 'tab=setup', WP_SIGNALS_FULL_PAGE_URL));
                exit;
            }

//            if ($appSetup !== 'none' && strpos(WP_SIGNALS_FULL_PAGE_URL, 'tab=setup') !== false) {
//                header('Location: ' . str_replace('tab=setup', 'tab=dashboard', WP_SIGNALS_FULL_PAGE_URL));
//                exit;
//            }


            if (strpos($_SERVER['REQUEST_URI'], 'json') !== false && strpos($_SERVER['REQUEST_URI'], 'fb-analytics') !== false){
                $installation_id = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-id');
                $pixel_id = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-pixel-id');

                header('Content-Type: application/json');
                $serverAnalyticsData = Common::CallApi('GET', 'https://fb-api.signalresiliency.com/stats?installation_id=' . $installation_id . '&pixel_id=' . $pixel_id);
                echo json_encode(json_decode($serverAnalyticsData));
                exit;
            }

            if (strpos($_SERVER['REQUEST_URI'], 'json') !== false && strpos($_SERVER['REQUEST_URI'], 'fb-match-keys') !== false){
                $installation_id = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-id');
                $pixel_id = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-pixel-id');

                header('Content-Type: application/json');
                $serverAnalyticsData = Common::CallApi('GET', 'https://fb-api.signalresiliency.com/stats?aggregation=match_keys&timing=true&installation_id=' . $installation_id . '&pixel_id=' . $pixel_id);
                echo json_encode(json_decode($serverAnalyticsData));
                exit;
            }

            if (strpos($_SERVER['REQUEST_URI'], 'setup') !== false && isset($_POST['action']) && sanitize_text_field($_POST['action']) === 'pixel' && $_POST['name'] && $_POST['account']) {

                $installation_id = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-id');
                $pixelData = Common::CallApi('POST', 'https://fb-api.signalresiliency.com/pixel?installation_id=' . $installation_id, array(
                    "name" => sanitize_text_field($_POST['name']),
                    "account" => sanitize_text_field($_POST['account'])
                ));

                echo json_encode(json_decode($pixelData));
                exit;
            }
        }
    }
}
