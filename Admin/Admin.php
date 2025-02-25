<?php

declare(strict_types=1);

namespace WpSignals\Admin;

use WpSignals\Admin\Settings;

// If this file is called directly, abort.
if (!defined('ABSPATH')) exit;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://signalresiliency.com
 * @since      1.0.0
 *
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WpSignals
 * @subpackage WpSignals/Admin
 * @author     Signal Resiliency <contact@signalresiliency.com>
 */
class Admin
{
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     */
    private $pluginSlug;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     */
    private $version;

    /**
     * The settings of this plugin.
     *
     * @since    1.0.0
     */
    private $settings;

    /**
     * Initialize the class and set its properties.
     *
     * @since   1.0.0
     * @param   $pluginSlug     The name of this plugin.
     * @param   $version        The version of this plugin.
     * @param   $settings       The Settings object.
     */
    public function __construct(string $pluginSlug, string $version, Settings $settings)
    {
        $this->pluginSlug = $pluginSlug;
        $this->version = $version;
        $this->settings = $settings;
    }

    /**
     * Register all the hooks of this class.
     *
     * @since    1.0.0
     * @param   $isAdmin    Whether the current request is for an administrative interface page.
     */
    public function initializeHooks(bool $isAdmin): void
    {
        // Admin
        if ($isAdmin)
        {
            add_action('admin_enqueue_scripts', array($this, 'enqueueStyles'), 10);
            add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'), 10);
        }
    }
    
    /**
     * Register the stylesheets for the admin area.
     *
     * @since   1.0.0
     * @param   $hook    A screen id to filter the current admin page
     */
    public function enqueueStyles(string $hook): void
    {
        /**
         * This function is provided for demonstration purposes only.
         *
         * You can use the $hook parameter to filter for a particular page, for more information see the codex,
         * https://codex.wordpress.org/Plugin_API/Action_Reference/admin_enqueue_scripts
         *
         * If you are unsure what the $hook name of the current admin page of which you want to conditionally load your script is, add this to your page:
         *  $screen = get_current_screen();
         *  print_r($screen);
         *
         * The reason to register the style before enqueue it:
         * - Conditional loading: When initializing the plugin, do not enqueue your styles, but register them.
         *                        You can enque the style on demand.
         * - Dependency: The style can be used as dependency, so the style will be automatically loaded, if one style is depend on it.
         */
        $styleId = $this->pluginSlug . '-admin';
        $styleFileName = ($this->settings->getDebug() === true) ? 'wp-signals-admin.css' : 'wp-signals-admin.min.css';
        $styleUrl = plugin_dir_url(__FILE__) . 'css/' . $styleFileName;
        if (wp_register_style($styleId, $styleUrl, array(), date('YmdHis', filemtime( plugin_dir_path(__FILE__) .  'css/' . $styleFileName)), 'all') === false)
        {
            exit(esc_html__('Style could not be registered: ', 'communal-marketplace') . $styleUrl);
        }

        /**
         * If you enque the style here, it will be loaded on every Admin page.
         * To load only on a certain page, use the $hook.
         */
        wp_enqueue_style($styleId);
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since   1.0.0
     * @param   $hook    A screen id to filter the current admin page
     */
    public function enqueueScripts(string $hook): void
    {
        /**
         * This function is provided for demonstration purposes only.
         *
         * You can use the $hook parameter to filter for a particular page,
         * for more information see the codex,
         * https://codex.wordpress.org/Plugin_API/Action_Reference/admin_enqueue_scripts
         *
         * If you are unsure what the $hook name of the current admin page of which you want to conditionally load your script is, add this to your page:
         *  $screen = get_current_screen();
         *  print_r($screen);
         *
         * The reason to register the script before enqueue it:
         * - Conditional loading: When initializing the plugin, do not enqueue your scripts, but register them.
         *                        You can enque the script on demand.
         * - Dependency: The script can be used as dependency, so the script will be automatically loaded, if one script is depend on it.
         */


        if (strpos($_SERVER['REQUEST_URI'], 'analytics') !== false) {
            wp_register_script($this->pluginSlug . '-echarts', plugin_dir_url(__FILE__) . 'js/charts/echarts-en.min.js', array(), '4.9.0', false);
        }

        $scriptId = $this->pluginSlug . '-admin';
        $scripFileName = ($this->settings->getDebug() === true) ? 'wp-signals-admin.js' : 'wp-signals-admin.min.js';
        $scriptUrl = plugin_dir_url(__FILE__) . 'js/' . $scripFileName;
        if (wp_register_script($scriptId, $scriptUrl, array('jquery'), date('YmdHis', filemtime( plugin_dir_path(__FILE__) .  'js/' . $scripFileName)), false) === false)
        {
            exit(esc_html__('Script could not be registered: ', 'wp-signals') . $scriptUrl);
        }



        if (strpos($_SERVER['REQUEST_URI'], 'analytics') !== false) {
            wp_enqueue_script($this->pluginSlug . '-echarts');
        }

        /**
         * If you enque the script here, it will be loaded on every Admin page.
         * To load only on a certain page, use the $hook.
         */
        wp_enqueue_script($scriptId);


    }
}
