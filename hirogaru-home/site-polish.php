<?php
if (!defined('ABSPATH')) { exit; }
function hsh_topic_details() {
    return array(
        'cats' => array('猫の世話とキャリー・おもちゃの選び方', '猫との暮らしで気になることや、キャリー・おもちゃの選び方について書いています。通院用キャリーの記事では、体格や持ち運び方に合わせた確認点をまとめています。', 'cat-carrier-checklist', '通院用キャリーの選び方', '猫の世話や、キャリー・おもちゃの選び方についての記事をまとめています。'),
        'whisky' => array('自宅で飲むウイスキーの選び方と楽しみ方', 'ウイスキーの飲み方や銘柄の選び方、グラスなどの道具について書いています。自宅でハイボールを作るときの分量や手順から紹介します。', 'highball-measure-and-adjust', 'ハイボールが薄く感じるときの見直し方', 'ウイスキーの飲み方や選び方、グラスなどの道具についての記事をまとめています。'),
        'it-freelance' => array('会社員からフリーランスへ移り、ITの仕事を始めました', 'MRからITの仕事へ移り、今はフリーランスとして働いています。仕事でデータを扱うときの注意点やAIの使い方、会社員から働き方を変えた経験について書いていきます。', 'csv-import-seven-checks', 'CSVを取り込む前に確認したいこと', 'データの整理やAIの使い方、フリーランスとして働くことについての記事をまとめています。'),
    );
}
function hsh_current_topic() {
    foreach (hsh_topics() as $slug => $topic) { if (is_category($slug)) { return $slug; } }
    return '';
}
add_filter('template_include', function ($template) {
    return hsh_current_topic() ? plugin_dir_path(__FILE__) . 'category-template.php' : $template;
}, 100);
function hsh_profile_markup() {
    return '<aside class="hsh-profile" aria-label="書き手について"><img src="' . esc_url(plugin_dir_url(__FILE__) . 'assets/photos/manmos-portrait.webp') . '" alt="保護猫のまんもす。管理人提供の写真です。" width="160" height="160" loading="lazy"><div><p class="hsh-kicker">管理人のプロフィール</p><strong>ひろがる趣味暮らし 管理人</strong><p>保護猫のまんもすと暮らしています。以前はMRとして働き、今はフリーランスでデータをシステムに取り込む仕事をしています。ウイスキーはシングルモルトが好みです。</p><a href="' . esc_url(home_url('/hsh-about/')) . '">プロフィールを読む →</a><small>写真は、管理人と暮らすまんもすです。</small></div></aside>';
}
function hsh_search_markup($id) {
    $value=function_exists('get_search_query')?get_search_query(false):'';
    if(isset($_GET['s'])&&is_string($_GET['s']))$value=wp_unslash($_GET['s']);
    $topic=function_exists('hsh_search_topic')?hsh_search_topic(isset($_GET['hsh_topic'])?wp_unslash($_GET['hsh_topic']):''):'';
    $options='<option value="">全カテゴリー</option>';
    foreach(hsh_topics() as $key=>$t)$options.='<option value="'.esc_attr($key).'"'.($topic===$key?' selected':'').'>'.esc_html($t[0]).'</option>';
    return '<form class="hsh-search" role="search" method="get" action="'.esc_url(home_url('/')).'"><label for="'.esc_attr($id).'">ブログ内を検索</label><div><input id="'.esc_attr($id).'" name="s" type="search" value="'.esc_attr($value).'" maxlength="100" placeholder="キャリー、ハイボール、CSVなど"><select name="hsh_topic" aria-label="検索するカテゴリー">'.$options.'</select><button type="submit">検索</button></div></form>';
}
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('hsh-reading', plugin_dir_url(__FILE__) . 'assets/reading.css', array(), '1.14.0');
    if ((int)get_option('hsh_page_id') && is_page((int)get_option('hsh_page_id'))) {
        foreach (array('cocoon-style','cocoon-keyframes','font-awesome-style','icomoon-style','baguettebox-style','cocoon-child-style','cocoon-child-keyframes','contact-form-7') as $handle) { wp_dequeue_style($handle); }
    }
}, 100);
add_filter('the_content', function ($content) {
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) { return $content; }
    $id = get_the_ID();
    if (!get_post_meta($id, '_hsh_starter_owned', true)) { return $content; }
    $slug = get_post_field('post_name', $id); $details = hsh_topic_details();
    foreach ($details as $topic => $d) {
        if ($d[2] !== $slug) { continue; }
        $url = hsh_category_url($topic);
        $content .= hsh_related_markup($topic,$id) . hsh_article_review_note($slug) . '<section class="hsh-next"><h2>同じカテゴリーの記事</h2><p>' . esc_html($d[4]) . '</p><a href="' . esc_url($url) . '">' . esc_html(hsh_topics()[$topic][0]) . 'の記事一覧 →</a></section>';
    }
    $intro=hsh_intro_post();
    if(!is_wp_error($intro)){$content.='<p class="hsh-next-overview"><a href="'.esc_url(get_permalink($intro)).'">このブログで読めることを見る →</a></p>';}
    return $content . hsh_profile_markup();
}, 20);
add_action('wp_footer', function () {
    if (is_page((int)get_option('hsh_page_id')) || hsh_current_topic() || is_search()) { return; }
    echo '<nav class="hsh-bottom-nav" aria-label="ブログの案内"><a href="' . esc_url(home_url('/')) . '">トップ</a>';
    foreach (hsh_topics() as $slug => $t) { echo '<a href="' . esc_url(hsh_category_url($slug)) . '">' . esc_html($t[0]) . '</a>'; }
    echo '<a href="' . esc_url(home_url('/hsh-about/')) . '">プロフィール</a><a href="' . esc_url(home_url('/hsh-editorial/')) . '">記事・広告について</a></nav>';
});
// Aggregate page requests only: no IP address, cookie, user ID or user agent is saved.
add_action('template_redirect', function () {
    if (is_admin() || is_user_logged_in() || is_feed() || is_preview() || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { return; }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/bot|crawl|spider|preview|headless|uptime|monitor/i', $ua)) { return; }
    if (!is_singular('post') || !get_post_meta(get_queried_object_id(), '_hsh_starter_owned', true)) { return; }
    $slug = get_post_field('post_name', get_queried_object_id());
    $day = wp_date('Y_m_d'); $counts = get_option('hsh_request_counts', array());
    $threshold = wp_date('Y_m_d',time()-89*DAY_IN_SECONDS);
    foreach ($counts as $date => $rows) { if ($date < $threshold || $date > $day) { unset($counts[$date]); } }
    if (!isset($counts[$day])) { $counts[$day] = get_option('hsh_requests_'.$day,array()); delete_option('hsh_requests_'.$day); }
    $counts[$day][$slug] = 1 + (int)($counts[$day][$slug] ?? 0);
    update_option('hsh_request_counts', $counts, false);
});
add_action('rest_api_init', function () {
    register_rest_route('hirogaru/v1','/site-audit',array('methods'=>'GET','permission_callback'=>'hsh_edit_permission','callback'=>'hsh_site_audit'));
});
function hsh_site_audit() {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    global $wp_version; $plugins = array();
    foreach (get_plugins() as $file => $p) { $plugins[] = array('name'=>$p['Name'],'version'=>$p['Version'],'active'=>is_plugin_active($file)); }
    $counts = get_option('hsh_request_counts', array()); $threshold=wp_date('Y_m_d',time()-89*DAY_IN_SECONDS);
    foreach($counts as $date=>$rows){if($date<$threshold){unset($counts[$date]);}} krsort($counts);
    return hsh_edit_response(array('wordpress'=>$wp_version,'php'=>PHP_VERSION,'public_indexing'=>(bool)get_option('blog_public'),'sitemap'=>home_url('/wp-sitemap.xml'),'updater'=>array('direct_filesystem'=>get_filesystem_method()==='direct','zip_archive'=>class_exists('ZipArchive'),'file_mods_allowed'=>!(defined('DISALLOW_FILE_MODS')&&DISALLOW_FILE_MODS),'last_update'=>get_option('hsh_last_plugin_update',null)),'plugins'=>$plugins,'backup'=>array('scope'=>'owned content can be exported using status and starter-review; hosting/database/media restore not verified','hosting_backup_verified'=>false),'search_console_connected'=>false,'measurement'=>array('kind'=>'approximate_uncached_page_requests','limitations'=>'キャッシュ配信・未知のボット・同時更新の影響あり。訪問者数や正確なPVではありません。','daily'=>$counts,'affiliate_links_configured'=>false)));
}
function hsh_verification_value($data) {
    if (!is_array($data) || array_keys($data)!==array('token') || !is_string($data['token']) || !preg_match('/^[A-Za-z0-9_-]{20,200}$/D',$data['token'])) { return new WP_Error('hsh_verification','Googleが発行した確認用トークンだけを指定してください。',array('status'=>400)); }
    return $data['token'];
}
add_action('rest_api_init',function(){register_rest_route('hirogaru/v1','/site-verification',array('methods'=>'POST','permission_callback'=>'hsh_edit_permission','callback'=>function($request){$token=hsh_verification_value($request->get_json_params());if(is_wp_error($token)){return $token;}update_option('hsh_gsc_verification',$token,false);return hsh_edit_response(array('saved'=>true,'verification_completed'=>false));}));});
add_action('wp_head',function(){if(is_front_page()){ $token=get_option('hsh_gsc_verification','');if(is_string($token)&&preg_match('/^[A-Za-z0-9_-]{20,200}$/D',$token)){echo '<meta name="google-site-verification" content="'.esc_attr($token).'">' . "\n";} }});
add_action('admin_menu',function(){add_theme_page('ブログ運用状況','ブログ運用状況','manage_options','hsh-health',function(){
    if(!current_user_can('manage_options')){return;} $audit=hsh_site_audit()->get_data();
    echo '<div class="wrap"><h1>ブログ運用状況</h1><p>検索登録や成約数を、未接続のまま取得済みとは表示しません。</p><h2>検索への公開</h2><p>'.($audit['public_indexing']?'公開設定は有効です。Googleへの登録・掲載は別途確認が必要です。':'検索エンジンへの公開が無効です。設定を確認してください。').'</p><p><a href="'.esc_url($audit['sitemap']).'">サイトマップを見る</a></p><h2>Search Console</h2><p>所有権確認とサイトマップ送信は完了しています（管理者からの報告）。検索データの自動取得はまだ接続していません。</p><h2>バックアップ</h2><p>専用記事には変更前の履歴を最大10件保持します。これはサイト全体のバックアップではありません。ConoHa側のデータベース・メディアを含む復元可否は未確認です。</p><h2>記事への到達件数（概数）</h2><p>ログイン中・既知のボットを除外。キャッシュ配信や同時アクセスの影響があり、正確なPV・訪問者数ではありません。個人識別用CookieやIPはこの集計に保存しません。</p><table class="widefat striped"><thead><tr><th>日付</th><th>猫</th><th>ウイスキー</th><th>IT・フリーランス</th></tr></thead><tbody>';
    foreach($audit['measurement']['daily'] as $day=>$counts){echo '<tr><td>'.esc_html(str_replace('_','-',$day)).'</td>';foreach(array('cat-carrier-checklist','highball-measure-and-adjust','csv-import-seven-checks') as $slug){echo '<td>'.(int)($counts[$slug]??0).'</td>';}echo '</tr>';}
    echo '</tbody></table><p><a href="'.esc_url(admin_url('themes.php?page=hsh-search-review')).'">Search ConsoleのCSVから改善候補を確認する</a></p><h2>収益</h2><p>広告リンクは未登録です。クリック数・成約数はまだ計測していません。提携条件を確認してから掲載します。</p></div>';
});});

/** Custom templates bypass Cocoon header.php, so supply missing SEO basics once. */
function hsh_document_head() {
    ob_start(); wp_head(); $head = ob_get_clean(); echo $head;
    $slug = hsh_current_topic(); $details = hsh_topic_details();
    $description = $slug ? $details[$slug][4] : '保護猫まんもすとの生活やウイスキーの楽しみ方、未経験で始めたITの仕事について書いている個人ブログです。';
    if (!preg_match('/<meta\b[^>]*name=[\'"]description[\'"]/i', $head)) { echo '<meta name="description" content="'.esc_attr($description).'">' . "\n"; }
    if (!preg_match('/<link\b[^>]*rel=[\'"]canonical[\'"]/i', $head)) {
        $url = $slug ? get_pagenum_link(max(1,(int)get_query_var('paged'))) : home_url('/');
        echo '<link rel="canonical" href="'.esc_url($url).'">' . "\n";
    }
    if(function_exists('hsh_blog_extra_head')){
        $url=$slug?get_pagenum_link(max(1,(int)get_query_var('paged'))):home_url('/');
        $title=$slug?hsh_topics()[$slug][0].' | ひろがる趣味暮らし':'ひろがる趣味暮らし｜猫・ウイスキー・ITの仕事';
        echo hsh_blog_extra_head($head,$url,$description,$title);
    }
}
