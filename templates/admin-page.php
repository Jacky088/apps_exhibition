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
?>

<div class="wrap ae-admin-wrapper">
    <!-- 系统通知信息（置于页面最顶部） -->
    <div class="ae-notices-container">
        <h1 class="screen-reader-text"><?php esc_html_e( '应用页面插件管理', 'apps-exhibition' ); ?></h1>
        <?php settings_errors( 'apps_exhibition_messages' ); ?>
    </div>

    <!-- 顶部导航栏与标题 -->
    <div class="ae-header-bar">
        <div class="ae-header-left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=apps-exhibition' ) ); ?>" class="ae-header-back-btn" title="<?php esc_attr_e( '返回应用展示', 'apps-exhibition' ); ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
            </a>
            <div class="ae-header-title-box">
                <span class="ae-puzzle-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="#1677FF">
                        <path d="M20.5 11H19V7c0-1.1-.9-2-2-2h-4V3.5C13 2.12 11.88 1 10.5 1S8 2.12 8 3.5V5H4c-1.1 0-1.99.9-1.99 2v3.8H3.5c1.49 0 2.7 1.21 2.7 2.7s-1.21 2.7-2.7 2.7H2V20c0 1.1.9 2 2 2h3.8v-1.5c0-1.49 1.21-2.7 2.7-2.7 1.49 0 2.7 1.21 2.7 2.7V22H17c1.1 0 2-.9 2-2v-4h1.5c1.38 0 2.5-1.12 2.5-2.5s-1.12-2.5-2.5-2.5z"/>
                    </svg>
                </span>
                <span class="ae-page-title"><?php esc_html_e( '应用页面插件管理', 'apps-exhibition' ); ?></span>
            </div>
        </div>

        <div class="ae-header-right">
            <button type="button" class="ae-help-btn" id="ae-help-toggle-btn">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <span><?php esc_html_e( '帮助', 'apps-exhibition' ); ?></span>
            </button>
        </div>
    </div>

    <!-- 选项卡导航 -->
    <div class="ae-nav-tabs">
        <a href="?page=apps-exhibition&tab=apps" class="ae-nav-tab <?php echo $current_tab === 'apps' ? 'is-active' : ''; ?>">
            <?php esc_html_e( '应用管理', 'apps-exhibition' ); ?>
        </a>
        <a href="?page=apps-exhibition&tab=settings" class="ae-nav-tab <?php echo $current_tab === 'settings' ? 'is-active' : ''; ?>">
            <?php esc_html_e( '分类设置', 'apps-exhibition' ); ?>
        </a>
        <a href="?page=apps-exhibition&tab=home_posters" class="ae-nav-tab <?php echo $current_tab === 'home_posters' ? 'is-active' : ''; ?>">
            <?php esc_html_e( '首页海报', 'apps-exhibition' ); ?>
        </a>
    </div>

    <!-- 通用二次确认弹窗（删除应用 / 批量删除 / 删除下载链接 / 删除分类标签） -->
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
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#faad14" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <span id="ae-confirm-notice-text"></span>
                </p>
                <div class="ae-form-actions">
                    <button type="button" class="ae-btn ae-btn-danger" id="ae-confirm-ok"><?php esc_html_e( '确认', 'apps-exhibition' ); ?></button>
                    <button type="button" class="ae-btn ae-btn-secondary" id="ae-confirm-cancel"><?php esc_html_e( '取消', 'apps-exhibition' ); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- 帮助说明模态框 -->
    <div class="ae-modal-overlay" id="ae-help-overlay" style="display:none;">
        <div class="ae-modal ae-help-modal">
            <div class="ae-modal-header">
                <h2><?php esc_html_e( '使用帮助与说明', 'apps-exhibition' ); ?></h2>
                <button type="button" class="ae-modal-close" id="ae-help-close">&times;</button>
            </div>
            <div class="ae-modal-body">
                <div class="ae-help-section">
                    <h3><?php esc_html_e( '1. 快速接入', 'apps-exhibition' ); ?></h3>
                    <p><?php esc_html_e( '在任何页面、文章或自定义区块中插入短代码：', 'apps-exhibition' ); ?></p>
                    <code>[apps_exhibition]</code>
                </div>
                <div class="ae-help-section">
                    <h3><?php esc_html_e( '2. 首页海报尺寸规范', 'apps-exhibition' ); ?></h3>
                    <p><?php esc_html_e( '建议上传宽高比为 16:6（如 1920×720 像素）的高清海报图片。移动端与桌面端保持一致比例，按规范尺寸上传两端均可完整展示无黑边裁切。', 'apps-exhibition' ); ?></p>
                </div>
                <div class="ae-help-section">
                    <h3><?php esc_html_e( '3. 分类拖拽排序与生效机制', 'apps-exhibition' ); ?></h3>
                    <p><?php esc_html_e( '在「分类设置」中拖拽分类的顺序，点击「保存筛选分类」后会自动同步为前台横向滚动筛选栏的标签展示顺序。', 'apps-exhibition' ); ?></p>
                </div>
                <div class="ae-form-actions" style="margin-top:20px;">
                    <button type="button" class="ae-btn ae-btn-primary" id="ae-help-confirm"><?php esc_html_e( '我知道了', 'apps-exhibition' ); ?></button>
                </div>
            </div>
        </div>
    </div>

<?php if ( $current_tab === 'apps' ) : ?>

    <?php
    global $wpdb;
    $table = $wpdb->prefix . 'apps_exhibition';
    $apps = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

    // 收集"使用中但不在分类设置里"的分类（如重命名后遗留的旧分类名）
    $extra_categories = [];
    foreach ( (array) $apps as $app_row ) {
        foreach ( explode( ',', $app_row['app_filter_category'] ) as $extra_cat ) {
            $extra_cat = trim( $extra_cat );
            if ( '' !== $extra_cat && ! in_array( $extra_cat, $filter_categories, true ) && ! in_array( $extra_cat, $extra_categories, true ) ) {
                $extra_categories[] = $extra_cat;
            }
        }
    }

    // 获取当前页面上下文参数（分类筛选、分页码、每页条数、搜索词）
    $selected_category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
    $initial_paged     = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
    $initial_page_size = isset( $_GET['page_size'] ) ? sanitize_text_field( wp_unslash( $_GET['page_size'] ) ) : '10';
    $initial_search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

    $all_dropdown_cats = array_unique( array_merge( $filter_categories, $extra_categories ) );
    if ( $selected_category !== '' && ! in_array( $selected_category, $all_dropdown_cats, true ) ) {
        $selected_category = '';
    }
    ?>

    <!-- 应用管理主卡片 -->
    <div class="ae-card ae-main-card">
        <!-- 现代化工具栏 -->
        <div class="ae-toolbar">
            <div class="ae-toolbar-left">
                <button type="button" class="ae-btn ae-btn-primary ae-btn-add" id="ae-add-app-btn">
                    <span class="ae-icon-plus" aria-hidden="true">+</span>
                    <span><?php esc_html_e( '添加应用', 'apps-exhibition' ); ?></span>
                </button>
                <button type="button" class="ae-btn ae-btn-secondary ae-bulk-delete-btn" id="ae-bulk-delete-btn" disabled>
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 6h18m-2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    <span><?php esc_html_e( '批量删除', 'apps-exhibition' ); ?></span>
                </button>
                <button type="button" class="ae-btn ae-btn-secondary ae-bulk-move-btn" id="ae-bulk-move-btn" disabled>
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="18" cy="5" r="3"></circle>
                        <circle cx="6" cy="12" r="3"></circle>
                        <circle cx="18" cy="19" r="3"></circle>
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                    </svg>
                    <span><?php esc_html_e( '批量移动', 'apps-exhibition' ); ?></span>
                </button>

                <!-- 分类筛选（用于按分类排序） -->
                <div class="ae-sort-category-select">
                    <label for="ae-category-filter"><?php esc_html_e( '排序分类：', 'apps-exhibition' ); ?></label>
                    <div class="ae-select-wrapper ae-custom-select" id="ae-category-filter-custom">
                        <select id="ae-category-filter" style="display:none;">
                            <option value="" <?php selected( $selected_category, '' ); ?>><?php esc_html_e( '全部应用（不可排序）', 'apps-exhibition' ); ?></option>
                            <?php foreach ( $filter_categories as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $selected_category, $cat ); ?>><?php echo esc_html( $cat ); ?></option>
                            <?php endforeach; ?>
                            <?php foreach ( $extra_categories as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $selected_category, $cat ); ?>><?php echo esc_html( $cat ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="ae-custom-select-trigger" tabindex="0">
                            <span class="ae-custom-select-selected-icon">
                                <?php echo Apps_Exhibition::get_category_icon_html( $selected_category !== '' ? $selected_category : '__all__' ); ?>
                            </span>
                            <span class="ae-custom-select-selected-text"><?php echo $selected_category !== '' ? esc_html( $selected_category ) : esc_html__( '全部应用（不可排序）', 'apps-exhibition' ); ?></span>
                            <span class="ae-custom-select-arrow">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </div>
                        <div class="ae-custom-select-menu">
                            <div class="ae-custom-select-item <?php echo $selected_category === '' ? 'is-selected' : ''; ?>" data-value="">
                                <span class="ae-custom-option-icon"><?php echo Apps_Exhibition::get_category_icon_html( '__all__' ); ?></span>
                                <span class="ae-custom-option-text"><?php esc_html_e( '全部应用（不可排序）', 'apps-exhibition' ); ?></span>
                            </div>
                            <?php foreach ( $all_dropdown_cats as $cat ) : ?>
                                <div class="ae-custom-select-item <?php echo $selected_category === $cat ? 'is-selected' : ''; ?>" data-value="<?php echo esc_attr( $cat ); ?>">
                                    <span class="ae-custom-option-icon"><?php echo Apps_Exhibition::get_category_icon_html( $cat ); ?></span>
                                    <span class="ae-custom-option-text"><?php echo esc_html( $cat ); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ae-toolbar-right">
                <div class="ae-search-box">
                    <span class="ae-search-icon">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </span>
                    <input type="text" id="ae-search-input" value="<?php echo esc_attr( $initial_search ); ?>" placeholder="<?php esc_attr_e( '搜索应用名称、插件名称...', 'apps-exhibition' ); ?>" />
                </div>
                <button type="button" class="ae-filter-setting-btn" id="ae-filter-setting-btn" title="<?php esc_attr_e( '重置搜索与筛选', 'apps-exhibition' ); ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="4" y1="21" x2="4" y2="14"></line>
                        <line x1="4" y1="10" x2="4" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12" y2="3"></line>
                        <line x1="20" y1="21" x2="20" y2="16"></line>
                        <line x1="20" y1="12" x2="20" y2="3"></line>
                        <line x1="1" y1="14" x2="7" y2="14"></line>
                        <line x1="9" y1="8" x2="15" y2="8"></line>
                        <line x1="17" y1="16" x2="23" y2="16"></line>
                    </svg>
                </button>
            </div>
        </div>

        <!-- 排序提示横幅 -->
        <div class="ae-alert-callout ae-sort-hint-banner" id="ae-sort-hint" style="display:none;">
            <span class="ae-alert-icon">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
            </span>
            <span><?php esc_html_e( '当前为分类视图，拖拽可调整该分类前端显示顺序；如需跨页自由拖拽，请将右下角每页条数切换为「全部显示」。', 'apps-exhibition' ); ?></span>
        </div>

        <!-- 批量操作提交表单 -->
        <form method="post" id="ae-bulk-form" action="<?php echo esc_url( admin_url( 'admin-post.php?action=apps_exhibition_bulk_delete' ) ); ?>">
            <?php wp_nonce_field( 'apps_exhibition_bulk_delete' ); ?>
            <input type="hidden" name="redirect_paged" id="ae-bulk-redirect-paged" value="<?php echo esc_attr( $initial_paged ); ?>" />
            <input type="hidden" name="redirect_category" id="ae-bulk-redirect-category" value="<?php echo esc_attr( $selected_category ); ?>" />
            <input type="hidden" name="redirect_page_size" id="ae-bulk-redirect-page-size" value="<?php echo esc_attr( $initial_page_size ); ?>" />
            <input type="hidden" name="redirect_search" id="ae-bulk-redirect-search" value="<?php echo esc_attr( $initial_search ); ?>" />

            <div class="ae-table-responsive">
                <table class="ae-table ae-apps-table" id="ae-apps-table">
                    <thead>
                        <tr>
                            <th class="ae-col-check"><input type="checkbox" id="ae-check-all" /></th>
                            <th class="ae-col-sort"><span class="ae-sort-dot" title="<?php esc_attr_e( '拖拽手柄列', 'apps-exhibition' ); ?>"></span></th>
                            <th class="ae-col-icon"><?php esc_html_e( '图标', 'apps-exhibition' ); ?></th>
                            <th class="ae-col-name" data-sort-key="name">
                                <span><?php esc_html_e( '名称', 'apps-exhibition' ); ?></span>
                                <span class="ae-sort-arrows" title="<?php esc_attr_e( '点击排序', 'apps-exhibition' ); ?>">⇅</span>
                            </th>
                            <th class="ae-col-desc" data-sort-key="desc">
                                <span><?php esc_html_e( '描述', 'apps-exhibition' ); ?></span>
                                <span class="ae-sort-arrows" title="<?php esc_attr_e( '点击排序', 'apps-exhibition' ); ?>">⇅</span>
                            </th>
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
                                $app_badge = $app['app_badge'] ?? '';
                            ?>
                                <tr data-id="<?php echo esc_attr( $app['id'] ); ?>"
                                    data-name="<?php echo esc_attr( $app['app_name'] ); ?>"
                                    data-badge="<?php echo esc_attr( $app_badge ); ?>"
                                    data-description="<?php echo esc_attr( $app['app_description'] ); ?>"
                                    data-icon="<?php echo esc_attr( $app['app_icon'] ); ?>"
                                    data-platforms="<?php echo esc_attr( $app['app_platforms'] ); ?>"
                                    data-filter-category="<?php echo esc_attr( $app['app_filter_category'] ); ?>"
                                    data-downloads="<?php echo esc_attr( wp_json_encode( $downloads ) ); ?>">
                                    <td class="ae-col-check">
                                        <input type="checkbox" name="app_ids[]" value="<?php echo esc_attr( $app['id'] ); ?>" class="ae-row-check" />
                                    </td>
                                    <td class="ae-col-sort">
                                        <span class="ae-drag-handle" title="<?php esc_attr_e( '拖拽排序', 'apps-exhibition' ); ?>">
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                                <line x1="4" y1="8" x2="20" y2="8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                <line x1="4" y1="16" x2="20" y2="16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            </svg>
                                        </span>
                                    </td>
                                    <td class="ae-col-icon">
                                        <div class="ae-icon-thumb-wrapper">
                                            <img src="<?php echo esc_url( $app['app_icon'] ); ?>" alt="" width="40" height="40" />
                                        </div>
                                    </td>
                                    <td class="ae-col-name">
                                        <div class="ae-name-cell">
                                            <span class="ae-app-title"><?php echo esc_html( $app['app_name'] ); ?></span>
                                            <?php if ( ! empty( $app_badge ) ) : ?>
                                                <span class="ae-tag-badge ae-tag-badge-hot"><?php echo esc_html( $app_badge ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="ae-col-desc" title="<?php echo esc_attr( $app['app_description'] ); ?>">
                                        <span class="ae-desc-text"><?php echo esc_html( $app['app_description'] ); ?></span>
                                    </td>
                                    <td class="ae-col-platform">
                                        <div class="ae-table-tags-wrap">
                                            <?php foreach ( $platforms as $p ) :
                                                $p = trim( $p );
                                                if ( '' === $p ) continue; ?>
                                                <span class="ae-table-tag">
                                                    <?php echo Apps_Exhibition::get_platform_icon_html( $p ); ?>
                                                    <span class="ae-tag-name"><?php echo esc_html( $p ); ?></span>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="ae-col-category">
                                        <div class="ae-table-tags-wrap">
                                            <?php foreach ( $filter_cats as $c ) :
                                                $c = trim( $c );
                                                if ( '' === $c ) continue; ?>
                                                <span class="ae-table-tag">
                                                    <?php echo Apps_Exhibition::get_category_icon_html( $c ); ?>
                                                    <span class="ae-tag-name"><?php echo esc_html( $c ); ?></span>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="ae-col-actions">
                                        <div class="ae-action-buttons">
                                            <button type="button" class="ae-square-btn ae-square-btn-edit ae-edit-btn" title="<?php esc_attr_e( '编辑', 'apps-exhibition' ); ?>">
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </button>
                                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=apps_exhibition_delete&id=' . $app['id'] ), 'apps_exhibition_delete_' . $app['id'] ) ); ?>" class="ae-square-btn ae-square-btn-delete ae-delete-btn" title="<?php esc_attr_e( '删除', 'apps-exhibition' ); ?>">
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr class="ae-no-apps"><td colspan="8"><?php esc_html_e( '暂无应用，点击上方按钮添加。', 'apps-exhibition' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- 现代化底部操作栏与分页组件 -->
        <div class="ae-table-footer">
            <div class="ae-footer-left">
                <span class="ae-selected-info" id="ae-selected-count-label" style="display:none;">
                    <?php esc_html_e( '已选择', 'apps-exhibition' ); ?> <strong id="ae-selected-count-num">0</strong> <?php esc_html_e( '项', 'apps-exhibition' ); ?>
                </span>
                <button type="button" class="ae-btn ae-btn-sm ae-btn-danger ae-footer-bulk-del" id="ae-footer-bulk-delete-btn" style="display:none;">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 6h18m-2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    <span><?php esc_html_e( '批量删除', 'apps-exhibition' ); ?></span>
                </button>
                <button type="button" class="ae-btn ae-btn-sm ae-btn-secondary ae-footer-bulk-move" id="ae-footer-bulk-move-btn" style="display:none;">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="18" cy="5" r="3"></circle>
                        <circle cx="6" cy="12" r="3"></circle>
                        <circle cx="18" cy="19" r="3"></circle>
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                    </svg>
                    <span><?php esc_html_e( '批量移动', 'apps-exhibition' ); ?></span>
                </button>
            </div>

            <div class="ae-footer-right">
                <span class="ae-pagination-total" id="ae-pagination-total"><?php echo sprintf( esc_html__( '共 %d 条', 'apps-exhibition' ), count( $apps ) ); ?></span>
                <div class="ae-page-size-selector">
                    <select id="ae-page-size-select">
                        <option value="10" <?php selected( $initial_page_size, '10' ); ?>>10 <?php esc_html_e( '条/页', 'apps-exhibition' ); ?></option>
                        <option value="20" <?php selected( $initial_page_size, '20' ); ?>>20 <?php esc_html_e( '条/页', 'apps-exhibition' ); ?></option>
                        <option value="50" <?php selected( $initial_page_size, '50' ); ?>>50 <?php esc_html_e( '条/页', 'apps-exhibition' ); ?></option>
                        <option value="all" <?php selected( $initial_page_size, 'all' ); ?>><?php esc_html_e( '全部显示', 'apps-exhibition' ); ?></option>
                    </select>
                </div>
                <div class="ae-pagination-nav">
                    <button type="button" class="ae-page-btn ae-page-prev" id="ae-page-prev" disabled title="<?php esc_attr_e( '上一页', 'apps-exhibition' ); ?>">&lt;</button>
                    <div class="ae-page-numbers" id="ae-page-numbers">
                        <!-- 页码由 JS 动态生成 -->
                    </div>
                    <button type="button" class="ae-page-btn ae-page-next" id="ae-page-next" title="<?php esc_attr_e( '下一页', 'apps-exhibition' ); ?>">&gt;</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 添加 / 编辑应用模态框 -->
    <div class="ae-modal-overlay" id="ae-modal-overlay" style="display:none;">
        <div class="ae-modal ae-app-edit-modal">
            <div class="ae-modal-header">
                <h2 id="ae-modal-title"><?php esc_html_e( '添加应用', 'apps-exhibition' ); ?></h2>
                <button type="button" class="ae-modal-close" id="ae-modal-close">&times;</button>
            </div>
            <div class="ae-modal-body">
                <form method="post" id="apps-exhibition-form" action="<?php echo esc_url( admin_url( 'admin-post.php?action=apps_exhibition_save' ) ); ?>">
                    <?php wp_nonce_field( 'apps_exhibition_form' ); ?>
                    <input type="hidden" name="app_id" id="ae-form-app-id" value="0" />
                    <input type="hidden" name="redirect_paged" id="ae-form-redirect-paged" value="<?php echo esc_attr( $initial_paged ); ?>" />
                    <input type="hidden" name="redirect_category" id="ae-form-redirect-category" value="<?php echo esc_attr( $selected_category ); ?>" />
                    <input type="hidden" name="redirect_page_size" id="ae-form-redirect-page-size" value="<?php echo esc_attr( $initial_page_size ); ?>" />
                    <input type="hidden" name="redirect_search" id="ae-form-redirect-search" value="<?php echo esc_attr( $initial_search ); ?>" />

                    <div class="ae-form-row">
                        <label for="app_name"><?php esc_html_e( '应用名称', 'apps-exhibition' ); ?> <span class="required">*</span></label>
                        <input name="app_name" type="text" id="app_name" class="ae-input" placeholder="<?php esc_attr_e( '输入应用名称', 'apps-exhibition' ); ?>" required />
                    </div>

                    <div class="ae-form-row">
                        <label for="app_badge"><?php esc_html_e( '应用标签 / 徽章（选填）', 'apps-exhibition' ); ?></label>
                        <input name="app_badge" type="text" id="app_badge" class="ae-input" placeholder="<?php esc_attr_e( '如：热门、推荐、官方、限免（建议不超过4字）', 'apps-exhibition' ); ?>" />
                    </div>

                    <div class="ae-form-row">
                        <label for="app_description"><?php esc_html_e( '应用描述', 'apps-exhibition' ); ?> <span class="required">*</span></label>
                        <textarea name="app_description" id="app_description" rows="3" class="ae-textarea" placeholder="<?php esc_attr_e( '简短描述该应用的核心特色功能...', 'apps-exhibition' ); ?>" required></textarea>
                    </div>

                    <div class="ae-form-row">
                        <label><?php esc_html_e( '应用图标', 'apps-exhibition' ); ?> <span class="required">*</span></label>
                        <div class="ae-icon-field">
                            <input type="hidden" name="app_icon" id="app_icon" value="" required />
                            <input type="hidden" name="removed_icon_url" id="ae-removed-icon-url" value="" />
                            <div id="app_icon_preview" class="ae-icon-preview"></div>
                            <div class="ae-icon-buttons">
                                <button type="button" class="ae-btn ae-btn-secondary" id="upload_icon_button"><?php esc_html_e( '上传图标', 'apps-exhibition' ); ?></button>
                                <button type="button" class="ae-btn ae-btn-danger" id="remove_icon_button"><?php esc_html_e( '移除', 'apps-exhibition' ); ?></button>
                            </div>
                        </div>
                    </div>

                    <div class="ae-form-row">
                        <label><?php esc_html_e( '应用平台', 'apps-exhibition' ); ?> <span class="required">*</span></label>
                        <div class="ae-chips-container">
                            <?php foreach ( $platform_options as $option ) : ?>
                                <label class="ae-chip-card">
                                    <input type="checkbox" name="app_platforms[]" value="<?php echo esc_attr( $option ); ?>" class="ae-chip-checkbox">
                                    <span class="ae-chip-check-indicator" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </span>
                                    <span class="ae-chip-icon"><?php echo Apps_Exhibition::get_platform_icon_html( $option ); ?></span>
                                    <span class="ae-chip-text"><?php echo esc_html( $option ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="ae-form-row">
                        <label><?php esc_html_e( '筛选分类', 'apps-exhibition' ); ?> <span class="required">*</span></label>
                        <div class="ae-chips-container">
                            <?php foreach ( $filter_categories as $category ) : ?>
                                <label class="ae-chip-card">
                                    <input type="checkbox" name="app_filter_category[]" value="<?php echo esc_attr( $category ); ?>" class="ae-chip-checkbox">
                                    <span class="ae-chip-check-indicator" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </span>
                                    <span class="ae-chip-icon"><?php echo Apps_Exhibition::get_category_icon_html( $category ); ?></span>
                                    <span class="ae-chip-text"><?php echo esc_html( $category ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="ae-form-row">
                        <label><?php esc_html_e( '下载链接', 'apps-exhibition' ); ?> <span class="required">*</span></label>
                        <div id="downloads_container" data-download-count="1">
                            <div class="download-item">
                                <input type="url" name="download_url[]" placeholder="<?php esc_attr_e( '下载链接 URL', 'apps-exhibition' ); ?>" class="ae-input dl-url" required />
                                <input type="text" name="download_text[]" placeholder="<?php esc_attr_e( '按钮文字（如：下载、安卓商店）', 'apps-exhibition' ); ?>" class="ae-input dl-text" required />
                                <button type="button" class="ae-square-btn ae-square-btn-delete remove-download-button" title="<?php esc_attr_e( '删除', 'apps-exhibition' ); ?>">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="ae-downloads-action-bar">
                            <button type="button" class="ae-btn ae-btn-secondary ae-btn-sm" id="add_download_button"><?php esc_html_e( '+ 添加下载链接', 'apps-exhibition' ); ?></button>
                            <span class="ae-tip-text"><?php esc_html_e( '最多 3 条下载链接。', 'apps-exhibition' ); ?></span>
                        </div>
                    </div>

                    <div class="ae-form-actions">
                        <button type="submit" class="ae-btn ae-btn-primary" id="ae-form-submit"><?php esc_html_e( '保存', 'apps-exhibition' ); ?></button>
                        <button type="button" class="ae-btn ae-btn-secondary" id="ae-form-cancel"><?php esc_html_e( '取消', 'apps-exhibition' ); ?></button>
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
                    <p class="ae-confirm-lead"><?php esc_html_e( '即将移除该应用图标', 'apps-exhibition' ); ?></p>
                </div>

                <p class="ae-confirm-lead"><?php esc_html_e( '移除后会产生以下影响：', 'apps-exhibition' ); ?></p>
                <ul class="ae-confirm-list">
                    <li><?php esc_html_e( '图标会立即从当前应用的表单预览中清除；', 'apps-exhibition' ); ?></li>
                    <li><?php esc_html_e( '点击「保存」后，该图片会从 WordPress 媒体库中被永久删除（含各种缩略图尺寸），删除后无法恢复；', 'apps-exhibition' ); ?></li>
                    <li><?php esc_html_e( '若该图片仍被其他应用或首页海报引用，则会自动保留，不会误删；', 'apps-exhibition' ); ?></li>
                    <li><?php esc_html_e( '未点击「保存」前关闭本窗口，不会删除任何图片。', 'apps-exhibition' ); ?></li>
                </ul>

                <p class="ae-confirm-notice">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#faad14" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?php esc_html_e( '温馨提示：移除后请重新上传图标并点击「保存」，修改才会生效；如需保留原图，请先点击「取消」。', 'apps-exhibition' ); ?></span>
                </p>

                <div class="ae-form-actions">
                    <button type="button" class="ae-btn ae-btn-danger" id="ae-icon-remove-confirm"><?php esc_html_e( '确认移除', 'apps-exhibition' ); ?></button>
                    <button type="button" class="ae-btn ae-btn-secondary" id="ae-icon-remove-cancel"><?php esc_html_e( '取消', 'apps-exhibition' ); ?></button>
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
                    <input type="hidden" name="redirect_paged" id="ae-move-redirect-paged" value="<?php echo esc_attr( $initial_paged ); ?>" />
                    <input type="hidden" name="redirect_category" id="ae-move-redirect-category" value="<?php echo esc_attr( $selected_category ); ?>" />
                    <input type="hidden" name="redirect_page_size" id="ae-move-redirect-page-size" value="<?php echo esc_attr( $initial_page_size ); ?>" />
                    <input type="hidden" name="redirect_search" id="ae-move-redirect-search" value="<?php echo esc_attr( $initial_search ); ?>" />

                    <div class="ae-form-row">
                        <label for="ae-move-source"><?php esc_html_e( '源分类（将被替换）', 'apps-exhibition' ); ?></label>
                        <select name="source_category" id="ae-move-source" class="ae-select"></select>
                        <p class="ae-tip-text"><?php esc_html_e( '选项来自当前勾选应用所挂的分类。选择具体分类：仅替换该分类，应用的其他分类保留；选择"全部"：直接覆盖所选应用的全部分类。', 'apps-exhibition' ); ?></p>
                    </div>

                    <div class="ae-form-row">
                        <label for="ae-move-target"><?php esc_html_e( '目标分类', 'apps-exhibition' ); ?></label>
                        <select name="target_category" id="ae-move-target" class="ae-select">
                            <option value=""><?php esc_html_e( '请选择分类', 'apps-exhibition' ); ?></option>
                            <?php foreach ( $filter_categories as $category ) : ?>
                                <option value="<?php echo esc_attr( $category ); ?>"><?php echo esc_html( $category ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="ae-tip-text"><?php esc_html_e( '选项与「分类设置」中的筛选分类实时同步。', 'apps-exhibition' ); ?></p>
                    </div>

                    <div class="ae-form-actions">
                        <button type="submit" class="ae-btn ae-btn-primary"><?php esc_html_e( '确认移动', 'apps-exhibition' ); ?></button>
                        <button type="button" class="ae-btn ae-btn-secondary" id="ae-move-cancel"><?php esc_html_e( '取消', 'apps-exhibition' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php elseif ( $current_tab === 'settings' ) : ?>

    <!-- 分类设置双卡片栅格布局 -->
    <div class="ae-settings-grid">
        <!-- 卡片 1：筛选分类 -->
        <div class="ae-card ae-settings-card">
            <div class="ae-settings-card-header">
                <div class="ae-header-title-group">
                    <span class="ae-header-icon ae-icon-tag" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#fff" stroke-width="2.2">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                            <line x1="7" y1="7" x2="7.01" y2="7"></line>
                        </svg>
                    </span>
                    <h3><?php esc_html_e( '筛选分类', 'apps-exhibition' ); ?></h3>
                </div>
            </div>
            <p class="ae-settings-desc">
                <?php esc_html_e( '输入名称后点「添加」新增；按住左侧拖拽排序，点铅笔改名，点垃圾桶删除。分类顺序会同步到前端筛选栏，所有更改需点击「保存」才会生效。', 'apps-exhibition' ); ?>
            </p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'apps_exhibition_filter_categories' ); ?>
                <input type="hidden" name="action" value="apps_exhibition_save_filter_categories">
                <div class="ae-cat-editor" data-cat-editor>
                    <div class="ae-cat-add-bar">
                        <input type="text" class="ae-input ae-cat-add-input" placeholder="<?php esc_attr_e( '输入分类名称后回车或点「添加」', 'apps-exhibition' ); ?>" />
                        <button type="button" class="ae-btn ae-btn-primary ae-cat-add-btn"><?php esc_html_e( '添加', 'apps-exhibition' ); ?></button>
                    </div>
                    <ul class="ae-cat-list<?php echo empty( $filter_categories ) ? '' : ' has-items'; ?>">
                        <li class="ae-cat-empty"><?php esc_html_e( '暂无分类，请在上方输入后点「添加」', 'apps-exhibition' ); ?></li>
                        <?php foreach ( $filter_categories as $category ) : ?>
                            <li class="ae-cat-item" data-origin="<?php echo esc_attr( $category ); ?>">
                                <span class="ae-cat-drag" title="<?php esc_attr_e( '拖拽排序', 'apps-exhibition' ); ?>">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                        <circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/>
                                        <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                                        <circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/>
                                    </svg>
                                </span>
                                <?php echo Apps_Exhibition::get_category_icon_html( $category ); ?>
                                <span class="ae-cat-name"><?php echo esc_html( $category ); ?></span>
                                <span class="ae-cat-actions">
                                    <button type="button" class="ae-cat-action-btn ae-cat-edit" title="<?php esc_attr_e( '修改', 'apps-exhibition' ); ?>">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>
                                    <button type="button" class="ae-cat-action-btn ae-cat-remove" title="<?php esc_attr_e( '删除', 'apps-exhibition' ); ?>">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <input type="hidden" name="filter_categories" class="ae-cat-value" value="<?php echo esc_attr( implode( "\n", $filter_categories ) ); ?>" />
                    <input type="hidden" name="filter_category_renames" class="ae-cat-renames" value="" />
                </div>
                <div class="ae-settings-card-footer">
                    <button type="submit" class="ae-btn ae-btn-primary">
                        <span class="ae-icon-plus">+</span>
                        <span><?php esc_html_e( '保存筛选分类', 'apps-exhibition' ); ?></span>
                    </button>
                    <span class="ae-count-indicator" id="ae-filter-cat-counter">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <span id="ae-filter-cat-text"><?php echo sprintf( esc_html__( '共 %d 个分类', 'apps-exhibition' ), count( $filter_categories ) ); ?></span>
                    </span>
                </div>
            </form>
        </div>

        <!-- 卡片 2：应用平台 -->
        <div class="ae-card ae-settings-card">
            <div class="ae-settings-card-header">
                <div class="ae-header-title-group">
                    <span class="ae-header-icon ae-icon-device" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#fff" stroke-width="2.2">
                            <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                            <line x1="12" y1="18" x2="12.01" y2="18"></line>
                        </svg>
                    </span>
                    <h3><?php esc_html_e( '应用平台', 'apps-exhibition' ); ?></h3>
                </div>
            </div>
            <p class="ae-settings-desc">
                <?php esc_html_e( '输入名称后点「添加」新增；按住左侧拖拽排序，点铅笔改名，点垃圾桶删除。改名后应用会自动迁移到新名称，所有更改需点击「保存」才会生效。', 'apps-exhibition' ); ?>
            </p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'apps_exhibition_platform_categories' ); ?>
                <input type="hidden" name="action" value="apps_exhibition_save_platform_categories">
                <div class="ae-cat-editor" data-cat-editor>
                    <div class="ae-cat-add-bar">
                        <input type="text" class="ae-input ae-cat-add-input" placeholder="<?php esc_attr_e( '输入平台名称后回车或点「添加」', 'apps-exhibition' ); ?>" />
                        <button type="button" class="ae-btn ae-btn-primary ae-cat-add-btn"><?php esc_html_e( '添加', 'apps-exhibition' ); ?></button>
                    </div>
                    <ul class="ae-cat-list<?php echo empty( $platform_options ) ? '' : ' has-items'; ?>">
                        <li class="ae-cat-empty"><?php esc_html_e( '暂无平台，请在上方输入后点「添加」', 'apps-exhibition' ); ?></li>
                        <?php foreach ( $platform_options as $platform ) : ?>
                            <li class="ae-cat-item" data-origin="<?php echo esc_attr( $platform ); ?>">
                                <span class="ae-cat-drag" title="<?php esc_attr_e( '拖拽排序', 'apps-exhibition' ); ?>">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                        <circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/>
                                        <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                                        <circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/>
                                    </svg>
                                </span>
                                <?php echo Apps_Exhibition::get_platform_icon_html( $platform ); ?>
                                <span class="ae-cat-name"><?php echo esc_html( $platform ); ?></span>
                                <span class="ae-cat-actions">
                                    <button type="button" class="ae-cat-action-btn ae-cat-edit" title="<?php esc_attr_e( '修改', 'apps-exhibition' ); ?>">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>
                                    <button type="button" class="ae-cat-action-btn ae-cat-remove" title="<?php esc_attr_e( '删除', 'apps-exhibition' ); ?>">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        </svg>
                                    </button>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <input type="hidden" name="platform_categories" class="ae-cat-value" value="<?php echo esc_attr( implode( "\n", $platform_options ) ); ?>" />
                    <input type="hidden" name="platform_category_renames" class="ae-cat-renames" value="" />
                </div>
                <div class="ae-settings-card-footer">
                    <button type="submit" class="ae-btn ae-btn-primary">
                        <span class="ae-icon-plus">+</span>
                        <span><?php esc_html_e( '保存平台分类', 'apps-exhibition' ); ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ( $current_tab === 'home_posters' ) : ?>

    <?php
        $home_posters = Apps_Exhibition::get_home_posters();
    ?>

    <!-- 首页海报主卡片 -->
    <div class="ae-card ae-poster-main-card">
        <div class="ae-settings-card-header">
            <div class="ae-header-title-group">
                <span class="ae-header-icon ae-icon-poster" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#fff" stroke-width="2.2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                </span>
                <h3><?php esc_html_e( '首页海报管理', 'apps-exhibition' ); ?></h3>
            </div>
        </div>

        <p class="ae-settings-desc">
            <?php esc_html_e( '建议海报尺寸 1920×720 像素（宽高比 16:6）。PC 与移动端均采用 16:6 统一比例，按此尺寸上传可在两端完整展示无裁切；尺寸或比例不符时将自动居中裁切适配。', 'apps-exhibition' ); ?>
        </p>

        <div class="ae-poster-top-actions">
            <button type="button" class="ae-btn ae-btn-primary" id="upload_home_poster">
                <span class="ae-icon-plus">+</span>
                <span><?php esc_html_e( '上传海报', 'apps-exhibition' ); ?></span>
            </button>
        </div>

        <div class="ae-alert-callout ae-poster-sort-alert">
            <span class="ae-alert-icon">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="5 9 2 12 5 15"></polyline>
                    <polyline points="9 5 12 2 15 5"></polyline>
                    <polyline points="15 19 12 22 9 19"></polyline>
                    <polyline points="19 9 22 12 19 15"></polyline>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <line x1="12" y1="2" x2="12" y2="22"></line>
                </svg>
            </span>
            <span><?php esc_html_e( '排序：按住海报卡片左侧的【拖拽手柄】即可调整顺序。调整后请点击【保存海报配置】生效，前端轮播将按此顺序展示。', 'apps-exhibition' ); ?></span>
        </div>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ae-poster-form">
            <?php wp_nonce_field( 'save_home_posters_nonce' ); ?>
            <input type="hidden" name="action" value="save_home_posters" />
            <input type="hidden" name="home_posters" id="home_posters" value="<?php echo esc_attr( wp_json_encode( $home_posters ) ); ?>" />

            <h4 class="ae-section-heading"><?php esc_html_e( '配置海报图片、下载链接与按钮文字', 'apps-exhibition' ); ?></h4>

            <div id="poster_config_list" class="ae-poster-cards-list">
                <?php $poster_index = 0; foreach ( $home_posters as $poster ) :
                    if ( is_array( $poster ) && isset( $poster['url'] ) ) :
                        $poster_index++;
                ?>
                <div class="poster-config-item" data-index="<?php echo esc_attr( $poster_index - 1 ); ?>">
                    <!-- 左侧：拖拽手柄、序号、缩略图及微型更换/删除按钮 -->
                    <div class="poster-left-panel">
                        <div class="poster-handle-badge" title="<?php esc_attr_e( '拖拽调整顺序', 'apps-exhibition' ); ?>">
                            <span class="poster-drag-cross">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="5 9 2 12 5 15"></polyline>
                                    <polyline points="9 5 12 2 15 5"></polyline>
                                    <polyline points="15 19 12 22 9 19"></polyline>
                                    <polyline points="19 9 22 12 19 15"></polyline>
                                    <line x1="2" y1="12" x2="22" y2="12"></line>
                                    <line x1="12" y1="2" x2="12" y2="22"></line>
                                </svg>
                            </span>
                            <span class="poster-order-num"><?php echo esc_html( $poster_index ); ?></span>
                        </div>
                        <div class="poster-media-box">
                            <div class="poster-img-container">
                                <img class="poster-preview-img" src="<?php echo esc_url( $poster['url'] ); ?>" alt="" />
                            </div>
                            <div class="poster-img-btns">
                                <button type="button" class="ae-btn ae-btn-xs ae-btn-sub change-poster-conf">
                                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                    <span><?php esc_html_e( '更换图片', 'apps-exhibition' ); ?></span>
                                </button>
                                <button type="button" class="ae-btn ae-btn-xs ae-btn-sub ae-btn-sub-danger remove-poster-conf">
                                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                                    </svg>
                                    <span><?php esc_html_e( '删除图片', 'apps-exhibition' ); ?></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 中间：下载链接与按钮文字 -->
                    <div class="poster-middle-panel">
                        <div class="poster-input-group">
                            <input type="text" class="ae-input download-url-input" placeholder="<?php esc_attr_e( '下载地址（如：https://...）', 'apps-exhibition' ); ?>" value="<?php echo esc_attr( $poster['download_url'] ?? '' ); ?>">
                        </div>
                        <div class="poster-input-group">
                            <input type="text" class="ae-input download-text-input" placeholder="<?php esc_attr_e( '按钮文字', 'apps-exhibition' ); ?>" value="<?php echo esc_attr( $poster['download_text'] ?? '' ); ?>">
                        </div>
                    </div>

                    <!-- 右侧：快捷预览与整行删除按钮 -->
                    <div class="poster-right-panel">
                        <button type="button" class="poster-action-btn poster-preview-action" data-url="<?php echo esc_url( $poster['url'] ); ?>" title="<?php esc_attr_e( '预览图片', 'apps-exhibition' ); ?>">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <span><?php esc_html_e( '预览', 'apps-exhibition' ); ?></span>
                        </button>
                        <button type="button" class="poster-action-btn poster-delete-action" title="<?php esc_attr_e( '删除海报配置', 'apps-exhibition' ); ?>">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            <span><?php esc_html_e( '删除', 'apps-exhibition' ); ?></span>
                        </button>
                    </div>
                </div>
                <?php endif; endforeach; ?>
            </div>

            <div class="ae-poster-submit-wrap">
                <button type="submit" class="ae-btn ae-btn-primary" id="save_home_posters_btn">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    <span><?php esc_html_e( '保存海报配置', 'apps-exhibition' ); ?></span>
                </button>
            </div>
        </form>
    </div>

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
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#faad14" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?php esc_html_e( '温馨提示：图片删除将在点击「保存海报配置」后生效；如需保留原图，请先点击「取消」。', 'apps-exhibition' ); ?></span>
                </p>

                <div class="ae-form-actions">
                    <button type="button" class="ae-btn ae-btn-danger" id="ae-poster-confirm-ok"><?php esc_html_e( '确认', 'apps-exhibition' ); ?></button>
                    <button type="button" class="ae-btn ae-btn-secondary" id="ae-poster-confirm-cancel"><?php esc_html_e( '取消', 'apps-exhibition' ); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- 海报图片预览弹窗 -->
    <div class="ae-modal-overlay" id="ae-poster-lightbox-overlay" style="display:none;">
        <div class="ae-modal ae-lightbox-modal">
            <div class="ae-modal-header">
                <h2><?php esc_html_e( '海报全尺寸预览', 'apps-exhibition' ); ?></h2>
                <button type="button" class="ae-modal-close" id="ae-lightbox-close">&times;</button>
            </div>
            <div class="ae-modal-body ae-lightbox-body">
                <img id="ae-lightbox-img" src="" alt="<?php esc_attr_e( '海报预览', 'apps-exhibition' ); ?>" />
            </div>
        </div>
    </div>

<?php else: ?>
    <p><?php esc_html_e( '请选择选项卡进行管理。', 'apps-exhibition' ); ?></p>
<?php endif; ?>
</div>
