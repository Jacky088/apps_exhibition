<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'apps_exhibition', 'apps_exhibition_shortcode' );

function apps_exhibition_shortcode() {
    global $wpdb;

    $plugin = Apps_Exhibition::get_instance();

    // 只有调用短代码时才加载资源
    wp_enqueue_style( 'apps-exhibition-style' );
    wp_enqueue_style( 'swiper-css' );
    wp_enqueue_script( 'swiper-js' );
    wp_enqueue_script( 'apps-exhibition-front' );

    wp_localize_script( 'apps-exhibition-front', 'appsExhibitionFront', [
        'noResults' => __( '没有找到符合条件的应用。', 'apps-exhibition' ),
    ] );

    $table = $wpdb->prefix . 'apps_exhibition';
    $filter_category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';

    // 缓存全部应用数据
    $cache_key = 'apps_exhibition_all_data_v' . Apps_Exhibition::VERSION;
    $all_apps = get_transient( $cache_key );

    if ( false === $all_apps ) {
        $all_apps = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );
        set_transient( $cache_key, $all_apps, 3600 );
    }

    if ( ! $all_apps ) {
        return '<p>' . esc_html__( '没有可展示的应用', 'apps-exhibition' ) . '</p>';
    }

    // 构建 ID -> app 映射
    $apps_by_id = [];
    foreach ( $all_apps as $app ) {
        $apps_by_id[ $app['id'] ] = $app;
    }

    // 获取所有使用中的筛选分类
    $categories_in_use = [];
    foreach ( $all_apps as $app ) {
        $cs = explode( ',', $app['app_filter_category'] );
        foreach ( $cs as $c ) {
            $c = trim( $c );
            if ( $c && ! in_array( $c, $categories_in_use, true ) ) {
                $categories_in_use[] = $c;
            }
        }
    }
    sort( $categories_in_use );

    // 默认选中"全部"分类
    if ( $filter_category === '' ) {
        $filter_category = '__all__';
    }

    // 按分类排序应用：根据每个分类的自定义排序
    // 该计算为 O(分类数 × 应用数)，结果缓存，避免每次请求重算
    $sorted_cache_key        = 'apps_exhibition_sorted_v' . Apps_Exhibition::VERSION;
    $sorted_apps_by_category = get_transient( $sorted_cache_key );

    if ( false === $sorted_apps_by_category || ! is_array( $sorted_apps_by_category ) ) {
        // 获取分类排序配置
        $category_orders = get_option( 'apps_exhibition_category_order', [] );
        if ( ! is_array( $category_orders ) ) {
            $category_orders = [];
        }

        // 预先建立 分类 => 应用ID 列表 的映射，避免嵌套遍历
        $ids_by_category = [];
        foreach ( $all_apps as $app ) {
            $app_id   = (int) $app['id'];
            $app_cats = array_map( 'trim', explode( ',', $app['app_filter_category'] ) );
            foreach ( $app_cats as $app_cat ) {
                if ( $app_cat === '' ) {
                    continue;
                }
                $ids_by_category[ $app_cat ][] = $app_id;
            }
        }

        $sorted_apps_by_category = [];
        foreach ( $categories_in_use as $cat ) {
            $cat_app_ids = isset( $ids_by_category[ $cat ] ) ? $ids_by_category[ $cat ] : [];

            // 如果该分类有自定义排序，按排序重排
            if ( isset( $category_orders[ $cat ] ) && is_array( $category_orders[ $cat ] ) ) {
                $available = array_flip( $cat_app_ids );
                $ordered   = [];

                // 先放有排序的
                foreach ( $category_orders[ $cat ] as $oid ) {
                    $oid = (int) $oid;
                    if ( isset( $available[ $oid ] ) ) {
                        $ordered[] = $oid;
                        unset( $available[ $oid ] );
                    }
                }

                // 再追加新增的（不在排序列表中的），保持原有相对顺序
                foreach ( $cat_app_ids as $aid ) {
                    if ( isset( $available[ $aid ] ) ) {
                        $ordered[] = $aid;
                        unset( $available[ $aid ] );
                    }
                }

                $cat_app_ids = $ordered;
            }

            $sorted_apps_by_category[ $cat ] = $cat_app_ids;
        }

        set_transient( $sorted_cache_key, $sorted_apps_by_category, 3600 );
    }

    $home_posters = Apps_Exhibition::get_home_posters();

    ob_start();
    ?>

    <div id="apps-exhibition-root" class="apps-exhibition-wrap">

        <?php if ( ! empty( $home_posters ) ) : ?>
            <div class="home-posters-container swiper">
                <div class="swiper-wrapper">
                    <?php foreach ( $home_posters as $poster ) :
                        if ( ! isset( $poster['url'] ) ) continue;
                        $download_url = isset( $poster['download_url'] ) ? $poster['download_url'] : '';
                        $download_text = isset( $poster['download_text'] ) ? $poster['download_text'] : '';
                    ?>
                    <div class="swiper-slide" style="position:relative;">
                        <?php if ( $download_url ) : ?>
                            <a href="<?php echo esc_url( $download_url ); ?>" target="_blank" rel="noopener noreferrer" style="display:block; width:100%; height:100%; border-radius:12px; overflow:hidden; position:relative;">
                                <img src="<?php echo esc_url( $poster['url'] ); ?>" loading="lazy" decoding="async" alt="<?php echo esc_attr( $download_text ?: __('海报', 'apps-exhibition') ); ?>" style="width:100%; height:100%; object-fit: cover; object-position: center; border-radius:12px;"/>
                                <?php if ( $download_text ) : ?>
                                    <span class="download-btn slide-download-btn-position" style="position:absolute; top:50%; right:20px; transform:translateY(-50%); z-index:20; pointer-events:auto;"><?php echo esc_html( $download_text ); ?></span>
                                <?php endif; ?>
                            </a>
                        <?php else: ?>
                            <img src="<?php echo esc_url( $poster['url'] ); ?>" loading="lazy" decoding="async" alt="<?php esc_attr_e('海报', 'apps-exhibition'); ?>" style="width:100%; height:100%; object-fit:cover; object-position:center; border-radius:12px;"/>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
                <!-- 导航箭头 -->
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>
        <?php endif; ?>

        <div class="apps-exhibition-filter-group">
            <div class="apps-exhibition-filter">
                <span class="filter-label"><?php esc_html_e( '筛选分类:', 'apps-exhibition' ); ?></span>
                <div class="filter-scroll">
                    <div class="filter-scroll-inner">
                        <!-- "全部"分类按钮 -->
                        <a class="filter-btn<?php echo ( $filter_category === '__all__' ) ? ' active' : ''; ?>"
                           href="#"
                           data-category="__all__"
                           data-order=""
                        ><?php esc_html_e( '全部', 'apps-exhibition' ); ?></a>
                        <?php foreach ( $categories_in_use as $category ) : ?>
                            <a class="filter-btn<?php echo ( $filter_category === $category ) ? ' active' : ''; ?>"
                               href="#"
                               data-category="<?php echo esc_attr( $category ); ?>"
                               data-order="<?php echo esc_attr( implode( ',', $sorted_apps_by_category[ $category ] ?? [] ) ); ?>"
                            ><?php echo esc_html( $category ); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="apps-exhibition-list">
            <?php foreach ( $all_apps as $app ) :
                $downloads  = apps_exhibition_parse_downloads( $app['app_downloads'] );
                $platforms  = explode(',', $app['app_platforms']);
                $app_categories = explode(',', $app['app_filter_category']);
                $app_categories_attr = implode(',', array_map('trim', $app_categories));
                ?>
                <div class="apps-exhibition-item"
                     data-id="<?php echo esc_attr( $app['id'] ); ?>"
                     data-categories="<?php echo esc_attr( $app_categories_attr ); ?>"
                     title="<?php echo esc_attr( $app['app_name'] ); ?>">
                    <div class="app-icon-wrapper">
                         <img src="<?php echo esc_url( $app['app_icon'] ); ?>" loading="lazy" decoding="async" alt="<?php echo esc_attr( $app['app_name'] ); ?>" class="app-icon-img" width="72" height="72">
                    </div>

                    <div class="app-text-content">
                        <h3 class="app-name"><?php echo esc_html( $app['app_name'] ); ?></h3>
                        <div class="app-desc"><?php echo esc_html( $app['app_description'] ); ?></div>
                        <div class="app-platform-tags">
                            <?php foreach ($platforms as $plat): if(trim($plat)): ?>
                                <span class="platform-tag"><?php echo esc_html($plat); ?></span>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                    <div class="app-hover-action">
                        <?php if ( ! empty( $downloads ) ) : ?>
                            <?php foreach ( $downloads as $download ) :
                                if ( ! empty( $download['url'] ) && ! empty( $download['text'] ) ) : ?>
                                    <a href="<?php echo esc_url( $download['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="download-btn"><?php echo esc_html( $download['text'] ); ?></a>
                            <?php endif; endforeach; ?>
                        <?php else : ?>
                            <span class="download-btn download-btn-disabled"><?php esc_html_e( '暂无下载', 'apps-exhibition' ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
