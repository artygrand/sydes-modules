<?php
/**
* PostOmat - work with simply posts and rss
*
* version 1.8
* autor ArtyGrand
*/

$button = '<a href="?mod=post">Пост-О-мат</a>';

require_once '../system/class/database.php';
$db = new DataBase;

if (!$db -> getTable('posts')){
	$db -> query("CREATE TABLE posts (id INTEGER PRIMARY KEY, stream TEXT, data TEXT, content TEXT, status TEXT, mdate TEXT);");
}

if (empty($_GET['act'])) $_GET['act'] = 'view';
switch ($_GET['act']){
	case 'view':  //отобразить посты(с фильтром)
		$p['content'] = getAllPosts($db);
		$p['style'] = "
			.button{margin-bottom:32px;}
			.data{color:brown; font-weight:bold; width:80%; display:inline-block; margin: 10px 0 0 20px;}
			.post{margin:0 0 10px 20px; border-left:4px solid brown; padding-left:5px;}
			.v0{border-left:4px solid lightgray;}
			#postform{width:800px; z-index:100; background:#faeedd; padding: 30px;border: 10px solid #DDC2A9;}
			#postform p{padding:2px 10px;}
			.title + div{font-size:14px;}
			.spoiler + div > div > p + span{opacity:0.2}
			.spoiler + div > div:hover > p + span{opacity:1}";

		$p['jsfunction'] = "
		/*
		*Edit posts
		*/
		function getPostForm(id){
			$.get('?mod=post&act=get_form&id=' + id, function(data){
				$('#container').html(data).fadeIn('fast')
				$('#shade').fadeIn('fast')
			})
		}
		function post_del(id) {
			$.ajax({
				type: 'POST', url: '?mod=post&act=delete', data: {id: id},
				success: function(msg){
					$('div#p_' + id).remove()
				}
			});
		}
";
		return $p;
		break;
	case 'save':
		savePost($db);
		goToLink('?mod=post');
		break;
	case 'get_form':
		getForm($db);
		goToLink('?mod=post');
		break;
	case 'delete':
		delPost($db);
		break;
	default:
		goToLink('?mod=post', lang('restricted'));
		break;
}

/*ФУНКЦИИ*/

function getAllPosts($db){
	$query = $db -> query("SELECT * FROM posts ORDER BY stream;");
	$listing = '<h2>Списки постов<a onclick="getPostForm(0)" id="settings">Создать новый пост</a></h2>';
	if (!$query){
		$listing .= "<p>Пока пусто</p><div>";
	} else {
		$head = $query[0]['stream'];
		$listing .= '	<div class="title spoiler">' . $head . '</div><div style="display:none;">';
		
		foreach($query as $post){
			$post['content'] = unescape($post['content']);
			if($post['stream'] !== $head){
				$listing .= '</div>' . PHP_EOL . '	<div class="title spoiler">' . $post['stream'] . '</div><div style="display:none;">';
				$head = $post['stream'];
			}
			$vis = $post['status'] == 0 ? ' v0' : $vis = '';

			$listing .= '
			<div id="p_' . $post['id'] . '">
				<p class="data">' . $post['data'] . '</p><span><a onclick="getPostForm(' . $post['id'] . ')">Изменить</a> | <a onclick="post_del(' . $post['id'] . ')">Удалить</a></span>
				<div class="post' . $vis . '">' . $post['content'] . '</div>
			</div>';
		}
	}
	$listing .= "</div>\n";
	return $listing;
}

function savePost($db){
	if ($_POST['data'] and $_POST['content']){
		$data['stream'] = $_POST['stream2'] ? trim($_POST['stream2']) : trim($_POST['stream1']);
		$data['data'] = trim($_POST['data']);
		$data['content'] = trim($_POST['content']);
		if(!$data['stream'] or !$data['content']){
			goToLink('?mod=post', lang('no_value'));
		}
		foreach($data as &$dataItem){
			$dataItem = escape($dataItem);
		}
		$data['status'] = $_POST['status'] == 1 ? 1 : 0;
		
		$id = intval($_GET['id']);
		if($id){//save post
			$save = $db -> query("UPDATE posts SET stream = '{$data['stream']}', data = '{$data['data']}', content = '{$data['content']}', status = '{$data['status']}' WHERE id = '$id'");
		} else {//or insert new
			$save = $db -> query("INSERT INTO posts(stream, data, content, status, mdate) VALUES ('{$data['stream']}', '{$data['data']}', '{$data['content']}', '{$data['status']}', '" . date('r') . "')");
		}
		goToLink('?mod=post', lang('saved'));
	} else {
		goToLink('?mod=post', lang('no_value'));
	}
}

function delPost($db){
	if (isset($_POST['id'])){
		$db -> query("DELETE FROM posts WHERE id = " . (int)$_POST['id']);
	}
}

function getForm($db){
	if ((int)$_GET['id'] != 0){
	$query = $db -> query("SELECT * FROM posts WHERE id = " . (int)$_GET['id']);
	if (!$query) goToLink('?mod=post', lang('no_value'));
	} else {
		$query = array(array('id' => '0', 'stream' => '', 'data' => '', 'content' => '', 'status' => '1', 'mdate' => ''));
	}
	
	$streams = $db -> query("SELECT stream FROM posts GROUP BY stream;");
	$streamList = getSelect($streams, 'stream', $query[0]['stream'], 'stream1');
	$status = $query[0]['status'] == 1 ? ' checked' : '';
	
	$listing = '<form action="?mod=post&act=save&id=' . $query[0]['id'] . '" method="post" id="postform">
			<div id="second-data">
				<button type="submit" class="button full">Сохранить</button>
				<div class="title">Пост виден пользователям?</div><div>
					<input type="checkbox" class="full" name="status" value="1"' . $status . '>
				</div>
				<div class="title">Поток:</div><div>
						' . $streamList . '
					<p>или создайте новый</p>
					<input type="text" name="stream2" class="full">
				</div>
			</div>
			<div id="main-data">
				<div class="title">Красная строка</div><div>
					<input type="text" name="data" value="' . $query[0]['data'] . '" required class="full">
				</div>
				<div class="title">Основной текст</div><div>
					<textarea name="content" class="full" style="height: 150px;" required>' . $query[0]['content'] . '</textarea>
				</div>
			</div>
		</form>
	' . PHP_EOL;
	die('' . $listing);
}
?>