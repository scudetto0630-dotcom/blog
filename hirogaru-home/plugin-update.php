<?php
if (!defined('ABSPATH')) { exit; }
/** Only an immutable artifact from this site's repository may replace this plugin. */
function hsh_update_spec($data) {
    if (!is_array($data) || array_diff(array_keys($data), array('commit', 'sha256', 'expected_version')) || !isset($data['commit'], $data['sha256'], $data['expected_version']) || !is_string($data['commit']) || !preg_match('/^[a-f0-9]{40}$/D', $data['commit']) || !is_string($data['sha256']) || !preg_match('/^[a-f0-9]{64}$/D', $data['sha256']) || !is_string($data['expected_version'])) {
        return new WP_Error('hsh_update_input', 'コミット・SHA256・現在のバージョンが必要です。', array('status' => 400));
    }
    return 'https://raw.githubusercontent.com/scudetto0630-dotcom/blog/' . $data['commit'] . '/hirogaru-home.zip';
}
function hsh_update_archive($zip) {
    if ($zip->numFiles < 1 || $zip->numFiles > 200) { return false; }
    $total = 0; $main = false; $seen = array();
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if (!$stat || !isset($stat['name'], $stat['size'])) { return false; }
        $name = $stat['name'];
        if (!preg_match('~^hirogaru-home/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_.-]*$~D', $name) || strpos($name, '..') !== false || isset($seen[strtolower($name)])) { return false; }
        $seen[strtolower($name)] = true;
        $opsys = 0; $attr = 0;
        if (!$zip->getExternalAttributesIndex($i, $opsys, $attr) || (($attr >> 16) & 0170000) === 0120000) { return false; }
        $total += $stat['size'];
        if ($total > 50 * 1024 * 1024) { return false; }
        if ($name === 'hirogaru-home/hirogaru-home.php') { $main = true; }
    }
    return $main;
}
add_action('rest_api_init', function () {
    register_rest_route('hirogaru/v1', '/plugin-update', array('methods' => 'POST', 'permission_callback' => 'hsh_edit_permission', 'callback' => 'hsh_update_plugin'));
});
function hsh_update_plugin($request) {
    $data = $request->get_json_params(); $url = hsh_update_spec($data);
    if (is_wp_error($url)) { return $url; }
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $plugin = 'hirogaru-home/hirogaru-home.php';
    $info = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
    if ($data['expected_version'] !== $info['Version']) { return new WP_Error('hsh_update_conflict', '現在のバージョンが変更されています。', array('status' => 409)); }
    if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS || get_filesystem_method() !== 'direct' || !class_exists('ZipArchive')) { return new WP_Error('hsh_update_environment', 'このサーバーでは安全な自動更新を利用できません。管理画面で置き換えてください。', array('status' => 503)); }
    if (!add_option('hsh_plugin_update_lock', time(), '', false)) { return new WP_Error('hsh_update_busy', '更新処理中です。再試行前に状態を確認してください。', array('status' => 409)); }
    $temp = '';
    try {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        $reply = wp_safe_remote_get($url, array('timeout' => 90, 'redirection' => 0, 'limit_response_size' => 16 * 1024 * 1024));
        if (is_wp_error($reply) || wp_remote_retrieve_response_code($reply) !== 200) { return new WP_Error('hsh_update_download', '更新ファイルを取得できません。', array('status' => 502)); }
        $body = wp_remote_retrieve_body($reply);
        if (!hash_equals($data['sha256'], hash('sha256', $body))) { return new WP_Error('hsh_update_hash', '更新ファイルの検証に失敗しました。', array('status' => 400)); }
        $temp = wp_tempnam('hsh-release.zip');
        if (!$temp || file_put_contents($temp, $body) !== strlen($body)) { return new WP_Error('hsh_update_disk', '一時保存に失敗しました。', array('status' => 500)); }
        unset($body); $zip = new ZipArchive();
        if ($zip->open($temp) !== true) { return new WP_Error('hsh_update_zip', 'ZIPが不正です。', array('status' => 400)); }
        $valid = hsh_update_archive($zip);
        $header = $valid ? $zip->getFromName('hirogaru-home/hirogaru-home.php') : '';
        $zip->close();
        if (!is_string($header) || !preg_match('/^[ \t*]*Version:[ \t]*([0-9]+\.[0-9]+\.[0-9]+)[ \t]*$/m', $header, $release) || version_compare($release[1], $info['Version'], '<=')) { $valid = false; }
        if (!$valid) { return new WP_Error('hsh_update_zip', '専用プラグイン以外のファイル、または不正なZIPです。', array('status' => 400)); }
        // Require WordPress core's temporary-backup/rollback support.
        global $wp_version;
        if (version_compare($wp_version, '6.3', '<')) { return new WP_Error('hsh_update_core', 'WordPress 6.3以上が必要です。', array('status' => 503)); }
        $skin = new Automatic_Upgrader_Skin(); $upgrader = new Plugin_Upgrader($skin);
        $result = $upgrader->run(array('package' => $temp, 'destination' => WP_PLUGIN_DIR, 'clear_destination' => true, 'clear_working' => true, 'hook_extra' => array('plugin' => $plugin, 'type' => 'plugin', 'action' => 'update', 'temp_backup' => array('slug' => 'hirogaru-home', 'src' => WP_PLUGIN_DIR, 'dir' => 'plugins'))));
        if (is_wp_error($result) || !$result) { return new WP_Error('hsh_update_failed', '更新に失敗しました。WordPress管理画面で状態を確認してください。', array('status' => 500)); }
        update_option('hsh_last_plugin_update', array('commit' => $data['commit'], 'sha256' => $data['sha256'], 'time' => time()), false);
        return hsh_edit_response(array('updated' => true, 'next' => '次のリクエストでstatusとサイト表示を確認してください。'));
    } finally {
        if ($temp && file_exists($temp)) { unlink($temp); }
        delete_option('hsh_plugin_update_lock');
    }
}
