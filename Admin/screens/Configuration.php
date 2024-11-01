<?php

namespace WpSignals\Admin\screens;

use WpSignals\Admin\components\Sidebar;
use WpSignals\Includes\Common;

class Configuration
{

    private $SidebarComponent;

    public function __construct()
    {
        $this->SidebarComponent = new Sidebar;
    }

    private function update() {

        if (isset($_POST['sse'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-sse', sanitize_text_field($_POST['sse']) === 'true' ? true : false);
        }

        if (isset($_POST['woocommerce'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-woocommerce', sanitize_text_field($_POST['woocommerce']) === 'true' ? true : false);
        }

        if (isset($_POST['contactform7'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-contactform7', sanitize_text_field($_POST['contactform7']) === 'true' ? true : false);
        }

        if (isset($_POST['mailchimp'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-mailchimp', sanitize_text_field($_POST['mailchimp']) === 'true' ? true : false);
        }

        if (isset($_POST['formidableform'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-formidableform', sanitize_text_field($_POST['formidableform']) === 'true' ? true : false);
        }

        if (isset($_POST['track'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-track', sanitize_text_field($_POST['track']) === 'true' ? true : false);
        }

        if (isset($_POST['location'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-pixel-location', sanitize_text_field($_POST['location']) === 'true' ? 'footer' : 'head');
        }

        if (isset($_POST['search'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-search', sanitize_text_field($_POST['search']) === 'true' ? true : false);
        }

        if (isset($_POST['block-wp-users'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-block-wp-users', sanitize_text_field($_POST['block-wp-users']) === 'true' ? true : false);
        }

        if (isset($_POST['skip-cache-sse'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-skip-cache-sse', sanitize_text_field($_POST['skip-cache-sse']) === 'true' ? true : false);
        }

        if (isset($_POST['batch-sse-events'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-batch-sse-events', sanitize_text_field($_POST['batch-sse-events']) === 'true' ? true : false);
        }

        if (isset($_POST['sse-manual-token'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-sse-manual-token', sanitize_text_field($_POST['sse-manual-token']));
        }

        if (isset($_POST['skip-cache-analytics'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-skip-cache-analytics', sanitize_text_field($_POST['skip-cache-analytics']) === 'true' ? true : false);
        }


        if (isset($_POST['delete']) && sanitize_text_field($_POST['delete']) === 'data') {
            $options = ['app-id', 'app-setup', 'app-pixel-id', 'app-pixel-name', 'app-pixel-limited', 'app-sse', 'app-pixel-location', 'app-block-wp-users', 'app-skip-cache-sse', 'app-batch-sse-events', 'app-skip-cache-analytics', 'app-sse-token', 'app-sse-manual-token', 'app-sse-errors-count', 'app-sse-last-transfer-message', 'app-last-resync-timestamp', 'app-search', 'app-status', 'app-track', 'app-data-fields', 'app-data-woocommerce', 'app-woocommerce', 'app-contactform7', 'app-mailchimp', 'app-formidableform', 'app-events', 'app-analytics', 'app-sse-events', 'app-last-sse-event-timestamp', 'app-last-reauth-check-timestamp'];

            foreach ($options as $option) {
                delete_option('wp-signals-configuration-' . $option);
            }

            //add basic info
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-id', Common::generateRandomString());
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-analytics', (object)[]);
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-woocommerce', false);
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-track', true);
            //update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-batch-sse-events', true);
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-data-fields', 'id,first_name,last_name,email,ip,browser');

            if (Common::GetCachingPlugin() !== false) {
                update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-skip-cache-sse', true);
                update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-skip-cache-analytics', true);
            }
        }


        if (isset($_POST['pixel-id'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-pixel-id', sanitize_text_field($_POST['pixel-id']));
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-setup', 'fb-api');
        }

        if (isset($_POST['simple-pixel-id'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-pixel-id', preg_replace('/[^0-9]/', '', sanitize_text_field($_POST['simple-pixel-id'])));
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-setup', 'manual');
        }

        if (isset($_POST['pixel-name'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-pixel-name', stripslashes(sanitize_text_field($_POST['pixel-name'])));
        }

        if (isset($_POST['pixel-limited'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-pixel-limited', sanitize_text_field($_POST['pixel-limited']) === 'true' ? true : false);
        }
    }

    public function render() {

        if ($_SERVER["REQUEST_METHOD"] == "POST"){
            $this->update();
        }

        $iconsUrl = plugin_dir_url(__FILE__) . '../assets/icons';
        $app_status = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-status', 'on');
        $appSetup = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-setup', 'none');
        $pixel_limited = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-pixel-limited', false);
        $installation_id = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-id');
        $environment = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-setup', 'fb-api');
        $domain = WP_SIGNALS_SERVER_DOMAIN;

        $connectUrl = 'https://fb-api.signalresiliency.com/connect?installation_id=' . urlencode($installation_id) . '&domain=' . urlencode($domain);



        if (isset($_POST['pixel-id']) || isset($_POST['simple-pixel-id'])) {
            $next_page = str_replace('tab=setup', 'tab=dashboard', WP_SIGNALS_FULL_PAGE_URL);
            if (strpos($next_page, '?') >= 0) {
                $next_page = $next_page . '&show=defaults';
            } else {
                $next_page = $next_page . '?show=defaults';
            }

            echo '<input type="hidden" name="redirector" value="' . $next_page  . '" />';
        }


        if (strpos(WP_SIGNALS_FULL_PAGE_URL, 'result=success') !== false && strpos(WP_SIGNALS_FULL_PAGE_URL, 'setup=manual') !== false) {
            ?>

            <div class="wp-signals-screen wp-signals-settings-screen">

                <div class="screen-body">

                    <div class="section">

                        <div class="centered-contents">

                            <h3 style="margin-bottom: 20px;">Wrapping things up</h3>
                            <p>To setup a pixel, you need to enter it's ID below, which you may find in the Facebook console.</p>

                            <form class="pixel-form" method="post" action="<?php echo $_SERVER["REQUEST_URI"] ?>">

                                <div class="form-group">
                                    <label>Pixel name:</label>
                                    <input style="text-align: center;" name="pixel-name" class="form-control" />
                                </div>

                                <div class="form-group">
                                    <label>Pixel ID:</label>
                                    <input style="text-align: center;" name="simple-pixel-id" class="form-control" />
                                </div>

                                <input type="submit" class="btn btn-success btn-setup-pixel" value="Complete setup" />
                            </form>

                        </div>
                    </div>


                </div>

                <div class="screen-sidebar">
                    <?php $this->SidebarComponent->render() ?>
                </div>
            </div>





            <?php


            return ;
        }





        if (strpos(WP_SIGNALS_FULL_PAGE_URL, 'result=success') !== false) {
            $pixels = Common::CallAPI('GET', 'https://fb-api.signalresiliency.com/pixels?installation_id=' . urlencode($installation_id));
            $pixels = json_decode($pixels);
            ?>




            <div class="wp-signals-screen wp-signals-settings-screen">

                <div class="screen-body">

                    <div class="section">
                        <div class="centered-contents">


                            <h3 style="margin-bottom: 20px;">Wrapping things up</h3>
                            <p>You have completed the first step, which allows you to access your FB pixel data inside the WP Signals plugin.<br /> To setup a pixel, please select one below:</p>

                            <form class="pixel-form" method="post" action="<?php echo $_SERVER["REQUEST_URI"] ?>">

                                <input type="hidden" name="pixel-name" value="" />
                                <input type="hidden" name="pixel-limited" value="false" />

                                <div class="form-group">
                                    <label>Advertiser account:</label>
                                    <select name="ad-account" class="form-control">

                                        <option value="choose">Choose one...</option>

                                        <?php
                                            foreach ($pixels->data as $pixel) {
                                                echo '<option data-disabled="' . json_encode($pixel->details->disabled) . '" value="' . $pixel->account_id . '">' . $pixel->details->name . '</option>';
                                            }
                                        ?>


                                    </select>
                                </div>


                                <div class="form-group">
                                    <label>Choose a pixel:</label>
                                    <select name="pixel-id" class="form-control">

                                        <option value="choose">Choose one...</option>

                                        <?php
                                        foreach ($pixels->data as $pixel) {
                                            foreach ($pixel->pixels->data as $info) {
                                                echo '<option data-disabled="' . json_encode($info->disabled) . '" data-account-id="' . $pixel->account_id . '" value="' . $info->id . '">' . $info->name . ' (#' . $info->id . ')</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>

                                <p style="margin-top: 20px; margin-bottom: 50px; line-height: 1.75;">If you don't see your pixel above, you can try using the <a href="#" class="manual-pixel-link">manual pixel setup</a>, visit the "Help" tab for more instructions and information or <a href="#create-pixel" class="create-pixel-btn">create a new Facebook pixel</a>.</p>
                                <input type="submit" class="btn btn-success btn-setup-pixel" value="Complete setup" />
                            </form>


                        </div>
                    </div>


                </div>

                <div class="screen-sidebar">
                    <?php $this->SidebarComponent->render() ?>
                </div>
            </div>





            <?php


            return ;
        }


        if ($appSetup !== 'none') {
            //config for everything else

            ?>
            <div class="wp-signals-screen wp-signals-settings-screen">

                <div class="screen-body">

                    <div class="section">


                        <?php if ($app_status === 'off') { ?>
                            <p class="alert alert-danger" style="background-color: #e51f45;">Plugin has been completely disabled. Please use the "Dashboard" tab to reenable it.</p>
                        <?php } ?>


                    <div class="left-contents">
                        <h3 style="margin-bottom: 20px;">Settings</h3>
                        <p>You can use this screen to edit configuration parameters that affect your pixel setup. Clicking on a checkbox will toggle it's value and save it automatically.</p>
                        <p>Please make sure you know what you are doing, as all of your changes will be published to your website immediately.</p>

                        <h3 style="margin: 60px 0 20px 0;">Basic settings</h3>
                        <div>
                            <div class="form-check" data-id="track" data-value="<?php echo get_option(WP_SIGNALS_SLUG . '-configuration-app-track', true) ? 'true' : 'false' ?>">
                                Track pixel analytics (safe, no cookies)
                            </div>

                            <div class="form-check" data-available="true" data-id="woocommerce" data-value="<?php echo get_option(WP_SIGNALS_SLUG . '-configuration-app-woocommerce', false) ? 'true' : 'false' ?>">
                                Track WooCommerce events (automatic)
                            </div>

                            <div class="form-check" data-available="true" data-id="contactform7" data-value="<?php echo get_option(WP_SIGNALS_SLUG . '-configuration-app-contactform7', false) ? 'true' : 'false' ?>">
                                Track Contact Form 7 events (automatic)
                            </div>

                            <div class="form-check" data-available="true" data-id="mailchimp" data-value="<?php echo get_option(WP_SIGNALS_SLUG . '-configuration-app-mailchimp', false) ? 'true' : 'false' ?>">
                                Track Mailchimp events (automatic)
                            </div>

                            <div class="form-check" data-available="true" data-id="formidableform" data-value="<?php echo get_option(WP_SIGNALS_SLUG . '-configuration-app-formidableform', false) ? 'true' : 'false' ?>">
                                Track Formidable Form events (automatic)
                            </div>

                            <div class="form-check" data-id="location" data-value="<?php echo get_option(WP_SIGNALS_SLUG . '-configuration-app-pixel-location', 'head') === 'footer' ? 'true' : 'false' ?>">
                                Add pixel to page footer (faster loads)
                            </div>

                            <div class="form-check" data-available="true" data-id="search" data-value="<?php echo get_option(WP_SIGNALS_SLUG . '-configuration-app-search', false) ? 'true' : 'false' ?>">
                                Automatically send search events
                            </div>

                            <div class="form-check" data-available="true" data-id="block-wp-users" data-value="<?php echo get_option(WP_SIGNALS_SLUG . '-configuration-app-block-wp-users', false) ? 'true' : 'false' ?>">
                                Disable tracking for WP admin users
                            </div>
                        </div>





                        <h3 style="margin: 60px 0 20px 0;">Server-side events</h3>
                        <div>
                            <p>Facebook allows you to send information directly from your server. This helps a great deal to make your Facebook pixels target more users, especially those that have browsers that prevent pixels firing or when users have ad-blockers installed. The downside is a small increase in CPU usage.</p>

                            <?php if (Common::GetCachingPlugin() !== false) {
                                ?>
                                <div class="alert alert-danger">We detected a possible Wordpress caching plugin installed on your WP website: &nbsp; <strong><?php echo Common::GetCachingPlugin() ?></strong>. <br /><br />
                                    Some Wordpress caching plugins might prevent Server-side events from firing, or prevent this plugin from gathering analytics data for your pageviews. In that case, please consider toggling the appropriate checkbox below, and wait a couple of minutes for the pages to rerender (or purge your cache manually).</div>

                            <?php } else { ?>
                                    <p style="color: green; margin-bottom: 30px;">We couldn't detect any caching plugins installed on your Wordpress website.</p>
                                <?php
                            }
                            ?>
                            <div class="form-check" data-environment="<?php echo $environment ?>" data-available="<?php echo (!$pixel_limited ? 'true' : 'false') ?>"
                                <?php if ($environment === 'fb-api' && $pixel_limited): ?> data-error-title="Limited pixel" data-error-message="You have selected a pixel from a business account, that can only be connected from the Business Manager inside Facebook. Server-side events are unavailable." <?php endif; ?>
                                 data-id="sse" data-value="<?php echo get_option(WP_SIGNALS_SLUG . '-configuration-app-sse', false) ? 'true' : 'false' ?>">
                                Use Server-side events (Conversions API)
                            </div>

                            <div class="form-check" data-available="true" data-id="skip-cache-sse" data-value="<?php echo get_option(WP_SIGNALS_SLUG . '-configuration-app-skip-cache-sse', false) ? 'true' : 'false' ?>">
                                Skip caching and ad-blockers for SSE
                            </div>

                            <div class="form-check" data-available="true" data-id="skip-cache-analytics" data-value="<?php echo get_option(WP_SIGNALS_SLUG . '-configuration-app-skip-cache-analytics', false) ? 'true' : 'false' ?>">
                                Skip caching and ad-blockers for analytics
                            </div>


                            <?php if ($environment !== 'fb-api') { ?>
                                <div class="manual-sse-div-info" style="margin-top: 40px; margin-bottom: 50px; display: <?php echo (get_option(WP_SIGNALS_SLUG . '-configuration-app-sse', false) ? 'block' : 'none') ?>;">
                                    <p style="color: red;">You are using the manual pixel setup, which means you need to provide an access token for pushing server-side events yourself. Follow the procedure outlined on <a href="https://developers.facebook.com/docs/marketing-api/conversions-api/get-started/">this Facebook page</a>, and paste the generated token in the textbox below. Server-side events won't work until you set a valid access token.</p>

                                    <textarea data-id="sse-manual-token" rows="3" cols="300" style="width: 800px; max-width: 80%;"><?php echo get_option(WP_SIGNALS_SLUG . '-configuration-app-sse-manual-token', '') ?></textarea>
                                </div>
                            <?php } ?>
                        </div>






                        <h3 style="margin: 60px 0 20px 0;">Plan & Version</h3>
                        <div>
                            <p style="padding-left: 10px;">Plugin version: <strong><?php echo WP_SIGNALS_VERSION ?></strong></p>
                        </div>


                        <h3 style="margin: 60px 0 20px 0;">Reset plugin</h3>
                        <div>
                            <p>By clicking the button below, you will reset all plugin settings and start from scratch.</p>
                            <button class="btn btn-danger btn-delete-data">Reset plugin</button>
                        </div>
                    </div>
                    </div>


                </div>

                <div class="screen-sidebar">
                    <?php $this->SidebarComponent->render() ?>
                </div>
            </div>
            <?php

        } else {

        ?>
            <div class="wp-signals-screen wp-signals-settings-screen">

                <div class="screen-body">

                    <div class="section">
                    <div class="centered-contents">
                        <h3 style="margin-bottom: 20px;">Let's get started</h3>
                        <p>To get started, you can either configure a Facebook pixel manually, or use our simple wizard, which requires access to your Facebook pixels data.</p>
                        <p>The privacy of your data, ads and pixels is guaranteed, as we don't store any additional information apart from what is required to setup your pixels and your firing rules.</p>

                        <h3 style="margin: 60px 0 20px 0;">Please choose one of the options below</h3>
                        <div class="button-group marked-options">
                            <div class="button-option selected" data-href="<?php echo $connectUrl ?>">
                                <img src="<?php echo $iconsUrl ?>/pixel-wizard.png">
                                <h4>Use the Wizard</h4>
                                <p>The simplest way to use our plugin. Requires access to your FB pixel data.</p>
                            </div>

                            <div class="button-option manual-pixel-setup-btn">
                                <img src="<?php echo $iconsUrl ?>/pixel-manual.png">
                                <h4>Manual pixel setup</h4>
                                <p>To complete this step, you need to know your pixel id.</p>
                            </div>
                        </div>
                    </div>
                    </div>


                </div>

                <div class="screen-sidebar">
                    <?php $this->SidebarComponent->render() ?>
                </div>
            </div>
        <?php
        }
    }
}
