<?php 
header('Content-Type: text/html; charset=UTF-8');//文字化け回避

$pre_user_tb = "pre_user_tb"; // pre_user_tb, メールアドレスとトークンを記録。
$user_tb = "user_tb"; // user_tb, ユーザー情報を記録するテーブル。
$login_url = "http://github.com/hyrpp/shokurepo_login.php";

$url_token = $_GET['url_token'];

// 1 データベースへの接続
$dsn = 'mysql:dbname=****;host=****';
$user = '****';
$pw = '****';
$pdo = new PDO($dsn, $user, $pw);

// メールアドレスの取得
$sql = $pdo -> prepare("SELECT mail_address FROM ".$pre_user_tb." WHERE url_token=(:url_token)");
$sql -> bindValue(':url_token', $url_token, PDO::PARAM_STR);
$sql -> execute();

$mail_array = $sql -> fetch();
$mail_address = $mail_array['mail_address'];

?>
<html>
<head>
	<!-- 文字化け回避 -->
	<meta http-equiv="content-type" charset="utf-8">
</head>
<body>
<?php
$sign_up_error = array();

if (!empty($_POST['confirmation'])){
	// 登録項目入力後、入力内容確認ボタン押下時
	$user_name = $_POST['user_name'];
	$user_account = $_POST['user_account'];
	$password = $_POST['password'];
	$password_2 = $_POST['password_2'];
	
	// エラーがあるとき
	if (empty($user_name) or empty($user_account) or empty($password) or empty($password_2)){
		$sign_up_error['empty'] = "未入力の項目があります！";
	} else if (!preg_match('/^[0-9a-zA-Z_]{4,30}$/', $user_account)){
		$sign_up_error['account'] = "アカウント名は4~30字の英数字または_で設定してください！";
	} else {
		// メールアドレスが未使用か確認
		$sql = 'SELECT mail_address FROM '.$user_tb;
		$results = $pdo -> query($sql);
		$mail_address_array = $results -> fetchAll();
		foreach($mail_address_array as $used_mail_address){
			if ($mail_address == $used_mail_address['mail_address']){
				$sign_up_error['used_mail_address'] = "既に本登録済みです！ログインページからログインしてください。";
			}
		}
		
		// アカウント名が未使用か確認
		$sql = 'SELECT user_account FROM '.$user_tb;
		$results = $pdo -> query($sql);
		$account_array = $results -> fetchAll();
		foreach($account_array as $used_account){
			if ($user_account == $used_account['user_account']){
				$sign_up_error['used_account'] = "そのアカウント名は既に他のユーザーに使用されています！他のアカウント名でご登録ください。";
			}
		}
		
		// パスワードが適しているか判別
		if (!preg_match('/^[0-9a-zA-Z_]{4,30}$/', $password)){
			$sign_up_error['password'] = "パスワードは4~30字の英数字または_で登録してください！";
		} else if ($password != $password_2) {
			$sign_up_error['wrong_password'] = "パスワードが確認用と一致しません！";
		}
	}
}

if (!empty($_POST['confirmation']) and count($sign_up_error) === 0){
	// 登録項目入力内容に問題がないとき、入力内容確認画面を表示
	$asterisk_password = str_repeat('*', strlen($password));
	?>
	<h2>入力内容確認</h2>
	メールアドレス：<?php echo $mail_address; ?><br>
	ユーザー名：<?php echo $user_name; ?><br>
	アカウント名：@<?php echo $user_account; ?><br>
	パスワード：<?php echo $asterisk_password; ?><br>
	<form action="<?php __FILE__ ?>" method="post">
		<input type="button" value="戻る" onClick="history.back()">
		<input type="submit" name="sign_up" value="新規登録">
		
		<input type="hidden" name="user_name" value="<?php echo $user_name; ?>">
		<input type="hidden" name="user_account" value="<?php echo $user_account; ?>">
		<input type="hidden" name="password" value="<?php echo $password; ?>">
	</form>
	<?php
	
} else if (!empty($_POST['sign_up'])){
	// 入力内容確認後、本登録完了
	?>
	<h2>登録完了</h2>
	ユーザー登録を完了しました！<br>
	<a href="<?php echo $login_url ?>">ログインページへ</a><br>
	<?php
	// 新規登録ボタンを押したときに$user_nameなどの変数の中身が消えてしまうので再定義する
	$user_name = $_POST['user_name'];
	$user_account= $_POST['user_account'];
	$password = $_POST['password'];
	
	// ユーザー情報を保存（本登録）
	$sql = $pdo -> prepare("INSERT INTO ".$user_tb." (mail_address, user_name, user_account, password) VALUES (:mail_address, :user_name, :user_account, :password)");
	$sql -> bindParam(':mail_address', $mail_address, PDO::PARAM_STR);
	$sql -> bindParam(':user_name', $user_name, PDO::PARAM_STR);
	$sql -> bindParam(':user_account', $user_account, PDO::PARAM_STR);
	$sql -> bindParam(':password', $password, PDO::PARAM_STR);
	$sql -> execute();

	
} else {
	// 通常時 or 入力項目のエラー発生時
?>
	<h2>ユーザー登録</h2>
	<form action="<?php __FILE__ ?>" method="post">
		<p> <!-- 新規登録 -->
			ユーザー名：<input type="text" name="user_name" value="<?php echo $user_name ?>"><br>
			アカウント名：@<input type="text" name="user_account" value="<?php echo $user_account ?>">(英数字および_が使用可能です)<br><br>
			パスワード：<input type="password" name="password"><br>
			パスワード（確認用）：<input type="password" name="password_2"><br><br>
			<input type="submit" name="confirmation" value="入力内容確認">
		</p>
	</form>
	
	<a href="<?php echo $login_url ?>">ログインページへ</a><br>
	<?php /* //もしボタンにするなら...
	<form action="misson_5_02.php" method="post">
		<input type="submit" name="" value="ログインページへ">
	</form>
	*/ ?>
<?php
	// エラーがあるときフォームの下に警告を表示
	if (count($sign_up_error) > 0){
		foreach ($sign_up_error as $error){
			echo "<p><font color='red'>".$error."</font></p>";
		}
	}
}
?>
</body>
</html>