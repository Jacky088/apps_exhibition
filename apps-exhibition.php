<?php
/**
 * Plugin Name: 应用页面插件
 * Plugin URI: https://github.com/Jacky088/apps_exhibition
 * Description: 推荐多个应用，支持后台管理、多端自适应、分类筛选、多下载按钮。
 * Version: 2.0.9
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

if ( ! defined( 'APPS_EXHIBITION_FILE' ) ) {
    define( 'APPS_EXHIBITION_FILE', __FILE__ );
}

final class Apps_Exhibition {

    const VERSION = '2.0.9';

    /**
     * 数据表结构版本。修改建表 SQL 或需要执行一次性数据迁移时必须递增此值，
     * 以便已安装的站点在升级插件后自动执行 dbDelta 与迁移逻辑。
     */
    const DB_VERSION = '1.0.1';

    const DB_VERSION_OPTION = 'apps_exhibition_db_version';

    /**
     * 已激活的插件版本。每次插件版本提升时用于强制刷新前端缓存，
     * 避免旧版本 transient 残留导致前端样式/数据不更新。
     */
    const VERSION_OPTION = 'apps_exhibition_version';

    /**
     * 表单校验错误暂存 transient 前缀（后接用户 ID）。
     * admin-post 处理器跳转前写入，后台列表页展示后立即删除。
     */
    const FORM_ERRORS_TRANSIENT = 'apps_exhibition_form_errors_';

    /**
     * 首页海报 option。旧版本使用无前缀的 'home_posters'，
     * 存在与主题/其他插件冲突的风险，现已迁移至带前缀的 key。
     */
    const POSTERS_OPTION     = 'apps_exhibition_home_posters';
    const POSTERS_OPTION_OLD = 'home_posters';

    const MAX_POSTERS = 10;

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

    public function __clone() { throw new \Exception( 'Cannot clone singleton' ); }
    public function __wakeup() { throw new \Exception( 'Cannot unserialize singleton' ); }

    private function init_hooks() {
        register_activation_hook( __FILE__, [ $this, 'activate_plugin' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate_plugin' ] );

        // 插件通过 FTP/Git 直接覆盖升级时不会触发激活钩子，
        // 因此在每次加载时检查一次结构版本。
        add_action( 'plugins_loaded', [ $this, 'maybe_upgrade' ] );
        // 插件版本提升时强制刷新前端缓存（覆盖升级不触发激活钩子，故每次加载检查）。
        add_action( 'plugins_loaded', [ $this, 'maybe_flush_cache_on_version' ] );

        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( APPS_EXHIBITION_FILE ), [ $this, 'add_plugin_action_links' ] );
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
        add_action( 'admin_post_apps_exhibition_bulk_move', 'apps_exhibition_handle_bulk_move' );

        // AJAX - 按分类保存排序
        add_action( 'wp_ajax_apps_exhibition_save_category_order', [ $this, 'ajax_save_category_order' ] );
    }

    public function activate_plugin() {
        $this->install_schema();
        $this->install_default_options();
        $this->migrate_posters_option();
        $this->migrate_slashed_data();

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * 停用时不删除任何用户数据（数据清理交给 uninstall.php），
     * 仅清除缓存与计划任务，避免停用期间残留脏缓存。
     */
    public function deactivate_plugin() {
        self::clear_frontend_cache();
    }

    /**
     * 覆盖式升级（FTP / Git / 手动替换文件）不会触发激活钩子，
     * 这里比对已存储的结构版本，必要时补跑建表与迁移逻辑。
     */
    public function maybe_upgrade() {
        $installed = get_option( self::DB_VERSION_OPTION );

        if ( $installed === self::DB_VERSION ) {
            return;
        }

        $this->install_schema();
        $this->install_default_options();
        $this->migrate_posters_option();
        $this->migrate_slashed_data();
        self::clear_frontend_cache();

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * 插件版本提升时强制刷新所有前端缓存。
     *
     * clear_frontend_cache() 仅删除当前 VERSION 对应的 transient，
     * 旧版本缓存不会被自动清理。这里通过直接删除数据库中所有以
     * apps_exhibition_all_data_v / apps_exhibition_sorted_v 为前缀的 transient，
     * 确保升级后前端立即使用最新数据与样式，无需手动清空缓存。
     * 对应的 _transient_timeout_ 行必须一并删除，否则会成为
     * 永不过期的孤儿行，缓慢膨胀 options 表。
     */
    public function maybe_flush_cache_on_version() {
        $saved = get_option( self::VERSION_OPTION );

        if ( $saved === self::VERSION ) {
            return;
        }

        global $wpdb;

        // 删除所有版本的前端缓存 transient（含旧版本残留与对应的 timeout 行）。
        $prefixes = [
            '_transient_apps_exhibition_all_data_v',
            '_transient_timeout_apps_exhibition_all_data_v',
            '_transient_apps_exhibition_sorted_v',
            '_transient_timeout_apps_exhibition_sorted_v',
        ];

        foreach ( $prefixes as $prefix ) {
            $like = $wpdb->esc_like( $prefix ) . '%';
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
        }

        self::clear_frontend_cache();

        update_option( self::VERSION_OPTION, self::VERSION );
    }

    /**
     * 建表 / 升级表结构。
     * 注意：dbDelta 需要标准的 "CREATE TABLE"（不能带 IF NOT EXISTS），
     * 否则它无法比对既有表的列并生成 ALTER 语句。
     */
    private function install_schema() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
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
    }

    private function install_default_options() {
        $option = get_option( 'apps_exhibition_filter_categories' );
        if ( ! is_array( $option ) || empty( $option ) ) {
            update_option( 'apps_exhibition_filter_categories', [ 'Emby', 'IPTV', '代理' ] );
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

    /**
     * 将旧的无前缀 option 'home_posters' 迁移到带前缀的 key。
     * 迁移只在新 key 尚不存在时执行，且执行后删除旧 key，保证幂等。
     */
    private function migrate_posters_option() {
        if ( false !== get_option( self::POSTERS_OPTION, false ) ) {
            // 新 key 已存在，仅清理可能残留的旧 key
            if ( false !== get_option( self::POSTERS_OPTION_OLD, false ) ) {
                delete_option( self::POSTERS_OPTION_OLD );
            }
            return;
        }

        $legacy = get_option( self::POSTERS_OPTION_OLD, false );
        if ( false === $legacy ) {
            return;
        }

        update_option( self::POSTERS_OPTION, is_array( $legacy ) ? $legacy : [] );
        delete_option( self::POSTERS_OPTION_OLD );
    }

    /**
     * 读取首页海报，兼容尚未迁移的旧数据。
     */
    public static function get_home_posters() {
        $posters = get_option( self::POSTERS_OPTION, false );

        if ( false === $posters ) {
            // 迁移尚未执行（例如覆盖升级后首次访问前端）时回退读取旧 key
            $posters = get_option( self::POSTERS_OPTION_OLD, [] );
        }

        return is_array( $posters ) ? $posters : [];
    }

    /**
     * 一次性数据迁移：清洗历史数据中因缺少 wp_unslash 而残留的反斜杠。
     *
     * 旧版保存表单时未做去斜杠处理，含引号的输入（如 Tom's App）
     * 会以 Tom\'s App 入库，且每次重新编辑保存都会再累积一层转义。
     * 本迁移对文本字段反复执行 stripslashes 直到值稳定，一次性还原所有层级。
     *
     * 幂等：对干净数据通常无变化（跳过更新）；仅当值中恰好包含
     * 用户刻意输入的 \' \" \\ 序列时才会被误清洗（极少见）。
     */
    private function migrate_slashed_data() {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT id, app_name, app_description, app_downloads FROM {$this->table_name}",
            ARRAY_A
        );
        if ( empty( $rows ) ) {
            return;
        }

        foreach ( $rows as $row ) {
            $name = self::strip_repeated_slashes( $row['app_name'] );
            $desc = self::strip_repeated_slashes( $row['app_description'] );

            // 下载链接为 JSON（或旧版序列化）结构：解码后逐项清洗，变更时统一回写为 JSON
            $downloads         = apps_exhibition_parse_downloads( $row['app_downloads'] );
            $downloads_changed = false;
            foreach ( $downloads as $key => $dl ) {
                $url  = self::strip_repeated_slashes( $dl['url'] ?? '' );
                $text = self::strip_repeated_slashes( $dl['text'] ?? '' );
                if ( $url !== ( $dl['url'] ?? '' ) || $text !== ( $dl['text'] ?? '' ) ) {
                    $downloads[ $key ]['url']  = $url;
                    $downloads[ $key ]['text'] = $text;
                    $downloads_changed = true;
                }
            }

            if ( $name === $row['app_name'] && $desc === $row['app_description'] && ! $downloads_changed ) {
                continue;
            }

            $wpdb->update(
                $this->table_name,
                [
                    'app_name'        => $name,
                    'app_description' => $desc,
                    'app_downloads'   => $downloads_changed ? wp_json_encode( $downloads ) : $row['app_downloads'],
                ],
                [ 'id' => $row['id'] ],
                [ '%s', '%s', '%s' ],
                [ '%d' ]
            );
        }
    }

    /**
     * 反复执行 stripslashes 直到值不再变化（上限 5 次），
     * 用于清除历史数据中多次保存累积的多层转义。
     */
    private static function strip_repeated_slashes( $value ) {
        $current = (string) $value;

        for ( $i = 0; $i < 5; $i++ ) {
            $stripped = stripslashes( $current );
            if ( $stripped === $current ) {
                break;
            }
            $current = $stripped;
        }

        return $current;
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

    /**
     * 校验 URL 格式合法且协议在白名单内（仅允许 http/https）。
     *
     * FILTER_VALIDATE_URL 会放行 javascript:、data: 等危险协议，
     * 因此入库前必须显式校验协议，避免脏数据进入数据库。
     */
    public static function is_safe_url( $url ) {
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        $scheme = wp_parse_url( $url, PHP_URL_SCHEME );
        return in_array( strtolower( (string) $scheme ), [ 'http', 'https' ], true );
    }

    public static function clear_frontend_cache() {
        delete_transient( 'apps_exhibition_all_data_v' . self::VERSION );
        delete_transient( 'apps_exhibition_sorted_v' . self::VERSION );
    }

    /**
     * AJAX 保存分类内排序
     */
    public function ajax_save_category_order() {
        check_ajax_referer( 'apps_exhibition_sort_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No permission' );
        }

        $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $order    = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : [];

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

    /**
     * 在插件列表页「停用」链接旁增加「设置」入口，一键进入应用展示设置页。
     */
    public function add_plugin_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=apps-exhibition&tab=settings' ) ),
            __( '设置', 'apps-exhibition' )
        );

        // 将「设置」插入到「停用」之后，保持操作链接的阅读顺序
        if ( isset( $links['deactivate'] ) ) {
            $new_links = [];
            foreach ( $links as $key => $value ) {
                $new_links[ $key ] = $value;
                if ( $key === 'deactivate' ) {
                    $new_links['settings'] = $settings_link;
                }
            }
            $links = $new_links;
        } else {
            $links['settings'] = $settings_link;
        }

        return $links;
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
            'maxPosters'         => self::MAX_POSTERS,
            'maxPostersAlert'    => sprintf( __( '最多只能上传 %d 张海报', 'apps-exhibition' ), self::MAX_POSTERS ),
            'maxPostersExceed'   => sprintf( __( '添加这些图片将超过 %d 张的限制', 'apps-exhibition' ), self::MAX_POSTERS ),
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
            'moveSelected'       => __( '已选择 %d 个应用', 'apps-exhibition' ),
            'moveAllOption'      => __( '全部（覆盖现有分类）', 'apps-exhibition' ),
            'moveSelectTarget'   => __( '请选择目标分类。', 'apps-exhibition' ),
            'moveConfirm'        => __( '确认移动所选应用的分类？此操作将立即生效。', 'apps-exhibition' ),
        ] );
    }

    public function frontend_register_scripts() {
        wp_register_style( 'apps-exhibition-style', $this->plugin_url . 'assets/css/apps-exhibition.css', [], self::VERSION );
        // Swiper 改为本地加载：规避 CDN 供应链风险与国内网络不稳定导致的轮播失效
        wp_register_style( 'swiper-css', $this->plugin_url . 'assets/vendor/swiper-bundle.min.css', [], '10.3.1' );
        wp_register_script( 'swiper-js', $this->plugin_url . 'assets/vendor/swiper-bundle.min.js', [], '10.3.1', true );
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
            return isset( $item['url'] ) && ! empty( $item['url'] ) && self::is_safe_url( $item['url'] );
        } );

        $posters = array_slice( $posters, 0, self::MAX_POSTERS );

        $posters = array_map( function( $item ) {
            return [
                'url'           => esc_url_raw( $item['url'] ),
                'download_url'  => isset( $item['download_url'] ) && ! empty( $item['download_url'] ) ? esc_url_raw( $item['download_url'] ) : '',
                'download_text' => isset( $item['download_text'] ) ? sanitize_text_field( $item['download_text'] ) : '',
            ];
        }, $posters );

        $posters = array_values( $posters );
        update_option( self::POSTERS_OPTION, $posters );
        delete_option( self::POSTERS_OPTION_OLD );

        self::clear_frontend_cache();

        wp_safe_redirect( add_query_arg( [ 'message' => 'home_posters_saved' ], wp_get_referer() ) );
        exit;
    }
}

Apps_Exhibition::get_instance();
