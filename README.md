# Typecho GitHub Uploader & Gallery Solution

这是一套为 Typecho 定制的轻量级**图床管理与壁纸展示方案**。它由一个**后端插件**和一个**前端模板**组成，实现了从图片上传、CDN 加速到自动归档展示的全流程自动化。

## ✨ 主要功能

* **☁️ GitHub 图床集成**：自动将图片上传至 GitHub 公开仓库。
* **⚡ CDN 加速**：自动生成 `jsDelivr` 全球加速链接。
* **🤖 自动归档**：上传时选择分类，系统会自动将图片链接写入“壁纸展示页”的数据库中，无需手动编辑文章。
* **🛡️ 安全鉴权**：基于 Typecho 原生插件机制，仅管理员可上传，Token 不暴露在前端，无 CORS 跨域风险。
* **🎨 瀑布流展示**：前端页面采用 Masonry 布局 + 懒加载 + Fancybox 灯箱，极致流畅。
* **🔧 零依赖**：不需要修改 Typecho 核心代码，不依赖伪静态配置。

## 📂 目录结构

请确保您的文件目录结构如下：

```text
Typecho根目录/
├── usr/
│   ├── plugins/
│   │   └── GithubUploader/       <-- 后端插件目录
│   │       ├── Plugin.php        # 插件入口与配置
│   │       └── Action.php        # 核心上传与数据库逻辑
│   │
│   └── themes/
│       └── 您的主题名/
│           ├── page_gallery.php  # 前端：壁纸展示页模板
│           └── page_upload.php   # 前端：上传工具页模板
