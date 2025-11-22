<?php
/**
 * GitHub 图床上传助手
 * * @package GithubUploader
 * @author Brian
 * @version 1.0.0
 * @link http://typecho.org
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class GithubUploader_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        // 注册一个系统 Action 路由：/action/github-upload
        // 这样无论伪静态怎么设置，Typecho 都能精准拦截请求
        Helper::addAction('github-upload', 'GithubUploader_Action');
        return _t('插件已激活，请去设置 GitHub Token');
    }

    public static function deactivate()
    {
        Helper::removeAction('github-upload');
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $owner = new Typecho_Widget_Helper_Form_Element_Text('owner', NULL, '', _t('GitHub 用户名'), _t('例如: Brian'));
        $form->addInput($owner->addRule('required', _t('必须填写')));

        $repo = new Typecho_Widget_Helper_Form_Element_Text('repo', NULL, '', _t('仓库名 (Repo)'), _t('例如: images (必须是公开仓库)'));
        $form->addInput($repo->addRule('required', _t('必须填写')));

        $branch = new Typecho_Widget_Helper_Form_Element_Text('branch', NULL, 'main', _t('分支名'), _t('通常是 main 或 master'));
        $form->addInput($branch);

        $token = new Typecho_Widget_Helper_Form_Element_Text('token', NULL, '', _t('GitHub Token'), _t('ghp_开头的密钥 (权限需勾选 repo)'));
        $form->addInput($token->addRule('required', _t('必须填写')));

        $folder = new Typecho_Widget_Helper_Form_Element_Text('folder', NULL, 'blog', _t('存储目录'), _t('例如: blog'));
        $form->addInput($folder);

        $slug = new Typecho_Widget_Helper_Form_Element_Text('gallery_slug', NULL, 'gallery', _t('壁纸展示页缩略名'), _t('上传后会自动将图片写入该页面。请确保独立页面的缩略名(Slug)与此一致。'));
        $form->addInput($slug->addRule('required', _t('必须填写')));
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
}
