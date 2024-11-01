<?php

namespace WpSignals\Admin\screens;

use WpSignals\Admin\components\Sidebar;
use WpSignals\Includes\Common;

class Events
{

    private $SidebarComponent;

    public function __construct()
    {
        $this->SidebarComponent = new Sidebar;
    }

    private function update() {

        if (isset($_POST['action']) && isset($_POST['event']) && isset($_POST['path'])) {

            $events = explode(';!;', get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-events', ''));
            if (get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-events', '') === '') {
                $events = [];
            }

            if (sanitize_text_field($_POST['action']) === 'remove') {
                //remove if exists

                foreach ($events as $key => $event) {
                    $eventDetails = json_decode($event, true);
                    if ($eventDetails['event'] === sanitize_text_field($_POST['event']) && $eventDetails['path'] === sanitize_text_field($_POST['path'])) {
                        unset($events[$key]);
                    }
                }
            }

            if (sanitize_text_field($_POST['action']) === 'add') {
                //remove if exists
                array_push($events, json_encode(array(
                    'event' => sanitize_text_field($_POST['event']),
                    'path' => sanitize_text_field($_POST['path'])
                )));
            }

            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-events', join(';!;', $events));
        }

        if (isset($_POST['first_name']) || isset($_POST['last_name']) || isset($_POST['email']) || isset($_POST['id']) || isset($_POST['ip']) || isset($_POST['browser'])) {
            $current_data_fields = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-data-fields', '');

            foreach (array('first_name', 'last_name', 'email', 'id', 'ip', 'browser') as $field_name) {
                if (isset($_POST[$field_name]) && !empty($_POST[$field_name])) {
                    if (sanitize_text_field($_POST[$field_name]) === 'true' && strpos($current_data_fields, $field_name) === false) {
                        if (strlen($current_data_fields) > 0 && substr($current_data_fields, -1) !== ',') {
                            $current_data_fields = $current_data_fields . ',';
                        }

                        $current_data_fields = $current_data_fields . $field_name . ',';
                    }

                    if (sanitize_text_field($_POST[$field_name]) !== 'true' && strpos($current_data_fields, $field_name) !== false) {
                        $current_data_fields = str_replace($field_name . ',', '', $current_data_fields);

                        if (strlen($current_data_fields) === 1 && $current_data_fields === ',') {
                            $current_data_fields = '';
                        }
                    }
                }
            }

            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-data-fields', $current_data_fields);
        }

        if (isset($_POST['woocommerce'])) {
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-data-woocommerce', sanitize_text_field($_POST['woocommerce']) === 'true' ? true : false);
        }
    }

    public function render() {

        if ($_SERVER["REQUEST_METHOD"] == "POST"){
            $this->update();
        }

        $iconsUrl = plugin_dir_url(__FILE__) . '../assets/icons';
        $app_status = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-status', 'on');
        $events = explode(';!;', get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-events', ''));
        if (count($events) === 1 && empty($events[0])) {
            $events = [];
        }


        ?>
            <div class="wp-signals-screen wp-signals-events-screen">

                <div class="screen-body">

                    <div class="section">

                        <?php if ($app_status === 'off') { ?>
                            <p class="alert alert-danger" style="background-color: #e51f45;">Plugin has been completely disabled. Please use the "Dashboard" tab to reenable it.</p>
                        <?php } ?>


                        <div class="left-contents">
                            <h3 style="margin-bottom: 20px;">Events &amp; Data</h3>
                            <p>Facebook Pixels can gather various data about your users. Similarly, you can define triggers for the most common events trackable by pixels.</p>
                            <p>All changes made in this screen are published immediately to your Wordpress instance.</p>

                            <h3 style="margin: 60px 0 20px 0;">Shared user data</h3>
                            <div>
                                <div class="form-check" data-id="id" data-value="<?php echo (strpos(get_option(WP_SIGNALS_SLUG . '-configuration-app-data-fields', ''), 'id') !== false) ? 'true' : 'false' ?>">
                                    User identifier
                                </div>

                                <div class="form-check" data-id="first_name" data-value="<?php echo (strpos(get_option(WP_SIGNALS_SLUG . '-configuration-app-data-fields', ''), 'first_name') !== false) ? 'true' : 'false' ?>">
                                    First name
                                </div>

                                <div class="form-check" data-id="last_name" data-value="<?php echo (strpos(get_option(WP_SIGNALS_SLUG . '-configuration-app-data-fields', ''), 'last_name') !== false) ? 'true' : 'false' ?>">
                                    Last name
                                </div>

                                <div class="form-check" data-id="email" data-value="<?php echo (strpos(get_option(WP_SIGNALS_SLUG . '-configuration-app-data-fields', ''), 'email') !== false) ? 'true' : 'false' ?>">
                                    Email Address
                                </div>

                                <div class="form-check" data-id="ip" data-value="<?php echo (strpos(get_option(WP_SIGNALS_SLUG . '-configuration-app-data-fields', ''), 'ip') !== false) ? 'true' : 'false' ?>">
                                    IP Address
                                </div>

                                <div class="form-check" data-id="browser" data-value="<?php echo (strpos(get_option(WP_SIGNALS_SLUG . '-configuration-app-data-fields', ''), 'browser') !== false) ? 'true' : 'false' ?>">
                                    Browser Agent
                                </div>

                                <div class="form-check" data-id="woocommerce" data-value="<?php echo get_option(WP_SIGNALS_SLUG . '-configuration-app-data-woocommerce', false) !== false ? 'true' : 'false' ?>">
                                    Woocommerce cart data
                                </div>
                            </div>





                            <h3 style="margin: 60px 0 20px 0;">Custom events</h3>
                            <div class="events-container">
                                <p>You can specify custom event triggers based on the pages your users visit (example, contact pages) or are redirected to (example, a successful payment).
                                     Custom events allow you to gather more information with your pixels, and automatically include the same user data that is selected above ("Shared user data"). <br /><br />
                                    As an example, if you create an event that sends the "Donate" event on a page url like /donate/, then the pixel will send that information to Facebook, including the appropriate user data.</p>

                                <table class="events" data-icons="<?php echo $iconsUrl ?>">
                                    <tr class="no-events<?php if (!empty($events)) { echo ' hidden'; } ?>">
                                        <td colspan="3">
                                            <img src="<?php echo $iconsUrl ?>/box.png" />
                                            <p>No events have been defined.<br /> Use the button below to create some...</p>
                                        </td>
                                    </tr>

                                    <?php foreach ($events as $event) {
                                        $currentData = json_decode($event, true);
                                        ?>

                                        <tr>
                                            <td><?php echo $currentData['event'] ?></td>
                                            <td><?php echo $currentData['path'] ?></td>
                                            <td style="width: 100px; text-align: center;"><img class="remove-event" src="<?php echo $iconsUrl ?>/delete.png" /></td>
                                        </tr>

                                    <?php } ?>
                                </table>

                                <div style="text-align: right;">
                                    <button class="btn btn-success btn-small btn-add-event">Add event</button>
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
