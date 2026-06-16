<?php
/**
 * Plugin Name: 应用页面插件
 * Plugin URI: https://github.com/Jacky088/apps_exhibition
 * Description: 推荐多个应用，支持后台管理、多端自适应、分类筛选、多下载按钮。
 * Version: 1.8.6.1
 * Author: 木木
 * Author URI: https://github.com/Jacky088/apps_exhibition
 * Text Domain: apps-exhibition
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! defined( 'APPS_EXHIBITION_PATH' ) ) {
    define( 'APPS_EXHIBITION_PATH', plugin_dir_path( __FILE__ ) );
}

final class Apps_Exhibition {

    const VERSION = '1.8.6.1';

    private $plugin_path;
    private $plugin_url;
    private $table_name;

    /** @var array 后台动态平台分类 默认值 */
    private $default_platforms = [ 'Android', 'AndroidTV', 'iOS', 'iPadOS', 'tvOS', 'macOS', 'Windows' ];

    public function __construct() {
        $this->plugin_path = plugin_dir_path( __FILE__ );
        $this->plugin_url  = plugin_dir_url( __FILE__ );

        global $wpdb;
        $this->table_name = $wpdb->prefix . 'apps_exhibition';

        $this->init_hooks();
    }

    private function init_hooks() {
        register_activation_hook( __FILE__, [ $this, 'activate_plugin' ] );

        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
        
        // 修改：改为注册脚本，在短代码中按需调用
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_register_scripts' ] );

        // 处理筛选分类表单提交
        add_action( 'admin_post_apps_exhibition_save_filter_categories', [ $this, 'handle_filter_categories_form' ] );

        // 新增：处理平台分类表单提交
        add_action( 'admin_post_apps_exhibition_save_platform_categories', [ $this, 'handle_platform_categories_form' ] );

        // 新增：处理首页海报保存
        add_action( 'admin_post_save_home_posters', 'save_home_posters' );

        // 引入功能文件
        require_once $this->plugin_path . 'includes/admin.php';
        require_once $this->plugin_path . 'includes/shortcode.php';

        // 处理应用表单提交
        add_action( 'admin_post_apps_exhibition_save', 'apps_exhibition_handle_form_post' );
        add_action( 'admin_post_apps_exhibition_delete', 'apps_exhibition_handle_delete' );
    }

    /**
     * 激活插件时创建表，并兼容升级
     */
    public function activate_plugin() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            app_name varchar(100) NOT NULL,
            app_description text NOT NULL,
            app_icon varchar(255) NOT NULL,
            app_platforms varchar(255) NOT NULL,
            app_filter_category varchar(255) NOT NULL DEFAULT '',
            app_downloads text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            poster_download_link varchar(255) DEFAULT '' COMMENT '海报下载链接',
            poster_download_text varchar(50) DEFAULT '' COMMENT '海报按钮文字',
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        $default_cats = [ 'Emby', 'IPTV', '代理' ];
        $option = get_option( 'apps_exhibition_filter_categories' );
        if ( ! is_array( $option ) || empty( $option ) ) {
            update_option( 'apps_exhibition_filter_categories', $default_cats );
        }

        $platforms_option = get_option( 'apps_exhibition_platform_categories' );
        if ( ! is_array( $platforms_option ) || empty( $platforms_option ) ) {
            update_option( 'apps_exhibition_platform_categories', $this->default_platforms );
        }
    }

    public function get_filter_categories() {
        $cats = get_option( 'apps_exhibition_filter_categories' );
        if ( $cats && is_array( $cats ) ) {
            return $cats;
        }
        return [ 'Emby', 'IPTV', '代理' ];
    }

    public function get_platform_categories() {
        $platforms = get_option( 'apps_exhibition_platform_categories' );
        if ( $platforms && is_array( $platforms ) ) {
            return $platforms;
        }
        return $this->default_platforms;
    }

    public function handle_filter_categories_form() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( '无权限访问', 'apps-exhibition' ) );
        }
        check_admin_referer( 'apps_exhibition_filter_categories' );

        $input = isset( $_POST['filter_categories'] ) ? wp_unslash( trim( $_POST['filter_categories'] ) ) : '';
        $cats = array_filter( array_unique( array_map( 'trim', explode( "\n", $input ) ) ), function ( $v ) {
            return $v !== '';
        } );
        $cats = array_values( $cats );

        $updated = update_option( 'apps_exhibition_filter_categories', $cats );

        $redirect_url = admin_url( 'admin.php?page=apps-exhibition&tab=filter_categories' );
        if ( $updated ) {
            $redirect_url = add_query_arg( 'message', 'cat_saved', $redirect_url );
        } else {
            $redirect_url = add_query_arg( 'message', 'cat_saved_error', $redirect_url );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    public function handle_platform_categories_form() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( '无权限访问', 'apps-exhibition' ) );
        }
        check_admin_referer( 'apps_exhibition_platform_categories' );

        $input = isset( $_POST['platform_categories'] ) ? wp_unslash( trim( $_POST['platform_categories'] ) ) : '';
        $platforms = array_filter( array_unique( array_map( 'trim', explode( "\n", $input ) ) ), function ( $v ) {
            return $v !== '';
        } );
        $platforms = array_values( $platforms );

        $updated = update_option( 'apps_exhibition_platform_categories', $platforms );

        $redirect_url = admin_url( 'admin.php?page=apps-exhibition&tab=platform_categories' );
        if ( $updated ) {
            $redirect_url = add_query_arg( 'message', 'platform_saved', $redirect_url );
        } else {
            $redirect_url = add_query_arg( 'message', 'platform_saved_error', $redirect_url );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    public function add_admin_menu() {
        add_menu_page(
            __( '应用展示', 'apps-exhibition' ),
            __( '应用展示', 'apps-exhibition' ),
            'manage_options',
            'apps-exhibition',
            [ $this, 'render_admin_page' ],
            'dashicons-tablet',
            30
        );
    }

    public function render_admin_page() {
        if ( function_exists( 'apps_exhibition_admin_page' ) ) {
            apps_exhibition_admin_page();
        }
    }

    public function admin_enqueue_scripts( $hook_suffix ) {
        if ( $hook_suffix !== 'toplevel_page_apps-exhibition' ) {
            return;
        }

        wp_enqueue_media(); 
        wp_enqueue_style( 'apps-exhibition-admin-style', $this->plugin_url . 'assets/css/admin.css', [], self::VERSION );
        wp_enqueue_script( 'apps-exhibition-admin-script', $this->plugin_url . 'assets/js/admin.js', [ 'jquery' ], self::VERSION, true );
    }

    /**
     * 优化：只注册样式，不直接加载，由 Shortcode 决定是否加载
     */
    public function frontend_register_scripts() {
        wp_register_style( 'apps-exhibition-style', $this->plugin_url . 'assets/css/apps-exhibition.css', [], self::VERSION );
        // 注册 Swiper
        wp_register_style( 'swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css', [], '10' );
        wp_register_script( 'swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js', [], '10', true );
    }
}

global $apps_exhibition_plugin_instance;
$apps_exhibition_plugin_instance = new Apps_Exhibition();

/**
 * 首页海报保存逻辑
 */
function save_home_posters() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( __( '无权限', 'apps-exhibition' ) );

    check_admin_referer( 'save_home_posters_nonce' );

    $posters_json = isset( $_POST['home_posters'] ) ? wp_unslash( $_POST['home_posters'] ) : '[]';
    $posters = json_decode( $posters_json, true );

    // 增强的 JSON 错误处理
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $posters ) ) {
        error_log( 'Apps Exhibition: Invalid JSON in home_posters - ' . json_last_error_msg() );
        $posters = [];
    }

    // 验证和清理海报数据
    $posters = array_filter( $posters, function($item) {
        if ( ! isset($item['url']) || empty( $item['url'] ) ) {
            return false;
        }
        // 验证 URL 格式
        if ( ! filter_var( $item['url'], FILTER_VALIDATE_URL ) ) {
            error_log( 'Apps Exhibition: Invalid poster URL - ' . $item['url'] );
            return false;
        }
        return true;
    });

    // 限制最大海报数量
    $posters = array_slice( $posters, 0, 10 );

    update_option( 'home_posters', $posters );

    wp_safe_redirect( add_query_arg( [ 'message' => 'home_posters_saved' ], wp_get_referer() ) );
    exit;
}
