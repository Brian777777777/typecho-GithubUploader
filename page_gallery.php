<?php
/**
 * Gallery 
 * @package custom
 */
?>
<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('component/header.php'); ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0/dist/fancybox.css" />
<script src="https://cdn.jsdelivr.net/npm/lozad/dist/lozad.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0/dist/fancybox.umd.js"></script>

<style>
    /* 基础 */
    .raw-content-hidden { display: none; }
    .main-inner{ width: 100%; margin: unset; }
    .content-wrap { width: 100%; }
    #main { min-height: 80vh; }

    .wallpaper-container {
        display: flex; flex-wrap: wrap; justify-content: center; 
        gap: 15px; padding: 20px 10px; min-height: 400px;
    }

    /* 图片卡片 */
    .wallpaper-item {
        position: relative; font-size: 0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 6px;
        overflow: hidden; background: #f5f5f5; cursor: pointer;
        display: block; contain: strict; content-visibility: auto; 
        contain-intrinsic-size: 300px 200px; width: 300px; height: 200px;
    }

    .wallpaper-img {
        display: block; width: 100%; height: 100%; object-fit: cover;
        transition: opacity 0.3s ease, transform 0.5s ease;
        opacity: 0; will-change: opacity;
    }
    .wallpaper-img.is-loaded { opacity: 1; }
    .wallpaper-item:hover .wallpaper-img { transform: scale(1.05); }

    /* --- [新增] 底部悬停蒙层 --- */
    .admin-overlay {
        position: absolute; bottom: 0; left: 0; width: 100%; height: 40px;
        background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
        display: flex; justify-content: center; align-items: center;
        opacity: 0; transform: translateY(10px);
        transition: all 0.3s ease;
        z-index: 20; /* 在 click-mask 之上 */
    }
    .wallpaper-item:hover .admin-overlay { opacity: 1; transform: translateY(0); }

    /* 垃圾桶按钮 */
    .delete-btn {
        background: transparent; border: none; cursor: pointer;
        color: #fff; padding: 5px; 
        transition: transform 0.2s;
        filter: drop-shadow(0 1px 2px rgba(0,0,0,0.5));
    }
    .delete-btn:hover { color: #ff4757; transform: scale(1.2); }

    /* 移动端隐藏删除功能 */
    @media (max-width: 768px) {
        .wallpaper-item, .wallpaper-img { width: 100%; height: auto; aspect-ratio: 300/200; }
        .admin-overlay { display: none !important; }
    }

    /* 其他 */
    .click-mask { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; background: transparent; }
    .gallery-filter { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; padding: 10px 0; border-bottom: 1px solid #eee; margin-bottom: 20px; }
    .filter-btn { padding: 6px 16px; border-radius: 20px; border: 1px solid #ddd; background: #fff; color: #666; cursor: pointer; font-size: 14px; transition: all 0.3s; }
    .filter-btn:hover { background: #f0f0f0; color: #333; }
    .filter-btn.active { background: #333; color: #fff; border-color: #333; }
    #loading-sentinel { width: 100%; height: 50px; text-align: center; line-height: 50px; color: #999; font-size: 14px; contain: strict; contain-intrinsic-size: 100% 50px; }
    .admin-panel { text-align: center; padding-top: 20px; margin-bottom: 10px; }
    .upload-btn-link { display: inline-flex; align-items: center; padding: 8px 24px; background-color: #333; color: #fff; border-radius: 30px; text-decoration: none; font-size: 14px; font-weight: bold; box-shadow: 0 4px 12px rgba(0,0,0,0.2); transition: transform 0.2s; }
    .upload-btn-link:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.3); }
</style>

<div id="main" class="main" role="main">
    <div class="main-inner clearfix">
        <div class="content-wrap">
            
            <?php 
            $rawContent = $this->text; 
            $rawContent = preg_replace('/^\xEF\xBB\xBF/', '', $rawContent);
            $lines = preg_split('/(\r\n|\n|\r)/', $rawContent);
            $data = ['全部' => []]; 
            $currentCat = '全部';
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                if (preg_match('/^\s*#\s*(.+?)\s*$/u', $line, $matches)) {
                    $currentCat = trim($matches[1]);
                    if (!isset($data[$currentCat])) $data[$currentCat] = [];
                    continue;
                }
                if (strpos($line, 'http') === 0) {
                    $url = trim($line);
                    $data[$currentCat][] = $url;
                    if ($currentCat !== '全部') $data['全部'][] = $url;
                }
            }
            foreach ($data as $k => $v) $data[$k] = array_values(array_unique($v));
            $allCategories = array_keys($data);
            $defaultCat = (count($allCategories) > 1) ? $allCategories[1] : '全部';
            $jsonData = json_encode($data);
            ?>

            <?php if($this->user->hasLogin() && $this->user->pass('administrator', true)): ?>
                <div class="admin-panel">
                    <a href="<?php $this->options->siteUrl('index.php/github-upload.html'); ?>" target="_blank" class="upload-btn-link">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        上传图片 (Admin)
                    </a>
                </div>
            <?php endif; ?>

            <div class="gallery-filter">
                <?php foreach ($data as $catName => $imgs): ?>
                    <?php if (count($imgs) > 0): ?>
                        <button class="filter-btn <?php echo ($catName === $defaultCat) ? 'active' : ''; ?>" 
                                onclick="switchCategory('<?php echo $catName; ?>', this)">
                            <?php echo $catName; ?> <span>(<?php echo count($imgs); ?>)</span>
                        </button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="wallpaper-container" id="gallery-container"></div>
            <div id="loading-sentinel">正在加载更多...</div>

        </div>
    </div>
</div>

<script>
    const allData = <?php echo $jsonData; ?>;
    const defaultCategory = "<?php echo $defaultCat; ?>";
    // [核心] 插件路由地址
    const ACTION_URL = "<?php $this->options->index('/action/github-upload'); ?>";
    const IS_ADMIN = <?php echo ($this->user->hasLogin() && $this->user->pass('administrator', true)) ? 'true' : 'false'; ?>;

    const container = document.getElementById('gallery-container');
    const sentinel = document.getElementById('loading-sentinel');
    let currentCategory = defaultCategory;
    let currentImages = allData[defaultCategory];
    let currentIndex = 0;
    const batchSize = 24;

    const lozadObserver = lozad('.lozad', {
        rootMargin: '200px 0px', 
        loaded: function(el) { el.classList.add('is-loaded'); }
    });

    window.switchCategory = function(catName, btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentCategory = catName;
        currentImages = allData[catName] || [];
        currentIndex = 0;
        container.innerHTML = '';
        sentinel.innerText = "正在加载更多...";
        setTimeout(renderBatch, 10);
    }

    function renderBatch() {
        if (!currentImages || currentImages.length === 0) {
            sentinel.innerText = "暂无图片"; return;
        }
        if (currentIndex >= currentImages.length) {
            sentinel.innerText = "—— 到底了 ——"; return;
        }

        const fragment = document.createDocumentFragment();
        const batch = currentImages.slice(currentIndex, currentIndex + batchSize);
        const newImages = [];

        batch.forEach(url => {
            const div = document.createElement('div');
            div.className = 'wallpaper-item';
            div.setAttribute('data-fancybox', currentCategory);
            div.setAttribute('data-src', url);

            const img = document.createElement('img');
            img.className = 'wallpaper-img lozad ignore-fancybox no-lightbox'; 
            img.setAttribute('data-src', url);
            img.decoding = "async"; img.width = 300; img.height = 200; img.alt = "";

            const mask = document.createElement('span');
            mask.className = 'click-mask';

            div.appendChild(img);
            div.appendChild(mask);

            // [核心] 添加底部悬停蒙层
            if (IS_ADMIN) {
                const overlay = document.createElement('div');
                overlay.className = 'admin-overlay';
                
                const delBtn = document.createElement('button');
                delBtn.className = 'delete-btn';
                delBtn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
                
                overlay.onclick = (e) => e.stopPropagation(); // 防止点蒙层空白处触发灯箱
                delBtn.onclick = (e) => { e.stopPropagation(); deleteImage(url, div); };
                
                overlay.appendChild(delBtn);
                div.appendChild(overlay);
            }

            fragment.appendChild(div);
            newImages.push(img);
        });

        container.appendChild(fragment);
        currentIndex += batchSize;
        window.requestAnimationFrame(() => newImages.forEach(img => lozadObserver.observe(img)));

        if (currentIndex >= currentImages.length) sentinel.innerText = "—— 到底了 ——";
    }

    // 删除逻辑
    function deleteImage(url, element) {
        if (!confirm("🗑️ 确定要彻底删除这张图片吗？\n(将从 GitHub 仓库和数据库中永久移除)")) return;

        const btn = element.querySelector('.delete-btn');
        btn.innerHTML = '...'; // Loading 态
        btn.disabled = true;

        const formData = new FormData();
        formData.append('url', url);

        // 请求插件 Action
        fetch(ACTION_URL + "?do=delete", {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                element.style.transition = "all 0.3s";
                element.style.transform = "scale(0)";
                setTimeout(() => element.remove(), 300);
            } else {
                alert("删除失败: " + res.error);
                btn.innerHTML = '🗑️';
                btn.disabled = false;
            }
        })
        .catch(e => {
            alert("网络错误");
            btn.disabled = false;
        });
    }

    if (currentImages.length > 0) {
        const scrollObserver = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) window.requestAnimationFrame(() => renderBatch());
        }, { rootMargin: '300px' });
        scrollObserver.observe(sentinel);
        window.requestAnimationFrame(() => renderBatch());
    } else {
        sentinel.innerHTML = "暂无图片";
    }

    Fancybox.bind("[data-fancybox]", {
        infinite: false,
        Toolbar: { display: ["zoom", "close"] },
        thumbs: false,
    });
</script>

<?php $this->need('component/footer.php'); ?>
