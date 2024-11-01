<?php

namespace WpSignals\Admin\components;

class Sidebar
{

    public function render() {

        $imagesUrl = plugin_dir_url(__FILE__) . '../assets/images';

        ?>
            <div class="wp-signals-component wp-signals-component-sidebar">

                <h2>Expert advice</h2>

                <div class="news-item">
                    <h3>Can I send data to Facebook?</h3>
                    <p>Our plugin can be used to automatically setup Facebook pixels, either on the client side or using server side events.</p>
                    <img src="<?php echo $imagesUrl ?>/facebook.jpg" />
                </div>


                <div class="news-item">
                    <h3>WooCommerce Support</h3>
                    <p>WooCommerce is a popular, open-source e-commerce plugin for WordPress. WP Signals automatically triggers WooCommerce events without you having to click a button, in addition to supporting several other WordPress plugins out of the box.</p>
                    <img src="<?php echo $imagesUrl ?>/woocommerce.png" />
                </div>


                <div class="news-item">
                    <h3>Browser Independence</h3>
                    <p>Implement products such as Facebook Conversions API to establish channels that are browser independent, and which cannot be restricted by Ad Blockers.</p>
                    <img src="<?php echo $imagesUrl ?>/table.jpg" />
                </div>
            </div>
        <?php
    }
}
