<?php
/**
 * Plugin Name: WooCommerce Product Add-ons - Variable Product Extension
 * Plugin URI:
 * Description: Customizes the Product Add-ons extension so that variable products can be used with add-on features.
 * Version: 0.1.0
 * Author: Angell EYE
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( '__AONPATH__', plugin_dir_path( __FILE__ ) );
define( '__AONURL__', plugin_dir_url( __FILE__ ) );

/**
 * Class SPAONS
 */
if( !class_exists( 'SPAONS' ) ):
    class SPAONS{
        /**
         * construct
         */
        public function __construct()
        {
            //Add PayPal Standard button arg
            add_filter( 'woocommerce_paypal_args', array($this,'ae_paypal_standard_additional_parameters'));
            add_action( 'woocommerce_variation_options', array( $this, 'woocommerce_variation_options' ), 10, 3 );
            add_action( 'woocommerce_save_product_variation', array( $this, 'woocommerce_save_product_variation' ), 10, 2 );
            add_action( 'wp_head', array( $this, 'woocommerce_before_variations_form' ), 99 );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts') );
            add_action( 'wp_ajax_change_product_variation', array( $this, 'change_product_variation') );
            add_action( 'wp_ajax_nopriv_change_product_variation', array( $this, 'change_product_variation') );
            add_filter( 'woocommerce_add_cart_item_data', array( $this, 'woocommerce_add_cart_item_data'), 99, 3 );           
            register_activation_hook( __FILE__, array($this, 'install') );
            register_deactivation_hook( __FILE__, array($this, 'deactivate') );

        }

        /**
         * Plugin Activation Dependency Check
         */
        function install() {
            /**
             * Check if WooCommerce & WooCommerce Product Add-ons are active
             **/
            if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ||
                !in_array( 'woocommerce-product-addons/woocommerce-product-addons.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

                // Deactivate the plugin
                deactivate_plugins(__FILE__);

                // Throw an error in the wordpress admin console
                $error_message = __('This plugin requires <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> &amp; <a href="http://www.woothemes.com/products/product-add-ons/">WooCommerce Product Add-ons</a> plugins to be active!', 'woocommerce');
                die($error_message);

            }
            
            /**
             * Log activation in Angell EYE database via web service.
             */
            $log_url = $_SERVER['HTTP_HOST'];
            $log_plugin_id = 99;
            $log_activation_status = 1;
            wp_remote_request('http://www.angelleye.com/web-services/wordpress/update-plugin-status.php?url=' . $log_url . '&plugin_id=' . $log_plugin_id . '&activation_status=' . $log_activation_status);

        }

        /**
         * Plugin Deactivation
         */
        function deactivate() {
            $log_url = $_SERVER['HTTP_HOST'];
            $log_plugin_id = 99;
            $log_activation_status = 0;
            wp_remote_request('http://www.angelleye.com/web-services/wordpress/update-plugin-status.php?url='.$log_url.'&plugin_id='.$log_plugin_id.'&activation_status='.$log_activation_status);

        }

        /**
         * Add additional parameters to the PayPal Standard checkout built into WooCommerce.
         *
         */
        public function ae_paypal_standard_additional_parameters($paypal_args)
        {
            $paypal_args['bn'] = 'AngellEYE_SP_WooCommerce';
            return $paypal_args;
        }      

        /*
         * add options variation
         */
        function woocommerce_variation_options( $loop, $variation_data, $variation )
        {
            $add_ons = get_post_meta( $variation->ID, '_variable_add_ons', true );
            ?>
            <label><input type="checkbox" class="checkbox" name="variable_add_ons[<?php echo $loop; ?>]" <?php checked( $add_ons, 'yes' ); ?> /> <?php _e( 'Display add-ons', 'woocommerce' ); ?>
                <a class="tips" data-tip="<?php esc_attr_e( 'Enable Options Add Ons', 'woocommerce' ); ?>" href="#">[?]</a>
            </label>
            <?php
        }

        /*
         * save add ons of product variation
         */
        function woocommerce_save_product_variation( $variation_id, $i )
        {
            $variable_add_ons = isset( $_POST['variable_add_ons'] ) ? $_POST['variable_add_ons'] : array();
            for ( $count = 0; $count <= $i; $count ++ )
            {
                $add_ons = isset( $variable_add_ons[ $count ] ) ? 'yes' : 'no';
                update_post_meta( $variation_id, '_variable_add_ons', wc_clean( $add_ons ) );
            }
        }

        /*
         * add script
         */
        function woocommerce_before_variations_form()
        {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($){
                    $(document).on('change','#format',function(){
                        //$('input[name="variation_id"]').change(function(){
                        $variation_id = $('input[name=variation_id]').val();
                        var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
                        $.post(
                            ajaxurl,
                            {
                                'action': 'change_product_variation',
                                'data':   $variation_id
                            },
                            function(response){
                                style_add_ons(response);
                            });
                    });
                    function style_add_ons(bool)
                    {
                        if ( bool == 'yes' )
                        {
                            $('.product-addon').css('display', 'block')
                        }
                        else
                        {
                            $('.product-addon').css('display', 'none');
                        }
                    }
                });
            </script>
            <?php
        }

        /*
         * load ajax
         */
        function change_product_variation()
        {
            $product_id = $_POST['data'];
            $add_ons = get_post_meta( $product_id, '_variable_add_ons', true );
            echo $add_ons;
            die();
        }

        function woocommerce_add_cart_item_data( $cart_item_data, $product_id, $variation_id )
        {
            $add_ons = get_post_meta( $variation_id, '_variable_add_ons', true );
            if( $add_ons == 'no' ) $cart_item_data = '';
            return $cart_item_data;
        }

        /*
         *
         */
        function admin_enqueue_scripts()
        {

        }

        /**
         * destruct
         */
        public function __destruct()
        {

        }

    }

    $SPAONS = new SPAONS();
endif;