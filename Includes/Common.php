<?php
namespace WpSignals\Includes;

class Common
{
    public static function generateRandomString($length = 30) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            if ($i % 6 == 0 && $i > 0) {
                $randomString .= '-';
            } else {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
        }
        return $randomString;
    }

    public static function GetActivePlugins($filter = '') {
        $apl = get_option('active_plugins');
        $plugins = get_plugins();
        $activated_plugins = array();

        foreach ($apl as $p){
            if(isset($plugins[$p])){
                if ($filter === '' || (stripos($plugins[$p]['Name'], $filter) !== false)) {
                    array_push($activated_plugins, $plugins[$p]['Name']);
                }
            }
        }

        return $activated_plugins;
    }

    public static function resync($forceSync = false) {

        $last_resync_timestamp = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-last-resync-timestamp', false);

        if ($forceSync || (!$last_resync_timestamp || $last_resync_timestamp + 12*60*60 < time())) {

            $adminEmail = get_option('admin_email');
            $environment = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-setup', 'fb-api');
            $manual_token = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-sse-manual-token');
            if ($environment === 'fb-api') {
                $environment = 'wizard';
            } else {
                $environment = 'manual';
            }

            $out_sync_data = array( 'setup_type' => $environment );
            if (!empty($adminEmail)) { $out_sync_data['contact_email'] = $adminEmail; }
            if (!empty($manual_token)) { $out_sync_data['token'] = $manual_token; }

            $installation_id = get_option(WP_SIGNALS_SLUG . '-configuration-app-id', false);
            $in_sync_data = Common::CallApi('POST', 'https://fb-api.signalresiliency.com/resync?installation_id=' . $installation_id, $out_sync_data);

            if ($in_sync_data !== false) {
                $in_sync_data = json_decode($in_sync_data, true);

                if (array_key_exists('result', $in_sync_data) && $in_sync_data['result'] === 'success' && array_key_exists('token', $in_sync_data) && !empty($in_sync_data['token'])) {
                    update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-sse-token', $in_sync_data['token']);
                }
            }

            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-last-resync-timestamp', time());
        }
    }

    public static function sendFacebookSSE($events) {
        Common::resync();
        $token = Common::getSSEToken();

        $errorsCounter = get_option(WP_SIGNALS_SLUG . '-configuration-app-sse-errors-count', 0);

        if ($token !== false && $events !== false && is_array($events) && count($events) > 0 && $errorsCounter < 5) {
            $pixel_id = get_option(WP_SIGNALS_SLUG . '-configuration-app-pixel-id', false);

            foreach($events as $key => $event) {
                if (array_key_exists('info', $event)) {
                    unset($event['info']);
                }
            }

            $post_data = array(
                'data' => $events,
                'access_token' => $token
            );

            $result = wp_remote_post('https://graph.facebook.com/v9.0/' . $pixel_id . '/events', array(
                'body' => json_encode($post_data),
                'timeout' => 5,
                'headers' => array('Content-Type' => 'application/json')
            ));

            // Execute post
            $http_code = wp_remote_retrieve_response_code($result);
            $http_body = wp_remote_retrieve_body($result);

            if ($http_body === false || ($http_code !== false && $http_code >= 400) || Common::isResponseError($http_body)) {

                $errorMessage = 'Unspecified error occurred while pushing data to Facebook. Possible timeout or slow network connection.';
                if ($result !== false) {
                    $informativeError = Common::extractErrorMessage($http_body);
                    if ($informativeError !== false) {
                        $errorMessage = $informativeError;
                    }
                }

                update_option(WP_SIGNALS_SLUG . '-configuration-app-sse-errors-count', $errorsCounter + 1);
                update_option(WP_SIGNALS_SLUG . '-configuration-app-sse-last-transfer-message', array(
                    'unix' => time(),
                    'message' => 'Detected ' . ($errorsCounter + 1) . ' error messages while pushing SSE events. ' . $errorMessage
                ));

            } else {
                update_option(WP_SIGNALS_SLUG . '-configuration-app-sse-errors-count', 0);
                update_option(WP_SIGNALS_SLUG . '-configuration-app-sse-last-transfer-message', array(
                    'unix' => time(),
                    'message' => 'Successfully pushed latest group of SSE event(s). Server communication with Facebook is working properly.'
                ));
            }

            update_option(WP_SIGNALS_SLUG . '-configuration-app-sse-events', false);
            update_option(WP_SIGNALS_SLUG . '-configuration' . '-app-last-sse-event-timestamp', time());
        }
    }


    public static function GetCachingPlugin() {
        $apl = get_option('active_plugins');
        $plugins = get_plugins();
        $activated_plugins = array();

        foreach ($apl as $p){
            if(isset($plugins[$p])){
                $plugin_name = $plugins[$p]['Name'];
                $confirmed = (stripos($plugin_name, 'cache') !== false || stripos($plugin_name, 'caching') !== false);

                if (stripos($plugin_name, 'swift performance') !== false) {
                    $confirmed = true;
                }

                if (stripos($plugin_name, 'cachify') !== false) {
                    $confirmed = true;
                }

                if (stripos($plugin_name, 'speed of light') !== false) {
                    $confirmed = true;
                }

                if ($confirmed) {
                    array_push($activated_plugins, $plugin_name);
                }
            }
        }

        return count($activated_plugins) > 0 ? $activated_plugins[0] : false;
    }


    public static function CallAPI($method, $url, $data = false)
    {
        switch ($method)
        {
            case "POST":

                if ($data) {
                    $result = wp_remote_post($url, array(
                        'body' => json_encode($data),
                        'headers' => array('Content-Type' => 'application/json')
                    ));
                } else {
                    $result = wp_remote_post($url);
                }

                break;
            default:
                if ($data) {
                    $url = sprintf("%s?%s", $url, http_build_query($data));
                }

                $result = wp_remote_get($url);
        }

        $http_code = wp_remote_retrieve_response_code($result);
        $http_body = wp_remote_retrieve_body($result);

        if ($result !== false && $http_body !== false && ($http_code === false || $http_code < 400)) {
            return $http_body;
        }

        return false;
    }

    private static function getSSEToken()
    {
        $token = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-sse-token', false);
        $environment = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-setup', 'fb-api');
        if ($environment === 'manual' && get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-sse-manual-token', false) !== false) {
            $token = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-sse-manual-token', false);
        }

        if ($environment === 'fb-api' && ($token === false || empty($token))) {
            Common::resync(true);
            $token = get_option(WP_SIGNALS_SLUG . '-configuration' . '-app-sse-token', false);
        }

        return ($token === false || empty($token)) ? false : $token;
    }

    private static function extractErrorMessage($result, $attempts = 0) {

        if (is_string($result)) {
            $data = json_decode($result, true);
            if ($data === null) {
                $data = $result;
            }

            $result = $data;
        }

        if (!is_array($result)) {
            return substr('Error detected. ' . $result, 0, 500);
        }

        if ($attempts < 5 && array_key_exists('data', $result)) {
            return Common::extractErrorMessage($result['data'], $attempts + 1);
        }

        if (array_key_exists('error', $result)) {
            return Common::extractErrorMessage($result['error'], $attempts + 1);
        }

        if (array_key_exists('message', $result)) {
            return Common::extractErrorMessage($result['message'], $attempts + 1);
        }

        return substr('Error detected. ' . json_encode($result), 0, 500);
    }


    private static function isResponseError($result) {

        if ($result !== false && is_string($result)) {
            $data = json_decode($result, true);
            if ($data === null) {
                $data = $result;
            }

            $result = $data;
        }

        return ($result !== false && is_array($result) && array_key_exists('error', $result) && array_key_exists('code', $result['error']));
    }
}