<?php
/**
 * Gallery Ultimate Images 
 *
 * @package custom
 */
?>
<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('component/header.php'); ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0/dist/fancybox.css" />

<style>
    /* --- 基础布局 --- */
    .raw-content-hidden { display: none; }
    .main-inner{ width: 100%; margin: unset; }
    .content-wrap { width: 100%; }

    /* 主容器最小高度，防止 Footer 上浮 */
    #main { min-height: 80vh; }

    .wallpaper-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: center; 
        gap: 15px;
        padding: 20px 10px;
        min-height: 400px;
    }

    /* --- [性能 CSS] --- */
    .wallpaper-item {
        position: relative;
        font-size: 0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        border-radius: 4px;
        overflow: hidden;
        background: #f5f5f5;
        cursor: pointer;
        display: block;
        contain: strict; 
        content-visibility: auto; 
        contain-intrinsic-size: 300px 200px; 
        width: 300px;
        height: 200px;
    }

    .wallpaper-img {
        display: block;
        width: 300px;
        height: 200px;
        object-fit: cover;
        transition: opacity 0.3s ease, transform 0.3s ease;
        opacity: 0; 
        will-change: opacity;
    }
    
    .wallpaper-img.is-loaded { opacity: 1; }
    .wallpaper-item:hover .wallpaper-img { transform: scale(1.05); }

    /* 点击遮罩 */
    .click-mask {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10;
        background: transparent;
    }

    @media (max-width: 768px) {
        .wallpaper-item {
            width: 100%;
            height: auto;
            aspect-ratio: 300 / 200;
            contain-intrinsic-size: 100% 200px; 
        }
        .wallpaper-img {
            width: 100%;
            height: auto;
            aspect-ratio: 300 / 200;
        }
    }

    /* 分类栏 */
    .gallery-filter {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
        margin-bottom: 20px;
    }

    .filter-btn {
        padding: 6px 16px;
        border-radius: 20px;
        border: 1px solid #ddd;
        background: #fff;
        color: #666;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }

    .filter-btn:hover { background: #f0f0f0; color: #333; }
    .filter-btn.active { background: #333; color: #fff; border-color: #333; }
    
    #loading-sentinel {
        width: 100%;
        height: 50px;
        text-align: center;
        line-height: 50px;
        color: #999;
        font-size: 14px;
        contain: strict;
        contain-intrinsic-size: 100% 50px;
    }

    /* --- [新增] 管理员上传按钮样式 --- */
    .admin-panel {
        text-align: center;
        padding-top: 20px;
        margin-bottom: 10px;
    }
    .upload-btn-link {
        display: inline-flex;
        align-items: center;
        padding: 8px 24px;
        background-color: #333; /* 深色醒目 */
        color: #fff;
        border-radius: 30px;
        text-decoration: none;
        font-size: 14px;
        font-weight: bold;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .upload-btn-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.3);
        color: #fff;
    }
    .upload-icon { margin-right: 6px; }
</style>

<div id="main" class="main" role="main">
    <div class="main-inner clearfix">
        <div class="content-wrap">
            
            <?php 
            // --- 数据解析逻辑 (保持不变) ---
            $rawContent = $this->text; 
            $lines = preg_split('/(\r\n|\n|\r)/', $rawContent);
            $data = ['全部' => []]; 
            $currentCat = '全部';
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                if (strpos($line, '#') === 0) {
                    $catName = trim(str_replace('#', '', $line));
                    if (!empty($catName)) {
                        $currentCat = $catName;
                        if (!isset($data[$currentCat])) $data[$currentCat] = [];
                    }
                    continue;
                }
                if (preg_match('/!\[.*?\]\((.*?)\)/', $line, $matches)) $line = $matches[1];
                $line = strip_tags($line);
                if (strpos($line, 'http') === 0) {
                    $url = trim($line);
                    $data[$currentCat][] = $url;
                    if ($currentCat !== '全部') $data['全部'][] = $url;
                }
            }
            foreach ($data as $k => $v) $data[$k] = array_values(array_unique($v));
            
            // 默认选中第一个分类逻辑
            $allCategories = array_keys($data);
            $defaultCat = (count($allCategories) > 1) ? $allCategories[1] : '全部';

            $jsonData = json_encode($data);
            ?>

            <?php if($this->user->hasLogin() && $this->user->pass('administrator', true)): ?>
                <div class="admin-panel">
                    <a href="<?php $this->options->siteUrl('index.php/upload.html'); ?>" target="_blank" class="upload-btn-link">
                        <svg class="upload-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        上传图片 (Admin)
                    </a>
                </div>
            <?php endif; ?>

            <div class="gallery-filter" id="gallery-filter">
                <?php foreach ($data as $catName => $imgs): ?>
                    <?php if (count($imgs) > 0): ?>
                        <button class="filter-btn <?php echo ($catName === $defaultCat) ? 'active' : ''; ?>" 
                                onclick="switchCategory('<?php echo $catName; ?>', this)">
                            <?php echo $catName; ?> 
                            <span style="font-size:12px; opacity:0.6;">(<?php echo count($imgs); ?>)</span>
                        </button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="wallpaper-container" id="gallery-container"></div>
            <div id="loading-sentinel">正在加载更多...</div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/lozad/dist/lozad.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@4.0/dist/fancybox.umd.js"></script>

<script>
    const allData = <?php echo $jsonData; ?>;
    const defaultCategory = "<?php echo $defaultCat; ?>";

    const container = document.getElementById('gallery-container');
    const sentinel = document.getElementById('loading-sentinel');
    
    let currentCategory = defaultCategory;
    let currentImages = allData[defaultCategory];
    let currentIndex = 0;
    const batchSize = 24;

    const lozadObserver = lozad('.lozad', {
        rootMargin: '200px 0px', 
        loaded: function(el) {
            el.classList.add('is-loaded');
        }
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
            sentinel.innerText = "暂无图片";
            return;
        }
        if (currentIndex >= currentImages.length) {
            sentinel.innerText = "—— 到底了 ——";
            return;
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
            img.decoding = "async"; 
            img.width = 300;
            img.height = 200;
            img.alt = "";

            const mask = document.createElement('span');
            mask.className = 'click-mask';

            div.appendChild(img);
            div.appendChild(mask);
            fragment.appendChild(div);
            newImages.push(img);
        });

        container.appendChild(fragment);
        currentIndex += batchSize;

        window.requestAnimationFrame(() => {
            newImages.forEach(img => {
                lozadObserver.observe(img);
            });
        });

        if (currentIndex >= currentImages.length) {
            sentinel.innerText = "—— 到底了 ——";
        }
    }

    if (currentImages.length > 0) {
        const scrollObserver = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                window.requestAnimationFrame(() => {
                    renderBatch();
                });
            }
        }, { rootMargin: '300px' });
        
        scrollObserver.observe(sentinel);
        
        window.requestAnimationFrame(() => {
            renderBatch();
        });
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
