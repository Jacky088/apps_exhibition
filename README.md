# Apps Exhibition (应用页面插件)

一个用来快速搭建「APP 商店」风格展示页的 WordPress 插件。

通过一个简单的短代码，你就可以在任意页面输出整洁的 APP 列表，适合个人 / 团队 / 公司以网页形式展示自家应用。

---

## 功能特性

- 在一个页面集中展示多个 APP 信息
- 支持显示应用名称、简介、图标、平台标签、下载按钮
- 支持多下载链接（最多 3 个按钮）
- 前端分类筛选，支持一个应用归属多个分类
- 后台按分类拖拽排序，精确控制每个分类下的前端展示顺序
- 首页海报轮播（Swiper），支持自定义下载链接
- 模态框式后台管理，添加/编辑应用无需刷新页面
- 搜索过滤 + 批量删除
- 多端自适应（桌面/平板/手机）
- PC端悬停卡片显示下载按钮（背景模糊遮罩）
- 移动端触摸点击显示/隐藏下载按钮
- 国际化支持（i18n ready）
- Transient 缓存加速前端查询
- 使用短代码一键插入到任意页面或文章

---

## 环境要求

- WordPress 5.6+
- PHP 7.4+
- MySQL 5.6+ / MariaDB 10.0+

---

## 安装

### 方法一：通过 WordPress 后台上传（推荐）

1. 从 [GitHub Releases](https://github.com/Jacky088/apps_exhibition/releases) 下载最新 ZIP 压缩包
2. 登录 WordPress 后台
3. 前往 **插件 → 安装插件 → 上传插件**
4. 上传 ZIP 文件并安装
5. 点击 **启用插件**

### 方法二：手动上传到服务器

将 `apps_exhibition` 文件夹上传至：

```
wp-content/plugins/apps_exhibition/
```

然后在 WordPress 后台 **插件** 页面找到「应用页面插件」并启用。

---

## 使用方法

### 基本用法

在任意页面或文章中插入短代码：

```
[apps_exhibition]
```

发布后即可看到 APP 展示页面。

### 后台管理

启用插件后，左侧菜单会出现「应用展示」入口，包含三个 Tab：

| Tab | 功能 |
|-----|------|
| 应用管理 | 添加/编辑/删除应用，按分类拖拽排序 |
| 分类设置 | 管理筛选分类和应用平台标签 |
| 首页海报 | 管理轮播海报及其下载链接 |

### 排序说明

1. 在「应用管理」Tab 的工具栏选择一个分类
2. 仅该分类下的应用会显示
3. 拖拽行即可调整该分类在前端的展示顺序
4. 松手后自动保存

---

## 界面预览

![Apps Exhibition 预览](./screenshot-preview-pc-app.png)

---

## 项目结构

```text
apps_exhibition/
├── apps-exhibition.php         # 插件入口，单例类，注册钩子、AJAX、脚本
├── includes/
│   ├── admin.php               # 后台表单处理（CRUD、批量删除）
│   └── shortcode.php           # 前端短代码渲染逻辑
├── templates/
│   └── admin-page.php          # 后台管理页面模板
├── assets/
│   ├── css/
│   │   ├── admin.css           # 后台样式（模态框、拖拽、卡片等）
│   │   └── apps-exhibition.css # 前端展示样式
│   └── js/
│       ├── admin.js            # 后台交互（模态框、排序、海报管理）
│       └── apps-exhibition.js  # 前端交互（分类筛选、Swiper、排序渲染）
├── README.md
└── screenshot-preview-pc-app.png
```

---

## 技术要点

- **单例模式** — 插件主类使用单例，避免重复实例化
- **Transient 缓存** — 前端查询结果缓存 1 小时，数据变更时自动清除
- **按分类排序** — 排序数据存储在 `wp_options`，结构为 `{分类名: [ID数组]}`
- **按需加载** — 前端 CSS/JS 仅在短代码被调用的页面加载
- **安全** — nonce 验证、权限检查、数据清洗（`esc_url_raw`、`sanitize_text_field`）
- **无 `!important`** — 前端 CSS 使用 `#apps-exhibition-root` 高优先级选择器

---

## 更新日志

### v2.0.5
- PC端下载按钮恢复为 hover 背景模糊遮罩显示
- 移动端改用触摸点击显示/收起下载按钮
- 平台标签全部显示，不再折叠
- 首页海报管理增加建议尺寸提示（1920×720px，16:6）
- 前端海报自动居中裁切适配（object-fit + object-position）
- 轮播图增加左右导航箭头（桌面端 hover 渐显）

### v2.0.4
- 分类筛选增加「全部」选项
- 分类切换 URL 联动（支持浏览器前进/后退）
- DOM 操作优化（DocumentFragment 批量插入）
- 移除平台标签无效的 backdrop-filter，降低 GPU 开销
- 增加 aspect-ratio fallback 兼容旧浏览器
- 轮播图增加 Swiper 导航箭头

### v2.0.3
- 下载链接按钮文字输入框改为空占位符样式
- 修复工具栏按钮对齐问题
- 分类设置 Tab 重新设计（卡片式布局）
- 拖拽排序改为按分类排序（选择分类后才能排序）
- 前端按分类自定义顺序渲染应用
- 后台改为模态框式表单
- 新增拖拽排序功能
- 新增搜索过滤
- 新增批量删除
- 内联删除确认替代浏览器 confirm
- 合并「筛选分类」和「应用平台」为一个 Tab

---

## 许可证

MIT License

---

如果这个插件对你有帮助，欢迎在 GitHub 上点一个 Star 支持作者！
