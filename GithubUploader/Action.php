<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class GithubUploader_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        // 1. 权限校验
        $this->widget('Widget_User')->pass('administrator');
        
        // 2. 基础响应头
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');

        // 3. 获取配置
        $options = Helper::options();
        $cfg = $options->plugin('GithubUploader');
        $db = Typecho_Db::get();

        // 4. 获取动作类型 (upload 或 delete)
        $do = $this->request->get('do');

        // ==================================================
        // 分支 A: 删除图片 (Hard Delete)
        // ==================================================
        if ($do === 'delete') {
            $urlToDelete = $this->request->get('url');
            if (empty($urlToDelete)) $this->response->throwJson(['success'=>false, 'error'=>'未提供URL']);

            // A1. 解析文件路径 (从 URL 中提取仓库相对路径)
            // 假设 URL 是 https://cdn.../blog/2023/a.jpg，我们需要提取 blog/2023/a.jpg
            $path = '';
            $pos = strpos($urlToDelete, $cfg->folder . '/');
            if ($pos !== false) {
                $path = substr($urlToDelete, $pos);
                $path = explode('?', $path)[0]; // 去除参数
            } else {
                // 兜底：如果找不到 folder，尝试匹配文件名
                $path = $cfg->folder . '/' . basename($urlToDelete);
            }

            // A2. 获取文件 SHA (删除必须项)
            $apiUrl = "https://api.github.com/repos/{$cfg->owner}/{$cfg->repo}/contents/{$path}";
            // 如果有分支设置，加上 ref 参数
            if (!empty($cfg->branch)) $apiUrl .= "?ref={$cfg->branch}";

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: token ' . $cfg->token,
                'User-Agent: Typecho-Uploader'
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $fileData = json_decode($res, true);
            $sha = $fileData['sha'] ?? '';

            // A3. 执行 GitHub 删除
            if ($code == 200 && !empty($sha)) {
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'message' => 'Delete via Typecho',
                    'sha'     => $sha,
                    'branch'  => $cfg->branch
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: token ' . $cfg->token,
                    'User-Agent: Typecho-Uploader',
                    'Content-Type: application/json'
                ]);
                curl_exec($ch);
                curl_close($ch);
            }

            // A4. 清理数据库
            $page = $db->fetchRow($db->select()->from('table.contents')
                ->where('slug = ?', $cfg->gallery_slug)->limit(1));
            
            if ($page) {
                $text = $page['text'];
                // 简单替换删除
                $text = str_replace($urlToDelete, '', $text);
                // 清理空行
                $text = preg_replace("/[\r\n]{3,}/", "\n\n", $text);
                
                $db->query($db->update('table.contents')->rows(['text' => $text])->where('cid = ?', $page['cid']));
                $this->response->throwJson(['success'=>true, 'msg'=>'删除成功']);
            } else {
                $this->response->throwJson(['success'=>false, 'error'=>'找不到展示页']);
            }
        }

        // ==================================================
        // 分支 B: 上传图片
        // ==================================================
        if ($do === 'upload' || empty($do)) {
            $file = $_FILES['file'] ?? null;
            $selectCat = $this->request->get('category_select');
            $customCat = $this->request->get('category_custom');
            $targetCategory = ($selectCat === 'new_custom_cat') ? trim($customCat) : trim($selectCat);

            if (!$file || $file['error'] !== UPLOAD_ERR_OK) $this->response->throwJson(['success'=>false, 'error'=>'文件错误']);
            if (empty($targetCategory)) $this->response->throwJson(['success'=>false, 'error'=>'分类为空']);

            // B1. GitHub 上传
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = date('Y/m/') . time() . '_' . mt_rand(100, 999) . '.' . $ext;
            $path = trim($cfg->folder, '/') . '/' . $filename;
            $content = base64_encode(file_get_contents($file['tmp_name']));

            $apiUrl = "https://api.github.com/repos/{$cfg->owner}/{$cfg->repo}/contents/{$path}";
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'message' => 'Upload via Typecho', 'content' => $content, 'branch'  => $cfg->branch
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: token ' . $cfg->token,
                'User-Agent: Typecho-Uploader',
                'Content-Type: application/json'
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 201 || $code === 200) {
                $cdnUrl = sprintf("https://cdn.jsdelivr.net/gh/%s/%s@%s/%s", $cfg->owner, $cfg->repo, $cfg->branch, $path);
                
                // B2. 数据库写入
                $page = $db->fetchRow($db->select()->from('table.contents')->where('slug = ?', $cfg->gallery_slug)->limit(1));
                if ($page) {
                    $text = preg_replace('/^\xEF\xBB\xBF/', '', $page['text']);
                    $pattern = '/^\s*#\s*' . preg_quote($targetCategory, '/') . '\s*$/m';
                    if (preg_match($pattern, $text)) $newText = preg_replace($pattern, "$0\n" . $cdnUrl, $text, 1);
                    else $newText = $text . "\n\n# " . $targetCategory . "\n" . $cdnUrl;
                    
                    $db->query($db->update('table.contents')->rows(['text' => $newText])->where('cid = ?', $page['cid']));
                }
                $this->response->throwJson(['success'=>true, 'url'=>$cdnUrl]);
            } else {
                $err = json_decode($res, true);
                $this->response->throwJson(['success'=>false, 'error'=>$err['message'] ?? 'GitHub API Error']);
            }
        }
    }
}
