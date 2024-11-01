<?php

namespace WpSignals\Admin\screens;

use DateInterval;
use DatePeriod;
use DateTime;
use WpSignals\Admin\components\Sidebar;
use WpSignals\Includes\Common;

class Analytics
{

    private $SidebarComponent;

    public function __construct()
    {
        $this->SidebarComponent = new Sidebar;
    }

    private function update() {}

    private function trimToLast8Days($analytics) {

        $dates = array();
        foreach ($analytics as $row => $value) {
            $current_date = explode('-', $row);
            foreach ($current_date as $key => $date_part) {
                while (strlen($date_part) < 2) {
                    $date_part = '0' . $date_part;
                }

                $current_date[$key] = $date_part;
            }

            array_push($dates, implode('-', $current_date));
        }

        sort($dates);
        $dates = array_slice($dates, -8);

        $result = array();
        foreach ($analytics as $row => $value) {
            $current_date = explode('-', $row);
            foreach ($current_date as $key => $date_part) {
                while (strlen($date_part) < 2) {
                    $date_part = '0' . $date_part;
                }

                $current_date[$key] = $date_part;
            }

            if (in_array(implode('-', $current_date), $dates)) {
                $result[$row] = $value;
            }
        }

        return $result;
    }

    public function render() {

        $iconsUrl = plugin_dir_url(__FILE__) . '../assets/icons';
        $analytics = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-analytics', (object)[]);
        $app_status = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-status', 'on');
        $pixel_limited = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-pixel-limited', false);

        $environment = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-setup', 'fb-api');

        $events = explode(';!;', get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-events', ''));
        if (count($events) === 1 && empty($events[0])) {
            $events = [];
        }


        //prepare analytics dates
        $end = new DateTime();
        $interval = new DateInterval('P7D');
        $day = new DateInterval('P1D');
        $begin = new DateTime();
        $begin = $begin->sub($interval);
        $end = $end->modify('+1 day');

        $period = new DatePeriod($begin, $day, $end);

        foreach ($period as $dt) {
            $key = $dt->format("Y-m-d");
            if (!property_exists($analytics, $key)) {
                $analytics->$key = 0;
            }
        }

        update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-analytics', $analytics);

        if ($_SERVER["REQUEST_METHOD"] == "POST"){
            $this->update();
            header("Location: " . $_SERVER['PHP_SELF'] . '#updated');
        }


        ?>

            <div class="wp-signals-screen wp-signals-analytics-screen">

                <div class="screen-body">

                    <div class="section">

                        <?php if ($app_status === 'off') { ?>
                            <p class="alert alert-danger" style="background-color: #e51f45;">Plugin has been completely disabled. Please use the "Dashboard" tab to reenable it.</p>
                        <?php } ?>

                        <h3>Analytics</h3>
                        <p>You can use this page to monitor the number of events tracked by your pixels. Data is updated every couple of minutes.</p>

                        <?php if (!get_option(WP_SIGNALS_SLUG . '-configuration-app-track', true)): ?>
                            <p class="alert alert-danger">Analytics tracking is disabled and new events will not be tracked. Please use the "Settings" tab to reenable it.</p>
                        <?php endif; ?>

                        <?php if (Common::GetCachingPlugin() !== false && get_option(WP_SIGNALS_SLUG . '-configuration-app-skip-cache-analytics', false) == false) { ?>
                            <p class="alert alert-danger">We detected a Wordpress caching plugin installed on your WP website, which may interfere with Analytics unless you enable the plugin to skip caching for the purpose of analytics tracking and preventing ad-blockers. Please use the "Settings" tab to enable it.</p>
                        <?php } ?>


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


                        <h3 style="margin-top: 40px; margin-bottom: 15px;">Daily breakdown</h3>
                        <p style="margin-bottom: -10px;">Please note that the Facebook data (blue line) might not exactly match the plugin data due to a delay by Facebook processing the data after several seconds/minutes.</p>
                        <div id="chart" style="width: 100%; height: 500px;" data-pixel-limited="<?php echo (($pixel_limited) ? 'true' : 'false') ?>" data-information="<?php echo str_replace('"', "'", json_encode($this->trimToLast8Days($analytics))) ?>"></div>


                        <?php if ($environment === 'fb-api' && !$pixel_limited) { ?>
                            <h3 class="keyschart-detail" style="margin-top: 40px; margin-bottom: 15px;">Keys breakdown</h3>
                            <p class="keyschart-detail" style="margin-bottom: 30px;">The following chart contains the keys (customer information) that Facebook is receiving for your pixel events.</p>
                            <div class="keyschart-detail" id="keyschart" style="width: 100%; height: 500px;" data-information=""></div>
                        <?php } ?>

                        <p class="text-right">Charts by Apache eCharts - a free framework for Rapid Construction of Web-based Visualization.</p>

                    </div>

                </div>

                <div class="screen-sidebar">
                    <?php $this->SidebarComponent->render() ?>
                </div>
            </div>
        <?php
    }
}
