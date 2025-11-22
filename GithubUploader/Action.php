<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class GithubUploader_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        // 1. 权限与环境校验
        $this->widget('Widget_User')->pass('administrator'); // 非管理员直接 403 报错终止
        
        // 2. 清空缓冲区，确保只输出 JSON
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');

        // 3. 获取插件配置
        $options = Helper::options();
        $cfg = $options->plugin('GithubUploader');

        // 4. 接收参数
        if (!$this->request->isPost()) {
            $this->response->throwJson(['success' => false, 'error' => 'Method Not Allowed']);
        }

        $file = $_FILES['file'] ?? null;
        $selectCat = $this->request->get('category_select');
        $customCat = $this->request->get('category_custom');
        $targetCategory = ($selectCat === 'new_custom_cat') ? trim($customCat) : trim($selectCat);

        if (empty($targetCategory)) $this->response->throwJson(['success' => false, 'error' => '请选择或输入分类']);
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) $this->response->throwJson(['success' => false, 'error' => '文件上传失败']);

        // 5. GitHub 上传
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = date('Y/m/') . time() . '_' . mt_rand(100, 999) . '.' . $ext;
        $path = trim($cfg->folder, '/') . '/' . $filename;
        $content = base64_encode(file_get_contents($file['tmp_name']));

        $apiUrl = "https://api.github.com/repos/{$cfg->owner}/{$cfg->repo}/contents/{$path}";
        $payload = json_encode([
            'message' => 'Upload via Typecho Plugin',
            'content' => $content,
            'branch'  => $cfg->branch
        ]);

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $cfg->token,
            'User-Agent: Typecho-Uploader',
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 201 || $httpCode === 200) {
            $cdnUrl = sprintf("https://cdn.jsdelivr.net/gh/%s/%s@%s/%s", $cfg->owner, $cfg->repo, $cfg->branch, $path);
            
            // 6. 写入数据库
            $db = Typecho_Db::get();
            $page = $db->fetchRow($db->select()->from('table.contents')
                ->where('slug = ?', $cfg->gallery_slug)
                ->where('type = ?', 'page')->limit(1));
            
            $writeMsg = "";
            if ($page) {
                $text = $page['text'];
                // BOM 清洗
                $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
                
                $pattern = '/^\s*#\s*' . preg_quote($targetCategory, '/') . '\s*$/m';
                if (preg_match($pattern, $text)) {
                    $newText = preg_replace($pattern, "$0\n" . $cdnUrl, $text, 1);
                } else {
                    $newText = $text . "\n\n# " . $targetCategory . "\n" . $cdnUrl;
                }
                
                $db->query($db->update('table.contents')->rows(['text' => $newText])->where('cid = ?', $page['cid']));
                $writeMsg = " | 已归档至: {$targetCategory}";
            } else {
                $writeMsg = " | ⚠️ 归档失败 (找不到slug: {$cfg->gallery_slug})";
            }

            echo json_encode(['success' => true, 'url' => $cdnUrl, 'msg' => $writeMsg]);
        } else {
            $err = json_decode($response, true);
            echo json_encode(['success' => false, 'error' => 'GitHub: ' . ($err['message'] ?? 'Unknown')]);
        }
        exit; // 完美结束，绝无 HTML 干扰
    }
}
