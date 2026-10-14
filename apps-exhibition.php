<?php
/**
 * Plugin Name: 应用页面插件
 * Plugin URI: https://github.com/Jacky088/apps_exhibition
 * Description: 推荐多个应用，支持后台管理、多端自适应、分类筛选、多下载按钮。
 * Version: 3.0.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: 木木
 * Author URI: https://github.com/Jacky088/apps_exhibition
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
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

    const VERSION = '3.0.0';

    /**
     * 数据表结构版本。修改建表 SQL 或需要执行一次性数据迁移时必须递增此值，
     * 以便已安装的站点在升级插件后自动执行 dbDelta 与迁移逻辑。
     */
    const DB_VERSION = '1.0.3';

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
    private static $cached_category_icons = null;
    private static $cached_platform_icons = null;
    private $plugin_path;
    private $plugin_url;
    private $table_name;
    private $default_platforms = [ 'Android', 'AndroidTV', 'iOS', 'iPadOS', 'tvOS', 'macOS', 'Windows', '鸿蒙系统' ];

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

        global $wpdb;
        $col_check = $wpdb->get_results( "SHOW COLUMNS FROM {$this->table_name} LIKE 'app_badge'" );
        if ( empty( $col_check ) ) {
            $wpdb->query( "ALTER TABLE {$this->table_name} ADD app_badge varchar(50) NOT NULL DEFAULT '' AFTER app_name" );
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
            app_badge varchar(50) NOT NULL DEFAULT '',
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
        } else {
            // 兼容已有安装：若平台列表中尚未包含鸿蒙系统，自动追加
            $has_harmony = false;
            foreach ( $platforms_option as $p ) {
                $lp = strtolower( (string) $p );
                if ( false !== strpos( $lp, '鸿蒙' ) || false !== strpos( $lp, 'harmony' ) || false !== strpos( $lp, 'hos' ) ) {
                    $has_harmony = true;
                    break;
                }
            }
            if ( ! $has_harmony ) {
                $platforms_option[] = '鸿蒙系统';
                update_option( 'apps_exhibition_platform_categories', $platforms_option );
            }
        }

        // 初始化分类排序 option
        if ( false === get_option( 'apps_exhibition_category_order' ) ) {
            update_option( 'apps_exhibition_category_order', [] );
        }

        // 初始化隐藏前台全部 option
        if ( false === get_option( 'apps_exhibition_hide_all_category' ) ) {
            update_option( 'apps_exhibition_hide_all_category', 0 );
        }

        // 初始化分类与平台自定义图标 option
        if ( false === get_option( 'apps_exhibition_category_icons' ) ) {
            update_option( 'apps_exhibition_category_icons', [] );
        }
        if ( false === get_option( 'apps_exhibition_platform_icons' ) ) {
            update_option( 'apps_exhibition_platform_icons', [] );
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
        self::$cached_category_icons = null;
        self::$cached_platform_icons = null;
        delete_transient( 'apps_exhibition_all_data_v' . self::VERSION );
        delete_transient( 'apps_exhibition_sorted_v' . self::VERSION );
    }

    public static function get_all_category_icons() {
        if ( null === self::$cached_category_icons ) {
            $icons = get_option( 'apps_exhibition_category_icons', [] );
            self::$cached_category_icons = is_array( $icons ) ? $icons : [];
        }
        return self::$cached_category_icons;
    }

    public static function get_all_platform_icons() {
        if ( null === self::$cached_platform_icons ) {
            $icons = get_option( 'apps_exhibition_platform_icons', [] );
            self::$cached_platform_icons = is_array( $icons ) ? $icons : [];
        }
        return self::$cached_platform_icons;
    }

    public function get_category_icons() {
        return self::get_all_category_icons();
    }

    public function get_platform_icons() {
        return self::get_all_platform_icons();
    }

    /**
     * 判断是否开启了“隐藏前台全部”开关
     */
    public static function is_hide_all_category() {
        return (bool) get_option( 'apps_exhibition_hide_all_category', 0 );
    }

    /**
     * 获取分类与平台新增时的通用默认图标 SVG。
     * 当用户添加未预置专属图标的新分类或新平台时，统一使用此图标以保证视觉样式一致。
     */
    public static function get_default_item_icon_svg() {
        return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F0F5FF"/><path d="M12 4.5l7 3.5-7 3.5-7-3.5 7-3.5z" stroke="#2F54EB" stroke-width="1.8" stroke-linejoin="round" fill="#2F54EB" fill-opacity="0.15"/><path d="M5 11.5l7 3.5 7-3.5" stroke="#2F54EB" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 15.5l7 3.5 7-3.5" stroke="#2F54EB" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    /**
     * 获取分类对应的专属图标 HTML
     */
    public static function get_category_icon_html( $name ) {
        $trim_name = trim( (string) $name );
        $category_icons = self::get_all_category_icons();
        if ( ! empty( $category_icons[ $trim_name ] ) ) {
            $url = esc_url( $category_icons[ $trim_name ] );
            return '<span class="ae-item-icon ae-item-icon-custom"><img src="' . $url . '" alt="' . esc_attr( $trim_name ) . '" class="ae-custom-icon-img" width="22" height="22" loading="lazy" decoding="async" /></span>';
        }

        $n = strtolower( $trim_name );
        $svg = '';

        if ( '__all__' === $n || '全部' === $n || 'all' === $n ) {
            // 全部 / 四宫格矩阵 (蓝)
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#E6F4FF"/><rect x="4.5" y="4.5" width="6.5" height="6.5" rx="1.5" fill="#1677FF"/><rect x="13" y="4.5" width="6.5" height="6.5" rx="1.5" fill="#1677FF"/><rect x="4.5" y="13" width="6.5" height="6.5" rx="1.5" fill="#1677FF"/><rect x="13" y="13" width="6.5" height="6.5" rx="1.5" fill="#1677FF"/></svg>';
        } elseif ( false !== strpos( $n, 'ai' ) || false !== strpos( $n, 'agent' ) || false !== strpos( $n, '智能' ) ) {
            // 机器人/AI 徽标 (蓝)
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#E6F4FF"/><path d="M12 3.8v2.2" stroke="#1677FF" stroke-width="1.8" stroke-linecap="round"/><rect x="5.5" y="6" width="13" height="11.5" rx="3.5" fill="#BAE0FF" fill-opacity="0.35" stroke="#1677FF" stroke-width="1.6"/><circle cx="9.5" cy="11.2" r="1.3" fill="#1677FF"/><circle cx="14.5" cy="11.2" r="1.3" fill="#1677FF"/><path d="M10 14.5c.6.5 1.4.7 2 .7s1.4-.2 2-.7" stroke="#1677FF" stroke-width="1.4" stroke-linecap="round"/><path d="M3.8 11.5H5.5M18.5 11.5H20.2" stroke="#1677FF" stroke-width="1.6" stroke-linecap="round"/></svg>';
        } elseif ( false !== strpos( $n, 'emby' ) ) {
            // Emby 绿色播放三角
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F6FFED"/><path d="M8.5 6.8c0-.7.8-1.1 1.4-.7l8.2 4.7c.6.4.6 1.3 0 1.6l-8.2 4.7c-.6.4-1.4 0-1.4-.7V6.8z" fill="#52C41A"/></svg>';
        } elseif ( false !== strpos( $n, 'jellyfin' ) ) {
            // Jellyfin 紫色多面体宝石
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F9F0FF"/><path d="M12 4.5l6.5 4.5-2.5 9.5h-8L5.5 9 12 4.5z" fill="#722ED1"/><path d="M12 8.5l3.5 2.5-1.5 5h-4L8.5 11 12 8.5z" fill="#D3ADF7"/></svg>';
        } elseif ( false !== strpos( $n, 'plex' ) ) {
            // Plex 柔和金底 + 官方标志性双色斜角箭（统一浅色调，告别死黑突兀块）
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#FFF7E6"/><rect x="5.5" y="6.5" width="2.2" height="11" rx="1.1" fill="#434343"/><path d="M9 6.5l5.5 5.5-5.5 5.5h3.2l5.5-5.5-5.5-5.5H9z" fill="#FA8C16"/></svg>';
        } elseif ( false !== strpos( $n, 'audio' ) || false !== strpos( $n, 'book' ) || false !== strpos( $n, '书' ) ) {
            // Audiobook 暖橙耳机听书（书本与耳机平衡感）
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#FFF2E8"/><path d="M5.5 17.5A2 2 0 017.5 16H18V6.5A1.5 1.5 0 0016.5 5h-9A2 2 0 005.5 7v10.5z" fill="#FFBB96" fill-opacity="0.35" stroke="#FA541C" stroke-width="1.6"/><path d="M5.5 17.5A2 2 0 017.5 16H18" stroke="#FA541C" stroke-width="1.6"/><path d="M8.5 10.5a3.5 3.5 0 017 0" stroke="#FA541C" stroke-width="1.6" stroke-linecap="round"/><rect x="7" y="10" width="2" height="3.5" rx="1" fill="#FA541C"/><rect x="15" y="10" width="2" height="3.5" rx="1" fill="#FA541C"/></svg>';
        } elseif ( false !== strpos( $n, 'navi' ) || false !== strpos( $n, 'music' ) || false !== strpos( $n, '音' ) ) {
            // Navidrome 蓝色音符微标
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#E6F7FF"/><path d="M9 16.5a2.2 2.2 0 11-2.2-2.2c.4 0 .9.1 1.2.3V7l8-2v7.5a2.2 2.2 0 11-2.2-2.2c.4 0 .9.1 1.2.3V7.2l-6 1.5V16.5z" fill="#1890FF"/></svg>';
        } elseif ( false !== strpos( $n, 'iptv' ) || false !== strpos( $n, 'tv' ) || false !== strpos( $n, '视' ) ) {
            // IPTV 品红窄边宽屏电视 + 播放图标
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#FFF0F6"/><rect x="4" y="6.5" width="16" height="11.5" rx="2.5" stroke="#EB2F96" stroke-width="1.6" fill="#FFD6E7" fill-opacity="0.35"/><path d="M10.5 9.8l4.2 2.45-4.2 2.45V9.8z" fill="#EB2F96"/><path d="M9.5 4.5l2.5 2 2.5-2M9 19.5h6" stroke="#EB2F96" stroke-width="1.5" stroke-linecap="round"/></svg>';
        } elseif ( false !== strpos( $n, '工' ) || false !== strpos( $n, 'tool' ) ) {
            // 工具 现代多功能扳手与螺丝刀微标
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F5F5F5"/><path d="M14.7 6.3a3.2 3.2 0 00-3.9.5l3.4 3.4-1.4 1.4-3.4-3.4a3.2 3.2 0 00-.5 3.9l-4 4a1.5 1.5 0 002.1 2.1l4-4a3.2 3.2 0 003.9-.5l-3.4-3.4 1.4-1.4 3.4 3.4a3.2 3.2 0 00.5-3.9l-2.1-2.1z" fill="#595959"/></svg>';
        } elseif ( false !== strpos( $n, '代理' ) || false !== strpos( $n, 'proxy' ) || false !== strpos( $n, 'clash' ) ) {
            // 代理 航行纸飞机微标 (青色)
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#E6FFFB"/><path d="M4.5 11.5l14-6-6 14-2.5-5.5-5.5-2.5z" fill="#13C2C2"/><path d="M10 14l8.5-8.5" stroke="#E6FFFB" stroke-width="1.4"/></svg>';
        } else {
            // 统一默认图标（未预置专属图标的新增分类）
            $svg = self::get_default_item_icon_svg();
        }

        return '<span class="ae-item-icon">' . $svg . '</span>';
    }

    /**
     * 获取平台对应的专属图标 HTML
     */
    public static function get_platform_icon_html( $name ) {
        $trim_name = trim( (string) $name );
        $platform_icons = self::get_all_platform_icons();
        if ( ! empty( $platform_icons[ $trim_name ] ) ) {
            $url = esc_url( $platform_icons[ $trim_name ] );
            return '<span class="ae-item-icon ae-item-icon-custom"><img src="' . $url . '" alt="' . esc_attr( $trim_name ) . '" class="ae-custom-icon-img" width="22" height="22" loading="lazy" decoding="async" /></span>';
        }

        $n = strtolower( $trim_name );
        $svg = '';

        if ( 'androidtv' === $n || false !== strpos( $n, 'androidtv' ) || false !== strpos( $n, 'android tv' ) ) {
            // AndroidTV 绿色电视 + 机器人 (与 Android 系列风格呼应)
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F6FFED"/><rect x="4" y="6" width="16" height="11.5" rx="2.5" stroke="#52C41A" stroke-width="1.6" fill="#D9F7BE" fill-opacity="0.35"/><path d="M9 3.8l2 2.2M15 3.8l-2 2.2" stroke="#52C41A" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11.8a2.5 2.5 0 015 0v1h-5v-1z" fill="#52C41A"/><circle cx="11" cy="11.2" r="0.5" fill="#fff"/><circle cx="13" cy="11.2" r="0.5" fill="#fff"/><path d="M10 19l-1 2m6-2l1 2" stroke="#52C41A" stroke-width="1.5" stroke-linecap="round"/></svg>';
        } elseif ( 'android' === $n || false !== strpos( $n, 'android' ) || false !== strpos( $n, '安卓' ) ) {
            // Android 官方经典绿色机器人
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F6FFED"/><path d="M7.5 9.5a4.5 4.5 0 019 0v2h-9v-2zm-1.8-2.2l1.6 1.4M18.3 7.3l-1.6 1.4M6.5 12.5h11v5.5a2 2 0 01-2 2H8.5a2 2 0 01-2-2v-5.5z" fill="#52C41A"/><circle cx="10" cy="8.8" r="0.8" fill="#fff"/><circle cx="14" cy="8.8" r="0.8" fill="#fff"/></svg>';
        } elseif ( 'ios' === $n ) {
            // iOS 纯正 Apple 经典徽标（标准大比例，饱满清晰）
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F5F5F7"/><path d="M15.98 12.47c-.01-1.21.54-2.13 1.65-2.8-.62-.89-1.56-1.38-2.8-1.47-1.17-.09-2.45.68-2.92.68-.49 0-1.63-.65-2.52-.65C7.55 8.26 5.6 9.7 5.6 12.63q0 1.3.48 2.68c.42 1.21 1.95 4.18 3.54 4.13.83-.02 1.42-.59 2.5-.59 1.05 0 1.59.59 2.52.59 1.6-.02 2.98-2.72 3.39-3.94-2.15-1.01-2.04-2.97-2.04-3.03zm-1.87-5.42c.9-1.07.82-2.04.79-2.39-.8.05-1.72.54-2.24 1.15-.58.65-.92 1.46-.84 2.37.86.07 1.65-.38 2.29-1.13z" fill="#1D1D1F"/></svg>';
        } elseif ( 'ipados' === $n || false !== strpos( $n, 'ipad' ) ) {
            // iPadOS 现代全面屏 iPad (全面屏圆角 + 底部触控指示条)
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#E6F4FF"/><rect x="5" y="4" width="14" height="16" rx="2.5" fill="#BAE0FF" fill-opacity="0.35" stroke="#1677FF" stroke-width="1.6"/><rect x="6.8" y="5.8" width="10.4" height="12.4" rx="1.5" stroke="#1677FF" stroke-width="1" stroke-opacity="0.6"/><path d="M10.5 16.5h3" stroke="#1677FF" stroke-width="1.5" stroke-linecap="round"/></svg>';
        } elseif ( 'tvos' === $n || false !== strpos( $n, 'appletv' ) ) {
            // tvOS 柔和灰底 + 纯正 Apple TV 徽标 (告别死黑突兀块)
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F5F5F7"/><rect x="4.5" y="5.5" width="15" height="13" rx="3.5" fill="#E4E5E8" stroke="#1D1D1F" stroke-width="1.5"/><path d="M8.2 9.5c-.1.2-.4.4-.7.4-.1-.3.2-.6.4-.8.2-.2.5-.3.7-.3 0 .3-.1.5-.4.7zm.8 2.1c0 .7.5 1 .5 1-.2.4-.4.8-.8.8-.3 0-.4-.2-.8-.2s-.5.2-.8.2c-.4 0-.8-.6-1-1.1-.4-1 0-2.1.8-2.1.4 0 .7.2.9.2.2 0 .5-.3.9-.3.2 0 .8.1 1.1.5-.7.3-.7.8-.7.8z" fill="#1D1D1F"/><path d="M12.5 9.2v5M11.5 10.3h2M14.5 11.2l1.2 3.2 1.2-3.2" stroke="#1D1D1F" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        } elseif ( 'macos' === $n || false !== strpos( $n, 'mac' ) ) {
            // macOS 现代 MacBook/iMac 全面屏显示器微标
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#E6F4FF"/><rect x="4.5" y="5.5" width="15" height="10" rx="1.5" stroke="#1677FF" stroke-width="1.6" fill="#BAE0FF" fill-opacity="0.35"/><circle cx="12" cy="7" r="0.6" fill="#1677FF"/><path d="M9.5 18.5h5m-2.5-3v3" stroke="#1677FF" stroke-width="1.6" stroke-linecap="round"/></svg>';
        } elseif ( 'windows' === $n || false !== strpos( $n, 'win' ) ) {
            // Windows 微软 Win11 官方四格徽标
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#E6F4FF"/><path d="M4.5 5.5l6-.8v6.3h-6V5.5zm7-.9l7.5-1v7.2h-7.5V4.6zM4.5 12.5h6v6.3l-6-.8v-5.5zm7 0h7.5v7.2l-7.5-1v-6.2z" fill="#0078D4"/></svg>';
        } elseif ( false !== strpos( $n, 'linux' ) ) {
            // Linux 暖色底经典企鹅
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#FFFBE6"/><path d="M12 4.5a3 3 0 00-3 3v4a3 3 0 006 0v-4a3 3 0 00-3-3z" fill="#262626"/><ellipse cx="12" cy="13" rx="3.5" ry="4" fill="#FAAD14"/><polygon points="11,8 13,8 12,9.5" fill="#FA8C16"/></svg>';
        } elseif ( false !== strpos( $n, '鸿蒙' ) || false !== strpos( $n, 'harmony' ) || false !== strpos( $n, 'hos' ) ) {
            // 鸿蒙系统 (HarmonyOS) 官方双环微标
            $svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#EBF3FF"/><circle cx="12" cy="10" r="4.2" stroke="#0A59F7" stroke-width="2.2"/><path d="M6 16.8h12" stroke="#0A59F7" stroke-width="2.2" stroke-linecap="round"/></svg>';
        } else {
            // 统一默认图标（未预置专属图标的新增平台）
            $svg = self::get_default_item_icon_svg();
        }

        return '<span class="ae-item-icon">' . $svg . '</span>';
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

        $input = isset( $_POST['filter_categories'] ) ? wp_unslash( $_POST['filter_categories'] ) : '';
        $cats  = $this->sanitize_category_list( $input );

        $renames_raw = isset( $_POST['filter_category_renames'] ) ? wp_unslash( $_POST['filter_category_renames'] ) : '';
        $renames     = $this->sanitize_rename_map( $renames_raw, $cats );

        update_option( 'apps_exhibition_filter_categories', $cats );

        $hide_all = ! empty( $_POST['hide_all_category'] ) ? 1 : 0;
        update_option( 'apps_exhibition_hide_all_category', $hide_all );

        // 保存分类自定义图标映射
        $icons_raw = isset( $_POST['filter_category_icons'] ) ? wp_unslash( $_POST['filter_category_icons'] ) : '';
        $icons     = $this->sanitize_icon_map( $icons_raw, $cats, $renames, 'apps_exhibition_category_icons' );
        update_option( 'apps_exhibition_category_icons', $icons );

        // 改名自动迁移：把应用表中的旧分类名替换为新名，并同步分类排序的 key
        if ( ! empty( $renames ) ) {
            $this->migrate_category_names( $renames, 'app_filter_category' );
            $this->migrate_category_order_keys( $renames );
        }

        self::clear_frontend_cache();

        wp_safe_redirect( add_query_arg( 'message', 'cat_saved', admin_url( 'admin.php?page=apps-exhibition&tab=settings' ) ) );
        exit;
    }

    public function handle_platform_categories_form() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( '无权限访问', 'apps-exhibition' ) );
        }
        check_admin_referer( 'apps_exhibition_platform_categories' );

        $input     = isset( $_POST['platform_categories'] ) ? wp_unslash( $_POST['platform_categories'] ) : '';
        $platforms = $this->sanitize_category_list( $input );

        $renames_raw = isset( $_POST['platform_category_renames'] ) ? wp_unslash( $_POST['platform_category_renames'] ) : '';
        $renames     = $this->sanitize_rename_map( $renames_raw, $platforms );

        update_option( 'apps_exhibition_platform_categories', $platforms );

        // 保存平台自定义图标映射
        $icons_raw = isset( $_POST['platform_category_icons'] ) ? wp_unslash( $_POST['platform_category_icons'] ) : '';
        $icons     = $this->sanitize_icon_map( $icons_raw, $platforms, $renames, 'apps_exhibition_platform_icons' );
        update_option( 'apps_exhibition_platform_icons', $icons );

        // 改名自动迁移：把应用表中的旧平台名替换为新名
        if ( ! empty( $renames ) ) {
            $this->migrate_category_names( $renames, 'app_platforms' );
        }

        self::clear_frontend_cache();

        wp_safe_redirect( add_query_arg( 'message', 'platform_saved', admin_url( 'admin.php?page=apps-exhibition&tab=settings' ) ) );
        exit;
    }

    /**
     * 清洗分类/平台列表：兼容换行与逗号两种分隔方式，去空白、去空项、去重。
     *
     * 分类名以逗号拼接存储于应用表，名称本身不允许含逗号，因此这里把逗号一并
     * 当作分隔符；同时兼容旧版「每行一个」的提交格式。
     *
     * @param string $input 原始输入（换行/逗号分隔）。
     * @return string[] 清洗后的名称列表。
     */
    private function sanitize_category_list( $input ) {
        $parts = preg_split( '/[\r\n,，]+/', (string) $input );
        if ( ! is_array( $parts ) ) {
            return [];
        }

        $items = array_filter( array_map( function ( $part ) {
            return sanitize_text_field( trim( $part ) );
        }, $parts ), function ( $v ) {
            return $v !== '';
        } );

        // 忽略大小写去重：与前端「同名不可添加」策略保持一致
        $unique = [];
        $seen   = [];
        foreach ( $items as $item ) {
            $key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $item, 'UTF-8' ) : strtolower( $item );
            if ( isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;
            $unique[]     = $item;
        }

        return array_values( $unique );
    }

    /**
     * 清洗「老名 → 新名」改名映射。
     *
     * 仅接受合法映射：新旧名均非空且不同；新名必须存在于保存后的列表
     * （防止产生悬空名称）；旧名不得仍留在列表中（否则应视为新增而非改名）。
     *
     * @param string   $raw_json    前端提交的 JSON 映射。
     * @param string[] $allowed_new 保存后的有效名称列表。
     * @return array<string,string> 清洗后的映射。
     */
    private function sanitize_rename_map( $raw_json, array $allowed_new ) {
        $raw = json_decode( (string) $raw_json, true );
        if ( ! is_array( $raw ) ) {
            return [];
        }

        $map = [];
        foreach ( $raw as $old => $new ) {
            if ( ! is_string( $old ) && ! is_int( $old ) ) {
                continue;
            }

            $old = sanitize_text_field( (string) $old );
            $new = sanitize_text_field( (string) $new );

            if ( '' === $old || '' === $new || $old === $new ) {
                continue;
            }
            if ( ! in_array( $new, $allowed_new, true ) ) {
                continue;
            }
            if ( in_array( $old, $allowed_new, true ) ) {
                continue;
            }

            $map[ $old ] = $new;
        }

        return $map;
    }

    /**
     * 清洗分类/平台的自定义图标映射。
     *
     * @param string|array $raw_json    前端提交的 JSON 映射（或数组）。
     * @param string[]     $valid_names 当前有效的分类/平台名称列表。
     * @param array        $renames     改名映射表 [ '旧名' => '新名' ]。
     * @param string       $option_key  既有配置 option 键名，用于改名时的平滑迁移。
     * @return array<string,string> 清洗后的图标映射 [ '名称' => 'URL' ]。
     */
    private function sanitize_icon_map( $raw_json, array $valid_names, array $renames = [], $option_key = '' ) {
        $raw = is_array( $raw_json ) ? $raw_json : json_decode( (string) $raw_json, true );
        if ( ! is_array( $raw ) ) {
            $raw = [];
        }

        // 结合已保存的旧映射，防止改名时旧名称的图标丢失
        if ( ! empty( $option_key ) && ! empty( $renames ) ) {
            $existing = get_option( $option_key, [] );
            if ( is_array( $existing ) ) {
                foreach ( $renames as $old_name => $new_name ) {
                    if ( isset( $existing[ $old_name ] ) && ! isset( $raw[ $new_name ] ) ) {
                        $raw[ $new_name ] = $existing[ $old_name ];
                    }
                }
            }
        }

        $cleaned    = [];
        $valid_flip = array_flip( $valid_names );

        foreach ( $raw as $name => $url ) {
            $name = trim( (string) $name );
            $url  = trim( (string) $url );

            // 仅保留当前有效名称中的项目且 URL 非空合法
            if ( isset( $valid_flip[ $name ] ) && ! empty( $url ) ) {
                $safe_url = esc_url_raw( $url );
                if ( self::is_safe_url( $safe_url ) ) {
                    $cleaned[ $name ] = $safe_url;
                }
            }
        }

        return $cleaned;
    }

    /**
     * 按改名映射迁移应用表中以逗号拼接的分类字段。
     *
     * 逐条解析 token 后映射，而非字符串直接替换，可正确处理「老名是新名子串」等情况。
     *
     * @param array<string,string> $map    老名 → 新名。
     * @param string               $column 目标字段（仅允许 app_filter_category / app_platforms）。
     * @return int 受影响的应用行数。
     */
    private function migrate_category_names( array $map, $column ) {
        if ( empty( $map ) || ! in_array( $column, [ 'app_filter_category', 'app_platforms' ], true ) ) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'apps_exhibition';

        $rows = $wpdb->get_results( "SELECT id, {$column} AS val FROM {$table}", ARRAY_A );
        if ( empty( $rows ) ) {
            return 0;
        }

        $changed = 0;
        foreach ( $rows as $row ) {
            $tokens = array_filter( array_map( 'trim', explode( ',', (string) $row['val'] ) ), function ( $token ) {
                return $token !== '';
            } );

            $touched    = false;
            $new_tokens = [];
            foreach ( $tokens as $token ) {
                if ( isset( $map[ $token ] ) && $map[ $token ] !== $token ) {
                    $new_tokens[] = $map[ $token ];
                    $touched      = true;
                } else {
                    $new_tokens[] = $token;
                }
            }

            $new_tokens = array_values( array_unique( $new_tokens ) );
            $new_val    = implode( ',', $new_tokens );

            if ( $touched && $new_val !== (string) $row['val'] ) {
                $wpdb->update( $table, [ $column => $new_val ], [ 'id' => (int) $row['id'] ], [ '%s' ], [ '%d' ] );
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * 同步「分类内应用排序」option 的 key，让分类改名后自定义排序不丢失。
     *
     * @param array<string,string> $map 老名 → 新名。
     */
    private function migrate_category_order_keys( array $map ) {
        if ( empty( $map ) ) {
            return;
        }

        $orders = get_option( 'apps_exhibition_category_order', [] );
        if ( ! is_array( $orders ) || empty( $orders ) ) {
            return;
        }

        $new_orders = [];
        $dirty      = false;
        foreach ( $orders as $cat => $list ) {
            if ( isset( $map[ $cat ] ) ) {
                $cat   = $map[ $cat ];
                $dirty = true;
            }
            $new_orders[ $cat ] = $list;
        }

        if ( $dirty ) {
            update_option( 'apps_exhibition_category_order', $new_orders );
        }
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

    /**
     * 资源缓存版本号：文件修改时间戳 + 插件版本。
     *
     * 单纯依赖 VERSION 常量时，改动 JS/CSS 后若忘记递增版本号（或浏览器/
     * CDN 缓存异常顽固），客户端会继续运行旧脚本，出现"新按钮永远禁用"这类
     * 新旧代码混跑的怪象。绑定 filemtime 后文件一变 URL 必变，彻底免疫缓存。
     */
    private function asset_version( $relative_path ) {
        $file  = $this->plugin_path . ltrim( $relative_path, '/' );
        $mtime = file_exists( $file ) ? filemtime( $file ) : false;

        return $mtime ? $mtime . '-' . self::VERSION : self::VERSION;
    }

    public function admin_enqueue_scripts( $hook_suffix ) {
        if ( $hook_suffix !== 'toplevel_page_apps-exhibition' ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_style( 'apps-exhibition-admin-style', $this->plugin_url . 'assets/css/admin.css', [], $this->asset_version( 'assets/css/admin.css' ) );
        wp_enqueue_script( 'apps-exhibition-admin-script', $this->plugin_url . 'assets/js/admin.js', [ 'jquery', 'jquery-ui-sortable' ], $this->asset_version( 'assets/js/admin.js' ), true );

        wp_localize_script( 'apps-exhibition-admin-script', 'appsExhibitionL10n', [
            'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
            'sortNonce'          => wp_create_nonce( 'apps_exhibition_sort_nonce' ),
            'categoryOrders'     => get_option( 'apps_exhibition_category_order', [] ),
            'noResults'          => __( '未找到符合条件的应用。', 'apps-exhibition' ),
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
            'removePosterBtn'    => __( '删除图片', 'apps-exhibition' ),
            'dragToSort'         => __( '拖拽调整顺序', 'apps-exhibition' ),
            'posterOrderChanged' => __( '顺序已调整，请点击「保存海报配置」生效', 'apps-exhibition' ),
            'confirmChangePosterTitle' => __( '确认更换海报图片？', 'apps-exhibition' ),
            'confirmRemovePosterTitle' => __( '确认删除海报图片？', 'apps-exhibition' ),
            'confirmChangePosterLead'  => __( '即将用新选择的图片替换当前海报图片：', 'apps-exhibition' ),
            'confirmRemovePosterLead'  => __( '即将删除以下海报图片：', 'apps-exhibition' ),
            'confirmChangePosterBtn'   => __( '确认更换', 'apps-exhibition' ),
            'confirmRemovePosterBtn'   => __( '确认删除', 'apps-exhibition' ),
            'deleteBtn'          => __( '删除', 'apps-exhibition' ),
            'editBtn'            => __( '编辑', 'apps-exhibition' ),
            'downloadUrlPlc'     => __( '下载链接 URL', 'apps-exhibition' ),
            'downloadTextPlc'    => __( '按钮文字', 'apps-exhibition' ),
            'downloadTextDefault' => __( '下载', 'apps-exhibition' ),
            'downloadAddrPlc'    => __( '下载地址', 'apps-exhibition' ),
            'confirmDelete'      => __( '确认删除？', 'apps-exhibition' ),
            'cancel'             => __( '取消', 'apps-exhibition' ),
            'confirmBulkDelete'  => __( '确定要删除选中的应用吗？此操作不可恢复。', 'apps-exhibition' ),
            'noBulkSelected'     => __( '请先选择要删除的应用。', 'apps-exhibition' ),
            // 二次确认弹窗通用文案
            'confirmTitle'       => __( '请确认操作', 'apps-exhibition' ),
            'confirmBtn'         => __( '确认', 'apps-exhibition' ),
            // 删除应用
            'confirmDeleteAppTitle'  => __( '确认删除该应用？', 'apps-exhibition' ),
            'confirmDeleteAppLead'   => __( '即将删除应用：', 'apps-exhibition' ),
            'confirmDeleteAppItem1'  => __( '点击「确认删除」后会立即执行删除，无需额外保存；', 'apps-exhibition' ),
            'confirmDeleteAppItem2'  => __( '该应用数据与其图标将从数据库和媒体库中被永久删除（含各种缩略图尺寸），删除后无法恢复；', 'apps-exhibition' ),
            'confirmDeleteAppItem3'  => __( '若该图标仍被其他应用或首页海报引用，则会自动保留，不会误删。', 'apps-exhibition' ),
            'confirmDeleteAppNotice' => __( '温馨提示：此操作不可恢复；如需保留该应用，请点击「取消」。', 'apps-exhibition' ),
            'confirmDeleteBtn'       => __( '确认删除', 'apps-exhibition' ),
            // 批量删除
            'confirmBulkDeleteTitle'  => __( '确认批量删除所选应用？', 'apps-exhibition' ),
            'confirmBulkDeleteLead'   => __( '即将删除所选的 %d 个应用：', 'apps-exhibition' ),
            'confirmBulkDeleteItem1'  => __( '点击「确认删除」后会立即执行删除，无需额外保存；', 'apps-exhibition' ),
            'confirmBulkDeleteItem2'  => __( '这些应用及其图标将从数据库和媒体库中被永久删除（含各种缩略图尺寸），删除后无法恢复；', 'apps-exhibition' ),
            'confirmBulkDeleteItem3'  => __( '若某图标仍被其他应用或首页海报引用，则会自动保留，不会误删。', 'apps-exhibition' ),
            'confirmBulkDeleteNotice' => __( '温馨提示：此操作不可恢复；如需保留，请点击「取消」。', 'apps-exhibition' ),
            // 删除下载链接
            'confirmRemoveDownloadTitle'  => __( '确认删除该下载链接？', 'apps-exhibition' ),
            'confirmRemoveDownloadLead'   => __( '即将从当前表单中移除以下下载链接：', 'apps-exhibition' ),
            'confirmRemoveDownloadTarget' => __( '下载链接：', 'apps-exhibition' ),
            'confirmRemoveDownloadEmpty'  => __( '该下载链接尚未填写完整内容', 'apps-exhibition' ),
            'confirmRemoveDownloadLast'   => __( '这是最后一个下载链接，确认后该行会被清空（每个应用至少需要保留一个下载链接）；', 'apps-exhibition' ),
            'confirmRemoveDownloadItem1'  => __( '该下载链接会立即从当前表单中移除；', 'apps-exhibition' ),
            'confirmRemoveDownloadItem2'  => __( '点击表单中的「保存」后，更改才会真正保存；', 'apps-exhibition' ),
            'confirmRemoveDownloadItem3'  => __( '未点击「保存」前关闭窗口，将恢复原有的下载链接。', 'apps-exhibition' ),
            'confirmRemoveDownloadNotice' => __( '温馨提示：删除将在点击「保存」后生效；如需保留，请点击「取消」。', 'apps-exhibition' ),
            'confirmRemoveDownloadBtn'    => __( '确认删除', 'apps-exhibition' ),
            // 更换应用图标（保存后删除原图标）
            'confirmChangeIconTitle'  => __( '确认更换应用图标？', 'apps-exhibition' ),
            'confirmChangeIconLead'   => __( '即将用新选择的图标替换当前图标：', 'apps-exhibition' ),
            'confirmChangeIconItem1'  => __( '新图标会立即显示在表单预览中，此时尚未保存；', 'apps-exhibition' ),
            'confirmChangeIconItem2'  => __( '点击「保存」后，原图标将从 WordPress 媒体库中被永久删除（含各种缩略图尺寸），删除后无法恢复；', 'apps-exhibition' ),
            'confirmChangeIconItem3'  => __( '若原图标仍被其他应用或首页海报引用，则会自动保留，不会误删；', 'apps-exhibition' ),
            'confirmChangeIconItem4'  => __( '未点击「保存」前取消或关闭窗口，将保留原图标。', 'apps-exhibition' ),
            'confirmChangeIconNotice' => __( '温馨提示：更换将在点击「保存」后生效；如需保留原图标，请点击「取消」。', 'apps-exhibition' ),
            'confirmChangeIconBtn'    => __( '确认更换', 'apps-exhibition' ),
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
            // 分类设置 - 标签输入
            'tagInputEmpty'           => __( '请输入名称后再添加。', 'apps-exhibition' ),
            'tagAdded'                => __( '已添加「%s」，点击下方保存后生效。', 'apps-exhibition' ),
            'tagTooLong'              => sprintf( __( '单项名称不能超过 %d 个字符。', 'apps-exhibition' ), 30 ),
            'tagMax'                  => sprintf( __( '最多只能添加 %d 项。', 'apps-exhibition' ), 50 ),
            'tagDuplicate'            => __( '「%s」已存在，不可添加。', 'apps-exhibition' ),
            'tagDuplicateRename'      => __( '「%s」已存在，无法改为该名称。', 'apps-exhibition' ),
            'tagEmpty'                => __( '请至少保留一项后再保存。', 'apps-exhibition' ),
            'confirmRemoveItemTitle'  => __( '确认删除该项？', 'apps-exhibition' ),
            'confirmRemoveItemLead'   => __( '即将删除：', 'apps-exhibition' ),
            'confirmRemoveItemItem1'  => __( '该项会立即从当前列表中移除；', 'apps-exhibition' ),
            'confirmRemoveItemItem2'  => __( '点击「保存」后更改才会真正生效；', 'apps-exhibition' ),
            'confirmRemoveItemItem3'  => __( '未点击「保存」前关闭页面，将保留原有分类。', 'apps-exhibition' ),
            'confirmRemoveItemNotice' => __( '温馨提示：删除将在点击「保存」后生效；如需保留，请点击「取消」。', 'apps-exhibition' ),
            'confirmRemoveItemBtn'    => __( '确认删除', 'apps-exhibition' ),
            // 分类与平台自定义图标
            'chooseCategoryIcon'      => __( '选择或上传分类图标 (SVG/PNG/WebP/JPG)', 'apps-exhibition' ),
            'choosePlatformIcon'      => __( '选择或上传平台图标 (SVG/PNG/WebP/JPG)', 'apps-exhibition' ),
            'useIcon'                 => __( '使用此图标', 'apps-exhibition' ),
            'clickToChangeIcon'       => __( '点击更换图标', 'apps-exhibition' ),
            'changeIconBtn'           => __( '更换图标', 'apps-exhibition' ),
            'resetDefaultIcon'        => __( '恢复默认图标', 'apps-exhibition' ),
            'iconChangedHint'         => __( '已更换图标，点击下方保存后生效。', 'apps-exhibition' ),
            'iconResetHint'           => __( '已恢复默认图标，点击下方保存后生效。', 'apps-exhibition' ),
        ] );
    }

    public function frontend_register_scripts() {
        wp_register_style( 'apps-exhibition-style', $this->plugin_url . 'assets/css/apps-exhibition.css', [], $this->asset_version( 'assets/css/apps-exhibition.css' ) );
        // Swiper 改为本地加载：规避 CDN 供应链风险与国内网络不稳定导致的轮播失效
        wp_register_style( 'swiper-css', $this->plugin_url . 'assets/vendor/swiper-bundle.min.css', [], '10.3.1' );
        wp_register_script( 'swiper-js', $this->plugin_url . 'assets/vendor/swiper-bundle.min.js', [], '10.3.1', true );
        wp_register_script( 'apps-exhibition-front', $this->plugin_url . 'assets/js/apps-exhibition.js', [ 'swiper-js' ], $this->asset_version( 'assets/js/apps-exhibition.js' ), true );
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

        // 记录被本次保存移除的海报图片，保存后清理媒体库孤儿图片
        $old_urls = [];
        foreach ( Apps_Exhibition::get_home_posters() as $poster ) {
            if ( isset( $poster['url'] ) && '' !== $poster['url'] ) {
                $old_urls[] = $poster['url'];
            }
        }
        $new_urls = wp_list_pluck( $posters, 'url' );
        $removed_urls = array_diff( $old_urls, $new_urls );

        update_option( self::POSTERS_OPTION, $posters );
        delete_option( self::POSTERS_OPTION_OLD );

        self::clear_frontend_cache();

        // 清理不再被任何应用或海报引用的媒体库图片（仍被引用的会自动跳过）
        if ( ! empty( $removed_urls ) && function_exists( 'apps_exhibition_delete_orphan_attachments' ) ) {
            apps_exhibition_delete_orphan_attachments( $removed_urls );
        }

        wp_safe_redirect( add_query_arg( [ 'message' => 'home_posters_saved' ], wp_get_referer() ) );
        exit;
    }
}

Apps_Exhibition::get_instance();
