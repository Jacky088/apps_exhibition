<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'apps_exhibition', 'apps_exhibition_shortcode' );

function apps_exhibition_shortcode() {
    global $wpdb, $apps_exhibition_plugin_instance;

    if ( ! $apps_exhibition_plugin_instance instanceof Apps_Exhibition ) {
        return '<p>' . esc_html__( '应用展示插件初始化错误。', 'apps-exhibition' ) . '</p>';
    }

    // [优化] 1. 只有调用短代码时才加载资源
    wp_enqueue_style( 'apps-exhibition-style' );
    wp_enqueue_style( 'swiper-css' );
    wp_enqueue_script( 'swiper-js' );

    $table = $wpdb->prefix . 'apps_exhibition';
    $filter_category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';

    // [优化] 2. 使用 Transients 缓存数据库查询结果 (缓存 1 小时)
    // 缓存键包含版本号，确保更新插件后缓存失效
    $cache_key = 'apps_exhibition_all_data_v' . Apps_Exhibition::VERSION;
    $all_apps = get_transient( $cache_key );

    if ( false === $all_apps ) {
        // 缓存不存在，查询数据库
        $all_apps = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
        // 设置缓存，时间 3600 秒 (1小时)
        set_transient( $cache_key, $all_apps, 3600 );
    }

    if ( ! $all_apps ) {
        return '<p>' . esc_html__( '没有可展示的应用', 'apps-exhibition' ) . '</p>';
    }

    // 获取所有实际使用过的筛选分类（用于按钮）
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

    // 前端默认选中第一个分类
    if ( $filter_category === '' && ! empty( $categories_in_use ) ) {
        $filter_category = $categories_in_use[0];
    }

    // 内存中过滤数据
    $final_filtered_apps = $all_apps;
    if ( $filter_category && in_array( $filter_category, $categories_in_use, true ) ) { 
        $final_filtered_apps = array_filter( $all_apps, function( $app ) use ( $filter_category ) {
            $cats_in_app = explode( ',', $app['app_filter_category'] );
            $cats_in_app = array_map('trim', $cats_in_app); // 去空格
            return in_array( $filter_category, $cats_in_app, true );
        } );
    }

    $home_posters = get_option( 'home_posters', [] );
    if ( ! is_array( $home_posters ) ) {
        $home_posters = [];
    }

    ob_start();
    ?>

    <div class="apps-exhibition-wrap">

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
                                <img src="<?php echo esc_url( $poster['url'] ); ?>" loading="lazy" decoding="async" alt="<?php echo esc_attr( $download_text ?: __('海报', 'apps-exhibition') ); ?>" style="width:100%; height:100%; object-fit: cover; border-radius:12px;"/>
                                <?php if ( $download_text ) : ?>
                                    <span class="download-btn slide-download-btn-position" style="position:absolute; top:50%; right:20px; transform:translateY(-50%); z-index:20; pointer-events:auto;"><?php echo esc_html( $download_text ); ?></span>
                                <?php endif; ?>
                            </a>
                        <?php else: ?>
                            <img src="<?php echo esc_url( $poster['url'] ); ?>" loading="lazy" decoding="async" alt="<?php esc_attr_e('海报', 'apps-exhibition'); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:12px;"/>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof Swiper !== 'undefined') {
                    new Swiper('.home-posters-container', {
                        loop: true,
                        autoplay: { delay: 4000, disableOnInteraction: false },
                        pagination: { el: '.swiper-pagination', clickable: true },
                        navigation: false,
                    });
                }
            });
            </script>
        <?php endif; ?>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 筛选分类功能 - 保持页面滚动位置
            const filterButtons = document.querySelectorAll('.apps-exhibition-filter .filter-btn');
            const appItems = document.querySelectorAll('.apps-exhibition-item');
            const appsList = document.querySelector('.apps-exhibition-list');

            if (filterButtons.length > 0 && appItems.length > 0) {
                filterButtons.forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();

                        const selectedCategory = this.getAttribute('data-category');

                        // 更新按钮激活状态
                        filterButtons.forEach(function(b) {
                            b.classList.remove('active');
                        });
                        this.classList.add('active');

                        // 显示/隐藏应用 - 使用 CSS 类而不是 display 样式
                        let visibleCount = 0;
                        appItems.forEach(function(item) {
                            const itemCategories = item.getAttribute('data-categories');
                            if (itemCategories) {
                                const categoriesArray = itemCategories.split(',').map(function(c) {
                                    return c.trim();
                                });

                                if (categoriesArray.indexOf(selectedCategory) !== -1) {
                                    item.classList.remove('hidden');
                                    visibleCount++;
                                } else {
                                    item.classList.add('hidden');
                                }
                            }
                        });

                        // 如果没有匹配的应用，显示提示
                        let noResultsMsg = appsList.querySelector('.no-results-message');
                        if (visibleCount === 0) {
                            if (!noResultsMsg) {
                                noResultsMsg = document.createElement('p');
                                noResultsMsg.className = 'no-results-message';
                                noResultsMsg.textContent = '<?php esc_html_e( '没有找到符合条件的应用。', 'apps-exhibition' ); ?>';
                                appsList.appendChild(noResultsMsg);
                            }
                            noResultsMsg.style.display = 'block';
                        } else {
                            if (noResultsMsg) {
                                noResultsMsg.style.display = 'none';
                            }
                        }
                    });
                });

                // 页面加载时触发第一个分类的筛选
                const activeBtn = document.querySelector('.apps-exhibition-filter .filter-btn.active');
                if (activeBtn) {
                    activeBtn.click();
                }
            }
        });
        </script>

        <div class="apps-exhibition-filter-group">
            <div class="apps-exhibition-filter">
                <span><?php esc_html_e( '筛选分类:', 'apps-exhibition' ); ?></span>

                <?php foreach ( $categories_in_use as $category ) : ?>
                    <a class="filter-btn<?php echo ( $filter_category === $category ) ? ' active' : ''; ?>"
                       href="#"
                       data-category="<?php echo esc_attr( $category ); ?>"><?php echo esc_html( $category ); ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="apps-exhibition-list">
            <?php if ( empty( $all_apps ) ) : ?>
                <p><?php esc_html_e( '没有找到符合条件的应用。', 'apps-exhibition' ); ?></p>
            <?php else : ?>
                <?php foreach ( $all_apps as $app ) :
                    $downloads  = maybe_unserialize( $app['app_downloads'] );
                    $downloads  = is_array( $downloads ) ? $downloads : [];
                    $platforms  = explode(',', $app['app_platforms']);
                    $app_categories = explode(',', $app['app_filter_category']);
                    $app_categories_attr = implode(',', array_map('trim', $app_categories));
                    ?>
                    <div class="apps-exhibition-item"
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
                                        <a href="<?php echo esc_url( $download['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="download-btn" style="margin-left:8px;"><?php echo esc_html( $download['text'] ); ?></a>
                                <?php endif; endforeach; ?>
                            <?php else : ?>
                                <span class="download-btn download-btn-disabled"><?php esc_html_e( '暂无下载', 'apps-exhibition' ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
