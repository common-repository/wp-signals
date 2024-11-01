<?php

namespace WpSignals\Admin\screens;

use WpSignals\Admin\components\Sidebar;
use WpSignals\Includes\Common;

class Logs
{

    private $SidebarComponent;


    public function __construct()
    {
        $this->SidebarComponent = new Sidebar;
    }

    public function render() {

        $iconsUrl = plugin_dir_url(__FILE__) . '../assets/icons';
        $installation_id = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-id');
        $app_status = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-status', 'on');
        $environment = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-setup', 'fb-api');

        $events = explode(';!;', get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-events', ''));
        if (count($events) === 1 && empty($events[0])) {
            $events = [];
        }

        $logsData = Common::CallApi('GET', 'https://fb-api.signalresiliency.com/logs?installation_id=' . $installation_id);
        if ($logsData !== false) {
            $logsData = json_decode($logsData, true);
            if (key_exists('logs', $logsData)) {
                $logsData = $logsData['logs'];
            }
        }

        $latest_sse_message = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-sse-last-transfer-message', false);
        if ($latest_sse_message !== false) {
            array_push($logsData, $latest_sse_message);
        }

        if ($environment === 'manual') {
            $pixelIdentifier = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-pixel-name', 'FB Pixel');
            array_push($logsData, array(
                    'unix' => time(),
                    'message' => 'Running Facebook pixel with name: ' . $pixelIdentifier
            ));
        }

        usort($logsData, function ($item1, $item2) {
            return $item2['unix'] <=> $item1['unix'];
        });


        ?>

            <div class="wp-signals-screen wp-signals-logs-screen">

                <div class="screen-body">

                    <div class="section">

                        <?php if ($app_status === 'off') { ?>
                            <p class="alert alert-danger" style="background-color: #e51f45;">Plugin has been completely disabled. Please use the "Dashboard" tab to reenable it.</p>
                        <?php } ?>

                        <h3>Logs</h3>
                        <p>As a plugin user, you can view a list of the latest log messages, to identify or debug potential issues with your pixel setup.</p>

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


                        <h3 style="margin-top: 40px; margin-bottom: 15px;">Latest messages</h3>
                        <p style="margin-bottom: -10px;">Please note that these messages are retrieved from the WP-Signals pixels, so they don't log any configuration changes you make...</p>



                        <table class="logs-data">
                            <tr>
                                <th>Timestamp</th>
                                <th>Message</th>
                            </tr>

                        <?php if ($logsData !== false && count($logsData) > 0) {

                            foreach ($logsData as $log) {
                                ?>
                                <tr>
                                    <td><?php echo date('M j, H:i', $log['unix']) ?></td>
                                    <td><?php echo htmlspecialchars($log['message']); ?></td>
                                </tr>
                                <?php
                            }

                         } else { ?>

                            <tr>
                                <td colspan="2" class="no-data">No log messages found. Please try again later...</td>
                            </tr>


                        <?php } ?>



                        </table>
                    </div>

                </div>

                <div class="screen-sidebar">
                    <?php $this->SidebarComponent->render() ?>
                </div>
            </div>
        <?php
    }
}
