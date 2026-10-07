<?php
/**
 * Plugin Name: ひろがる趣味暮らし トップページ
 * Description: 猫・ウイスキー・ITとフリーランスのトップページ。管理画面から適用・復元できます。
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }
function hsh_topics() {
    return array(
        'cats' => array('猫と暮らす', '一緒に過ごす毎日を、心地よく。猫との暮らしの工夫と、もの選び。', 'CAT LIFE', '01'),
        'whisky' => array('ウイスキーを楽しむ', 'お気に入りの一杯を見つける。ウイスキーの選び方と、楽しむ時間。', 'WHISKY TIME', '02'),
        'it-freelance' => array('ITの仕事とフリーランス', '正社員からフリーランスへ。未経験で始めたITの仕事と、AIを使った日々の工夫。', 'WORK & FREELANCE', '03'),
    );
}
add_action('admin_menu', function () {
    add_theme_page('ひろがるトップページ', 'ひろがるトップページ', 'manage_options', 'hsh-home', 'hsh_admin');
});
function hsh_admin() {
    if (!current_user_can('manage_options')) { return; }
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hsh_action'])) {
        check_admin_referer('hsh_settings');
        $action = sanitize_key(wp_unslash($_POST['hsh_action']));
        if ($action === 'restore') {
            $old = get_option('hsh_original_front');
            $id = (int) get_option('hsh_page_id');
            if (is_array($old) && (int) get_option('page_on_front') === $id) {
                update_option('show_on_front', $old['show_on_front']);
                update_option('page_on_front', $old['page_on_front']);
                delete_option('hsh_original_front');
                $message = '元のトップページ設定に戻しました。作成したページとカテゴリーは残しています。';
            } else { $message = '復元対象の設定がないか、トップページが別のページに変更されています。'; }
        } elseif ($action === 'apply') {
            $result = hsh_apply();
            $message = is_wp_error($result) ? $result->get_error_message() : 'トップページを適用しました。サイトのキャッシュがある場合は削除してください。';
        }
    }
    echo '<div class="wrap"><h1>ひろがる趣味暮らし トップページ</h1>';
    if ($message) { echo '<div class="notice notice-info"><p>' . esc_html($message) . '</p></div>'; }
    echo '<p>猫・ウイスキー・ITとフリーランスの3カテゴリーと、専用トップページを作成します。既存記事・テーマ・メニューは書き換えません。</p><p>適用前にサイトのバックアップを取得してください。トップページには独立したレイアウトを使い、記事ページは現在のテーマで表示します。</p><form method="post">';
    wp_nonce_field('hsh_settings');
    echo '<button class="button button-primary" name="hsh_action" value="apply">トップページを適用</button> <button class="button" name="hsh_action" value="restore">元のトップページ設定に戻す</button></form>';
    if (get_option('hsh_page_id')) { echo '<p><a href="' . esc_url(get_permalink((int) get_option('hsh_page_id'))) . '" target="_blank" rel="noopener">作成したページを見る</a></p>'; }
    echo '<p>停止する場合は、先に「元のトップページ設定に戻す」を実行してからプラグインを無効化してください。</p></div>';
}
function hsh_apply() {
    $ids = array();
    foreach (hsh_topics() as $slug => $topic) {
        $term = get_term_by('slug', $slug, 'category');
        if (!$term) {
            $inserted = wp_insert_term($topic[0], 'category', array('slug' => $slug));
            if (is_wp_error($inserted)) { return $inserted; }
            $ids[$slug] = (int) $inserted['term_id'];
        } else { $ids[$slug] = (int) $term->term_id; }
    }
    $id = (int) get_option('hsh_page_id');
    if ($id && get_post_type($id) !== 'page') { return new WP_Error('hsh_page', '保存されたページが見つかりません。設定の確認が必要です。'); }
    if (!$id) {
        $id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'ひろがる趣味暮らし', 'post_name' => 'hirogaru-home', 'post_content' => ''), true);
        if (is_wp_error($id)) { return $id; }
        update_option('hsh_page_id', $id);
    }
    if (get_post_status($id) !== 'publish') { return new WP_Error('hsh_status', '専用ページが公開状態ではありません。ページを確認してください。'); }
    update_option('hsh_categories', $ids);
    if (!get_option('hsh_original_front')) {
        update_option('hsh_original_front', array('show_on_front' => get_option('show_on_front'), 'page_on_front' => get_option('page_on_front')), false);
    }
    update_option('page_on_front', $id);
    update_option('show_on_front', 'page');
    return $id;
}
add_filter('template_include', function ($template) {
    $id = (int) get_option('hsh_page_id');
    return $id && is_page($id) ? plugin_dir_path(__FILE__) . 'home-template.php' : $template;
}, 99);
add_action('wp_enqueue_scripts', function () {
    if ((int) get_option('hsh_page_id') && is_page((int) get_option('hsh_page_id'))) {
        wp_enqueue_style('hsh-home', plugin_dir_url(__FILE__) . 'assets/home.css', array(), '1.0.0');
    }
});
function hsh_category_url($slug) {
    $ids = get_option('hsh_categories', array());
    $url = isset($ids[$slug]) ? get_category_link($ids[$slug]) : '';
    return is_wp_error($url) || !$url ? home_url('/') : $url;
}
function hsh_posts($slug, $limit = 3) {
    $ids = get_option('hsh_categories', array());
    return isset($ids[$slug]) ? get_posts(array('category' => (int) $ids[$slug], 'numberposts' => $limit, 'post_status' => 'publish')) : array();
}
register_deactivation_hook(__FILE__, function () {
    $old = get_option('hsh_original_front');
    if (is_array($old) && (int) get_option('page_on_front') === (int) get_option('hsh_page_id')) {
        update_option('show_on_front', $old['show_on_front']);
        update_option('page_on_front', $old['page_on_front']);
        delete_option('hsh_original_front');
    }
});
