<?php
/***** Enable 'Use WP_HOME and WP_SITEURL in MultiSite' plug-in *****
 *
 * Added "define('SUNRISE', 'on');" to "wp_config.php"
 * Copy or move "sunrise.php" to the "wp-content" directory
 *
 ***/

// check plugin
function isPluginActive( $plugin ) {
    return in_array( $plugin, (array) get_option( 'active_plugins', array() ) ) || isPluginActiveForNetwork( $plugin );
}
function isPluginActiveForNetwork( $plugin ) {
    $plugins = get_site_option('active_sitewide_plugins');
    if ( isset($plugins[$plugin]) )
        return true;

    return false;
}
if (!defined('LOCAL_FLAG')) {
    define('LOCAL_FLAG', false);
}




// wordpress-mu-domain-mapping (WordPress MU Domain Mapping)
$plugin_name = 'wordpress-mu-domain-mapping/domain_mapping.php';
if (isPluginActive($plugin_name) && LOCAL_FLAG == true) {
    // The domain mapping plugin only works if the site is installed in /. This is a limitation of how virtual servers work and is very difficult to work around.
    // Forcibly stop
    $plugins = get_site_option('active_sitewide_plugins');
    unset($plugins[$plugin_name]);
    update_site_option('active_sitewide_plugins', $plugins );
}elseif(isPluginActive($plugin_name) ){
	if ( !defined( 'SUNRISE_LOADED' ) )
        define( 'SUNRISE_LOADED', 1 );

    if( !defined('BLOG_ID_TARGET_SITE') ){
        define('BLOG_ID_TARGET_SITE', (integer)SITE_ID_CURRENT_SITE);
    }
    
    $wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';
    $dm_domain = $_SERVER[ 'HTTP_HOST' ];

    if (LOCAL_FLAG == true) {
        $current_blog = $wpdb->get_row("SELECT * FROM {$wpdb->blogs} WHERE blog_id = ".BLOG_ID_TARGET_SITE." LIMIT 1");
        $dm_domain = $current_blog->domain;
    }

    if( ( $nowww = preg_replace( '|^www\.|', '', $dm_domain ) ) != $dm_domain )
        $where = $wpdb->prepare( 'domain IN (%s,%s)', $dm_domain, $nowww );
    else
        $where = $wpdb->prepare( 'domain = %s', $dm_domain );

    $wpdb->suppress_errors();

    $domain_mapping_id = $wpdb->get_var( "SELECT blog_id FROM {$wpdb->dmtable} WHERE {$where} ORDER BY CHAR_LENGTH(domain) DESC LIMIT 1" );
    $wpdb->suppress_errors( false );
    if( $domain_mapping_id ) {
        $current_blog = $wpdb->get_row("SELECT * FROM {$wpdb->blogs} WHERE blog_id = '$domain_mapping_id' LIMIT 1");
        $current_blog->domain = $dm_domain;
        $current_blog->path = '/';
        $blog_id = $domain_mapping_id;
        $site_id = $current_blog->site_id;
        if (!defined('COOKIE_DOMAIN')) {
            define('COOKIE_DOMAIN', $dm_domain);
        }

        $current_site = $wpdb->get_row( "SELECT * from {$wpdb->site} WHERE id = '{$current_blog->site_id}' LIMIT 0,1" );
        $current_site->blog_id = $wpdb->get_var( "SELECT blog_id FROM {$wpdb->blogs} WHERE domain='{$current_site->domain}' AND path='{$current_site->path}'" );
        if ( function_exists( 'get_site_option' ) )
            $current_site->site_name = get_site_option( 'site_name' );
        elseif ( function_exists( 'get_current_site_name' ) )
            $current_site = get_current_site_name( $current_site );
        if (!defined('DOMAIN_MAPPING')) {
            define('DOMAIN_MAPPING', 1);
        }
    }
} // wordpress-mu-domain-mapping





// multisite-wp-define (Use WP_HOME and WP_SITEURL in MultiSite)
$plugin_name = 'multisite-wp-define/multisite-wp-define.php';
if ( isPluginActive($plugin_name) && LOCAL_FLAG == true ) {
    if (SUNRISE !== 'on') {
        return;
    }

    if (!defined('DOMAIN_CURRENT_SITE')) {
        define('DOMAIN_CURRENT_SITE', (isset($_SERVER['HTTP_HOST']) && strlen($_SERVER['HTTP_HOST'])>0)? $_SERVER['HTTP_HOST'] :$_SERVER['SERVER_NAME']);
    }
    if (!defined('SITE_ID_CURRENT_SITE')) {
        define('SITE_ID_CURRENT_SITE', 1);
    }
    if (!defined('BLOG_ID_CURRENT_SITE')) {
        define('BLOG_ID_CURRENT_SITE', 1);
    }
    if (!defined('PATH_CURRENT_SITE')) {
        define('PATH_CURRENT_SITE', '/');
    }

    if (!defined('COOKIE_DOMAIN')) {
        define('COOKIE_DOMAIN', DOMAIN_CURRENT_SITE);
    }

    if( !defined('BLOG_ID_TARGET_SITE') ){
        define('BLOG_ID_TARGET_SITE', (integer)SITE_ID_CURRENT_SITE);
    }

    $tmp = [];

    $current_site = new stdClass;
    $current_site->blog_id = (integer)BLOG_ID_CURRENT_SITE;
    $current_site->site_id = (integer)SITE_ID_CURRENT_SITE;
    $current_site->domain = DOMAIN_CURRENT_SITE;
    $current_site->path = PATH_CURRENT_SITE;
    $current_site->public = 1;
    $current_site->id = $current_site->blog_id;

    $current_blog = $current_site;

    global $wpdb;

    $tmp['target_site'] = (integer)BLOG_ID_TARGET_SITE;
    if( $tmp['target_site'] < 1 ){
        $tmp['target_site'] = $current_site->blog_id;
    }

    $tmp['protocol'] = 'http://';
    if (isset($_SERVER["SERVER_PORT"])) {
        $tmp['protocol'] = ($_SERVER['SERVER_PORT'] == 443)? 'https://' :'http://';
    }
    $tmp['url'] = $tmp['protocol'].DOMAIN_CURRENT_SITE.PATH_CURRENT_SITE;
    if (mb_substr($tmp['url'], -1) == '/') {
        $tmp['url'] = substr($tmp['url'], 0, -1);
    }

    $tmp['path'] = '/';
    if (isset($_SERVER["REQUEST_URI"])) {
        $tmp['path'] = explode(PATH_CURRENT_SITE, $_SERVER["REQUEST_URI"], 2);
        $tmp['path'] = '/'.$tmp['path'][1];
    }


    // content_url() support
    if (!defined('WP_CONTENT_URL')) {
        define('WP_CONTENT_URL', $tmp['url'].'/wp-content');
    }

    $sql = "SELECT * FROM {$wpdb->blogs}";
    $tmp['blogs'] = $wpdb->get_results($sql);
    if (count($tmp['blogs']) > 0) {
        foreach ($tmp['blogs'] as $t_key => $t_val) {
            $tmp['set_url'] = $tmp['url'].$t_val->path;
            $tmp['set_key_no'] = $t_val->blog_id.'_';
            if ($t_val->blog_id == 1) {
                $tmp['set_key_no'] = '';
            }
            $tmp['set_key'] = 'WP_'.$tmp['set_key_no'].'HOME';
            if (!defined($tmp['set_key'])) {
                define($tmp['set_key'], $tmp['set_url']);
            }
            $tmp['set_key'] = 'WP_'.$tmp['set_key_no'].'SITEURL';
            if (!defined($tmp['set_key'])) {
                define($tmp['set_key'], $tmp['set_url']);
            }

            eval('$tmp["set_url"] = '.$tmp['set_key'].';');
            $tmp["set_url"] = parse_url($tmp["set_url"]);

            if( $t_val->blog_id != $current_site->blog_id ){
                if(
                    ($t_val->blog_id == $tmp['target_site']) ||
                    ($t_val->path === substr($tmp['path'], 0, strlen($t_val->path)))
                 ){
//                    switch_to_blog($tmp['target_site']);
                     $tmp['path'] = $t_val->path;
                     $blog_id = $t_val->blog_id;
                     $current_blog = $t_val;
                     $current_blog = new WP_Site($t_val);
                     $current_blog->domain = $tmp["set_url"]["host"];
                 }
            }

        }
    }

    unset($tmp);
} // multisite-wp-define