<?php
require __DIR__ . '/article-refresh.php';
require_once __DIR__ . '/../hirogaru-home/plugin-update.php';
$spec=array('commit'=>str_repeat('a',40),'sha256'=>str_repeat('b',64),'expected_version'=>'1.4.0');
check(hsh_update_spec($spec)==='https://raw.githubusercontent.com/scudetto0630-dotcom/blog/'.str_repeat('a',40).'/hirogaru-home.zip','immutable repository URL');
foreach(array(array(),array_merge($spec,array('commit'=>'main')),array_merge($spec,array('url'=>'https://evil.test')),array_merge($spec,array('sha256'=>'x'))) as $bad){check(is_wp_error(hsh_update_spec($bad)),'invalid update spec rejected');}
class UpdateArchiveFixture {
    public $numFiles; public $names; public $size; public $symlink;
    function __construct($names,$size=100,$symlink=false){$this->names=$names;$this->numFiles=count($names);$this->size=$size;$this->symlink=$symlink;}
    function statIndex($i){return array('name'=>$this->names[$i],'size'=>$this->size);}
    function getExternalAttributesIndex($i,&$opsys,&$attr){$opsys=3;$attr=($this->symlink?0120777:0100644)<<16;return true;}
}
check(hsh_update_archive(new UpdateArchiveFixture(array('hirogaru-home/hirogaru-home.php','hirogaru-home/assets/home.css'))),'own archive accepted');
foreach(array(array('../x.php'),array('another-plugin/x.php'),array('hirogaru-home/../x.php'),array('hirogaru-home/a.php','hirogaru-home/A.php'),array('hirogaru-home/assets/home.css')) as $names){check(!hsh_update_archive(new UpdateArchiveFixture($names)),'unsafe or incomplete archive rejected');}
check(!hsh_update_archive(new UpdateArchiveFixture(array('hirogaru-home/hirogaru-home.php'),100,true)),'symlink rejected');
check(!hsh_update_archive(new UpdateArchiveFixture(array('hirogaru-home/hirogaru-home.php'),60000000)),'expanded size bounded');
echo "All plugin update validation tests passed. Total tests: $tests\n";
