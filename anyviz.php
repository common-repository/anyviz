<?php
/*
Plugin Name: AnyViz
Description: This Plugin connects to the AnyViz Cloud via REST API and shows Tag values by Shortcodes
Author: Mirasoft GmbH & Co. KG
Author URI: https://www.anyviz.io
Version: 1.0.1
*/

if ( !defined( 'ABSPATH' ) ) exit;

/*** SHORTCODE ***/

add_shortcode('anyviz_tag_value', 'anyviz_request_tag_value');

function anyviz_request_tag_value( $atts ) {
    $params = shortcode_atts( array(
		'id' => null,
        'digits' => null
	), $atts );

    // Api Key
    $api_key = get_option('anyviz_api_key');

    // Instanz Id
    $instance = anyviz_get_option_or_default('anyviz_instance', 'portal.anyviz.io');
    if (substr( $instance, 0, 4 ) !== 'http')
        $instance = 'https://' . $instance;
    if (substr( $instance, strlen($instance) - 1, 1 ) !== '/')
        $instance = $instance . '/';
    
    // Tag Id
    $tag_id = $params['id'];
    if (!is_numeric($tag_id))
        return '?';

    $url = $instance . 'api/TagValue/' . $tag_id;

    $args = array(
        'headers' => array(
            'ApiKey' => $api_key
        ),
        'body' => array()
    );

    $response = wp_remote_get($url, $args);
    $response_code = wp_remote_retrieve_response_code($response);
    
    if($response_code == 200) {
        $body = wp_remote_retrieve_body($response);
        return anyviz_get_value($body, $params);
    } else {
        return '?'; // 'Response: ' . $response_code;
    }
}

function anyviz_get_value( $result,  $params ) {
    if (!is_numeric($result))
        return htmlspecialchars($result);
    
    $digits = $params['digits'];
    if (!is_numeric($digits))
        $digits = anyviz_get_option_or_default('anyviz_digits', 2);
    
    $separators = anyviz_get_option_or_default('anyviz_separators', 0);
    $sep_decimal = $separators == 1 ? '.' : ',';
    $sep_thousands = $separators == 1 ? ',' : '.';

    return number_format($result, $digits, $sep_decimal, $sep_thousands);
}

function anyviz_get_option_or_default(string $key, $default) {
    $result = get_option($key);
    if ($result === '')
        $result = $default;
    return $result;
}


/*** LINK TO SETTINGS ***/

add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'anyviz_add_settings_link');

function anyviz_add_settings_link( $links ) {
	$links[] = '<a href="' .
		admin_url( 'options-general.php?page=anyviz%2Fanyviz.php' ) .
		'">' . __('Settings') . '</a>';
	return $links;
}


/*** SETTINGS ***/

add_action('admin_menu', 'anyviz_create_menu');

function anyviz_create_menu() {
	//create new top-level menu
	add_options_page('AnyViz Settings Page', 'AnyViz', 'administrator', __FILE__, 'anyviz_create_settings_page' , plugins_url('/content/icon.png', __FILE__) );

	//call register settings function
	add_action( 'admin_init', 'anyviz_register_settings' );
}

function anyviz_register_settings() {
	//register settings
	register_setting( 'anyviz-settings-group', 'anyviz_api_key' );
	register_setting( 'anyviz-settings-group', 'anyviz_instance', array( 'description' => 'Optional', 'default' => 'portal.anyviz.io' ) );
    register_setting( 'anyviz-settings-group', 'anyviz_digits', array( 'type' => 'number', 'default' => 2 ) ); 
    register_setting( 'anyviz-settings-group', 'anyviz_separators', array( 'type' => 'number', 'default' => 0 ) ); 
}

function anyviz_create_settings_page() {
?>
<div class="wrap">
<h1>AnyViz <?php echo __('Settings'); ?></h1>

<form method="post" action="options.php">
    <?php settings_fields( 'anyviz-settings-group' ); ?>
    <?php do_settings_sections( 'anyviz-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">API Token</th>
        <td><input type="text" name="anyviz_api_key" class="regular-text" value="<?php echo esc_attr( get_option('anyviz_api_key') ); ?>"></td>
        </tr>
         
        <tr valign="top">
        <th scope="row">AnyViz Instance</th>
        <td><input type="text" name="anyviz_instance" class="regular-text" value="<?php echo esc_attr( get_option('anyviz_instance') ); ?>"></td>
        </tr>

        <tr valign="top">
        <th scope="row">Number Format</th>
        <td>
            <label><input id="anyviz-format-digits" type="number" step="1" min="0" max="8" onchange="anyvizFormatPreview()" name="anyviz_digits" style="margin-bottom: 0.8rem;" value="<?php echo esc_attr( get_option('anyviz_digits') ); ?>"> Digits</label><br>
            <fieldset>
                <label><input id="anyviz-format-separator" type="radio" onchange="anyvizFormatPreview()" name="anyviz_separators" value="0" <?php checked(0, get_option('anyviz_separators'), true); ?>><span> Decimal Separator: <b>,</b> | Thousands Separator: <b>.</b></span></label><br>
                <label><input type="radio" onchange="anyvizFormatPreview()" name="anyviz_separators" value="1" <?php checked(1, get_option('anyviz_separators'), true); ?>><span> Decimal Separator: <b>.</b> | Thousands Separator: <b>,</b></span></label><br>
            </fieldset>
            <span><b>Preview: </b><span id="anyviz-format-preview"></span></span>
        </td>
        </tr>
    </table>
    
    <script>
        function anyvizFormatPreview() {
            var sep = document.getElementById('anyviz-format-separator');
            var text = sep.checked ? '1.000' : '1,000';

            var digits = parseInt(document.getElementById('anyviz-format-digits').value);
            if (isNaN(digits))
                digits = 2;
            if (digits > 0) {
                text += sep.checked ? ',' : '.';
                while(digits-- > 0)
                    text += '0';
            }

            var prev = document.getElementById('anyviz-format-preview');
            prev.innerHTML = text;
        }
        anyvizFormatPreview();
    </script>

    <?php submit_button(); ?>

</form>
</div>
<?php 
}