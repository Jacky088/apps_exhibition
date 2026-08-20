<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 对已编码的字符串先解码再编码，保证重复调用结果一致（幂等）。
 *
 * 直接 rawurlencode 会把用户粘贴的、已经编码过的 URL 二次编码
 * （例如 %E4%B8%AD 变成 %25E4%25B8%25AD），导致链接失效。
 */
function apps_exhibition_encode_once( $value, $preserve = '' ) {
    $decoded = rawurldecode( $value );
    $encoded = rawurlencode( $decoded );

    // 还原那些在该 URL 组件中具有语义、不应被编码的保留字符
    $length = strlen( $preserve );
    for ( $i = 0; $i < $length; $i++ ) {
        $char = $preserve[ $i ];
        $encoded = str_replace( rawurlencode( $char ), $char, $encoded );
    }

    return $encoded;
}

/**
 * 尝试对用户输入的 URL 进行局部编码（path/query/fragment），以兼容包含中文或特殊字符的链接
 * 返回编码后的 URL（或原始输入，当解析失败时）
 *
 * 该函数是幂等的：对同一个 URL 多次调用结果不变。
 */
function sanitize_and_normalize_url( $url ) {
    $url = trim( $url );
    if ( $url === '' ) return $url;

    $parts = @parse_url( $url );
    if ( ! $parts || ! isset( $parts['scheme'] ) || ! isset( $parts['host'] ) ) {
        return $url;
    }

    $normalized = '';
    $normalized .= $parts['scheme'] . '://';

    if ( isset( $parts['user'] ) ) {
        $normalized .= $parts['user'];
        if ( isset( $parts['pass'] ) ) {
            $normalized .= ':' . $parts['pass'];
        }
        $normalized .= '@';
    }

    $normalized .= $parts['host'];
    if ( isset( $parts['port'] ) ) {
        $normalized .= ':' . $parts['port'];
    }

    if ( isset( $parts['path'] ) ) {
        // 对 path 的每个段做一次性编码，保留 '/' 作为分隔符
        $segments = explode( '/', $parts['path'] );
        $segments = array_map( function ( $segment ) {
            // 路径中这些子分隔符合法，无需编码
            return apps_exhibition_encode_once( $segment, '@:$,;+!*\'()' );
        }, $segments );
        $normalized .= implode( '/', $segments );
    }

    if ( isset( $parts['query'] ) ) {
        // 对 query 的键和值分别做一次性编码，保留 & = 结构
        $pairs = explode( '&', $parts['query'] );
        $encPairs = array();
        foreach ( $pairs as $p ) {
            if ( $p === '' ) {
                continue;
            }
            if ( strpos( $p, '=' ) !== false ) {
                list( $k, $v ) = explode( '=', $p, 2 );
                $encPairs[] = apps_exhibition_encode_once( $k ) . '=' . apps_exhibition_encode_once( $v );
            } else {
                $encPairs[] = apps_exhibition_encode_once( $p );
            }
        }
        $normalized .= '?' . implode( '&', $encPairs );
    }

    if ( isset( $parts['fragment'] ) ) {
        $normalized .= '#' . apps_exhibition_encode_once( $parts['fragment'], '/?' );
    }

    return $normalized;
}

/**
 * 读取应用的下载链接。
 *
 * 新数据以 JSON 存储；旧数据为 PHP 序列化字符串，
 * 这里做双向兼容，保证升级后历史数据仍可正常读取。
 */
function apps_exhibition_parse_downloads( $raw ) {
    if ( empty( $raw ) ) {
        return [];
    }

    if ( is_array( $raw ) ) {
        return $raw;
    }

    $decoded = json_decode( $raw, true );
    if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
        return $decoded;
    }

    // 回退：兼容旧的 PHP 序列化格式
    $legacy = maybe_unserialize( $raw );
    return is_array( $legacy ) ? $legacy : [];
}

/**
 * 处理表单提交（新增 / 编辑应用）
 */
function apps_exhibition_handle_form() {
    global $wpdb;

    $plugin = Apps_Exhibition::get_instance();
    $table  = $wpdb->prefix . 'apps_exhibition';

    $id = isset( $_POST['app_id'] ) ? intval( $_POST['app_id'] ) : 0;

    // Fail Fast 验证（wp_unslash 去除 WP 魔术引号，避免含引号输入入库残留反斜杠）
    $app_name = sanitize_text_field( wp_unslash( $_POST['app_name'] ?? '' ) );
    if ( empty( $app_name ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '应用名称不能为空。', 'apps-exhibition' ), 'error' );
        return false;
    }

    $app_description = sanitize_textarea_field( wp_unslash( $_POST['app_description'] ?? '' ) );
    if ( empty( $app_description ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '应用描述不能为空。', 'apps-exhibition' ), 'error' );
        return false;
    }

    $app_icon = esc_url_raw( wp_unslash( $_POST['app_icon'] ?? '' ) );
    if ( empty( $app_icon ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '应用图标不能为空。', 'apps-exhibition' ), 'error' );
        return false;
    }

    $platform_options = $plugin->get_platform_categories();
    $app_platforms_raw = wp_unslash( $_POST['app_platforms'] ?? [] );
    $app_platforms_selected = [];
    if ( is_array( $app_platforms_raw ) ) {
        $app_platforms_selected = array_intersect( array_map( 'sanitize_text_field', $app_platforms_raw ), $platform_options );
    }
    if ( empty( $app_platforms_selected ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '请至少选择一个平台分类。', 'apps-exhibition' ), 'error' );
        return false;
    }
    $app_platforms_str = implode( ',', $app_platforms_selected );

    $app_filter_raw = wp_unslash( $_POST['app_filter_category'] ?? [] );
    $app_filter_selected = [];
    if ( is_array( $app_filter_raw ) ) {
        $app_filter_selected = array_filter( array_map( 'sanitize_text_field', $app_filter_raw ), function($val) { return $val !== ''; } );
    }
    if ( empty( $app_filter_selected ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '请至少选择一个筛选分类。', 'apps-exhibition' ), 'error' );
        return false;
    }
    $app_filter_str = implode( ',', $app_filter_selected );

    $download_urls  = wp_unslash( $_POST['download_url'] ?? [] );
    $download_texts = wp_unslash( $_POST['download_text'] ?? [] );
    $downloads      = [];

    if ( ! is_array( $download_urls ) ) $download_urls = [];
    if ( ! is_array( $download_texts ) ) $download_texts = [];

    $has_valid_download = false;
    for ( $i = 0; $i < count( $download_urls ); $i++ ) {
        $url  = sanitize_text_field( trim( $download_urls[ $i ] ?? '' ) );
        $text = sanitize_text_field( trim( $download_texts[ $i ] ?? '' ) );

        if ( ! empty( $url ) && ! empty( $text ) ) {
            // 先对 URL 做兼容性处理（编码 path/query/fragment），再做格式与协议白名单校验
            $normalized_url = sanitize_and_normalize_url( $url );
            if ( ! Apps_Exhibition::is_safe_url( $normalized_url ) ) {
                add_settings_error( 'apps_exhibition_messages', 'error', sprintf( __( '第%d个下载链接无效（仅支持 http/https 链接）。', 'apps-exhibition' ), $i + 1 ), 'error' );
                return false;
            }
            if ( count( $downloads ) >= 3 ) {
                add_settings_error( 'apps_exhibition_messages', 'error', __( '最多只能添加3个下载链接。', 'apps-exhibition' ), 'error' );
                return false;
            }
            $downloads[] = [ 'url' => $normalized_url, 'text' => $text ];
            $has_valid_download = true;
        } elseif ( ! empty( $url ) || ! empty( $text ) ) {
            add_settings_error( 'apps_exhibition_messages', 'error', sprintf( __( '第%d个下载链接或按钮文字缺失，请填写完整或清除。', 'apps-exhibition' ), $i + 1 ), 'error' );
            return false;
        }
    }

    if ( ! $has_valid_download ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '请至少填写一个下载链接。', 'apps-exhibition' ), 'error' );
        return false;
    }

    $data = [
        'app_name'            => $app_name,
        'app_description'     => $app_description,
        'app_icon'            => $app_icon,
        'app_platforms'       => $app_platforms_str,
        'app_filter_category' => $app_filter_str,
        'app_downloads'       => wp_json_encode( $downloads ),
    ];
    $formats = [ '%s', '%s', '%s', '%s', '%s', '%s' ];

    if ( $id > 0 ) {
        $updated = $wpdb->update( $table, $data, [ 'id' => $id ], $formats, [ '%d' ] );
        if ( $updated === false ) {
            add_settings_error( 'apps_exhibition_messages', 'error', __( '更新应用失败。', 'apps-exhibition' ), 'error' );
            return false;
        }
        Apps_Exhibition::clear_frontend_cache();
        return true;
    } else {
        $inserted = $wpdb->insert( $table, $data, $formats );
        if ( ! $inserted ) {
            add_settings_error( 'apps_exhibition_messages', 'error', __( '新增应用失败。', 'apps-exhibition' ), 'error' );
            return false;
        }
        Apps_Exhibition::clear_frontend_cache();
        return true;
    }
}

function apps_exhibition_handle_form_post() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( '无权限访问', 'apps-exhibition' ) );
    }
    check_admin_referer( 'apps_exhibition_form' );

    $result = apps_exhibition_handle_form();

    $redirect_url = admin_url( 'admin.php?page=apps-exhibition' );
    if ( $result === true ) {
        $msg_code = ( isset( $_POST['app_id'] ) && intval( $_POST['app_id'] ) > 0 ) ? 'updated' : 'inserted';
    } else {
        // 暂存具体校验错误，跳转后由列表页还原展示（见 templates/admin-page.php）
        $errors = get_settings_errors( 'apps_exhibition_messages' );
        if ( ! empty( $errors ) ) {
            set_transient( Apps_Exhibition::FORM_ERRORS_TRANSIENT . get_current_user_id(), $errors, 60 );
        }
        $msg_code = 'error';
    }
    wp_safe_redirect( add_query_arg( 'message', $msg_code, $redirect_url ) );
    exit;
}

function apps_exhibition_handle_delete() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( '无权限访问', 'apps-exhibition' ) );
    }

    $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
    if ( ! $id || ! check_admin_referer( 'apps_exhibition_delete_' . $id ) ) {
        wp_die( __( '安全验证失败', 'apps-exhibition' ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'apps_exhibition';
    $deleted = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

    if ( $deleted !== false ) {
        Apps_Exhibition::clear_frontend_cache();
    }

    $msg_code = ( $deleted === false ) ? 'delete_error' : 'deleted';
    wp_safe_redirect( add_query_arg( 'message', $msg_code, admin_url( 'admin.php?page=apps-exhibition' ) ) );
    exit;
}

function apps_exhibition_handle_bulk_delete() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( '无权限访问', 'apps-exhibition' ) );
    }
    check_admin_referer( 'apps_exhibition_bulk_delete' );

    $ids = isset( $_POST['app_ids'] ) ? $_POST['app_ids'] : [];
    if ( ! is_array( $ids ) || empty( $ids ) ) {
        wp_safe_redirect( add_query_arg( 'message', 'error', admin_url( 'admin.php?page=apps-exhibition' ) ) );
        exit;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'apps_exhibition';

    $deleted_count = 0;
    foreach ( $ids as $id ) {
        $id = intval( $id );
        if ( $id > 0 && $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] ) ) {
            $deleted_count++;
        }
    }

    if ( $deleted_count > 0 ) {
        Apps_Exhibition::clear_frontend_cache();
    }

    wp_safe_redirect( add_query_arg( 'message', 'deleted', admin_url( 'admin.php?page=apps-exhibition' ) ) );
    exit;
}

/**
 * 批量移动分类：将所选应用的源分类替换为目标分类（源为空时覆盖全部分类）。
 *
 * 主要用于分类重命名后的数据迁移：在「分类设置」中改名后，历史应用
 * 仍挂旧分类名，此处批量勾选并移动到新分类完成迁移。
 *
 * 两种语义：
 * - source 为具体分类：仅替换该分类，应用的其他分类保留（不含源分类的应用跳过）
 * - source 为空（"全部"）：所选应用的分类整体覆盖为目标分类
 */
function apps_exhibition_handle_bulk_move() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( '无权限访问', 'apps-exhibition' ) );
    }
    check_admin_referer( 'apps_exhibition_bulk_move' );

    // app_ids 为模态框 JS 汇总勾选项生成的逗号分隔串
    $ids_raw = isset( $_POST['app_ids'] ) ? wp_unslash( $_POST['app_ids'] ) : '';
    $ids     = array_filter( array_map( 'intval', explode( ',', $ids_raw ) ) );

    $source = isset( $_POST['source_category'] ) ? sanitize_text_field( wp_unslash( $_POST['source_category'] ) ) : '';
    $target = isset( $_POST['target_category'] ) ? sanitize_text_field( wp_unslash( $_POST['target_category'] ) ) : '';

    // 目标分类必须存在于当前筛选分类列表（与「分类设置」实时同步的白名单）
    $valid_categories = Apps_Exhibition::get_instance()->get_filter_categories();

    $redirect = function ( $code ) {
        wp_safe_redirect( add_query_arg( 'message', $code, admin_url( 'admin.php?page=apps-exhibition' ) ) );
        exit;
    };

    if ( empty( $ids ) || '' === $target || ! in_array( $target, $valid_categories, true ) ) {
        $redirect( 'bulk_move_error' );
    }

    if ( '' !== $source && $source === $target ) {
        $redirect( 'bulk_move_same' );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'apps_exhibition';

    // 一次性取回所选应用的当前分类，PHP 内完成替换后逐条回写
    $ids_csv = implode( ',', array_map( 'intval', $ids ) );
    $rows    = $wpdb->get_results( "SELECT id, app_filter_category FROM {$table} WHERE id IN ({$ids_csv})", ARRAY_A );

    if ( empty( $rows ) ) {
        $redirect( 'bulk_move_error' );
    }

    $moved = 0;
    foreach ( $rows as $row ) {
        $cats = array_values( array_filter( array_map( 'trim', explode( ',', $row['app_filter_category'] ) ) ) );

        if ( '' === $source ) {
            // 覆盖模式：分类直接设为目标分类
            $new_cats = [ $target ];
        } else {
            // 替换模式：仅将源分类换成目标分类（保持原位置），不含源分类的应用跳过
            $key = array_search( $source, $cats, true );
            if ( false === $key ) {
                continue;
            }
            $cats[ $key ] = $target;
            $new_cats     = array_values( array_unique( $cats ) );
        }

        $new_str = implode( ',', $new_cats );
        if ( $new_str === $row['app_filter_category'] ) {
            continue;
        }

        $wpdb->update(
            $table,
            [ 'app_filter_category' => $new_str ],
            [ 'id' => $row['id'] ],
            [ '%s' ],
            [ '%d' ]
        );
        $moved++;
    }

    if ( $moved > 0 ) {
        Apps_Exhibition::clear_frontend_cache();
    }

    $redirect( 'bulk_moved' );
}

function apps_exhibition_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( '无权限访问', 'apps-exhibition' ) );
    }

    global $wpdb;
    $plugin = Apps_Exhibition::get_instance();
    $table  = $wpdb->prefix . 'apps_exhibition';

    include APPS_EXHIBITION_PATH . 'templates/admin-page.php';
}
