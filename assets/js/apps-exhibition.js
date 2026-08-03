(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {

        // === Swiper 初始化（含导航箭头） ===
        var swiperContainer = document.querySelector('.home-posters-container');
        if (swiperContainer && typeof Swiper !== 'undefined') {
            new Swiper('.home-posters-container', {
                loop: true,
                autoplay: { delay: 4000, disableOnInteraction: false },
                pagination: { el: '.swiper-pagination', clickable: true },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev'
                }
            });
        }

        // === 筛选分类功能（DocumentFragment + URL联动） ===
        var filterButtons = document.querySelectorAll('.apps-exhibition-filter .filter-btn');
        var appItems = document.querySelectorAll('.apps-exhibition-item');
        var appsList = document.querySelector('.apps-exhibition-list');

        // 禁用应用图标本身的点击/触摸行为，避免触发卡片点击或打开行为（适用于桌面和移动）
        var appIconSelectors = '.app-icon-img, .app-icon-wrapper';
        var appIcons = document.querySelectorAll(appIconSelectors);
        if (appIcons && appIcons.length) {
            appIcons.forEach(function(icon) {
                ['click', 'pointerdown', 'touchstart'].forEach(function(eventName) {
                    icon.addEventListener(eventName, function(e) {
                        e.stopPropagation();
                        if (eventName === 'click') {
                            e.preventDefault();
                        }
                    }, { passive: false });
                });
            });
        }

        if (filterButtons.length > 0 && appItems.length > 0) {

            // 构建 id -> DOM 映射
            var itemsById = {};
            appItems.forEach(function(item) {
                var id = item.getAttribute('data-id');
                if (id) itemsById[id] = item;
            });

            function filterByCategory(btn) {
                var selectedCategory = btn.getAttribute('data-category');
                var orderStr = btn.getAttribute('data-order') || '';
                var orderIds = orderStr ? orderStr.split(',').map(function(s) { return s.trim(); }) : [];

                // 更新按钮激活状态
                filterButtons.forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');

                // URL 联动
                var url = new URL(window.location);
                if (selectedCategory === '__all__') {
                    url.searchParams.delete('category');
                } else {
                    url.searchParams.set('category', selectedCategory);
                }
                history.pushState(null, '', url);

                // 使用 DocumentFragment 批量操作 DOM
                var fragment = document.createDocumentFragment();
                var visibleCount = 0;

                // 移除旧的无结果提示
                var oldNoResults = appsList.querySelector('.no-results-message');
                if (oldNoResults) oldNoResults.remove();

                if (selectedCategory === '__all__') {
                    // "全部"分类：显示所有应用
                    appItems.forEach(function(item) {
                        item.classList.remove('hidden');
                        fragment.appendChild(item);
                        visibleCount++;
                    });
                } else if (orderIds.length > 0) {
                    // 有自定义排序：按排序顺序追加
                    appItems.forEach(function(item) { item.classList.add('hidden'); });

                    orderIds.forEach(function(id) {
                        var item = itemsById[id];
                        if (item) {
                            var cats = (item.getAttribute('data-categories') || '').split(',').map(function(c) { return c.trim(); });
                            if (cats.indexOf(selectedCategory) !== -1) {
                                item.classList.remove('hidden');
                                fragment.appendChild(item);
                                visibleCount++;
                            }
                        }
                    });

                    // 追加不在排序列表中的
                    appItems.forEach(function(item) {
                        var id = item.getAttribute('data-id');
                        if (orderIds.indexOf(id) === -1) {
                            var cats = (item.getAttribute('data-categories') || '').split(',').map(function(c) { return c.trim(); });
                            if (cats.indexOf(selectedCategory) !== -1) {
                                item.classList.remove('hidden');
                                fragment.appendChild(item);
                                visibleCount++;
                            }
                        }
                    });

                    // 把剩余隐藏的也追加回去保持DOM完整
                    appItems.forEach(function(item) {
                        if (item.classList.contains('hidden')) {
                            fragment.appendChild(item);
                        }
                    });
                } else {
                    // 无自定义排序：按原始顺序显示
                    appItems.forEach(function(item) {
                        var cats = (item.getAttribute('data-categories') || '').split(',').map(function(c) { return c.trim(); });
                        if (cats.indexOf(selectedCategory) !== -1) {
                            item.classList.remove('hidden');
                            visibleCount++;
                        } else {
                            item.classList.add('hidden');
                        }
                        fragment.appendChild(item);
                    });
                }

                // 一次性将 fragment 插入 DOM
                appsList.appendChild(fragment);

                // 无结果提示
                if (visibleCount === 0) {
                    var noResultsMsg = document.createElement('p');
                    noResultsMsg.className = 'no-results-message';
                    noResultsMsg.textContent = (typeof appsExhibitionFront !== 'undefined')
                        ? appsExhibitionFront.noResults
                        : '没有找到符合条件的应用。';
                    appsList.appendChild(noResultsMsg);
                }
            }

            // 绑定点击事件
            filterButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    filterByCategory(this);
                });
            });

            // 监听浏览器前进/后退
            window.addEventListener('popstate', function() {
                var urlParams = new URLSearchParams(window.location.search);
                var cat = urlParams.get('category') || '__all__';
                var targetBtn = null;

                filterButtons.forEach(function(btn) {
                    if (btn.getAttribute('data-category') === cat) {
                        targetBtn = btn;
                    }
                });

                if (targetBtn) {
                    filterByCategory(targetBtn);
                } else {
                    var firstBtn = filterButtons[0];
                    if (firstBtn) filterByCategory(firstBtn);
                }
            });

            // 页面加载时：根据URL参数或默认激活分类
            var urlParams = new URLSearchParams(window.location.search);
            var initialCategory = urlParams.get('category');
            var activeBtn = null;

            if (initialCategory) {
                filterButtons.forEach(function(btn) {
                    if (btn.getAttribute('data-category') === initialCategory) {
                        activeBtn = btn;
                    }
                });
            }

            if (!activeBtn) {
                activeBtn = document.querySelector('.apps-exhibition-filter .filter-btn.active');
            }

            if (activeBtn) {
                filterByCategory(activeBtn);
            }
        }

        // === 移动端：触摸卡片切换下载按钮显示 ===
        if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
            var cards = document.querySelectorAll('.apps-exhibition-item');
            var currentActive = null;

            cards.forEach(function(card) {
                card.addEventListener('click', function(e) {
                    // 如果点击的是下载按钮本身，或点击的是图标区域，不触发卡片展开
                    if (e.target.closest('.download-btn') || e.target.closest('.app-icon-wrapper') || e.target.closest('.app-icon-img')) {
                        return;
                    }

                    if (currentActive === card) {
                        // 再次点击同一张卡片，收起
                        card.classList.remove('touch-active');
                        currentActive = null;
                    } else {
                        // 收起之前的
                        if (currentActive) {
                            currentActive.classList.remove('touch-active');
                        }
                        // 展开当前
                        card.classList.add('touch-active');
                        currentActive = card;
                    }
                });
            });

            // 点击卡片外部收起
            document.addEventListener('click', function(e) {
                if (currentActive && !currentActive.contains(e.target)) {
                    currentActive.classList.remove('touch-active');
                    currentActive = null;
                }
            });
        }
    });
})();
