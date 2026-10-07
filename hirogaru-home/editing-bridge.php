<?php
if (!defined('ABSPATH')) { exit; }
/** Scoped editing API: no PHP, files, users, plugins, or arbitrary existing posts. */
function hsh_connection_admin($new_token) {
    echo '<hr><h2>専用編集接続</h2><p>トップページの文章・配色と、この接続が新規作成した固定ページだけを編集できます。既存記事、テーマ、プラグイン、ユーザーの管理権限は付与しません。</p>';
    echo '<p>キーはパスワードと同様に扱い、チャットやスクリーンショットには含めないでください。Codex環境設定の秘密情報 <code>HSH_EDIT_TOKEN</code> に登録します。</p>';
    echo '<p>接続状態：' . (get_option('hsh_edit_token_hash') ? 'キー発行済み（外部からの接続確認は別途必要）' : '無効') . '</p>';
    if ($new_token) {
        echo '<p><label for="hsh-token">今回の接続キー（一度だけ表示）</label><br><input id="hsh-token" type="text" readonly autocomplete="off" spellcheck="false" style="width:min(100%,700px)" value="' . esc_attr($new_token) . '"></p>';
    }
    echo '<form method="post">'; wp_nonce_field('hsh_settings');
    echo '<button class="button" name="hsh_action" value="connect">接続キーを発行・再発行</button> <button class="button" name="hsh_action" value="disconnect">編集接続を無効にする</button></form>';
}
function hsh_home_value($name, $fallback) {
    $settings = get_option('hsh_home_settings', array());
    return isset($settings[$name]) ? $settings[$name] : $fallback;
}
function hsh_edit_public_pages() {
    $result = array();
    foreach (get_option('hsh_edit_pages', array()) as $id) {
        $page = get_post((int) $id);
        if ($page && $page->post_type === 'page' && $page->post_status === 'publish' && get_post_meta($page->ID, '_hsh_bridge_owned', true) === '1') { $result[] = $page; }
    }
    return $result;
}
function hsh_edit_permission($request) {
    if (!is_ssl() || wp_parse_url(home_url('/'), PHP_URL_SCHEME) !== 'https') { return new WP_Error('hsh_https', 'HTTPSが必要です。', array('status' => 403)); }
    $stored = get_option('hsh_edit_token_hash', '');
    $header = $request->get_header('authorization');
    if (!is_string($stored) || strlen($stored) !== 64 || !is_string($header) || !preg_match('/^Bearer ([A-Za-z0-9]{64})$/D', $header, $match) || !hash_equals($stored, hash('sha256', $match[1]))) {
        return new WP_Error('hsh_auth', '編集接続の認証が必要です。', array('status' => 401));
    }
    return true;
}
function hsh_edit_response($data) {
    $response = new WP_REST_Response($data);
    $response->header('Cache-Control', 'no-store, private');
    return $response;
}
add_action('rest_api_init', function () {
    foreach (array('status' => 'GET', 'home' => 'POST', 'page' => 'POST') as $name => $method) {
        register_rest_route('hirogaru/v1', '/' . $name, array('methods' => $method, 'callback' => 'hsh_edit_' . $name, 'permission_callback' => 'hsh_edit_permission'));
    }
});
function hsh_edit_status() {
    $pages = array();
    foreach (get_option('hsh_edit_pages', array()) as $key => $id) {
        if (get_post_meta((int) $id, '_hsh_bridge_owned', true) === '1') {
            $page = get_post((int) $id);
            if ($page) { $pages[$key] = array('id' => $page->ID, 'status' => $page->post_status, 'title' => $page->post_title, 'content' => $page->post_content, 'url' => get_permalink($page)); }
        }
    }
    return hsh_edit_response(array('version' => '1.1.0', 'scope' => array('homepage_text_colors', 'bridge_owned_pages'), 'home_page_id' => (int) get_option('hsh_page_id'), 'home_settings' => get_option('hsh_home_settings', array()), 'pages' => $pages));
}
function hsh_edit_home($request) {
    $data = $request->get_json_params();
    if (!is_array($data) || !$data) { return new WP_Error('hsh_input', 'JSONオブジェクトが必要です。', array('status' => 400)); }
    $allowed = array('heading_1', 'heading_2', 'heading_3', 'intro', 'about', 'background', 'text_color', 'accent', 'hide_empty');
    $next = get_option('hsh_home_settings', array());
    foreach ($data as $key => $value) {
        if (!in_array($key, $allowed, true)) { return new WP_Error('hsh_field', '未対応の設定項目です。', array('status' => 400)); }
        if ($key === 'hide_empty') {
            if (!is_bool($value)) { return new WP_Error('hsh_value', 'hide_empty は true / false です。', array('status' => 400)); }
            $next[$key] = $value; continue;
        }
        if (!is_string($value) || strlen($value) > 12000) { return new WP_Error('hsh_value', '文字列の長さ・形式が不正です。', array('status' => 400)); }
        if (in_array($key, array('background', 'text_color', 'accent'), true)) {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $value)) { return new WP_Error('hsh_color', '色は #RRGGBB で指定してください。', array('status' => 400)); }
            $next[$key] = $value;
        } elseif ($key === 'about') { $next[$key] = wp_kses_post($value); }
        else {
            $next[$key] = sanitize_textarea_field($value);
            if (strpos($key, 'heading_') === 0 && trim($next[$key]) === '') { return new WP_Error('hsh_heading', '見出しは空にできません。', array('status' => 400)); }
        }
    }
    $history = get_option('hsh_home_history', array());
    $history[] = array('time' => time(), 'settings' => get_option('hsh_home_settings', array()));
    update_option('hsh_home_history', array_slice($history, -10), false);
    update_option('hsh_home_settings', $next, false);
    return hsh_edit_response(array('updated' => true, 'settings' => $next));
}
function hsh_edit_page($request) {
    $data = $request->get_json_params();
    if (!is_array($data) || array_diff(array_keys($data), array('key', 'title', 'content', 'status'))) { return new WP_Error('hsh_input', 'JSON項目が不正です。', array('status' => 400)); }
    if (!isset($data['key'], $data['title'], $data['content']) || !is_string($data['key']) || !in_array($data['key'], array('about', 'editorial', 'contact'), true) || !is_string($data['title']) || !is_string($data['content']) || strlen($data['title']) > 600 || strlen($data['content']) > 100000 || trim(sanitize_text_field($data['title'])) === '') {
        return new WP_Error('hsh_input', 'key / title / content が不正です。', array('status' => 400));
    }
    $status = isset($data['status']) ? $data['status'] : 'draft';
    if (!is_string($status) || !in_array($status, array('draft', 'publish'), true)) { return new WP_Error('hsh_status', '公開状態が不正です。', array('status' => 400)); }
    $lock = 'hsh_edit_page_lock';
    if (!add_option($lock, time(), '', false)) { return new WP_Error('hsh_busy', '他の処理が実行中です。', array('status' => 409)); }
    try {
        $pages = get_option('hsh_edit_pages', array());
        $id = isset($pages[$data['key']]) ? (int) $pages[$data['key']] : 0;
        if ($id && (get_post_type($id) !== 'page' || get_post_meta($id, '_hsh_bridge_owned', true) !== '1')) { return new WP_Error('hsh_scope', '既存ページは編集できません。', array('status' => 403)); }
        $post = array('post_type' => 'page', 'post_status' => $status, 'post_title' => sanitize_text_field($data['title']), 'post_content' => wp_kses_post($data['content']));
        if ($id) { $post['ID'] = $id; }
        else {
            $slug = 'hsh-' . $data['key'];
            if (get_page_by_path($slug, OBJECT, 'page')) { return new WP_Error('hsh_conflict', '同名ページが存在します。既存ページは上書きしません。', array('status' => 409)); }
            $post['post_name'] = $slug;
            $post['meta_input'] = array('_hsh_bridge_owned' => '1');
        }
        $saved = wp_insert_post(wp_slash($post), true);
        if (is_wp_error($saved)) { return $saved; }
        $pages[$data['key']] = $saved;
        update_option('hsh_edit_pages', $pages, false);
        return hsh_edit_response(array('id' => $saved, 'status' => get_post_status($saved), 'url' => get_permalink($saved)));
    } finally { delete_option($lock); }
}
add_action('wp_enqueue_scripts', function () {
    $id = (int) get_option('hsh_page_id');
    if (!$id || !is_page($id)) { return; }
    $settings = get_option('hsh_home_settings', array());
    $css = '';
    foreach (array('background' => 'background', 'text_color' => 'color') as $key => $property) {
        if (isset($settings[$key]) && preg_match('/^#[0-9a-fA-F]{6}$/D', $settings[$key])) { $css .= 'body.hsh-home{' . $property . ':' . $settings[$key] . ';}'; }
    }
    if (isset($settings['accent']) && preg_match('/^#[0-9a-fA-F]{6}$/D', $settings['accent'])) { $css .= 'body.hsh-home .hsh-button{background:' . $settings['accent'] . ';}'; }
    if ($css) { wp_add_inline_style('hsh-home', $css); }
}, 20);
register_deactivation_hook(plugin_dir_path(__FILE__) . 'hirogaru-home.php', function () { delete_option('hsh_edit_token_hash'); });
