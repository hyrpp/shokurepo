<?php

header('Content-Type: text/html; charset=UTF-8'); //文字化け回避

$user_tb = "user_tb"; // ユーザー情報を記録するテーブル。
$post_tb = "post_tb"; // 投稿内容を記録するテーブル。
$login_url = "http://github.com/hyrpp/shokurepo_login.php";
$post_error = array();
$user_account = $_GET['user_account'];
$display_num = 5; // 一度に表示する投稿の個数

$narrow_down_mode = $_POST['narrow_down_mode']; // 地域検索時は1, 表示件数の制御時に必要になったため導入。
if (empty($narrow_down_mode)){
	// ログイン直後は$narrow_down_modeが空になるので0を代入
	$narrow_down_mode = 0;
}

$display_times = $_POST['display_times']; // 最初の表示では0, 「次のn件を表示」を押すと+1される値。
if (empty($display_times)){
	// ログイン直後は$display_timesが空になるので0を代入
	$display_times = 0;
}

// データベースへの接続
$dsn = 'mysql:dbname=****;host=****';
$user = '****';
$pw = '****';
$pdo = new PDO($dsn, $user, $pw);

// 投稿用テーブルの作成
$sql = "CREATE TABLE ".$post_tb
."("
."post_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,"
."user_name VARCHAR(32),"
."user_account VARCHAR(32),"
."restaurant VARCHAR(32),"
."place VARCHAR(32),"
."cuisine VARCHAR(32),"
."fname TEXT,"
."extension TEXT,"
."raw_data LONGBLOB,"
."comment VARCHAR(200),"
."post_date TEXT"
.");";
$stmt = $pdo -> query($sql);

if ($_POST['post']){
	// 新規に投稿があったとき
	// 投稿内容と時刻を変数に格納
	$restaurant = $_POST['restaurant'];
	$place = $_POST['place'];
	$cuisine = $_POST['cuisine'];
	$comment = $_POST['comment'];
	$post_date = date("Y/n/j G:i"); // 2018/1/1 1:01
	
	$narrow_down_mode = 0; // 地域検索モードをOFFにする
	$display_times = 0; // 投稿直後は最新の投稿を表示するようにする
	
	if (empty($restaurant) or empty($place) or empty($_FILES['cuisine_pic']['name'])){
		// 必須項目に未入力があったとき
		$post_error['empty'] = "店名・地域名・料理画像は必須項目です！";
	} else if (!empty($_FILES['cuisine_pic']['error'])){
		// $_FILEにエラー
		$post_error['pic_error'] = "画像アップロード時にエラーが発生しました。";
	} else {
		// 料理名もしくはコメントが未入力のとき
		if (empty($cuisine)){
			$cuisine = "&emsp;－－&emsp;";
		}
		if (empty($comment)){
			$comment = "&emsp;－－&emsp;";
		}
		
		// アカウント名からユーザー名を特定
		$sql = $pdo -> prepare("SELECT user_name FROM ".$user_tb." WHERE user_account=(:user_account)");
		$sql -> bindValue(':user_account', $user_account, PDO::PARAM_STR);
		$sql -> execute();
		
		$row_count = $sql -> rowCount(); // アカウント名が一致した行の個数(通常、0 or 1 になるはず)
		if ($row_count != 1) {
			// 入力されたアカウント名に対してパスワードが複数 or なし (エラー)
			$post_error['post'] = "アカウントに異常があります。再度新規登録してください。";
		} else {
			// アカウント名に対応するユーザー名が1つのとき(正常)
			$user_name_array = $sql -> fetch();
			$user_name = $user_name_array['user_name'];
		
			// 拡張子を確認
			$tmp = pathinfo($_FILES['cuisine_pic']['name']);
			$extension = $tmp['extension'];
	        if($extension === "jpg" || $extension === "jpeg" || $extension === "JPG" || $extension === "JPEG"){
	            $extension = "jpeg";
	        } else if ($extension === "png" || $extension === "PNG"){
	            $extension = "png";
	        } else {
	        	$post_error['extension'] = "画像ファイルが非対応のファイルです！";
	        }
        }
        
		
		if (count($post_error) === 0){
			// エラーがないとき
			//---画像を縮小(縮小して$_FILES['cuisine_pic']['tmp_name']を更新する)---
			$new_width = 400;
			$image_file = $_FILES['cuisine_pic']['tmp_name'];
			
			list($original_width, $original_height) = getimagesize($image_file); // 元画像のサイズ
			$proportion = $original_width / $original_height; // 元画像の比率
			$new_height = $new_width / $proportion; // 高さを設定
			
			//高さが幅より大きい場合は、高さを幅に合わせ、横幅を縮小
			if($proportion < 1){
			    $new_height = $new_width;
			    $new_width = $new_width * $proportion;
			}
			
			if ($extension === "jpeg") {
			    $original_image = imagecreatefromjpeg($image_file); //JPEGファイルを読み込む
			    $new_image = imagecreatetruecolor($new_width, $new_height); // 画像作成
		    } elseif ($extension === "png") {
				$original_image = imagecreatefrompng($image_file); //PNGファイルを読み込む
				$new_image = imagecreatetruecolor($new_width, $new_height); // 画像作成
				
				/* ----- 透過問題解決 ------ */
				imagealphablending($new_image, false);  // アルファブレンディングをoffにする
				imagesavealpha($new_image, true);       // 完全なアルファチャネル情報を保存するフラグをonにする
			}
			
			// 元画像から再サンプリング
			imagecopyresampled($new_image, $original_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
			
			if ($extension === "jpeg") {
				imagejpeg($new_image, $_FILES['cuisine_pic']['tmp_name']);
			} elseif ($extension === "png") {
				imagepng($new_image, $_FILES['cuisine_pic']['tmp_name']);
			}
			//---画像の縮小end---
			
			
			// 画像をバイナリデータにする
			$raw_data = file_get_contents($_FILES['cuisine_pic']['tmp_name']);
			
			// データベースに保存($post_tb)
			$date = getdate();
            $fname = $_FILES["cuisine_pic"]["tmp_name"].$date["year"].$date["mon"].$date["mday"].$date["hours"].$date["minutes"].$date["seconds"];
            $fname = hash("sha256", $fname);
			
			$sql = $pdo -> prepare("INSERT INTO ".$post_tb." (user_name, user_account, restaurant, place, cuisine, fname, extension, raw_data, comment, post_date) VALUES (:user_name, :user_account, :restaurant, :place, :cuisine, :fname, :extension, :raw_data, :comment, :post_date)");
			$sql -> bindParam(':user_name', $user_name, PDO::PARAM_STR);
			$sql -> bindParam(':user_account', $user_account, PDO::PARAM_STR);
			$sql -> bindParam(':restaurant', $restaurant, PDO::PARAM_STR);
			$sql -> bindParam(':place', $place, PDO::PARAM_STR);
			$sql -> bindParam(':cuisine', $cuisine, PDO::PARAM_STR);
			$sql -> bindParam(':fname', $fname, PDO::PARAM_STR);
			$sql -> bindParam(':extension', $extension, PDO::PARAM_STR);
			$sql -> bindParam(':raw_data', $raw_data, PDO::PARAM_STR);
			$sql -> bindParam(':comment', $comment, PDO::PARAM_STR);
			$sql -> bindParam(':post_date', $post_date, PDO::PARAM_STR);
			$sql -> execute();
		}
	}
}
?>

<html>
<head>
	<!-- 文字化け回避 -->
	<meta http-equiv="content-type" charset="utf-8">
</head>
<body>


<h2>投稿フォーム
&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;
<font size="-1"><a href="<?php echo $login_url ?>">ログアウト</a></font>
</h2>
<form action="<?php __FILE__ ?>" method="post" enctype="multipart/form-data">
	<p>
		&#x1f3e0;店名：<input type="text" name="restaurant">&emsp;&emsp;
		&#x1f5fa;地域・最寄駅：<input type="text" name="place"><br>
		&#x1f374;料理名：<input type="text" name="cuisine"><br>
		&#x1f4f7;料理画像：<input type="file" name="cuisine_pic"><br>
		&#x1f536;コメント：<br><textarea name="comment" cols="40" rows="5"></textarea><br>
		<input type="submit" name="post" value="投稿">
	</p>
</form>
<br>
<h2>地域検索</h2>
<form action="<?php __FILE__ ?>" method="post" enctype="multipart/form-data">
	<p>
		&#x1F50D;検索地域・最寄駅：<input type="text" name="narrow_down_place">
		<input type="submit" name="narrow_down" value="検索">
	</p>
</form>

<?php 
	if (count($post_error) > 0){
		// エラーを画面に表示
		foreach($post_error as $error){
			echo "<p><font color='red'>".$error."</font></p>";
		}
	}
	$narrow_down_place = $_POST['narrow_down_place'];
	if (!empty($narrow_down_place)){
		$tl_str = "&emsp;[ &#x1F50D;".$narrow_down_place." ]";
	}
?>
<br><hr>
<h2>タイムライン<?php echo $tl_str; ?></h2>

<?php
if (!empty($narrow_down_place)){
	// 地域検索ボタン押下時
	$narrow_down_mode = 1; // 地域検索モードをONにする
	$display_times = 0; // 地域検索直後は最新の投稿を表示するようにする
} else if (!empty($_POST['display_first'])){
	// 「最新の投稿を表示」ボタン押下時
	$display_times = 0;
} else if (!empty($_POST['display_previous'])){
	// 「前のn件を表示」ボタン押下時
	$display_times -= 1;
} else if (!empty($_POST['display_next'])){
	// 「次のn件を表示」ボタン押下時
	$display_times += 1;
}

//---------タイムラインの表示-----------
if ($narrow_down_mode){
	// 地域検索時は地域で絞る
	$sql = 'SELECT * FROM '.$post_tb.' WHERE place="'.$narrow_down_place.'" ORDER BY post_id DESC';
	$results = $pdo -> query($sql);
} else {
	// 通常時
	$sql = 'SELECT * FROM '.$post_tb.' ORDER BY post_id DESC';
	$results = $pdo -> query($sql);
}

$n = 0;
foreach ($results as $row){
	$post_id = $row['post_id'];
	$user_name = $row['user_name'];
	$user_account = $row['user_account'];
	$restaurant = $row['restaurant'];
	$place = $row['place'];
	$cuisine = $row['cuisine'];
	$fname = $row['fname'];
	$comment = $row['comment'];
	$post_date = $row['post_date'];
	
	if ($n >= $display_num * $display_times and $n < $display_num * ($display_times + 1)){
		// 表示個数を制限(num*times <= n < num*(times+1))
	?>
		<p>
			<b> <?php echo $user_name; ?> </b>
				   <font color="darkgray">@<?php echo $user_account; ?></font>
				   &emsp;&emsp;&emsp;&emsp;- <?php echo $post_date; ?> -<br>
			&#x1f3e0;<?php echo $restaurant; ?>&emsp;&emsp;(&#x1f5fa;<?php echo $place; ?>)<br>
			&#x1f374;<?php echo $cuisine; ?> <br>
			&#x1f536;<?php echo $comment; ?> <br>
			<?php echo "<img src='shokurepo_import_pic.php?pic_token=".$fname."' alt=''>"; ?> <br>
			<br>
		</p><br>
		<?php
	}
	$n += 1;
}
	
	
?>
<form action="<?php __FILE__ ?>" method="post">
	<input type="submit" name="display_first" value="最新の投稿を表示">
	<input type="submit" name="display_previous" value="前の<?php echo $display_num; ?>件を表示">
	<input type="submit" name="display_next" value="次の<?php echo $display_num; ?>件を表示">
	<input type="hidden" name="display_times" value="<?php echo $display_times; ?>">
	<input type="hidden" name="narrow_down_mode" value="<?php echo $narrow_down_mode; ?>">
</form>

</body>
</html>