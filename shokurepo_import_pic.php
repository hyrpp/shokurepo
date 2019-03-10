<?php

$post_tb = "post_tb";
$pic_token = $_GET['pic_token'];

if (empty($pic_token)){
	header("Location: shokurepo_post_tl.php");
} else {
	// 1 データベースへの接続
	$dsn = 'mysql:dbname=****;host=****';
	$user = '****';
	$pw = '****';
	$pdo = new PDO($dsn, $user, $pw);
	
	// fnameを取得、headerを決めてraw_dataをecho
	$sql = "SELECT * FROM ".$post_tb." WHERE fname = :pic_token;";
    $stmt = $pdo->prepare($sql);
    $stmt -> bindValue(":pic_token", $pic_token, PDO::PARAM_STR);
    $stmt -> execute();
    $row = $stmt -> fetch(PDO::FETCH_ASSOC);
    header("Content-Type: image/".$row["extension"]);
    echo ($row["raw_data"]);
}