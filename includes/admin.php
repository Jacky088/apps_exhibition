<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
            if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
                add_settings_error( 'apps_exhibition_messages', 'error', sprintf( __( '第%d个下载链接格式无效。', 'apps-exhibition' ), $i + 1 ), 'error' );
                return false;
            }
            if ( count( $downloads ) >= 3 ) {
                add_settings_error( 'apps_exhibition_messages', 'error', __( '最多只能添加3个下载链接。', 'apps-exhibition' ), 'error' );
                return false;
            }
            $downloads[] = [ 'url' => $url, 'text' => $text ];
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
        'app_downloads'       => maybe_serialize( $downloads ),
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
