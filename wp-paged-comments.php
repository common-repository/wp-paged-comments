<?php 
/*
Plugin Name: WP Paged Comments
Plugin URI: http://blog.2i2j.com/plugins/wp-paged-comments
Description: Breaks down comments into a number of pages 
Author: 偶爱偶家
Contributors: none
Version: 1.3.5
Author URI: http://blog.2i2j.com/
Donate link:  http://blog.2i2j.com/redir/donate-wppagedcomments
*/

/*
ChangeLog:

2009-01-07
			1.3.5 发布

2008-01-06
			1. 增加$_GET['wpccount'], $_GET['wpcper']等参数, 现在可以完整的应用在comments中了

2009-01-05
			1. 增加$_GET['wpcord']参数, 使得该值可被外部使用, 用于表现评论的排序.

2008-12-25
			1.3.4 发布

2008-08-27
			1. 增加显示所有评论时评论排序方式.

2008-08-22
			1. 增加$_GET['wpc']赋值, 使该值可被外部使用.

2008-08-21
			1. 增加default page, 并且唯一化url
			2. 增加发表评论后直接转到对应的page.

2008-07-01
			1.3.3 发布

2008-06-26
			增加手动定位导航栏的功能, 直接在主题中输入<div id="wp-paged-comments-1" class="wp-paged-comments" style="display:none"></div>, 如果要增加, 增加id的数字即可.

2008-05-28
			1.3.2 发布

2008-05-21
			1. 修改filter的优先级, 兼容wp thread comment

2008-05-16
			1. 修正strpos的参数位置bug
			2. 增加wpcflag参数, 兼容wp thread comment跳转

2008-05-14
			1. 修改js, 将div显示至评论的ol之前.

2008-04-21
			1. 增加all链接, 显示所有评论.

2008-04-19
			1. 重写Domready(tmd抄错了)

2008-04-16
			1. 修正page导航中的first错误, 修正page=1和或者pre=1时的url不唯一性.
			2. 增加评论之前的分页导航, 方便使用.
			3. 将原先的挂载到wp_footer的函数直接挂载到domready中, 减少对主题的依赖.

2008-02-22
			1. 重新定位seesion_start位置, 防止session出错.
			2. 修改url, 适应没有进行seo的博客.
			3. 修改$leftpage $rightpage等参数, 全部强制为int, 修正在currentpage为最后一页时出错.

2007-11-28
			1. 解决在session.auto_start=off的情况下, 无法显示分页的问题;
			2. 修改add_footer, 只在需要的文章中load js, 从而防止js出错.

*/

if(!class_exists('wp_paged_comments')):
@session_start();
class wp_paged_comments{

	var $info = '';
	var $status = '';
	var $message = '';
	var $options = '';
	var $default_options = '';
	var $db_options = 'wppagedcomments';
	var $donate_link = 'http://blog.2i2j.com/redir/donate-wppagedcomments';

	function init_info(){
		$path = basename(str_replace('\\','/',dirname(__FILE__)));
		$info['file'] = basename(__FILE__);
		$info['siteurl'] = get_option('siteurl');
		$info['url'] = $info['siteurl'] . '/wp-content/plugins';
		$info['dir'] = 'wp-content/plugins';
		$info['path'] = '';
		if ( $path != 'plugins' ) {
			$info['url'] .= '/' . $path;
			$info['dir'] .= '/' . $path;
			$info['path'].= $path;
		}
		$this->info = array(
			'siteurl' 			=> $info['siteurl'],
			'url'		=> $info['url'],
			'dir'		=> $info['dir'],
			'path'		=> $info['path'],
			'file'		=> $info['file']
		);
		unset($info);
	}
	function init_textdomain() {
		load_plugin_textdomain('wp-paged-comments',$this->info['dir']);
	}

	function deactivate_me(){
		if((string) $this->options['clean_option'] === 'yes')
			delete_option($this->db_options);
		delete_option('wpcflag');
		return true;
	}

	function resetToDefaultOptions() {
		update_option($this->db_options, $this->default_options);
		$this->options = $this->default_options;
	}

	function init_options(){
		update_option('wpcflag','true');

		$defaultopt = array(
			'paged_posts' => 'none',
			'exclude_posts' => '',
			'comments_per_page' => 20,
			'comments_orderby' => 'ASC',
			'exclude_orderby' => '',
			'page_css' =>
'.wp-paged-comments {
	padding: 0px 0px 10px;
}
.wp-paged-comments a, #wp-paged-comments a:link, #wp-paged-comments a:active {
	padding: 2px 4px 2px 4px; 
	margin: 2px;
	text-decoration: none;
	border: 1px solid #0066cc;
	color: #0066cc;
	background-color: #FFFFFF;	
}
.wp-paged-comments a:hover {	
	border: 1px solid #114477;
	color: #114477;
	background-color: #FFFFFF;
}
.wp-paged-comments span.pages {
	padding: 2px 4px 2px 4px; 
	margin: 2px 2px 2px 2px;
	color: #000000;
	border: 1px solid #000000;
	background-color: #FFFFFF;
}
.wp-paged-comments span.current {
	padding: 2px 4px 2px 4px; 
	margin: 2px;
	font-weight: bold;
	border: 1px solid #000000;
	color: #000000;
	background-color: #FFFFFF;
}
.wp-paged-comments span.extend {
	padding: 2px 4px 2px 4px; 
	margin: 2px;	
	border: 1px solid #000000;
	color: #000000;
	background-color: #FFFFFF;
}',
			'maxpages' => 5,
			'clean_option' => '',
			'comments_defaultpage' => 'auto',
			'comments_showall_ordering' => 'auto'
		);

		// Set class property for default options
		$this->default_options = $defaultopt;

		// Get options from WP options
		$optionsFromTable = get_option( $this->db_options );
		if ( !$optionsFromTable ) {
			$this->resetToDefaultOptions();
		}

		// Update default options by getting not empty values from options table
		foreach( (array) $defaultopt as $def_optname => $def_optval ) {
			if(isset($optionsFromTable[$def_optname])){
				$defaultopt[$def_optname] = $optionsFromTable[$def_optname];
			}
		}

		// Set the class property and unset no used variable
		$this->options = $defaultopt;
		unset($defaultopt);
		unset($optionsFromTable);
	}

	function wp_paged_comments(){
		$this->init_info();
		$this->init_options();
		//$this->resetToDefaultOptions();

		//add hook
		add_action('deactivate_'.$this->info['path'].'/'.$this->info['file'], array($this,'deactivate_me'));
		add_action('init', array(&$this, 'init_textdomain'));
		add_action('admin_menu', array(&$this,'wp_paged_comments_admin'));
		add_filter('comments_array',array(&$this,'wp_paged_comments_page'),9999);	//此处优先级为兼容wp thread comment, 必须低于wp thread comment的优先级
		add_action('wp_head',array(&$this,'wp_paged_comments_head'),9999);
		add_filter('comment_post_redirect', array(&$this,'commentpostredirectcompatable'),9999,2);
	}

	function commentpostredirectcompatable($location,$c){
		$t = explode('#', $location);
		if(strpos($t[0], '?') !== FALSE){
			$t[0] .= '&wpc=dlc'; //dlc用于发表评论时直接引导到最后发表的评论
		}else{
			$t[0] .= '?wpc=dlc';
		}

		$location = $t[0] . '#' . $t[1];
		unset($t);
		return $location;
	}

	function wp_paged_comments_page($comments){
		global $post;

		$currentpage = 0;
		//$orderby = 'ASC';
		$_GET['wpcord'] = 1;
		$_GET['wpccount'] = count($comments);
		$_GET['wpcper'] = 0;

		if(strtolower(trim($_GET['wpc'])) === 'all'){
			if(strtolower($this->options['comments_showall_ordering']) === 'asc'){
				$_GET['wpc'] = 1;
				return $comments;
			}elseif(strtolower($this->options['comments_showall_ordering']) === 'desc'){
				$_GET['wpc'] = 1;
				$_GET['wpcord'] = -1;
				return array_reverse($comments);
			}
		}
		
		$exclude_posts = array();
		if(isset($this->options['exclude_posts']) && !empty($this->options['exclude_posts'])){
			$exclude_posts = str_replace(' ','',$this->options['exclude_posts']);
			$exclude_posts = explode(',',$exclude_posts);
		}

		if((strtoupper($this->options['paged_posts']) == 'NONE' && !in_array($post->ID,$exclude_posts)) || (strtoupper($this->options['paged_posts']) == 'ALL' && in_array($post->ID,$exclude_posts))){
			$_GET['wpc'] = 1;
			return $comments;
		}

		$exclude_orderby = array();
		if(isset($this->options['exclude_orderby']) && !empty($this->options['exclude_orderby'])){
			$exclude_orderby = str_replace(' ','',$this->options['exclude_orderby']);
			$exclude_orderby = explode(',',$exclude_orderby);
		}

		if((strtoupper($this->options['comments_orderby']) == 'DESC' && !in_array($post->ID,$exclude_orderby)) || (strtoupper($this->options['comments_orderby']) == 'ASC' && in_array($post->ID,$exclude_orderby))){
			$comments = array_reverse($comments);
			$_GET['wpcord'] = -1;
			if($_GET['wpc'] === 'dlc')
				$currentpage = 1;
		}

		if(strtolower(trim($_GET['wpc'])) === 'all'){
			$_GET['wpc'] = 1;
			return $comments;
		}

		unset($exclude_posts,$exclude_orderby);
		
		if(empty($this->options['comments_per_page']) || $this->options['comments_per_page'] == 0)
			return $comments;

		$perpage = $this->options['comments_per_page'];

		if(count($comments) > $perpage){
			$url = $baseurl = get_permalink();
			$url .= strpos($url,'?') ? '&' : '?';
			
			$pagesnum = (int) ceil(count($comments)/$perpage);
			if(0 == $currentpage && $_GET['wpc'] === 'dlc')
				$currentpage = $pagesnum;

			$defaultpage = $this->options['comments_defaultpage'] === 'last' ? $pagesnum : 1;
			
			if(0 == $currentpage)
				$currentpage = (int) $_GET['wpc'] ? (int) $_GET['wpc'] : $defaultpage;
			$_GET['wpc'] = $currentpage; //重新将$currentpage赋值给wpc, 使得wpc可以用于外部.
			$_GET['wpcper'] = $perpage;

			$maxpages = $this->options['maxpages']-1;
			$leftpage = (int) ($currentpage - intval($maxpages/2)) > 0 ? $currentpage - intval($maxpages/2) : 1;
			$rightpage = (int) ($leftpage + $maxpages) < $pagesnum ? $leftpage + $maxpages : $pagesnum;
			$leftpage = (int) ($rightpage - $maxpages) > 0 ? $rightpage - $maxpages : 1;
			
			$pagestring = '<span class="pages">Page '.$currentpage.' of '.$pagesnum.'</span>';
			
			if($currentpage > 1){
				$pagestring .= '<a title="Page First" href="'.($defaultpage === 1 ? $baseurl : $url.'wpc=1').'#comments">&laquo; First</a><span class="extend">...</span>';
			}

			if($currentpage != $leftpage){
				if($currentpage === 2)
					$pagestring .= '<a title="prev comments" href="'.($defaultpage === 1 ? $baseurl : $url.'wpc=1').'#comments">&laquo;</a>';
				else
					$pagestring .= '<a title="prev comments" href="'.$url.'wpc='.($currentpage-1).'#comments">&laquo;</a>';
			}
			
			for($i=$leftpage; $i<=$rightpage; $i++){
				if($currentpage === $i){
					$pagestring .= '<span class="current">'.$i.'</span>';
				}else{
					if($i === 1)
						$pagestring .= '<a title="Page '.$i.'" href="'.($defaultpage === 1 ? $baseurl : $url.'wpc=1').'#comments">'.$i.'</a>';
					elseif($defaultpage === $i)
						$pagestring .= '<a title="Page '.$i.'" href="'.$baseurl.'#comments">'.$i.'</a>';
					else
						$pagestring .= '<a title="Page '.$i.'" href="'.$url.'wpc='.$i.'#comments">'.$i.'</a>';
				}
			}
			
			if($currentpage != $rightpage){
				$pagestring .= '<a href="'.($defaultpage === ($currentpage+1) ? $baseurl : $url.'wpc='.($currentpage+1)).'#comments" title="next comments">&raquo;</a>';
			}
			
			if($currentpage < $pagesnum){
				$pagestring .= '<span class="extend">...</span><a title="Page Last" href="'.($defaultpage === $pagesnum ? $baseurl : $url.'wpc='.$pagesnum).'#comments">Last &raquo;</a>';
			}

			$pagestring .= '<a title="Show All" href="'.$url.'wpc=all#comments">All</a>';

			$_SESSION['innerhtml'] = $pagestring;
			$comments = array_slice($comments,($currentpage-1)*$perpage,$perpage);
			$_SESSION['lastcommentid'] =  $comments[count($comments)-1]->comment_ID;
			$_SESSION['firstcommentid'] =  $comments[0]->comment_ID;
			echo '<script type="text/javascript" src="'.$this->info['url'].'/'.$this->info['file'].'?js=wp-paged-comments"></script>';
		}
		return $comments;
	}

	function wp_paged_comments_head(){
		echo '<style type="text/css"><!--' . $this->options['page_css'] . '--></style>';
	}

	function wp_paged_comments_admin(){
		add_options_page(__('WP Paged Comments Option','wp-paged-comments'), __('WP Paged Comments','wp-paged-comments'), 5, __FILE__, array(&$this,'wp_paged_comments_options_page'));
	}

	function displayMessage(){
		if ( $this->message != '') {
			$message = $this->message;
			$status = $this->status;
			$this->message = $this->status = ''; // Reset
		}

		if ($message){
?>
			<div id="message" class="<?php echo ($status != '') ? $status :'updated'; ?> fade">
				<p><strong><?php echo $message; ?></strong></p>
			</div>
<?php	
		}
		unset($message,$status);
	}

	function wp_paged_comments_options_page(){

		if ( isset($_POST['updateoptions']) ) {
			foreach((array) $this->options as $key => $oldvalue) {
				$this->options[$key] = ( isset($_POST[$key]) && !empty($_POST[$key]) ) ? stripslashes($_POST[$key]) : $this->default_options[$key];
			}
			update_option($this->db_options, $this->options);
			$this->message = __('Options saved','wp-paged-comments');
			$this->status = 'updated';
		} elseif ( isset($_POST['reset_options']) ){
			update_option( $this->db_options, $this->default_options );
			$this->options = $this->default_options;
			$this->message = __('resetted to default options!','wp-paged-comments');
			$this->status = '';
		}
		$this->displayMessage();
?>

<div class="updated">
	<strong><p><?php echo sprintf(__('Thanks for using this plugin! If it works and you are satisfied with the results, isn\'t it worth at least one dollar? <a href="%s" target="_blank">Donations</a> help me to continue support and development of this <i>free</i> software! <a href="%s" target="_blank">Sure, no problem!</a>','wp-paged-comments'), $this->donate_link, $this->donate_link); ?></p></strong>
	<div style="clear:right;"></div>
</div>

<div class="wrap">
	<h2>WP Paged Comments</h2>
	<form method="post" action="">
		<fieldset name="wp_basic_options"  class="options">
		<p>
			<strong><?php _e('Edit Posts of Paged Comments','wp-paged-comments'); ?></strong>
			<br /><br />
			<label>Posts Paged Comments:</label>
			<?php echo '<select name="paged_posts" id="paged_posts"><option value="ALL" ' .((strtoupper($this->options['paged_posts']) == 'ALL') ? 'selected="selected"' : '') .' >ALL</option><option value="NONE" ' .((strtoupper($this->options['paged_posts']) == 'NONE') ? 'selected="selected"' : '') .' >NONE</option></select>'; ?>
			<br />
			<small><?php _e('Select Posts of Paged Comments','wp-paged-comments'); ?></small>
			<br />
			<label>Exclude Posts Paged Comments:</label>
			<br />
			<input type="text" name="exclude_posts" id="exclude_posts" value="<?php echo $this->options['exclude_posts']; ?>" size="80" />
			<br />
			<small><?php _e('Edit Exclude Posts ID  Paged Comments(separate by comma)','wp-paged-comments'); ?></small>
		</p>
		<p>
			<strong><?php _e('Edit Comments Orderby','wp-paged-comments'); ?></strong>
			<br /><br />
			<label>Comments Orderby:</label>
			<?php echo '<select name="comments_orderby" id="comments_orderby"><option value="ASC" ' .((strtoupper($this->options['comments_orderby']) == 'ASC') ? 'selected="selected"' : '') .' >ASC</option><option value="DESC" ' .((strtoupper($this->options['comments_orderby']) == 'DESC') ? 'selected="selected"' : '') .' >DESC</option></select>'; ?>
			<br />
			<small><?php _e('Select orderby of comments','wp-paged-comments'); ?></small>
			<br />
			<label>Exclude Orderby Posts:</label>
			<br />
			<input type="text" name="exclude_orderby" id="exclude_orderby" value="<?php echo $this->options['exclude_orderby']; ?>" size="80" />
			<br />
			<small><?php _e('Edit Exclude Posts ID Comments Orderby(separate by comma)','wp-paged-comments'); ?></small>
		</p>
		<p>
			<strong><?php _e('Edit Comments Default Page','wp-paged-comments'); ?></strong>
			<br /><br />
			<label>Comments Default Page:</label>
			<?php echo '<select name="comments_defaultpage" id="comments_defaultpage"><option value="auto" ' .((strtolower($this->options['comments_defaultpage']) == 'auto') ? 'selected="selected"' : '') .' >auto</option><option value="first" ' .((strtolower($this->options['comments_defaultpage']) == 'first') ? 'selected="selected"' : '') .' >first</option><option value="last" ' .((strtolower($this->options['comments_defaultpage']) == 'last') ? 'selected="selected"' : '') .' >last</option></select>'; ?>
			<br />
			<small><?php _e('The default page is either "first" (page 1), "last", or "auto" (determined by the comment ordering value). When comment ordering is set to ascending (ASC) the default comment page loaded is page 1 (showing the earliest comments). When comment ordering is set to descending (DESC) the default comment page is the last page (showing the latest comments). To override this behaviour set the value below to either "first" or "last".','wp-paged-comments'); ?></small>
		</p>
		<p>
			<strong><?php _e('Edit Comments Show all Ordering','wp-paged-comments'); ?></strong>
			<br /><br />
			<label>Comments Show all Ordering:</label>
			<?php echo '<select name="comments_showall_ordering" id="comments_showall_ordering"><option value="auto" ' .((strtolower($this->options['comments_showall_ordering']) == 'auto') ? 'selected="selected"' : '') .' >AUTO</option><option value="asc" ' .((strtolower($this->options['comments_showall_ordering']) == 'asc') ? 'selected="selected"' : '') .' >ASC</option><option value="desc" ' .((strtolower($this->options['comments_showall_ordering']) == 'desc') ? 'selected="selected"' : '') .' >DESC</option></select>'; ?>
			<br />
			<small><?php _e('Determines how comments are ordered when loaded on a single page. The default ascending ("ASC") order means comments will be ordered with the earliest comment displayed first. "DESC" reverses this order.','wp-paged-comments'); ?></small>
		</p>
		<p>
			<strong><?php _e('Edit Comments per Page','wp-paged-comments'); ?></strong>
			<br /><br />
			<label>Comments per Page:</label>
			<input type="text" name="comments_per_page" id="comments_per_page" value="<?php echo $this->options['comments_per_page']; ?>" size="3" />
			<br />
			<small><?php _e('Input number of comments per page(leave empty or input zero to show all comments)','wp-paged-comments'); ?></small>
		</p>
		<p>
			<strong><?php _e('Edit Max Pages to Show','wp-paged-comments'); ?></strong>
			<br /><br />
			<label>Max Pages to Show:</label>
			<input type="text" name="maxpages" id="maxpages" value="<?php echo $this->options['maxpages']; ?>" size="3" />
			<br />
			<small><?php _e('Input number of max pages to show','wp-paged-comments'); ?></small>
		</p>
		<p>
			<strong><?php _e('Edit Page CSS','wp-paged-comments'); ?></strong>
			<br /><br />
			<textarea style="font-size: 90%" name="page_css" id="page_css" cols="100%" rows="15" ><?php echo htmlspecialchars(stripslashes($this->options['page_css'])); ?></textarea>
			<br />
			<small><?php _e('Use CSS only, HTML and PHP cannot be used.','wp-paged-comments'); ?></small>
		</p>
		<p>
			<strong><?php _e('Configuration deal with deactivate','wp-paged-comments'); ?></strong>
			<br /><br />
			<label><?php _e('Clean Configuration after deactivate:','wp-paged-comments'); ?></label>
			<input type="checkbox" name="clean_option" id="clean_option" value="yes" <?php if ($this->options['clean_option'] == 'yes') { ?> checked="checked"<?php } ?>/>
			<br />
			<small><?php _e('check box if you want to clean configuration after deactivate wp-paged-comments','wp-paged-comments'); ?></small>
		</p>
		<p class="submit">
			<input type="submit" name="updateoptions" value="<?php _e('Update Options','wp-paged-comments'); ?> &raquo;" />
			<input type="submit" name="reset_options" onclick="return confirm('<?php _e('Do you really want to restore the default options?','wp-paged-comments'); ?>');" value="<?php _e('Reset Options','wp-paged-comments'); ?>" />
		</p>
		</fieldset>
	</form>
</div>
<?php
	}
}
endif;


if($_GET['js'] == 'wp-paged-comments'){
	header("Content-Type:text/javascript");
?>

function DOMReady(func){
	// Dean Edwards/Matthias Miller/John Resig
	function init(){
		// quit if this function has already been called
		if(arguments.callee.done) return;

		// flag this function so we don't do the same thing twice
		arguments.callee.done = true;

		// kill the timer
		if(_timer) clearInterval(_timer);

		// do stuff
		return func();
	};

	/* for Mozilla/Opera9 */
	if(document.addEventListener){
		document.addEventListener("DOMContentLoaded", init, false);
	}

	/* for Internet Explorer */
	/*@cc_on @*/
	/*@if(@_win32)
		document.write("<script id=__ie_onload defer src=javascript:void(0)><\/script>");
		var script = document.getElementById("__ie_onload");
		script.onreadystatechange = function(){
			if(this.readyState == "complete"){
				init(); // call the onload handler
			}
		};
	/*@end @*/

	/* for Safari */
	if(/WebKit/i.test(navigator.userAgent)){ // sniff
		var _timer = setInterval(function(){
			if(/loaded|complete/.test(document.readyState)){
				init(); // call the onload handler
			}
		}, 10);
	}

	/* for other browsers */
	window.onload = init;
}

function insertAfter(newElement, targetElement){
	var parent = targetElement.parentNode;
	if(parent.lastChild == targetElement){
		parent.appendChild(newElement);
	}else{
		parent.insertBefore(newElement, targetElement.nextSibling);
	}
}
function creatwpcpd(){

	var innerHTML = '<?php echo $_SESSION['innerhtml']; ?>';

	var wpcpd = null, flag = false;
	
	for(var i=1; i<3; i++){
		wpcpd = document.getElementById('wp-paged-comments-'+i);
		if(wpcpd != null){
			wpcpd.innerHTML = innerHTML;
			wpcpd.style.display = 'block';
			flag = true;
		}
	}

	if(flag == true)
		return true;

	var wpcpdli = document.getElementById('comment-<?php echo $_SESSION['lastcommentid']; ?>');
	if(wpcpdli == null){
		return false;
	}
	var wpcpdliparent = wpcpdli.parentNode;
	var wpcpdlst = document.createElement("div");
	if(wpcpdlst == null){
		return false;
	}

	wpcpdlst.id = "wp-paged-comments-2";
	wpcpdlst.className = "wp-paged-comments";
	wpcpdlst.innerHTML = innerHTML;

	if(wpcpdliparent.lastChild == wpcpdli || (wpcpdliparent.lastChild.nodeName == "#text" && wpcpdliparent.lastChild.previousSibling == wpcpdli)){
		insertAfter(wpcpdlst,wpcpdliparent);
	}else{
		insertAfter(wpcpdlst,wpcpdli);
	}

	wpcpdli = document.getElementById('comment-<?php echo $_SESSION['firstcommentid']; ?>');
	if(wpcpdli == null){
		return false;
	}
	var wpcpdliparent = wpcpdli.parentNode;
	var wpcpdfst = wpcpdlst.cloneNode(true)
	if(wpcpdfst == null){
		return false;
	}

	wpcpdfst.id = "wp-paged-comments-1";

	if(wpcpdliparent.firstChild == wpcpdli || (wpcpdliparent.firstChild.nodeName == "#text" && wpcpdliparent.firstChild.nextSibling == wpcpdli)){
		wpcpdliparent.parentNode.insertBefore(wpcpdfst,wpcpdliparent);
	}else{
		wpcpdliparent.insertBefore(wpcpdfst,wpcpdli);
	}

	return true;
}
DOMReady(creatwpcpd);

<?php
	unset($_SESSION['firstcommentid'], $_SESSION['lastcommentid'], $_SESSION['innerhtml']);
	die();exit();
}else{
	$new_wp_paged_comments = new wp_paged_comments();
}

?>