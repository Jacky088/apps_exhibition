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

    // Fail Fast 验证
    $app_name = sanitize_text_field( $_POST['app_name'] ?? '' );
    if ( empty( $app_name ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '应用名称不能为空。', 'apps-exhibition' ), 'error' );
        return false;
    }

    $app_description = sanitize_textarea_field( $_POST['app_description'] ?? '' );
    if ( empty( $app_description ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '应用描述不能为空。', 'apps-exhibition' ), 'error' );
        return false;
    }

    $app_icon = esc_url_raw( $_POST['app_icon'] ?? '' );
    if ( empty( $app_icon ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '应用图标不能为空。', 'apps-exhibition' ), 'error' );
        return false;
    }

    $platform_options = $plugin->get_platform_categories();
    $app_platforms_raw = $_POST['app_platforms'] ?? [];
    $app_platforms_selected = [];
    if ( is_array( $app_platforms_raw ) ) {
        $app_platforms_selected = array_intersect( array_map( 'sanitize_text_field', $app_platforms_raw ), $platform_options );
    }
    if ( empty( $app_platforms_selected ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '请至少选择一个平台分类。', 'apps-exhibition' ), 'error' );
        return false;
    }
    $app_platforms_str = implode( ',', $app_platforms_selected );

    $app_filter_raw = $_POST['app_filter_category'] ?? [];
    $app_filter_selected = [];
    if ( is_array( $app_filter_raw ) ) {
        $app_filter_selected = array_filter( array_map( 'sanitize_text_field', $app_filter_raw ), function($val) { return $val !== ''; } );
    }
    if ( empty( $app_filter_selected ) ) {
        add_settings_error( 'apps_exhibition_messages', 'error', __( '请至少选择一个筛选分类。', 'apps-exhibition' ), 'error' );
        return false;
    }
    $app_filter_str = implode( ',', $app_filter_selected );

    $download_urls  = $_POST['download_url'] ?? [];
    $download_texts = $_POST['download_text'] ?? [];
    $downloads      = [];

    if ( ! is_array( $download_urls ) ) $download_urls = [];
    if ( ! is_array( $download_texts ) ) $download_texts = [];

    $has_valid_download = false;
    for ( $i = 0; $i < count( $download_urls ); $i++ ) {
        $url  = sanitize_text_field( trim( $download_urls[ $i ] ?? '' ) );
        $text = sanitize_text_field( trim( $download_texts[ $i ] ?? '' ) );

        if ( ! empty( $url ) && ! empty( $text ) ) {
            // 先对 URL 做兼容性处理（编码 path/query/fragment），再校验
            $normalized_url = sanitize_and_normalize_url( $url );
            if ( ! filter_var( $normalized_url, FILTER_VALIDATE_URL ) ) {
                add_settings_error( 'apps_exhibition_messages', 'error', sprintf( __( '第%d个下载链接格式无效。', 'apps-exhibition' ), $i + 1 ), 'error' );
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

function apps_exhibition_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( '无权限访问', 'apps-exhibition' ) );
    }

    global $wpdb;
    $plugin = Apps_Exhibition::get_instance();
    $table  = $wpdb->prefix . 'apps_exhibition';

    include APPS_EXHIBITION_PATH . 'templates/admin-page.php';
}
