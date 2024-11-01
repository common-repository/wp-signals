<?php

namespace WpSignals\Admin\screens;

use WpSignals\Admin\components\Sidebar;
use WpSignals\Includes\Common;

class Help
{

    private $SidebarComponent;

    public function __construct()
    {
        $this->SidebarComponent = new Sidebar;
    }

    private function update() {

    }

    public function render() {

        $iconsUrl = plugin_dir_url(__FILE__) . '../assets/icons';

        if ($_SERVER["REQUEST_METHOD"] == "POST"){
            $this->update();
        }

        ?>

            <div class="wp-signals-screen wp-signals-help-screen">

                <div class="screen-body">

                    <div class="section">
                        <h3>Frequently Asked Questions</h3>
                        <img class="section-icon" src="<?php echo $iconsUrl ?>/faq.png" />

                        <div class="question-item">
                            <p class="question">What is a Facebook Pixel?</p>
                            <p class="answer">A Facebook pixel helps you measure customer actions, build audiences and unlock optimization tools for your ads.</p>
                        </div>

                        <div class="question-item">
                            <p class="question">How can I add my first pixel?</p>
                            <p class="answer">The simplest way to add a FB pixel with the WP Signals plugin is to use our powerful wizard, which is the first screen shown after activating the plugin.</p>
                        </div>

                        <div class="question-item">
                            <p class="question">What is the Conversions API?</p>
                            <p class="answer">The Conversions API allows advertisers to send web events from their servers directly to Facebook, skipping any roadblocks set by browsers and ad blockers. WP Signals supports all features from the Conversions API.</p>
                        </div>

                        <div class="question-item">
                            <p class="question">I don't see my pixels in the dropdown list?</p>
                            <p class="answer">If you cannot see some of your pixels when using the wizard, they might have specific permissions or belong to a business instead of your personal user account. In that case, you can grant yourself those permissions, or use the manual setup process instead of the wizard.</p>
                        </div>

                        <div class="question-item">
                            <p class="question">How do I trigger custom events?</p>
                            <p class="answer">To setup custom events, you can use the "Data & Events" tab, and add firing rules on specific urls. For example, you can fire a "Contact" event on a url such as "/contact/". You can setup as many events as you like.</p>
                        </div>
                    </div>


                    <div class="section">
                        <h3>Get help</h3>
                        <img class="section-icon" src="<?php echo $iconsUrl ?>/message.png" />

                        <p>Check out our collection of Frequently Asked Questions to get an answer to the most common questions that we get pinged about.</p>
                        <p>If you cannot find the answer you are looking for there, please feel free to visit our website at <a style="font-weight: bold;" href="https://signalresiliency.com">https://signalresiliency.com</a>, or check our Wordpress plugin page.</p>
                        <p>If you like our plugin, please use a minute of your time to rate it, so we can continue to spend time and resources making it even better for everyone.</p>
                    </div>



                </div>

                <div class="screen-sidebar">
                    <?php $this->SidebarComponent->render() ?>
                </div>
            </div>
        <?php
    }
}
