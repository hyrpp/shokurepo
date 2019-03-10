<?php

header('Content-Type: text/html; charset=UTF-8'); //文字化け回避

$filehead = basename(__FILE__, ".php");
$pre_user_tb = "pre_user_tb"; // pre_user_tb, メールアドレスとトークンを記録。
$user_tb = "user_tb"; // user_tb, ユーザー情報を記録するテーブル。
$login_url = "http://github.com/hyrpp/".$filehead.".php";
$login_error = array();


// 1 データベースへの接続
$dsn = 'mysql:dbname=****;host=****';
$user = '****';
$pw = '****';
$pdo = new PDO($dsn, $user, $pw);

// ユーザー情報_仮_登録用テーブルの作成
$sql = "CREATE TABLE ".$pre_user_tb
."("
."pre_user_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,"
."mail_address VARCHAR(50),"
."url_token VARCHAR(128)"
.");";
$stmt = $pdo -> query($sql);

// ユーザー情報_本_登録用テーブルの作成
$sql = "CREATE TABLE ".$user_tb
."("
."user_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,"
."mail_address VARCHAR(50),"
."user_name VARCHAR(32),"
."user_account VARCHAR(32),"
."password VARCHAR(32),"
."date DATETIME"
.");";
$stmt = $pdo -> query($sql);


?>

<html>
<head>
<!-- 文字化け回避 -->
<meta http-equiv="content-type" charset="utf-8">
</head>
<body>

<?php
if (!empty($_POST['login'])){
	// ログインボタン押下時
	$login_user_account = $_POST['login_user_account'];
	$login_password = $_POST['login_password'];
	
	if (empty($login_user_account) or empty($login_password)){
		// 入力されていない項目があるとき
		$login_error['empty'] = "アカウント名もしくはパスワードが入力されていません！";
	} else {
		// 入力されたアカウント名が未登録であるとき
		// アカウントから正しいパスワードの取得
		$sql = $pdo -> prepare("SELECT password FROM ".$user_tb." WHERE user_account=(:user_account)");
		$sql -> bindValue(':user_account', $login_user_account, PDO::PARAM_STR);
		$sql -> execute();
		
		$row_count = $sql -> rowCount(); // アカウント名が一致した行の個数(0 or 1 になるはず)
		
		if ($row_count == 0) {
			// 入力されたアカウント名が登録されていないとき
			$login_error['not_registered_account'] = "入力されたアカウントは登録されていません！新規登録してください。";
		} else if ($row_count > 1){
			// 入力されたアカウント名に対してパスワードが複数(エラー)
			$login_error['multiple_password'] = "アカウントにエラーが発生しました。新規登録し直してください。";
		} else {
			// $row_count==1 のとき(入力されたアカウントに対応した正しいパスワードが1つ)
			$password_array = $sql -> fetch();
			$right_password = $password_array['password'];
			if ($login_password != $right_password) {
				$login_error['wrong_password'] = "パスワードが異なります！";
			} else {
				// パスワードが一致しているとき
				// 投稿・タイムライン画面へ
// 				header("location: mission_5_post_tl.php"."?file_num=".$file_num."&sub_num=".$sub_num."&user_account=".$login_user_account);
				header("location: shokurepo_post_tl.php"."?user_account=".$login_user_account);
			}
		}
	}
}

// 新規登録ボタン押下時、新規登録フォーム
if (!empty($_POST['mail_registration_form'])){
?>
	<h2>メールアドレス登録</h2>
	<form action="<?php __FILE__ ?>" method="post">
		メールアドレスをご入力ください。入力されたアドレスにユーザー登録用のURLを送付いたします。
		<p>メールアドレス：<input type="text" name="mail_address" size="40"></p>
		<?php /*<input type="hidden" name="token" value="<?php=$token?>">*/ ?>
		<input type="submit" name="mail_registration" value="メール送信">
	</form>

<?php // 入力されたメールアドレスにメール送信
} else if (!empty($_POST['mail_registration'])){
	$mail_address = $_POST['mail_address'];
	
	// メールアドレスが入力されていないとき
	if (empty($mail_address)){
		echo "メールアドレスが入力されていません！<br>";
		echo '<input type="button" value="戻る" onClick="history.back()">';
	} else {
		// メールアドレスが本登録済みでないか確認
		$used_mail_address_flag = false;
		$sql = 'SELECT mail_address FROM '.$user_tb;
		$results = $pdo -> query($sql);
		$mail_address_array = $results -> fetchAll();
		foreach($mail_address_array as $used_mail_address){
			if ($mail_address == $used_mail_address['mail_address']){
				$used_mail_address_flag = true;
			}
		}
		if ($used_mail_address_flag){
			// メールアドレスが本登録済みのとき(エラー)
			?>
			<font color='red'>そのメールアドレスは既に登録されています！ログインページからログインしてください。</font><br>
			<a href="<?php echo $login_url ?>">ログインページへ</a><br>
			<?php
		} else {
			// メールに添付するURL
			$url_token = hash('sha256', uniqid(rand(),1));
// 			$url = "http://tt-511.99sv-coco.com/mission_5_sign_up.php"."?file_num=".$file_num."&sub_num=".$sub_num."&url_token=".$url_token;
			$url = "http://tt-511.99sv-coco.com/shokurepo_sign_up.php"."?url_token=".$url_token;
			
			// メールの送信項目
			$mail_to = $mail_address;
			$mail_from = "shokujireport@gmail.com";
			$name = "TECH-BASE B班 石田";
			$subject = "[TECH-BASE 石田]新規ユーザー登録用URLのお知らせ";
			$message = "メールアドレスが登録されました。\n下記のURLからユーザー登録を完了してください。\n{$url}";
			
			mb_language("Japanese");
			mb_internal_encoding("UTF-8");
			$header = 'From: ' . mb_encode_mimeheader($name) . ' <' . $mail_from . '>';
			
			// メール送信
			if (mb_send_mail($mail_to, $subject, $message, $header, '-f'. $mail_from)) {
				echo "認証メールを送信しました。<br>URLからユーザー登録を完了してください。<br>";
				echo "<a href=".$login_url.">ログインページへ</a><br>";
				
				// メール送信が成功したらユーザー情報（メールアドレスとトークン）を仮登録
				$sql = $pdo -> prepare("INSERT INTO ".$pre_user_tb." (mail_address, url_token) VALUES (:mail_address, :url_token)");
				$sql -> bindParam(':mail_address', $mail_address, PDO::PARAM_STR);
				$sql -> bindParam(':url_token', $url_token, PDO::PARAM_STR);
				$sql -> execute();
				
			} else {
				// メール送信失敗
				echo "<font color='red'>認証メールの送信に失敗しました。</font><br>";
				echo '<input type="button" value="戻る" onClick="history.back()">';
			}
		}
	}
	

} else {
// ログインフォーム・新規登録ボタン
?>
<h1>＜食レポ掲示板＞</h1>
<form action="<?php __FILE__ ?>" method="post">
	<!-- ログイン -->
	<h2>ログインフォーム</h2>
	<p> 
		アカウント名：@<input type="text" name="login_user_account"><br>
		<?php /*
		もしくは<br>
		<input type="text" name="mail_address" placeholder="メールアドレス"><br><br>
		*/ ?>
		パスワード：<input type="password" name="login_password"><br>
		<input type="submit" name="login" value="ログイン">
	</p>
	
	<!-- 新規登録 -->
	<h2><br>新規ユーザー登録はこちらから</h2>
	<p>
		<input type="submit" name="mail_registration_form" value="新規登録する">
	</p>
</form>
<?php 
	if (count($login_error) > 0){
		foreach($login_error as $error){
			echo "<p><font color='red'>".$error."</font></p>";
		}
	}
	
} ?>

</body>
</html>