<?php
/**
 * Plugin Name: DHL Global Mail Shipping
 * Plugin URI: #
 * Description: Realtime Shipping Rates, Order Creation automation included.
 * Version: 2.0.1
 * Author: Shipi
 * Author URI: https://myshipi.com/
 * Developer: aarsiv
 * Developer URI: https://myshipi.com/
 * Text Domain: dhl_gm
 * Domain Path: /i18n/languages/
 *
 * WC requires at least: 2.6
 * WC tested up to: 5.9
 *
 *
 * @package WooCommerce
 */

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define WC_PLUGIN_FILE.
if ( ! defined( 'SHIPI_DHL_GM_PLUGIN_FILE' ) ) {
	define( 'SHIPI_DHL_GM_PLUGIN_FILE', __FILE__ );
}

function woo_dhl_gm_plugin_activation( $plugin ) {
    if( $plugin == plugin_basename( __FILE__ ) ) {
        $setting_value = version_compare(WC()->version, '2.1', '>=') ? "wc-settings" : "woocommerce_settings";
    	// Don't forget to exit() because wp_redirect doesn't exit automatically
    	exit( wp_redirect( admin_url( 'admin.php?page=' . $setting_value  . '&tab=shipping&section=dhl_gm' ) ) );
    }
}
add_action( 'activated_plugin', 'woo_dhl_gm_plugin_activation' );

// Include the main WooCommerce class.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	
	if( !class_exists('dhl_gm_parent') ){
		Class dhl_gm_parent
		{
			private $errror = '';
			public function __construct() {
				add_action( 'woocommerce_shipping_init', array($this,'dhl_gm_init') );
				add_action( 'init', array($this,'hit_order_status_update') );
				add_filter( 'woocommerce_shipping_methods', array($this,'dhl_gm_method') );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'dhl_gm_plugin_action_links' ) );
				add_action( 'add_meta_boxes', array($this, 'create_dhl_gm_shipping_meta_box' ));
				add_action( 'save_post', array($this, 'create_dhl_gm_shipping'), 10, 1 );
				add_action( 'save_post', array($this, 'create_dhl_gm_return_shipping'), 10, 1 );
				// add_filter( 'bulk_actions-edit-shop_order', array($this, 'hit_bulk_order_menu'), 10, 1 );
				add_filter( 'handle_bulk_actions-edit-shop_order', array($this, 'hit_bulk_create_order'), 10, 3 );
				add_action( 'admin_notices', array($this, 'shipo_bulk_label_action_admin_notice' ) );
				add_filter( 'woocommerce_product_data_tabs', array($this,'hit_product_data_tab') );
				add_action( 'woocommerce_process_product_meta', array($this,'hit_save_product_options' ));
				add_filter( 'woocommerce_product_data_panels', array($this,'hit_product_option_view') );
				add_action( 'admin_menu', array($this, 'dhl_gm_menu_page' ));
				// add_filter( 'manage_edit-shop_order_columns', array($this, 'a2z_wc_new_order_column') );
				// add_action( 'woocommerce_checkout_order_processed', array( $this, 'hit_wc_checkout_order_processed' ) );
				// add_action( 'woocommerce_thankyou', array( $this, 'hit_wc_checkout_order_processed' ) );
				add_action( 'woocommerce_order_status_processing', array( $this, 'hit_wc_checkout_order_processed' ) );
				add_action('woocommerce_order_details_after_order_table', array( $this, 'dhl_gm_track' ) );
				// add_action( 'manage_shop_order_posts_custom_column', array( $this, 'show_buttons_to_downlaod_shipping_label') );
				add_action('admin_print_styles', array($this, 'hits_admin_scripts'));
				
				$general_settings = get_option('dhl_gm_main_settings');
				$general_settings = empty($general_settings) ? array() : $general_settings;

				if(isset($general_settings['dhl_gm_v_enable']) && $general_settings['dhl_gm_v_enable'] == 'yes' ){
					add_action( 'woocommerce_product_options_shipping', array($this,'hit_choose_vendor_address' ));
					add_action( 'woocommerce_process_product_meta', array($this,'hit_save_product_meta' ));

					// Edit User Hooks
					add_action( 'edit_user_profile', array($this,'hit_define_dhl_gm_credentails') );
					add_action( 'edit_user_profile_update', array($this, 'save_user_fields' ));

				}
			
			}
			public function hits_admin_scripts() {
		        global $wp_scripts;
		        wp_enqueue_script('wc-enhanced-select');
		        wp_enqueue_script('chosen');
		        wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css');

		    }
			
			function a2z_wc_new_order_column( $columns ) {
				$columns['dhl_gm'] = 'DHL Global Mail';
				return $columns;
			}
			
			function show_buttons_to_downlaod_shipping_label( $column ) {
				global $post;
				
				if ( 'dhl_gm' === $column ) {
			
					$order    = wc_get_order( $post->ID );
					$json_data = get_option('dhl_gm_values_'.$post->ID);
					
					if(!empty($json_data)){
						$array_data = json_decode( $json_data, true );
						if(isset($array_data[0])){
							foreach ($array_data as $key => $value) {
								_e( '<a href="'.$value['label'].'" target="_blank" class="button button-secondary"><span class="dashicons dashicons-printer" style="vertical-align:sub;"></span></a> ');
								_e( ' <a href="'.$value['invoice'].'" target="_blank" class="button button-secondary"><span class="dashicons dashicons-pdf" style="vertical-align:sub;"></span></a><br/>');
							}
						}else{
							_e( '<a href="'.$array_data['label'].'" target="_blank" class="button button-secondary"><span class="dashicons dashicons-printer" style="vertical-align:sub;"></span></a> ');
							_e( ' <a href="'.$array_data['invoice'].'" target="_blank" class="button button-secondary"><span class="dashicons dashicons-pdf" style="vertical-align:sub;"></span></a>');
						}
					}else{
						_e( '-');
					}
				}
			}
			
			function dhl_gm_menu_page() {

				$general_settings = get_option('dhl_gm_main_settings');
				
				
				add_submenu_page( 'options-general.php', 'DHL Global Mail Config', 'DHL Global Mail Config', 'manage_options', 'dhl-gm-configuration', array($this, 'my_admin_page_contents') ); 

			}
			function my_label_page_contents(){
				
			}
			function my_admin_page_contents(){
				include_once('controllors/views/dhl_gm_settings_view.php');
			}

			public function hit_product_data_tab( $tabs) {

				$tabs['hits_ghl_gm_product_options'] = array(
					'label'		=> __( 'Shipi - DHL Global Mail Options', 'dhl_gm' ),
					'target'	=> 'dhl_gm_product_options',
					// 'class'		=> array( 'show_if_simple', 'show_if_variable' ),
				);
			
				return $tabs;
			
			}

			public function hit_save_product_options( $post_id ){
				if( isset($_POST['hits_dhl_gm_cc']) ){
					$cc = sanitize_text_field($_POST['hits_dhl_gm_cc']);
					update_post_meta( $post_id, 'hits_dhl_gm_cc', (string) esc_html( $cc ) );
				}
				if( isset($_POST['hits_dhl_gm_export_reason']) ){
					$cc = sanitize_text_field($_POST['hits_dhl_gm_export_reason']);
					update_post_meta( $post_id, 'hits_dhl_gm_export_reason', (string) esc_html( $cc ) );
				}
				if( isset($_POST['hits_dhl_gm_desc']) ){
					$cc = sanitize_text_field($_POST['hits_dhl_gm_desc']);
					update_post_meta( $post_id, 'hits_dhl_gm_desc', (string) esc_html( $cc ) );
				}
				
			}

			public function hit_product_option_view(){
				global $woocommerce, $post;
				$hits_dhl_gm_saved_cc = get_post_meta( $post->ID, 'hits_dhl_gm_cc', true);
				$hits_dhl_gm_saved_export_reason = get_post_meta( $post->ID, 'hits_dhl_gm_export_reason', true);
				$hits_dhl_gm_saved_desc = get_post_meta( $post->ID, 'hits_dhl_gm_desc', true);
			
				?>
				<div id='dhl_gm_product_options' class='panel woocommerce_options_panel'>
					<div class='options_group'>
						<p class="form-field">
							<label for="hits_dhl_gm_cc"><?php _e( 'Enter Commodity code', 'dhl_gm' ); ?></label>
							<span class='woocommerce-help-tip' data-tip="<?php _e('Enter commodity code for product (20 charcters max).','dhl_gm') ?>"></span>
							<input type='text' id='hits_dhl_gm_cc' name='hits_dhl_gm_cc' maxlength="20" <?php _e(!empty($hits_dhl_gm_saved_cc) ? 'value="'.$hits_dhl_gm_saved_cc.'"' : '');?> style="width: 30%;">
						</p>
					</div>
					<div class='options_group'>
						<p class="form-field">
							<label for="hits_dhl_gm_desc"><?php _e( 'Description of contents - (Invoice Product Name)', 'dhl_gm' ); ?></label>
							<input type='text' id='hits_dhl_gm_desc' name='hits_dhl_gm_desc' maxlength="100" <?php _e(!empty($hits_dhl_gm_saved_desc) ? 'value="'.$hits_dhl_gm_saved_desc.'"' : '');?> style="width: 90%;">
						</p>
					</div>
					
				</div>
				<?php
			}

			public function hit_bulk_order_menu( $actions ) {
				$actions['create_label_shipo'] = __( 'Create Labels - Shipi', 'dhl_gm' );
				return $actions;
			}

			public function hit_bulk_create_order($redirect_to, $action, $order_ids){
				
			}

			function shipo_bulk_label_action_admin_notice() {
				if(isset($_GET['success_lbl']) && isset($_GET['failed_lbl'])){
					printf( '<div id="message" class="updated fade"><p>
						Generated labels: '. esc_html($_GET['success_lbl']) .' Failed Label: '. esc_html($_GET['failed_lbl']).' </p></div>');
				}

			}

			public function dhl_gm_track($order){
				
			}
			public function save_user_fields($user_id){
				if(isset($_POST['dhl_gm_country'])){
					$general_settings['dhl_gm_site_id'] = sanitize_text_field(isset($_POST['dhl_gm_site_id']) ? $_POST['dhl_gm_site_id'] : '');
					$general_settings['dhl_gm_site_pwd'] = sanitize_text_field(isset($_POST['dhl_gm_site_pwd']) ? $_POST['dhl_gm_site_pwd'] : '');
					$general_settings['dhl_gm_acc_no'] = sanitize_text_field(isset($_POST['dhl_gm_acc_no']) ? $_POST['dhl_gm_acc_no'] : '');
					$general_settings['dhl_gm_shipper_name'] = sanitize_text_field(isset($_POST['dhl_gm_shipper_name']) ? $_POST['dhl_gm_shipper_name'] : '');
					$general_settings['dhl_gm_company'] = sanitize_text_field(isset($_POST['dhl_gm_company']) ? $_POST['dhl_gm_company'] : '');
					$general_settings['dhl_gm_mob_num'] = sanitize_text_field(isset($_POST['dhl_gm_mob_num']) ? $_POST['dhl_gm_mob_num'] : '');
					$general_settings['dhl_gm_email'] = sanitize_text_field(isset($_POST['dhl_gm_email']) ? $_POST['dhl_gm_email'] : '');
					$general_settings['dhl_gm_address1'] = sanitize_text_field(isset($_POST['dhl_gm_address1']) ? $_POST['dhl_gm_address1'] : '');
					$general_settings['dhl_gm_address2'] = sanitize_text_field(isset($_POST['dhl_gm_address2']) ? $_POST['dhl_gm_address2'] : '');
					$general_settings['dhl_gm_city'] = sanitize_text_field(isset($_POST['dhl_gm_city']) ? $_POST['dhl_gm_city'] : '');
					$general_settings['dhl_gm_state'] = sanitize_text_field(isset($_POST['dhl_gm_state']) ? $_POST['dhl_gm_state'] : '');
					$general_settings['dhl_gm_zip'] = sanitize_text_field(isset($_POST['dhl_gm_zip']) ? $_POST['dhl_gm_zip'] : '');
					$general_settings['dhl_gm_country'] = sanitize_text_field(isset($_POST['dhl_gm_country']) ? $_POST['dhl_gm_country'] : '');
					$general_settings['dhl_gm_gstin'] = sanitize_text_field(isset($_POST['dhl_gm_gstin']) ? $_POST['dhl_gm_gstin'] : '');
					$general_settings['dhl_gm_con_rate'] = sanitize_text_field(isset($_POST['dhl_gm_con_rate']) ? $_POST['dhl_gm_con_rate'] : '');
					$general_settings['dhl_gm_def_dom'] = sanitize_text_field(isset($_POST['dhl_gm_def_dom']) ? $_POST['dhl_gm_def_dom'] : '');

					$general_settings['dhl_gm_def_inter'] = sanitize_text_field(isset($_POST['dhl_gm_def_inter']) ? $_POST['dhl_gm_def_inter'] : '');

					update_post_meta($user_id,'dhl_gm_vendor_settings',$general_settings);
				}

			}

			public function hit_define_dhl_gm_credentails( $user ){
				global $dhl_gm_core;
				$main_settings = get_option('dhl_gm_main_settings');
				$main_settings = empty($main_settings) ? array() : $main_settings;
				$allow = false;
				
				if(!isset($main_settings['dhl_gm_v_roles'])){
					return;
				}else{
					foreach ($user->roles as $value) {
						if(in_array($value, $main_settings['dhl_gm_v_roles'])){
							$allow = true;
						}
					}
				}
				
				if(!$allow){
					return;
				}

				$general_settings = get_post_meta($user->ID,'dhl_gm_vendor_settings',true);
				$general_settings = empty($general_settings) ? array() : $general_settings;
				$countires =  array(
									'AF' => 'Afghanistan',
									'AL' => 'Albania',
									'DZ' => 'Algeria',
									'AS' => 'American Samoa',
									'AD' => 'Andorra',
									'AO' => 'Angola',
									'AI' => 'Anguilla',
									'AG' => 'Antigua and Barbuda',
									'AR' => 'Argentina',
									'AM' => 'Armenia',
									'AW' => 'Aruba',
									'AU' => 'Australia',
									'AT' => 'Austria',
									'AZ' => 'Azerbaijan',
									'BS' => 'Bahamas',
									'BH' => 'Bahrain',
									'BD' => 'Bangladesh',
									'BB' => 'Barbados',
									'BY' => 'Belarus',
									'BE' => 'Belgium',
									'BZ' => 'Belize',
									'BJ' => 'Benin',
									'BM' => 'Bermuda',
									'BT' => 'Bhutan',
									'BO' => 'Bolivia',
									'BA' => 'Bosnia and Herzegovina',
									'BW' => 'Botswana',
									'BR' => 'Brazil',
									'VG' => 'British Virgin Islands',
									'BN' => 'Brunei',
									'BG' => 'Bulgaria',
									'BF' => 'Burkina Faso',
									'BI' => 'Burundi',
									'KH' => 'Cambodia',
									'CM' => 'Cameroon',
									'CA' => 'Canada',
									'CV' => 'Cape Verde',
									'KY' => 'Cayman Islands',
									'CF' => 'Central African Republic',
									'TD' => 'Chad',
									'CL' => 'Chile',
									'CN' => 'China',
									'CO' => 'Colombia',
									'KM' => 'Comoros',
									'CK' => 'Cook Islands',
									'CR' => 'Costa Rica',
									'HR' => 'Croatia',
									'CU' => 'Cuba',
									'CY' => 'Cyprus',
									'CZ' => 'Czech Republic',
									'DK' => 'Denmark',
									'DJ' => 'Djibouti',
									'DM' => 'Dominica',
									'DO' => 'Dominican Republic',
									'TL' => 'East Timor',
									'EC' => 'Ecuador',
									'EG' => 'Egypt',
									'SV' => 'El Salvador',
									'GQ' => 'Equatorial Guinea',
									'ER' => 'Eritrea',
									'EE' => 'Estonia',
									'ET' => 'Ethiopia',
									'FK' => 'Falkland Islands',
									'FO' => 'Faroe Islands',
									'FJ' => 'Fiji',
									'FI' => 'Finland',
									'FR' => 'France',
									'GF' => 'French Guiana',
									'PF' => 'French Polynesia',
									'GA' => 'Gabon',
									'GM' => 'Gambia',
									'GE' => 'Georgia',
									'DE' => 'Germany',
									'GH' => 'Ghana',
									'GI' => 'Gibraltar',
									'GR' => 'Greece',
									'GL' => 'Greenland',
									'GD' => 'Grenada',
									'GP' => 'Guadeloupe',
									'GU' => 'Guam',
									'GT' => 'Guatemala',
									'GG' => 'Guernsey',
									'GN' => 'Guinea',
									'GW' => 'Guinea-Bissau',
									'GY' => 'Guyana',
									'HT' => 'Haiti',
									'HN' => 'Honduras',
									'HK' => 'Hong Kong',
									'HU' => 'Hungary',
									'IS' => 'Iceland',
									'IN' => 'India',
									'ID' => 'Indonesia',
									'IR' => 'Iran',
									'IQ' => 'Iraq',
									'IE' => 'Ireland',
									'IL' => 'Israel',
									'IT' => 'Italy',
									'CI' => 'Ivory Coast',
									'JM' => 'Jamaica',
									'JP' => 'Japan',
									'JE' => 'Jersey',
									'JO' => 'Jordan',
									'KZ' => 'Kazakhstan',
									'KE' => 'Kenya',
									'KI' => 'Kiribati',
									'KW' => 'Kuwait',
									'KG' => 'Kyrgyzstan',
									'LA' => 'Laos',
									'LV' => 'Latvia',
									'LB' => 'Lebanon',
									'LS' => 'Lesotho',
									'LR' => 'Liberia',
									'LY' => 'Libya',
									'LI' => 'Liechtenstein',
									'LT' => 'Lithuania',
									'LU' => 'Luxembourg',
									'MO' => 'Macao',
									'MK' => 'Macedonia',
									'MG' => 'Madagascar',
									'MW' => 'Malawi',
									'MY' => 'Malaysia',
									'MV' => 'Maldives',
									'ML' => 'Mali',
									'MT' => 'Malta',
									'MH' => 'Marshall Islands',
									'MQ' => 'Martinique',
									'MR' => 'Mauritania',
									'MU' => 'Mauritius',
									'YT' => 'Mayotte',
									'MX' => 'Mexico',
									'FM' => 'Micronesia',
									'MD' => 'Moldova',
									'MC' => 'Monaco',
									'MN' => 'Mongolia',
									'ME' => 'Montenegro',
									'MS' => 'Montserrat',
									'MA' => 'Morocco',
									'MZ' => 'Mozambique',
									'MM' => 'Myanmar',
									'NA' => 'Namibia',
									'NR' => 'Nauru',
									'NP' => 'Nepal',
									'NL' => 'Netherlands',
									'NC' => 'New Caledonia',
									'NZ' => 'New Zealand',
									'NI' => 'Nicaragua',
									'NE' => 'Niger',
									'NG' => 'Nigeria',
									'NU' => 'Niue',
									'KP' => 'North Korea',
									'MP' => 'Northern Mariana Islands',
									'NO' => 'Norway',
									'OM' => 'Oman',
									'PK' => 'Pakistan',
									'PW' => 'Palau',
									'PA' => 'Panama',
									'PG' => 'Papua New Guinea',
									'PY' => 'Paraguay',
									'PE' => 'Peru',
									'PH' => 'Philippines',
									'PL' => 'Poland',
									'PT' => 'Portugal',
									'PR' => 'Puerto Rico',
									'QA' => 'Qatar',
									'CG' => 'Republic of the Congo',
									'RE' => 'Reunion',
									'RO' => 'Romania',
									'RU' => 'Russia',
									'RW' => 'Rwanda',
									'SH' => 'Saint Helena',
									'KN' => 'Saint Kitts and Nevis',
									'LC' => 'Saint Lucia',
									'VC' => 'Saint Vincent and the Grenadines',
									'WS' => 'Samoa',
									'SM' => 'San Marino',
									'ST' => 'Sao Tome and Principe',
									'SA' => 'Saudi Arabia',
									'SN' => 'Senegal',
									'RS' => 'Serbia',
									'SC' => 'Seychelles',
									'SL' => 'Sierra Leone',
									'SG' => 'Singapore',
									'SK' => 'Slovakia',
									'SI' => 'Slovenia',
									'SB' => 'Solomon Islands',
									'SO' => 'Somalia',
									'ZA' => 'South Africa',
									'KR' => 'South Korea',
									'SS' => 'South Sudan',
									'ES' => 'Spain',
									'LK' => 'Sri Lanka',
									'SD' => 'Sudan',
									'SR' => 'Suriname',
									'SZ' => 'Swaziland',
									'SE' => 'Sweden',
									'CH' => 'Switzerland',
									'SY' => 'Syria',
									'TW' => 'Taiwan',
									'TJ' => 'Tajikistan',
									'TZ' => 'Tanzania',
									'TH' => 'Thailand',
									'TG' => 'Togo',
									'TO' => 'Tonga',
									'TT' => 'Trinidad and Tobago',
									'TN' => 'Tunisia',
									'TR' => 'Turkey',
									'TC' => 'Turks and Caicos Islands',
									'TV' => 'Tuvalu',
									'VI' => 'U.S. Virgin Islands',
									'UG' => 'Uganda',
									'UA' => 'Ukraine',
									'AE' => 'United Arab Emirates',
									'GB' => 'United Kingdom',
									'US' => 'United States',
									'UY' => 'Uruguay',
									'UZ' => 'Uzbekistan',
									'VU' => 'Vanuatu',
									'VE' => 'Venezuela',
									'VN' => 'Vietnam',
									'YE' => 'Yemen',
									'ZM' => 'Zambia',
									'ZW' => 'Zimbabwe',
								);
				 $_dhl_gm_carriers = array(
					//"Public carrier name" => "technical name",
					"LTL" => "Less Than Truckload",
					"TL" => "Truckload",
					"Air" => "Air",
					"Ocean" => "Ocean",
					"Bulk" => "Bulk",
					"Consol" => "Consolidated",
					"Flatbed" => "Flatbed"
				);			

				_e( '<hr><h3 class="heading">DHL Global Mail - <a href="https://myshipi.com/" target="_blank">Shipi</a></h3>');
				    ?>
				    
				    <table class="form-table">
						<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('DHL Global Mail Integration Team will give this details to you.','dhl_gm') ?>"></span>	<?php _e('Client ID','dhl_gm') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','dhl_gm') ?> </p>
						</td>
						<td>
							<input type="text" name="dhl_gm_site_id" value="<?php _e( (isset($general_settings['dhl_gm_site_id'])) ? $general_settings['dhl_gm_site_id'] : ''); ?>">
						</td>

					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('DHL Global Mail Integration Team will give this details to you.','dhl_gm') ?>"></span>	<?php _e('Client Secret','dhl_gm') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','dhl_gm') ?> </p>
						</td>
						<td>
							<input type="text" name="dhl_gm_site_pwd" value="<?php _e( (isset($general_settings['dhl_gm_site_pwd'])) ? $general_settings['dhl_gm_site_pwd'] : ''); ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('DHL Global Mail Integration Team will give this details to you.','dhl_gm') ?>"></span>	<?php _e('Customer Code','dhl_gm') ?></h4>
							<p> <?php _e('Leave this field as empty to use default account.','dhl_gm') ?> </p>
						</td>
						<td>
							
							<input type="text" name="dhl_gm_acc_no" value="<?php _e( (isset($general_settings['dhl_gm_acc_no'])) ? $general_settings['dhl_gm_acc_no'] : ''); ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipping Person Name','dhl_gm') ?>"></span>	<?php _e('Shipper Name','dhl_gm') ?></h4>
						</td>
						<td>
							<input type="text" name="dhl_gm_shipper_name" value="<?php _e( (isset($general_settings['dhl_gm_shipper_name'])) ? $general_settings['dhl_gm_shipper_name'] : ''); ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipper Company Name.','dhl_gm') ?>"></span>	<?php _e('Company Name','dhl_gm') ?></h4>
						</td>
						<td>
							<input type="text" name="dhl_gm_company" value="<?php _e( (isset($general_settings['dhl_gm_company'])) ? $general_settings['dhl_gm_company'] : ''); ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Shipper Mobile / Contact Number.','dhl_gm') ?>"></span>	<?php _e('Contact Number','dhl_gm') ?></h4>
						</td>
						<td>
							<input type="text" name="dhl_gm_mob_num" value="<?php _e( (isset($general_settings['dhl_gm_mob_num'])) ? $general_settings['dhl_gm_mob_num'] : ''); ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Email Address of the Shipper.','dhl_gm') ?>"></span>	<?php _e('Email Address','dhl_gm') ?></h4>
						</td>
						<td>
							<input type="text" name="dhl_gm_email" value="<?php _e( (isset($general_settings['dhl_gm_email'])) ? $general_settings['dhl_gm_email'] : ''); ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Address Line 1 of the Shipper from Address.','dhl_gm') ?>"></span>	<?php _e('Address Line 1','dhl_gm') ?></h4>
						</td>
						<td>
							<input type="text" name="dhl_gm_address1" value="<?php _e( (isset($general_settings['dhl_gm_address1'])) ? $general_settings['dhl_gm_address1'] : ''); ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Address Line 2 of the Shipper from Address.','dhl_gm') ?>"></span>	<?php _e('Address Line 2','dhl_gm') ?></h4>
						</td>
						<td>
							<input type="text" name="dhl_gm_address2" value="<?php _e( (isset($general_settings['dhl_gm_address2'])) ? $general_settings['dhl_gm_address2'] : ''); ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%;padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('City of the Shipper from address.','dhl_gm') ?>"></span>	<?php _e('City','dhl_gm') ?></h4>
						</td>
						<td>
							<input type="text" name="dhl_gm_city" value="<?php _e( (isset($general_settings['dhl_gm_city'])) ? $general_settings['dhl_gm_city'] : ''); ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('State of the Shipper from address.','dhl_gm') ?>"></span>	<?php _e('State (Two Digit String)','dhl_gm') ?></h4>
						</td>
						<td>
							<input type="text" name="dhl_gm_state" value="<?php _e( (isset($general_settings['dhl_gm_state'])) ? $general_settings['dhl_gm_state'] : ''); ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Postal/Zip Code.','dhl_gm') ?>"></span>	<?php _e('Postal/Zip Code','dhl_gm') ?></h4>
						</td>
						<td>
							<input type="text" name="dhl_gm_zip" value="<?php _e( (isset($general_settings['dhl_gm_zip'])) ? $general_settings['dhl_gm_zip'] : ''); ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Country of the Shipper from Address.','dhl_gm') ?>"></span>	<?php _e('Country','dhl_gm') ?></h4>
						</td>
						<td>
							<select name="dhl_gm_country" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($countires as $key => $value)
								{

									if(isset($general_settings['dhl_gm_country']) && ($general_settings['dhl_gm_country'] == $key))
									{
										_e( "<option value=".$key." selected='true'>".$value." [". $dhl_gm_core[$key]['currency'] ."]</option>");
									}
									else
									{
										_e( "<option value=".$key.">".$value." [". $dhl_gm_core[$key]['currency'] ."]</option>");
									}
								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('GSTIN/VAT No.','dhl_gm') ?>"></span>	<?php _e('GSTIN/VAT No','dhl_gm') ?></h4>
						</td>
						<td>
							<input type="text" name="dhl_gm_gstin" value="<?php _e( (isset($general_settings['dhl_gm_gstin'])) ? $general_settings['dhl_gm_gstin'] : ''); ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Conversion Rate from Site Currency to DHL Global Mail Currency.','dhl_gm') ?>"></span>	<?php _e('Conversion Rate from Site Currency to DHL Global Mail Currency ( Ignore if auto conversion is Enabled )','dhl_gm') ?></h4>
						</td>
						<td>
							<input type="text" name="dhl_gm_con_rate" value="<?php _e( (isset($general_settings['dhl_gm_con_rate'])) ? $general_settings['dhl_gm_con_rate'] : ''); ?>">
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Default Domestic Express Shipping.','dhl_gm') ?>"></span>	<?php _e('Default Domestic Service','dhl_gm') ?></h4>
							<p><?php _e('This will be used while shipping label generation.','dhl_gm') ?></p>
						</td>
						<td>
							<select name="dhl_gm_def_dom" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($_dhl_gm_carriers as $key => $value)
								{
									if(isset($general_settings['dhl_gm_def_dom']) && ($general_settings['dhl_gm_def_dom'] == $key))
									{
										_e( "<option value=".$key." selected='true'>[".$key."] ".$value."</option>");
									}
									else
									{
										_e( "<option value=".$key.">[".$key."] ".$value."</option>");
									}
								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<td style=" width: 50%; padding: 5px; ">
							<h4> <span class="woocommerce-help-tip" data-tip="<?php _e('Default International Shipping.','dhl_gm') ?>"></span>	<?php _e('Default International Service','dhl_gm') ?></h4>
							<p><?php _e('This will be used while shipping label generation.','dhl_gm') ?></p>
						</td>
						<td>
							<select name="dhl_gm_def_inter" class="wc-enhanced-select" style="width:210px;">
								<?php foreach($_dhl_gm_carriers as $key => $value)
								{
									if(isset($general_settings['dhl_gm_def_inter']) && ($general_settings['dhl_gm_def_inter'] == $key))
									{
										_e( "<option value=".$key." selected='true'>[".$key."] ".$value."</option>");
									}
									else
									{
										_e( "<option value=".$key.">[".$key."] ".$value."</option>");
									}
								} ?>
							</select>
						</td>
					</tr>
				    </table>
				    <hr>
				    <?php
			}
			public function hit_save_product_meta( $post_id ){
				if(isset( $_POST['dhl_gm_shipment'])){
					$dhl_gm_shipment = sanitize_text_field($_POST['dhl_gm_shipment']);
					if( !empty( $dhl_gm_shipment ) )
					update_post_meta( $post_id, 'dhl_gm_address', (string) esc_html( $dhl_gm_shipment ) );	
				}
							
			}
			public function hit_choose_vendor_address(){
				global $woocommerce, $post;
				$hit_multi_vendor = get_option('hit_multi_vendor');
				$hit_multi_vendor = empty($hit_multi_vendor) ? array() : $hit_multi_vendor;
				$selected_addr = get_post_meta( $post->ID, 'dhl_gm_address', true);

				$main_settings = get_option('dhl_gm_main_settings');
				$main_settings = empty($main_settings) ? array() : $main_settings;
				if(!isset($main_settings['dhl_gm_v_roles']) || empty($main_settings['dhl_gm_v_roles'])){
					return;
				}
				$v_users = get_users( [ 'role__in' => $main_settings['dhl_gm_v_roles'] ] );
				
				?>
				<div class="options_group">
				<p class="form-field dhl_gm_shipment">
					<label for="dhl_gm_shipment"><?php _e( 'DHL Global Mail Account', 'woocommerce' ); ?></label>
					<select id="dhl_gm_shipment" style="width:240px;" name="dhl_gm_shipment" class="wc-enhanced-select" data-placeholder="<?php _e( 'Search for a product&hellip;', 'woocommerce' ); ?>">
						<option value="default" >Default Account</option>
						<?php
							if ( $v_users ) {
								foreach ( $v_users as $value ) {
									_e( '<option value="' .  $value->data->ID  . '" '.($selected_addr == $value->data->ID ? 'selected="true"' : '').'>' . $value->data->display_name . '</option>');
								}
							}
						?>
					</select>
					</p>
				</div>
				<?php
			}

			public function dhl_gm_init()
			{
				include_once("controllors/dhl_gm_init.php");
			}
			public function hit_order_status_update(){
				global $woocommerce;
				if(isset($_GET['shipi_key'])){
					$shipi_key = sanitize_text_field($_GET['shipi_key']);
					if($shipi_key == 'fetch'){
						esc_html(json_encode(array(get_transient('dhl_gm_nonce_temp'))));
						die();
					}
				}

				if(isset($_GET['hitshipo_integration_key']) && isset($_GET['hitshipo_action'])){
					$integration_key = sanitize_text_field($_GET['hitshipo_integration_key']);
					$hitshipo_action = sanitize_text_field($_GET['hitshipo_action']);
					$general_settings = get_option('dhl_gm_main_settings');
					$general_settings = empty($general_settings) ? array() : $general_settings;
					if(isset($general_settings['dhl_gm_integration_key']) && $integration_key == $general_settings['dhl_gm_integration_key']){
						if($hitshipo_action == 'stop_working'){
							update_option('dhl_gm_working_status', 'stop_working');
						}else if ($hitshipo_action == 'start_working'){
							update_option('dhl_gm_working_status', 'start_working');
						}
					}
					
				}

				if(isset($_GET['h1t_updat3_0rd3r']) && isset($_GET['key']) && isset($_GET['action'])){
					$order_id = sanitize_text_field($_GET['h1t_updat3_0rd3r']);
					$key = sanitize_text_field($_GET['key']);
					$action = sanitize_text_field($_GET['action']);
					$order_ids = explode(",",$order_id);
					$general_settings = get_option('dhl_gm_main_settings',array());
					
					if(isset($general_settings['dhl_gm_integration_key']) && $general_settings['dhl_gm_integration_key'] == $key){
						if($action == 'processing'){
							foreach ($order_ids as $order_id) {
								$order = wc_get_order( $order_id );
								$order->update_status( 'processing' );
							}
						}else if($action == 'completed'){
							foreach ($order_ids as $order_id) {
								  $order = wc_get_order( $order_id );
								  $order->update_status( 'completed' );
								  	
							}
						}
					}
					die();
				}

				if(isset($_GET['h1t_updat3_sh1pp1ng']) && isset($_GET['key']) && isset($_GET['user_id']) && isset($_GET['carrier']) && isset($_GET['track']) && $_GET['carrier'] == "dhl_gm"){
					$order_id = sanitize_text_field($_GET['h1t_updat3_sh1pp1ng']);
					$key = sanitize_text_field($_GET['key']);
					$general_settings = get_option('dhl_gm_main_settings',array());
					$user_id = sanitize_text_field($_GET['user_id']);
					$carrier = sanitize_text_field($_GET['carrier']);
					$track = sanitize_text_field($_GET['track']);
					$return_status = isset($_GET['return']) ? sanitize_text_field($_GET['return']) : '';
					$output['status'] = 'success';
					$output['tracking_num'] = $track;
					$output['label'] = "https://app.myshipi.com/api/shipping_labels/".$user_id."/".$carrier."/order_".$order_id."_track_".$track."_label.pdf";
					$output['invoice'] = "";
					$output['slip'] = "https://app.myshipi.com/api/shipping_labels/".$user_id."/".$carrier."/order_".$order_id."_track_".$track."_packing_slip.pdf";
					$result_arr = array();
					if(isset($general_settings['dhl_gm_integration_key']) && $general_settings['dhl_gm_integration_key'] == $key){
						if(isset($_GET['label'])){
							$output['user_id'] = sanitize_text_field($_GET['label']);
							if(isset($general_settings['dhl_gm_v_enable']) && $general_settings['dhl_gm_v_enable'] == 'yes'){
								$result_arr = !empty(get_option('dhl_gm_values_'.$order_id, array())) ? json_decode(get_option('dhl_gm_values_'.$order_id, array())) : [];
							}
							$result_arr[] = $output;
						}else{
							$result_arr[] = $output;							
						}
						
						// if(!empty($return_status)){
						// 	update_option('dhl_gm_return_values_'.$order_id, json_encode($result_arr));
						// 	update_post_meta($order_id, apply_filters('a2z_rtracking_id_meta_name', 'a2z_rtracking_num'), $track);
						// 	die();
						// }

						update_option('dhl_gm_values_'.$order_id, json_encode($result_arr));
					}

					die();
				}
			}
			public function dhl_gm_method( $methods )
			{
				if (is_admin() && !is_ajax() || apply_filters('dhl_gm_method_enabled', true)) {
					$methods['dhl_gm'] = 'dhl_gm'; 
				}

				return $methods;
			}
			
			public function dhl_gm_plugin_action_links($links)
			{
				$setting_value = version_compare(WC()->version, '2.1', '>=') ? "wc-settings" : "woocommerce_settings";
				$plugin_links = array(
					'<a href="' . admin_url( 'admin.php?page=' . $setting_value  . '&tab=shipping&section=dhl_gm' ) . '" style="color:green;">' . __( 'Configure', 'dhl_gm' ) . '</a>',
					'<a href="https://app.myshipi.com/support" target="_blank" >' . __('Support', 'dhl_gm') . '</a>'
					);
				return array_merge( $plugin_links, $links );
			}
			public function create_dhl_gm_shipping_meta_box() {
	       		add_meta_box( 'create_dhl_gm_shipping', __('DHL Global Mail Shipping Label','dhl_gm'), array($this, 'create_dhl_gm_shipping_label_genetation'), 'shop_order', 'side', 'core' );
	       		// add_meta_box( 'create_dhl_gm_return_shipping', __('DHL Global Mail Return Label','dhl_gm'), array($this, 'create_dhl_gm_return_label_genetation'), 'shop_order', 'side', 'core' );
		    }
		    public function create_dhl_gm_shipping_label_genetation($post){
		    			    	
		        if($post->post_type !='shop_order' ){
		    		return;
		    	}
		    	$order = wc_get_order( $post->ID );
		    	$order_id = $order->get_id();
		        $_dhl_gm_carriers = array(
					//"Public carrier name" => "technical name",
					"GPP" => "Packet Plus",
					"GMP" => "Packet",
					"GMM" => "Business Mail Standard",
					"GMR" => "Business Mail Registered",
					"GPT" => "Packet Tracked"
				);

		        $general_settings = get_option('dhl_gm_main_settings',array());
		       	
		        $items = $order->get_items();

    		    $custom_settings = array();
		    	$custom_settings['default'] =  array();
		    	$vendor_settings = array();

		    	$pack_products = array();
				
				foreach ( $items as $item ) {
					$product_data = $item->get_data();
				    $product = array();
				    $product['product_name'] = $product_data['name'];
				    $product['product_quantity'] = $product_data['quantity'];
				    $product['product_id'] = $product_data['product_id'];
				    
				    $pack_products[] = $product;
				}

				if(isset($general_settings['dhl_gm_v_enable']) && $general_settings['dhl_gm_v_enable'] == 'yes' && isset($general_settings['dhl_gm_v_labels']) && $general_settings['dhl_gm_v_labels'] == 'yes'){
					// Multi Vendor Enabled
					foreach ($pack_products as $key => $value) {

						$product_id = $value['product_id'];
						$dhl_gm_account = get_post_meta($product_id,'dhl_gm_address', true);
						if(empty($dhl_gm_account) || $dhl_gm_account == 'default'){
							$dhl_gm_account = 'default';
							if (!isset($vendor_settings[$dhl_gm_account])) {
								$vendor_settings[$dhl_gm_account] = $custom_settings['default'];
							}
							
							$vendor_settings[$dhl_gm_account]['products'][] = $value;
						}

						if($dhl_gm_account != 'default'){
							$user_account = get_post_meta($dhl_gm_account,'dhl_gm_vendor_settings', true);
							$user_account = empty($user_account) ? array() : $user_account;
							if(!empty($user_account)){
								if(!isset($vendor_settings[$dhl_gm_account])){
									$vendor_settings[$dhl_gm_account] = $custom_settings['default'];
									unset($value['product_id']);
									$vendor_settings[$dhl_gm_account]['products'][] = $value;
								}
							}else{
								$dhl_gm_account = 'default';
								$vendor_settings[$dhl_gm_account] = $custom_settings['default'];
								$vendor_settings[$dhl_gm_account]['products'][] = $value;
							}
						}

					}

				}

				if(empty($vendor_settings)){
					$custom_settings['default']['products'] = $pack_products;
				}else{
					$custom_settings = $vendor_settings;
				}

		       	$json_data = get_option('dhl_gm_values_'.$order_id);

		       	$notice = get_option('dhl_gm_status_'.$order_id, null);
		        if($notice && $notice != 'success'){
		        	_e( "<p style='color:red'>".$notice."</p>");
		        	delete_option('dhl_gm_status_'.$order_id);
		        }
		        if(!empty($json_data)){
   					$array_data = json_decode( $json_data, true ); 					
		       		if (!empty($array_data)) {
		       			if(isset($array_data[0])){
			       			foreach ($array_data as $key => $value) {
			       				if(isset($value['user_id'])){
			       					unset($custom_settings[$value['user_id']]);
			       				}
			       				if(isset($value['user_id']) && $value['user_id'] == 'default'){
									_e( '<br/><b>Account:</b><small> Default</small>');
			       				}else{
			       					$user = get_user_by( 'id', $value['user_id'] );
			       					_e( '<br/><b>Account:</b> <small>'.$user->display_name.'</small>');
			       				}
			       				if (isset($value['tracking_num'])) {
									_e( '<br/><b>Tracking No:</b> <small>'.$value['tracking_num'].'</small><br/>');
			       				}
			       				if(isset($value['label']) && !empty($value['label'])){
									_e( ' <a href="'.$value['label'].'" target="_blank" class="button button-primary" style="margin-top:3px;"> Label </a>');
								}
				       			if(isset($value['slip'])){
									_e( ' <a href="'.$value['slip'].'" target="_blank" class="button button-primary" style="margin-top:3px;"> Packing Slip </a><br/><br/>');
								}
								if(isset($value['user_id'])){
									_e( '<button name="dhl_gm_reset" value="'.$value['user_id'].'" style="background:#34b1e2; color: #fff;border-color: #34b1e2;box-shadow: 0px 1px 0px #34b1e2;text-shadow: 0px 1px 0px #fff; margin-right: 2px;" class="button button-primary" type="submit">Reset shipment(s)</button>');
			       				}else{
									_e( '<button name="dhl_gm_reset" value="default" style="background:#34b1e2; color: #fff;border-color: #34b1e2;box-shadow: 0px 1px 0px #34b1e2;text-shadow: 0px 1px 0px #fff; margin-right: 2px;" class="button button-primary" type="submit">Reset shipment(s)</button>');
			       				}
			       			}
			       		}else{
			       			$custom_settings = array();
			       			if (isset($array_data['tracking_num'])) {
								_e( '<br/><b>Tracking No:</b> <small>'.$array_data['tracking_num'].'</small><br/>');
			       			}
							if(isset($array_data['slip'])){
								_e( ' <a href="'.$array_data['slip'].'" target="_blank" class="button button-primary"> Packing Slip </a>');
							}
							_e( '<button name="dhl_gm_reset" value="default" style="background:#34b1e2; color: #fff;border-color: #34b1e2;box-shadow: 0px 1px 0px #34b1e2;text-shadow: 0px 1px 0px #fff; margin-right: 2px;" class="button button-primary" type="submit">Reset shipment(s)</button>');
			       		}
		       		}
   				}
   				$woo_curr = get_option('woocommerce_currency');
	       		foreach ($custom_settings as $ukey => $value) {
	       			$sel_service = $this->get_sel_ship_ser_of_ven($order, $ukey);
	       			if($ukey == 'default'){
						_e( '<br/><b>Default Account</b><br/>');
				        _e( '<br/><label>Select Service: </label><br/><select id="dhl_gm_service_code_default" name="dhl_gm_service_code_default">');
				        if(!empty($general_settings['dhl_gm_carrier'])){
					       	foreach ($general_settings['dhl_gm_carrier'] as $key => $value) {
					       		_e( "<option value='".$key."'>".$key .' - ' .$_dhl_gm_carriers[$key]."</option>");
					       	}
					       }
				        _e( '</select><br/>');

						_e( apply_filters("dhl_gm_custom_fields", '', 'default'));
						_e( "<br/>");
				        _e( '<button name="dhl_gm_create_label" value="default" style="background:#34b1e2; color: #fff;border-color: #34b1e2;box-shadow: 0px 1px 0px #34b1e2;text-shadow: 0px 1px 0px #fff;" class="button button-primary" type="submit">Create Shipment</button>');
				        
	       			}else{
	       				$user = get_user_by( 'id', $ukey );
		       			_e( '<br/><b>Account:</b> <small>'.$user->display_name.'</small>');
				        _e( '<br/><label>Select Service: </label><br/><select id="dhl_gm_service_code_'.$ukey.'" name="dhl_gm_service_code_'.$ukey.'">');
				        if(!empty($general_settings['dhl_gm_carrier'])){
					       	foreach ($general_settings['dhl_gm_carrier'] as $key => $value) {
					       		_e( "<option value='".$key."'>".$key .' - ' .$_dhl_gm_carriers[$key]."</option>");
					       	}
					       }
				        _e( '</select>');
		        
						_e( apply_filters("dhl_gm_custom_fields", '', $ukey));
						_e( "<br/>");
						_e( '<button name="dhl_gm_create_label" value="'.$ukey.'" style="background:#34b1e2; color: #fff;border-color: #34b1e2;box-shadow: 0px 1px 0px #34b1e2;text-shadow: 0px 1px 0px #fff;" class="button button-primary" type="submit">Create Shipment</button><br/>');
	       			}
	       		}
		    }

		    public function create_dhl_gm_return_label_genetation($post){
		    		    	
		        if($post->post_type !='shop_order' ){
		    		return;
		    	}
		    	$order = wc_get_order( $post->ID );
		    	$order_id = $order->get_id();
		        $_dhl_gm_carriers = array(
								//"Public carrier name" => "technical name",
								"LTL" => "Less Than Truckload",
								"TL" => "Truckload",
								"Air" => "Air",
								"Ocean" => "Ocean",
								"Bulk" => "Bulk",
								"Consol" => "Consolidated",
								"Flatbed" => "Flatbed"
							);

		        $general_settings = get_option('dhl_gm_main_settings',array());
		       	
		       	$json_data = get_option('dhl_gm_return_values_'.$order_id);
		       	if(empty($json_data)){
			        _e( '<b>Choose Service to Return</b>');
			        _e( '<br/><select name="dhl_gm_return_service_code">');
			        if(!empty($general_settings['dhl_gm_carrier'])){
			        	foreach ($general_settings['dhl_gm_carrier'] as $key => $value) {
			        		_e( "<option value='".$key."'>".$key .' - ' .$_dhl_gm_carriers[$key]."</option>");
			        	}
			        }
			        _e( '</select>');
			        _e( '<br/><b>Products to return</b>');
			        _e( '<br/>');
			        _e( '<table>');
			        $items = $order->get_items();
					foreach ( $items as $item ) {
						$product_data = $item->get_data();
					    
					    $product_variation_id = $item->get_variation_id();
					    $product_id = $product_data['product_id'];
					    if(!empty($product_variation_id) && $product_variation_id != 0){
					    	$product_id = $product_variation_id;
					    }

					    _e( "<tr><td><input type='checkbox' name='return_products[]' checked value='".$product_id."'>
					    	</td>");
						_e( "<td style='width:150px;'><small title='".$product_data['name']."'>". substr($product_data['name'],0,7)."</small></td>");
						_e( "<td><input type='number' name='qty_products[".$product_id."]' style='width:50px;' value='".$product_data['quantity']."'></td>");
						_e( "</tr>");
					    
					    
					}
			        _e( '</table><br/>');

			        $notice = get_option('dhl_gm_return_status_'.$order_id, null);
			        if($notice && $notice != 'success'){
			        	_e( "<p style='color:red'>".$notice."</p>");
			        	delete_option('dhl_gm_return_status_'.$order_id);
			        }

			        _e( '<button name="dhl_gm_create_return_label" style="background:#34b1e2; color: #fff;border-color: #34b1e2;box-shadow: 0px 1px 0px #34b1e2;text-shadow: 0px 1px 0px #fff;" class="button button-primary" type="submit">Create Return Shipment</button>');
			        
		       	} else{
		       		$array_data = json_decode( $json_data, true );
		       		_e( '<a href="'.$array_data['label'].'" target="_blank" style="background:#34b1e2; color: #fff;border-color: #34b1e2;box-shadow: 0px 1px 0px #34b1e2;text-shadow: 0px 1px 0px #fff;" class="button button-primary"> Return Label </a> ');
					   _e( '<a href="'.$array_data['invoice'].'" target="_blank" class="button button-primary"> Invoice </a></br>');
					   _e( '<button name="dhl_gm_return_reset" class="button button-secondary" style="margin-top:3px;" type="submit"> Reset</button>');
		       		
		       	}

		    }

		    public function hit_wc_checkout_order_processed($order_id){
		    	
				$post = get_post($order_id);
				
		    	if($post->post_type !='shop_order' ){
		    		return;
		    	}

		        $order = wc_get_order( $order_id );

				$service_code = $multi_ven = '';
		        foreach( $order->get_shipping_methods() as $item_id => $item ){
					$service_code = $item->get_meta('dhl_gm_service');

				}
				if(empty($service_code)){
					return;
					
				}
              
				$general_settings = get_option('dhl_gm_main_settings',array());
		    	$order_data = $order->get_data();
		    	$items = $order->get_items();
		    	
		    	if(!isset($general_settings['dhl_gm_label_automation']) || $general_settings['dhl_gm_label_automation'] != 'yes'){
		    		return;
		    	}
				
				$order_products = $this->get_products_on_order($general_settings, $order);
				$custom_settings = $this->get_vendors_on_order($general_settings, $order_products);

				$order_id = $order_data['id'];
	       		$order_currency = $order_data['currency'];

	       		$order_shipping_first_name = $order_data['shipping']['first_name'];
				$order_shipping_last_name = $order_data['shipping']['last_name'];
				$order_shipping_company = empty($order_data['shipping']['company']) ? $order_data['shipping']['first_name'] :  $order_data['shipping']['company'];
				$order_shipping_address_1 = $order_data['shipping']['address_1'];
				$order_shipping_address_2 = $order_data['shipping']['address_2'];
				$order_shipping_city = $order_data['shipping']['city'];
				$order_shipping_state = $order_data['shipping']['state'];
				$order_shipping_postcode = $order_data['shipping']['postcode'];
				$order_shipping_country = $order_data['shipping']['country'];
				$order_shipping_phone = $order_data['billing']['phone'];
				$order_shipping_email = $order_data['billing']['email'];
				if(!empty($general_settings) && isset($general_settings['dhl_gm_integration_key'])){
					$mode = 'live';
					if(isset($general_settings['dhl_gm_test']) && $general_settings['dhl_gm_test']== 'yes'){
						$mode = 'test';
					}
					$execution = 'manual';
					if(isset($general_settings['dhl_gm_label_automation']) && $general_settings['dhl_gm_label_automation']== 'yes'){
						$execution = 'auto';
					}

					$boxes_to_shipo = array();
					if (isset($general_settings['dhl_gm_packing_type']) && $general_settings['dhl_gm_packing_type'] == "box") {
						if (isset($general_settings['dhl_gm_boxes']) && !empty($general_settings['dhl_gm_boxes'])) {
							foreach ($general_settings['dhl_gm_boxes'] as $box) {
								if ($box['enabled'] != 1) {
									continue;
								}else {
									$boxes_to_shipo[] = $box;
								}
							}
						}
					}

					foreach ($custom_settings as $key => $cvalue) {
						$service_code = $this->get_sel_ship_ser_of_ven($order, $key);
						$desination_country = (isset($order_data['shipping']['country']) && $order_data['shipping']['country'] != '') ? $order_data['shipping']['country'] : $order_data['billing']['country'];
						if(empty($service_code)){
							if( !isset($general_settings['dhl_gm_international_service']) && !isset($general_settings['dhl_gm_Domestic_service'])){
								return;
							}
							if (isset($general_settings['dhl_gm_country']) && $general_settings["dhl_gm_country"] == $desination_country && $general_settings['dhl_gm_Domestic_service'] != 'null'){
								$service_code = $general_settings['dhl_gm_Domestic_service'];
							} elseif (isset($general_settings['dhl_gm_country']) && $general_settings["dhl_gm_country"] != $desination_country && $general_settings['dhl_gm_international_service'] != 'null'){
								$service_code = $general_settings['dhl_gm_international_service'];
							} else {
								return;
							}
						}
						global $dhl_gm_core;
						$frm_curr = get_option('woocommerce_currency');
						$to_curr = isset($dhl_gm_core[$cvalue['dhl_gm_country']]) ? $dhl_gm_core[$cvalue['dhl_gm_country']]['currency'] : '';
						$curr_con_rate = ( isset($cvalue['dhl_gm_con_rate']) && !empty($cvalue['dhl_gm_con_rate']) ) ? $cvalue['dhl_gm_con_rate'] : 0;

						if (!empty($frm_curr) && !empty($to_curr) && ($frm_curr != $to_curr) ) {
							if (isset($general_settings['dhl_gm_auto_con_rate']) && $general_settings['dhl_gm_auto_con_rate'] == "yes") {
								$current_date = date('m-d-Y', time());
								$ex_rate_data = get_option('dhl_gm_ex_rate'.$key);
								$ex_rate_data = !empty($ex_rate_data) ? $ex_rate_data : array();
								if (empty($ex_rate_data) || (isset($ex_rate_data['date']) && $ex_rate_data['date'] != $current_date) ) {
									if (isset($cvalue['dhl_gm_country']) && !empty($cvalue['dhl_gm_country']) && isset($general_settings['dhl_gm_integration_key']) && !empty($general_settings['dhl_gm_integration_key'])) {
										
										$ex_rate_Request = json_encode(array('integrated_key' => $general_settings['dhl_gm_integration_key'],
															'from_curr' => $frm_curr,
															'to_curr' => $to_curr));

										$ex_rate_url = "https://app.myshipi.com/get_exchange_rate.php";
										$ex_rate_response = wp_remote_post( $ex_rate_url , array(
														'method'      => 'POST',
														'timeout'     => 45,
														'redirection' => 5,
														'httpversion' => '1.0',
														'blocking'    => true,
														'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
														'body'        => $ex_rate_Request,
														)
													);

										$ex_rate_result = ( is_array($ex_rate_response) && isset($ex_rate_response['body'])) ? json_decode($ex_rate_response['body'], true) : array();

										if ( !empty($ex_rate_result) && isset($ex_rate_result['ex_rate']) && $ex_rate_result['ex_rate'] != "Not Found" ) {
											$ex_rate_result['date'] = $current_date;
											update_option('dhl_gm_ex_rate'.$key, $ex_rate_result);
										}else {
											if (!empty($ex_rate_data)) {
												$ex_rate_data['date'] = $current_date;
												update_option('dhl_gm_ex_rate'.$key, $ex_rate_data);
											}
										}
									}
								}
								$get_ex_rate = get_option('dhl_gm_ex_rate'.$key, '');
								$get_ex_rate = !empty($get_ex_rate) ? $get_ex_rate : array();
								$curr_con_rate = ( !empty($get_ex_rate) && isset($get_ex_rate['ex_rate']) ) ? $get_ex_rate['ex_rate'] : 0;
							}
						}

						$c_codes = [];

						foreach($cvalue['products'] as $prod_to_shipo_key => $prod_to_shipo){
							$saved_cc = get_post_meta( $prod_to_shipo['product_id'], 'hits_dhl_gm_cc', true);
							if(!empty($saved_cc)){
								$c_codes[] = $saved_cc;
							}

							if (!empty($frm_curr) && !empty($to_curr) && ($frm_curr != $to_curr) ) {
								if ($curr_con_rate > 0) {
									$cvalue['products'][$prod_to_shipo_key]['price'] = $prod_to_shipo['price'] * $curr_con_rate;
								}
							}
						}

						//For Automatic Label Generation						
						
						$data = array();
						$data['integrated_key'] = $general_settings['dhl_gm_integration_key'];
						$data['order_id'] = $order_id;
						$data['exec_type'] = $execution;
						$data['mode'] = $mode;
						$data['carrier_type'] = 'dhl_gm';
						$data['meta'] = array(
							"site_id" => $cvalue['dhl_gm_site_id'],
							"password"  => $cvalue['dhl_gm_site_pwd'],
							"acc_no" => $cvalue['dhl_gm_acc_no'],
							"t_company" => $order_shipping_company,
							"t_address1" => str_replace('"', '', $order_shipping_address_1),
							"t_address2" => str_replace('"', '', $order_shipping_address_2),
							"t_city" => $order_shipping_city,
							"t_state" => $order_shipping_state,
							"t_postal" => $order_shipping_postcode,
							"t_country" => $order_shipping_country,
							"t_name" => $order_shipping_first_name . ' '. $order_shipping_last_name,
							"t_phone" => $order_shipping_phone,
							"t_email" => $order_shipping_email,
							"products" => $cvalue['products'],
							"pack_algorithm" => $general_settings['dhl_gm_packing_type'],
							"boxes" => $boxes_to_shipo,
							"max_weight" => $general_settings['dhl_gm_max_weight'],
							"wight_dim_unit" => isset($general_settings['dhl_gm_weight_unit']) ? $general_settings['dhl_gm_weight_unit'] : "KG_CM",
							"service_code" => $service_code,
							"s_company" => $cvalue['dhl_gm_company'],
							"s_address1" => $cvalue['dhl_gm_address1'],
							"s_address2" => $cvalue['dhl_gm_address2'],
							"s_city" => $cvalue['dhl_gm_city'],
							"s_state" => $cvalue['dhl_gm_state'],
							"s_postal" => $cvalue['dhl_gm_zip'],
							"s_country" => $cvalue['dhl_gm_country'],
							"gstin" => $cvalue['dhl_gm_gstin'],
							"s_name" => $cvalue['dhl_gm_shipper_name'],
							"s_phone" => $cvalue['dhl_gm_mob_num'],
							"s_email" => $cvalue['dhl_gm_email'],
							"sent_email_to" => $cvalue['dhl_gm_label_email'],
							"translation" => ( (isset($general_settings['dhl_gm_translation']) && $general_settings['dhl_gm_translation'] == "yes" ) ? 'Y' : 'N'),
							"translation_key" => (isset($general_settings['dhl_gm_translation_key']) ? $general_settings['dhl_gm_translation_key'] : ''),
							"nature_type" => isset($general_settings['dhl_gm_nature_type']) ? $general_settings['dhl_gm_nature_type'] : "",
							"pickup_type" => isset($general_settings['dhl_gm_pickup_type']) ? $general_settings['dhl_gm_pickup_type'] : "",
							"con_desc" => isset($general_settings['dhl_gm_con_desc']) ? $general_settings['dhl_gm_con_desc'] : "",
							"global_cc" => isset($general_settings['dhl_gm_cc']) ? $general_settings['dhl_gm_cc'] : "",
							"commodity_code" => $c_codes,
							"ship_price" => isset($order_data['shipping_total']) ? $order_data['shipping_total'] : 0,
							"order_total" => isset($order_data['total']) ? $order_data['total'] : 0,
							"order_total_tax" => isset($order_data['total_tax']) ? $order_data['total_tax'] : 0,
							"label_copies" => (isset($general_settings['dhl_gm_label_copies']) && $general_settings['dhl_gm_label_copies'] > 0) ? $general_settings['dhl_gm_label_copies'] : 1,
							"label" => $key
						);
						//Auto Shipment
						$auto_ship_url = "https://app.myshipi.com/label_api/create_shipment.php";
						wp_remote_post( $auto_ship_url , array(
							'method'      => 'POST',
							'timeout'     => 45,
							'redirection' => 5,
							'httpversion' => '1.0',
							'blocking'    => false,
							'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
							'body'        => json_encode($data),
							)
						);

					}
	       		
				}	
		    }

		    // Save the data of the Meta field
			public function create_dhl_gm_shipping( $order_id ) {
		    	$post = get_post($order_id);
		    	if($post->post_type !='shop_order' ){
		    		return;
		    	}
		    	
		    	if (  isset( $_POST[ 'dhl_gm_reset' ] ) ) {
		    		update_option('dhl_gm_values_'.$order_id, "");
		    	}

		    	if (isset($_POST['dhl_gm_create_label'])){
		    		$order = wc_get_order($order_id);
		    		$create_shipment_for = sanitize_text_field($_POST['dhl_gm_create_label']);
		    		
		        	$service_code = isset($_POST['dhl_gm_service_code_'.$create_shipment_for]) ? sanitize_text_field($_POST['dhl_gm_service_code_'.$create_shipment_for]) : "GPP";
				   
			       if($order){
		       		$order_data = $order->get_data();
			       		$order_id = $order_data['id'];
						
			       		$order_currency = $order_data['currency'];
			       		$general_settings = get_option('dhl_gm_main_settings',array());

			       		$order_products = $this->get_products_on_order($general_settings, $order);
				       	$custom_settings = $this->get_vendors_on_order($general_settings, $order_products);

			       		$order_shipping_first_name = $order_data['shipping']['first_name'];
						$order_shipping_last_name = $order_data['shipping']['last_name'];
						$order_shipping_company = empty($order_data['shipping']['company']) ? $order_data['shipping']['first_name'] :  $order_data['shipping']['company'];
						$order_shipping_address_1 = $order_data['shipping']['address_1'];
						$order_shipping_address_2 = $order_data['shipping']['address_2'];
						$order_shipping_city = $order_data['shipping']['city'];
						$order_shipping_state = $order_data['shipping']['state'];
						$order_shipping_postcode = $order_data['shipping']['postcode'];
						$order_shipping_country = $order_data['shipping']['country'];
						$order_shipping_phone = $order_data['billing']['phone'];
						$order_shipping_email = $order_data['billing']['email'];

					if(!empty($general_settings) && isset($general_settings['dhl_gm_integration_key']) && isset($custom_settings[$create_shipment_for])){
						$mode = 'live';
						if(isset($general_settings['dhl_gm_test']) && $general_settings['dhl_gm_test']== 'yes'){
							$mode = 'test';
						}

						$execution = 'manual';
						
						$boxes_to_shipo = array();
						if (isset($general_settings['dhl_gm_packing_type']) && $general_settings['dhl_gm_packing_type'] == "box") {
							if (isset($general_settings['dhl_gm_boxes']) && !empty($general_settings['dhl_gm_boxes'])) {
								foreach ($general_settings['dhl_gm_boxes'] as $box) {
									if ($box['enabled'] != 1) {
										continue;
									}else {
										$boxes_to_shipo[] = $box;
									}
								}
							}
						}

						global $dhl_gm_core;
						$frm_curr = get_option('woocommerce_currency');
						$to_curr = isset($dhl_gm_core[$custom_settings[$create_shipment_for]['dhl_gm_country']]) ? $dhl_gm_core[$custom_settings[$create_shipment_for]['dhl_gm_country']]['currency'] : '';
						$curr_con_rate = ( isset($custom_settings[$create_shipment_for]['dhl_gm_con_rate']) && !empty($custom_settings[$create_shipment_for]['dhl_gm_con_rate']) ) ? $custom_settings[$create_shipment_for]['dhl_gm_con_rate'] : 0;

						if (!empty($frm_curr) && !empty($to_curr) && ($frm_curr != $to_curr) ) {
							if (isset($general_settings['dhl_gm_auto_con_rate']) && $general_settings['dhl_gm_auto_con_rate'] == "yes") {
								$current_date = date('m-d-Y', time());
								$ex_rate_data = get_option('dhl_gm_ex_rate'.$create_shipment_for);
								$ex_rate_data = !empty($ex_rate_data) ? $ex_rate_data : array();
								if (empty($ex_rate_data) || (isset($ex_rate_data['date']) && $ex_rate_data['date'] != $current_date) ) {
									if (isset($custom_settings[$create_shipment_for]['dhl_gm_country']) && !empty($custom_settings[$create_shipment_for]['dhl_gm_country']) && isset($general_settings['dhl_gm_integration_key']) && !empty($general_settings['dhl_gm_integration_key'])) {
													
										$ex_rate_Request = json_encode(array('integrated_key' => $general_settings['dhl_gm_integration_key'],
															'from_curr' => $frm_curr,
															'to_curr' => $to_curr));

										$ex_rate_url = "https://app.myshipi.com/get_exchange_rate.php";
										$ex_rate_response = wp_remote_post( $ex_rate_url , array(
														'method'      => 'POST',
														'timeout'     => 45,
														'redirection' => 5,
														'httpversion' => '1.0',
														'blocking'    => true,
														'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
														'body'        => $ex_rate_Request,
														)
													);

										$ex_rate_result = ( is_array($ex_rate_response) && isset($ex_rate_response['body'])) ? json_decode($ex_rate_response['body'], true) : array();

										if ( !empty($ex_rate_result) && isset($ex_rate_result['ex_rate']) && $ex_rate_result['ex_rate'] != "Not Found" ) {
											$ex_rate_result['date'] = $current_date;
											update_option('dhl_gm_ex_rate'.$create_shipment_for, $ex_rate_result);
										}else {
											if (!empty($ex_rate_data)) {
												$ex_rate_data['date'] = $current_date;
												update_option('dhl_gm_ex_rate'.$create_shipment_for, $ex_rate_data);
											}
										}
									}
								}
								$get_ex_rate = get_option('dhl_gm_ex_rate'.$create_shipment_for, '');
								$get_ex_rate = !empty($get_ex_rate) ? $get_ex_rate : array();
								$curr_con_rate = ( !empty($get_ex_rate) && isset($get_ex_rate['ex_rate']) ) ? $get_ex_rate['ex_rate'] : 0;
							}
						}

						$c_codes = [];

						foreach($custom_settings[$create_shipment_for]['products'] as $prod_to_shipo_key => $prod_to_shipo){
							$saved_cc = get_post_meta( $prod_to_shipo['product_id'], 'hits_dhl_gm_cc', true);
							if(!empty($saved_cc)){
								$c_codes[] = $saved_cc;
							}

							if (!empty($frm_curr) && !empty($to_curr) && ($frm_curr != $to_curr) ) {
								if ($curr_con_rate > 0 && apply_filters("hit_do_conversion_while_label_generation", true)) {
									$custom_settings[$create_shipment_for]['products'][$prod_to_shipo_key]['price'] = $prod_to_shipo['price'] * $curr_con_rate;
								}
							}
						}
						$data = array();
						$data['integrated_key'] = $general_settings['dhl_gm_integration_key'];
						$data['order_id'] = $order_id;
						$data['exec_type'] = $execution;
						$data['mode'] = $mode;
						$data['carrier_type'] = 'dhl_gm';
						$data['meta'] = array(
							"site_id" => $custom_settings[$create_shipment_for]['dhl_gm_site_id'],
							"password"  => $custom_settings[$create_shipment_for]['dhl_gm_site_pwd'],
							"acc_no" => $custom_settings[$create_shipment_for]['dhl_gm_acc_no'],
							"t_company" => $order_shipping_company,
							"t_address1" => str_replace('"', '', $order_shipping_address_1),
							"t_address2" => str_replace('"', '', $order_shipping_address_2),
							"t_city" => $order_shipping_city,
							"t_state" => $order_shipping_state,
							"t_postal" => $order_shipping_postcode,
							"t_country" => $order_shipping_country,
							"t_name" => $order_shipping_first_name . ' '. $order_shipping_last_name,
							"t_phone" => $order_shipping_phone,
							"t_email" => $order_shipping_email,
							"products" => $custom_settings[$create_shipment_for]['products'],
							"pack_algorithm" => $general_settings['dhl_gm_packing_type'],
							"boxes" => $boxes_to_shipo,
							"max_weight" => $general_settings['dhl_gm_max_weight'],
							"wight_dim_unit" => isset($general_settings['dhl_gm_weight_unit']) ? $general_settings['dhl_gm_weight_unit'] : "KG_CM",
							"service_code" => $service_code,
							"s_company" => $custom_settings[$create_shipment_for]['dhl_gm_company'],
							"s_address1" => $custom_settings[$create_shipment_for]['dhl_gm_address1'],
							"s_address2" => $custom_settings[$create_shipment_for]['dhl_gm_address2'],
							"s_city" => $custom_settings[$create_shipment_for]['dhl_gm_city'],
							"s_state" => $custom_settings[$create_shipment_for]['dhl_gm_state'],
							"s_postal" => $custom_settings[$create_shipment_for]['dhl_gm_zip'],
							"s_country" => $custom_settings[$create_shipment_for]['dhl_gm_country'],
							"gstin" => $custom_settings[$create_shipment_for]['dhl_gm_gstin'],
							"s_name" => $custom_settings[$create_shipment_for]['dhl_gm_shipper_name'],
							"s_phone" => $custom_settings[$create_shipment_for]['dhl_gm_mob_num'],
							"s_email" => $custom_settings[$create_shipment_for]['dhl_gm_email'],
							"sent_email_to" => $custom_settings[$create_shipment_for]['dhl_gm_label_email'],
							"translation" => ( (isset($general_settings['dhl_gm_translation']) && $general_settings['dhl_gm_translation'] == "yes" ) ? 'Y' : 'N'),
							"translation_key" => (isset($general_settings['dhl_gm_translation_key']) ? $general_settings['dhl_gm_translation_key'] : ''),
							"nature_type" => isset($general_settings['dhl_gm_nature_type']) ? $general_settings['dhl_gm_nature_type'] : "",
							"pickup_type" => isset($general_settings['dhl_gm_pickup_type']) ? $general_settings['dhl_gm_pickup_type'] : "",
							"con_desc" => isset($general_settings['dhl_gm_con_desc']) ? $general_settings['dhl_gm_con_desc'] : "",
							"global_cc" => isset($general_settings['dhl_gm_cc']) ? $general_settings['dhl_gm_cc'] : "",
							"commodity_code" => $c_codes,
							"ship_price" => isset($order_data['shipping_total']) ? $order_data['shipping_total'] : 0,
							"order_total" => isset($order_data['total']) ? $order_data['total'] : 0,
							"order_total_tax" => isset($order_data['total_tax']) ? $order_data['total_tax'] : 0,
							"label_copies" => (isset($general_settings['dhl_gm_label_copies']) && $general_settings['dhl_gm_label_copies'] > 0) ? $general_settings['dhl_gm_label_copies'] : 1,
							"label" => $create_shipment_for
						);
						
						//Manual Shipment
						$manual_ship_url = "https://app.myshipi.com/label_api/create_shipment.php";
						$response = wp_remote_post( $manual_ship_url , array(
							'method'      => 'POST',
							'timeout'     => 45,
							'redirection' => 5,
							'httpversion' => '1.0',
							'blocking'    => true,
							'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
							'body'        => json_encode($data),
							)
						);

						$output = (is_array($response) && isset($response['body'])) ? json_decode($response['body'],true) : [];
							if($output){
								if(isset($output['status'])){
									if(isset($output['status']) && $output['status'] != 'success'){
										update_option('dhl_gm_status_'.$order_id, $output['status']);
									}else if(isset($output['status']) && $output['status'] == 'success'){
										$output['user_id'] = $create_shipment_for;
										$val = get_option('dhl_gm_values_'.$order_id, []);
										$result_arr = array();
										if(!empty($val)){
											$result_arr = json_decode($val, true);
										}
										$result_arr[] = $output;
										update_option('dhl_gm_values_'.$order_id, json_encode($result_arr));
									}
								}else{
									update_option('dhl_gm_status_'.$order_id, 'Unhandled/empty response found. Contact Shipi Team.');
								}
							}else{
								update_option('dhl_gm_status_'.$order_id, 'Site not Connected with Shipi. Contact Shipi Team.');
							}
						}	
			       }
		        }
		    }

		    // Save the data of the Meta field
			public function create_dhl_gm_return_shipping( $order_id ) {
				
		    	$post = get_post($order_id);
		    	if($post->post_type !='shop_order' ){
		    		return;
		    	}
		    	
		  //   	if (  isset( $_POST[ 'dhl_gm_reset' ] ) ) {
		  //   		delete_option('dhl_gm_return_values_'.$order_id);
				// }
				
				if (  isset( $_POST[ 'dhl_gm_return_reset' ] ) ) {
		    		delete_option('dhl_gm_return_values_'.$order_id);
		    	}

		    	if (  isset( $_POST['dhl_gm_create_return_label']) && isset( $_POST[ 'dhl_gm_return_service_code' ] ) ) {
		           
		        }
		    }

		    private function get_vendors_on_order($general_settings = [], $pack_products = [])
		    {
		    	$custom_settings = array();
				$custom_settings['default'] = array(
					'dhl_gm_site_id' => isset($general_settings['dhl_gm_site_id'])? $general_settings['dhl_gm_site_id'] : '',
					'dhl_gm_site_pwd' => isset($general_settings['dhl_gm_site_pwd'])? $general_settings['dhl_gm_site_pwd'] : '',
					'dhl_gm_acc_no' => isset($general_settings['dhl_gm_acc_no'])? $general_settings['dhl_gm_acc_no'] : '',
					'dhl_gm_shipper_name' => isset($general_settings['dhl_gm_shipper_name'])?$general_settings['dhl_gm_shipper_name'] : '',
					'dhl_gm_company' => isset($general_settings['dhl_gm_company'])?$general_settings['dhl_gm_company'] : '',
					'dhl_gm_mob_num' => isset($general_settings['dhl_gm_mob_num'])?$general_settings['dhl_gm_mob_num'] : '',
					'dhl_gm_email' => isset($general_settings['dhl_gm_email'])?$general_settings['dhl_gm_email'] : '',
					'dhl_gm_address1' => isset($general_settings['dhl_gm_address1'])?$general_settings['dhl_gm_address1'] : '',
					'dhl_gm_address2' => isset($general_settings['dhl_gm_address2'])?$general_settings['dhl_gm_address2'] : '',
					'dhl_gm_city' => isset($general_settings['dhl_gm_city'])?$general_settings['dhl_gm_city'] : '',
					'dhl_gm_state' => isset($general_settings['dhl_gm_state'])? $general_settings['dhl_gm_state']: '',
					'dhl_gm_zip' => isset($general_settings['dhl_gm_zip'])?$general_settings['dhl_gm_zip'] : '',
					'dhl_gm_country' => isset($general_settings['dhl_gm_country'])?$general_settings['dhl_gm_country'] : '',
					'dhl_gm_gstin' => isset($general_settings['dhl_gm_gstin'])?$general_settings['dhl_gm_gstin'] : '',
					'dhl_gm_con_rate' => isset($general_settings['dhl_gm_con_rate'])? $general_settings['dhl_gm_con_rate']: '',
					'dhl_gm_label_email' => isset($general_settings['dhl_gm_label_email']) ? $general_settings['dhl_gm_label_email'] : ''
				);
				$vendor_settings = array();
				if(isset($general_settings['dhl_gm_v_enable']) && $general_settings['dhl_gm_v_enable'] == 'yes' && isset($general_settings['dhl_gm_v_labels']) && $general_settings['dhl_gm_v_labels'] == 'yes'){
					// Multi Vendor Enabled
					foreach ($pack_products as $key => $value) {
						$product_id = $value['product_id'];
						$dhl_gm_account = get_post_meta($product_id,'dhl_gm_address', true);
						if(empty($dhl_gm_account) || $dhl_gm_account == 'default'){
							$dhl_gm_account = 'default';
							if (!isset($vendor_settings[$dhl_gm_account])) {
								$vendor_settings[$dhl_gm_account] = $custom_settings['default'];
							}
							$vendor_settings[$dhl_gm_account]['products'][] = $value;
						}

						if($dhl_gm_account != 'default'){
							$user_account = get_post_meta($dhl_gm_account,'dhl_gm_vendor_settings', true);
							$user_account = empty($user_account) ? array() : $user_account;
							if(!empty($user_account)){
								if(!isset($vendor_settings[$dhl_gm_account])){
									$vendor_settings[$dhl_gm_account] = $custom_settings['default'];
									if($user_account['dhl_gm_site_id'] != '' && $user_account['dhl_gm_site_pwd'] != '' && $user_account['dhl_gm_acc_no'] != ''){
										$vendor_settings[$dhl_gm_account]['dhl_gm_site_id'] = $user_account['dhl_gm_site_id'];
										if($user_account['dhl_gm_site_pwd'] != ''){
											$vendor_settings[$dhl_gm_account]['dhl_gm_site_pwd'] = $user_account['dhl_gm_site_pwd'];
										}
										if($user_account['dhl_gm_acc_no'] != ''){
											$vendor_settings[$dhl_gm_account]['dhl_gm_acc_no'] = $user_account['dhl_gm_acc_no'];
										}
									}

									if ($user_account['dhl_gm_address1'] != '' && $user_account['dhl_gm_city'] != '' && $user_account['dhl_gm_state'] != '' && $user_account['dhl_gm_zip'] != '' && $user_account['dhl_gm_country'] != '' && $user_account['dhl_gm_shipper_name'] != '') {
										if($user_account['dhl_gm_shipper_name'] != ''){
											$vendor_settings[$dhl_gm_account]['dhl_gm_shipper_name'] = $user_account['dhl_gm_shipper_name'];
										}
										if($user_account['dhl_gm_company'] != ''){
											$vendor_settings[$dhl_gm_account]['dhl_gm_company'] = $user_account['dhl_gm_company'];
										}
										if($user_account['dhl_gm_mob_num'] != ''){
											$vendor_settings[$dhl_gm_account]['dhl_gm_mob_num'] = $user_account['dhl_gm_mob_num'];
										}
										if($user_account['dhl_gm_email'] != ''){
											$vendor_settings[$dhl_gm_account]['dhl_gm_email'] = $user_account['dhl_gm_email'];
										}
										if ($user_account['dhl_gm_address1'] != '') {
											$vendor_settings[$dhl_gm_account]['dhl_gm_address1'] = $user_account['dhl_gm_address1'];
										}
										$vendor_settings[$dhl_gm_account]['dhl_gm_address2'] = $user_account['dhl_gm_address2'];
										if($user_account['dhl_gm_city'] != ''){
											$vendor_settings[$dhl_gm_account]['dhl_gm_city'] = $user_account['dhl_gm_city'];
										}
										if($user_account['dhl_gm_state'] != ''){
											$vendor_settings[$dhl_gm_account]['dhl_gm_state'] = $user_account['dhl_gm_state'];
										}
										if($user_account['dhl_gm_zip'] != ''){
											$vendor_settings[$dhl_gm_account]['dhl_gm_zip'] = $user_account['dhl_gm_zip'];
										}
										if($user_account['dhl_gm_country'] != ''){
											$vendor_settings[$dhl_gm_account]['dhl_gm_country'] = $user_account['dhl_gm_country'];
										}
										$vendor_settings[$dhl_gm_account]['dhl_gm_gstin'] = $user_account['dhl_gm_gstin'];
										$vendor_settings[$dhl_gm_account]['dhl_gm_con_rate'] = $user_account['dhl_gm_con_rate'];
									}
									
									if(isset($general_settings['dhl_gm_v_email']) && $general_settings['dhl_gm_v_email'] == 'yes'){
										$user_dat = get_userdata($dhl_gm_account);
										$vendor_settings[$dhl_gm_account]['dhl_gm_label_email'] = $user_dat->data->user_email;
									}
									
									if($order_data['shipping']['country'] != $vendor_settings[$dhl_gm_account]['dhl_gm_country']){
										$vendor_settings[$dhl_gm_account]['service_code'] = empty($service_code) ? $user_account['dhl_gm_def_inter'] : $service_code;
									}else{
										$vendor_settings[$dhl_gm_account]['service_code'] = empty($service_code) ? $user_account['dhl_gm_def_dom'] : $service_code;
									}
								}
								$vendor_settings[$dhl_gm_account]['products'][] = $value;
							}
						}
					}
				}
				if(empty($vendor_settings)){
					$custom_settings['default']['products'] = $pack_products;
				}else{
					$custom_settings = $vendor_settings;
				}
				return $custom_settings;
		    }

		    private function get_products_on_order($general_settings = [], $order = [])
		    {
		    	$items = $order->get_items();
				$pack_products = array();
				foreach ( $items as $item ) {
					$product_data = $item->get_data();
					$product = array();
					$product['product_name'] = str_replace('"', '', $product_data['name']);
					$product['product_quantity'] = $product_data['quantity'];
					$product['product_id'] = $product_data['product_id'];

					$saved_cc = get_post_meta( $product_data['product_id'], 'hits_dhl_gm_cc', true);
					if(!empty($saved_cc)){
						$product['commodity_code'] = $saved_cc;
					}

					$saved_desc = get_post_meta( $product_data['product_id'], 'hits_dhl_gm_desc', true);
					if(!empty($saved_desc)){
						$product['invoice_desc'] = $saved_desc;
					}

					$product_variation_id = $item->get_variation_id();
					if(empty($product_variation_id)){
						$getproduct = wc_get_product( $product_data['product_id'] );
					}else{
						$getproduct = wc_get_product( $product_variation_id );
					}
						    
					$woo_weight_unit = get_option('woocommerce_weight_unit');
					$woo_dimension_unit = get_option('woocommerce_dimension_unit');

					$dhl_gm_mod_weight_unit = $dhl_gm_mod_dim_unit = '';

					if(!empty($general_settings['dhl_gm_weight_unit']) && $general_settings['dhl_gm_weight_unit'] == 'KG_CM')
					{
						$dhl_gm_mod_weight_unit = 'kg';
						$dhl_gm_mod_dim_unit = 'cm';
					}elseif(!empty($general_settings['dhl_gm_weight_unit']) && $general_settings['dhl_gm_weight_unit'] == 'LB_IN'){
						$dhl_gm_mod_weight_unit = 'lbs';
						$dhl_gm_mod_dim_unit = 'in';
					} else {
						$dhl_gm_mod_weight_unit = 'kg';
						$dhl_gm_mod_dim_unit = 'cm';
					}

					$product['sku'] =  $getproduct->get_sku();
					$product['price'] = (isset($product_data['total']) && isset($product_data['quantity'])) ? number_format(($product_data['total'] / $product_data['quantity']), 2) : 0;
					

					if ($woo_dimension_unit != $dhl_gm_mod_dim_unit) {
						$prod_width = $getproduct->get_width();
						$prod_height = $getproduct->get_height();
						$prod_depth = $getproduct->get_length();

						//wc_get_dimension( $dimension, $to_unit, $from_unit );
						$product['width'] = (!empty($prod_width) && $prod_width > 0) ? round(wc_get_dimension( $prod_width, $dhl_gm_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
						$product['height'] = (!empty($prod_height) && $prod_height > 0) ? round(wc_get_dimension( $prod_height, $dhl_gm_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
						$product['depth'] = (!empty($prod_depth) && $prod_depth > 0) ? round(wc_get_dimension( $prod_depth, $dhl_gm_mod_dim_unit, $woo_dimension_unit ), 2) : 0.1 ;
					}else {
						$product['width'] = $getproduct->get_width();
						$product['height'] = $getproduct->get_height();
						$product['depth'] = $getproduct->get_length();
					}
					if ($woo_weight_unit != $dhl_gm_mod_weight_unit) {
						$prod_weight = $getproduct->get_weight();
						$product['weight'] = (!empty($prod_weight) && $prod_weight > 0) ? round(wc_get_weight( $prod_weight, $dhl_gm_mod_weight_unit, $woo_weight_unit ), 2) : 0.1 ;
					}else{
						$product['weight'] = $getproduct->get_weight();
					}
					$pack_products[] = $product;
				}
				return $pack_products;
		    }
		    private function get_sel_ship_ser_of_ven($order = [], $ven_id='default')
		    {
		    	$service_code = "";
		    	if( $order->has_shipping_method('dhl_gm') ) {
		    		foreach ($order->get_shipping_methods() as $m_key => $method) {
		    			if ($method->get_method_id() == "dhl_gm") {
		    				if (($method->get_meta("dhl_gm_multi_ven") == $ven_id) || (empty($method->get_meta("dhl_gm_multi_ven")) && $ven_id == "default")) {
		    					$service_code = $method->get_meta("dhl_gm_service");
		    				}
		    			}

		    		}
		    	}
		    	return $service_code;
		    }
			
		    private function a2z_get_zipcode_or_city($country, $city, $postcode)
			{
				$no_postcode_country = array(
					'AE', 'AF', 'AG', 'AI', 'AL', 'AN', 'AO', 'AW', 'BB', 'BF', 'BH', 'BI', 'BJ', 'BM', 'BO', 'BS', 'BT', 'BW', 'BZ', 'CD', 'CF', 'CG', 'CI', 'CK',
					'CL', 'CM', 'CR', 'CV', 'DJ', 'DM', 'DO', 'EC', 'EG', 'ER', 'ET', 'FJ', 'FK', 'GA', 'GD', 'GH', 'GI', 'GM', 'GN', 'GQ', 'GT', 'GW', 'GY', 'HK', 'HN', 'HT', 'IE', 'IQ', 'IR',
					'JM', 'JO', 'KE', 'KH', 'KI', 'KM', 'KN', 'KP', 'KW', 'KY', 'LA', 'LB', 'LC', 'LK', 'LR', 'LS', 'LY', 'ML', 'MM', 'MO', 'MR', 'MS', 'MT', 'MU', 'MW', 'MZ', 'NA', 'NE', 'NG', 'NI',
					'NP', 'NR', 'NU', 'OM', 'PA', 'PE', 'PF', 'PY', 'QA', 'RW', 'SA', 'SB', 'SC', 'SD', 'SL', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SY', 'TC', 'TD', 'TG', 'TL', 'TO', 'TT', 'TV', 'TZ',
					'UG', 'UY', 'VC', 'VE', 'VG', 'VN', 'VU', 'WS', 'XA', 'XB', 'XC', 'XE', 'XL', 'XM', 'XN', 'XS', 'YE', 'ZM', 'ZW'
				);

				$postcode_city = !in_array($country, $no_postcode_country) ? $postcode_city = "<Postalcode>{$postcode}</Postalcode>" : '';
				if (!empty($city)) {
					$postcode_city .= "<City>{$city}</City>";
				}
				return $postcode_city;
			}
			public function hit_get_dhl_gm_packages($package, $general_settings, $orderCurrency, $chk = false)
			{
				switch ($general_settings['dhl_gm_packing_type']) {
					case 'box':
						return $this->box_shipping($package, $general_settings, $orderCurrency, $chk);
						break;
					case 'weight_based':
						return $this->weight_based_shipping($package, $general_settings, $orderCurrency, $chk);
						break;
					case 'per_item':
					default:
						return $this->per_item_shipping($package, $general_settings, $orderCurrency, $chk);
						break;
				}
			}
			private function weight_based_shipping($package, $general_settings, $orderCurrency, $chk = false)
			{
				
				if (!class_exists('WeightPack')) {
					include_once 'controllors/classes/weight_pack/class-hit-weight-packing.php';
				}
				$max_weight = isset($general_settings['dhl_gm_max_weight']) && $general_settings['dhl_gm_max_weight'] != ''  ? $general_settings['dhl_gm_max_weight'] : 10;
				$weight_pack = new WeightPack('pack_ascending');
				$weight_pack->set_max_weight($max_weight);

				$package_total_weight = 0;
				$insured_value = 0;

				$ctr = 0;
				foreach ($package as $item_id => $product_data) {
					$ctr++;

					$chk_qty = $product_data['product_quantity'];

					$weight_pack->add_item($product_data['weight'], $product_data, $chk_qty);
				}

				$pack   =   $weight_pack->pack_items();
				$errors =   $pack->get_errors();
				if (!empty($errors)) {
					//do nothing
					return;
				} else {
					$boxes    =   $pack->get_packed_boxes();
					$unpacked_items =   $pack->get_unpacked_items();

					$insured_value        =   0;

					$packages      =   array_merge($boxes, $unpacked_items); // merge items if unpacked are allowed
					$package_count  =   sizeof($packages);
					// get all items to pass if item info in box is not distinguished
					$packable_items =   $weight_pack->get_packable_items();
					$all_items    =   array();
					if (is_array($packable_items)) {
						foreach ($packable_items as $packable_item) {
							$all_items[]    =   $packable_item['data'];
						}
					}
					//pre($packable_items);
					$order_total = '';

					$to_ship  = array();
					$group_id = 1;
					foreach ($packages as $package) {
						$packed_products = array();
						if (isset($package['items'])) {
							foreach ($package['items'] as $key => $value) {
								$insured_value += isset($value['price']) ? $value['price'] : 0;
							}
						}
						$packed_products    =   isset($package['items']) ? $package['items'] : $all_items;
						// Creating package request
						$package_total_weight   = $package['weight'];

						$insurance_array = array(
							'Amount' => $insured_value,
							'Currency' => $orderCurrency
						);

						$group = array(
							'GroupNumber' => $group_id,
							'GroupPackageCount' => 1,
							'Weight' => array(
								'Value' => round($package_total_weight, 3),
								'Units' => (isset($general_settings['weg_dim']) && $general_settings['weg_dim'] === 'yes') ? 'KG' : 'LBS'
							),
							'packed_products' => $packed_products,
						);
						$group['InsuredValue'] = $insurance_array;
						$group['packtype'] = 'BOX';

						$to_ship[] = $group;
						$group_id++;
					}
				}
				return $to_ship;
			}
			private function box_shipping($package, $general_settings, $orderCurrency, $chk = false)
			{
				if (!class_exists('HIT_Boxpack')) {
					include_once 'controllors/classes/hit-box-packing.php';
				}
				$boxpack = new HIT_Boxpack();
				$boxes = isset($general_settings['dhl_gm_boxes']) ? $general_settings['dhl_gm_boxes'] : array();
				if (empty($boxes)) {
					return false;
				}
				// $boxes = unserialize($boxes);
				// Define boxes
				foreach ($boxes as $key => $box) {
					if (!$box['enabled']) {
						continue;
					}
					$box['pack_type'] = !empty($box['pack_type']) ? $box['pack_type'] : 'BOX';

					$newbox = $boxpack->add_box($box['length'], $box['width'], $box['height'], $box['box_weight'], $box['pack_type']);

					if (isset($box['id'])) {
						$newbox->set_id(current(explode(':', $box['id'])));
					}

					if ($box['max_weight']) {
						$newbox->set_max_weight($box['max_weight']);
					}

					if ($box['pack_type']) {
						$newbox->set_packtype($box['pack_type']);
					}
				}

				// Add items
				foreach ($package as $item_id => $product_data) {

					if (isset($product_data['weight']) && !empty($product_data['weight'])) {
						$item_weight = round($product_data['weight'] > 0.001 ? $product_data['weight'] : 0.001, 3);
					}

					if (isset($product_data['width']) && isset($product_data['height']) && isset($product_data['depth']) && !empty($product_data['width']) && !empty($product_data['height']) && !empty($product_data['depth'])) {
						$item_dimension = array(
							'Length' => max(1, round($product_data['depth'], 3)),
							'Width' => max(1, round($product_data['width'], 3)),
							'Height' => max(1, round($product_data['height'], 3))
						);
					}

					if (isset($item_weight) && isset($item_dimension)) {

						// $dimensions = array($values['depth'], $values['height'], $values['width']);
						$chk_qty = $product_data['product_quantity'];
						for ($i = 0; $i < $chk_qty; $i++) {
							$boxpack->add_item($item_dimension['Width'], $item_dimension['Height'], $item_dimension['Length'], $item_weight, round($product_data['price']), array(
								'data' => $product_data
							));
						}
					} else {
						//    $this->debug(sprintf(__('Product #%s is missing dimensions. Aborting.', 'wf-shipping-dhl'), $item_id), 'error');
						return;
					}
				}

				// Pack it
				$boxpack->pack();
				$packages = $boxpack->get_packages();
				$to_ship = array();
				$group_id = 1;
				foreach ($packages as $package) {
					if ($package->unpacked === true) {
						//$this->debug('Unpacked Item');
					} else {
						//$this->debug('Packed ' . $package->id);
					}

					$dimensions = array($package->length, $package->width, $package->height);

					sort($dimensions);
					$insurance_array = array(
						'Amount' => round($package->value),
						'Currency' => $orderCurrency
					);


					$group = array(
						'GroupNumber' => $group_id,
						'GroupPackageCount' => 1,
						'Weight' => array(
							'Value' => round($package->weight, 3),
							'Units' => (isset($general_settings['weg_dim']) && $general_settings['weg_dim'] === 'yes') ? 'KG' : 'LBS'
						),
						'Dimensions' => array(
							'Length' => max(1, round($dimensions[2], 3)),
							'Width' => max(1, round($dimensions[1], 3)),
							'Height' => max(1, round($dimensions[0], 3)),
							'Units' => (isset($general_settings['weg_dim']) && $general_settings['weg_dim'] === 'yes') ? 'CM' : 'IN'
						),
						'InsuredValue' => $insurance_array,
						'packed_products' => array(),
						'package_id' => $package->id,
						'packtype' => 'BOX'
					);

					if (!empty($package->packed) && is_array($package->packed)) {
						foreach ($package->packed as $packed) {
							$group['packed_products'][] = $packed->get_meta('data');
						}
					}

					if (!$package->packed) {
						foreach ($package->unpacked as $unpacked) {
							$group['packed_products'][] = $unpacked->get_meta('data');
						}
					}

					$to_ship[] = $group;

					$group_id++;
				}

				return $to_ship;
			}
			private function per_item_shipping($package, $general_settings, $orderCurrency, $chk = false)
			{
				
				$to_ship = array();
				$group_id = 1;

				// Get weight of order
				foreach ($package as $item_id => $product_data) {

					$group = array();
					$insurance_array = array(
						'Amount' => round($product_data['price']),
						'Currency' => $orderCurrency
					);

					if (isset($product_data['weight']) && !empty($product_data['weight'])) {
						$dhl_per_item_weight = round($product_data['weight'] > 0.001 ? $product_data['weight'] : 0.001, 3);
					}

					$group = array(
						'GroupNumber' => $group_id,
						'GroupPackageCount' => 1,
						'Weight' => array(
							'Value' => $dhl_per_item_weight,
							'Units' => (isset($general_settings['dhl_gm_weight_unit']) && $general_settings['dhl_gm_weight_unit'] == 'KG_CM') ? 'KG' : 'LBS'
						),
						'packed_products' => array($product_data)
					);

					if (isset($product_data['width']) && isset($product_data['height']) && isset($product_data['depth']) && !empty($product_data['width']) && !empty($product_data['height']) && !empty($product_data['depth'])) {

						$group['Dimensions'] = array(
							'Length' => max(1, round($product_data['depth'], 3)),
							'Width' => max(1, round($product_data['width'], 3)),
							'Height' => max(1, round($product_data['height'], 3)),
							'Units' => (isset($general_settings['dhl_gm_weight_unit']) && $general_settings['dhl_gm_weight_unit'] == 'KG_CM') ? 'CM' : 'IN'
						);
					}

					$group['packtype'] = 'BOX';

					$group['InsuredValue'] = $insurance_array;

					$chk_qty = $product_data['product_quantity'];

					for ($i = 0; $i < $chk_qty; $i++)
						$to_ship[] = $group;

					$group_id++;
				}

				return $to_ship;
			}
			public function dhl_gm_is_eu_country ($countrycode, $destinationcode) {
				$eu_countrycodes = array(
					'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 
					'ES', 'FI', 'FR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV',
					'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
					'HR', 'GR'

				);
				return(in_array($countrycode, $eu_countrycodes) && in_array($destinationcode, $eu_countrycodes));
			}
		}

		$dhl_gm_core = array();
		$dhl_gm_core['AD'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['AE'] = array('region' => 'AP', 'currency' =>'AED', 'weight' => 'KG_CM');
		$dhl_gm_core['AF'] = array('region' => 'AP', 'currency' =>'AFN', 'weight' => 'KG_CM');
		$dhl_gm_core['AG'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_gm_core['AI'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_gm_core['AL'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['AM'] = array('region' => 'AP', 'currency' =>'AMD', 'weight' => 'KG_CM');
		$dhl_gm_core['AN'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'KG_CM');
		$dhl_gm_core['AO'] = array('region' => 'AP', 'currency' =>'AOA', 'weight' => 'KG_CM');
		$dhl_gm_core['AR'] = array('region' => 'AM', 'currency' =>'ARS', 'weight' => 'KG_CM');
		$dhl_gm_core['AS'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_gm_core['AT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['AU'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
		$dhl_gm_core['AW'] = array('region' => 'AM', 'currency' =>'AWG', 'weight' => 'LB_IN');
		$dhl_gm_core['AZ'] = array('region' => 'AM', 'currency' =>'AZN', 'weight' => 'KG_CM');
		$dhl_gm_core['AZ'] = array('region' => 'AM', 'currency' =>'AZN', 'weight' => 'KG_CM');
		$dhl_gm_core['GB'] = array('region' => 'EU', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_gm_core['BA'] = array('region' => 'AP', 'currency' =>'BAM', 'weight' => 'KG_CM');
		$dhl_gm_core['BB'] = array('region' => 'AM', 'currency' =>'BBD', 'weight' => 'LB_IN');
		$dhl_gm_core['BD'] = array('region' => 'AP', 'currency' =>'BDT', 'weight' => 'KG_CM');
		$dhl_gm_core['BE'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['BF'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_gm_core['BG'] = array('region' => 'EU', 'currency' =>'BGN', 'weight' => 'KG_CM');
		$dhl_gm_core['BH'] = array('region' => 'AP', 'currency' =>'BHD', 'weight' => 'KG_CM');
		$dhl_gm_core['BI'] = array('region' => 'AP', 'currency' =>'BIF', 'weight' => 'KG_CM');
		$dhl_gm_core['BJ'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_gm_core['BM'] = array('region' => 'AM', 'currency' =>'BMD', 'weight' => 'LB_IN');
		$dhl_gm_core['BN'] = array('region' => 'AP', 'currency' =>'BND', 'weight' => 'KG_CM');
		$dhl_gm_core['BO'] = array('region' => 'AM', 'currency' =>'BOB', 'weight' => 'KG_CM');
		$dhl_gm_core['BR'] = array('region' => 'AM', 'currency' =>'BRL', 'weight' => 'KG_CM');
		$dhl_gm_core['BS'] = array('region' => 'AM', 'currency' =>'BSD', 'weight' => 'LB_IN');
		$dhl_gm_core['BT'] = array('region' => 'AP', 'currency' =>'BTN', 'weight' => 'KG_CM');
		$dhl_gm_core['BW'] = array('region' => 'AP', 'currency' =>'BWP', 'weight' => 'KG_CM');
		$dhl_gm_core['BY'] = array('region' => 'AP', 'currency' =>'BYR', 'weight' => 'KG_CM');
		$dhl_gm_core['BZ'] = array('region' => 'AM', 'currency' =>'BZD', 'weight' => 'KG_CM');
		$dhl_gm_core['CA'] = array('region' => 'AM', 'currency' =>'CAD', 'weight' => 'LB_IN');
		$dhl_gm_core['CF'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_gm_core['CG'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_gm_core['CH'] = array('region' => 'EU', 'currency' =>'CHF', 'weight' => 'KG_CM');
		$dhl_gm_core['CI'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_gm_core['CK'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
		$dhl_gm_core['CL'] = array('region' => 'AM', 'currency' =>'CLP', 'weight' => 'KG_CM');
		$dhl_gm_core['CM'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_gm_core['CN'] = array('region' => 'AP', 'currency' =>'CNY', 'weight' => 'KG_CM');
		$dhl_gm_core['CO'] = array('region' => 'AM', 'currency' =>'COP', 'weight' => 'KG_CM');
		$dhl_gm_core['CR'] = array('region' => 'AM', 'currency' =>'CRC', 'weight' => 'KG_CM');
		$dhl_gm_core['CU'] = array('region' => 'AM', 'currency' =>'CUC', 'weight' => 'KG_CM');
		$dhl_gm_core['CV'] = array('region' => 'AP', 'currency' =>'CVE', 'weight' => 'KG_CM');
		$dhl_gm_core['CY'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['CZ'] = array('region' => 'EU', 'currency' =>'CZK', 'weight' => 'KG_CM');
		$dhl_gm_core['DE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['DJ'] = array('region' => 'EU', 'currency' =>'DJF', 'weight' => 'KG_CM');
		$dhl_gm_core['DK'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
		$dhl_gm_core['DM'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_gm_core['DO'] = array('region' => 'AP', 'currency' =>'DOP', 'weight' => 'LB_IN');
		$dhl_gm_core['DZ'] = array('region' => 'AM', 'currency' =>'DZD', 'weight' => 'KG_CM');
		$dhl_gm_core['EC'] = array('region' => 'EU', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_gm_core['EE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['EG'] = array('region' => 'AP', 'currency' =>'EGP', 'weight' => 'KG_CM');
		$dhl_gm_core['ER'] = array('region' => 'EU', 'currency' =>'ERN', 'weight' => 'KG_CM');
		$dhl_gm_core['ES'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['ET'] = array('region' => 'AU', 'currency' =>'ETB', 'weight' => 'KG_CM');
		$dhl_gm_core['FI'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['FJ'] = array('region' => 'AP', 'currency' =>'FJD', 'weight' => 'KG_CM');
		$dhl_gm_core['FK'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_gm_core['FM'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_gm_core['FO'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
		$dhl_gm_core['FR'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['GA'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_gm_core['GB'] = array('region' => 'EU', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_gm_core['GD'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_gm_core['GE'] = array('region' => 'AM', 'currency' =>'GEL', 'weight' => 'KG_CM');
		$dhl_gm_core['GF'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['GG'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_gm_core['GH'] = array('region' => 'AP', 'currency' =>'GHS', 'weight' => 'KG_CM');
		$dhl_gm_core['GI'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_gm_core['GL'] = array('region' => 'AM', 'currency' =>'DKK', 'weight' => 'KG_CM');
		$dhl_gm_core['GM'] = array('region' => 'AP', 'currency' =>'GMD', 'weight' => 'KG_CM');
		$dhl_gm_core['GN'] = array('region' => 'AP', 'currency' =>'GNF', 'weight' => 'KG_CM');
		$dhl_gm_core['GP'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['GQ'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_gm_core['GR'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['GT'] = array('region' => 'AM', 'currency' =>'GTQ', 'weight' => 'KG_CM');
		$dhl_gm_core['GU'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_gm_core['GW'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_gm_core['GY'] = array('region' => 'AP', 'currency' =>'GYD', 'weight' => 'LB_IN');
		$dhl_gm_core['HK'] = array('region' => 'AM', 'currency' =>'HKD', 'weight' => 'KG_CM');
		$dhl_gm_core['HN'] = array('region' => 'AM', 'currency' =>'HNL', 'weight' => 'KG_CM');
		$dhl_gm_core['HR'] = array('region' => 'AP', 'currency' =>'HRK', 'weight' => 'KG_CM');
		$dhl_gm_core['HT'] = array('region' => 'AM', 'currency' =>'HTG', 'weight' => 'LB_IN');
		$dhl_gm_core['HU'] = array('region' => 'EU', 'currency' =>'HUF', 'weight' => 'KG_CM');
		$dhl_gm_core['IC'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['ID'] = array('region' => 'AP', 'currency' =>'IDR', 'weight' => 'KG_CM');
		$dhl_gm_core['IE'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['IL'] = array('region' => 'AP', 'currency' =>'ILS', 'weight' => 'KG_CM');
		$dhl_gm_core['IN'] = array('region' => 'AP', 'currency' =>'INR', 'weight' => 'KG_CM');
		$dhl_gm_core['IQ'] = array('region' => 'AP', 'currency' =>'IQD', 'weight' => 'KG_CM');
		$dhl_gm_core['IR'] = array('region' => 'AP', 'currency' =>'IRR', 'weight' => 'KG_CM');
		$dhl_gm_core['IS'] = array('region' => 'EU', 'currency' =>'ISK', 'weight' => 'KG_CM');
		$dhl_gm_core['IT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['JE'] = array('region' => 'AM', 'currency' =>'GBP', 'weight' => 'KG_CM');
		$dhl_gm_core['JM'] = array('region' => 'AM', 'currency' =>'JMD', 'weight' => 'KG_CM');
		$dhl_gm_core['JO'] = array('region' => 'AP', 'currency' =>'JOD', 'weight' => 'KG_CM');
		$dhl_gm_core['JP'] = array('region' => 'AP', 'currency' =>'JPY', 'weight' => 'KG_CM');
		$dhl_gm_core['KE'] = array('region' => 'AP', 'currency' =>'KES', 'weight' => 'KG_CM');
		$dhl_gm_core['KG'] = array('region' => 'AP', 'currency' =>'KGS', 'weight' => 'KG_CM');
		$dhl_gm_core['KH'] = array('region' => 'AP', 'currency' =>'KHR', 'weight' => 'KG_CM');
		$dhl_gm_core['KI'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
		$dhl_gm_core['KM'] = array('region' => 'AP', 'currency' =>'KMF', 'weight' => 'KG_CM');
		$dhl_gm_core['KN'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_gm_core['KP'] = array('region' => 'AP', 'currency' =>'KPW', 'weight' => 'LB_IN');
		$dhl_gm_core['KR'] = array('region' => 'AP', 'currency' =>'KRW', 'weight' => 'KG_CM');
		$dhl_gm_core['KV'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['KW'] = array('region' => 'AP', 'currency' =>'KWD', 'weight' => 'KG_CM');
		$dhl_gm_core['KY'] = array('region' => 'AM', 'currency' =>'KYD', 'weight' => 'KG_CM');
		$dhl_gm_core['KZ'] = array('region' => 'AP', 'currency' =>'KZF', 'weight' => 'LB_IN');
		$dhl_gm_core['LA'] = array('region' => 'AP', 'currency' =>'LAK', 'weight' => 'KG_CM');
		$dhl_gm_core['LB'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_gm_core['LC'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'KG_CM');
		$dhl_gm_core['LI'] = array('region' => 'AM', 'currency' =>'CHF', 'weight' => 'LB_IN');
		$dhl_gm_core['LK'] = array('region' => 'AP', 'currency' =>'LKR', 'weight' => 'KG_CM');
		$dhl_gm_core['LR'] = array('region' => 'AP', 'currency' =>'LRD', 'weight' => 'KG_CM');
		$dhl_gm_core['LS'] = array('region' => 'AP', 'currency' =>'LSL', 'weight' => 'KG_CM');
		$dhl_gm_core['LT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['LU'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['LV'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['LY'] = array('region' => 'AP', 'currency' =>'LYD', 'weight' => 'KG_CM');
		$dhl_gm_core['MA'] = array('region' => 'AP', 'currency' =>'MAD', 'weight' => 'KG_CM');
		$dhl_gm_core['MC'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['MD'] = array('region' => 'AP', 'currency' =>'MDL', 'weight' => 'KG_CM');
		$dhl_gm_core['ME'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['MG'] = array('region' => 'AP', 'currency' =>'MGA', 'weight' => 'KG_CM');
		$dhl_gm_core['MH'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_gm_core['MK'] = array('region' => 'AP', 'currency' =>'MKD', 'weight' => 'KG_CM');
		$dhl_gm_core['ML'] = array('region' => 'AP', 'currency' =>'COF', 'weight' => 'KG_CM');
		$dhl_gm_core['MM'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_gm_core['MN'] = array('region' => 'AP', 'currency' =>'MNT', 'weight' => 'KG_CM');
		$dhl_gm_core['MO'] = array('region' => 'AP', 'currency' =>'MOP', 'weight' => 'KG_CM');
		$dhl_gm_core['MP'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_gm_core['MQ'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['MR'] = array('region' => 'AP', 'currency' =>'MRO', 'weight' => 'KG_CM');
		$dhl_gm_core['MS'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_gm_core['MT'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['MU'] = array('region' => 'AP', 'currency' =>'MUR', 'weight' => 'KG_CM');
		$dhl_gm_core['MV'] = array('region' => 'AP', 'currency' =>'MVR', 'weight' => 'KG_CM');
		$dhl_gm_core['MW'] = array('region' => 'AP', 'currency' =>'MWK', 'weight' => 'KG_CM');
		$dhl_gm_core['MX'] = array('region' => 'AM', 'currency' =>'MXN', 'weight' => 'KG_CM');
		$dhl_gm_core['MY'] = array('region' => 'AP', 'currency' =>'MYR', 'weight' => 'KG_CM');
		$dhl_gm_core['MZ'] = array('region' => 'AP', 'currency' =>'MZN', 'weight' => 'KG_CM');
		$dhl_gm_core['NA'] = array('region' => 'AP', 'currency' =>'NAD', 'weight' => 'KG_CM');
		$dhl_gm_core['NC'] = array('region' => 'AP', 'currency' =>'XPF', 'weight' => 'KG_CM');
		$dhl_gm_core['NE'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_gm_core['NG'] = array('region' => 'AP', 'currency' =>'NGN', 'weight' => 'KG_CM');
		$dhl_gm_core['NI'] = array('region' => 'AM', 'currency' =>'NIO', 'weight' => 'KG_CM');
		$dhl_gm_core['NL'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['NO'] = array('region' => 'EU', 'currency' =>'NOK', 'weight' => 'KG_CM');
		$dhl_gm_core['NP'] = array('region' => 'AP', 'currency' =>'NPR', 'weight' => 'KG_CM');
		$dhl_gm_core['NR'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
		$dhl_gm_core['NU'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
		$dhl_gm_core['NZ'] = array('region' => 'AP', 'currency' =>'NZD', 'weight' => 'KG_CM');
		$dhl_gm_core['OM'] = array('region' => 'AP', 'currency' =>'OMR', 'weight' => 'KG_CM');
		$dhl_gm_core['PA'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_gm_core['PE'] = array('region' => 'AM', 'currency' =>'PEN', 'weight' => 'KG_CM');
		$dhl_gm_core['PF'] = array('region' => 'AP', 'currency' =>'XPF', 'weight' => 'KG_CM');
		$dhl_gm_core['PG'] = array('region' => 'AP', 'currency' =>'PGK', 'weight' => 'KG_CM');
		$dhl_gm_core['PH'] = array('region' => 'AP', 'currency' =>'PHP', 'weight' => 'KG_CM');
		$dhl_gm_core['PK'] = array('region' => 'AP', 'currency' =>'PKR', 'weight' => 'KG_CM');
		$dhl_gm_core['PL'] = array('region' => 'EU', 'currency' =>'PLN', 'weight' => 'KG_CM');
		$dhl_gm_core['PR'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_gm_core['PT'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['PW'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_gm_core['PY'] = array('region' => 'AM', 'currency' =>'PYG', 'weight' => 'KG_CM');
		$dhl_gm_core['QA'] = array('region' => 'AP', 'currency' =>'QAR', 'weight' => 'KG_CM');
		$dhl_gm_core['RE'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['RO'] = array('region' => 'EU', 'currency' =>'RON', 'weight' => 'KG_CM');
		$dhl_gm_core['RS'] = array('region' => 'AP', 'currency' =>'RSD', 'weight' => 'KG_CM');
		$dhl_gm_core['RU'] = array('region' => 'AP', 'currency' =>'RUB', 'weight' => 'KG_CM');
		$dhl_gm_core['RW'] = array('region' => 'AP', 'currency' =>'RWF', 'weight' => 'KG_CM');
		$dhl_gm_core['SA'] = array('region' => 'AP', 'currency' =>'SAR', 'weight' => 'KG_CM');
		$dhl_gm_core['SB'] = array('region' => 'AP', 'currency' =>'SBD', 'weight' => 'KG_CM');
		$dhl_gm_core['SC'] = array('region' => 'AP', 'currency' =>'SCR', 'weight' => 'KG_CM');
		$dhl_gm_core['SD'] = array('region' => 'AP', 'currency' =>'SDG', 'weight' => 'KG_CM');
		$dhl_gm_core['SE'] = array('region' => 'EU', 'currency' =>'SEK', 'weight' => 'KG_CM');
		$dhl_gm_core['SG'] = array('region' => 'AP', 'currency' =>'SGD', 'weight' => 'KG_CM');
		$dhl_gm_core['SH'] = array('region' => 'AP', 'currency' =>'SHP', 'weight' => 'KG_CM');
		$dhl_gm_core['SI'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['SK'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['SL'] = array('region' => 'AP', 'currency' =>'SLL', 'weight' => 'KG_CM');
		$dhl_gm_core['SM'] = array('region' => 'EU', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['SN'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_gm_core['SO'] = array('region' => 'AM', 'currency' =>'SOS', 'weight' => 'KG_CM');
		$dhl_gm_core['SR'] = array('region' => 'AM', 'currency' =>'SRD', 'weight' => 'KG_CM');
		$dhl_gm_core['SS'] = array('region' => 'AP', 'currency' =>'SSP', 'weight' => 'KG_CM');
		$dhl_gm_core['ST'] = array('region' => 'AP', 'currency' =>'STD', 'weight' => 'KG_CM');
		$dhl_gm_core['SV'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_gm_core['SY'] = array('region' => 'AP', 'currency' =>'SYP', 'weight' => 'KG_CM');
		$dhl_gm_core['SZ'] = array('region' => 'AP', 'currency' =>'SZL', 'weight' => 'KG_CM');
		$dhl_gm_core['TC'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_gm_core['TD'] = array('region' => 'AP', 'currency' =>'XAF', 'weight' => 'KG_CM');
		$dhl_gm_core['TG'] = array('region' => 'AP', 'currency' =>'XOF', 'weight' => 'KG_CM');
		$dhl_gm_core['TH'] = array('region' => 'AP', 'currency' =>'THB', 'weight' => 'KG_CM');
		$dhl_gm_core['TJ'] = array('region' => 'AP', 'currency' =>'TJS', 'weight' => 'KG_CM');
		$dhl_gm_core['TL'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_gm_core['TN'] = array('region' => 'AP', 'currency' =>'TND', 'weight' => 'KG_CM');
		$dhl_gm_core['TO'] = array('region' => 'AP', 'currency' =>'TOP', 'weight' => 'KG_CM');
		$dhl_gm_core['TR'] = array('region' => 'AP', 'currency' =>'TRY', 'weight' => 'KG_CM');
		$dhl_gm_core['TT'] = array('region' => 'AM', 'currency' =>'TTD', 'weight' => 'LB_IN');
		$dhl_gm_core['TV'] = array('region' => 'AP', 'currency' =>'AUD', 'weight' => 'KG_CM');
		$dhl_gm_core['TW'] = array('region' => 'AP', 'currency' =>'TWD', 'weight' => 'KG_CM');
		$dhl_gm_core['TZ'] = array('region' => 'AP', 'currency' =>'TZS', 'weight' => 'KG_CM');
		$dhl_gm_core['UA'] = array('region' => 'AP', 'currency' =>'UAH', 'weight' => 'KG_CM');
		$dhl_gm_core['UG'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		$dhl_gm_core['US'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_gm_core['UY'] = array('region' => 'AM', 'currency' =>'UYU', 'weight' => 'KG_CM');
		$dhl_gm_core['UZ'] = array('region' => 'AP', 'currency' =>'UZS', 'weight' => 'KG_CM');
		$dhl_gm_core['VC'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_gm_core['VE'] = array('region' => 'AM', 'currency' =>'VEF', 'weight' => 'KG_CM');
		$dhl_gm_core['VG'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_gm_core['VI'] = array('region' => 'AM', 'currency' =>'USD', 'weight' => 'LB_IN');
		$dhl_gm_core['VN'] = array('region' => 'AP', 'currency' =>'VND', 'weight' => 'KG_CM');
		$dhl_gm_core['VU'] = array('region' => 'AP', 'currency' =>'VUV', 'weight' => 'KG_CM');
		$dhl_gm_core['WS'] = array('region' => 'AP', 'currency' =>'WST', 'weight' => 'KG_CM');
		$dhl_gm_core['XB'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
		$dhl_gm_core['XC'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
		$dhl_gm_core['XE'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'LB_IN');
		$dhl_gm_core['XM'] = array('region' => 'AM', 'currency' =>'EUR', 'weight' => 'LB_IN');
		$dhl_gm_core['XN'] = array('region' => 'AM', 'currency' =>'XCD', 'weight' => 'LB_IN');
		$dhl_gm_core['XS'] = array('region' => 'AP', 'currency' =>'SIS', 'weight' => 'KG_CM');
		$dhl_gm_core['XY'] = array('region' => 'AM', 'currency' =>'ANG', 'weight' => 'LB_IN');
		$dhl_gm_core['YE'] = array('region' => 'AP', 'currency' =>'YER', 'weight' => 'KG_CM');
		$dhl_gm_core['YT'] = array('region' => 'AP', 'currency' =>'EUR', 'weight' => 'KG_CM');
		$dhl_gm_core['ZA'] = array('region' => 'AP', 'currency' =>'ZAR', 'weight' => 'KG_CM');
		$dhl_gm_core['ZM'] = array('region' => 'AP', 'currency' =>'ZMW', 'weight' => 'KG_CM');
		$dhl_gm_core['ZW'] = array('region' => 'AP', 'currency' =>'USD', 'weight' => 'KG_CM');
		
	}
	$dhl_gm = new dhl_gm_parent();
}
