jQuery(document).ready(function($) {
    'use strict';

    var l10n = window.appsExhibitionL10n || {};

    // HTML 属性值转义
    function escAttr( value ) {
        return String( value == null ? '' : value ).replace( /[&<>"']/g, function( c ) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
        } );
    }

    // 确保任何系统通知消息始终置于页面最顶部的 .ae-notices-container 中
    function ensureNoticesAtTop() {
        var $notices = $('.ae-admin-wrapper .notice, .ae-admin-wrapper div.updated, .ae-admin-wrapper div.error');
        if ($notices.length) {
            var $container = $('.ae-notices-container');
            if ($container.length && !$notices.parent().is('.ae-notices-container')) {
                $container.append($notices);
            }
        }
    }
    ensureNoticesAtTop();
    setTimeout(ensureNoticesAtTop, 80);

    function showToast(msg, isError) {
        var $toast = $('<div class="ae-sort-toast' + (isError ? ' ae-toast-error' : '') + '">' + msg + '</div>');
        $('body').append($toast);
        setTimeout(function() {
            $toast.fadeOut(300, function() { $toast.remove(); });
        }, 2200);
    }

    // =============================================
    // 模态框通用状态与事件
    // =============================================
    var $overlay = $('#ae-modal-overlay');
    var $iconRemoveOverlay = $('#ae-icon-remove-overlay');
    var $confirmOverlay = $('#ae-confirm-overlay');
    var $helpOverlay = $('#ae-help-overlay');
    var $lightboxOverlay = $('#ae-poster-lightbox-overlay');
    var $moveOverlay = $('#ae-move-modal-overlay');
    var $posterConfirmOverlay = $('#ae-poster-confirm-overlay');

    var $form = $('#apps-exhibition-form');
    var $title = $('#ae-modal-title');

    function openModal(mode, data) {
        $form[0].reset();
        $('#ae-form-app-id').val(0);
        $('#app_badge').val('');
        $('#app_icon').val('');
        $('#app_icon_preview').css('background-image', 'none');
        $('#ae-removed-icon-url').val('');
        $('#downloads_container').html(getDownloadItemHtml('', ''));
        updateAddDownloadBtn();
        $form.find('input[type="checkbox"]').prop('checked', false);
        $form.find('.ae-chip-card').removeClass('is-checked');

        if (mode === 'edit' && data) {
            $title.text(l10n.editApp || '编辑应用');
            $('#ae-form-app-id').val(data.id);
            $('#app_name').val(data.name);
            $('#app_badge').val(data.badge || '');
            $('#app_description').val(data.description);
            $('#app_icon').val(data.icon);
            if (data.icon) {
                $('#app_icon_preview').css('background-image', 'url(' + data.icon + ')');
            }
            if (data.platforms) {
                data.platforms.split(',').forEach(function(p) {
                    var $cb = $form.find('input[name="app_platforms[]"][value="' + $.escapeSelector(p.trim()) + '"]');
                    $cb.prop('checked', true);
                    $cb.closest('.ae-chip-card').addClass('is-checked');
                });
            }
            if (data.filterCategory) {
                data.filterCategory.split(',').forEach(function(c) {
                    var $cb = $form.find('input[name="app_filter_category[]"][value="' + $.escapeSelector(c.trim()) + '"]');
                    $cb.prop('checked', true);
                    $cb.closest('.ae-chip-card').addClass('is-checked');
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

    // 平台与分类芯片卡片勾选联动
    $form.on('change', '.ae-chip-checkbox', function() {
        var $card = $(this).closest('.ae-chip-card');
        if (this.checked) {
            $card.addClass('is-checked');
        } else {
            $card.removeClass('is-checked');
        }
    });

    // 帮助弹窗
    $('#ae-help-toggle-btn').on('click', function() {
        $helpOverlay.fadeIn(200);
        $('body').css('overflow', 'hidden');
    });
    $('#ae-help-close, #ae-help-confirm').on('click', function() {
        $helpOverlay.fadeOut(200);
        $('body').css('overflow', '');
    });
    $helpOverlay.on('click', function(e) {
        if ($(e.target).is($helpOverlay)) {
            $helpOverlay.fadeOut(200);
            $('body').css('overflow', '');
        }
    });

    // 海报灯箱大图预览
    $(document).on('click', '.poster-preview-action', function(e) {
        e.preventDefault();
        var url = $(this).attr('data-url');
        if (!url) return;
        $('#ae-lightbox-img').attr('src', url);
        $lightboxOverlay.fadeIn(200);
        $('body').css('overflow', 'hidden');
    });
    $('#ae-lightbox-close').on('click', function() {
        $lightboxOverlay.fadeOut(200);
        $('body').css('overflow', '');
    });
    $lightboxOverlay.on('click', function(e) {
        if ($(e.target).is($lightboxOverlay)) {
            $lightboxOverlay.fadeOut(200);
            $('body').css('overflow', '');
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key !== 'Escape') return;
        if ($confirmOverlay.is(':visible')) { closeConfirm(); return; }
        if ($iconRemoveOverlay.is(':visible')) { $iconRemoveOverlay.fadeOut(200); return; }
        if ($posterConfirmOverlay.is(':visible')) { $posterConfirmOverlay.fadeOut(200); return; }
        if ($helpOverlay.is(':visible')) { $helpOverlay.fadeOut(200); $('body').css('overflow', ''); return; }
        if ($lightboxOverlay.is(':visible')) { $lightboxOverlay.fadeOut(200); $('body').css('overflow', ''); return; }
        if ($overlay.is(':visible')) closeModal();
    });

    // 编辑按钮
    $(document).on('click', '.ae-edit-btn', function() {
        var $row = $(this).closest('tr');
        var downloads = [];
        try { downloads = JSON.parse($row.attr('data-downloads') || '[]'); } catch(e) {}

        openModal('edit', {
            id: $row.attr('data-id'),
            name: $row.attr('data-name'),
            badge: $row.attr('data-badge'),
            description: $row.attr('data-description'),
            icon: $row.attr('data-icon'),
            platforms: $row.attr('data-platforms'),
            filterCategory: $row.attr('data-filter-category'),
            downloads: downloads
        });
    });

    // =============================================
    // 通用二次确认弹窗
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

    // 应用单项删除确认
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
    // 应用图标上传
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
        if (!$('#app_icon').val()) { clearIconField(); return; }
        $('#ae-icon-remove-preview').css('background-image', 'url(' + $('#app_icon').val() + ')');
        $iconRemoveOverlay.fadeIn(200);
    });

    $('#ae-icon-remove-confirm').on('click', function() {
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
    // 下载链接表单交互
    // =============================================
    var MAX_DOWNLOADS = 3;

    function getDownloadItemHtml(url, text) {
        return '<div class="download-item">' +
            '<input type="url" name="download_url[]" placeholder="' + escAttr(l10n.downloadUrlPlc || '下载链接 URL') + '" value="' + escAttr(url || '') + '" class="ae-input dl-url" required />' +
            '<input type="text" name="download_text[]" placeholder="' + escAttr(l10n.downloadTextPlc || '按钮文字（如：下载、安卓商店）') + '" value="' + escAttr(text || '') + '" class="ae-input dl-text" required />' +
            '<button type="button" class="ae-square-btn ae-square-btn-delete remove-download-button" title="' + escAttr(l10n.deleteBtn || '删除') + '">' +
            '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>' +
            '</button>' +
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

    // 表单提交校验
    $form.on('submit', function() {
        if (!$('#app_name').val().trim() || !$('#app_description').val().trim() || !$('#app_icon').val().trim()) {
            alert(l10n.fillRequired || '请填写所有必填项 (应用名称、描述、图标)。');
            return false;
        }
        if ($form.find('input[name="app_platforms[]"]:checked').length === 0) {
            alert(l10n.selectPlatform || '请至少选择一个平台分类。');
            return false;
        }
        if ($form.find('input[name="app_filter_category[]"]:checked').length === 0) {
            alert(l10n.selectFilter || '请至少选择一个筛选分类。');
            return false;
        }
        var hasValid = false, valid = true;
        $('#downloads_container .download-item').each(function() {
            var url = $(this).find('input[name="download_url[]"]').val().trim();
            var text = $(this).find('input[name="download_text[]"]').val().trim();
            if (url && text) { hasValid = true; }
            else if (url || text) {
                alert(l10n.fillDownload || '请确保所有下载链接和按钮文字均已填写。');
                valid = false;
                return false;
            }
        });
        if (!hasValid) {
            alert(l10n.needOneDownload || '请至少填写一个完整下载链接（URL和按钮文字）。');
            return false;
        }
        return valid;
    });

    // =============================================
    // 应用列表：分页、搜索、排序与全选联动
    // =============================================
    var currentPage = 1;
    var pageSize = 10;
    var sortColumn = '';
    var sortDirection = 'asc';

    function getVisibleRows() {
        return $('#ae-sortable-body tr[data-id]:not(.ae-category-hidden):not(.ae-search-hidden)');
    }

    function renderPagination() {
        var $visibleRows = getVisibleRows();
        var total = $visibleRows.length;
        $('#ae-pagination-total').text('共 ' + total + ' 条');

        if (pageSize === 'all' || pageSize >= total || total === 0) {
            $visibleRows.removeClass('ae-page-hidden');
            $('#ae-page-numbers').empty();
            $('#ae-page-prev').prop('disabled', true);
            $('#ae-page-next').prop('disabled', true);
            return;
        }

        var totalPages = Math.ceil(total / pageSize);
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        var startIdx = (currentPage - 1) * pageSize;
        var endIdx = startIdx + pageSize;

        $visibleRows.each(function(index) {
            $(this).toggleClass('ae-page-hidden', index < startIdx || index >= endIdx);
        });

        // 渲染页码
        var $pageContainer = $('#ae-page-numbers').empty();
        for (var p = 1; p <= totalPages; p++) {
            var $btn = $('<button type="button" class="ae-page-btn' + (p === currentPage ? ' is-active' : '') + '">' + p + '</button>');
            (function(page) {
                $btn.on('click', function() {
                    currentPage = page;
                    renderPagination();
                    updateBulkBtn();
                });
            })(p);
            $pageContainer.append($btn);
        }

        $('#ae-page-prev').prop('disabled', currentPage <= 1);
        $('#ae-page-next').prop('disabled', currentPage >= totalPages);
    }

    $('#ae-page-prev').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            renderPagination();
            updateBulkBtn();
        }
    });

    $('#ae-page-next').on('click', function() {
        var total = getVisibleRows().length;
        var totalPages = Math.ceil(total / pageSize);
        if (currentPage < totalPages) {
            currentPage++;
            renderPagination();
            updateBulkBtn();
        }
    });

    $('#ae-page-size-select').on('change', function() {
        var val = $(this).val();
        pageSize = val === 'all' ? 'all' : parseInt(val, 10);
        currentPage = 1;
        renderPagination();
        updateBulkBtn();
    });

    // 搜索过滤联动
    $('#ae-search-input').on('input', function() {
        var keyword = $(this).val().trim().toLowerCase();
        $('#ae-sortable-body tr[data-id]').each(function() {
            var name = ($(this).attr('data-name') || '').toLowerCase();
            var badge = ($(this).attr('data-badge') || '').toLowerCase();
            var desc = ($(this).attr('data-description') || '').toLowerCase();
            var plats = ($(this).attr('data-platforms') || '').toLowerCase();
            var cats = ($(this).attr('data-filter-category') || '').toLowerCase();

            var match = (keyword === '') ||
                (name.indexOf(keyword) !== -1) ||
                (badge.indexOf(keyword) !== -1) ||
                (desc.indexOf(keyword) !== -1) ||
                (plats.indexOf(keyword) !== -1) ||
                (cats.indexOf(keyword) !== -1);

            $(this).toggleClass('ae-search-hidden', !match);
        });
        currentPage = 1;
        renderPagination();
        updateBulkBtn();
    });

    // 重置搜索/筛选设置按钮
    $('#ae-filter-setting-btn').on('click', function() {
        $('#ae-search-input').val('').trigger('input');
        $('#ae-category-filter').val('').trigger('change');
        showToast('已重置搜索与筛选条件');
    });

    // =============================================
    // 自定义带图标下拉选择器 (Custom Icon Select)
    // =============================================
    (function() {
        var $custom = $('#ae-category-filter-custom');
        if (!$custom.length) return;

        var $trigger = $custom.find('.ae-custom-select-trigger');
        var $realSelect = $custom.find('select');

        $trigger.on('click', function(e) {
            e.stopPropagation();
            var isOpen = $custom.hasClass('is-open');
            $('.ae-custom-select').removeClass('is-open');
            if (!isOpen) {
                $custom.addClass('is-open');
            }
        });

        $custom.on('click', '.ae-custom-select-item', function(e) {
            e.stopPropagation();
            var $item = $(this);
            var val = $item.attr('data-value');

            $custom.find('.ae-custom-select-item').removeClass('is-selected');
            $item.addClass('is-selected');

            $custom.find('.ae-custom-select-selected-icon').html($item.find('.ae-custom-option-icon').html());
            $custom.find('.ae-custom-select-selected-text').text($item.find('.ae-custom-option-text').text());

            $custom.removeClass('is-open');

            if ($realSelect.val() !== val) {
                $realSelect.val(val).trigger('change');
            }
        });

        $realSelect.on('change', function() {
            var val = $(this).val();
            var $matching = $custom.find('.ae-custom-select-item[data-value="' + $.escapeSelector(val) + '"]');
            if ($matching.length) {
                $custom.find('.ae-custom-select-item').removeClass('is-selected');
                $matching.addClass('is-selected');
                $custom.find('.ae-custom-select-selected-icon').html($matching.find('.ae-custom-option-icon').html());
                $custom.find('.ae-custom-select-selected-text').text($matching.find('.ae-custom-option-text').text());
            }
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.ae-custom-select').length) {
                $('.ae-custom-select').removeClass('is-open');
            }
        });

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.ae-custom-select').removeClass('is-open');
            }
        });
    })();

    // 表头快速排序（名称 / 描述）
    $('.ae-apps-table th[data-sort-key]').on('click', function() {
        var key = $(this).attr('data-sort-key');
        if (sortColumn === key) {
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            sortColumn = key;
            sortDirection = 'asc';
        }

        var $tbody = $('#ae-sortable-body');
        var $rows = $tbody.find('tr[data-id]').get();

        $rows.sort(function(a, b) {
            var valA = ($(a).attr(key === 'name' ? 'data-name' : 'data-description') || '').toLowerCase();
            var valB = ($(b).attr(key === 'name' ? 'data-name' : 'data-description') || '').toLowerCase();
            if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
            if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });

        $.each($rows, function(idx, item) {
            $tbody.append(item);
        });

        renderPagination();
    });

    // 全选与批量操作状态
    function updateBulkBtn() {
        var checkedCount = $('.ae-row-check:checked').length;
        var hasSelection = checkedCount > 0;

        $('#ae-bulk-delete-btn').prop('disabled', !hasSelection);
        $('#ae-bulk-move-btn').prop('disabled', !hasSelection);

        if (hasSelection) {
            $('#ae-selected-count-label').show();
            $('#ae-selected-count-num').text(checkedCount);
            $('#ae-footer-bulk-delete-btn').show().prop('disabled', false);
            $('#ae-footer-bulk-move-btn').show().prop('disabled', false);
        } else {
            $('#ae-selected-count-label').hide();
            $('#ae-footer-bulk-delete-btn').hide();
            $('#ae-footer-bulk-move-btn').hide();
        }

        // 高亮选中行
        $('.ae-row-check').each(function() {
            $(this).closest('tr').toggleClass('is-selected', $(this).prop('checked'));
        });

        var visibleChecks = $('.ae-row-check:visible');
        var visibleChecked = $('.ae-row-check:visible:checked');
        $('#ae-check-all').prop('checked', visibleChecks.length > 0 && visibleChecks.length === visibleChecked.length);
    }

    $('#ae-check-all').on('change', function() {
        $('.ae-row-check:visible').prop('checked', $(this).prop('checked'));
        updateBulkBtn();
    });

    $(document).on('change', '.ae-row-check', function() {
        updateBulkBtn();
    });

    // 批量删除
    function handleBulkDelete() {
        var $checked = $('.ae-row-check:checked');
        if ($checked.length === 0) { alert(l10n.noBulkSelected || '请先勾选需要操作的应用。'); return; }

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
    }

    $('#ae-bulk-delete-btn, #ae-footer-bulk-delete-btn').on('click', handleBulkDelete);

    // 批量移动分类
    function openMoveModal() {
        var $checked = $('.ae-row-check:checked');
        if ($checked.length === 0) { alert(l10n.noBulkSelected || '请先勾选需要操作的应用。'); return; }

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

        var $source = $('#ae-move-source').empty();
        Object.keys(catMap).sort().forEach(function(c) {
            $source.append($('<option>', { value: c, text: c }));
        });
        $source.append($('<option>', { value: '', text: l10n.moveAllOption || '全部（覆盖现有分类）' }));

        $('#ae-move-target').val('');
        $moveOverlay.fadeIn(200);
        $('body').css('overflow', 'hidden');
    }

    function closeMoveModal() {
        $moveOverlay.fadeOut(200);
        $('body').css('overflow', '');
    }

    $('#ae-bulk-move-btn, #ae-footer-bulk-move-btn').on('click', openMoveModal);
    $('#ae-move-modal-close, #ae-move-cancel').on('click', closeMoveModal);
    $moveOverlay.on('click', function(e) { if ($(e.target).is($moveOverlay)) closeMoveModal(); });

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

    $('#ae-category-filter').on('change', function() {
        var category = $(this).val();
        currentSortCategory = category;

        if (category === '') {
            $('#ae-sortable-body tr[data-id]').removeClass('ae-category-hidden');
            $('#ae-apps-table').removeClass('ae-sort-mode');
            $('#ae-sort-hint').hide();
            if (sortableInitialized) {
                $('#ae-sortable-body').sortable('disable');
            }
            renderPagination();
        } else {
            $('#ae-sortable-body tr[data-id]').each(function() {
                var cats = ($(this).attr('data-filter-category') || '').split(',').map(function(c) { return c.trim(); });
                $(this).toggleClass('ae-category-hidden', cats.indexOf(category) === -1);
            });
            $('#ae-apps-table').addClass('ae-sort-mode');
            $('#ae-sort-hint').show();

            // 排序模式下展示该分类的所有项，便于跨页自由拖动
            $('#ae-sortable-body tr[data-id]:not(.ae-category-hidden)').removeClass('ae-page-hidden');
            initSortable();
            $('#ae-sortable-body').sortable('enable');
        }
        updateBulkBtn();
    });

    if ($('#ae-sortable-body tr[data-id]').length > 0) {
        initSortable();
        $('#ae-sortable-body').sortable('disable');
        renderPagination();
    }

    // =============================================
    // 「首页海报」Tab 交互逻辑
    // =============================================
    (function() {
        var HOME_POSTERS_INPUT = '#home_posters';
        if (!$(HOME_POSTERS_INPUT).length) return;

        var MAX_POSTERS = parseInt(l10n.maxPosters, 10) || 10;

        function getPostersArray() {
            try { var arr = JSON.parse($(HOME_POSTERS_INPUT).val()); return Array.isArray(arr) ? arr : []; }
            catch(e) { return []; }
        }

        function posterHandleHtml(order) {
            return '<div class="poster-handle-badge" title="' + escAttr(l10n.dragToSort || '拖拽调整顺序') + '">' +
                '<span class="poster-drag-cross"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                '<polyline points="5 9 2 12 5 15"></polyline>' +
                '<polyline points="9 5 12 2 15 5"></polyline>' +
                '<polyline points="15 19 12 22 9 19"></polyline>' +
                '<polyline points="19 9 22 12 19 15"></polyline>' +
                '<line x1="2" y1="12" x2="22" y2="12"></line>' +
                '<line x1="12" y1="2" x2="12" y2="22"></line>' +
                '</svg></span>' +
                '<span class="poster-order-num">' + order + '</span>' +
                '</div>';
        }

        function renderConfig(posters) {
            var config = '';
            for (var i = 0; i < posters.length; i++) {
                config += '<div class="poster-config-item" data-index="' + i + '">' +
                    '<div class="poster-left-panel">' +
                    posterHandleHtml(i + 1) +
                    '<div class="poster-media-box">' +
                    '<div class="poster-img-container"><img class="poster-preview-img" src="' + escAttr(posters[i].url) + '" alt="" /></div>' +
                    '<div class="poster-img-btns">' +
                    '<button type="button" class="ae-btn ae-btn-xs ae-btn-sub change-poster-conf">' +
                    '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg> <span>' + (l10n.changePosterBtn || '更换图片') + '</span></button>' +
                    '<button type="button" class="ae-btn ae-btn-xs ae-btn-sub ae-btn-sub-danger remove-poster-conf">' +
                    '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg> <span>' + (l10n.removePosterBtn || '删除图片') + '</span></button>' +
                    '</div></div></div>' +
                    '<div class="poster-middle-panel">' +
                    '<div class="poster-input-group"><input type="text" class="ae-input download-url-input" placeholder="' + escAttr(l10n.downloadAddrPlc || '下载地址') + '" value="' + escAttr(posters[i].download_url || '') + '"></div>' +
                    '<div class="poster-input-group"><input type="text" class="ae-input download-text-input" placeholder="' + escAttr(l10n.downloadTextPlc || '按钮文字') + '" value="' + escAttr(posters[i].download_text || '') + '"></div>' +
                    '</div>' +
                    '<div class="poster-right-panel">' +
                    '<button type="button" class="poster-action-btn poster-preview-action" data-url="' + escAttr(posters[i].url) + '">' +
                    '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> <span>预览</span></button>' +
                    '<button type="button" class="poster-action-btn poster-delete-action">' +
                    '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg> <span>删除</span></button>' +
                    '</div></div>';
            }
            $('#poster_config_list').html(config);
        }

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
            $('body').css('overflow', 'hidden');
        }

        function closePosterConfirm() {
            $posterConfirmOverlay.fadeOut(200);
            $('body').css('overflow', '');
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
            renderConfig(cur);
            closePosterConfirm();
        });

        $('#ae-poster-confirm-close, #ae-poster-confirm-cancel').on('click', closePosterConfirm);
        $posterConfirmOverlay.on('click', function(e) { if ($(e.target).is($posterConfirmOverlay)) closePosterConfirm(); });

        // 上传新海报
        var uploadFrame = null, changeFrame = null, changeIdx = -1;
        $('#upload_home_poster').on('click', function(e) {
            e.preventDefault();
            if (getPostersArray().length >= MAX_POSTERS) { alert(l10n.maxPostersAlert || '已达最大海报数量限制。'); return; }
            if (uploadFrame) { uploadFrame.open(); return; }
            uploadFrame = wp.media({ title: l10n.selectPosterTitle || '选择海报图片', button: { text: l10n.insertBtn || '插入' }, multiple: true });
            uploadFrame.on('select', function() {
                var atts = uploadFrame.state().get('selection').toArray();
                var cur = getPostersArray();
                if (cur.length + atts.length > MAX_POSTERS) { alert(l10n.maxPostersExceed || '超过最大海报限制'); return; }
                atts.forEach(function(att) { cur.push({ url: att.attributes.url, download_url: '', download_text: '' }); });
                $(HOME_POSTERS_INPUT).val(JSON.stringify(cur));
                renderConfig(cur);
            });
            uploadFrame.open();
        });

        // 更换海报图片
        $('#poster_config_list').on('click', '.change-poster-conf', function(e) {
            e.preventDefault();
            changeIdx = $(this).closest('.poster-config-item').index();
            if (changeFrame) { changeFrame.open(); return; }
            changeFrame = wp.media({ title: l10n.selectPosterTitle || '选择海报图片', button: { text: l10n.insertBtn || '插入' }, multiple: false });
            changeFrame.on('select', function() {
                var att = changeFrame.state().get('selection').first().toJSON();
                var cur = getPostersArray();
                var oldUrl = (cur[changeIdx] && cur[changeIdx].url) ? cur[changeIdx].url : '';
                if (att.url === oldUrl) return;
                openPosterConfirm('change', changeIdx, att.url);
            });
            changeFrame.open();
        });

        // 删除海报图片
        $('#poster_config_list').on('click', '.remove-poster-conf', function(e) {
            e.preventDefault();
            openPosterConfirm('remove', $(this).closest('.poster-config-item').index(), '');
        });

        // 整行删除海报配置卡片
        $('#poster_config_list').on('click', '.poster-delete-action', function(e) {
            e.preventDefault();
            var idx = $(this).closest('.poster-config-item').index();
            openPosterConfirm('remove', idx, '');
        });

        // 输入监听
        $('#poster_config_list').on('input', '.download-url-input, .download-text-input', function() {
            var cur = getPostersArray();
            $('#poster_config_list .poster-config-item').each(function(i) {
                if (cur[i]) {
                    cur[i].download_url = $(this).find('.download-url-input').val().trim();
                    cur[i].download_text = $(this).find('.download-text-input').val().trim();
                }
            });
            $(HOME_POSTERS_INPUT).val(JSON.stringify(cur));
        });

        // 海报卡片拖拽排序
        function applyPosterOrder($container) {
            var order = [];
            $container.children('.poster-config-item').each(function() {
                order.push(parseInt($(this).attr('data-index'), 10));
            });

            var cur = getPostersArray();
            if (order.length !== cur.length) return;

            var next = order.map(function(i) { return cur[i]; });
            $(HOME_POSTERS_INPUT).val(JSON.stringify(next));

            setTimeout(function() {
                renderConfig(next);
                showToast(l10n.posterOrderChanged || '顺序已调整，请点击「保存海报配置」生效');
            }, 0);
        }

        $('#poster_config_list').sortable({
            handle: '.poster-handle-badge',
            items: '> .poster-config-item',
            axis: 'y',
            placeholder: 'poster-sortable-placeholder',
            forcePlaceholderSize: true,
            tolerance: 'pointer',
            update: function() { applyPosterOrder($(this)); }
        });

        renderConfig(getPostersArray());
    })();

    // =============================================
    // 「分类设置」Tab 双卡片编辑与全选
    // =============================================
    (function() {
        var $editors = $('.ae-cat-editor');
        if (!$editors.length) return;

        var MAX_ITEMS = 50;
        var MAX_LEN = 30;

        function cleanName(raw) {
            return String(raw == null ? '' : raw).replace(/[\r\n]+/g, ' ').replace(/[,，]/g, '').trim();
        }

        function getValues($editor) {
            var values = [];
            $editor.find('.ae-cat-name').each(function() { values.push($(this).text()); });
            return values;
        }

        function sync($editor) {
            var values = getValues($editor);
            $editor.find('.ae-cat-value').val(values.join('\n'));
            $editor.find('.ae-cat-list').toggleClass('has-items', values.length > 0);

            // 更新分类数量统计
            if ($editor.find('input[name="filter_categories"]').length) {
                $('#ae-filter-cat-text').text('共 ' + values.length + ' 个分类');
            }
        }

        function exists($editor, name, $except) {
            var found = false;
            $editor.find('.ae-cat-name').each(function() {
                if ($except && $except.is(this)) return;
                if ($(this).text().toLowerCase() === name.toLowerCase()) { found = true; return false; }
            });
            return found;
        }

        function getItemIconSvg(name, isPlatform) {
            var n = name.toLowerCase();
            if (isPlatform) {
                if (n.indexOf('androidtv') !== -1) {
                    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F6FFED"/><rect x="4" y="6" width="16" height="12" rx="3" stroke="#52C41A" stroke-width="1.8"/><circle cx="8" cy="12" r="1" fill="#52C41A"/><circle cx="16" cy="12" r="1" fill="#52C41A"/><path d="M10 18l-1 3m5-3l1 3" stroke="#52C41A" stroke-width="1.8" stroke-linecap="round"/></svg>';
                } else if (n.indexOf('android') !== -1 || n.indexOf('安卓') !== -1) {
                    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F6FFED"/><path d="M8 8.5a4 4 0 018 0v2H8v-2zm-1.5-2L5 5m12.5 1.5L19 5M7 12h10v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6z" fill="#52C41A"/><circle cx="10" cy="8" r="0.8" fill="#fff"/><circle cx="14" cy="8" r="0.8" fill="#fff"/></svg>';
                } else if (n === 'ios') {
                    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F5F5F5"/><path d="M14.5 4c-.5.6-1.3 1-2 1-.1-.7.3-1.5.8-2 .5-.5 1.3-.9 1.9-.9.1.7-.2 1.4-.7 1.9zm1.7 5.2c-.1 0-1.8 1-1.8 3.1s2.2 2.9 2.2 2.9c-.3 1-1.1 2.3-2 2.3-.7 0-1.2-.4-2-.4s-1.3.4-2 .4c-.9 0-1.8-1.3-2.3-2.6-.9-2.3-.2-5.1 1.7-5.1 1 0 1.7.6 2.3.6.5 0 1.4-.7 2.5-.6.5 0 1.9.1 2.7 1.2-.1.1-1.3.7-1.3 2.2z" fill="#262626"/></svg>';
                } else if (n.indexOf('ipad') !== -1) {
                    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#E6F4FF"/><rect x="5.5" y="4" width="13" height="16" rx="2" stroke="#1677FF" stroke-width="1.8"/><circle cx="12" cy="17.5" r="0.8" fill="#1677FF"/></svg>';
                } else if (n.indexOf('tvos') !== -1 || n.indexOf('appletv') !== -1) {
                    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#1F1F1F"/><rect x="5" y="7" width="14" height="10" rx="2" stroke="#fff" stroke-width="1.8"/><path d="M9 12h2.5m1.5-1.5l2 3m0-3l-2 3" stroke="#FA8C16" stroke-width="1.5" stroke-linecap="round"/></svg>';
                } else if (n.indexOf('mac') !== -1) {
                    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#E6F4FF"/><rect x="4" y="5" width="16" height="11" rx="1.5" stroke="#1677FF" stroke-width="1.8"/><path d="M9 19h6m-3-3v3" stroke="#1677FF" stroke-width="1.8" stroke-linecap="round"/></svg>';
                } else if (n.indexOf('win') !== -1) {
                    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#E6F4FF"/><path d="M5 5.5l5.5-.8v6.3H5V5.5zm6.5-1l7-1v7.3h-7V4.5zM5 12h5.5v6.3L5 17.5V12zm6.5 0h7v7.3l-7-1V12z" fill="#0078D4"/></svg>';
                }
                return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F0F5FF"/><rect x="6" y="5" width="12" height="14" rx="2" stroke="#2F54EB" stroke-width="1.8"/><circle cx="12" cy="16" r="1" fill="#2F54EB"/></svg>';
            }

            // 分类图标
            if (n.indexOf('ai') !== -1 || n.indexOf('agent') !== -1 || n.indexOf('智能') !== -1) {
                return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#E6F4FF"/><path d="M12 4v2m-5 4h10a2 2 0 012 2v5a2 2 0 01-2 2H7a2 2 0 01-2-2v-5a2 2 0 012-2zm-3 4H3m18 0h-1m-11 1v1m6-1v1" stroke="#1677FF" stroke-width="1.8" stroke-linecap="round"/><circle cx="9.5" cy="13.5" r="1" fill="#1677FF"/><circle cx="14.5" cy="13.5" r="1" fill="#1677FF"/></svg>';
            } else if (n.indexOf('emby') !== -1) {
                return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F6FFED"/><path d="M8 6.5l10 5.5-10 5.5V6.5z" fill="#52C41A"/></svg>';
            } else if (n.indexOf('jellyfin') !== -1) {
                return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F9F0FF"/><path d="M12 4l7 5-2.5 9.5h-9L5 9l7-5z" fill="#722ED1"/><path d="M12 8l3.5 2.5-1.5 5h-4L8.5 10.5 12 8z" fill="#D3ADF7"/></svg>';
            } else if (n.indexOf('plex') !== -1) {
                return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#1F1F1F"/><path d="M9 7l5 5-5 5h3l5-5-5-5H9z" fill="#FA8C16"/></svg>';
            } else if (n.indexOf('audio') !== -1 || n.indexOf('book') !== -1 || n.indexOf('书') !== -1) {
                return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#FFF2E8"/><path d="M5 6.5A2.5 2.5 0 017.5 4H19v14H7.5A2.5 2.5 0 005 20.5V6.5z" stroke="#FA541C" stroke-width="1.8" stroke-linecap="round"/><path d="M5 18a2.5 2.5 0 012.5-2.5H19" stroke="#FA541C" stroke-width="1.8"/></svg>';
            } else if (n.indexOf('navi') !== -1 || n.indexOf('music') !== -1 || n.indexOf('音') !== -1) {
                return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#E6F7FF"/><path d="M9 17a2 2 0 100-4 2 2 0 000 4zm0 0V7l8-2v8m-8-4l8-2" stroke="#1890FF" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="17" cy="13" r="2" fill="#1890FF"/></svg>';
            } else if (n.indexOf('iptv') !== -1 || n.indexOf('tv') !== -1 || n.indexOf('视') !== -1) {
                return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#FFF0F6"/><rect x="4" y="7" width="16" height="12" rx="2" stroke="#EB2F96" stroke-width="1.8"/><path d="M9 4l3 3 3-3m-7 8h6" stroke="#EB2F96" stroke-width="1.8" stroke-linecap="round"/></svg>';
            } else if (n.indexOf('工') !== -1 || n.indexOf('tool') !== -1) {
                return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F5F5F5"/><path d="M12 4l7 4-7 4-7-4 7-4zm-7 5.5l7 4v6.5l-7-4V9.5zm14 0l-7 4v6.5l7-4V9.5z" fill="#595959"/></svg>';
            } else if (n.indexOf('代理') !== -1 || n.indexOf('proxy') !== -1 || n.indexOf('clash') !== -1) {
                return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#E6FFFB"/><circle cx="12" cy="12" r="7" stroke="#13C2C2" stroke-width="1.8"/><path d="M5 12h14M12 5a10 10 0 010 14M12 5a10 10 0 000 14" stroke="#13C2C2" stroke-width="1.5"/></svg>';
            }
            return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none"><rect width="24" height="24" rx="6" fill="#F0F5FF"/><path d="M6 6h6l7 7-6 6-7-7V6z" stroke="#2F54EB" stroke-width="1.8" stroke-linejoin="round"/><circle cx="9.5" cy="9.5" r="1.5" fill="#2F54EB"/></svg>';
        }

        function buildItem(name, isPlatform) {
            var iconHtml = '<span class="ae-item-icon">' + getItemIconSvg(name, isPlatform) + '</span>';
            return $('<li class="ae-cat-item"></li>')
                .attr('data-origin', name)
                .append('<span class="ae-cat-drag" title="' + escAttr(l10n.dragToSort || '拖拽排序') + '"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg></span>')
                .append(iconHtml)
                .append($('<span class="ae-cat-name"></span>').text(name))
                .append('<span class="ae-cat-actions">' +
                    '<button type="button" class="ae-cat-action-btn ae-cat-edit" title="' + escAttr(l10n.editBtn || '修改') + '"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>' +
                    '<button type="button" class="ae-cat-action-btn ae-cat-remove" title="' + escAttr(l10n.deleteBtn || '删除') + '"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>' +
                    '</span>');
        }

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

            var isPlatform = $editor.find('input[name="platform_categories"]').length > 0;
            $editor.find('.ae-cat-list').append(buildItem(name, isPlatform));
            sync($editor);
            return 'added';
        }

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

        $editors.on('click', '.ae-cat-add-btn', function() {
            var $editor = $(this).closest('.ae-cat-editor');
            var $input = $editor.find('.ae-cat-add-input');
            if (addItem($editor, $input.val()) !== 'invalid') $input.val('');
            $input.focus();
        });

        $editors.on('keydown', '.ae-cat-add-input', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var $editor = $(this).closest('.ae-cat-editor');
            if (addItem($editor, $(this).val()) !== 'invalid') $(this).val('');
        });

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

        // 收集改名映射提交
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
