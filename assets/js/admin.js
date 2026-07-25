jQuery(document).ready(function($) {
    'use strict';

    var l10n = window.appsExhibitionL10n || {};

    // =============================================
    // 模态框
    // =============================================
    var $overlay = $('#ae-modal-overlay');
    var $form = $('#apps-exhibition-form');
    var $title = $('#ae-modal-title');

    function openModal(mode, data) {
        $form[0].reset();
        $('#ae-form-app-id').val(0);
        $('#app_icon').val('');
        $('#app_icon_preview').css('background-image', 'none');
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
    $(document).on('keydown', function(e) { if (e.key === 'Escape' && $overlay.is(':visible')) closeModal(); });

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
    // 内联删除确认
    // =============================================
    $(document).on('click', '.ae-delete-btn', function(e) {
        e.preventDefault();
        var $wrap = $(this).closest('.ae-delete-wrap');
        var href = $(this).attr('href');
        $wrap.html(
            '<span class="ae-delete-confirm">' +
            '<a href="' + href + '">' + (l10n.confirmDelete || '确认删除？') + '</a> ' +
            '<span class="ae-cancel-delete">' + (l10n.cancel || '取消') + '</span>' +
            '</span>'
        );
    });

    $(document).on('click', '.ae-cancel-delete', function() {
        var $wrap = $(this).closest('.ae-delete-wrap');
        var href = $wrap.find('.ae-delete-confirm a').attr('href');
        $wrap.html('<a href="' + href + '" class="button button-small ae-delete-btn" title="' + (l10n.deleteBtn || '删除') + '"><span class="dashicons dashicons-trash"></span></a>');
    });

    // =============================================
    // 图标上传
    // =============================================
    var iconUploader;
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
            $('#app_icon').val(att.url);
            $('#app_icon_preview').css('background-image', 'url(' + att.url + ')');
        });
        iconUploader.open();
    });

    $('#remove_icon_button').on('click', function(e) {
        e.preventDefault();
        $('#app_icon').val('');
        $('#app_icon_preview').css('background-image', 'none');
    });

    // =============================================
    // 下载链接
    // =============================================
    var MAX_DOWNLOADS = 3;

    function getDownloadItemHtml(url, text) {
        return '<div class="download-item">' +
            '<input type="url" name="download_url[]" placeholder="' + (l10n.downloadUrlPlc || '下载链接 URL') + '" value="' + (url || '') + '" style="width:55%;" required />' +
            '<input type="text" name="download_text[]" placeholder="' + (l10n.downloadTextPlc || '按钮文字（如：下载、安卓商店）') + '" value="' + (text || '') + '" style="width:30%;" required />' +
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
        if ($('#downloads_container .download-item').length > 1) {
            $(this).closest('.download-item').remove();
        } else {
            $(this).closest('.download-item').find('input[name="download_url[]"]').val('');
            $(this).closest('.download-item').find('input[name="download_text[]"]').val('');
        }
        updateAddDownloadBtn();
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
        $('#ae-bulk-delete-btn').prop('disabled', $('.ae-row-check:checked').length === 0);
    }

    $('#ae-check-all').on('change', function() {
        $('.ae-row-check').prop('checked', $(this).prop('checked'));
        updateBulkBtn();
    });

    $(document).on('change', '.ae-row-check', function() {
        updateBulkBtn();
        var total = $('.ae-row-check').length;
        var checked = $('.ae-row-check:checked').length;
        $('#ae-check-all').prop('checked', total === checked && total > 0);
    });

    $('#ae-bulk-delete-btn').on('click', function() {
        if ($('.ae-row-check:checked').length === 0) { alert(l10n.noBulkSelected); return; }
        if (confirm(l10n.confirmBulkDelete)) { $('#ae-bulk-form').submit(); }
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

        function getPostersArray() {
            try { var arr = JSON.parse($(HOME_POSTERS_INPUT).val()); return Array.isArray(arr) ? arr : []; }
            catch(e) { return []; }
        }

        function renderHomePosters(posters) {
            var preview = '', config = '';
            for (var i = 0; i < posters.length; i++) {
                preview += '<div class="poster-item" style="position:relative; display:inline-block; margin-right:10px;">' +
                    '<img src="' + posters[i].url + '" style="max-width:150px; max-height:150px; border:1px solid #ccc; border-radius:8px;" />' +
                    '<div style="margin-top:4px; text-align:center;">' +
                    '<button type="button" class="button change-poster">' + (l10n.changePosterBtn || '更换图片') + '</button> ' +
                    '<button type="button" class="button remove-poster">' + (l10n.removePosterBtn || '删除海报') + '</button>' +
                    '</div></div>';

                config += '<div class="poster-config-item" style="border:1px solid #ccc; border-radius:6px; padding:10px; margin-bottom:10px; background:#f9f9f9; display:flex; align-items:flex-start; gap:15px;">' +
                    '<div style="flex:0 0 auto;"><img src="' + posters[i].url + '" style="max-width:200px; max-height:150px; border-radius:6px;"></div>' +
                    '<div style="flex:1 1 auto; display:flex; flex-direction:column; gap:10px;">' +
                    '<div><button type="button" class="button remove-poster-conf">' + (l10n.deleteBtn || '删除') + '</button></div>' +
                    '<div><input type="text" class="widefat download-url-input" placeholder="' + (l10n.downloadAddrPlc || '下载地址') + '" value="' + (posters[i].download_url || '') + '"></div>' +
                    '<div><input type="text" class="widefat download-text-input" placeholder="' + (l10n.downloadTextPlc || '按钮文字') + '" value="' + (posters[i].download_text || '') + '"></div>' +
                    '</div></div>';
            }
            $('#poster_preview_container').html(preview);
            $('#poster_config_list').html(config);
        }

        var uploadFrame = null, changeFrame = null, changeIdx = -1, MAX_POSTERS = 10;

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

        $('#poster_preview_container').on('click', '.change-poster', function(e) {
            e.preventDefault();
            changeIdx = $(this).closest('.poster-item').index();
            if (changeFrame) { changeFrame.open(); return; }
            changeFrame = wp.media({ title: l10n.selectPosterTitle, button: { text: l10n.insertBtn }, multiple: false });
            changeFrame.on('select', function() {
                var att = changeFrame.state().get('selection').first().toJSON();
                var cur = getPostersArray();
                if (cur[changeIdx]) { cur[changeIdx].url = att.url; }
                $(HOME_POSTERS_INPUT).val(JSON.stringify(cur));
                renderHomePosters(cur);
            });
            changeFrame.open();
        });

        $('#poster_preview_container').on('click', '.remove-poster', function() {
            var idx = $(this).closest('.poster-item').index();
            var cur = getPostersArray();
            if (idx >= 0) cur.splice(idx, 1);
            $(HOME_POSTERS_INPUT).val(JSON.stringify(cur));
            renderHomePosters(cur);
        });

        $('#poster_config_list').on('click', '.remove-poster-conf', function() {
            var idx = $(this).closest('.poster-config-item').index();
            var cur = getPostersArray();
            if (idx >= 0) cur.splice(idx, 1);
            $(HOME_POSTERS_INPUT).val(JSON.stringify(cur));
            renderHomePosters(cur);
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

        renderHomePosters(getPostersArray());
    })();
});
