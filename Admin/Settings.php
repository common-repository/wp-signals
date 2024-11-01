<?php

declare(strict_types=1);

namespace WpSignals\Admin;

use WpSignals\Admin\screens\Logs;
use WpSignals\Admin\screens\Dashboard;
use WpSignals\Admin\screens\Events;
use WpSignals\Admin\screens\Analytics;
use WpSignals\Admin\screens\Configuration;
use WpSignals\Admin\screens\Help;
use WpSignals\Includes\Common;


// If this file is called directly, abort.
if (!defined('ABSPATH')) exit;

/**
 * Settings of the admin area.
 * Add the appropriate suffix constant for every field ID to take advantake the standardized sanitizer.
 *
 * @since      1.0.0
 *
 * @package    WpSignals
 * @subpackage WpSignals/Admin
 */
class Settings extends SettingsBase
{
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     */
    private $pluginSlug;

    /**
     * The slug name for the menu.
     * Should be unique for this menu page and only include
     * lowercase alphanumeric, dashes, and underscores characters to be compatible with sanitize_key().
     *
     * @since    1.0.0
     */
    private $menuSlug;

    /**
     * Ids of setting fields.
     */
    private $debugId;
    /**
     * @var Dashboard
     */
    private $DashboardScreen;
    /**
     * @var Help
     */
    private $HelpScreen;
    /**
     * @var Events
     */
    private $EventsScreen;
    /**
     * @var Analytics
     */
    private $AnalyticsScreen;
    /**
     * @var Configuration
     */
    private $ConfigurationScreen;
    /**
     * @var Logs
     */
    private $LogsScreen;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    $pluginSlug       The name of this plugin.
     */
    public function __construct(string $pluginSlug)
    {
        $this->pluginSlug = $pluginSlug;
        $this->menuSlug = $this->pluginSlug;

        $this->DashboardScreen = new Dashboard;
        $this->HelpScreen = new Help;
        $this->LogsScreen = new Logs;
        $this->EventsScreen = new Events;
        $this->AnalyticsScreen = new Analytics;
        $this->ConfigurationScreen = new Configuration;
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
            add_action('admin_menu', array($this, 'setupSettingsMenu'), 10);
        }
    }

    /**
     * This function introduces the plugin options into the Main menu.
     */
    public function setupSettingsMenu(): void
    {
        //Add the menu item to the Main menu
        add_menu_page(
            'WP Signals Options',                      // Page title: The title to be displayed in the browser window for this page.
            'WP Signals',                              // Menu title: The text to be used for the menu.
            'manage_options',                           // Capability: The capability required for this menu to be displayed to the user.
            $this->menuSlug,                            // Menu slug: The slug name to refer to this menu by. Should be unique for this menu page.
            array($this, 'renderSettingsPageContent'),  // Callback: The name of the function to call when rendering this menu's page
            'dashicons-chart-pie',                         // Icon
            81                                          // Position: The position in the menu order this item should appear.
        );
    }

    /**
     * Renders the Settings page to display for the Settings menu defined above.
     *
     * @since   1.0.0
     * @param   activeTab       The name of the active tab.
     */
    public function renderSettingsPageContent(string $activeTab = ''): void
    {
        // Check user capabilities
        if (!current_user_can('manage_options'))
        {
            return;
        }

        // Add error/update messages
        // check if the user have submitted the settings. Wordpress will add the "settings-updated" $_GET parameter to the url
        if (isset($_GET['settings-updated']))
        {
            // Add settings saved message with the class of "updated"
            add_settings_error($this->pluginSlug, $this->pluginSlug . '-message', __('Settings saved.'), 'success');
        }

        // Show error/update messages
        settings_errors($this->pluginSlug);

        $appSetup = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-setup', 'none');

        ?>
        <!-- Create a header in the default WordPress 'wrap' container -->
        <div class="wrap custom-wp-signals-admin">

            <h2><?php esc_html_e('WP Signals Options', 'wp-signals'); ?></h2>

            <?php $activeTab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard'; ?>

            <h2 class="nav-tab-wrapper">
                <?php if ($appSetup !== 'none'): ?><a href="?page=<?php echo $this->menuSlug; ?>&tab=dashboard" class="nav-tab <?php echo $activeTab === 'dashboard' ? 'nav-tab-active' : ''; ?>">Dashboard</a><?php endif; ?>
                                                   <a href="?page=<?php echo $this->menuSlug; ?>&tab=setup" class="nav-tab <?php echo $activeTab === 'setup' ? 'nav-tab-active' : ''; ?>">Settings</a>
                <?php if ($appSetup !== 'none'): ?><a href="?page=<?php echo $this->menuSlug; ?>&tab=analytics" class="nav-tab <?php echo $activeTab === 'analytics' ? 'nav-tab-active' : ''; ?>">Analytics</a><?php endif; ?>
                <?php if ($appSetup !== 'none'): ?><a href="?page=<?php echo $this->menuSlug; ?>&tab=events" class="nav-tab <?php echo $activeTab === 'events' ? 'nav-tab-active' : ''; ?>">Data &amp; Events</a><?php endif; ?>
                <?php if ($appSetup !== 'none'): ?><a href="?page=<?php echo $this->menuSlug; ?>&tab=logs" class="nav-tab <?php echo $activeTab === 'logs' ? 'nav-tab-active' : ''; ?>">Logs</a><?php endif; ?>
                                                   <a href="?page=<?php echo $this->menuSlug; ?>&tab=help" class="nav-tab <?php echo $activeTab === 'help' ? 'nav-tab-active' : ''; ?>">Help</a>

            </h2>

            <?php
                switch($activeTab) {
                    case 'dashboard': ($this->DashboardScreen->render()); break;
                    case 'setup': ($this->ConfigurationScreen->render()); break;
                    case 'analytics': ($this->AnalyticsScreen->render()); break;
                    case 'events': ($this->EventsScreen->render()); break;
                    case 'logs': ($this->LogsScreen->render()); break;
                    case 'help': ($this->HelpScreen->render()); break;

                }
            ?>

        </div><!-- /.wrap -->
        <?php
    }

    public function getDebug(): bool {
        return (defined(WP_SIGNALS_SERVER_DOMAIN) && stripos(WP_SIGNALS_SERVER_DOMAIN, 'localhost') >= 0);
    }
}
