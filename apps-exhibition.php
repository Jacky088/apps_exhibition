<?php
/**
 * Plugin Name: 应用页面插件
 * Plugin URI: https://github.com/Jacky088/apps_exhibition
 * Description: 推荐多个应用，支持后台管理、多端自适应、分类筛选、多下载按钮。
 * Version: 2.0.3
 * Author: 木木
 * Author URI: https://github.com/Jacky088/apps_exhibition
 * Text Domain: apps-exhibition
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'APPS_EXHIBITION_PATH' ) ) {
    define( 'APPS_EXHIBITION_PATH', plugin_dir_path( __FILE__ ) );
}

final class Apps_Exhibition {

    const VERSION = '2.0.3';

    private static $instance = null;
    private $plugin_path;
    private $plugin_url;
    private $table_name;
    private $default_platforms = [ 'Android', 'AndroidTV', 'iOS', 'iPadOS', 'tvOS', 'macOS', 'Windows' ];

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->plugin_path = plugin_dir_path( __FILE__ );
        $this->plugin_url  = plugin_dir_url( __FILE__ );

        global $wpdb;
        $this->table_name = $wpdb->prefix . 'apps_exhibition';

        $this->init_hooks();
    }

    private function __clone() {}
    public function __wakeup() { throw new \Exception( 'Cannot unserialize singleton' ); }

    private function init_hooks() {
        register_activation_hook( __FILE__, [ $this, 'activate_plugin' ] );

        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'frontend_register_scripts' ] );

        add_action( 'admin_post_apps_exhibition_save_filter_categories', [ $this, 'handle_filter_categories_form' ] );
        add_action( 'admin_post_apps_exhibition_save_platform_categories', [ $this, 'handle_platform_categories_form' ] );
        add_action( 'admin_post_save_home_posters', [ $this, 'save_home_posters' ] );

        require_once $this->plugin_path . 'includes/admin.php';
        require_once $this->plugin_path . 'includes/shortcode.php';

        add_action( 'admin_post_apps_exhibition_save', 'apps_exhibition_handle_form_post' );
        add_action( 'admin_post_apps_exhibition_delete', 'apps_exhibition_handle_delete' );
        add_action( 'admin_post_apps_exhibition_bulk_delete', 'apps_exhibition_handle_bulk_delete' );

        // AJAX - 按分类保存排序
        add_action( 'wp_ajax_apps_exhibition_save_category_order', [ $this, 'ajax_save_category_order' ] );
    }

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

        // 初始化分类排序 option
        if ( false === get_option( 'apps_exhibition_category_order' ) ) {
            update_option( 'apps_exhibition_category_order', [] );
        }
    }

    public function get_table_name() {
        return $this->table_name;
    }

    public function get_filter_categories() {
        $cats = get_option( 'apps_exhibition_filter_categories' );
        return ( $cats && is_array( $cats ) ) ? $cats : [ 'Emby', 'IPTV', '代理' ];
    }

    public function get_platform_categories() {
        $platforms = get_option( 'apps_exhibition_platform_categories' );
        return ( $platforms && is_array( $platforms ) ) ? $platforms : $this->default_platforms;
    }

    /**
     * 获取指定分类的排序（ID数组）
     */
    public function get_category_order( $category ) {
        $all_orders = get_option( 'apps_exhibition_category_order', [] );
        if ( isset( $all_orders[ $category ] ) && is_array( $all_orders[ $category ] ) ) {
            return $all_orders[ $category ];
        }
        return [];
    }

    public static function clear_frontend_cache() {
        delete_transient( 'apps_exhibition_all_data_v' . self::VERSION );
    }

    /**
     * AJAX 保存分类内排序
     */
    public function ajax_save_category_order() {
        check_ajax_referer( 'apps_exhibition_sort_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No permission' );
        }

        $category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';
        $order    = isset( $_POST['order'] ) ? $_POST['order'] : [];

        if ( empty( $category ) || ! is_array( $order ) ) {
            wp_send_json_error( 'Invalid data' );
        }

        // 清洗 ID
        $order = array_map( 'intval', $order );

        $all_orders = get_option( 'apps_exhibition_category_order', [] );
        if ( ! is_array( $all_orders ) ) {
            $all_orders = [];
        }
        $all_orders[ $category ] = $order;
        update_option( 'apps_exhibition_category_order', $all_orders );

        self::clear_frontend_cache();
        wp_send_json_success();
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

        update_option( 'apps_exhibition_filter_categories', $cats );
        self::clear_frontend_cache();

        wp_safe_redirect( add_query_arg( 'message', 'cat_saved', admin_url( 'admin.php?page=apps-exhibition&tab=settings' ) ) );
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

        update_option( 'apps_exhibition_platform_categories', $platforms );
        self::clear_frontend_cache();

        wp_safe_redirect( add_query_arg( 'message', 'platform_saved', admin_url( 'admin.php?page=apps-exhibition&tab=settings' ) ) );
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
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_style( 'apps-exhibition-admin-style', $this->plugin_url . 'assets/css/admin.css', [], self::VERSION );
        wp_enqueue_script( 'apps-exhibition-admin-script', $this->plugin_url . 'assets/js/admin.js', [ 'jquery', 'jquery-ui-sortable' ], self::VERSION, true );

        wp_localize_script( 'apps-exhibition-admin-script', 'appsExhibitionL10n', [
            'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
            'sortNonce'          => wp_create_nonce( 'apps_exhibition_sort_nonce' ),
            'fillRequired'       => __( '请填写所有必填项 (应用名称、描述、图标)。', 'apps-exhibition' ),
            'selectPlatform'     => __( '请至少选择一个平台分类。', 'apps-exhibition' ),
            'selectFilter'       => __( '请至少选择一个筛选分类。', 'apps-exhibition' ),
            'fillDownload'       => __( '请确保所有下载链接和按钮文字均已填写，或者留空以便删除。', 'apps-exhibition' ),
            'needOneDownload'    => __( '请至少填写一个完整下载链接（URL和按钮文字）。', 'apps-exhibition' ),
            'maxPostersAlert'    => sprintf( __( '最多只能上传 %d 张海报', 'apps-exhibition' ), 10 ),
            'maxPostersExceed'   => sprintf( __( '添加这些图片将超过 %d 张的限制', 'apps-exhibition' ), 10 ),
            'selectIconTitle'    => __( '选择应用图标', 'apps-exhibition' ),
            'useIconBtn'         => __( '使用这个图标', 'apps-exhibition' ),
            'selectPosterTitle'  => __( '选择海报图片', 'apps-exhibition' ),
            'insertBtn'          => __( '插入', 'apps-exhibition' ),
            'changePosterBtn'    => __( '更换图片', 'apps-exhibition' ),
            'removePosterBtn'    => __( '删除海报', 'apps-exhibition' ),
            'deleteBtn'          => __( '删除', 'apps-exhibition' ),
            'downloadUrlPlc'     => __( '下载链接 URL', 'apps-exhibition' ),
            'downloadTextPlc'    => __( '按钮文字', 'apps-exhibition' ),
            'downloadTextDefault' => __( '下载', 'apps-exhibition' ),
            'downloadAddrPlc'    => __( '下载地址', 'apps-exhibition' ),
            'confirmDelete'      => __( '确认删除？', 'apps-exhibition' ),
            'cancel'             => __( '取消', 'apps-exhibition' ),
            'confirmBulkDelete'  => __( '确定要删除选中的应用吗？此操作不可恢复。', 'apps-exhibition' ),
            'noBulkSelected'     => __( '请先选择要删除的应用。', 'apps-exhibition' ),
            'sortSaved'          => __( '排序已保存', 'apps-exhibition' ),
            'sortError'          => __( '排序保存失败', 'apps-exhibition' ),
            'addApp'             => __( '添加应用', 'apps-exhibition' ),
            'editApp'            => __( '编辑应用', 'apps-exhibition' ),
            'selectCategoryFirst' => __( '请先选择一个分类再拖拽排序', 'apps-exhibition' ),
            'allApps'            => __( '全部应用', 'apps-exhibition' ),
        ] );
    }

    public function frontend_register_scripts() {
        wp_register_style( 'apps-exhibition-style', $this->plugin_url . 'assets/css/apps-exhibition.css', [], self::VERSION );
        wp_register_style( 'swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css', [], '10' );
        wp_register_script( 'swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js', [], '10', true );
        wp_register_script( 'apps-exhibition-front', $this->plugin_url . 'assets/js/apps-exhibition.js', [ 'swiper-js' ], self::VERSION, true );
    }

    public function save_home_posters() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( '无权限', 'apps-exhibition' ) );
        }

        check_admin_referer( 'save_home_posters_nonce' );

        $posters_json = isset( $_POST['home_posters'] ) ? wp_unslash( $_POST['home_posters'] ) : '[]';
        $posters = json_decode( $posters_json, true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $posters ) ) {
            $posters = [];
        }

        $posters = array_filter( $posters, function( $item ) {
            return isset( $item['url'] ) && ! empty( $item['url'] ) && filter_var( $item['url'], FILTER_VALIDATE_URL );
        } );

        $posters = array_slice( $posters, 0, 10 );

        $posters = array_map( function( $item ) {
            return [
                'url'           => esc_url_raw( $item['url'] ),
                'download_url'  => isset( $item['download_url'] ) && ! empty( $item['download_url'] ) ? esc_url_raw( $item['download_url'] ) : '',
                'download_text' => isset( $item['download_text'] ) ? sanitize_text_field( $item['download_text'] ) : '',
            ];
        }, $posters );

        $posters = array_values( $posters );
        update_option( 'home_posters', $posters );

        wp_safe_redirect( add_query_arg( [ 'message' => 'home_posters_saved' ], wp_get_referer() ) );
        exit;
    }
}

Apps_Exhibition::get_instance();
