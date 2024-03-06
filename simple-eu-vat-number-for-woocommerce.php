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

defined( 'ABSPATH' ) || exit;

class Simple_EU_VAT_Number_For_WooCommerce {

    const ALLOWED_COUNTRIES = array( 'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK' );

    public function __construct() {
        //require_once 'vendor/autoload.php'; // to include php-vat-checker library

        add_action( 'plugins_loaded', array( $this, 'svnfw_check_dependencies' ) );
        add_action('wp_enqueue_scripts', array($this, 'svnfw_enqueue_custom_scripts'));
        add_action( 'plugins_loaded', array( $this, 'svnfw_load_plugin_textdomain' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'svnfw_save_vat_number' ) );
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'svnfw_display_vat_number_admin_order_meta' ), 10, 1 );
        add_action( 'woocommerce_checkout_fields', array( $this, 'svnfw_display_vat_number_field_checkout' ) );
        add_action( 'woocommerce_checkout_process', array( $this, 'svnfw_validate_vat_number' ) );
        //sección de detalles de facturación en la página de edición del pedido.
        add_filter('woocommerce_billing_fields', array($this, 'svnfw_display_vat_number_field_account'));
        add_filter('woocommerce_admin_billing_fields', array($this, 'svnfw_display_vat_number_admin'));
        // Agrega el campo a la página del perfil de usuario
        add_filter('woocommerce_customer_meta_fields', array($this, 'svnfw_add_vat_number_to_user_profile'));
        // Guarda el valor del campo cuando se actualiza el perfil de usuario
        add_action('woocommerce_save_account_details', 'svnfw_save_vat_number_user_profile');



        
    }



    public function svnfw_add_vat_number_to_user_profile($fields) {
        $fields['billing']['fields']['vat_number'] = array(
            'label' => __('VAT Number', 'svnfw'),
            'description' => ''
        );
    
        return $fields;
    }
    
    public function svnfw_save_vat_number_user_profile($user_id) {
        if (isset($_POST['vat_number'])) {
            update_user_meta($user_id, 'vat_number', sanitize_text_field($_POST['vat_number']));
        }
    }



    
    public function svnfw_check_dependencies() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( __('This plugin requires WooCommerce to be installed and active.', 'svnfw') );
        }
    }

    public function svnfw_enqueue_custom_scripts() {
        if(is_checkout() || is_account_page()) {
            wp_enqueue_script('custom-scripts', plugins_url('/scripts.js', __FILE__), array('jquery'), null, true);
            wp_localize_script('custom-scripts', 'svnfw_settings', array( 'allowed_countries' => self::ALLOWED_COUNTRIES, ));
        }
    }
    
    public function svnfw_display_vat_number_admin( $fields ) {
        $fields['vat_number'] = array(
            'label' => __('VAT Number', 'svnfw')
        );
    
        return $fields;
    }
    
    public function svnfw_load_plugin_textdomain() {
        load_plugin_textdomain( 'svnfw', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
    }

    public function svnfw_save_vat_number( $order_id ) {
        if ( ! empty( $_POST['vat_number'] ) ) {
            update_post_meta( $order_id, '_vat_number', sanitize_text_field( $_POST['vat_number'] ) );
        }
    }

    public function svnfw_display_vat_number_admin_order_meta( $order ) {
        echo '<p><strong>' . __('VAT Number:', 'svnfw') . '</strong> ' . get_post_meta( $order->get_id(), '_vat_number', true ) . '</p>';
    }
    

    public function svnfw_display_vat_number_field_checkout( $fields ) {
        $fields['billing']['vat_number'] = array(
            'label'     => __('VAT Number', 'svnfw'),
            'placeholder'   => _x('VAT Number', 'placeholder', 'svnfw'),
            'required'  => false,
            'class'     => array('form-row-wide'),
            'clear'     => true,
            'priority'  => 35,
        );

        return $fields;
    }
    

    public function svnfw_display_vat_number_field_account( $fields ) {
        $fields['vat_number'] = array(
            'label'     => __('VAT Number', 'svnfw'),
            'placeholder'   => _x('VAT Number', 'placeholder', 'svnfw'),
            'description' => '',
            'priority' => 35
        );
        return $fields;
    }
    
    
    

    public function svnfw_validate_vat_number() {
        $allowed_countries = self::ALLOWED_COUNTRIES;
        if ( in_array( WC()->customer->get_billing_country(), $allowed_countries ) ) {
            $vat_number = isset( $_POST['vat_number'] ) ? $_POST['vat_number'] : '';
            if ( ! empty( $vat_number ) && ! $this->svnfw_is_valid_vat_number( $vat_number ) ) {
                wc_add_notice( __('Invalid VAT Number.', 'svnfw'), 'error' );
            }
        }
    }

    public function svnfw_is_valid_vat_number( $vat_number ) {
        //todo
        return true;
    }
    
    
}

new Simple_EU_VAT_Number_For_WooCommerce();


