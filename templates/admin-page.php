<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $apps_exhibition_plugin_instance;
if ( ! $apps_exhibition_plugin_instance instanceof Apps_Exhibition ) {
    esc_html_e( '插件初始化错误。', 'apps-exhibition' );
    return;
}

$platform_options = $apps_exhibition_plugin_instance->get_platform_categories();
$filter_categories = $apps_exhibition_plugin_instance->get_filter_categories();

$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'apps';

if ( isset( $_GET['message'] ) ) {
    $msg = sanitize_text_field( $_GET['message'] );
    switch ( $msg ) {
        case 'inserted':
            add_settings_error( 'apps_exhibition_messages', 'inserted', __( '新增应用成功！', 'apps-exhibition' ), 'updated' );
            break;
        case 'updated':
            add_settings_error( 'apps_exhibition_messages', 'updated', __( '更新应用成功！', 'apps-exhibition' ), 'updated' );
            break;
        case 'deleted':
            add_settings_error( 'apps_exhibition_messages', 'deleted', __( '删除应用成功！', 'apps-exhibition' ), 'updated' );
            break;
        case 'delete_error':
            add_settings_error( 'apps_exhibition_messages', 'delete_error', __( '删除应用失败！', 'apps-exhibition' ), 'error' );
            break;
        case 'cat_saved':
            add_settings_error( 'apps_exhibition_messages', 'cat_saved', __( '修改筛选分类成功！', 'apps-exhibition' ), 'updated' );
            break;
        case 'cat_saved_error':
            add_settings_error( 'apps_exhibition_messages', 'cat_saved_error', __( '修改筛选分类失败！', 'apps-exhibition' ), 'error' );
            break;
        case 'platform_saved':
            add_settings_error( 'apps_exhibition_messages', 'platform_saved', __( '修改应用平台成功！', 'apps-exhibition' ), 'updated' );
            break;
        case 'platform_saved_error':
            add_settings_error( 'apps_exhibition_messages', 'platform_saved_error', __( '修改应用平台失败！', 'apps-exhibition' ), 'error' );
            break;
        case 'home_posters_saved':
            add_settings_error( 'apps_exhibition_messages', 'home_posters_saved', __( '上传海报成功！', 'apps-exhibition' ), 'updated' );
            break;
        case 'error':
            add_settings_error( 'apps_exhibition_messages', 'error', __( '操作失败，请检查输入。', 'apps-exhibition' ), 'error' );
            break;
    }
}
settings_errors( 'apps_exhibition_messages' );
?>

<div class="wrap">
    <h1><?php esc_html_e( '应用页面插件管理', 'apps-exhibition' ); ?></h1>

    <h2 class="nav-tab-wrapper">
        <a href="?page=apps-exhibition&tab=apps" class="nav-tab <?php echo $current_tab === 'apps' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( '应用管理', 'apps-exhibition' ); ?></a>
        <a href="?page=apps-exhibition&tab=filter_categories" class="nav-tab <?php echo $current_tab === 'filter_categories' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( '筛选分类管理', 'apps-exhibition' ); ?></a>
        <a href="?page=apps-exhibition&tab=platform_categories" class="nav-tab <?php echo $current_tab === 'platform_categories' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( '应用平台管理', 'apps-exhibition' ); ?></a>
        <a href="?page=apps-exhibition&tab=home_posters" class="nav-tab <?php echo $current_tab === 'home_posters' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( '首页海报管理', 'apps-exhibition' ); ?></a>
    </h2>

<?php if ( $current_tab === 'apps' ) : ?>

    <?php
    global $wpdb;

    $table = $wpdb->prefix . 'apps_exhibition';

    $edit_mode = false;
    $edit_app  = [
        'id'                  => 0,
        'app_name'            => '',
        'app_description'     => '',
        'app_icon'            => '',
        'app_platforms'       => [],
        'app_filter_category' => [],
        'app_downloads'       => [],
    ];

    if ( isset( $_GET['action'], $_GET['id'] ) && $_GET['action'] === 'edit' ) {
        $id = intval( $_GET['id'] );

        // 验证编辑操作的 nonce
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'apps_exhibition_edit_' . $id ) ) {
            wp_die( __( '安全验证失败', 'apps-exhibition' ) );
        }

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $id ), ARRAY_A );
        if ( $row ) {
            $edit_mode = true;
            $edit_app = $row;
            $edit_app['app_platforms'] = $row['app_platforms'] ? explode( ',', $row['app_platforms'] ) : [];
            $edit_app['app_filter_category'] = $row['app_filter_category'] ? explode( ',', $row['app_filter_category'] ) : [];
            $edit_app['app_downloads'] = maybe_unserialize( $row['app_downloads'] );
            if ( ! is_array( $edit_app['app_downloads'] ) ) {
                $edit_app['app_downloads'] = [];
            }
        }
    }

    $apps = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
    ?>

    <form method="post" id="apps-exhibition-form" action="<?php echo esc_url( admin_url( 'admin-post.php?action=apps_exhibition_save' ) ); ?>">
        <?php wp_nonce_field( 'apps_exhibition_form' ); ?>
        <input type="hidden" name="app_id" value="<?php echo esc_attr( $edit_app['id'] ?? 0 ); ?>" />

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th><label for="app_name"><?php esc_html_e( '应用名称', 'apps-exhibition' ); ?> <span style="color:red;">*</span></label></th>
                    <td><input name="app_name" type="text" id="app_name" value="<?php echo esc_attr( $edit_app['app_name'] ?? '' ); ?>" class="regular-text" required></td>
                </tr>

                <tr>
                    <th><label for="app_description"><?php esc_html_e( '应用描述', 'apps-exhibition' ); ?> <span style="color:red;">*</span></label></th>
                    <td><textarea name="app_description" id="app_description" rows="3" class="large-text" required><?php echo esc_textarea( $edit_app['app_description'] ?? '' ); ?></textarea></td>
                </tr>

                <tr>
                    <th><?php esc_html_e( '应用图标', 'apps-exhibition' ); ?> <span style="color:red;">*</span></th>
                    <td>
                        <input type="hidden" name="app_icon" id="app_icon" value="<?php echo esc_attr( $edit_app['app_icon'] ?? '' ); ?>" required>
                        <div id="app_icon_preview" style="width:100px; height:100px; background-size:contain; background-repeat:no-repeat; background-position:center center; border:1px solid #ddd; margin-bottom:10px; <?php if ( ! empty( $edit_app['app_icon'] ) ) echo esc_attr( 'background-image:url(' . esc_url( $edit_app['app_icon'] ) . ')' ); ?>"></div>
                        <button class="button" id="upload_icon_button"><?php esc_html_e( '上传图标', 'apps-exhibition' ); ?></button>
                        <button class="button" id="remove_icon_button"><?php esc_html_e( '移除图标', 'apps-exhibition' ); ?></button>
                        <p class="description"><?php esc_html_e( '请选择应用图标图片。', 'apps-exhibition' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th><?php esc_html_e( '应用平台', 'apps-exhibition' ); ?> <span style="color:red;">*</span></th>
                    <td>
                        <?php foreach ( $platform_options as $option ) : ?>
                            <label style="margin-right: 15px;">
                                <input type="checkbox" name="app_platforms[]" value="<?php echo esc_attr( $option ); ?>" <?php checked( in_array( $option, $edit_app['app_platforms'] ?? [], true ) ); ?>>
                                <?php echo esc_html( $option ); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e( '请选择至少一个平台分类。', 'apps-exhibition' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th><?php esc_html_e( '筛选分类', 'apps-exhibition' ); ?> <span style="color:red;">*</span></th>
                    <td>
                        <?php foreach ( $filter_categories as $category ) : ?>
                            <label style="margin-right: 15px;">
                                <input type="checkbox" name="app_filter_category[]" value="<?php echo esc_attr( $category ); ?>" <?php checked( in_array( $category, $edit_app['app_filter_category'] ?? [], true ) ); ?>>
                                <?php echo esc_html( $category ); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e( '请选择至少一个筛选分类。', 'apps-exhibition' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th><?php esc_html_e( '下载链接', 'apps-exhibition' ); ?> <span style="color:red;">*</span></th>
                    <td>
                        <div id="downloads_container" data-download-count="<?php echo count($edit_app['app_downloads']); ?>">
                            <?php
                            $downloads = is_array( $edit_app['app_downloads'] ?? null ) ? $edit_app['app_downloads'] : [];
                            if ( empty( $downloads ) ) {
                                $downloads = [ [ 'url' => '', 'text' => __( '下载', 'apps-exhibition' ) ] ];
                            }
                            foreach ( $downloads as $index => $dl ) :
                            ?>
                                <div class="download-item" style="margin-bottom:8px; display:flex; gap:8px; align-items:center;">
                                    <input type="url" name="download_url[]" placeholder="<?php esc_attr_e( '下载链接 URL', 'apps-exhibition' ); ?>" value="<?php echo esc_attr( $dl['url'] ?? '' ); ?>" style="width:60%;" required />
                                    <input type="text" name="download_text[]" placeholder="<?php esc_attr_e( '按钮文字', 'apps-exhibition' ); ?>" value="<?php echo esc_attr( $dl['text'] ?? '' ); ?>" style="width:30%;" required />
                                    <button type="button" class="button remove-download-button" style="width:8%;"><?php esc_html_e( '删除', 'apps-exhibition' ); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button" id="add_download_button" style="margin-top:6px;"><?php esc_html_e( '添加下载链接', 'apps-exhibition' ); ?></button>
                        <p class="description"><?php esc_html_e( '请至少填写一个下载链接，最多支持添加3条下载链接。', 'apps-exhibition' ); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button( $edit_mode ? __( '更新应用', 'apps-exhibition' ) : __( '添加应用', 'apps-exhibition' ) ); ?>

        <?php if ( $edit_mode ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=apps-exhibition' ) ); ?>" class="button"><?php esc_html_e( '取消', 'apps-exhibition' ); ?></a>
        <?php endif; ?>
    </form>

    <?php if ( ! empty( $apps ) ) : ?>
        <h2><?php esc_html_e( '已添加应用', 'apps-exhibition' ); ?></h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( '图标', 'apps-exhibition' ); ?></th>
                    <th><?php esc_html_e( '名称', 'apps-exhibition' ); ?></th>
                    <th><?php esc_html_e( '描述', 'apps-exhibition' ); ?></th>
                    <th><?php esc_html_e( '平台', 'apps-exhibition' ); ?></th>
                    <th><?php esc_html_e( '筛选分类', 'apps-exhibition' ); ?></th>
                    <th><?php esc_html_e( '操作', 'apps-exhibition' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $apps as $app ) :
                    $platforms = explode( ',', $app['app_platforms'] );
                    $filter_cats = explode( ',', $app['app_filter_category'] );
                ?>
                    <tr>
                        <td><img src="<?php echo esc_url( $app['app_icon'] ); ?>" alt="" width="48" height="48"></td>
                        <td><?php echo esc_html( $app['app_name'] ); ?></td>
                        <td><?php echo esc_html( wp_trim_words( $app['app_description'], 20 ) ); ?></td>
                        <td><?php echo esc_html( implode( ', ', $platforms ) ); ?></td>
                        <td><?php echo esc_html( implode( ', ', $filter_cats ) ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=apps-exhibition&action=edit&id=' . $app['id'] ), 'apps_exhibition_edit_' . $app['id'] ) ); ?>"><?php esc_html_e( '编辑', 'apps-exhibition' ); ?></a> |
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=apps_exhibition_delete&id=' . $app['id'] ), 'apps_exhibition_delete_' . $app['id'] ) ); ?>" onclick="return confirm('<?php esc_attr_e( '确定删除吗？', 'apps-exhibition' ); ?>');" style="color:#a00;"><?php esc_html_e( '删除', 'apps-exhibition' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

<?php elseif ( $current_tab === 'filter_categories' ) : ?>

    <h2><?php esc_html_e( '筛选分类管理', 'apps-exhibition' ); ?></h2>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'apps_exhibition_filter_categories' ); ?>
        <input type="hidden" name="action" value="apps_exhibition_save_filter_categories">

        <p><?php esc_html_e( '每行填写一个筛选分类，保存后前端和应用添加页面均会更新。', 'apps-exhibition' ); ?></p>
        <textarea name="filter_categories" rows="8" cols="50" class="large-text code"><?php echo esc_textarea( implode( "\n", $filter_categories ) ); ?></textarea>

        <p><input type="submit" class="button button-primary" value="<?php esc_attr_e( '保存筛选分类', 'apps-exhibition' ); ?>"></p>
    </form>

<?php elseif ( $current_tab === 'platform_categories' ) : ?>

    <h2><?php esc_html_e( '应用平台管理', 'apps-exhibition' ); ?></h2>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'apps_exhibition_platform_categories' ); ?>
        <input type="hidden" name="action" value="apps_exhibition_save_platform_categories">

        <p><?php esc_html_e( '每行填写一个应用平台，保存后前端和应用添加页面均会更新。', 'apps-exhibition' ); ?></p>
        <textarea name="platform_categories" rows="8" cols="50" class="large-text code"><?php echo esc_textarea( implode( "\n", $platform_options ) ); ?></textarea>

        <p><input type="submit" class="button button-primary" value="<?php esc_attr_e( '保存平台分类', 'apps-exhibition' ); ?>"></p>
    </form>

<?php elseif ( $current_tab === 'home_posters' ) : ?>

    <?php
        $home_posters = get_option( 'home_posters', [] );
        if ( ! is_array( $home_posters ) ) {
            $home_posters = [];
        }
    ?>

    <h2><?php esc_html_e( '首页海报管理', 'apps-exhibition' ); ?></h2>

    <button type="button" class="button" id="upload_home_poster"><?php esc_html_e( '上传海报', 'apps-exhibition' ); ?></button>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px;">
        <?php wp_nonce_field( 'save_home_posters_nonce' ); ?>
        <input type="hidden" name="action" value="save_home_posters" />
        <input type="hidden" name="home_posters" id="home_posters" value="<?php echo esc_attr( wp_json_encode( $home_posters ) ); ?>" />

        <div id="poster_preview_container" style="margin-bottom:20px;">
            <?php foreach ( $home_posters as $poster ) : 
                if ( is_array( $poster ) && isset( $poster['url'] ) ) : 
            ?>
            <div class="poster-item" style="position: relative; display: inline-block; margin-right: 10px;">
                <img src="<?php echo esc_url( $poster['url'] ); ?>" style="max-width:150px; max-height:150px; border:1px solid #ccc; border-radius:8px;" />
                <div style="margin-top:4px; text-align:center;">
                    <button type="button" class="button change-poster"><?php esc_html_e( '更换图片', 'apps-exhibition' ); ?></button>
                    <button type="button" class="button remove-poster"><?php esc_html_e( '删除海报', 'apps-exhibition' ); ?></button>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>

        <h3><?php esc_html_e( '配置下载链接与按钮文字', 'apps-exhibition' ); ?></h3>

        <div id="poster_config_list">
            <?php foreach ( $home_posters as $poster ) : 
                if ( is_array( $poster ) && isset( $poster['url'] ) ) : 
            ?>
            <div class="poster-config-item" style="border:1px solid #ccc; border-radius:6px; padding:10px; margin-bottom:10px; background:#f9f9f9;
                display: flex; align-items: flex-start; gap: 15px;">

                <!-- 左侧海报图片 -->
                <div style="flex: 0 0 auto;">
                    <img src="<?php echo esc_url( $poster['url'] ); ?>" style="max-width:200px; max-height:150px; border-radius: 6px;">
                </div>

                <!-- 右侧：从上到下 删除按钮、下载地址输入框、按钮文字输入框 -->
                <div style="flex: 1 1 auto; display: flex; flex-direction: column; gap: 10px;">
                    <div>
                        <button type="button" class="button remove-poster-conf"><?php esc_html_e( '删除', 'apps-exhibition' ); ?></button>
                    </div>
                    <div>
                        <input type="text" class="widefat download-url-input" placeholder="<?php esc_attr_e( '下载地址', 'apps-exhibition' ); ?>" value="<?php echo esc_attr( $poster['download_url'] ?? '' ); ?>">
                    </div>
                    <div>
                        <input type="text" class="widefat download-text-input" placeholder="<?php esc_attr_e( '按钮文字', 'apps-exhibition' ); ?>" value="<?php echo esc_attr( $poster['download_text'] ?? '' ); ?>">
                    </div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>

        <p><input type="submit" class="button-primary" value="<?php esc_attr_e( '保存海报配置', 'apps-exhibition' ); ?>"></p>
    </form>

<?php else: ?>

    <p><?php esc_html_e( '请选择左侧选项卡进行管理。', 'apps-exhibition' ); ?></p>

<?php endif; ?>
</div>
