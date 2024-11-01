<?php

namespace WpSignals\Admin\screens;

use WpSignals\Admin\components\Sidebar;
use WpSignals\Includes\Common;

class Dashboard
{

    private $SidebarComponent;

    public function __construct()
    {
        $this->SidebarComponent = new Sidebar;
    }

    private function update() {

        if (isset($_POST['status'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-status', sanitize_text_field($_POST['status']) === 'off' ? 'off' : 'on');
        }

    }

    public function render() {

        $iconsUrl = plugin_dir_url(__FILE__) . '../assets/icons';
        $app_status = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-status', 'on');
        $environment = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-setup', 'fb-api');

        $pixelIdentifier = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-pixel-name', 'FB Pixel');

        $environmentName = ($environment === 'fb-api') ? 'Facebook API' : 'Manual setup';

        $dashboardUrl = WP_SIGNALS_FULL_PAGE_URL;
        $dashboardUrl = str_replace('show=defaults', 'i=first', $dashboardUrl);

        $events = explode(';!;', get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-events', ''));
        if (count($events) === 1 && empty($events[0])) {
            $events = [];
        }

        if ($_SERVER["REQUEST_METHOD"] == "POST"){
            $this->update();
        }


        $shouldReauth = false;
        $installation_id = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-id');
        $lastReauthCheckTimestamp = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-last-reauth-check-timestamp', false);
        $connectUrl = 'https://fb-api.signalresiliency.com/connect?installation_id=' . urlencode($installation_id) . '&reauth=true&domain=' . urlencode(WP_SIGNALS_SERVER_DOMAIN);

        if ($lastReauthCheckTimestamp === false || ($lastReauthCheckTimestamp + 3*60*60 < time())) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-last-reauth-check-timestamp', time());

            $errorsCounter = get_option(WP_SIGNALS_SLUG . '-configuration-app-sse-errors-count', 0);
            $checkData = Common::CallApi('GET', 'https://fb-api.signalresiliency.com/should_reauth?installation_id=' . $installation_id . '&errors=' . $errorsCounter);
            if ($checkData !== false) {
                $checkData = json_decode($checkData, true);
                if (key_exists('reauth', $checkData) && $checkData['reauth'] == true) {
                    $shouldReauth = true;
                }
            }
        }

        ?>

            <div class="wp-signals-screen wp-signals-dashboard-screen">

                <div class="screen-body">


                    <?php if (isset($_GET) && isset($_GET['show']) && sanitize_text_field($_GET['show']) === 'defaults') {
                        Common::resync(true);
                        ?>


            <div class="section">


                <div class="centered-contents">
                    <div class="default-setup">
                        <h3>Setup complete!</h3>
                        <p>Setup has been completed successfully. Your pixel is now operational.</p>

                        <img src="<?php echo $iconsUrl ?>/correct.png" />

                        <p style="text-decoration: underline; margin-bottom: 17px;">The default configuration is specified below:</p>
                        <p><img src="<?php echo $iconsUrl ?>/tick.png" /> JavaScript pixel (running on frontend)</p>
                        <p><img src="<?php echo $iconsUrl ?>/tick.png" /> Pixel analytics (number of times pixel has fired)</p>


                        <p style="text-decoration: underline; margin-top: 50px; margin-bottom: 17px;">When your Facebook pixel fires, the following information is sent:</p>
                        <p><img src="<?php echo $iconsUrl ?>/tick.png" /> Basic user data and events (name, email, ip address, browser)</p>
                        <p><img src="<?php echo $iconsUrl ?>/tick.png" /> Values are hashed automatically to guarantee privacy</p>


                        <p style="margin-top: 60px;">Our plugin has a lot of extra options and configuration parameters you can change from the <em>tabs</em> listed on the top of the page.
                            Good luck. Thank you for using the WP Signals plugin.</p>

                        <a href="<?php echo $dashboardUrl ?>" class="btn btn-success">Continue to dashboard</a>
                    </div>
                </div>

            </div>



                        <?php
                    } else {

                        ?>

                    <div class="section">


                        <?php if ($app_status === 'off') { ?>
                            <p class="alert alert-danger" style="background-color: #e51f45;">Warning: Plugin has been disabled. Please click on the toggle button below to activate it.</p>
                        <?php } else if ($shouldReauth) { ?>
                            <p class="alert alert-danger" style="background-color: #e51f45;">Warning: Your Facebook access token might have expired. Please reactivate your permissions by clicking <a href="<?php echo $connectUrl ?>">here</a>.</p>
                        <?php } ?>

                        <h3>Dashboard</h3>
                        <p>The dashboard is the main entry point to the WP Signals plugin. Use it to monitor the status of your Facebook pixels, and to access the other features of the plugin.</p>


                        <div class="dashboard-status">
                            <div class="image">
                                <?php if ($app_status === 'on') { ?>
                                    <img data-status="on" src="<?php echo $iconsUrl ?>/switch-on.png" />
                                <?php } else { ?>
                                    <img data-status="off" src="<?php echo $iconsUrl ?>/switch-off.png" />
                                <?php } ?>
                            </div>

                            <div class="information">
                                <?php if ($app_status === 'on') { ?>
                                    <h3>Plugin is activated and working correctly.</h3>
                                    <p>Active pixel &nbsp;&gt;&nbsp; <em><?php echo $pixelIdentifier ?></em></p>
                                    <p>You can use the tabs on the top of this page to edit various configuration parameters...</p>
                                <?php } else { ?>
                                    <h3>Plugin has been deactivated.</h3>
                                    <p>You can toggle the checkbox to the left of this message to re-enable it...</p>
                                <?php } ?>
                            </div>
                        </div>


            <?php if ($app_status === 'on') { ?>

                        <div class="analytics-group">
                            <div class="analytics-item">
                                <img src="<?php echo $iconsUrl ?>/<?php if ($app_status === 'on') { echo 'checkmark'; } else { echo 'eye'; } ?>.png" />
                                <h3>Plugin status</h3>
                                <p><?php if ($app_status === 'on') { echo 'Activated'; } else { echo 'Deactivated'; } ?></p>
                            </div>

                            <div class="analytics-item">
                                <img src="<?php echo $iconsUrl ?>/facebook.png" />
                                <h3>Environment</h3>
                                <p><?php echo $environmentName ?></p>
                            </div>

                            <div class="analytics-item">
                                <img src="<?php echo $iconsUrl ?>/puzzle.png" />
                                <h3>Plugin mode</h3>
                                <p>All features</p>
                            </div>
                        </div>



                        <div class="analytics-group">
                            <div class="analytics-item">
                                <img src="<?php echo $iconsUrl ?>/pixel.png" />
                                <h3>Active pixels</h3>
                                <p>1 pixel</p>
                            </div>

                            <div class="analytics-item">
                                <img src="<?php echo $iconsUrl ?>/browser.png" />
                                <h3>Special triggers</h3>
                                <p><?php if (count($events) > 0) { echo count($events) . ' event(s) defined'; } else { echo 'None (deactivated)'; } ?></p>
                            </div>

                            <div class="analytics-item">
                                <img src="<?php echo $iconsUrl ?>/web-plugin.png" />
                                <h3>Plugin version</h3>
                                <p><?php echo WP_SIGNALS_VERSION ?></p>
                            </div>
                        </div>


            <?php } ?>

                        <h3>Available options</h3>
                        <p>You can use the buttons below to access other features of the WP Signals plugin.</p>

                        <div class="button-group" style="width: 90%;">
                            <div class="button-option" style="background-color: white;" data-href="<?php echo str_replace('tab=dashboard', 'tab=setup', WP_SIGNALS_FULL_PAGE_URL) ?>">
                                <img src="<?php echo $iconsUrl ?>/settings.png">
                                <h4>Settings</h4>
                                <p>The settings tab allows you to modify the various options that the plugin supports.</p>
                            </div>

                            <div class="button-option" style="background-color: white;" data-href="<?php echo str_replace('tab=dashboard', 'tab=analytics', WP_SIGNALS_FULL_PAGE_URL) ?>">
                                <img src="<?php echo $iconsUrl ?>/analytics.png">
                                <h4>Analytics</h4>
                                <p>Monitor the performance of your app, the number of times your pixels have fired and more.</p>
                            </div>

                            <div class="button-option" style="background-color: white;" data-href="<?php echo str_replace('tab=dashboard', 'tab=help', WP_SIGNALS_FULL_PAGE_URL) ?>">
                                <img src="<?php echo $iconsUrl ?>/question.png">
                                <h4>Help center</h4>
                                <p>Have questions or issues with the plugin? Access the help center to get access to our FAQs.</p>
                            </div>
                        </div>

                    </div>

                        <?php
                    }
                    ?>

                </div>
                <div class="screen-sidebar">
                    <?php $this->SidebarComponent->render() ?>
                </div>
            </div>
        <?php
    }
}
