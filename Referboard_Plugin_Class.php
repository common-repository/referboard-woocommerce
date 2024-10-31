<?php
    
    /**
     * User: referboard_smionli
     * Date: 2016/8/17
     * Time: 17:29
     * @author: smion.li<smion@referboard.com>
     * @description: base class for different platform plugin
     */
    class Referboard_Plugin_Class
    {
        const CALLBACK_URL = 'https://www.referboard.com/referSales/purchase';
        const tracker_table = 'referboard_tracker';
        const CHECK_RETAILER_URL = "https://www.referboard.com/webservice/";
        const SERVER_API = "refer123Nipo12j)12mn";
       // const SERVER_DOMAIN = 'www.referboard.com';
        const SERVER_DOMAIN = 'www.referboard.com';
        
        public $api_key;
        public $availableRetailer = false;
        
        /**
         * Creates a new instance for the model
         * @param $api retailer api code
         */
        public function __construct($api)
        {
            $this->setApi_Key($api);
        }
        
        /**
         * Setup Api Key and automatically check api available
         * @param $api
         */
        public function setApi_Key($api){
            $this->api_key = $api;
            //$this->checkApiAvailable();
        }
        
        /**
         * check retailer api key is available
         */
        public function checkApiAvailable(){
            $data = [
                'command' => 'checkRetailerApi',
                'api_key' => $this->api_key,
            ];
            $result = $this->curlSend($data,$this::CHECK_RETAILER_URL);
            
            /**
             * check retailer
             * @TODO: need to apply status checking
             */
            if($result['accept']){
                $result_data = json_decode($result['response'],true);
                if($result_data && isset($result_data['result']) && $result_data['result']==true){
                    $this->availableRetailer = true;
                    return true;
                }
            }
            $this->availableRetailer = false;
            return false;
        }
        
        /**
         * Check Product Detail is correct or not
         * @param array $data
         * @return array|bool true | array of error
         */
        protected function checkProductDetail(array &$data){
            $error_result =[];
            /**
             * optional
             */
            if(!isset($data['product_id'])){
                $data['product_id'] = '';
            }
            
            if(!isset($data['product_desc'])){
                $data['product_desc'] = '';
            }
            
            /**
             * mandatory
             */
            if(!isset($data['product_title'])){
                $error_result['product_title'] = 'missed';
            }
            
            if(!isset($data['product_image'])){
                $error_result['product_image'] = 'missed';
            }
            
            if(!isset($data['product_image_list'])){
                $error_result['product_image_list'] = 'missed';
            }else if(!is_array($data['product_image_list'])){
                $error_result['product_image_list'] = 'not array';
            }
            
            if(!isset($data['product_price'])){
                $error_result['product_price'] = 'missed';
            }
            
            if(!isset($data['product_price_type'])){
                $error_result['product_price_type'] = 'missed';
            }
            
            if(!isset($data['product_url'])){
                $error_result['product_url'] = 'missed';
            }
            
            
            /**
             * api key
             */
            if(!isset($data['product_retailer_id'])){
                if(empty($this->api_key)){
                    $error_result['product_retailer_id'] = 'missed';
                }else{
                    $data['product_retailer_id'] = $this->api_key;
                }
            }
            
            if(!empty($error_result)){
                return $error_result;
            } else return true;
        }
        
        /**
         * @param array $data
         * @param string $output_type
         * @return array|bool|string
         */
        public function generateReferButtonCode(array $data,$output_type='full'){
            
            $checkResult = $this->checkProductDetail($data);
            
            if($checkResult === true){
                
                $img_list = '';
                foreach($data['product_image_list'] as $item){
                    $img_list .= "'".str_replace("'","\\'",$item)."' ,";
                }
                
                $top = "

                <script type=\"text/javascript\"> 
                //setup refer button settings
                var refer_settings = {
                //setup product id in referboard, recommend and it need to be unique [optional]
                product_id: '".str_replace("'","\\'",$data['product_id'])."',
                //product title [mandatory]
                product_title: '".str_replace("'","\\'",$data['product_title'])."',
                //product description [optional]
                product_desc: '".str_replace("'","\\'",$data['product_desc'])."',
                //product image url (full address) Use the largest product image [mandatory]
                product_image: '".str_replace("'","\\'",$data['product_image'])."',
                //array of image url links All other product images e.g. ['http://exmaple.com/123.jpg','http://example.com/123-2.jpg'], a [mandatory]
                product_image_list: [".$img_list."],
                //product price [mandatory]
                product_price: '".$data['product_price']."',
                //product price type, default is Australian dollar [mandatory]
                product_price_type: '".$data['product_price_type']."',
                //product url, full address include http or https [mandatory]
                product_url: '".str_replace("'","\\'",$data['product_url'])."',
                //setup retailer api key [mandatory]
                product_retailer_id: '".str_replace("'","\\'",$data['product_retailer_id'])."'
                };
                var referPluginS = document.createElement('script');
                referPluginS.async = true;
                referPluginS.type = 'text/javascript';
                var useSSL = 'https:' == document.location.protocol;
                referPluginS.src = (useSSL ? 'https:' : 'http:') + '//".$this::SERVER_DOMAIN."/js/referButton/refer_popup.js';
                var node = document.getElementsByTagName('script')[0];
                node.parentNode.insertBefore(referPluginS, node);
                var Refer_Opentop = Refer_Opentop || {};
                Refer_Opentop.cmd = Refer_Opentop.cmd || [];
                </script>
             ";
                
                $button = "

                <div id=\"referboard_button_div\"> 
                <script type=\"text/javascript\">
                Refer_Opentop.cmd.push(function(){
                Refer_Opentop.load_popup(refer_settings,'referboard_button_div');
                });
                </script>
                </div> 

            ";
                
                switch($output_type){
                    case 'top':
                        $result = $top;
                        break;
                    case 'button':
                        $result = $button;
                        break;
                    default:
                        $result = $top.$button;
                        break;
                }
                
                return $result;
            }else return $checkResult;
        }
        
        /**
         * Home Page Tracking Code
         * @param array $data
         * @return bool|string false | output string
         */
        public function generateHomePageCode(array $data = [],$type='all'){
            if(!empty($this->api_key)){
                
                if($type=='all')
                {
                    $output = "<script type=\"text/javascript\" src=\"//".$this::SERVER_DOMAIN."/js/referButton/cookie_track.js?api=".$this->api_key."\"></script> ";
                }else{
                    $output = '//'.$this::SERVER_DOMAIN."/js/referButton/cookie_track.js?api=".$this->api_key;
                }
                
            }else $output = false;
            return $output;
        }
        
        
        
        /**
         * Check Transaction Data
         * @param array $data
         * @return array|bool
         */
        protected function checkTransactionData(array &$data){
            $error_result =[];
            /**
             * optional
             */
            if(!isset($data['cid'])){
                $data['cid'] = '';
            }
            
            if(!isset($data['extra'])){
                $data['extra'] = '';
            }
            
            /**
             * mandatory
             */
            if(!isset($data['id'])){
                $error_result['id'] = 'ref_product/id missed';
            }
            
            if(!isset($data['email'])){
                $error_result['email'] = 'customer email missed';
            }
            
            if(!isset($data['amount'])){
                $error_result['amount'] = 'missed';
            }
            
            if(!isset($data['buyer_ip'])){
                $error_result['buyer_ip'] = 'missed';
            }
            
            if(!isset($data['buyer_history'])){
                $error_result['buyer_history'] = 'missed';
            }
            
            if(!isset($data['currency'])){
                $error_result['currency'] = 'missed';
            }
            
            if(!isset($data['tid'])){
                $error_result['tid'] = 'transaction id missed';
            }
            
            
            /**
             * api key
             */
            if(!isset($data['rkey'])){
                if(empty($this->api_key)){
                    $error_result['rkey'] = 'api key missed';
                }else{
                    $data['rkey'] = $this->api_key;
                }
            }
            
            if(!empty($error_result)){
                return $error_result;
            } else return true;
        }
        
        /**
         * Confirmation Code for Confirmation Page with front end
         * @param array $data
         * @return array|bool|string
         */
        public function generateConfirmationCode(array $data){
            
            $checkResult = $this->checkTransactionData($data);
            
            if($checkResult === true){
                $output = "

                <script type=\"text/javascript\"> 
                var referPluginS = document.createElement('script');
                referPluginS.async = true;
                referPluginS.type = 'text/javascript';
                var useSSL = 'https:' == document.location.protocol;
                referPluginS.src = (useSSL ? 'https:' : 'http:') + '//".$this::SERVER_DOMAIN."/js/referButton/refer_popup.js';
                var node = document.getElementsByTagName('script')[0];
                node.parentNode.insertBefore(referPluginS, node);
                var Refer_Opentop = Refer_Opentop || {};
                Refer_Opentop.cmd = Refer_Opentop.cmd || [];

                var tracking_params = {
                    // retailer API key
                    rkey: '".str_replace("'","\\'",$data['rkey'])."',
                    // refer product id
                    id: '".str_replace("'","\\'",$data['id'])."',
                    // referboard user's id
                    cid: '".str_replace("'","\\'",$data['cid'])."',
                    // customer's email
                    email: '".str_replace("'","\\'",$data['email'])."',
                    // order total
                    amount: '".str_replace("'","\\'",$data['amount'])."',
                    // verify the order
                    verify: 1,
                    //The ip address of buyer
                    buyer_ip: '".str_replace("'","\\'",$data['buyer_id'])."',
                    //Did user buy product in last one month
                    buyer_history:'".str_replace("'","\\'",$data['buyer_history'])."',
                    //currency, Three Characters for country by using ISO-4217 [optional, default will be AUD]
                    currency: '".str_replace("'","\\'",$data['currency'])."',
                    //retailer transaction id
                    tid:'".str_replace("'","\\'",$data['tid'])."',
                    //retailer reference, maximum 100 characters
                    extra:'".str_replace("'","\\'",$data['extra'])."',
                    //Request from js
                    frontend:1,
                };

                Refer_Opentop.cmd.push(function(){
                    //need to update cookies of buyer's buy history
                    Refer_Opentop.updateBuyHistoryCookies(tracking_params.buyer_history);
                    //Front end call back function
                    Refer_Opentop.successCallback(tracking_params);
                }
                </script>

            ";
                return $output;
            }else return $checkResult;
        }
        
        /**
         * Send Transaction Data to referboard
         * @param array $data
         * @return array|bool|string true | error string | error array
         */
        public function sendTransactionData(array $data){
            $checkResult = $this->checkTransactionData($data);
            
            if($checkResult === true){
                $result = $this->curlSend($data,$this::CALLBACK_URL,'post');
                
                /**
                 * check retailer
                 * @TODO: need to apply status checking
                 */
                if($result['accept']){
                    $result_data = json_decode($result['accept'],true);
                    if($result_data && isset($result_data['result']) && $result_data['result']===true){
                        return true;
                    }else return "invalid response from referboard:".$result['accept'];
                }else return "send error:".$result['error'];
                
            }else return $checkResult;
        }
        
        /**
         * Capture Referboard Params
         * @param $data ['rf_product'=>'','rf_buyer_ip'=> '','rf_rproduct'=>'','rf_user'=>'','buyer_history'=>'']
         * @param string $type data|cookie
         * @return array
         */
        public function captureReferboardParams($data,$type='data')
        {
            $result = ['rf_product'=>'','rf_buyer_ip'=> '','rf_rproduct'=>'','rf_user'=>'','buyer_history'=>''];
            switch($type){
                case 'data':
                    $result['rf_product'] = empty($data['rf_product']) ? (empty($_REQUEST['rf_product']) ? '' : $_REQUEST['rf_product']) : $data['rf_product'];
                    $result['rf_buyer_ip'] = empty($data['rf_buyer_ip']) ? (empty($_REQUEST['rf_buyer_ip']) ? '' : $_REQUEST['rf_buyer_ip']) : $data['rf_buyer_ip'];
                    $result['rf_rproduct'] = empty($data['rf_rproduct']) ? (empty($_REQUEST['rf_rproduct']) ? '' : $_REQUEST['rf_rproduct']) : $data['rf_rproduct'];
                    $result['rf_user'] = empty($data['rf_user']) ? (empty($_REQUEST['rf_user']) ? '' : $_REQUEST['rf_user']) : $data['rf_user'];
                    $result['buyer_history'] = empty($data['buyer_history']) ? (empty($_REQUEST['buyer_history']) ? '' : $_REQUEST['buyer_history']) : $data['buyer_history'];
                    break;
                case 'cookie':
                    //try cookie first by passing data
                    $result['rf_product'] = empty($data['rf_product']) ? (empty($_REQUEST['rf_product']) ? '' : $_REQUEST['rf_product']) : $data['rf_product'];
                    $result['rf_buyer_ip'] = empty($data['rf_buyer_ip']) ? (empty($_REQUEST['rf_buyer_ip']) ? '' : $_REQUEST['rf_buyer_ip']) : $data['rf_buyer_ip'];
                    $result['rf_rproduct'] = empty($data['rf_rproduct']) ? (empty($_REQUEST['rf_rproduct']) ? '' : $_REQUEST['rf_rproduct']) : $data['rf_rproduct'];
                    $result['rf_user'] = empty($data['rf_user']) ? (empty($_REQUEST['rf_user']) ? '' : $_REQUEST['rf_user']) : $data['rf_user'];
                    $result['buyer_history'] = empty($data['buyer_history']) ? (empty($_REQUEST['buyer_history']) ? '' : $_REQUEST['buyer_history']) : $data['buyer_history'];
                    
                    //try cookie by browser if some record missed
                    if(!empty($_COOKIE['rb_order_info'])){
                        $cookie_data =  $_COOKIE["rb_order_info"];
                        $cookie_data = json_decode($cookie_data,true);
                        if(is_array($cookie_data)){
                            $result['rf_product'] = empty($result['rf_product']) ? (empty($cookie_data['rf_product']) ? '' : $cookie_data['rf_product']) : $result['rf_product'];
                            $result['rf_buyer_ip'] = empty($result['rf_buyer_ip']) ? (empty($cookie_data['rf_buyer_ip']) ? '' : $cookie_data['rf_buyer_ip']) : $result['rf_buyer_ip'];
                            $result['rf_rproduct'] = empty($result['rf_rproduct']) ? (empty($cookie_data['rf_rproduct']) ? '' : $cookie_data['rf_rproduct']) : $result['rf_rproduct'];
                        }
                    }
                    break;
                default:
                    break;
            }
            return $result;
        }
        
        
        
        /**
         * Curl Tools
         * @param array $data
         * @param $url
         * @param string $send_method
         * @param string $accept_code
         * @param string $reject_code
         * @return array (accept=>true/false,response=>string,error=>string,send_value=>string)
         */
        protected function curlSend(array $data, $url, $send_method = 'get', $accept_code = '', $reject_code = '')
        {
            /**
             * format of result array
             * 'accept' => true/false
             * 'response' => text
             * 'error'  => text
             * 'send_value' => text
             */
            $result = array('accept' => '', 'response' => '', 'error' => '', 'send_value' => '');
            if (!empty($url) && !empty($data)) {
                $ch = curl_init();
                try {
                    $data_string = http_build_query($data);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    if ($send_method == 'post') {
                        curl_setopt($ch, CURLOPT_POST, count($data_string));
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                        $result['send_value'] = $url . "?" . $data_string;
                    } else {
                        if (strpos($url, '?') !== false) {
                            $url = $url . "&" . $data_string;
                        } else {
                            $url = $url . "?" . $data_string;
                        }
                        $result['send_value'] = $url;
                    }
                    
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_HEADER, 0);  // DO NOT RETURN HTTP HEADERS
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    //curl_setopt($ch, CURLOPT_SSLVERSION, 3);
                    
                    $response = curl_exec($ch);
                    $result['response'] = $response;
                    
                    //compare result with accept code or reject code
                    $result['accept'] = true;
                    if ($response !== false && $response !== null) {
                        if ($accept_code != null && $accept_code != '') {
                            if (stripos($response, $accept_code) !== false) {
                                $result['accept'] = true;
                            } else {
                                $result['accept'] = false;
                            }
                        }
                        
                        if ($reject_code != null && $reject_code != '') {
                            if (stripos($response, $reject_code) !== false) {
                                $result['accept'] = false;
                            }
                        }
                    } else {
                        $result['error'] = curl_error($ch);
                        $result['send_method'] = $send_method;
                        $result['accept'] = false;
                    }
                    
                    curl_close($ch);
                } catch (Exception $e) {
                    curl_close($ch);
                    $result['accept'] = false;
                    $result['error'] = $e->getMessage();
                    $result['send_method'] = $send_method;
                }
            }
            return $result;
        }
        
    }