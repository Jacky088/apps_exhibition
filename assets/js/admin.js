jQuery(document).ready(function($) {
    // --------- 应用图标 上传/移除功能 ---------
    (function() {
        const UPLOAD_ICON_BTN = '#upload_icon_button';
        const REMOVE_ICON_BTN = '#remove_icon_button';

        if (!$(UPLOAD_ICON_BTN).length) return;

        let mediaUploader;

        // 防重复绑定
        if (!$(UPLOAD_ICON_BTN).data('bound')) {
            $(UPLOAD_ICON_BTN).data('bound', true).on('click', function(e) {
                e.preventDefault();

                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                mediaUploader = wp.media({
                    title: '选择应用图标',
                    button: { text: '使用这个图标' },
                    multiple: false
                });

                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#app_icon').val(attachment.url);
                    $('#app_icon_preview').css('background-image', 'url(' + attachment.url + ')');
                });

                mediaUploader.open();
            });
        }

        if (!$(REMOVE_ICON_BTN).data('bound')) {
            $(REMOVE_ICON_BTN).data('bound', true).on('click', function(e) {
                e.preventDefault();
                $('#app_icon').val('');
                $('#app_icon_preview').css('background-image', 'none');
            });
        }
    })();

    // --------- 下载链接 添加/删除功能（限制最大3条） ---------
    (function() {
        const ADD_DOWNLOAD_BTN = '#add_download_button';
        const DOWNLOADS_CONTAINER = '#downloads_container';
        const DOWNLOAD_ITEM_CLASS = '.download-item';
        const REMOVE_DOWNLOAD_BTN_CLASS = '.remove-download-button';
        const MAX_DOWNLOAD_LINKS = 3;

        if (!$(ADD_DOWNLOAD_BTN).length || !$(DOWNLOADS_CONTAINER).length) return;

        // 初始化当前下载链接数量
        let currentCount = parseInt($(DOWNLOADS_CONTAINER).attr('data-download-count'), 10);
        if (isNaN(currentCount) || currentCount < 1) {
            currentCount = 1;
            $(DOWNLOADS_CONTAINER).attr('data-download-count', currentCount);
        }

        // 根据当前数量更新按钮状态
        function updateAddButtonState() {
            if (currentCount >= MAX_DOWNLOAD_LINKS) {
                $(ADD_DOWNLOAD_BTN).prop('disabled', true).addClass('disabled').attr('aria-disabled', 'true');
            } else {
                $(ADD_DOWNLOAD_BTN).prop('disabled', false).removeClass('disabled').removeAttr('aria-disabled');
            }
        }

        updateAddButtonState();

        if (!$(ADD_DOWNLOAD_BTN).data('bound')) {
            $(ADD_DOWNLOAD_BTN).data('bound', true).on('click', function() {
                if (currentCount >= MAX_DOWNLOAD_LINKS) {
                    return; // 防止多点击
                }
                const item = $(
                    '<div class="download-item" style="margin-bottom:8px; display:flex; gap:8px; align-items:center;">' +
                    '<input type="url" name="download_url[]" placeholder="下载链接 URL" required style="width:60%;" />' +
                    '<input type="text" name="download_text[]" placeholder="按钮文字" value="下载" required style="width:30%;" />' +
                    '<button type="button" class="button remove-download-button" style="width:8%;">删除</button>' +
                    '</div>'
                );
                $(DOWNLOADS_CONTAINER).append(item);
                currentCount++;
                $(DOWNLOADS_CONTAINER).attr('data-download-count', currentCount);
                updateAddButtonState();
            });
        }

        // 删除下载链接按钮事件代理
        if (!$(DOWNLOADS_CONTAINER).data('boundRemove')) {
            $(DOWNLOADS_CONTAINER).data('boundRemove', true).on('click', REMOVE_DOWNLOAD_BTN_CLASS, function() {
                const downloadItems = $(DOWNLOADS_CONTAINER).find(DOWNLOAD_ITEM_CLASS);

                if (downloadItems.length > 1) {
                    $(this).closest(DOWNLOAD_ITEM_CLASS).remove();
                    currentCount--;
                    if (currentCount < 1) currentCount = 1; // 保持至少1条
                    $(DOWNLOADS_CONTAINER).attr('data-download-count', currentCount);
                    updateAddButtonState();
                } else {
                    // 保留最后一个，清空内容
                    const $item = $(this).closest(DOWNLOAD_ITEM_CLASS);
                    $item.find('input[name="download_url[]"]').val('');
                    $item.find('input[name="download_text[]"]').val('下载');
                }
            });
        }
    })();

    // --------- 应用表单提交校验 ---------
    (function() {
        const FORM_ID = '#apps-exhibition-form';
        if (!$(FORM_ID).length) return;

        if (!$(FORM_ID).data('bound')) {
            $(FORM_ID).data('bound', true).on('submit', function() {
                if (!$('#app_name').val().trim() || !$('#app_description').val().trim() || !$('#app_icon').val().trim()) {
                    alert('请填写所有必填项 (应用名称、描述、图标)。');
                    return false;
                }
                if ($('input[name="app_platforms[]"]:checked').length === 0) {
                    alert('请至少选择一个平台分类。');
                    return false;
                }
                if ($('input[name="app_filter_category[]"]:checked').length === 0) {
                    alert('请至少选择一个筛选分类。');
                    return false;
                }
                let hasValidDownload = false;
                let valid = true;
                $('#downloads_container .download-item').each(function() {
                    const url = $(this).find('input[name="download_url[]"]').val().trim();
                    const text = $(this).find('input[name="download_text[]"]').val().trim();

                    if (url && text) {
                        hasValidDownload = true;
                    } else if (url || text) {
                        alert('请确保所有下载链接和按钮文字均已填写，或者留空以便删除。');
                        valid = false;
                        return false; // 跳出each
                    }
                });
                if (!hasValidDownload) {
                    alert('请至少填写一个完整下载链接（URL和按钮文字）。');
                    return false;
                }
                return valid;
            });
        }
    })();

    // --------- 首页海报管理相关功能 ---------
    (function($){
        const HOME_POSTERS_INPUT = '#home_posters';

        if (!$(HOME_POSTERS_INPUT).length) return;

        // 获取并解析海报数组
        function getPostersArray() {
            let arr = [];
            try {
                const val = $(HOME_POSTERS_INPUT).val();
                arr = JSON.parse(val);
                if (!Array.isArray(arr)) arr = [];
            } catch(e) {
                arr = [];
            }
            return arr;
        }

        // 渲染海报预览和配置界面
        function renderHomePosters(posters) {
            let preview_html = '';
            for (let i = 0; i < posters.length; i++) {
                preview_html += '<div class="poster-item" style="position: relative; display: inline-block; margin-right: 10px;">' +
                    '<img src="'+posters[i].url+'" style="max-width:150px; max-height:150px; border:1px solid #ccc; border-radius:8px;" />' +
                    '<div style="margin-top:4px; text-align:center;">' +
                    '<button type="button" class="button change-poster">更换图片</button> ' +
                    '<button type="button" class="button remove-poster">删除海报</button>' +
                    '</div></div>';
            }
            $('#poster_preview_container').html(preview_html);

            let config_html = '';
            for (let i = 0; i < posters.length; i++) {
                config_html += '<div class="poster-config-item" style="border:1px solid #ccc; border-radius:6px; padding:10px; margin-bottom:10px; background:#f9f9f9; display: flex; align-items: flex-start; gap: 15px;">' +
                    '<div style="flex: 0 0 auto;">' +
                    '<img src="'+posters[i].url+'" style="max-width:200px; max-height:150px; border-radius: 6px;">' +
                    '</div>' +
                    '<div style="flex: 1 1 auto; display: flex; flex-direction: column; gap: 10px;">' +
                    '<div><button type="button" class="button remove-poster-conf">删除</button></div>' +
                    '<div><input type="text" class="widefat download-url-input" placeholder="下载地址" value="'+(posters[i].download_url||'')+'"></div>' +
                    '<div><input type="text" class="widefat download-text-input" placeholder="按钮文字" value="'+(posters[i].download_text||'')+'"></div>' +
                    '</div>' +
                    '</div>';
            }
            $('#poster_config_list').html(config_html);
        }

        let uploadHomePosterFrame = null;
        let changePosterFrame = null;
        let changePosterIndex = -1;

        // 上传海报按钮绑定
        const UPLOAD_POSTER_BTN = '#upload_home_poster';
        const MAX_POSTERS = 10; // 最大海报数量限制

        if (!$(UPLOAD_POSTER_BTN).data('bound')) {
            $(UPLOAD_POSTER_BTN).data('bound', true).on('click', function(e) {
                e.preventDefault();

                const currentPosters = getPostersArray();
                if (currentPosters.length >= MAX_POSTERS) {
                    alert('最多只能上传 ' + MAX_POSTERS + ' 张海报');
                    return;
                }

                if (uploadHomePosterFrame) {
                    uploadHomePosterFrame.open();
                    return;
                }

                uploadHomePosterFrame = wp.media({
                    title: '选择海报图片',
                    button: { text: '插入' },
                    multiple: true
                });

                uploadHomePosterFrame.on('select', function() {
                    const attachments = uploadHomePosterFrame.state().get('selection').toArray();
                    const currentPosters = getPostersArray();

                    // 检查添加后是否超过限制
                    if (currentPosters.length + attachments.length > MAX_POSTERS) {
                        alert('添加这些图片将超过 ' + MAX_POSTERS + ' 张的限制');
                        return;
                    }

                    attachments.forEach(function(att) {
                        currentPosters.push({
                            url: att.attributes.url,
                            download_url: '',
                            download_text: ''
                        });
                    });

                    $(HOME_POSTERS_INPUT).val(JSON.stringify(currentPosters));
                    renderHomePosters(currentPosters);
                });

                uploadHomePosterFrame.open();
            });
        }

        // 更换海报按钮绑定（事件代理）
        const POSTER_PREVIEW_CONTAINER = '#poster_preview_container';
        if (!$(POSTER_PREVIEW_CONTAINER).data('boundChange')) {
            $(POSTER_PREVIEW_CONTAINER).data('boundChange', true).on('click', '.change-poster', function(e) {
                e.preventDefault();

                changePosterIndex = $(this).closest('.poster-item').index();

                if (changePosterFrame) {
                    changePosterFrame.open();
                    return;
                }

                changePosterFrame = wp.media({
                    title: '选择海报图片',
                    button: { text: '插入' },
                    multiple: false
                });

                changePosterFrame.on('select', function() {
                    const attachment = changePosterFrame.state().get('selection').first().toJSON();
                    const currentPosters = getPostersArray();

                    if (currentPosters && currentPosters[changePosterIndex]) {
                        currentPosters[changePosterIndex].url = attachment.url;
                        $(HOME_POSTERS_INPUT).val(JSON.stringify(currentPosters));
                        renderHomePosters(currentPosters);
                    }
                });

                changePosterFrame.open();
            });
        }

        // 删除海报按钮（预览区域）（事件代理）
        if (!$(POSTER_PREVIEW_CONTAINER).data('boundRemove')) {
            $(POSTER_PREVIEW_CONTAINER).data('boundRemove', true).on('click', '.remove-poster', function() {
                const index = $(this).closest('.poster-item').index();
                const currentPosters = getPostersArray();
                if (index >= 0 && index < currentPosters.length) {
                    currentPosters.splice(index, 1);
                    $(HOME_POSTERS_INPUT).val(JSON.stringify(currentPosters));
                    renderHomePosters(currentPosters);
                }
            });
        }

        // 删除配置区按钮绑定（事件代理）
        const POSTER_CONFIG_LIST = '#poster_config_list';
        if (!$(POSTER_CONFIG_LIST).data('boundRemoveConf')) {
            $(POSTER_CONFIG_LIST).data('boundRemoveConf', true).on('click', '.remove-poster-conf', function() {
                const index = $(this).closest('.poster-config-item').index();
                const currentPosters = getPostersArray();
                if (index >= 0 && index < currentPosters.length) {
                    currentPosters.splice(index, 1);
                    $(HOME_POSTERS_INPUT).val(JSON.stringify(currentPosters));
                    renderHomePosters(currentPosters);
                }
            });
        }

        // 监听配置区下载链接和按钮文字输入变化，实时更新隐藏input（事件代理）
        if (!$(POSTER_CONFIG_LIST).data('boundInputChange')) {
            $(POSTER_CONFIG_LIST).data('boundInputChange', true).on('input', '.download-url-input, .download-text-input', function() {
                const currentPosters = getPostersArray();

                $(POSTER_CONFIG_LIST + ' .poster-config-item').each(function(i) {
                    const $item = $(this);
                    const url = $item.find('img').attr('src') || '';
                    const download_url = $item.find('.download-url-input').val() || '';
                    const download_text = $item.find('.download-text-input').val() || '';

                    if (currentPosters[i]) {
                        currentPosters[i].url = url;
                        currentPosters[i].download_url = download_url.trim();
                        currentPosters[i].download_text = download_text.trim();
                    }
                });

                $(HOME_POSTERS_INPUT).val(JSON.stringify(currentPosters));
            });
        }

        // 页面初始化时渲染
        renderHomePosters(getPostersArray());

    })(jQuery);
});
