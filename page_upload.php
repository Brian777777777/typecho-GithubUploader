<?php
/**
 * GitHub Upload Page (UI Enhanced with Loading)
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 1. 权限校验
if (!$this->user->hasLogin() || !$this->user->pass('administrator', true)) {
    $this->response->redirect($this->options->siteUrl);
    exit;
}

// 2. 获取配置的 Slug
$cfg = Helper::options()->plugin('GithubUploader');
$GALLERY_SLUG = $cfg->gallery_slug;

// 3. 读取现有分类
$db = Typecho_Db::get();
$page = $db->fetchRow($db->select()->from('table.contents')->where('slug = ?', $GALLERY_SLUG)->limit(1));
$cats = [];

if ($page) {
    $content = $page['text'];
    $content = preg_replace('~<\s*br\s*/?\s*>~i', "\n", $content);
    $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
    $content = strip_tags($content);
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $content = preg_replace('/(^|\n)[\p{Cf}\x{00A0}]+/u', '$1', $content);
    if (preg_match_all('/^\s*#\s*(.+?)\s*$/mu', $content, $matches)) {
        foreach ($matches[1] as $c) {
            $c = trim($c);
            if ($c !== '' && stripos($c, 'http') !== 0) $cats[] = $c;
        }
    }

    $cats = array_values(array_unique($cats));
}


$this->need('component/header.php'); 
?>

<style>
    #main { min-height: 80vh; overflow: hidden; padding-bottom: 50px; width: 100%; display: block; }
    .main-inner .content-wrap { float: none !important; margin: 0 auto !important; display: block; width: 100%; max-width: 800px; }
    .upload-container { margin: 30px auto; padding: 20px; text-align: center; }
    
    /* 分类选择器 */
    .cat-wrapper { display: flex; gap: 15px; justify-content: center; margin-bottom: 25px; flex-wrap: wrap; }
    .cat-elem { padding: 12px; border: 2px solid #eee; border-radius: 8px; font-size: 15px; outline: none; }
    .cat-select { min-width: 180px; cursor: pointer; background: #fff; }
    .cat-input { flex: 1; max-width: 200px; border-color: #333; display: none; animation: fadeIn 0.3s; }
    
    /* 上传区域 */
    .upload-zone { 
        border: 3px dashed #e0e0e0; border-radius: 12px; padding: 40px 20px; height: 180px;
        display: flex; flex-direction: column; justify-content: center; align-items: center;
        cursor: pointer; background: #fafafa; transition: all 0.3s; position: relative;
    }
    .upload-zone:hover { border-color: #333; background: #f4f4f4; transform: translateY(-2px); }
    
    .upload-icon { color: #888; margin-bottom: 15px; transition: color 0.3s; }
    .upload-zone:hover .upload-icon { color: #333; }
    .upload-zone p { margin: 0; color: #666; font-size: 15px; font-weight: 500; }
    .sub-text { font-size: 12px; color: #999; margin-top: 5px !important; }

    /* Loading 样式 */
    /* 确保 Loading 区域居中显示 */
    .upload-loading { 
        display: none; 
        flex-direction: column; 
        align-items: center; 
        justify-content: center; 
        width: 100%; 
        height: 100%; /* 充满容器 */
    }

    .upload-loading p {
        margin-top: 10px;
        color: #888;
        font-size: 14px;
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

    /* 结果显示区域 */
    .preview-box { margin-top: 30px; display: none; text-align: left; animation: slideUp 0.4s ease; }
    
    .preview-img { 
        max-width: 100%; max-height: 300px; border-radius: 8px; 
        box-shadow: 0 5px 15px rgba(0,0,0,0.1); display: block; margin: 0 auto 20px; 
    }
    
    .msg-tip { 
        text-align: center; margin-bottom: 20px; font-weight: bold; 
        color: #009068; background: #e6fffa; padding: 10px; border-radius: 6px; border: 1px solid #b7eb8f;
    }

    /* 链接与按钮组 */
    .link-group { margin-bottom: 15px; }
    .link-label { font-size: 13px; color: #666; margin-bottom: 6px; display: block; font-weight: bold; }
    
    .input-wrapper { display: flex; gap: 10px; }
    
    .link-input { 
        flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px; 
        font-family: monospace; color: #333; background: #f9f9f9;
    }
    
    .copy-btn { 
        padding: 0 20px; background: #333; color: #fff; border: none; border-radius: 6px; 
        cursor: pointer; font-weight: bold; transition: background 0.2s; white-space: nowrap;
    }
    .copy-btn:hover { background: #555; }
    
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div id="main" class="main" role="main">
    <div class="main-inner clearfix">
        <div class="content-wrap">
            <div class="upload-container">
                
                <div class="cat-wrapper">
                    <select id="catSelect" class="cat-elem cat-select">
                        <option value="" disabled selected>-- 选择分类 --</option>
                        <?php foreach ($cats as $c): ?>
                            <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                        <?php endforeach; ?>
                        <option value="new_custom_cat" style="color:#0070f3; font-weight:bold;">+ 新增分类...</option>
                    </select>
                    <input type="text" id="catCustom" class="cat-elem cat-input" placeholder="输入新分类名称">
                </div>

                <div class="upload-zone" id="dropZone">
                    
                    <div id="upload-default-content">
                        <svg width="100" height="100" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <style>
                                /* 呼吸浮动 */
                                .cute-cloud-body { animation: float 3s ease-in-out infinite; }
                                @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
                                
                                /* 眨眼卖萌 */
                                .cute-eye-l, .cute-eye-r { animation: blink 4s infinite; transform-origin: center; }
                                .cute-eye-r { animation-delay: 0.1s; } /* 稍微错开一点点更自然 */
                                @keyframes blink { 
                                    0%, 48%, 52%, 100% { transform: scaleY(1); } 
                                    50% { transform: scaleY(0.1); } 
                                }
                                
                                /* 腮红微闪 */
                                .cute-blush { animation: blush 3s ease-in-out infinite; }
                                @keyframes blush { 0%, 100% { opacity: 0.6; } 50% { opacity: 0.8; } }
                            </style>

                            <g class="cute-cloud-body">
                                <path d="M25 65 C12 65 8 49 16 41 C16 25 35 23 42 29 C48 19 72 17 78 33 C92 31 98 45 88 59 C85 65 82 65 75 65 Z" 
                                    fill="#ffffff" stroke="#333" stroke-width="4" stroke-linejoin="round"/>
                                
                                <g transform="translate(0, 2)">
                                    <circle class="cute-eye-l" cx="40" cy="48" r="3" fill="#333"/>
                                    <circle class="cute-eye-r" cx="62" cy="48" r="3" fill="#333"/>
                                    
                                    <circle class="cute-blush" cx="30" cy="52" r="4" fill="#ffadad" opacity="0.6"/>
                                    <circle class="cute-blush" cx="72" cy="52" r="4" fill="#ffadad" opacity="0.6"/>
                                    
                                    <path d="M47 52 Q51 56 55 52" stroke="#333" stroke-width="2.5" stroke-linecap="round"/>
                                </g>
                            </g>
                        </svg>
                        <p>点击 / 拖拽 / 粘贴图片 (Ctrl+V)</p>
                    </div>

                    <div id="upload-loading-state" class="upload-loading">
                        <svg width="100" height="100" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <style>
                                /* 云朵轻微抖动 */
                                .loading-cloud { animation: shake 2s ease-in-out infinite; }
                                @keyframes shake { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-2px); } }

                                /* 雨滴跳动 */
                                .rain-drop { animation: drop-bounce 1s ease-in-out infinite; }
                                .drop-1 { animation-delay: 0s; fill: #74b9ff; } /* 蓝 */
                                .drop-2 { animation-delay: 0.2s; fill: #a29bfe; } /* 紫 */
                                .drop-3 { animation-delay: 0.4s; fill: #ff7675; } /* 粉 */

                                @keyframes drop-bounce {
                                    0%, 100% { transform: translateY(0); opacity: 0.5; }
                                    50% { transform: translateY(10px); opacity: 1; }
                                }
                            </style>

                            <g class="loading-cloud" transform="translate(0, -10)">
                                <path d="M25 65 C12 65 8 49 16 41 C16 25 35 23 42 29 C48 19 72 17 78 33 C92 31 98 45 88 59 C85 65 82 65 75 65 Z" 
                                    fill="#ffffff" stroke="#333" stroke-width="4" stroke-linejoin="round"/>
                                <path d="M38 48 L42 50 L38 52" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M64 48 L60 50 L64 52" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </g>

                            <g transform="translate(0, 5)">
                                <path class="rain-drop drop-1" d="M35 75 Q38 82 35 82 Q32 82 35 75" />
                                <path class="rain-drop drop-2" d="M50 75 Q53 82 50 82 Q47 82 50 75" />
                                <path class="rain-drop drop-3" d="M65 75 Q68 82 65 82 Q62 82 65 75" />
                            </g>
                        </svg>
                        <p>正在上传并写入数据库...</p>
                    </div>

                    <input type="file" id="fileInput" style="display:none" accept="image/*">
                </div>

                <div class="preview-box" id="resultArea">
                    <div class="msg-tip" id="msg"></div>
                    <img id="preview" class="preview-img">
                    
                    <div class="link-group">
                        <label class="link-label">Markdown 链接 (推荐)</label>
                        <div class="input-wrapper">
                            <input type="text" class="link-input" id="mdUrl" readonly>
                            <button class="copy-btn" onclick="copyToClipboard('mdUrl')">复制</button>
                        </div>
                    </div>

                    <div class="link-group">
                        <label class="link-label">源文件 URL</label>
                        <div class="input-wrapper">
                            <input type="text" class="link-input" id="rawUrl" readonly>
                            <button class="copy-btn" onclick="copyToClipboard('rawUrl')">复制</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    const ACTION_URL = "<?php $this->options->index('/action/github-upload'); ?>";

    const sel = document.getElementById('catSelect');
    const inp = document.getElementById('catCustom');
    const zone = document.getElementById('dropZone');
    const fileInp = document.getElementById('fileInput');
    
    // UI 元素
    const defaultContent = document.getElementById('upload-default-content');
    const loadingState = document.getElementById('upload-loading-state');
    const resultArea = document.getElementById('resultArea');

    sel.onchange = () => {
        if (sel.value === 'new_custom_cat') { inp.style.display = 'block'; inp.focus(); }
        else { inp.style.display = 'none'; inp.value = ''; }
    };

    zone.onclick = () => fileInp.click();
    fileInp.onchange = () => { if(fileInp.files[0]) upload(fileInp.files[0]); };
    
    zone.ondragover = (e) => { e.preventDefault(); zone.style.borderColor = '#333'; };
    zone.ondragleave = () => { zone.style.borderColor = '#e0e0e0'; };
    zone.ondrop = (e) => { 
        e.preventDefault(); zone.style.borderColor = '#e0e0e0';
        if(e.dataTransfer.files[0]) upload(e.dataTransfer.files[0]); 
    };

    document.onpaste = (e) => {
        const item = e.clipboardData.items[0];
        if (item && item.type.indexOf('image') > -1) upload(item.getAsFile());
    };

    function upload(file) {
        const cat = (sel.value === 'new_custom_cat') ? inp.value.trim() : sel.value;
        if (!cat) return alert('请选择或输入分类');

        const fd = new FormData();
        fd.append('file', file);
        fd.append('category_select', sel.value);
        fd.append('category_custom', inp.value);

        // 1. 切换 UI 到 Loading 状态
        defaultContent.style.display = 'none';
        loadingState.style.display = 'flex'; // flex 布局居中
        resultArea.style.display = 'none';   // 隐藏上次的结果

        fetch(ACTION_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                // 2. 恢复 UI
                defaultContent.style.display = 'block';
                loadingState.style.display = 'none';

                if (d.success) {
                    document.getElementById('preview').src = d.url;
                    document.getElementById('msg').innerText = "(⁎˃ᆺ˂)  上传成功！";
                    
                    const alt = file.name.replace(/\.[^/.]+$/, "");
                    document.getElementById('mdUrl').value = `![${alt}](${d.url})`;
                    document.getElementById('rawUrl').value = d.url;
                    
                    // 3. 显示结果区域
                    resultArea.style.display = 'block';
                } else {
                    alert('失败: ' + d.error);
                }
            })
            .catch(e => {
                // 错误也要恢复 UI
                defaultContent.style.display = 'block';
                loadingState.style.display = 'none';
                alert('网络错误: ' + e);
                console.error(e);
            });
    }

    function copyToClipboard(elementId) {
        const inputElement = document.getElementById(elementId);
        inputElement.select();
        inputElement.setSelectionRange(0, 99999); 

        try {
            document.execCommand("copy");
            const btn = inputElement.nextElementSibling;
            const originalText = btn.innerText;
            btn.innerText = '已复制!';
            btn.style.background = '#009068';
            setTimeout(() => {
                btn.innerText = originalText;
                btn.style.background = '#333';
            }, 1500);
        } catch (err) {
            alert('复制失败，请手动复制');
        }
    }
</script>

<?php $this->need('component/footer.php'); ?>
