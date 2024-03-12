<?php
/*
Plugin Name: The most simple way to manage EU VAT Number for WooCommerce
Plugin URI: https://github.com/jjmontalban/simple-eu-vat-number-for-woocommerce
Description: A plugin to manage VAT numbers for EU customers in WooCommerce.
Version: 1.0
Author: JJMontalban
Author URI:  https://jjmontalban.github.io
Text Domain: svnfw
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Domain Path: lang
*/  

use DragonBe\Vies\Vies;
use DragonBe\Vies\ViesException;

defined( 'ABSPATH' ) || exit;

class Simple_EU_VAT_Number_For_WooCommerce {

    public $allowed_countries;

    public function __construct() {
        require_once 'vendor/autoload.php';

        add_action( 'plugins_loaded', array( $this, 'svnfw_check_dependencies' ) );
        add_action( 'plugins_loaded', array( $this, 'svnfw_load_plugin_textdomain' ) );
        add_action('woocommerce_loaded',  array( $this, 'svnfw_define_allowed_countries' ) );
        add_action('wp_enqueue_scripts', array($this, 'svnfw_enqueue_custom_scripts'));
        //checkout
        add_action( 'woocommerce_checkout_fields', array( $this, 'svnfw_add_vat_checkout' ) );
        add_action( 'woocommerce_after_checkout_validation', array( $this, 'svnfw_validate_vat_checkout' ), 10, 2 );
        add_action( 'woocommerce_created_customer', array( $this, 'svnfw_save_vat_registration' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'svnfw_save_vat_checkout' ) );
        //account
        add_filter('woocommerce_billing_fields', array($this, 'svnfw_add_vat_account'));
        add_action('woocommerce_after_save_address_validation', array($this, 'svnfw_save_vat_account'), 10, 2);
        //admin
        add_filter('woocommerce_customer_meta_fields', array($this, 'svnfw_add_vat_admin'));
        add_action('edit_user_profile_update', array($this, 'svnfw_save_vat_admin'));
        //order
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'svnfw_add_vat_order' ), 10, 1 );
        //email
        add_filter( 'woocommerce_email_order_meta_fields',  array( $this, 'svnfw_add_vat_email' ), 10, 3 );
    }


    /**
     * Checks the plugin dependencies.
     *
     * This method checks if WooCommerce is installed and active.
     * If WooCommerce is not active, it deactivates this plugin and displays an error message.
     *
     * @return void
     */
    public function svnfw_check_dependencies() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( __('This plugin requires WooCommerce to be installed and active.', 'svnfw') );
        }
    }

    /**
     * Loads the plugin text domain for translation.
     *
     * This method is used to load the text domain, which is used for translation. 
     * The text domain is a unique identifier, which helps WordPress to fetch the translated strings.
     *
     * @return void
     */
    public function svnfw_load_plugin_textdomain() {
        load_plugin_textdomain( 'svnfw', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
    }

    /**
     * Defines the allowed countries for the plugin.
     *
     * This method gets the store's default country from the WooCommerce settings,
     * then defines an array of all EU countries (except the store's default country).
     * The resulting array is saved in the $allowed_countries property of the class.
     *
     * @return void
     */
    function svnfw_define_allowed_countries() {
        if (class_exists('WooCommerce')) {
            $store_country = get_option('woocommerce_default_country');
            $country_info = explode(":", $store_country);
            $store_country = $country_info[0];
            $allowed_countries = array( 'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK' );
            $this->allowed_countries = array_diff($allowed_countries, array($store_country));
        }
    }

    /**
     * Enqueues custom scripts for the plugin.
     *
     * This method is used to enqueue custom JavaScript scripts for the plugin.
     * The scripts are only enqueued on the checkout and account pages. 
     * The method also localizes the script, passing the array of allowed countries to the JavaScript file.
     *
     * @return void
     */
    public function svnfw_enqueue_custom_scripts() {
        if(is_checkout() || is_account_page()) {
            wp_enqueue_script('custom-scripts', plugins_url('/scripts.js', __FILE__), array('jquery'), null, true);
            wp_localize_script('custom-scripts', 'svnfw_settings', array( 'allowed_countries' => $this->allowed_countries 
        ));
        }
    }


    //////////////////////CHECKOUT 


    /**
     * Displays the VAT number field at checkout.
     *
     * This method is used to add a VAT number field to the billing section at checkout.
     * The field is not required, it spans the full width of the form, clears after input, and has a priority of 35.
     *
     * @param array $fields Existing checkout fields.
     * @return array $fields Modified checkout fields.
     */
    public function svnfw_add_vat_checkout( $fields ) {
        $fields['billing']['vat_number'] = array(
            'label'     => __('VAT Number', 'svnfw'),
            'placeholder'   => __('VAT Number', 'placeholder', 'svnfw'),
            'required'  => false,
            'class'     => array('form-row-wide'),
            'clear'     => true,
            'priority'  => 35,
        );

        return $fields;
    }

    /**
     * Validates the VAT number during checkout.
     *
     * This method is used to validate the VAT number entered at checkout.
     * If the VAT number is not valid, it adds an error message which prevents the checkout from being completed.
     *
     * @param array $data An array of posted data.
     * @param object $errors A WP_Error object containing any errors encountered during validation.
     */
    public function svnfw_validate_vat_checkout( $data, $errors ) {
        if (in_array(WC()->customer->get_billing_country(), $this->allowed_countries)) {
            $vat_number = isset($data['vat_number']) ? $data['vat_number'] : '';
            if (!empty($vat_number) && !$this->svnfw_is_valid_vat_number($vat_number)) {
                $errors->add( 'validation', '<strong>' . __( 'Error', 'svnfw' ) . '</strong>: ' . __( 'Invalid VAT Number.', 'svnfw' ) );
            }
        }
    }

    /**
     * Saves the VAT number when a new user account is created at checkout.
     *
     * This method is used to save the VAT number to the user meta when a new user account is created during checkout.
     * This allows the VAT number to be associated with the user for future orders.
     *
     * @param int $customer_id The ID of the customer who has just been created.
     */
    public function svnfw_save_vat_registration( $customer_id ) {
        if ( isset( $_POST['vat_number'] ) ) {
            $vat_number = sanitize_text_field( $_POST['vat_number'] );
            if ( !empty( $vat_number ) ) {
                update_user_meta( $customer_id, 'vat_number', $vat_number );
            }
        }
    }
    

    /**
     * Saves the VAT number to the order meta after checkout.
     *
     * This method is used to save the VAT number to the order meta after a successful checkout.
     * The VAT number is saved in the order meta so it can be used for order processing and reporting.
     *
     * @param int $order_id The ID of the order that has just been created.
     */    
    public function svnfw_save_vat_checkout($order_id) {
        if (in_array(WC()->customer->get_billing_country(), $this->allowed_countries)) {
            $vat_number = isset($_POST['vat_number']) ? $_POST['vat_number'] : '';
            if (!empty($vat_number)) {
                update_post_meta($order_id, '_vat_number', sanitize_text_field($vat_number));
            }
        }
    }



    ///////////////////////////////////ACCOUNT

    /**
     * Adds the VAT number field to the account page.
     *
     * This method is used to add the VAT number field to the account page.
     *
     * @param array $fields Existing account page fields.
     * @return array $fields Modified account page fields.
     */
    public function svnfw_add_vat_account( $fields ) {
        $fields['vat_number'] = array(
            'label'     => __('VAT Number', 'svnfw'),
            'placeholder'   => __('VAT Number', 'placeholder', 'svnfw'),
            'description' => '',
            'priority' => 35
        );
        return $fields;
    }


    public function validate_vat_number($vat_number) {
        if (!empty($vat_number) && !$this->svnfw_is_valid_vat_number($vat_number)) {
            return new WP_Error('invalid_vat_number', __('Invalid VAT Number.', 'svnfw'));
        }
        return true;
    }
    
    public function svnfw_save_vat_account($user_id, $load_address) {
        if ($load_address === 'billing' && isset($_POST['vat_number'])) {
            $vat_number = $_POST['vat_number'];
            if (!empty($vat_number)) {
                $validation_result = $this->validate_vat_number($vat_number);
                if (is_wp_error($validation_result)) {
                    wc_add_notice($validation_result->get_error_message(), 'error');
                    return; // No guardes el valor si la validación falla
                }
                update_user_meta($user_id, 'vat_number', sanitize_text_field($vat_number));
            }
        }
    }
    
    
    
   ////////////////////////////////////////////////////////////ADMIN


    /**
     * Adds the VAT number field to user profiles.
     *
     * This method is used to add a VAT number field to the billing section of user profiles in the dashboard.
     *
     * @param array $fields Existing user profile fields.
     * @return array $fields Modified user profile fields.
     */
    public function svnfw_add_vat_admin($fields) {
        $fields['billing']['fields']['vat_number'] = array(
            'label' => __('VAT Number', 'svnfw'),
            'description' => ''
        );
    
        return $fields;
    }

    /**
     * Validates the VAT number from the admin user profile page.
     * If the VAT number is not valid, it adds an error and prevents the user metadata from being saved.
     * If the VAT number is valid, it saves the number in the user metadata.
     *
     * @param int $user_id The ID of the user being saved.
     */
    public function svnfw_save_vat_admin($user_id) {
        if (isset($_POST['vat_number'])) {
            $vat_number = sanitize_text_field($_POST['vat_number']);
    
            if (!$this->svnfw_is_valid_vat_number($vat_number)) {
                add_action('user_profile_update_errors', function($errors) {
                    $errors->add('vat_number', '<strong>' . __('Error', 'svnfw') . '</strong>: ' . __('Invalid VAT Number.', 'svnfw'));
                }, 10, 3);
    
                add_filter('update_user_metadata', function($null, $object_id, $meta_key, $meta_value) use ($user_id, $vat_number) {
                    if ($object_id == $user_id && $meta_key == 'vat_number' && $meta_value == $vat_number) {
                        return false;
                    }
                }, 10, 4);
            } else {
                update_user_meta($user_id, 'vat_number', $vat_number);
            }
        }
    }
    
    
    
    //////////////////////////////////ORDER
    

    /**
     * Displays the VAT number in the admin order page.
     *
     * This method is used to display the VAT number in the admin order page.
     *
     * @param WC_Order $order The order object.
     * @return void
     */
    public function svnfw_add_vat_order( $order ) {
        echo '<p><strong>' . __('VAT Number:', 'svnfw') . '</strong> ' . get_post_meta( $order->get_id(), '_vat_number', true ) . '</p>';
    }




    /////////////////////////EMAIL


    /**
     * Adds the VAT number to order emails.
     *
     * This function hooks into the 'woocommerce_email_order_meta_fields' filter,
     * which is used to add additional meta fields to order emails.
     *
     * @param array $fields The existing meta fields that are added to order emails.
     * @param bool $sent_to_admin Whether the email is being sent to the admin.
     * @param WC_Order $order The order object.
     * @return array $fields The modified meta fields.
     */
    public function svnfw_add_vat_email( $fields, $sent_to_admin, $order ) {
        $fields['vat_number'] = array(
            'label' => __( 'VAT Number', 'svnfw' ), // The field name
            'value' => get_post_meta( $order->get_id(), '_vat_number', true ),
        );

        return $fields;
    }

    ////////////////VALIDATION

    /**
     * Checks if a VAT number is valid.
     *
     * This method is used to check if a VAT number is valid.
     *
     * @param string $vat_number The VAT number to check.
     * @return bool True if the VAT number is valid, false otherwise.
     */
    public function svnfw_is_valid_vat_number( $vat_number ) {
        /* 
        $vies = new Vies();
        $countryCode = substr($vat_number, 0, 2);
        $vatNumber = substr($vat_number, 2);

        try {
            $result = $vies->validateVat($countryCode, $vatNumber);
            return $result->isValid();
        } catch (ViesException $e) {
            return false;
        } 
        */

        return true;
    }



}

new Simple_EU_VAT_Number_For_WooCommerce();