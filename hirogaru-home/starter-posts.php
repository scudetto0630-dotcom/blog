<?php
if (!defined('ABSPATH')) { exit; }
/** This connection can publish only the three packaged articles; never arbitrary posts. */
function hsh_starter_articles() {
    $items = json_decode(file_get_contents(plugin_dir_path(__FILE__) . 'starter-articles.json'), true);
    return is_array($items) ? $items : array();
}
function hsh_starter_status() {
    $result = array();
    foreach (hsh_starter_articles() as $article) {
        $post = get_page_by_path($article['slug'], OBJECT, 'post');
        $owned = $post && get_post_meta($post->ID, '_hsh_starter_owned', true) === $article['slug'];
        $result[] = array('slug' => $article['slug'], 'title' => $article['title'], 'id' => $owned ? $post->ID : null, 'status' => $owned ? $post->post_status : ($post ? 'conflict' : 'not_created'), 'url' => $owned ? get_permalink($post) : null);
    }
    return hsh_edit_response(array('articles' => $result));
}
function hsh_starter_publish($request) {
    $data = $request->get_json_params();
    if (!is_array($data) || !empty($data)) { return new WP_Error('hsh_input', '空のJSONオブジェクトのみ受け付けます。', array('status'=>400)); }
    if (!add_option('hsh_starter_lock', time(), '', false)) { return new WP_Error('hsh_busy', '別の投稿処理が実行中です。結果を確認してください。', array('status'=>409)); }
    try {
        $home = get_post((int)get_option('hsh_page_id'));
        $author = $home ? (int)$home->post_author : 0;
        if (!$author || !user_can($author, 'publish_posts')) { return new WP_Error('hsh_author', '投稿者を確認できません。', array('status'=>409)); }
        $articles = hsh_starter_articles();
        if (count($articles) !== 3) { return new WP_Error('hsh_bundle', '記事データを確認できません。', array('status'=>500)); }
        foreach ($articles as $article) {
            $category = get_term_by('slug', $article['category'], 'category');
            if (!$category) { return new WP_Error('hsh_category', 'カテゴリーを確認できません。', array('status'=>409)); }
            $existing = get_page_by_path($article['slug'], OBJECT, 'post');
            if ($existing && get_post_meta($existing->ID, '_hsh_starter_owned', true) !== $article['slug']) { return new WP_Error('hsh_conflict', '同じURLの記事が存在します。既存記事は変更しません。', array('status'=>409)); }
        }
        foreach ($articles as $article) {
            $existing = get_page_by_path($article['slug'], OBJECT, 'post');
            if ($existing) {
                if ($existing->post_status !== 'publish') { return new WP_Error('hsh_changed', '作成済み記事の状態が変更されています。自動再公開しません。', array('status'=>409)); }
                continue; // Do not overwrite later human edits or duplicate a completed publication.
            }
            $category = get_term_by('slug', $article['category'], 'category');
            $id = wp_insert_post(wp_slash(array('post_type'=>'post', 'post_status'=>'publish', 'post_author'=>$author, 'post_name'=>$article['slug'], 'post_title'=>sanitize_text_field($article['title']), 'post_content'=>wp_kses_post($article['content']), 'post_excerpt'=>sanitize_text_field($article['excerpt']), 'post_category'=>array((int)$category->term_id), 'comment_status'=>'closed', 'meta_input'=>array('_hsh_starter_owned'=>$article['slug'], 'the_page_meta_description'=>sanitize_text_field($article['excerpt'])))), true);
            if (is_wp_error($id)) { return $id; }
        }
        return hsh_starter_status();
    } finally { delete_option('hsh_starter_lock'); }
}
add_action('rest_api_init', function () {
    register_rest_route('hirogaru/v1', '/starter-posts', array(
        array('methods'=>'GET', 'callback'=>'hsh_starter_status', 'permission_callback'=>'hsh_edit_permission'),
        array('methods'=>'POST', 'callback'=>'hsh_starter_publish', 'permission_callback'=>'hsh_edit_permission'),
    ));
});
