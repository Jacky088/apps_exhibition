(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {

        // --- Swiper 初始化 ---
        var swiperContainer = document.querySelector('.home-posters-container');
        if (swiperContainer && typeof Swiper !== 'undefined') {
            new Swiper('.home-posters-container', {
                loop: true,
                autoplay: { delay: 4000, disableOnInteraction: false },
                pagination: { el: '.swiper-pagination', clickable: true },
                navigation: false
            });
        }

        // --- 筛选分类功能（支持按分类排序） ---
        var filterButtons = document.querySelectorAll('.apps-exhibition-filter .filter-btn');
        var appItems = document.querySelectorAll('.apps-exhibition-item');
        var appsList = document.querySelector('.apps-exhibition-list');

        if (filterButtons.length > 0 && appItems.length > 0) {

            // 构建 id -> DOM 映射
            var itemsById = {};
            appItems.forEach(function(item) {
                var id = item.getAttribute('data-id');
                if (id) itemsById[id] = item;
            });

            filterButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();

                    var selectedCategory = this.getAttribute('data-category');
                    var orderStr = this.getAttribute('data-order') || '';
                    var orderIds = orderStr ? orderStr.split(',').map(function(s) { return s.trim(); }) : [];

                    // 更新按钮激活状态
                    filterButtons.forEach(function(b) { b.classList.remove('active'); });
                    this.classList.add('active');

                    // 隐藏所有
                    appItems.forEach(function(item) { item.classList.add('hidden'); });

                    // 按排序显示并重排 DOM
                    var visibleCount = 0;

                    if (orderIds.length > 0) {
                        // 有自定义排序：按排序顺序追加
                        orderIds.forEach(function(id) {
                            var item = itemsById[id];
                            if (item) {
                                var cats = (item.getAttribute('data-categories') || '').split(',').map(function(c) { return c.trim(); });
                                if (cats.indexOf(selectedCategory) !== -1) {
                                    item.classList.remove('hidden');
                                    appsList.appendChild(item); // 移到末尾（按顺序）
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
                                    appsList.appendChild(item);
                                    visibleCount++;
                                }
                            }
                        });
                    } else {
                        // 无自定义排序：按原始顺序显示
                        appItems.forEach(function(item) {
                            var cats = (item.getAttribute('data-categories') || '').split(',').map(function(c) { return c.trim(); });
                            if (cats.indexOf(selectedCategory) !== -1) {
                                item.classList.remove('hidden');
                                visibleCount++;
                            }
                        });
                    }

                    // 无结果提示
                    var noResultsMsg = appsList.querySelector('.no-results-message');
                    if (visibleCount === 0) {
                        if (!noResultsMsg) {
                            noResultsMsg = document.createElement('p');
                            noResultsMsg.className = 'no-results-message';
                            noResultsMsg.textContent = (typeof appsExhibitionFront !== 'undefined')
                                ? appsExhibitionFront.noResults
                                : '没有找到符合条件的应用。';
                            appsList.appendChild(noResultsMsg);
                        }
                        noResultsMsg.style.display = 'block';
                    } else {
                        if (noResultsMsg) noResultsMsg.style.display = 'none';
                    }
                });
            });

            // 页面加载时触发第一个激活分类
            var activeBtn = document.querySelector('.apps-exhibition-filter .filter-btn.active');
            if (activeBtn) {
                activeBtn.click();
            }
        }
    });
})();
