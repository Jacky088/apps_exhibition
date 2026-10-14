<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'apps_exhibition', 'apps_exhibition_shortcode' );

function apps_exhibition_shortcode( $atts = [] ) {
    global $wpdb;

    $plugin = Apps_Exhibition::get_instance();

    // 只有调用短代码时才加载资源
    wp_enqueue_style( 'apps-exhibition-style' );
    wp_enqueue_style( 'swiper-css' );
    wp_enqueue_script( 'swiper-js' );
    wp_enqueue_script( 'apps-exhibition-front' );

    wp_localize_script( 'apps-exhibition-front', 'appsExhibitionFront', [
        'noResults' => __( '该分类下暂无应用。', 'apps-exhibition' ),
    ] );

    $table = $wpdb->prefix . 'apps_exhibition';
    $atts  = shortcode_atts( [ 'category' => '' ], $atts, 'apps_exhibition' );
    $filter_category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : sanitize_text_field( $atts['category'] ?? '' );

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
    // 按「分类设置」中保存的顺序排列前端筛选栏（后台拖拽顺序即前端展示顺序）
    $configured_categories = $plugin->get_filter_categories();
    $ordered_categories    = [];
    foreach ( $configured_categories as $configured_cat ) {
        if ( in_array( $configured_cat, $categories_in_use, true ) ) {
            $ordered_categories[] = $configured_cat;
        }
    }
    // 配置中未包含的历史分类（如重命名后遗留的旧名）追加到末尾，避免前端漏显
    foreach ( $categories_in_use as $cat ) {
        if ( ! in_array( $cat, $ordered_categories, true ) ) {
            $ordered_categories[] = $cat;
        }
    }
    $categories_in_use = $ordered_categories;

    $hide_all_category = Apps_Exhibition::is_hide_all_category();

    // 默认选中分类：开启隐藏“全部”时，若未指定分类或传入了已隐藏的“全部”，默认激活第一个分类；否则默认“全部”
    if ( $filter_category === '' || ( $hide_all_category && $filter_category === '__all__' ) ) {
        if ( $hide_all_category && ! empty( $categories_in_use ) ) {
            $filter_category = $categories_in_use[0];
        } else {
            $filter_category = '__all__';
        }
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

    <div id="apps-exhibition-root" class="apps-exhibition-wrap notoc no-toc ez-toc-ignore lwptoc-ignore rank-math-toc-ignore" data-toc-ignore="true" data-no-toc="true">

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
                <button type="button" class="filter-nav filter-nav-prev" aria-label="<?php esc_attr_e( '向左滚动', 'apps-exhibition' ); ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <div class="filter-scroll">
                    <div class="filter-scroll-inner">
                        <!-- "全部"分类按钮（开启隐藏开关时不渲染） -->
                        <?php if ( ! $hide_all_category ) : ?>
                            <a class="filter-btn<?php echo ( $filter_category === '__all__' ) ? ' active' : ''; ?>"
                               href="#"
                               data-category="__all__"
                               data-order=""
                            >
                                <?php echo Apps_Exhibition::get_category_icon_html( '__all__' ); ?>
                                <span class="filter-text"><?php esc_html_e( '全部', 'apps-exhibition' ); ?></span>
                            </a>
                        <?php endif; ?>
                        <?php foreach ( $categories_in_use as $category ) : ?>
                            <a class="filter-btn<?php echo ( $filter_category === $category ) ? ' active' : ''; ?>"
                               href="#"
                               data-category="<?php echo esc_attr( $category ); ?>"
                               data-order="<?php echo esc_attr( implode( ',', $sorted_apps_by_category[ $category ] ?? [] ) ); ?>"
                            >
                                <?php echo Apps_Exhibition::get_category_icon_html( $category ); ?>
                                <span class="filter-text"><?php echo esc_html( $category ); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="button" class="filter-nav filter-nav-next" aria-label="<?php esc_attr_e( '向右滚动', 'apps-exhibition' ); ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
        </div>

        <div class="apps-exhibition-list">
            <?php
            $display_apps = $all_apps;
            if ( $filter_category !== '__all__' && ! empty( $sorted_apps_by_category[ $filter_category ] ) ) {
                $ordered_ids = $sorted_apps_by_category[ $filter_category ];
                $apps_by_id  = [];
                foreach ( $all_apps as $app ) {
                    $apps_by_id[ (int) $app['id'] ] = $app;
                }
                $sorted_display = [];
                $used_ids       = [];
                foreach ( $ordered_ids as $oid ) {
                    if ( isset( $apps_by_id[ $oid ] ) ) {
                        $sorted_display[] = $apps_by_id[ $oid ];
                        $used_ids[ $oid ] = true;
                    }
                }
                foreach ( $all_apps as $app ) {
                    if ( empty( $used_ids[ (int) $app['id'] ] ) ) {
                        $sorted_display[] = $app;
                    }
                }
                $display_apps = $sorted_display;
            }

            $visible_count = 0;
            foreach ( $display_apps as $app ) {
                $app_cats_list = array_map( 'trim', explode( ',', $app['app_filter_category'] ) );
                if ( $filter_category === '__all__' || in_array( $filter_category, $app_cats_list, true ) ) {
                    $visible_count++;
                }
            }

            if ( $visible_count === 0 && ! empty( $all_apps ) ) : ?>
                <div class="no-results-message ae-empty-state">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="#94a3b8" stroke-width="1.5">
                        <circle cx="12" cy="12" r="9"/>
                        <line x1="9" y1="10" x2="9.01" y2="10"/>
                        <line x1="15" y1="10" x2="15.01" y2="10"/>
                        <path d="M9.5 15.5a3.5 3.5 0 0 1 5 0"/>
                    </svg>
                    <p><?php esc_html_e( '该分类下暂无应用。', 'apps-exhibition' ); ?></p>
                </div>
            <?php endif;

            foreach ( $display_apps as $app ) :
                $downloads  = apps_exhibition_parse_downloads( $app['app_downloads'] );
                $platforms  = explode(',', $app['app_platforms']);
                $app_categories = explode(',', $app['app_filter_category']);
                $app_categories_attr = implode(',', array_map('trim', $app_categories));
                $app_cats_list = array_map( 'trim', $app_categories );
                $is_hidden     = false;
                if ( $filter_category !== '__all__' && ! in_array( $filter_category, $app_cats_list, true ) ) {
                    $is_hidden = true;
                }
                ?>
                <div class="apps-exhibition-item<?php echo $is_hidden ? ' hidden' : ''; ?>"
                     data-id="<?php echo esc_attr( $app['id'] ); ?>"
                     data-categories="<?php echo esc_attr( $app_categories_attr ); ?>"
                     title="<?php echo esc_attr( $app['app_name'] ); ?>">
                    <div class="app-icon-wrapper">
                         <img src="<?php echo esc_url( $app['app_icon'] ); ?>" loading="lazy" decoding="async" alt="<?php echo esc_attr( $app['app_name'] ); ?>" class="app-icon-img" width="72" height="72">
                    </div>

                    <div class="app-text-content">
                        <div class="app-name notoc no-toc ez-toc-ignore lwptoc-ignore rank-math-toc-ignore">
                            <?php echo esc_html( $app['app_name'] ); ?>
                            <?php if ( ! empty( $app['app_badge'] ) ) : ?>
                                <span class="app-badge"><?php echo esc_html( $app['app_badge'] ); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="app-desc"><?php echo esc_html( $app['app_description'] ); ?></div>
                        <div class="app-platform-tags">
                            <?php foreach ($platforms as $plat): if(trim($plat)): ?>
                                <span class="platform-tag">
                                    <?php echo Apps_Exhibition::get_platform_icon_html( trim($plat) ); ?>
                                    <span class="platform-name"><?php echo esc_html( trim($plat) ); ?></span>
                                </span>
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
