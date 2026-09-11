<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$plugin = Apps_Exhibition::get_instance();
$platform_options = $plugin->get_platform_categories();
$filter_categories = $plugin->get_filter_categories();

$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'apps';

// 还原上一次表单提交暂存的具体校验错误（读取后立即删除，避免重复展示）
$form_errors_transient = Apps_Exhibition::FORM_ERRORS_TRANSIENT . get_current_user_id();
$form_errors = get_transient( $form_errors_transient );
$has_form_errors = false;
if ( $form_errors && is_array( $form_errors ) ) {
    delete_transient( $form_errors_transient );
    foreach ( $form_errors as $err ) {
        if ( ! empty( $err['message'] ) ) {
            add_settings_error(
                $err['setting'] ?? 'apps_exhibition_messages',
                $err['code'] ?? 'form_error',
                $err['message'],
                $err['type'] ?? 'error'
            );
            $has_form_errors = true;
        }
    }
}

if ( isset( $_GET['message'] ) ) {
    $msg = sanitize_text_field( wp_unslash( $_GET['message'] ) );
    $messages_map = [
        'inserted'           => [ __( '新增应用成功！', 'apps-exhibition' ), 'updated' ],
        'updated'            => [ __( '更新应用成功！', 'apps-exhibition' ), 'updated' ],
        'deleted'            => [ __( '删除成功！', 'apps-exhibition' ), 'updated' ],
        'delete_error'       => [ __( '删除失败！', 'apps-exhibition' ), 'error' ],
        'bulk_moved'         => [ __( '批量移动分类完成！', 'apps-exhibition' ), 'updated' ],
        'bulk_move_error'    => [ __( '批量移动失败，请检查所选应用与目标分类。', 'apps-exhibition' ), 'error' ],
        'bulk_move_same'     => [ __( '源分类与目标分类相同，无需移动。', 'apps-exhibition' ), 'error' ],
        'cat_saved'          => [ __( '筛选分类已保存！', 'apps-exhibition' ), 'updated' ],
        'platform_saved'     => [ __( '应用平台已保存！', 'apps-exhibition' ), 'updated' ],
        'home_posters_saved' => [ __( '海报保存成功！', 'apps-exhibition' ), 'updated' ],
        'error'              => [ __( '操作失败，请检查输入。', 'apps-exhibition' ), 'error' ],
    ];
    // 已还原具体校验错误时，不再叠加笼统的失败提示
    if ( isset( $messages_map[ $msg ] ) && ! ( $msg === 'error' && $has_form_errors ) ) {
        add_settings_error( 'apps_exhibition_messages', $msg, $messages_map[$msg][0], $messages_map[$msg][1] );
    }
}
settings_errors( 'apps_exhibition_messages' );
?>

<div class="wrap apps-exhibition-admin">
    <h1><?php esc_html_e( '应用页面插件管理', 'apps-exhibition' ); ?></h1>

    <h2 class="nav-tab-wrapper">
        <a href="?page=apps-exhibition&tab=apps" class="nav-tab <?php echo $current_tab === 'apps' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( '应用管理', 'apps-exhibition' ); ?></a>
        <a href="?page=apps-exhibition&tab=settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( '分类设置', 'apps-exhibition' ); ?></a>
        <a href="?page=apps-exhibition&tab=home_posters" class="nav-tab <?php echo $current_tab === 'home_posters' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( '首页海报', 'apps-exhibition' ); ?></a>
    </h2>

<?php if ( $current_tab === 'apps' ) : ?>

    <?php
    global $wpdb;
    $table = $wpdb->prefix . 'apps_exhibition';
    $apps = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

    // 收集"使用中但不在分类设置里"的分类（如重命名后遗留的旧分类名），
    // 追加到排序分类下拉，便于按旧分类名筛选出待迁移的应用
    $extra_categories = [];
    foreach ( (array) $apps as $app_row ) {
        foreach ( explode( ',', $app_row['app_filter_category'] ) as $extra_cat ) {
            $extra_cat = trim( $extra_cat );
            if ( '' !== $extra_cat && ! in_array( $extra_cat, $filter_categories, true ) && ! in_array( $extra_cat, $extra_categories, true ) ) {
                $extra_categories[] = $extra_cat;
            }
        }
    }
    ?>

    <!-- 工具栏 -->
    <div class="ae-toolbar">
        <button type="button" class="button button-primary ae-btn-add" id="ae-add-app-btn">
            <?php esc_html_e( '添加应用', 'apps-exhibition' ); ?>
        </button>
        <button type="button" class="button ae-bulk-delete-btn" id="ae-bulk-delete-btn" disabled>
            <?php esc_html_e( '批量删除', 'apps-exhibition' ); ?>
        </button>

        <button type="button" class="button ae-bulk-move-btn" id="ae-bulk-move-btn" disabled>
            <?php esc_html_e( '批量移动', 'apps-exhibition' ); ?>
        </button>

        <!-- 分类筛选（用于排序） -->
        <div class="ae-sort-category-select">
            <label for="ae-category-filter"><?php esc_html_e( '排序分类：', 'apps-exhibition' ); ?></label>
            <select id="ae-category-filter">
                <option value=""><?php esc_html_e( '全部应用（不可排序）', 'apps-exhibition' ); ?></option>
                <?php foreach ( $filter_categories as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                <?php endforeach; ?>
                <?php foreach ( $extra_categories as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="ae-search-box">
            <input type="text" id="ae-search-input" placeholder="<?php esc_attr_e( '搜索应用...', 'apps-exhibition' ); ?>" />
        </div>
    </div>

    <!-- 批量删除表单 -->
    <form method="post" id="ae-bulk-form" action="<?php echo esc_url( admin_url( 'admin-post.php?action=apps_exhibition_bulk_delete' ) ); ?>">
        <?php wp_nonce_field( 'apps_exhibition_bulk_delete' ); ?>

        <table class="widefat fixed striped ae-apps-table" id="ae-apps-table">
            <thead>
                <tr>
                    <th class="ae-col-check"><input type="checkbox" id="ae-check-all" /></th>
                    <th class="ae-col-sort"></th>
                    <th class="ae-col-icon"><?php esc_html_e( '图标', 'apps-exhibition' ); ?></th>
                    <th class="ae-col-name"><?php esc_html_e( '名称', 'apps-exhibition' ); ?></th>
                    <th class="ae-col-desc"><?php esc_html_e( '描述', 'apps-exhibition' ); ?></th>
                    <th class="ae-col-platform"><?php esc_html_e( '平台', 'apps-exhibition' ); ?></th>
                    <th class="ae-col-category"><?php esc_html_e( '筛选分类', 'apps-exhibition' ); ?></th>
                    <th class="ae-col-actions"><?php esc_html_e( '操作', 'apps-exhibition' ); ?></th>
                </tr>
            </thead>
            <tbody id="ae-sortable-body">
                <?php if ( ! empty( $apps ) ) : ?>
                    <?php foreach ( $apps as $app ) :
                        $platforms = explode( ',', $app['app_platforms'] );
                        $filter_cats = explode( ',', $app['app_filter_category'] );
                        $downloads = apps_exhibition_parse_downloads( $app['app_downloads'] );
                    ?>
                        <tr data-id="<?php echo esc_attr( $app['id'] ); ?>"
                            data-name="<?php echo esc_attr( $app['app_name'] ); ?>"
                            data-description="<?php echo esc_attr( $app['app_description'] ); ?>"
                            data-icon="<?php echo esc_attr( $app['app_icon'] ); ?>"
                            data-platforms="<?php echo esc_attr( $app['app_platforms'] ); ?>"
                            data-filter-category="<?php echo esc_attr( $app['app_filter_category'] ); ?>"
                            data-downloads="<?php echo esc_attr( wp_json_encode( $downloads ) ); ?>">
                            <td class="ae-col-check"><input type="checkbox" name="app_ids[]" value="<?php echo esc_attr( $app['id'] ); ?>" class="ae-row-check" /></td>
                            <td class="ae-col-sort"><span class="dashicons dashicons-menu ae-drag-handle" title="<?php esc_attr_e( '拖拽排序', 'apps-exhibition' ); ?>"></span></td>
                            <td class="ae-col-icon"><img src="<?php echo esc_url( $app['app_icon'] ); ?>" alt="" width="36" height="36" /></td>
                            <td class="ae-col-name"><?php echo esc_html( $app['app_name'] ); ?></td>
                            <td class="ae-col-desc" title="<?php echo esc_attr( $app['app_description'] ); ?>"><?php echo esc_html( wp_trim_words( $app['app_description'], 15 ) ); ?></td>
                            <td class="ae-col-platform"><?php echo esc_html( implode( ', ', $platforms ) ); ?></td>
                            <td class="ae-col-category"><?php echo esc_html( implode( ', ', $filter_cats ) ); ?></td>
                            <td class="ae-col-actions">
                                <button type="button" class="button button-small ae-edit-btn" title="<?php esc_attr_e( '编辑', 'apps-exhibition' ); ?>"><span class="dashicons dashicons-edit"></span></button>
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=apps_exhibition_delete&id=' . $app['id'] ), 'apps_exhibition_delete_' . $app['id'] ) ); ?>" class="button button-small ae-delete-btn" title="<?php esc_attr_e( '删除', 'apps-exhibition' ); ?>"><span class="dashicons dashicons-trash"></span></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr class="ae-no-apps"><td colspan="8"><?php esc_html_e( '暂无应用，点击上方按钮添加。', 'apps-exhibition' ); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <!-- 排序提示 -->
    <p class="ae-sort-hint" id="ae-sort-hint" style="display:none;">
        <span class="dashicons dashicons-info"></span>
        <?php esc_html_e( '当前为排序模式，拖拽行可调整该分类下的前端显示顺序。不属于该分类的应用已隐藏。', 'apps-exhibition' ); ?>
    </p>

    <!-- 模态框 -->
    <div class="ae-modal-overlay" id="ae-modal-overlay" style="display:none;">
        <div class="ae-modal">
            <div class="ae-modal-header">
                <h2 id="ae-modal-title"><?php esc_html_e( '添加应用', 'apps-exhibition' ); ?></h2>
                <button type="button" class="ae-modal-close" id="ae-modal-close">&times;</button>
            </div>
            <div class="ae-modal-body">
                <form method="post" id="apps-exhibition-form" action="<?php echo esc_url( admin_url( 'admin-post.php?action=apps_exhibition_save' ) ); ?>">
                    <?php wp_nonce_field( 'apps_exhibition_form' ); ?>
                    <input type="hidden" name="app_id" id="ae-form-app-id" value="0" />

                    <div class="ae-form-row">
                        <label for="app_name"><?php esc_html_e( '应用名称', 'apps-exhibition' ); ?> <span class="required">*</span></label>
                        <input name="app_name" type="text" id="app_name" class="regular-text" required />
                    </div>

                    <div class="ae-form-row">
                        <label for="app_description"><?php esc_html_e( '应用描述', 'apps-exhibition' ); ?> <span class="required">*</span></label>
                        <textarea name="app_description" id="app_description" rows="2" class="large-text" required></textarea>
                    </div>

                    <div class="ae-form-row">
                        <label><?php esc_html_e( '应用图标', 'apps-exhibition' ); ?> <span class="required">*</span></label>
                        <div class="ae-icon-field">
                            <input type="hidden" name="app_icon" id="app_icon" value="" required />
                            <input type="hidden" name="removed_icon_url" id="ae-removed-icon-url" value="" />
                            <div id="app_icon_preview" class="ae-icon-preview"></div>
                            <button type="button" class="button" id="upload_icon_button"><?php esc_html_e( '上传图标', 'apps-exhibition' ); ?></button>
                            <button type="button" class="button" id="remove_icon_button"><?php esc_html_e( '移除', 'apps-exhibition' ); ?></button>
                        </div>
                    </div>

                    <div class="ae-form-row">
                        <label><?php esc_html_e( '应用平台', 'apps-exhibition' ); ?> <span class="required">*</span></label>
                        <div class="ae-checkbox-group">
                            <?php foreach ( $platform_options as $option ) : ?>
                                <label><input type="checkbox" name="app_platforms[]" value="<?php echo esc_attr( $option ); ?>"> <?php echo esc_html( $option ); ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="ae-form-row">
                        <label><?php esc_html_e( '筛选分类', 'apps-exhibition' ); ?> <span class="required">*</span></label>
                        <div class="ae-checkbox-group">
                            <?php foreach ( $filter_categories as $category ) : ?>
                                <label><input type="checkbox" name="app_filter_category[]" value="<?php echo esc_attr( $category ); ?>"> <?php echo esc_html( $category ); ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="ae-form-row">
                        <label><?php esc_html_e( '下载链接', 'apps-exhibition' ); ?> <span class="required">*</span></label>
                        <div id="downloads_container" data-download-count="1">
                            <div class="download-item">
                                <input type="url" name="download_url[]" placeholder="<?php esc_attr_e( '下载链接 URL', 'apps-exhibition' ); ?>" style="width:55%;" required />
                                <input type="text" name="download_text[]" placeholder="<?php esc_attr_e( '按钮文字（如：下载、安卓商店）', 'apps-exhibition' ); ?>" style="width:30%;" required />
                                <button type="button" class="button-link remove-download-button" title="<?php esc_attr_e( '删除', 'apps-exhibition' ); ?>"><span class="dashicons dashicons-dismiss"></span></button>
                            </div>
                        </div>
                        <button type="button" class="button button-small" id="add_download_button"><?php esc_html_e( '+ 添加下载链接', 'apps-exhibition' ); ?></button>
                        <p class="description"><?php esc_html_e( '最多3条下载链接。', 'apps-exhibition' ); ?></p>
                    </div>

                    <div class="ae-form-actions">
                        <button type="submit" class="button button-primary" id="ae-form-submit"><?php esc_html_e( '保存', 'apps-exhibition' ); ?></button>
                        <button type="button" class="button" id="ae-form-cancel"><?php esc_html_e( '取消', 'apps-exhibition' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 移除图标二次确认模态框 -->
    <div class="ae-modal-overlay" id="ae-icon-remove-overlay" style="display:none;">
        <div class="ae-modal ae-confirm-modal">
            <div class="ae-modal-header">
                <h2><?php esc_html_e( '确认移除应用图标？', 'apps-exhibition' ); ?></h2>
                <button type="button" class="ae-modal-close" id="ae-icon-remove-close">&times;</button>
            </div>
            <div class="ae-modal-body">
                <div class="ae-icon-remove-preview-wrap">
                    <div id="ae-icon-remove-preview" class="ae-icon-preview"></div>
                    <p class="ae-confirm-lead"><?php esc_html_e( '即将移除以下图标：', 'apps-exhibition' ); ?></p>
                </div>

                <p class="ae-confirm-lead"><?php esc_html_e( '移除后会产生以下影响：', 'apps-exhibition' ); ?></p>
                <ul class="ae-confirm-list">
                    <li><?php esc_html_e( '图标会立即从当前应用的表单预览中清除；', 'apps-exhibition' ); ?></li>
                    <li><?php esc_html_e( '点击「保存」后，该图片会从 WordPress 媒体库中被永久删除（含各种缩略图尺寸），删除后无法恢复；', 'apps-exhibition' ); ?></li>
                    <li><?php esc_html_e( '若该图片仍被其他应用或首页海报引用，则会自动保留，不会误删；', 'apps-exhibition' ); ?></li>
                    <li><?php esc_html_e( '未点击「保存」前关闭本窗口，不会删除任何图片。', 'apps-exhibition' ); ?></li>
                </ul>

                <p class="ae-confirm-notice">
                    <span class="dashicons dashicons-warning"></span>
                    <span><?php esc_html_e( '温馨提示：移除后请重新上传图标并点击「保存」，修改才会生效；如需保留原图，请先点击「取消」。', 'apps-exhibition' ); ?></span>
                </p>

                <div class="ae-form-actions">
                    <button type="button" class="button ae-btn-danger" id="ae-icon-remove-confirm"><?php esc_html_e( '确认移除', 'apps-exhibition' ); ?></button>
                    <button type="button" class="button" id="ae-icon-remove-cancel"><?php esc_html_e( '取消', 'apps-exhibition' ); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- 批量移动分类模态框 -->
    <div class="ae-modal-overlay" id="ae-move-modal-overlay" style="display:none;">
        <div class="ae-modal ae-move-modal">
            <div class="ae-modal-header">
                <h2><?php esc_html_e( '批量移动分类', 'apps-exhibition' ); ?></h2>
                <button type="button" class="ae-modal-close" id="ae-move-modal-close">&times;</button>
            </div>
            <div class="ae-modal-body">
                <p class="ae-move-count" id="ae-move-selected-count"></p>

                <form method="post" id="ae-bulk-move-form" action="<?php echo esc_url( admin_url( 'admin-post.php?action=apps_exhibition_bulk_move' ) ); ?>">
                    <?php wp_nonce_field( 'apps_exhibition_bulk_move' ); ?>
                    <input type="hidden" name="app_ids" id="ae-move-app-ids" value="" />

                    <div class="ae-form-row">
                        <label for="ae-move-source"><?php esc_html_e( '源分类（将被替换）', 'apps-exhibition' ); ?></label>
                        <select name="source_category" id="ae-move-source"></select>
                        <p class="description"><?php esc_html_e( '选项来自当前勾选应用所挂的分类。选择具体分类：仅替换该分类，应用的其他分类保留；选择"全部"：直接覆盖所选应用的全部分类。', 'apps-exhibition' ); ?></p>
                    </div>

                    <div class="ae-form-row">
                        <label for="ae-move-target"><?php esc_html_e( '目标分类', 'apps-exhibition' ); ?></label>
                        <select name="target_category" id="ae-move-target">
                            <option value=""><?php esc_html_e( '请选择分类', 'apps-exhibition' ); ?></option>
                            <?php foreach ( $filter_categories as $category ) : ?>
                                <option value="<?php echo esc_attr( $category ); ?>"><?php echo esc_html( $category ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( '选项与「分类设置」中的筛选分类实时同步。', 'apps-exhibition' ); ?></p>
                    </div>

                    <div class="ae-form-actions">
                        <button type="submit" class="button button-primary"><?php esc_html_e( '确认移动', 'apps-exhibition' ); ?></button>
                        <button type="button" class="button" id="ae-move-cancel"><?php esc_html_e( '取消', 'apps-exhibition' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 通用二次确认弹窗（删除应用 / 批量删除 / 删除下载链接） -->
    <div class="ae-modal-overlay" id="ae-confirm-overlay" style="display:none;">
        <div class="ae-modal ae-confirm-modal">
            <div class="ae-modal-header">
                <h2 id="ae-confirm-title"><?php esc_html_e( '请确认操作', 'apps-exhibition' ); ?></h2>
                <button type="button" class="ae-modal-close" id="ae-confirm-close">&times;</button>
            </div>
            <div class="ae-modal-body">
                <p class="ae-confirm-lead" id="ae-confirm-lead" style="display:none;"></p>
                <ul class="ae-confirm-list" id="ae-confirm-list" style="display:none;"></ul>
                <p class="ae-confirm-notice" id="ae-confirm-notice" style="display:none;">
                    <span class="dashicons dashicons-warning"></span>
                    <span id="ae-confirm-notice-text"></span>
                </p>
                <div class="ae-form-actions">
                    <button type="button" class="button ae-btn-danger" id="ae-confirm-ok"><?php esc_html_e( '确认', 'apps-exhibition' ); ?></button>
                    <button type="button" class="button" id="ae-confirm-cancel"><?php esc_html_e( '取消', 'apps-exhibition' ); ?></button>
                </div>
            </div>
        </div>
    </div>

<?php elseif ( $current_tab === 'settings' ) : ?>

    <div class="ae-settings-wrap">
        <div class="ae-settings-card">
            <div class="ae-settings-card-header">
                <span class="dashicons dashicons-tag"></span>
                <h3><?php esc_html_e( '筛选分类', 'apps-exhibition' ); ?></h3>
            </div>
            <p class="ae-settings-desc"><?php esc_html_e( '管理前端页面的筛选分类标签，每行填写一个分类名称。', 'apps-exhibition' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'apps_exhibition_filter_categories' ); ?>
                <input type="hidden" name="action" value="apps_exhibition_save_filter_categories">
                <textarea name="filter_categories" rows="8" class="ae-settings-textarea"><?php echo esc_textarea( implode( "\n", $filter_categories ) ); ?></textarea>
                <div class="ae-settings-card-footer">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e( '保存筛选分类', 'apps-exhibition' ); ?>">
                </div>
            </form>
        </div>

        <div class="ae-settings-card">
            <div class="ae-settings-card-header">
                <span class="dashicons dashicons-smartphone"></span>
                <h3><?php esc_html_e( '应用平台', 'apps-exhibition' ); ?></h3>
            </div>
            <p class="ae-settings-desc"><?php esc_html_e( '管理可选的应用平台标签，每行填写一个平台名称。', 'apps-exhibition' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'apps_exhibition_platform_categories' ); ?>
                <input type="hidden" name="action" value="apps_exhibition_save_platform_categories">
                <textarea name="platform_categories" rows="8" class="ae-settings-textarea"><?php echo esc_textarea( implode( "\n", $platform_options ) ); ?></textarea>
                <div class="ae-settings-card-footer">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e( '保存平台分类', 'apps-exhibition' ); ?>">
                </div>
            </form>
        </div>
    </div>

<?php elseif ( $current_tab === 'home_posters' ) : ?>

    <?php
        $home_posters = Apps_Exhibition::get_home_posters();
    ?>

    <h2><?php esc_html_e( '首页海报管理', 'apps-exhibition' ); ?></h2>
    <p class="description" style="margin-bottom: 12px; font-size: 13px; color: #666;">
        <?php esc_html_e( '建议海报尺寸 1920×720 像素（宽高比 16:6）。PC 与移动端均采用 16:6 统一比例，按此尺寸上传可两端完整显示无裁切；尺寸或比例不符时将自动居中裁切适配。', 'apps-exhibition' ); ?>
    </p>
    <button type="button" class="button" id="upload_home_poster"><?php esc_html_e( '上传海报', 'apps-exhibition' ); ?></button>

    <p class="ae-poster-sort-hint">
        <span class="dashicons dashicons-move"></span>
        <span><?php esc_html_e( '排序：按住海报卡片左侧的「拖拽手柄」即可调整顺序。调整后请点击「保存海报配置」生效，前端轮播将按此顺序展示。', 'apps-exhibition' ); ?></span>
    </p>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px;">
        <?php wp_nonce_field( 'save_home_posters_nonce' ); ?>
        <input type="hidden" name="action" value="save_home_posters" />
        <input type="hidden" name="home_posters" id="home_posters" value="<?php echo esc_attr( wp_json_encode( $home_posters ) ); ?>" />

        <h3 style="margin-top:20px;"><?php esc_html_e( '配置海报图片、下载链接与按钮文字', 'apps-exhibition' ); ?></h3>
        <div id="poster_config_list">
            <?php $poster_index = 0; foreach ( $home_posters as $poster ) :
                if ( is_array( $poster ) && isset( $poster['url'] ) ) :
                    $poster_index++;
            ?>
            <div class="poster-config-item" data-index="<?php echo esc_attr( $poster_index - 1 ); ?>" style="border:1px solid #ccc; border-radius:6px; padding:10px; margin-bottom:10px; background:#f9f9f9; display: flex; align-items: flex-start; gap: 15px;">
                <div style="flex: 0 0 auto; text-align: center;">
                    <div class="poster-media-row">
                        <div class="poster-drag-handle" title="<?php esc_attr_e( '拖拽调整顺序', 'apps-exhibition' ); ?>">
                            <span class="poster-order"><?php echo esc_html( $poster_index ); ?></span>
                            <span class="poster-drag-grip"></span>
                        </div>
                        <img class="poster-preview-img" src="<?php echo esc_url( $poster['url'] ); ?>" style="max-width:200px; max-height:150px; border-radius: 6px;">
                    </div>
                    <div style="margin-top:6px; display:flex; gap:6px; justify-content:center;">
                        <button type="button" class="button button-small change-poster-conf"><?php esc_html_e( '更换图片', 'apps-exhibition' ); ?></button>
                        <button type="button" class="button button-small remove-poster-conf"><?php esc_html_e( '删除图片', 'apps-exhibition' ); ?></button>
                    </div>
                </div>
                <div style="flex: 1 1 auto; display: flex; flex-direction: column; gap: 10px;">
                    <div><input type="text" class="widefat download-url-input" placeholder="<?php esc_attr_e( '下载地址', 'apps-exhibition' ); ?>" value="<?php echo esc_attr( $poster['download_url'] ?? '' ); ?>"></div>
                    <div><input type="text" class="widefat download-text-input" placeholder="<?php esc_attr_e( '按钮文字', 'apps-exhibition' ); ?>" value="<?php echo esc_attr( $poster['download_text'] ?? '' ); ?>"></div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>

        <p><input type="submit" class="button-primary" value="<?php esc_attr_e( '保存海报配置', 'apps-exhibition' ); ?>"></p>
    </form>

    <!-- 海报图片「更换 / 删除」二次确认弹窗 -->
    <div class="ae-modal-overlay" id="ae-poster-confirm-overlay" style="display:none;">
        <div class="ae-modal ae-confirm-modal">
            <div class="ae-modal-header">
                <h2 id="ae-poster-confirm-title"><?php esc_html_e( '确认操作？', 'apps-exhibition' ); ?></h2>
                <button type="button" class="ae-modal-close" id="ae-poster-confirm-close">&times;</button>
            </div>
            <div class="ae-modal-body">
                <div class="ae-poster-confirm-preview-wrap">
                    <figure class="ae-poster-confirm-figure">
                        <div id="ae-poster-confirm-old" class="ae-poster-confirm-preview"></div>
                        <figcaption><?php esc_html_e( '当前图片', 'apps-exhibition' ); ?></figcaption>
                    </figure>
                    <span class="ae-poster-confirm-arrow" id="ae-poster-confirm-arrow">&rarr;</span>
                    <figure class="ae-poster-confirm-figure" id="ae-poster-confirm-new-wrap" style="display:none;">
                        <div id="ae-poster-confirm-new" class="ae-poster-confirm-preview"></div>
                        <figcaption><?php esc_html_e( '新图片', 'apps-exhibition' ); ?></figcaption>
                    </figure>
                </div>

                <p class="ae-confirm-lead" id="ae-poster-confirm-lead"><?php esc_html_e( '即将执行以下操作：', 'apps-exhibition' ); ?></p>
                <ul class="ae-confirm-list">
                    <li><?php esc_html_e( '图片会立即从当前海报配置中移除或替换，页面预览同步更新；', 'apps-exhibition' ); ?></li>
                    <li><?php esc_html_e( '点击「保存海报配置」后，被移除或替换的图片将从 WordPress 媒体库中被永久删除（含各种缩略图尺寸），删除后无法恢复；', 'apps-exhibition' ); ?></li>
                    <li><?php esc_html_e( '若该图片仍被其他应用或其他海报引用，则会自动保留，不会误删；', 'apps-exhibition' ); ?></li>
                    <li><?php esc_html_e( '未点击「保存海报配置」前，即使关闭窗口或刷新页面，也不会删除任何图片。', 'apps-exhibition' ); ?></li>
                </ul>

                <p class="ae-confirm-notice">
                    <span class="dashicons dashicons-warning"></span>
                    <span><?php esc_html_e( '温馨提示：图片删除将在点击「保存海报配置」后生效；如需保留原图，请先点击「取消」。', 'apps-exhibition' ); ?></span>
                </p>

                <div class="ae-form-actions">
                    <button type="button" class="button ae-btn-danger" id="ae-poster-confirm-ok"><?php esc_html_e( '确认', 'apps-exhibition' ); ?></button>
                    <button type="button" class="button" id="ae-poster-confirm-cancel"><?php esc_html_e( '取消', 'apps-exhibition' ); ?></button>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <p><?php esc_html_e( '请选择选项卡进行管理。', 'apps-exhibition' ); ?></p>
<?php endif; ?>
</div>
