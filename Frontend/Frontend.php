<?php

declare(strict_types=1);

namespace WpSignals\Frontend;

use DateTime;
use WpSignals\Admin\Settings;
use WpSignals\Includes\Common;

// If this file is called directly, abort.
if (!defined('ABSPATH')) exit;

/**
 * The frontend functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the frontend stylesheet and JavaScript.
 *
 * @link       https://signalresiliency.com
 * @since      1.0.0
 *
 * @package    WpSignals
 * @subpackage WpSignals/Frontend
 * @author     Signal Resiliency <contact@signalresiliency.com>
 */
class Frontend
{
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     */
    private $pluginSlug;

    private $renderIdentifier;

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

    private $actionEvents;
    /**
     * @var array
     */
    private $functionsCalled;

    /**
     * Initialize the class and set its properties.
     *
     * @since   1.0.0
     * @param   $pluginSlug     The name of the plugin.
     * @param   $version        The version of this plugin.
     * @param   $settings       The Settings object.
     */
    public function __construct(string $pluginSlug, string $version, Settings $settings)
    {
        $this->pluginSlug = $pluginSlug;
        $this->version = $version;
        $this->settings = $settings;
        $this->actionEvents = array();
        $this->renderIdentifier = hash('sha256', 'id' . time() . 'x' . Common::generateRandomString(10));

        $this->functionsCalled = array();
    }

    public function json_skip_cache_sse(){
        $json = file_get_contents('php://input');
        $data = json_decode(base64_decode(json_decode($json, true)["data"]), true);

        $events = get_option(WP_SIGNALS_SLUG . '-configuration-app-sse-events', false);
        $batchSendEvents = false /*get_option(WP_SIGNALS_SLUG . '-configuration-app-batch-sse-events', false)*/;

        if (!$events || !is_array($events)) {
            $events = array();
        }

        $renderIdentifier = $this->renderIdentifier;
        if (!empty($data['renderId'])) {
            $renderIdentifier = $data['renderId'];
        }

        array_push($events, array_merge(array('event_name' => 'PageView', 'event_id' => $this->getEventIdentifier('PageView', 'pg', $renderIdentifier), 'event_time' => time(), 'action_source' => 'website', 'event_source_url' => $data['basic']['url'], 'user_data' => $this->getUserData($data['user'], false)), array_key_exists('custom', $data) ? $data['custom'] : array()));
        $extraEvents = $data['events'];
        foreach ($extraEvents as $key => $extraEvent) {
            $actual_user_data = (!array_key_exists('extra_user', $extraEvent)) ? $data['user'] : array_merge($data['user'], $extraEvents['extra_user']);
            array_push($events, array_merge(array('event_name' => $extraEvent['name'], 'event_id' => $this->getEventIdentifier($extraEvent['name'], 'ex' . $key, $renderIdentifier), 'event_time' => time(), 'action_source' => 'website', 'event_source_url' => $data['basic']['url'], 'user_data' => $this->getUserData($actual_user_data, false)), array_key_exists('custom', $extraEvent) ? $extraEvent['custom'] : array()));
        }

        if ($batchSendEvents) {
            update_option(WP_SIGNALS_SLUG . '-configuration-app-sse-events', $events);
        } else {
            Common::sendFacebookSSE($events);
        }

        return (array('status' => 'success'));
    }

    public function json_skip_cache_analytics() {
        $this->updateAnalytics();
        return (array('status' => 'success'));
    }

    public function loadFacebookSSE() {

        if (in_array('loadFacebookSSE', $this->functionsCalled)) {
            return;
        } else {
            array_push($this->functionsCalled, 'loadFacebookSSE');
        }

        $pixel_sse = get_option(WP_SIGNALS_SLUG . '-configuration-app-sse', false);
        $app_status = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-status', 'on');

        if ($app_status === 'on') {
            $skip_cache_sse = get_option(WP_SIGNALS_SLUG . '-configuration-app-skip-cache-sse', false);
            $skip_cache_analytics = get_option(WP_SIGNALS_SLUG . '-configuration-app-skip-cache-analytics', false);
            $woocommerce_triggers = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-woocommerce', false);

            if ($skip_cache_sse || $skip_cache_analytics) {
                add_action('rest_api_init', function () {

                    register_rest_route('wp-signals/v1', '/sse', array(
                        'methods' => 'POST',
                        'callback' => array($this, 'json_skip_cache_sse'),
                    ));

                    register_rest_route('wp-signals/v1', '/al', array(
                        'methods' => 'POST',
                        'callback' => array($this, 'json_skip_cache_analytics'),
                    ));
                });
            }

            $pixel_id = get_option(WP_SIGNALS_SLUG . '-configuration-app-pixel-id', false);
            $block_wp_users = get_option(WP_SIGNALS_SLUG . '-configuration-app-block-wp-users', false);

            if ($pixel_id !== false && (!$block_wp_users || !current_user_can('edit_pages'))) {
                //add to html
                $pixel_location = get_option(WP_SIGNALS_SLUG . '-configuration-app-pixel-location', 'head');

                if ($pixel_sse) {
                    if ($pixel_location !== 'footer' && !$woocommerce_triggers) {
                        add_action('wp_head', array($this, 'sendSSEEvent'));
                    } else {
                        add_action('wp_footer', array($this, 'sendSSEEvent'));
                    }

                } /* else */ {
                    //use html
                    if ($pixel_location !== 'footer' && !$woocommerce_triggers) {
                        add_action('wp_head', array($this, 'renderPixelInHtml'));
                    } else {
                        add_action('wp_footer', array($this, 'renderPixelInHtml'));
                    }
                }
            }
        }
    }

    public function updateAnalytics() {
        if (get_option(WP_SIGNALS_SLUG . '-configuration-app-track', true)) {
            $analytics = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-analytics', (object)[]);
            $day = new DateTime();
            $key = $day->format("Y-m-d");
            if (!property_exists($analytics, $key)) {
                $analytics->$key = 1;
            } else {
                $analytics->$key = intval($analytics->$key) + 1;
            }
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-analytics', $analytics);
        }
    }

    /**
     * Register all the hooks of this class.
     *
     * @since    1.0.0
     * @param   $isAdmin    Whether the current request is for an administrative interface page.
     */
    public function initializeHooks(bool $isAdmin): void
    {
        if (!$isAdmin) {

            if (get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-woocommerce', false)) {
                add_action( 'woocommerce_add_to_cart', array($this, 'trackWoocommerceAddToCartEvent'), 9, 4);
                add_action( 'woocommerce_thankyou_order_id', array($this, 'trackWoocommercePurchaseEvent'), 9, 1);
            }

            if (get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-contactform7', false)) {
                add_action( 'wpcf7_submit', array($this, 'trackContactForm7'), 10, 2);
            }

            if (get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-mailchimp', false)) {
                add_action( 'mc4wp_form_subscribed', array($this, 'trackMailchimp'), 9, 0);
            }

            if (get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-formidableform', false)) {
                add_action( 'frm_after_create_entry', array($this, 'trackFormidableForm'), 20, 2);
            }

            add_action('plugins_loaded', array($this, 'loadFacebookSSE'), 10);
        }

        // Frontend
        //if (!$isAdmin)
        //{
        //    add_action('wp_enqueue_scripts', array($this, 'enqueueStyles'), 10);
        //    add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'), 10);
        //}
    }

    private static function identifyCartItem($key) {
        if (WC()->cart) {
            $cart = WC()->cart->get_cart();
            if (!empty($cart) && !empty($cart[$key])) {
                return $cart[$key];
            }
        }

        return null;
    }

    private static function identifyProductIds($cart) {
        $product_list = array();
        if (!empty($cart) && count($cart->get_cart()) > 0) {
            foreach ($cart->get_cart() as $cart_product) {
                if (!empty($cart_product['data'])) {
                    $product_list[] = self::identifyProduct($cart_product['data']);
                }
            }
        }

        return $product_list;
    }

    private static function identifyProduct($item) {
        $woocommerce_id = $item->get_id();
        return $item->get_sku() ? ($item->get_sku() . '_' . $woocommerce_id) : ('wp_item_' . $woocommerce_id);
    }


    public function trackWoocommerceAddToCartEvent($cart_item_key, $product_id, $counter, $variation_id) {
        $product_info = array();

        if (!empty($cart_item_key)) {
            $product = self::identifyCartItem($cart_item_key);
            $product_info['content_ids'] = array(self::identifyProduct($product['data']));
        }

        array_push($this->actionEvents, array('event' => 'AddToCart', 'custom' => array_merge(array(
            'currency' => \get_woocommerce_currency(),
            'content_type' => 'product'
        ), $product_info)));
    }

    public function trackWoocommercePurchaseEvent($order_id) {

        $order = wc_get_order($order_id);

        array_push($this->actionEvents, array('event' => 'Purchase', 'custom' => array(
            'currency' => \get_woocommerce_currency(),
            'content_type' => 'product',
            'value' => $order->get_total())));
    }

    public function trackMailchimp() {

        $event_data = array();
        foreach (array('EMAIL', 'FNAME', 'LNAME', 'PHONE') as $field_name) {
            $code_name = substr(strtolower($field_name), 0, 2);

            if (!empty($_POST[$field_name])) {
                $event_data[$code_name] = sanitize_text_field($_POST[$field_name]);
            }

            if (!empty($_POST[strtolower($field_name)])) {
                $event_data[$code_name] = sanitize_text_field($_POST[strtolower($field_name)]);
            }
        }

        array_push($this->actionEvents, array('event' => 'Lead', 'extra_user' => $event_data, 'custom' => array()));
    }

    public function trackFormidableForm($event_id, $form_id) {

        if (empty($event_id)) {
            return ;
        }

        $event_data = array();
        $data = new \FrmEntryValues($event_id);

        if (!empty($data)) {
            $fields_data = $data->get_field_values();

            if (!empty($fields_data)) {

                foreach ($fields_data as $current_field) {
                    $field = $current_field->get_field();
                    if ($field->type == 'email') {
                        $event_data['em'] = $current_field->get_saved_value();
                    }

                    if ($field->type == 'tel' || $field->type == 'phone') {
                        $event_data['ph'] = $current_field->get_saved_value();
                    }

                    if ($field->type == 'text' && (stripos($field->name, 'first') !== false && stripos($field->name, 'name') !== false)) {
                        $event_data['fn'] = $current_field->get_saved_value();
                    }

                    if ($field->type == 'text' && (stripos($field->name, 'last') !== false && stripos($field->name, 'name') !== false)) {
                        $event_data['ln'] = $current_field->get_saved_value();
                    }

                    if ($field->type == 'text' && (stripos($field->name, 'name') !== false) && !array_key_exists('fn', $event_data) && !array_key_exists('ln', $event_data)) {
                        $name_pairs = explode(" ", preg_replace('!\s+!', ' ', trim($current_field->get_saved_value()), 2));
                        $event_data['fn'] = $name_pairs[0];

                        if (count($name_pairs) >= 2) {
                            $event_data['ln'] = $name_pairs[1];
                        }
                    }
                }
            }
        }

        array_push($this->actionEvents, array('event' => 'Lead', 'extra_user' => $event_data, 'custom' => array()));
    }

    public function trackContactForm7($form, $result) {

        if ($result['status'] === 'mail_sent' || $result['status'] === 'mail_failed' || (strpos($result['status'], 'fail') === false && strpos($result['status'], 'spam') === false && strpos($result['status'], 'error') === false)) {

            if (!empty($form)) {
                $wpcf7_fields = $form->scan_form_tags();
                $user_data = $this->getInitData();

                foreach ($wpcf7_fields as $form_tag) {
                    if ($form_tag->basetype == "email" && isset($_POST[$form_tag->name]) && strlen(sanitize_text_field($_POST[$form_tag->name])) > 0) {
                        $user_data['em'] = sanitize_text_field($_POST[$form_tag->name]); //contact-form-7 should verify email validity
                    }

                    if ($form_tag->basetype === "tel" && isset($_POST[$form_tag->name]) && strlen(sanitize_text_field($_POST[$form_tag->name])) > 0) {
                        $user_data['ph'] = sanitize_text_field($_POST[$form_tag->name]);
                    }

                    if ($form_tag->basetype === "text" && stripos($form_tag->name, 'name') !== false && isset($_POST[$form_tag->name]) && strlen(sanitize_text_field($_POST[$form_tag->name])) > 0) {
                        $name_pairs = explode(" ", preg_replace('!\s+!', ' ', trim(sanitize_text_field($_POST[$form_tag->name]))), 2);
                        $customData['fn'] = $name_pairs[0];

                        if (count($name_pairs) >= 2) {
                            $customData['ln'] = $name_pairs[1];
                        }
                    }
                }

                $user_data = $this->getUserData($user_data);
                $result['event-identifier'] = 'Lead-' . $result['posted_data_hash'];
                if (!empty($_POST['tempid'])) {
                    $result['event-identifier'] = sanitize_text_field($_POST['tempid']);
                }

                if (get_option(WP_SIGNALS_SLUG . '-configuration-app-sse', false)) {
                    Common::sendFacebookSSE(array(array('event_name' => 'Lead', 'event_id' => $result['event-identifier'], 'event_time' => time(), 'action_source' => 'website', 'event_source_url' => WP_SIGNALS_FULL_PAGE_URL, 'user_data' => $user_data)));
                }
            }
        }

        return $result;
    }

    private function getEventIdentifier($type, $from, $renderId = false) {
        if ($renderId === false) {
            $renderId = $this->renderIdentifier;
        }

        return hash('sha256', $type . '-' . $from . '-' . $renderId);
    }

    private function getContactForm7Js()
    {
        return <<<EOD
function wpsignalsCF7Handler(e) {
var form_fields = document.querySelectorAll('.wpcf7-form input');
var extraData = {};
var eventData = {};


if (document.querySelector('.wpcf7-form input[name="tempid"]')) {
    eventData['eventID'] = document.querySelector('.wpcf7-form input[name="tempid"]').value;
} else if (e && (e['event-identifier'] || (e.detail && e.detail.apiResponse && e.detail.apiResponse.posted_data_hash))) {
    eventData['eventID'] = e['event-identifier'] ? e['event-identifier'] : ('Lead-' + e.detail.apiResponse.posted_data_hash);
}

for (var form_field of form_fields) {
    if (form_field.value && form_field.value.trim() && form_field.type) {
       var form_field_type = form_field.type.toLowerCase();
       var form_field_name = form_field.name.toLowerCase();
       
       if (form_field_type === 'email') {
         extraData['em'] = form_field.value.trim().toLowerCase();
       }
       
       if (form_field_type === 'tel') {
         extraData['ph'] = form_field.value.trim().toLowerCase();
       }
       
       if (form_field_type === 'text' && form_field_name.indexOf('name') >= 0) {
         extraData['fn'] = form_field.value.trim().split(' ')[0].toLowerCase();
         
         if (form_field.value.trim().split(' ').length >= 2) {
            extraData['ln'] = form_field.value.trim().split(' ').slice(1).join(' ').toLowerCase();
         }                 
       }
    }
}

fbq('track', 'Lead', extraData, eventData);
}

document.addEventListener("DOMContentLoaded", function(){
    document.addEventListener('wpcf7mailsent', wpsignalsCF7Handler, false);
    document.addEventListener('wpcf7mailfailed', wpsignalsCF7Handler, false);
    
    var submitForm = document.querySelector('.wpcf7-form');
    if (submitForm) {
        var input = document.createElement("input");
        input.setAttribute("type", "hidden");
        input.setAttribute("name", "tempid");
        input.setAttribute("value", "Lead-" + (new Date().getTime()) + "-" + Math.round(Math.random() * 1000000000));
        submitForm.appendChild(input);
    }
});
EOD;
    }


    public function getInitData() {
        $data = array();
        $user = wp_get_current_user();

        if ($user === 0) {
            return $data;
        }

        $current_data_fields = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-data-fields', '');

        foreach (array('first_name', 'last_name', 'email', 'id') as $field_name) {
            if (strpos($current_data_fields, $field_name) !== false) {
                switch ($field_name) {
                    case 'id': if (!empty($user->ID)) $data['external_id'] = $user->ID; break;
                    case 'first_name': if (!empty($user->user_firstname)) $data['fn'] = $user->user_firstname; break;
                    case 'last_name': if (!empty($user->user_lastname)) $data['ln'] = $user->user_lastname; break;
                    case 'email': if (!empty($user->user_email)) $data['em'] = $user->user_email; break;
                }
            }
        };

        return $data;
    }

    public function renderPixelInHtml() {
        if (in_array('renderPixelInHtml', $this->functionsCalled)) {
            return;
        } else {
            array_push($this->functionsCalled, 'renderPixelInHtml');
        }

        $pixel_id = get_option(WP_SIGNALS_SLUG . '-configuration-app-pixel-id', false);
        $track_search = get_option(WP_SIGNALS_SLUG . '-configuration-app-search', false);
        $skip_cache_analytics = get_option(WP_SIGNALS_SLUG . '-configuration-app-skip-cache-analytics', false);


        if ($pixel_id !== false) {
            $send_search_event = (isset($_REQUEST['q']) || isset($_GET['q']));
            $initData = $this->getInitData();
            if (count($initData) > 0) {
                $initData = ', ' . json_encode($initData);
            } else {
                $initData = '';
            }

            $search_event = ($send_search_event && $track_search ? "\n  fbq('track', 'Search', {}, { eventID: '" . $this->getEventIdentifier('Search', 'sp')  . "' });" : '');
            $search_image = ($send_search_event && $track_search ? ("\n" . '  <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=' . $pixel_id . '&ev=Search&eid=' . $this->getEventIdentifier('Search', 'sp') . '&noscript=1"/>') : '');
            $track_code = ($skip_cache_analytics ? ("\n" . 'var xta = new XMLHttpRequest(); xta.onreadystatechange = function() {}; xta.open("POST", "/wp-json/wp-signals/v1/al", true); xta.send();') : '');

            $custom_events = '';
            $custom_event_images = '';
            $other_handlers = '';

            $events = explode(';!;', get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-events', ''));
            if (count($events) === 1 && empty($events[0])) {
                $events = [];
            }

            $eventPos = 0;
            foreach ($events as $event) {
                $eventDetails = json_decode($event, true);
                $name = $eventDetails['event'];
                $path = str_replace('@x@x@', '.*', preg_quote(str_replace('*', '@x@x@', $eventDetails['path'])));

                if (preg_match('#' . $path . '#', $_SERVER['REQUEST_URI'])) {
                    $custom_events .= "\n  fbq('track', '" . $name . "', {}, { eventID: '" . $this->getEventIdentifier($name, 'ex' . $eventPos)  . "' }"  . ");";
                    $custom_event_images .= "\n" . '  <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=' . $pixel_id . '&ev=' . $name . '&eid=' . $this->getEventIdentifier($name, 'ex' . $eventPos) . '&noscript=1"/>';
                    $eventPos++;
                }
            }

            foreach ($this->actionEvents as $event) {
                $eventDetails = $event;
                $name = $eventDetails['event'];

                if (empty($eventDetails['custom'])) {
                    $eventDetails['custom'] = array();
                }

                if (!array_key_exists('extra_user', $eventDetails)) {
                    $eventDetails['custom'] = array_merge($eventDetails['custom'], $eventDetails['extra_user']);
                }

                //$eventDetails['custom']['eventID'] = $this->getEventIdentifier($name, 'ex' . $eventPos);

                $custom_events .= "\n  fbq('track', '" . $name . "', " . json_encode($eventDetails['custom']) . ", {eventID: '" . $this->getEventIdentifier($name, 'ex' . $eventPos) . "'});";
                $custom_event_images .= "\n" . '  <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=' . $pixel_id . '&ev=' . $name . '&eid=' . $this->getEventIdentifier($name, 'ex' . $eventPos) .  '&noscript=1"/>';

                $eventPos++;
            }


            if (get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-contactform7', false)) {
                $other_handlers .= "\n" . $this->getContactForm7Js();
            }


            $pageviewIdentifier = $this->getEventIdentifier('PageView', 'pg');
            $script = <<<EOD
<script>
  !function(f,b,e,v,n,t,s)
  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
  n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)}(window, document,'script',
  'https://connect.facebook.net/en_US/fbevents.js');
  fbq('init', '$pixel_id'$initData);
  fbq('track', 'PageView', {}, { eventID: "$pageviewIdentifier" });$search_event$custom_events$track_code$other_handlers
</script>
<noscript>
  <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=$pixel_id&ev=PageView&eid=$pageviewIdentifier&noscript=1"/>$search_image$custom_event_images
</noscript>
EOD;

            echo $script;

            if (!$skip_cache_analytics) {
                $this->updateAnalytics();
            }
        }
    }


    public function sendSSEEvent() {

        if (in_array('sendSSEEvent', $this->functionsCalled)) {
            return;
        } else {
            array_push($this->functionsCalled, 'sendSSEEvent');
        }

        $extraEvents = array();
        $initData = $this->getInitData();
        $events = explode(';!;', get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-events', ''));
        if (count($events) === 1 && empty($events[0])) {
            $events = [];
        }

        foreach ($events as $event) {
            $eventDetails = json_decode($event, true);
            $name = $eventDetails['event'];
            $path = str_replace('@x@x@', '.*', preg_quote(str_replace('*', '@x@x@', $eventDetails['path'])));

            if (preg_match('#' . $path . '#', $_SERVER['REQUEST_URI'])) {
                array_push($extraEvents, array('name' => $name, 'custom' => array()));
            }
        }

        foreach ($this->actionEvents as $event) {
            $eventDetails = $event;
            $name = $eventDetails['event'];

            if (array_key_exists('extra_user', $eventDetails)) {
                array_push($extraEvents, array('name' => $name, 'extra_user' => $this->hashFacebookData($eventDetails['extra_user']), 'custom' => $eventDetails['custom']));
            } else {
                array_push($extraEvents, array('name' => $name, 'custom' => $eventDetails['custom']));
            }
        }

        $user_data = $this->getUserData($initData);

        $skip_cache_sse = get_option(WP_SIGNALS_SLUG . '-configuration-app-skip-cache-sse', false);
        if ($skip_cache_sse) {

            $all_data = array(
                "basic" => array(
                    "url" => WP_SIGNALS_FULL_PAGE_URL
                ),
                "user" => $user_data,
                "events" => $extraEvents,
                "renderId" => $this->renderIdentifier
            );

            $sse_code = 'var xt = new XMLHttpRequest(); xt.onreadystatechange = function() {}; xt.open("POST", "/wp-json/wp-signals/v1/sse", true); xt.setRequestHeader("Content-Type", "application/json;charset=UTF-8"); xt.send(\'{"data": "' . base64_encode(json_encode($all_data)) . '"}\');';
            echo '<script>' . $sse_code . '</script>' . "\n";

        } else {
            $events = get_option(WP_SIGNALS_SLUG . '-configuration-app-sse-events', false);
            $batchSendEvents = false /*get_option(WP_SIGNALS_SLUG . '-configuration-app-batch-sse-events', false)*/;

            if (!$events || !is_array($events)) {
                $events = array();
            }

            array_push($events, array('event_name' => 'PageView', 'event_id' => $this->getEventIdentifier('PageView', 'pg'), 'event_time' => time(), 'action_source' => 'website', 'event_source_url' => WP_SIGNALS_FULL_PAGE_URL, 'user_data' => $user_data));
            foreach ($extraEvents as $key=>$extraEvent) {
                $actual_user_data = (!array_key_exists('extra_user', $extraEvent)) ? $user_data : array_merge($user_data, $extraEvent['extra_user']);
                array_push($events, array_merge(array('event_name' => $extraEvent['name'], 'event_id' => $this->getEventIdentifier($extraEvent['name'], 'ex' . $key), 'event_time' => time(), 'action_source' => 'website', 'event_source_url' => WP_SIGNALS_FULL_PAGE_URL, 'user_data' => $actual_user_data), $extraEvent['custom']));
            }

            if ($batchSendEvents) {
                update_option(WP_SIGNALS_SLUG . '-configuration-app-sse-events', $events);
            } else {
                Common::sendFacebookSSE($events);
            }
        }



        $skip_cache_analytics = get_option(WP_SIGNALS_SLUG . '-configuration-app-skip-cache-analytics', false);
        if ($skip_cache_analytics) {
            $track_code = 'var xta = new XMLHttpRequest(); xta.onreadystatechange = function() {}; xta.open("POST", "/wp-json/wp-signals/v1/al", true); xta.send();';
            echo '<script>' . $track_code . '</script>' . "\n";
        } else {
            $this->updateAnalytics();
        }
    }

    /**
     * Register the stylesheets for the frontend side of the site.
     *
     * @since    1.0.0
     */
    public function enqueueStyles(): void
    {
        /**
         * This function is provided for demonstration purposes only.
         *
         * The reason to register the style before enqueue it:
         * - Conditional loading: When initializing the plugin, do not enqueue your styles, but register them.
         *                        You can enque the style on demand.
         * - Shortcodes: In this way you can load your style only where shortcode appears.
         *              If you enqueue it here it will be loaded on every page, even if the shortcode isn’t used.
         *              Plus, the style will be registered only once, even if the shortcode is used multiple times.
         * - Dependency: The style can be used as dependency, so the style will be automatically loaded, if one style is depend on it.
         */
        $styleId = $this->pluginSlug . '-frontend';
        $styleFileName = ($this->settings->getDebug() === true) ? 'wp-signals-frontend.css' : 'wp-signals-frontend.min.css';
        $styleUrl = plugin_dir_url(__FILE__) . 'css/' . $styleFileName;

        if (wp_register_style($styleId, $styleUrl, array(), date('YmdHis', filemtime( plugin_dir_path(__FILE__) . 'css/' . $styleFileName)), 'all') === false)
        {
            exit(esc_html__('Style could not be registered: ', 'communal-marketplace') . $styleUrl);
        }

        /**
         * If you enque the style here, it will be loaded on every page on the frontend.
         * To load only with a shortcode, move the wp_enqueue_style to the callback function of the add_shortcode.
         */
        wp_enqueue_style($styleId);
    }

    /**
     * Register the JavaScript for the frontend side of the site.
     *
     * @since    1.0.0
     */
    public function enqueueScripts(): void
    {
        /**
         * This function is provided for demonstration purposes only.
         *
         * The reason to register the script before enqueue it:
         * - Conditional loading: When initializing the plugin, do not enqueue your scripts, but register them.
         *                        You can enque the script on demand.
         * - Shortcodes: In this way you can load your script only where shortcode appears.
         *              If you enqueue it here it will be loaded on every page, even if the shortcode isn’t used.
         *              Plus, the script will be registered only once, even if the shortcode is used multiple times.
         * - Dependency: The script can be used as dependency, so the script will be automatically loaded, if one script is depend on it.
         */
        $scriptId = $this->pluginSlug . '-frontend';
        $scripFileName = ($this->settings->getDebug() === true) ? 'wp-signals-frontend.js' : 'wp-signals-frontend.min.js';
        $scriptUrl = plugin_dir_url(__FILE__) . 'js/' . $scripFileName;
        if (wp_register_script($scriptId, $scriptUrl, array('jquery'), date('YmdHis', filemtime( plugin_dir_path(__FILE__) . 'js/' . $scripFileName)), false) === false)
        {
            exit(esc_html__('Script could not be registered: ', 'wp-signals') . $scriptUrl);
        }

        /**
         * If you enque the script here, it will be loaded on every page on the frontend.
         * To load only with a shortcode, move the wp_enqueue_script to the callback function of the add_shortcode.
         * If you use the wp_localize_script function, you should place it under the wp_enqueue_script.
         */
        wp_enqueue_script($scriptId);
    }

    private function getUserData($initData, $withHashing = true)
    {
        $ip = $this->get_ip_address();

        $user_data = $initData;
        if (!empty($ip) && $ip !== false) {
            $user_data['client_ip_address'] = $ip;
        }

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $user_data['client_user_agent'] = strtolower(trim($_SERVER['HTTP_USER_AGENT']));
        }

        if (isset($_GET['fbclid'])) {
            $user_data['fbc'] = 'fb.1.' . (time() * 1000) . '.' . sanitize_text_field($_GET['fbclid']);
        }

        if (isset($_COOKIE['_fbc'])) {
            $user_data['fbc'] = sanitize_text_field($_COOKIE['_fbc']);
        }

        if (isset($_COOKIE['_fbp'])) {
            $user_data['fbp'] = sanitize_text_field($_COOKIE['_fbp']);
        }

        return ($withHashing ? $this->hashFacebookData($user_data) : $user_data);
    }

    private function hashFacebookData($initData) {
        $user_data = $initData;

        if (isset($user_data['ph'])) {
            $user_data['ph'] = preg_replace('/\D/', '', $user_data['ph']);
        }

        foreach (array('fn', 'ln', 'em', 'external_id', 'ph') as $field_name) {
            if (isset($user_data[$field_name])) {
                $user_data[$field_name] = hash('sha256', strtolower(trim($user_data[$field_name] . '')));
            }
        }

        return $user_data;
    }

    private function get_ip_address() {

        if (!empty($_SERVER['HTTP_CLIENT_IP']) && $this->validate_ip($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP']) && $this->validate_ip($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== false) {
                $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                foreach ($iplist as $ip) {
                    if ($this->validate_ip($ip))
                        return $ip;
                }
            }
            else {
                if ($this->validate_ip($_SERVER['HTTP_X_FORWARDED_FOR']))
                    return $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED']) && $this->validate_ip($_SERVER['HTTP_X_FORWARDED']))
            return $_SERVER['HTTP_X_FORWARDED'];
        if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && $this->validate_ip($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
            return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        if (!empty($_SERVER['HTTP_FORWARDED_FOR']) && $this->validate_ip($_SERVER['HTTP_FORWARDED_FOR']))
            return $_SERVER['HTTP_FORWARDED_FOR'];
        if (!empty($_SERVER['HTTP_FORWARDED']) && $this->validate_ip($_SERVER['HTTP_FORWARDED']))
            return $_SERVER['HTTP_FORWARDED'];

        return $_SERVER['REMOTE_ADDR'];
    }

    private function validate_ip($ip) {

        if (strtolower($ip) === 'unknown')
            return false;

        $ip = ip2long($ip);

        if ($ip !== false && $ip !== -1) {
            $ip = sprintf('%u', $ip);

            if ($ip >= 0 && $ip <= 50331647)
                return false;
            if ($ip >= 167772160 && $ip <= 184549375)
                return false;
            if ($ip >= 2130706432 && $ip <= 2147483647)
                return false;
            if ($ip >= 2851995648 && $ip <= 2852061183)
                return false;
            if ($ip >= 2886729728 && $ip <= 2887778303)
                return false;
            if ($ip >= 3221225984 && $ip <= 3221226239)
                return false;
            if ($ip >= 3232235520 && $ip <= 3232301055)
                return false;
            if ($ip >= 4294967040)
                return false;
        }
        return true;
    }
}

