<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://shopagain.io
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
                <img src="<?php echo esc_url(plugin_dir_url( dirname( __FILE__ ) ) . 'image/shopagain-logo-full.svg'); ?>">
            </div>
            <div class="sac-content-subtitles">
                <?php if ( isset( $this->shopgain_options['shopagain_auth_key'] ) ) { ?>
                    <span class="sac-content-title">Your store is connected to ShopAgain!</span>
                    <span class="sac-content-subtitle">
                    Head back to your ShopAgain account to complete your account setup and start using ShopAgain.
                    </span>
                    <div class="connect-buttons">
                        <fieldset class="connect-button">
                            <a id="wck_manage_settings" class="button button-primary" href="<?php echo esc_url($this->shopagain_url . "woocommerce/?url=" . get_home_url()); ?>" target="_blank">Continue to ShopAgain</a>
                        </fieldset>
                    </div>
                <?php } else { ?>
                    <span class="sac-content-title">Connect your ShopAgain account</span>
                    <span class="sac-content-subtitle">
                    Connect your WooCommerce store with ShopAgain to begin syncing data. Sign up for a ShopAgain account before you begin.
                    </span>
                    <div class="connect-buttons">
                        <fieldset class="connect-button">
                            <a id="wck_oauth_connect" class="button button-primary" href="<?php echo esc_url($this->shopagain_url . "woocommerce/auth/?url=" . get_home_url()); ?>">Connect Account</a>
            
                        </fieldset>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
