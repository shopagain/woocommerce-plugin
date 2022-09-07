<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://softeq.com
 * @since      1.0.0
 *
 * @package    Shopagain
 * @subpackage Shopagain/admin/partials
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="sac-settings">
    <div class="sac-content-wrapper">
        <div class="sac-content">
            <div class="sac-logo">
                <!-- <img src="<?php // echo SHOPAGAIN_URL ?>admin/image/shopagain-logo.png"> -->
                <img src="https://uploads-ssl.webflow.com/626bdf68454e2d0e6cd4cc79/626bdf68454e2dbe04d4ccbf_SA-redblack-logo.svg">
            </div>
            <div class="sac-content-subtitles">
                <?php if ( isset( $this->shopgain_options['shopagain_auth_key'] ) ) { ?>
                    <span class="sac-content-title">Your ShopAgain is connected!</span>
                    <span class="sac-content-subtitle">Head back to the ShopAgain dashboard to continue with next steps for getting your account up and running or to modify any of your ShopAgain + WooCommerce integration settings.</a> </span>
                    <div class="connect-buttons">
                        <fieldset class="connect-button">
                            <a id="wck_manage_settings" class="button button-primary" href="<?= $this->shopagain_url?>woocommerce/" target="_blank">Go to your ShopAgain Page</a>
                        </fieldset>
                    </div>
                <?php } else { ?>
                    <span class="sac-content-title">Connect your ShopAgain account</span>
                    <span class="sac-content-subtitle">
                    SYNC ALL YOUR STORE DATA WITH A SINGLE CLICK</span>
                    <div class="connect-buttons">
                        <fieldset class="connect-button">
                            <a id="wck_oauth_connect" class="button button-primary" href="<?= $this->shopagain_url?>woocommerce/auth/?url=<?= get_home_url(); ?>">Connect Account</a>
            
                        </fieldset>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
