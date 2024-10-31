<?php
    /**
     * Plugin Name: referboard-woocommerce
     * Version: 1.14
     * Author: Referboard
     * Date: 17/08/2016
     * Time: 9:05 AM
     */
    global $product;
    global $post;
    
    include_once('Referboard_Plugin_Class.php');
?>

<?php
    
    /**
     * Check if WooCommerce is active
     **/
    
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
    {
        session_start();
        /**
         * Add Refer button setting page on admin dashboard
         */
        add_action( 'admin_menu', 'referboard_button_admin_menu' );
        
        
        /**
         * Save params in session
         */
        $api = get_option('refer_button_retailer_apiKey','');
        
        if(empty($api))
        {
            /**
             * If api key is not setup, need to show an error message
             */
            
            
        }else{
            
            /**
             * Add cookies track js
             */
           
    
            /**
             * Try to get refer board request params
             */
            $referboard = new Referboard_Plugin_Class($api);
          
            
            $track_js_url = $referboard->generateHomePageCode([],'js');
           wp_enqueue_script( 'referbutton_track', $track_js_url, array( 'jquery' ) );
            
          
            /**
             * Add refer button
             */
           // add_action( 'woocommerce_after_add_to_cart_form', 'generateReferbutton' );
            add_action( 'woocommerce_single_product_summary', 'generateReferbutton' );
            
            
            /**
             * When create new order, save data into database
             */
            add_action('woocommerce_new_order','save_referboard_params');
    
            /**
             * Call back function to referboard
             */
            add_action('woocommerce_order_status_completed','referboard_callback');
            
            
        }
    
    
    
        function referboard_button_admin_menu() {
            add_menu_page(
                'Referboard Button Setting',
                'ReferboardButton',
                'manage_options',
                'referboard-button-plugin',
                'referboard_button_options_page'
            );
        
            add_action( 'admin_init', 'register_refer_button_settings' );
        }
    
        /**
         * Setup two variables
         * refer_button_retailer_apiKey: Retailer API key
         * refer_button_currency: Retailer product price currency
         */
        function register_refer_button_settings()
        {
            register_setting( 'refer-button-setting-group', 'refer_button_retailer_apiKey' );
            register_setting( 'refer-button-setting-group', 'refer_button_currency' );
        }
    
    
        /**
         * Front html for refer button setting
         */
        function referboard_button_options_page() {
            ?>
            <div class="wrap">
                <h2>Refer Button Setting</h2>
                <form method="post" action="options.php">
                    <?php settings_fields( 'refer-button-setting-group' ); ?>
                    <?php do_settings_sections( 'refer-button-setting-group' ); ?>
                
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">APIKey</th>
                            <td><input type="text" name="refer_button_retailer_apiKey" value="<?php echo esc_attr( get_option('refer_button_retailer_apiKey') ); ?>" /></td>
                        </tr>
                    
                        <tr valign="top">
                            <th scope="row">Currency</th>
                            <td><input type="text" name="refer_button_currency" value="<?php echo esc_attr( get_option('refer_button_currency') ); ?>" /></td>
                        </tr>
                
                    </table>
                
                    <?php submit_button(); ?>
                </form>
        
            </div>
        
            <?php
        }
    
    
        /**
         * Add product information into script tag
         * Generate refer button
         */
        function generateReferbutton()
        {
        
            $apikey = get_option('refer_button_retailer_apiKey','');
            $referboard = new Referboard_Plugin_Class($apikey);
    
    
            $referboard_params = $referboard->captureReferboardParams($_REQUEST,'cookie');
            if(!empty($referboard_params['rf_product']) && $referboard_params['buyer_history'] ==0) {
                /**
                 * Try to get referboard params in request data, or
                 * If user still has a cookies period
                 */
        
        
                $_SESSION['referboard'] = $referboard_params;
        
        
               // error_log(print_r($_SESSION,true));
            }
            
            
            
            $id = get_the_ID();

            
            /**
             * Get product information
             */
            $product = new WC_Product( $id );
            //$product = wc_get_product(  get_the_ID()  );
            $product_id = $id;
            $product_price  = $product->price;
            $product_name = $product->get_title();
            $product_desc =str_replace(array("\r\n", "\r", "\n"), "<br />", $product->post->post_content);
            if(empty($product_desc))
            {
                $product_desc = str_replace(array("\r\n", "\r", "\n"), "<br />", $product->post->post_excerpt);
            }
            $product_desc = htmlentities($product_desc);
            $product_price_type = get_option('refer_button_currency','AUD');
            $product_retailer_id = get_option('refer_button_retailer_apiKey','');
            $product_url = get_permalink($id);
    
    
    
    
            /**
             * Get product feature image and image gallery list
             */
            $product_image_list = [];
            $product_feature_image = wp_get_attachment_url($product->get_image_id());
            $product_image_list[] = $product_feature_image;
    
    
            /**
             * Get images in gallery
             */

            $gallery_ids = $product->get_gallery_attachment_ids();
            if(is_array($gallery_ids))
            {
                foreach($gallery_ids as $id)
                {
                    $img_url = wp_get_attachment_url($id);

                    if(!in_array($img_url,$product_image_list))
                    {
                        $product_image_list[] = $img_url;
                    }

                }
            }
            
    
            $data = [];
            $data['product_id'] = $product_id;
            $data['product_title'] = $product_name;
            $data['product_desc']  = $product_desc;
            $data['product_image'] = $product_feature_image;
            $data['product_image_list'] = $product_image_list;
            $data['product_price'] = $product_price;
            $data['product_price_type'] = $product_price_type;
            $data['product_url'] = $product_url;
            $data['product_retailer_id'] = $product_retailer_id;
        
           $refer_button =  $referboard->generateReferButtonCode($data);
            
            
            echo $refer_button;
        }
    
    
    
        /**
         * When order has been finished:
         * If there is an saved session from referboard, and then need callback to referboard
         * @param $order_id: Order Id get from currenty tranaction
         */
        function referboard_callback($order_id)
        {
    
            $apikey = get_option('refer_button_retailer_apiKey','');
            $referboard = new Referboard_Plugin_Class($apikey);
            
            /**
             * Firstly need to check if there is an saved sesstion
             */
            $referboard_data_json = get_transient($order_id);
            $order = new WC_Order( $order_id );
            if(isset($referboard_data_json))
            {
                /**
                 * If data found in database, need to make callback to referboard
                 */
            
                $referboard_data = json_decode($referboard_data_json,true);
                $referboard_data['amount'] = $order->get_total();
                
                $result = $referboard->sendTransactionData($referboard_data);
    
               // error_log(print_r($result,true));
                /**
                 * After make callback to Referboard, need to delete temp data saved in database
                 */
                delete_transient($order_id);
            }
        
        }
    
    
        /**
         * Need to obtain session data, and then save this data in database
         * @param $order_id, this is used to identify the order which user buy
         */
        function save_referboard_params($order_id)
        {
            $order = new WC_Order( $order_id );
            $session_data = isset($_SESSION['referboard'])?$_SESSION['referboard']:null;
            
            if(!empty($session_data))
            {
               // error_log('save to database: '.print_r($session_data,true));
                /**
                 * If there is a referboard session ,and then need to save session data in database
                 */
                $rf_postback_data = [
                    'id'            => $session_data['rf_product'],
                    'amount'        => $order->get_total(),
                    'currency'      => get_option('refer_button_currency','AUD'),
                    'cid'           => $session_data['rf_user'],
                    'rkey'          =>  get_option('refer_button_retailer_apiKey',''),
                    'email'         => $order->billing_email,
                    'buyer_ip'      => !empty($session_data['rf_buyer_ip'])?$session_data['rf_buyer_ip']:$_SERVER['REMOTE_ADDR'],
                    'buyer_history' => 0,
                    'tid'           => $order_id,
                    'extra'         => 'freetext'
                ];
    
                
                $session_data_json = json_encode($rf_postback_data);
                $expired_time = 60*60*24*30;
                set_transient($order_id,$session_data_json,$expired_time);
            }
        }
        
    
    }