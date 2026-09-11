jQuery(document).ready(function($) {
    'use strict';

    var l10n = window.appsExhibitionL10n || {};

    // HTML 属性值转义：防止值中的引号/尖括号破坏属性边界导致 XSS
    function escAttr( value ) {
        return String( value == null ? '' : value ).replace( /[&<>"']/g, function( c ) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
        } );
    }

    // =============================================
    // 模态框
    // =============================================
    var $overlay = $('#ae-modal-overlay');
    var $iconRemoveOverlay = $('#ae-icon-remove-overlay');
    var $confirmOverlay = $('#ae-confirm-overlay');
    var $form = $('#apps-exhibition-form');
    var $title = $('#ae-modal-title');

    function openModal(mode, data) {
        $form[0].reset();
        $('#ae-form-app-id').val(0);
        $('#app_icon').val('');
        $('#app_icon_preview').css('background-image', 'none');
        $('#ae-removed-icon-url').val('');
        $('#downloads_container').html(getDownloadItemHtml('', ''));
        updateAddDownloadBtn();
        $form.find('input[type="checkbox"]').prop('checked', false);

        if (mode === 'edit' && data) {
            $title.text(l10n.editApp || '编辑应用');
            $('#ae-form-app-id').val(data.id);
            $('#app_name').val(data.name);
            $('#app_description').val(data.description);
            $('#app_icon').val(data.icon);
            if (data.icon) {
                $('#app_icon_preview').css('background-image', 'url(' + data.icon + ')');
            }
            if (data.platforms) {
                data.platforms.split(',').forEach(function(p) {
                    $form.find('input[name="app_platforms[]"][value="' + $.escapeSelector(p.trim()) + '"]').prop('checked', true);
                });
            }
            if (data.filterCategory) {
                data.filterCategory.split(',').forEach(function(c) {
                    $form.find('input[name="app_filter_category[]"][value="' + $.escapeSelector(c.trim()) + '"]').prop('checked', true);
                });
            }
            if (data.downloads && data.downloads.length > 0) {
                var html = '';
                data.downloads.forEach(function(dl) {
                    html += getDownloadItemHtml(dl.url || '', dl.text || '');
                });
                $('#downloads_container').html(html);
                updateAddDownloadBtn();
            }
        } else {
            $title.text(l10n.addApp || '添加应用');
        }

        $overlay.fadeIn(200);
        $('body').css('overflow', 'hidden');
    }

    function closeModal() {
        $overlay.fadeOut(200);
        $('body').css('overflow', '');
    }

    $('#ae-add-app-btn').on('click', function() { openModal('add'); });
    $('#ae-modal-close, #ae-form-cancel').on('click', closeModal);
    $overlay.on('click', function(e) { if ($(e.target).is($overlay)) closeModal(); });
    $(document).on('keydown', function(e) {
        if (e.key !== 'Escape') return;
        // 通用确认弹窗层级最高，优先关闭
        if ($confirmOverlay.is(':visible')) { closeConfirm(); return; }
        // 移除图标确认弹窗优先关闭，避免同时关掉下层的应用编辑弹窗
        if ($iconRemoveOverlay.is(':visible')) { $iconRemoveOverlay.fadeOut(200); return; }
        if ($overlay.is(':visible')) closeModal();
    });

    $(document).on('click', '.ae-edit-btn', function() {
        var $row = $(this).closest('tr');
        var downloads = [];
        try { downloads = JSON.parse($row.attr('data-downloads') || '[]'); } catch(e) {}

        openModal('edit', {
            id: $row.attr('data-id'),
            name: $row.attr('data-name'),
            description: $row.attr('data-description'),
            icon: $row.attr('data-icon'),
            platforms: $row.attr('data-platforms'),
            filterCategory: $row.attr('data-filter-category'),
            downloads: downloads
        });
    });

    // =============================================
    // 通用二次确认弹窗
    // 所有删除/移除操作统一走此弹窗：说明影响 + 提示「保存后生效」
    // =============================================
    var confirmCallback = null;

    function openConfirm(opts) {
        opts = opts || {};

        $('#ae-confirm-title').text(opts.title || (l10n.confirmTitle || '请确认操作'));

        if (opts.lead) {
            $('#ae-confirm-lead').text(opts.lead).show();
        } else {
            $('#ae-confirm-lead').hide();
        }

        var $list = $('#ae-confirm-list').empty();
        if (opts.items && opts.items.length) {
            opts.items.forEach(function(item) {
                $list.append($('<li>').text(item));
            });
            $list.show();
        } else {
            $list.hide();
        }

        if (opts.notice) {
            $('#ae-confirm-notice-text').text(opts.notice);
            $('#ae-confirm-notice').show();
        } else {
            $('#ae-confirm-notice').hide();
        }

        $('#ae-confirm-ok').text(opts.okText || (l10n.confirmBtn || '确认'));
        confirmCallback = opts.onConfirm || null;

        $confirmOverlay.fadeIn(200);
        $('body').css('overflow', 'hidden');
    }

    function closeConfirm() {
        $confirmOverlay.fadeOut(200);
        // 仅当没有其他模态框打开时才恢复页面滚动
        if (!$overlay.is(':visible') && !$iconRemoveOverlay.is(':visible')) {
            $('body').css('overflow', '');
        }
        confirmCallback = null;
    }

    $('#ae-confirm-close, #ae-confirm-cancel').on('click', closeConfirm);
    $confirmOverlay.on('click', function(e) {
        if ($(e.target).is($confirmOverlay)) closeConfirm();
    });
    $('#ae-confirm-ok').on('click', function() {
        var cb = confirmCallback;
        closeConfirm();
        if (typeof cb === 'function') cb();
    });

    // =============================================
    // 列表页删除（页面无保存按钮，二次确认后立即生效）
    // =============================================
    $(document).on('click', '.ae-delete-btn', function(e) {
        e.preventDefault();
        var href = $(this).attr('href');
        var name = $(this).closest('tr').attr('data-name') || '';

        openConfirm({
            title: l10n.confirmDeleteAppTitle || '确认删除该应用？',
            lead: (l10n.confirmDeleteAppLead || '即将删除应用：') + name,
            items: [
                l10n.confirmDeleteAppItem1 || '点击「确认删除」后会立即执行删除，无需额外保存；',
                l10n.confirmDeleteAppItem2 || '该应用数据与其图标将从数据库和媒体库中被永久删除（含各种缩略图尺寸），删除后无法恢复；',
                l10n.confirmDeleteAppItem3 || '若该图标仍被其他应用或首页海报引用，则会自动保留，不会误删。'
            ],
            notice: l10n.confirmDeleteAppNotice || '温馨提示：此操作不可恢复；如需保留该应用，请点击「取消」。',
            okText: l10n.confirmDeleteBtn || '确认删除',
            onConfirm: function() {
                if (href) { window.location.href = href; }
            }
        });
    });

    // =============================================
    // 图标上传
    // =============================================
    var iconUploader;

    function applyIcon(url) {
        $('#app_icon').val(url);
        $('#app_icon_preview').css('background-image', 'url(' + url + ')');
    }

    $('#upload_icon_button').on('click', function(e) {
        e.preventDefault();
        if (iconUploader) { iconUploader.open(); return; }
        iconUploader = wp.media({
            title: l10n.selectIconTitle || '选择应用图标',
            button: { text: l10n.useIconBtn || '使用这个图标' },
            multiple: false
        });
        iconUploader.on('select', function() {
            var att = iconUploader.state().get('selection').first().toJSON();
            var oldUrl = $('#app_icon').val() || '';

            // 编辑状态下更换图标会删除原图标，需二次确认
            if (oldUrl && oldUrl !== att.url) {
                openConfirm({
                    title: l10n.confirmChangeIconTitle || '确认更换应用图标？',
                    lead: l10n.confirmChangeIconLead || '即将用新选择的图标替换当前图标：',
                    items: [
                        l10n.confirmChangeIconItem1 || '新图标会立即显示在表单预览中，此时尚未保存；',
                        l10n.confirmChangeIconItem2 || '点击「保存」后，原图标将从 WordPress 媒体库中被永久删除（含各种缩略图尺寸），删除后无法恢复；',
                        l10n.confirmChangeIconItem3 || '若原图标仍被其他应用或首页海报引用，则会自动保留，不会误删；',
                        l10n.confirmChangeIconItem4 || '未点击「保存」前取消或关闭窗口，将保留原图标。'
                    ],
                    notice: l10n.confirmChangeIconNotice || '温馨提示：更换将在点击「保存」后生效；如需保留原图标，请点击「取消」。',
                    okText: l10n.confirmChangeIconBtn || '确认更换',
                    onConfirm: function() { applyIcon(att.url); }
                });
                return;
            }

            applyIcon(att.url);
        });
        iconUploader.open();
    });

    function clearIconField() {
        $('#app_icon').val('');
        $('#app_icon_preview').css('background-image', 'none');
    }

    $('#remove_icon_button').on('click', function(e) {
        e.preventDefault();
        // 未选择图标时无需二次确认，直接清空
        if (!$('#app_icon').val()) { clearIconField(); return; }

        // 展示待移除图标的预览，配合影响说明帮助用户确认
        $('#ae-icon-remove-preview').css('background-image', 'url(' + $('#app_icon').val() + ')');
        $iconRemoveOverlay.fadeIn(200);
    });

    $('#ae-icon-remove-confirm').on('click', function() {
        // 记录被移除的图标 URL，保存时由后端联动清理媒体库孤儿图片
        $('#ae-removed-icon-url').val($('#app_icon').val());
        clearIconField();
        $iconRemoveOverlay.fadeOut(200);
    });

    $('#ae-icon-remove-close, #ae-icon-remove-cancel').on('click', function() {
        $iconRemoveOverlay.fadeOut(200);
    });

    $iconRemoveOverlay.on('click', function(e) {
        if ($(e.target).is($iconRemoveOverlay)) { $iconRemoveOverlay.fadeOut(200); }
    });

    // =============================================
    // 下载链接
    // =============================================
    var MAX_DOWNLOADS = 3;

    function getDownloadItemHtml(url, text) {
        return '<div class="download-item">' +
            '<input type="url" name="download_url[]" placeholder="' + (l10n.downloadUrlPlc || '下载链接 URL') + '" value="' + escAttr(url || '') + '" style="width:55%;" required />' +
            '<input type="text" name="download_text[]" placeholder="' + (l10n.downloadTextPlc || '按钮文字（如：下载、安卓商店）') + '" value="' + escAttr(text || '') + '" style="width:30%;" required />' +
            '<button type="button" class="button-link remove-download-button" title="' + (l10n.deleteBtn || '删除') + '"><span class="dashicons dashicons-dismiss"></span></button>' +
            '</div>';
    }

    function updateAddDownloadBtn() {
        $('#add_download_button').prop('disabled', $('#downloads_container .download-item').length >= MAX_DOWNLOADS);
    }

    $('#add_download_button').on('click', function() {
        if ($('#downloads_container .download-item').length >= MAX_DOWNLOADS) return;
        $('#downloads_container').append(getDownloadItemHtml('', ''));
        updateAddDownloadBtn();
    });

    $(document).on('click', '.remove-download-button', function() {
        var $item = $(this).closest('.download-item');
        var url = $item.find('input[name="download_url[]"]').val().trim();
        var text = $item.find('input[name="download_text[]"]').val().trim();
        var isLast = $('#downloads_container .download-item').length <= 1;

        var targetDesc = (url || text)
            ? (l10n.confirmRemoveDownloadTarget || '下载链接：') + (url || '') + '（' + (text || '') + '）'
            : (l10n.confirmRemoveDownloadEmpty || '该下载链接尚未填写完整内容');

        openConfirm({
            title: l10n.confirmRemoveDownloadTitle || '确认删除该下载链接？',
            lead: l10n.confirmRemoveDownloadLead || '即将从当前表单中移除以下下载链接：',
            items: [
                targetDesc,
                isLast
                    ? (l10n.confirmRemoveDownloadLast || '这是最后一个下载链接，确认后该行会被清空（每个应用至少需要保留一个下载链接）；')
                    : (l10n.confirmRemoveDownloadItem1 || '该下载链接会立即从当前表单中移除；'),
                l10n.confirmRemoveDownloadItem2 || '点击表单中的「保存」后，更改才会真正保存；',
                l10n.confirmRemoveDownloadItem3 || '未点击「保存」前关闭窗口，将恢复原有的下载链接。'
            ],
            notice: l10n.confirmRemoveDownloadNotice || '温馨提示：删除将在点击「保存」后生效；如需保留，请点击「取消」。',
            okText: l10n.confirmRemoveDownloadBtn || '确认删除',
            onConfirm: function() {
                if ($('#downloads_container .download-item').length > 1) {
                    $item.remove();
                } else {
                    $item.find('input[name="download_url[]"]').val('');
                    $item.find('input[name="download_text[]"]').val('');
                }
                updateAddDownloadBtn();
            }
        });
    });

    // =============================================
    // 表单校验
    // =============================================
    $form.on('submit', function() {
        if (!$('#app_name').val().trim() || !$('#app_description').val().trim() || !$('#app_icon').val().trim()) {
            alert(l10n.fillRequired); return false;
        }
        if ($form.find('input[name="app_platforms[]"]:checked').length === 0) {
            alert(l10n.selectPlatform); return false;
        }
        if ($form.find('input[name="app_filter_category[]"]:checked').length === 0) {
            alert(l10n.selectFilter); return false;
        }
        var hasValid = false, valid = true;
        $('#downloads_container .download-item').each(function() {
            var url = $(this).find('input[name="download_url[]"]').val().trim();
            var text = $(this).find('input[name="download_text[]"]').val().trim();
            if (url && text) { hasValid = true; }
            else if (url || text) { alert(l10n.fillDownload); valid = false; return false; }
        });
        if (!hasValid) { alert(l10n.needOneDownload); return false; }
        return valid;
    });

    // =============================================
    // 搜索
    // =============================================
    $('#ae-search-input').on('input', function() {
        var keyword = $(this).val().trim().toLowerCase();
        $('#ae-sortable-body tr[data-id]').each(function() {
            var name = ($(this).attr('data-name') || '').toLowerCase();
            $(this).toggleClass('ae-search-hidden', keyword !== '' && name.indexOf(keyword) === -1);
        });
    });

    // =============================================
    // 全选 / 批量删除
    // =============================================
    function updateBulkBtn() {
        var hasSelection = $('.ae-row-check:checked').length > 0;
        $('#ae-bulk-delete-btn').prop('disabled', !hasSelection);
        $('#ae-bulk-move-btn').prop('disabled', !hasSelection);
    }

    // 全选仅作用于当前可见行（被分类筛选或搜索隐藏的行不受影响），
    // 避免筛选后全选误将隐藏行一并纳入批量操作
    $('#ae-check-all').on('change', function() {
        $('.ae-row-check:visible').prop('checked', $(this).prop('checked'));
        updateBulkBtn();
    });

    $(document).on('change', '.ae-row-check', function() {
        updateBulkBtn();
        var total = $('.ae-row-check:visible').length;
        var checked = $('.ae-row-check:visible:checked').length;
        $('#ae-check-all').prop('checked', total === checked && total > 0);
    });

    $('#ae-bulk-delete-btn').on('click', function() {
        var $checked = $('.ae-row-check:checked');
        if ($checked.length === 0) { alert(l10n.noBulkSelected); return; }

        openConfirm({
            title: l10n.confirmBulkDeleteTitle || '确认批量删除所选应用？',
            lead: (l10n.confirmBulkDeleteLead || '即将删除所选的 %d 个应用：').replace('%d', $checked.length),
            items: [
                l10n.confirmBulkDeleteItem1 || '点击「确认删除」后会立即执行删除，无需额外保存；',
                l10n.confirmBulkDeleteItem2 || '这些应用及其图标将从数据库和媒体库中被永久删除（含各种缩略图尺寸），删除后无法恢复；',
                l10n.confirmBulkDeleteItem3 || '若某图标仍被其他应用或首页海报引用，则会自动保留，不会误删。'
            ],
            notice: l10n.confirmBulkDeleteNotice || '温馨提示：此操作不可恢复；如需保留，请点击「取消」。',
            okText: l10n.confirmDeleteBtn || '确认删除',
            onConfirm: function() {
                $('#ae-bulk-form').submit();
            }
        });
    });

    // =============================================
    // 批量移动分类
    // =============================================
    var $moveOverlay = $('#ae-move-modal-overlay');

    function openMoveModal() {
        var $checked = $('.ae-row-check:checked');
        if ($checked.length === 0) { alert(l10n.noBulkSelected); return; }

        // 汇总勾选应用的 ID 与其当前所挂分类（含重命名后遗留的旧分类名）
        var ids = [];
        var catMap = {};
        $checked.each(function() {
            var $row = $(this).closest('tr');
            ids.push($row.attr('data-id'));
            var rowCats = ($row.attr('data-filter-category') || '').split(',');
            for (var i = 0; i < rowCats.length; i++) {
                var c = rowCats[i].trim();
                if (c) { catMap[c] = true; }
            }
        });

        $('#ae-move-app-ids').val(ids.join(','));
        $('#ae-move-selected-count').text((l10n.moveSelected || '已选择 %d 个应用').replace('%d', ids.length));

        // 源分类选项 = 勾选应用身上实际存在的分类；末尾提供整体覆盖选项
        var $source = $('#ae-move-source');
        $source.empty();
        Object.keys(catMap).sort().forEach(function(c) {
            $source.append($('<option>', { value: c, text: c }));
        });
        $source.append($('<option>', { value: '', text: l10n.moveAllOption || '全部（覆盖现有分类）' }));

        // 每次打开重置目标分类
        $('#ae-move-target').val('');

        $moveOverlay.fadeIn(200);
    }

    function closeMoveModal() {
        $moveOverlay.fadeOut(200);
    }

    $('#ae-bulk-move-btn').on('click', openMoveModal);
    $('#ae-move-modal-close, #ae-move-cancel').on('click', closeMoveModal);
    $moveOverlay.on('click', function(e) {
        if ($(e.target).is($moveOverlay)) { closeMoveModal(); }
    });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $moveOverlay.is(':visible')) { closeMoveModal(); }
    });

    $('#ae-bulk-move-form').on('submit', function() {
        if (!$('#ae-move-target').val()) {
            alert(l10n.moveSelectTarget || '请选择目标分类。');
            return false;
        }
        return confirm(l10n.moveConfirm || '确认移动所选应用的分类？');
    });

    // =============================================
    // 按分类拖拽排序
    // =============================================
    var currentSortCategory = '';
    var sortableInitialized = false;

    function initSortable() {
        if (sortableInitialized) {
            $('#ae-sortable-body').sortable('destroy');
        }
        $('#ae-sortable-body').sortable({
            handle: '.ae-drag-handle',
            axis: 'y',
            placeholder: 'ui-sortable-placeholder',
            items: 'tr[data-id]:not(.ae-category-hidden):not(.ae-search-hidden)',
            helper: function(e, tr) {
                var $originals = tr.children();
                var $helper = tr.clone();
                $helper.children().each(function(index) {
                    $(this).width($originals.eq(index).width());
                });
                return $helper;
            },
            update: function() {
                if (!currentSortCategory) return;

                var order = [];
                $('#ae-sortable-body tr[data-id]:not(.ae-category-hidden)').each(function() {
                    order.push($(this).attr('data-id'));
                });

                $.post(l10n.ajaxUrl, {
                    action: 'apps_exhibition_save_category_order',
                    nonce: l10n.sortNonce,
                    category: currentSortCategory,
                    order: order
                }, function(response) {
                    showToast(response.success ? (l10n.sortSaved || '排序已保存') : (l10n.sortError || '排序保存失败'), !response.success);
                }).fail(function() {
                    showToast(l10n.sortError || '排序保存失败', true);
                });
            }
        });
        sortableInitialized = true;
    }

    // 分类选择切换
    $('#ae-category-filter').on('change', function() {
        var category = $(this).val();
        currentSortCategory = category;

        if (category === '') {
            // 全部模式 - 显示所有，禁用排序
            $('#ae-sortable-body tr[data-id]').removeClass('ae-category-hidden');
            $('#ae-apps-table').removeClass('ae-sort-mode');
            $('#ae-sort-hint').hide();
            if (sortableInitialized) {
                $('#ae-sortable-body').sortable('disable');
            }
        } else {
            // 分类模式 - 只显示该分类的应用，启用排序
            $('#ae-sortable-body tr[data-id]').each(function() {
                var cats = ($(this).attr('data-filter-category') || '').split(',').map(function(c) { return c.trim(); });
                $(this).toggleClass('ae-category-hidden', cats.indexOf(category) === -1);
            });
            $('#ae-apps-table').addClass('ae-sort-mode');
            $('#ae-sort-hint').show();

            initSortable();
            $('#ae-sortable-body').sortable('enable');
        }
    });

    // 初始化（默认全部模式，不可排序）
    if ($('#ae-sortable-body tr[data-id]').length > 0) {
        initSortable();
        $('#ae-sortable-body').sortable('disable');
    }

    function showToast(msg, isError) {
        var $toast = $('<div class="ae-sort-toast' + (isError ? ' ae-toast-error' : '') + '">' + msg + '</div>');
        $('body').append($toast);
        setTimeout(function() { $toast.fadeOut(300, function() { $toast.remove(); }); }, 2000);
    }

    // =============================================
    // 首页海报
    // =============================================
    (function() {
        var HOME_POSTERS_INPUT = '#home_posters';
        if (!$(HOME_POSTERS_INPUT).length) return;

        var MAX_POSTERS = parseInt(l10n.maxPosters, 10) || 10;

        function getPostersArray() {
            try { var arr = JSON.parse($(HOME_POSTERS_INPUT).val()); return Array.isArray(arr) ? arr : []; }
            catch(e) { return []; }
        }

        // 拖拽手柄（含顺序序号）：按住手柄可调整海报顺序
        function posterHandleHtml(order) {
            return '<div class="poster-drag-handle" title="' + escAttr(l10n.dragToSort || '拖拽调整顺序') + '">' +
                '<span class="poster-drag-grip" aria-hidden="true"></span>' +
                '<span class="poster-order">' + order + '</span>' +
                '</div>';
        }

        // 海报配置卡片：图片 + 更换/删除按钮 + 下载链接与按钮文字。
        // 每个条目写入 data-index（当前数组下标），拖拽后据此还原新顺序。
        function renderConfig(posters) {
            var config = '';
            for (var i = 0; i < posters.length; i++) {
                config += '<div class="poster-config-item" data-index="' + i + '" style="border:1px solid #ccc; border-radius:6px; padding:10px; margin-bottom:10px; background:#f9f9f9; display:flex; align-items:flex-start; gap:15px;">' +
                    '<div style="flex:0 0 auto; text-align:center;">' +
                    '<div class="poster-media-row">' +
                    posterHandleHtml(i + 1) +
                    '<img class="poster-preview-img" src="' + escAttr(posters[i].url) + '" style="max-width:200px; max-height:150px; border-radius:6px;">' +
                    '</div>' +
                    '<div style="margin-top:6px; display:flex; gap:6px; justify-content:center;">' +
                    '<button type="button" class="button button-small change-poster-conf">' + (l10n.changePosterBtn || '更换图片') + '</button>' +
                    '<button type="button" class="button button-small remove-poster-conf">' + (l10n.removePosterBtn || '删除图片') + '</button>' +
                    '</div>' +
                    '</div>' +
                    '<div style="flex:1 1 auto; display:flex; flex-direction:column; gap:10px;">' +
                    '<div><input type="text" class="widefat download-url-input" placeholder="' + (l10n.downloadAddrPlc || '下载地址') + '" value="' + escAttr(posters[i].download_url || '') + '"></div>' +
                    '<div><input type="text" class="widefat download-text-input" placeholder="' + (l10n.downloadTextPlc || '按钮文字') + '" value="' + escAttr(posters[i].download_text || '') + '"></div>' +
                    '</div></div>';
            }
            $('#poster_config_list').html(config);
        }

        function renderHomePosters(posters) {
            renderConfig(posters);
        }

        // =============================================
        // 更换 / 删除 图片 二次确认弹窗
        // 确认后仅更新隐藏域与界面，图片实际删除在「保存海报配置」时由后端联动完成
        // =============================================
        var $posterConfirmOverlay = $('#ae-poster-confirm-overlay');
        var posterConfirmTarget = null;

        function openPosterConfirm(mode, index, newUrl) {
            var cur = getPostersArray();
            var oldUrl = (cur[index] && cur[index].url) ? cur[index].url : '';

            posterConfirmTarget = { mode: mode, index: index, newUrl: newUrl || '' };

            $('#ae-poster-confirm-old').css('background-image', oldUrl ? 'url("' + oldUrl + '")' : 'none');

            if (mode === 'change') {
                $('#ae-poster-confirm-title').text(l10n.confirmChangePosterTitle || '确认更换海报图片？');
                $('#ae-poster-confirm-lead').text(l10n.confirmChangePosterLead || '即将用新选择的图片替换当前海报图片：');
                $('#ae-poster-confirm-new').css('background-image', newUrl ? 'url("' + newUrl + '")' : 'none');
                $('#ae-poster-confirm-new-wrap, #ae-poster-confirm-arrow').show();
                $('#ae-poster-confirm-ok').text(l10n.confirmChangePosterBtn || '确认更换');
            } else {
                $('#ae-poster-confirm-title').text(l10n.confirmRemovePosterTitle || '确认删除海报图片？');
                $('#ae-poster-confirm-lead').text(l10n.confirmRemovePosterLead || '即将删除以下海报图片：');
                $('#ae-poster-confirm-new-wrap, #ae-poster-confirm-arrow').hide();
                $('#ae-poster-confirm-ok').text(l10n.confirmRemovePosterBtn || '确认删除');
            }

            $posterConfirmOverlay.fadeIn(200);
        }

        function closePosterConfirm() {
            $posterConfirmOverlay.fadeOut(200);
            posterConfirmTarget = null;
        }

        $('#ae-poster-confirm-ok').on('click', function() {
            if (!posterConfirmTarget) { closePosterConfirm(); return; }

            var target = posterConfirmTarget;
            var cur = getPostersArray();

            if (target.mode === 'change') {
                if (cur[target.index]) { cur[target.index].url = target.newUrl; }
            } else if (cur[target.index]) {
                cur.splice(target.index, 1);
            }

            $(HOME_POSTERS_INPUT).val(JSON.stringify(cur));
            renderHomePosters(cur);
            closePosterConfirm();
        });

        $('#ae-poster-confirm-close, #ae-poster-confirm-cancel').on('click', closePosterConfirm);

        $posterConfirmOverlay.on('click', function(e) {
            if ($(e.target).is($posterConfirmOverlay)) closePosterConfirm();
        });

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $posterConfirmOverlay.is(':visible')) closePosterConfirm();
        });

        // =============================================
        // 上传 / 更换海报
        // =============================================
        var uploadFrame = null, changeFrame = null, changeIdx = -1;

        $('#upload_home_poster').on('click', function(e) {
            e.preventDefault();
            if (getPostersArray().length >= MAX_POSTERS) { alert(l10n.maxPostersAlert); return; }
            if (uploadFrame) { uploadFrame.open(); return; }
            uploadFrame = wp.media({ title: l10n.selectPosterTitle, button: { text: l10n.insertBtn }, multiple: true });
            uploadFrame.on('select', function() {
                var atts = uploadFrame.state().get('selection').toArray();
                var cur = getPostersArray();
                if (cur.length + atts.length > MAX_POSTERS) { alert(l10n.maxPostersExceed); return; }
                atts.forEach(function(att) { cur.push({ url: att.attributes.url, download_url: '', download_text: '' }); });
                $(HOME_POSTERS_INPUT).val(JSON.stringify(cur));
                renderHomePosters(cur);
            });
            uploadFrame.open();
        });

        // 更换图片：先选新图，若与原图不同再弹二次确认；确认后旧图将在保存时联动删除
        $('#poster_config_list').on('click', '.change-poster-conf', function(e) {
            e.preventDefault();
            changeIdx = $(this).closest('.poster-config-item').index();
            if (changeFrame) { changeFrame.open(); return; }
            changeFrame = wp.media({ title: l10n.selectPosterTitle, button: { text: l10n.insertBtn }, multiple: false });
            changeFrame.on('select', function() {
                var att = changeFrame.state().get('selection').first().toJSON();
                var cur = getPostersArray();
                var oldUrl = (cur[changeIdx] && cur[changeIdx].url) ? cur[changeIdx].url : '';
                if (att.url === oldUrl) { return; } // 未实际更换，无需确认
                openPosterConfirm('change', changeIdx, att.url);
            });
            changeFrame.open();
        });

        // 删除图片：直接弹二次确认
        $('#poster_config_list').on('click', '.remove-poster-conf', function(e) {
            e.preventDefault();
            openPosterConfirm('remove', $(this).closest('.poster-config-item').index(), '');
        });

        $('#poster_config_list').on('input', '.download-url-input, .download-text-input', function() {
            var cur = getPostersArray();
            $('#poster_config_list .poster-config-item').each(function(i) {
                if (cur[i]) {
                    cur[i].url = $(this).find('img').attr('src') || '';
                    cur[i].download_url = $(this).find('.download-url-input').val().trim();
                    cur[i].download_text = $(this).find('.download-text-input').val().trim();
                }
            });
            $(HOME_POSTERS_INPUT).val(JSON.stringify(cur));
        });

        // =============================================
        // 海报排序（拖拽手柄调整顺序；保存后前端轮播按此顺序展示）
        // =============================================
        // 读取被拖拽容器内的 DOM 顺序，还原海报数组，写回隐藏域并重绘列表
        function applyPosterOrder($container, itemSelector) {
            var order = [];
            $container.children(itemSelector).each(function() {
                order.push(parseInt($(this).attr('data-index'), 10));
            });

            var cur = getPostersArray();
            // 仅当顺序恰为 0..n-1 的完整排列时才应用，避免异常 DOM 导致数据错乱
            if (order.length !== cur.length) return;
            var sorted = order.slice().sort(function(a, b) { return a - b; });
            for (var k = 0; k < sorted.length; k++) {
                if (sorted[k] !== k) return;
            }

            var next = order.map(function(i) { return cur[i]; });
            $(HOME_POSTERS_INPUT).val(JSON.stringify(next));

            // 延后到本次拖拽完全结束后再重绘，避免与 jQuery UI 内部清理冲突
            setTimeout(function() {
                renderHomePosters(next);
                showToast(l10n.posterOrderChanged || '顺序已调整，请点击「保存海报配置」生效');
            }, 0);
        }

        var posterSortableReady = false;
        function initPosterSortable() {
            if (posterSortableReady) return;
            posterSortableReady = true;

            $('#poster_config_list').sortable({
                handle: '.poster-drag-handle',
                items: '> .poster-config-item',
                axis: 'y',
                placeholder: 'poster-sortable-placeholder',
                forcePlaceholderSize: true,
                tolerance: 'pointer',
                update: function() { applyPosterOrder($(this), '.poster-config-item'); }
            });
        }

        initPosterSortable();
        renderHomePosters(getPostersArray());
    })();

    // =============================================
    // 分类/平台列表编辑（分类设置）
    // 顶部输入 + 添加按钮，下方竖排列表：左侧拖拽手柄，右侧编辑 / 删除
    // 仅修改当前页面状态与隐藏域，所有更改必须点击「保存」后才生效
    // =============================================
    (function() {
        var $editors = $('.ae-cat-editor');
        if (!$editors.length) return;

        var MAX_ITEMS = 50;
        var MAX_LEN = 30;

        // 清洗名称：去掉换行与逗号（分类名以逗号拼接存储，含逗号会破坏数据）
        function cleanName(raw) {
            return String(raw == null ? '' : raw).replace(/[\r\n]+/g, ' ').replace(/[,，]/g, '').trim();
        }

        function getValues($editor) {
            var values = [];
            $editor.find('.ae-cat-name').each(function() { values.push($(this).text()); });
            return values;
        }

        // 把当前列表顺序写回隐藏域，并切换空状态（不提交则不生效）
        function sync($editor) {
            var values = getValues($editor);
            $editor.find('.ae-cat-value').val(values.join('\n'));
            $editor.find('.ae-cat-list').toggleClass('has-items', values.length > 0);
        }

        function exists($editor, name, $except) {
            var found = false;
            $editor.find('.ae-cat-name').each(function() {
                if ($except && $except.is(this)) return;
                if ($(this).text().toLowerCase() === name.toLowerCase()) { found = true; return false; }
            });
            return found;
        }

        function buildItem(name) {
            return $('<li class="ae-cat-item"></li>')
                .attr('data-origin', name)
                .append('<span class="ae-cat-drag dashicons dashicons-menu" title="' + escAttr(l10n.dragToSort || '拖拽排序') + '"></span>')
                .append($('<span class="ae-cat-name"></span>').text(name))
                .append('<span class="ae-cat-actions">' +
                    '<button type="button" class="ae-cat-edit" title="' + escAttr(l10n.editBtn || '修改') + '"><span class="dashicons dashicons-edit"></span></button>' +
                    '<button type="button" class="ae-cat-remove" title="' + escAttr(l10n.deleteBtn || '删除') + '"><span class="dashicons dashicons-trash"></span></button>' +
                    '</span>');
        }

        // 返回值：'added' 成功 / 'duplicate' 同名 / 'invalid' 超长或超量 / 'empty' 空输入
        function addItem($editor, rawName) {
            var name = cleanName(rawName);
            if (!name) return 'empty';

            if (name.length > MAX_LEN) {
                showToast((l10n.tagTooLong || '单项名称不能超过 %d 个字符。').replace('%d', MAX_LEN), true);
                return 'invalid';
            }
            if (exists($editor, name)) {
                showToast((l10n.tagDuplicate || '「%s」已存在，不可添加。').replace('%s', name), true);
                return 'duplicate';
            }
            if (getValues($editor).length >= MAX_ITEMS) {
                showToast((l10n.tagMax || '最多只能添加 %d 项。').replace('%d', MAX_ITEMS), true);
                return 'invalid';
            }

            $editor.find('.ae-cat-list').append(buildItem(name));
            sync($editor);
            return 'added';
        }

        // 拖拽排序：按住左侧手柄拖动，顺序写入隐藏域，保存后同步到前端筛选栏
        $editors.each(function() {
            var $editor = $(this);
            $editor.find('.ae-cat-list').sortable({
                handle: '.ae-cat-drag',
                items: '> .ae-cat-item',
                axis: 'y',
                placeholder: 'ae-cat-placeholder',
                forcePlaceholderSize: true,
                tolerance: 'pointer',
                update: function() { sync($editor); }
            });
        });

        // 顶部添加：点「添加」按钮
        $editors.on('click', '.ae-cat-add-btn', function() {
            var $editor = $(this).closest('.ae-cat-editor');
            var $input = $editor.find('.ae-cat-add-input');
            if (addItem($editor, $input.val()) !== 'invalid') $input.val('');
            $input.focus();
        });

        // 顶部添加：输入框回车
        $editors.on('keydown', '.ae-cat-add-input', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var $editor = $(this).closest('.ae-cat-editor');
            if (addItem($editor, $(this).val()) !== 'invalid') $(this).val('');
        });

        // 删除：复用通用二次确认弹窗，确认后仅修改页面状态
        $editors.on('click', '.ae-cat-remove', function(e) {
            e.preventDefault();
            var $item = $(this).closest('.ae-cat-item');
            var $editor = $item.closest('.ae-cat-editor');
            var name = $item.find('.ae-cat-name').text();

            openConfirm({
                title: l10n.confirmRemoveItemTitle || '确认删除该项？',
                lead: (l10n.confirmRemoveItemLead || '即将删除：') + name,
                items: [
                    l10n.confirmRemoveItemItem1 || '该项会立即从当前列表中移除；',
                    l10n.confirmRemoveItemItem2 || '点击「保存」后更改才会真正生效；',
                    l10n.confirmRemoveItemItem3 || '未点击「保存」前关闭页面，将保留原有分类。'
                ],
                notice: l10n.confirmRemoveItemNotice || '温馨提示：删除将在点击「保存」后生效；如需保留，请点击「取消」。',
                okText: l10n.confirmRemoveItemBtn || '确认删除',
                onConfirm: function() {
                    $item.remove();
                    sync($editor);
                }
            });
        });

        // 行内就地改名：点铅笔按钮进入编辑（Enter 确认 / Esc 取消，仅改页面状态）
        function startEdit($item) {
            if (!$item.length || $item.hasClass('is-editing')) return;

            var $editor = $item.closest('.ae-cat-editor');
            var $name = $item.find('.ae-cat-name');
            var oldName = $name.text();
            var $input = $('<input type="text" class="ae-cat-edit-input" />').val(oldName);

            $item.addClass('is-editing');
            $name.addClass('is-editing').after($input);
            $input.trigger('focus').trigger('select');

            var done = false;
            function finish(commit) {
                if (done) return;
                done = true;

                if (commit) {
                    var newName = cleanName($input.val());
                    if (newName && newName !== oldName) {
                        if (newName.length > MAX_LEN) {
                            showToast((l10n.tagTooLong || '单项名称不能超过 %d 个字符。').replace('%d', MAX_LEN), true);
                        } else if (exists($editor, newName, $name)) {
                            showToast((l10n.tagDuplicateRename || '「%s」已存在，无法改为该名称。').replace('%s', newName), true);
                        } else {
                            $name.text(newName);
                            sync($editor);
                        }
                    }
                }

                $input.remove();
                $name.removeClass('is-editing');
                $item.removeClass('is-editing');
            }

            $input.on('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); finish(true); }
                else if (e.key === 'Escape') { e.preventDefault(); finish(false); }
            });
            $input.on('blur', function() { finish(true); });
        }

        $editors.on('click', '.ae-cat-edit', function(e) {
            e.preventDefault();
            startEdit($(this).closest('.ae-cat-item'));
        });

        // 提交前：校验至少保留一项，并收集「老名→新名」映射供后端自动迁移
        $editors.each(function() {
            var $editor = $(this);

            $editor.closest('form').on('submit', function(e) {
                if (getValues($editor).length === 0) {
                    e.preventDefault();
                    showToast(l10n.tagEmpty || '请至少保留一项后再保存。', true);
                    return;
                }

                var renames = {};
                $editor.find('.ae-cat-item').each(function() {
                    var $item = $(this);
                    var origin = $item.attr('data-origin') || '';
                    var current = $item.find('.ae-cat-name').text();
                    if (origin && current && origin !== current) {
                        renames[origin] = current;
                    }
                });
                $editor.find('.ae-cat-renames').val(JSON.stringify(renames));
            });
        });

        $editors.each(function() { sync($(this)); });
    })();
});
