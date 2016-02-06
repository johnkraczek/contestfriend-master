<?php
/*
Plugin Name: contestfriend for WordPress
Plugin URI: http://www.contestfriend.com
Description: Create your own contests and sweepstakes in seconds with customizable designs and multitude of features ranging from referrals, automatic random winner pick to integration with your favorite mailing list service like Mailchimp, AWeber, CampaignMonitor and GetResponse.
Version: 1.0
Author: Nick Berry
Author URI: http://www.contestfriend.com/plugin/
License: GPLv2+
*/

/**
* Application entry point. Contains plugin startup class that loads on <i>plugins_loaded</i> action.
* @package ch
*/

 
if(!class_exists('cf_Manager'))
{
    register_activation_hook(__FILE__, array('cf_Manager', 'install'));
    register_deactivation_hook(__FILE__, array('cf_Manager', 'deactivate'));
    register_uninstall_hook(__FILE__, array('cf_Manager', 'uninstall'));

    add_action('plugins_loaded', 'init_contest_friend');
    
    require('models/model-contest.php');
    require('contest-widget.php');
    require('models/model-participant.php');
    
    if(is_admin())
    {
        require('tables/table-contests.php');
        require('tables/table-participants.php');
        require('pages/page-list.php');
        require('pages/page-contest.php');
        require('pages/page-participants.php');
    }
    
    /**
    * Main startup and manager class.
    * Called on <i>plugins_loaded</i> action.
    * <ul>
    * <li>setting url and path values</li>
    * <li>install, deactivate and uninstall hooks</li>
    * <li>updating the admin menu</li>
    * <li>processing requests</li>
    * <li>processing automatic contest expiry</li>
    * <li>registering and enqueueing styles and scripts</li>
    * </ul>
    * @package ch
    */
    class cf_Manager
    {
        /**
        * Participant SQL table name.
        */
        const sqlt_participant = 'cf_participants';
        /**
        * Participant meta SQL table name.
        */
        const sqlt_participant_meta = 'cf_participants_meta';
        /**
        * Post type name.
        */
        const post_type = 'contestfriend';
        
        /**
        * Plugin directory, set in constructor.
        * @access public
        * @var string
        */
        public static $plugin_dir;
        /**
        * Plugin url, set in constructor.
        * 
        * @access public
        * @var string
        */
        public static $plugin_url;
        
        /**
        * Class constructor.
        * Sets plugin url and directory and adds hooks to <i>init</i>, <i>admin_menu</i>, <i>contestfriend_autoexpiry</i> actions.
        */
        function __construct()
        {
            self::$plugin_dir = plugin_dir_path(__FILE__);
            self::$plugin_url = plugins_url('', __FILE__);
                   
            add_action('init', array(&$this, 'init'));
            
            add_action('admin_menu', array(&$this, 'init_menu'), 9);
            
            add_action('contestfriend_autoexpiry', array(&$this, 'process_autoexpiry'));

            add_filter('cf_description', 'do_shortcode');
        }

        /**
        * Contest autoexpiry method.
        * Called on <i>contestfriend_autoexpiry</i> hook, that was registered by <i>wp_schedule_event()</i> on plugin install.
        * Method checks for active contests that have <i>cf_autoselect_winner</i> attribute set, calls
        * contest object methods to pick the desired number of winners and sets the contest to <i>winners_picked</i> 
        * status.
        */
        function process_autoexpiry()
        {
            $contests = cf_Contest::get_all('','','','','active');
            foreach($contests as $contest)
            {
                if($contest->is_expired())
                {
                    if(isset($contest->cf_autoselect_winner) && $contest->cf_autoselect_winner=='1')
                    {
                        $winners_num = 1;
                        if(isset($contest->cf_winners_num) && is_numeric($contest->cf_winners_num) && $contest->cf_winners_num>0)
                            $winners_num = $contest->cf_winners_num;
                        
                        $current_winners = $contest->get_current_winner_num();
                        $pick_num = intval($winners_num) - intval($current_winners);
                        if($pick_num>0)
                            $contest->pick_winners($pick_num);

                        $contest->set_status('winners_picked');
                    }
                }
            }
        }


        
        /**
        * Install method creates required sql tables and schedules autoexpiry event
        */
        static function install()
        {           
            global $wpdb;
        
            $sql = 'CREATE TABLE IF NOT EXISTS `'.$wpdb->prefix.self::sqlt_participant.'` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `contest_id` INT UNSIGNED NOT NULL,
                `date_gmt` DATETIME NOT NULL,
                `ip` VARchAR(39) NOT NULL,
                `code` VARchAR(32) NOT NULL,
                `email` VARchAR(100) NOT NULL,
                `status` varchar(15) NOT NULL DEFAULT "0",
                PRIMARY KEY (id)
            );';
            
            $res = $wpdb->query($sql);
            if($res===false)
                die();
                
            $sql = 'CREATE TABLE IF NOT EXISTS `'.$wpdb->prefix.self::sqlt_participant_meta.'` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `participant_id` INT UNSIGNED NOT NULL,
                `meta_key` VARchAR(50) NOT NULL,
                `meta_value` VARchAR(200) NOT NULL,
                PRIMARY KEY (id),
                KEY participant_id (participant_id)
            );';
            
            $res = $wpdb->query($sql);
            if($res===false)
                die();
                
            wp_schedule_event(time(), 'hourly', 'contestfriend_autoexpiry');
        }
        
        /**
        * Uninstall method deletes all remaining contests, sql tables and clears schedules autoexpiry event
        */
        static function uninstall()
        {
            global $wpdb;
            
            $cf_posts = get_posts(array('post_type' => 'contestfriend'));
            if(is_array($cf_posts))
            {
                foreach($cf_posts as $post)
                    wp_delete_post($post->ID, true);
            }
                       
            $sql = 'DROP TABLE `'.$wpdb->prefix.self::sqlt_participant.'`';
            $wpdb->query($sql);
            
            $sql = 'DROP TABLE `'.$wpdb->prefix.self::sqlt_participant_meta.'`';
            $wpdb->query($sql);
            
            wp_clear_scheduled_hook('contestfriend_autoexpiry');
        }
        
        /**
        * Plugin deactivate method.
        * Called when deactivating the plugin in WordPress admin.
        */
        static function deactivate()
        {
            
        }
        
        /**
        * Initialize method, called on <i>init</i> action.
        * <ul>
        * <li>setting up all ajax hooks</li>
        * <li>adding shortcodes</li>
        * <li>registering post types</li>
        * <li>registering textdomain for i18n</li>
        * <li>registering and enqueueing styles and scripts</li>
        * <li>catching and processing contest double-optin based on $_GET parameters</li>
        * </ul>
        */
        function init()
        {
            // ajax hooks
            add_action('wp_ajax_contestfriend_process', array('cf_Widget', 'process'));
            add_action('wp_ajax_nopriv_contestfriend_process', array('cf_Widget', 'process'));
            
            add_action('wp_ajax_cf_mailchimp_list', array('cf_Page_Contest', 'field_mailchimp_list_ajax'));
            add_action('wp_ajax_cf_campaignmonitor_client', array('cf_Page_Contest', 'field_campaignmonitor_client_ajax'));
            add_action('wp_ajax_cf_campaignmonitor_list', array('cf_Page_Contest', 'field_campaignmonitor_list_ajax'));
            add_action('wp_ajax_cf_aweber_code', array('cf_Page_Contest', 'field_aweber_code_ajax'));
            add_action('wp_ajax_cf_aweber_list',  array('cf_Page_Contest', 'field_aweber_list_ajax'));
            
            add_action('wp_ajax_cf_getresponse_list', array('cf_Page_Contest', 'field_getresponse_list_ajax'));
            
            // register shortcode
            add_shortcode('contestfriend', array('cf_Widget', 'html'));
            
            // register post type
            $array = array(
                'public' => false, 
                'exclude_from_search' => true
                );
                
            register_post_type(self::post_type, $array);

            // register i18n domain
            load_plugin_textdomain('contestfriend', false, dirname(plugin_basename(__FILE__)).'/languages/');
            
            // register styles & scripts
            wp_enqueue_script('jquery');
            
            wp_register_style('cf_css_base', self::$plugin_url.'/css/cf_base.css');
            wp_enqueue_style('cf_css_base');
            
            wp_register_style('cf_css_jquery_ui', self::$plugin_url.'/css/jquery-ui-1.9.1.custom.min.css');
            
            wp_register_script('cf_js_widget', self::$plugin_url.'/js/cf_widget.js', array('jquery'));
            
            wp_register_script('cf_js_datetimepicker', self::$plugin_url.'/js/jquery-ui-timepicker-addon.min.js', array('jquery', 'jquery-ui-core', 'jquery-ui-slider', 'jquery-ui-datepicker'));
            
            // catch and process double optin
            if(isset($_GET['contestfriend_confirm']))
            {
                cf_Widget::process_optin($_GET['contestfriend_confirm']);
                
                $url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER["REQUEST_URI"];
                $url = remove_query_arg('contestfriend_confirm', $url);
                wp_safe_redirect($url);
                die();
            }         
        }
        
        /**
        * Called on <i>admin_menu</i> action. Creates page objects used in back-end.
        */
        function init_menu()
        {
            new cf_Page_List();
            new cf_Page_Contest();
            new cf_Page_Participants();
        }
    }
    
    /**
    * Init function hooked to <i>plugins_loaded</i> action, that creates new <i>cf_Manager</i> object.
    */
    function init_contest_friend()
    {
        new cf_Manager();
    }
}
