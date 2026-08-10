<?php
/**
 * 卸载清理脚本。
 *
 * 仅在用户于后台「删除插件」时由 WordPress 调用（停用不会触发）。
 * 负责移除插件创建的数据表、options 与 transients，避免残留数据。
 */

// 防止被直接访问：必须由 WordPress 的卸载流程触发
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * 清理单个站点的数据。
 */
function apps_exhibition_uninstall_site() {
    global $wpdb;

    $options = [
        'apps_exhibition_db_version',
        'apps_exhibition_filter_categories',
        'apps_exhibition_platform_categories',
        'apps_exhibition_category_order',
        'apps_exhibition_home_posters',
        // 旧版本使用的无前缀 key，保留清理以兼容未迁移的站点
        'home_posters',
    ];

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    // 清除所有版本的 transient（key 中包含插件版本号，升级后会产生多个残留）
    $like_data   = $wpdb->esc_like( '_transient_apps_exhibition_' ) . '%';
    $like_timeout = $wpdb->esc_like( '_transient_timeout_apps_exhibition_' ) . '%';

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $like_data,
            $like_timeout
        )
    );

    // 删除数据表
    $table = $wpdb->prefix . 'apps_exhibition';
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

global $wpdb;

if ( is_multisite() ) {
    $site_ids = get_sites( [
        'fields' => 'ids',
        'number' => 0,
    ] );

    foreach ( $site_ids as $site_id ) {
        switch_to_blog( $site_id );
        apps_exhibition_uninstall_site();
        restore_current_blog();
    }
} else {
    apps_exhibition_uninstall_site();
}
