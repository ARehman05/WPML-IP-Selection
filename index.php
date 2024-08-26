<?php
function workever_get_user_ip(){
	$ipaddress = '';
	if ( isset( $_SERVER['HTTP_CLIENT_IP']) ){
		$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
	}else if( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ){
		$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
	}else if( isset( $_SERVER['HTTP_X_FORWARDED'] ) ){
		$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
	}else if( isset( $_SERVER['HTTP_FORWARDED_FOR'] ) ){
		$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
	}else if( isset( $_SERVER['HTTP_FORWARDED'] ) ){
		$ipaddress = $_SERVER['HTTP_FORWARDED'];
	}else if( isset( $_SERVER['REMOTE_ADDR'] ) ){
		$ipaddress = $_SERVER['REMOTE_ADDR'];
	}else{
		$ipaddress = '';
	}
	return $ipaddress;
}

function workever_adjust_wpml_country_codes($languages) {
    $adjusted_languages = array();
    foreach ($languages as $code => $info) {
        // Custom mapping to match API country codes
        switch (strtoupper($code)) {
            case 'AUS':
                $adjusted_code = 'AU';
                break;
            case 'SA':
                $adjusted_code = 'ZA';
                break;
            case 'CAN':
                $adjusted_code = 'CA';
                break;
            default:
                $adjusted_code = strtoupper($code);
                break;
        }    
        $adjusted_languages[$adjusted_code] = $info;
    }
    return $adjusted_languages;
}

// Active countries in our Site
function workever_get_active_wpml_languages() {
    $languages = apply_filters('wpml_active_languages', NULL, 'orderby=id&order=desc');
	return workever_adjust_wpml_country_codes($languages);
}

// European Union 27 country array
function workever_get_eu_countries() {
    return array(
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
    );
}

// IPINFO API function
function workever_get_ipinfo_data($ip_address) {
	$token = '#########';
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, "https://ipinfo.io/{$ip_address}?token={$token}");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($ch);
	curl_close($ch);
	
	$data = json_decode($response, true);

	return $data;
}

// Redirect Site based on user IP
function workever_wpml_redirect_based_on_ip() {
	$url = $_SERVER['REQUEST_URI'];
    if ( ( !empty($_SERVER['HTTP_USER_AGENT']) and preg_match('~(bot|crawl)~i', $_SERVER['HTTP_USER_AGENT']) ) || wp_doing_ajax() || is_admin() || strpos( $url, "/robots.txt" ) !== false || strpos( $url, "/safetygate-wp-login.php" ) !== false || strpos( $url, "/xmlrpc.php" ) !== false || strpos( $url, "/wp-json" ) !== false || strpos( $url, "/?wc-api=wc_stripe" ) !== false || current_user_can('administrator') || strpos($url,"/wc-auth") !== false || strpos($url,"/wc-ajax") !== false || strpos($url,"/wc-api") !== false || strpos( $url, "/wp-admin" ) !== false || strpos( $url, "/wp-login.php" ) !== false || strpos( $url, "/wp-activate.php" ) !== false || strpos( $url, "/wp-signup.php" ) !== false || strpos( $url, "/lost-password" ) !== false) return;

	$active_regions		= workever_get_active_wpml_languages();
    $default_region		= 'us';
    $fallback_region	= 'gb';
    $user_ip			= workever_get_user_ip();
	$current_language	= apply_filters('wpml_current_language', null);

	if( '' == $user_ip ){
		if( 'gb' != $current_language ){
			wp_redirect( site_url() );
			exit;
		}
		return false;
	}
	
	if(isset($_COOKIE['user_default_region_country'])) {
		$country_code	= $_COOKIE['user_default_region_country'];
	}else{
		//$ipinfo_data	= workever_get_ipinfo_data($user_ip);
		//$country_code	= isset($ipinfo_data['country']) ? $ipinfo_data['country'] : null;
		$country_code	= do_shortcode('[useriploc type="countrycode"]');
	}

   $redirect_url = $fallback_region; // Default fallback
   if ($country_code) {
        $eu_countries = workever_get_eu_countries();
        if (in_array($country_code, $eu_countries)) {
            $redirect_url = $active_regions['EUR']['code'];
        } elseif (array_key_exists($country_code, $active_regions)) {
            $redirect_url = $active_regions[$country_code]['code'];	
        } else {
            $redirect_url = $default_region;
        }

		$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
		$serverName = $_SERVER['SERVER_NAME'];
		$requestUri = $_SERVER['REQUEST_URI'];
		$currentUrl = $protocol . $serverName . $requestUri;
		$custom_cookie = 'user_region_redirected_'.$current_language;

		if(isset($_GET['lng'])) {
			setcookie($custom_cookie, 'true', time() + (24 * 60 * 60 * 30), '/');
			$redirect_url = apply_filters('wpml_permalink', $currentUrl, $current_language);
			wp_redirect ($redirect_url);
			return;
		}

		if( isset($_COOKIE[$custom_cookie]) ) {
			$redirect_url = apply_filters('wpml_permalink', $currentUrl, $current_language);
			wp_redirect ($redirect_url);
			return;
		}

		if(isset($_COOKIE['user_default_region']) && $_COOKIE['user_default_region'] == $current_language) {
			$redirect_url = apply_filters('wpml_permalink', $currentUrl, $redirect_url);
			wp_redirect ($redirect_url);
			return;
		}

		// Set a custom cookie to store the user's default region
		setcookie('user_default_region', $redirect_url, time() + (24 * 60 * 60 * 30), '/');
	   	setcookie('user_default_region_country', $country_code, time() + (24 * 60 * 60 * 30), '/');
		setcookie('wp-wpml_current_language', $redirect_url, time() + (24 * 60 * 60 * 30), '/');


		$redirect_url = apply_filters('wpml_permalink', $currentUrl, $redirect_url);

		wp_redirect( $redirect_url );
		exit;
	}
}
add_action('init', 'workever_wpml_redirect_based_on_ip');
