<?php
/**
 * GitHub Upload Page (UI Only)
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 权限校验
if (!$this->user->hasLogin() || !$this->user->pass('administrator', true)) {
    // 如果不是管理员，直接跳转回首页，或者显示 404
    $this->response->redirect($this->options->siteUrl);
    exit;
}

// 获取配置的 Slug，用于前端读取分类 (Read Only)
$cfg = Helper::options()->plugin('GithubUploader');
$GALLERY_SLUG = $cfg->gallery_slug;

// 读取现有分类
$db = Typecho_Db::get();
$page = $db->fetchRow($db->select()->from('table.contents')->where('slug = ?', $GALLERY_SLUG)->limit(1));
$cats = [];
if ($page) {
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $page['text']);
    if (preg_match_all('/^#\s+(.+?)\s*$/mu', $content, $matches)) {
        foreach ($matches[1] as $c) {
            $c = trim($c);
            if (!empty($c) && stripos($c, 'http') !== 0) $cats[] = $c;
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
    
    .cat-wrapper { display: flex; gap: 15px; justify-content: center; margin-bottom: 25px; flex-wrap: wrap; }
    .cat-elem { padding: 12px; border: 2px solid #eee; border-radius: 8px; font-size: 15px; outline: none; }
    .cat-select { min-width: 180px; cursor: pointer; background: #fff; }
    .cat-input { flex: 1; max-width: 200px; border-color: #333; display: none; animation: fadeIn 0.3s; }
    
    .upload-zone { border: 3px dashed #e0e0e0; border-radius: 12px; padding: 50px; cursor: pointer; background: #fafafa; transition: 0.3s; }
    .upload-zone:hover { border-color: #333; background: #f4f4f4; }
    
    .preview-box { margin-top: 20px; display: none; }
    .preview-img { max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .msg-tip { margin-top: 10px; font-weight: bold; color: #009068; }
    .url-input { width: 80%; padding: 10px; margin-top: 10px; border: 1px solid #ddd; border-radius: 4px; text-align: center; }
    
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
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
                        <option value="new_custom_cat" style="color:blue; font-weight:bold;">+ 新增分类...</option>
                    </select>
                    <input type="text" id="catCustom" class="cat-elem cat-input" placeholder="输入新分类名称">
                </div>

                <div class="upload-zone" id="dropZone">
                    <p>点击 / 拖拽 / 粘贴图片 (Ctrl+V)</p>
                    <input type="file" id="fileInput" style="display:none" accept="image/*">
                </div>

                <div class="preview-box" id="resultArea">
                    <div class="msg-tip" id="msg"></div>
                    <img id="preview" class="preview-img">
                    <input type="text" class="url-input" id="resUrl" readonly onclick="this.select()">
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    // 这是 Typecho 官方路由地址，如 /index.php/action/github-upload
    const ACTION_URL = "<?php $this->options->index('/action/github-upload'); ?>";

    const sel = document.getElementById('catSelect');
    const inp = document.getElementById('catCustom');
    const zone = document.getElementById('dropZone');
    const fileInp = document.getElementById('fileInput');

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

        document.getElementById('msg').innerText = "正在上传...";
        document.getElementById('resultArea').style.display = 'block';

        fetch(ACTION_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    document.getElementById('preview').src = d.url;
                    document.getElementById('msg').innerText = "✅ 成功！" + d.msg;
                    document.getElementById('resUrl').value = `![${file.name.replace(/\.[^/.]+$/, "")}](${d.url})`;
                } else {
                    alert('失败: ' + d.error);
                    document.getElementById('msg').innerText = "❌ 失败";
                }
            })
            .catch(e => {
                alert('网络错误: ' + e);
                console.error(e);
            });
    }
</script>

<?php $this->need('component/footer.php'); ?>
