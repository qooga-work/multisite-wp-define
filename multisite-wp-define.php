<?php
/*
Plugin Name: Use WP_HOME and WP_SITEURL in MultiSite
Plugin URI: https://qooga.jb-jk.net/use-wp_home-and-wp_siteurl-in-multisite/
Description: Allows wp_options values to be overwritten in wp-config.php for MultiSite
Author: Tam Oya
Version: 1.0
Author URI: https://qooga.jb-jk.net
License: GPL2
*/
class Use_Wp_Define_In_MultiSite
{
    public static $package = "use-wp-define-in-multiSite";
    public static $version = "1.0.0";
    public static $title   = "Use WP_HOME and WP_SITEURL in MultiSite";

    public static function register_hooks()
    {
        add_filter('option_siteurl', array(__CLASS__, 'config_wp_siteurl'));
        add_filter('option_home', array(__CLASS__, 'config_wp_home'));
    }

    public static function config_wp_siteurl($url = '')
    {
        if (is_multisite()):
            global $blog_id, $current_site;
        $cur_blog_id = defined('BLOG_ID_CURRENT_SITE')? BLOG_ID_CURRENT_SITE : 1;
        $key = ($blog_id!=$cur_blog_id)? $blog_id.'_' : '';
        $constant = 'WP_'.$key.'SITEURL';
        if (defined($constant)) {
            return untrailingslashit(constant($constant));
        }
        endif;
        return $url;
    }

    public static function config_wp_home($url = '')
    {
        if (is_multisite()):
            global $blog_id;
        $cur_blog_id = defined('BLOG_ID_CURRENT_SITE')? BLOG_ID_CURRENT_SITE : 1;
        $key = ($blog_id!=$cur_blog_id)? $blog_id.'_' : '';
        $constant = 'WP_'.$key.'HOME';
        if (defined($constant)) {
            return untrailingslashit(constant($constant));
        }
        endif;
        return $url;
    }
}

Use_Wp_Define_In_MultiSite::register_hooks();