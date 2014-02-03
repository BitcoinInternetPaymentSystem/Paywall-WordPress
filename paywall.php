<?php
$bips_invoice_api_key = 'your_invoice_api_key';
$price_per_article = 1; // $1 USD
$currency = 'USD';
/*
Plugin Name: Bitcoin Paywall
Plugin URI: https://bips.me/plugins#paywall
Description: Hide parts of the content of your posts or pages until paid by surrounding it with [paywall] and [/paywall] shortcode.
Version: 1.0
Author: BIPS
Author URI: https://bips.me/

Copyright: © 2014 BIPS.
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/
	register_activation_hook( __FILE__, 'paywall_install' );

	function paywall_install()
	{
		global $wpdb;

		$table_name = $wpdb->prefix . "bitcoin_paywall"; 

		$sql = "CREATE TABLE $table_name (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		article_id int(11) unsigned NOT NULL,
		article_number int(11) unsigned NOT NULL,
		ip int(11) unsigned NOT NULL,
		UNIQUE KEY id (id)
		);";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$wpdb->insert( $table_name, array( 'article_id' => 0, 'article_number' => 0, 'ip' => time() ) );
	}

	wp_enqueue_script('jquery');

	add_action('init', 'paywall_init');
	function paywall_init()
	{
		global $wpdb;

		$table_name = $wpdb->prefix . "bitcoin_paywall";
		$myrows = $wpdb->get_results("SELECT ip FROM $table_name WHERE article_id = 0 AND article_number = 0 ORDER BY id ASC LIMIT 1;");

		if (isset($_GET['action']) && $_GET['action'] == 'bitcoin_paywall_pay')
		{
			global $bips_invoice_api_key;
			global $price_per_article;
			global $currency;

			$fields = array(
				'price' => $price_per_article,
				'currency' => $currency,
				'item' => 'Paywall'
			);

			$ch = curl_init();
			curl_setopt_array($ch, array(
			CURLOPT_URL => 'https://bips.me/api/v1/invoice',
			CURLOPT_USERPWD => $bips_invoice_api_key,
			CURLOPT_POSTFIELDS => http_build_query($fields) . '&custom=' . json_encode(array(
				'article_id' => intval($_GET['cmd']),
				'article_number' => intval($_GET['arg']),
				'returnurl' => rawurlencode($_SERVER["HTTP_REFERER"] . '#post-' . intval($_GET['cmd'])),
				'callbackurl' => rawurlencode(get_home_url() . '/?action=bitcoin_paywall_unlock'),
				'secret' => 'CA2B9CEDCA142E112CA8999' . @$myrows[0]->ip
			)),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC));
			$url = curl_exec($ch);
			curl_close($ch);

			header('Location: ' . $url);
			exit;
		}
		else if (isset($_GET['action']) && $_GET['action'] == 'bitcoin_paywall_unlock')
		{
			$BIPS = $_POST;
			$hash = hash('sha512', $BIPS['transaction']['hash'] . 'CA2B9CEDCA142E112CA8999' . @$myrows[0]->ip);

			if ($BIPS['hash'] == $hash && $BIPS['status'] == 1)
			{
				$ip = long2ip(ip2long($_SERVER['REMOTE_ADDR']));
				$ip = sprintf("%u", ip2long($ip));

				$table_name = $wpdb->prefix . "bitcoin_paywall";
				$rows_affected = $wpdb->insert( $table_name, array( 'article_id' => $BIPS['custom']['article_id'], 'article_number' => $BIPS['custom']['article_number'], 'ip' => $ip ) );
			}

			exit;
		}
		else
		{
			wp_register_script('paywall-wordpress-js', WP_PLUGIN_URL . '/Paywall-WordPress/js.js');
			wp_enqueue_script('paywall-wordpress-js');
		}
	}

	// Add styling
	add_action('wp_head', 'paywall_head');
	function paywall_head()
	{
		$str_css_url = WP_PLUGIN_URL . "/Paywall-WordPress/style.css";
		echo '<link rel="stylesheet" href="' . $str_css_url . '" type="text/css" media="screen" />'."\n";
	}

	// Main functionality
	$article_number = 0;
	add_shortcode('paywall', 'paywall');
	function paywall($atts, $content = null)
	{
		global $bips_invoice_api_key;
		global $article_number;

		if ($atts['text'] != '')
		{
			$str_unlock_link = $atts['text'];
		}
		else
		{
			$str_unlock_link = 'Unlock Content';
		}

		if ($bips_invoice_api_key == null || $bips_invoice_api_key == '' || $bips_invoice_api_key == 'your_invoice_api_key') {
			$str_unlock_link = 'To use paywall you must get an invoice API key.';
		}

		$str_return = '';

		$article_id = get_the_ID();

		if (paywall_check($article_id, $article_number))
		{
			$str_return .= do_shortcode($content);
		}
		else
		{
			$str_return .= '<div class="paywall-wrap">';
			$str_return .= '	<a class="paywall-unlock" id="paywall-' . $article_number . '" unlocklink-text="' . $str_unlock_link . '">' . $str_unlock_link . '</a>';
			$str_return .= '	<div class="paywall" status="invisible">';
			$str_return .= '		<a href="' . get_home_url() . '/?action=bitcoin_paywall_pay&cmd=' . $article_id . '&arg=' . $article_number . '">&#10095; &#10095; &#10095;</a>';
			$str_return .= '	</div>';
			$str_return .= '</div>';
		}

		$article_number++;

		return $str_return;
	}

	function paywall_check($article_id, $article_number)
	{
		global $wpdb;

		$ip = long2ip(ip2long($_SERVER['REMOTE_ADDR']));
		$ip = sprintf("%u", ip2long($ip));

		$table_name = $wpdb->prefix . "bitcoin_paywall"; 

		$myrows = $wpdb->get_results("SELECT id FROM $table_name WHERE article_id = " . $article_id . " AND article_number = " . $article_number . " AND ip = " . $ip . " LIMIT 1;");

		if (@$myrows[0]->id > 0)
		{
			return true;
		}

		return false;
	}
?>
