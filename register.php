<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db.php';
$msg = '';
$msgColor = 'green';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $family_code = trim($_POST['family_code']);
    $name = trim($_POST['name']);
    $password = $_POST['password'];
    $is_new = !empty($_POST['new_family']);

    // familiesテーブル存在チェック
    $stmt = $pdo->prepare("SELECT id FROM families WHERE family_code = :code");
    $stmt->execute(['code' => $family_code]);
    $family = $stmt->fetch();

    if ($is_new) {
        if ($family) {
            $msg = 'その家族コードは既に使われています。別のコードで作成してください。';
            $msgColor = 'red';
        } else {
            // 家族新規作成
            $stmt2 = $pdo->prepare("INSERT INTO families (family_code) VALUES (:code)");
            $stmt2->execute(['code' => $family_code]);
            // 新しいfamilyのidを取得
            $stmt = $pdo->prepare("SELECT id FROM families WHERE family_code = :code");
            $stmt->execute(['code' => $family_code]);
            $family = $stmt->fetch();
        }
    }

    if (!$is_new && !$family) {
        $msg = '家族コードが存在しません。';
        $msgColor = 'red';
    }

    if ($family && (($is_new && empty($msg)) || !$is_new)) {
        // family_codeとname両方でusersで一意となるよう、username = "{family_code}_{name}" で登録する
        $unique_username = $family_code . '_' . $name;

        // 同じユーザー名がいないか確認
        $stmt3 = $pdo->prepare("SELECT id FROM users WHERE username = :username");
        $stmt3->execute(['username'=>$unique_username]);
        if ($stmt3->fetch()) {
            $msg = '同じユーザー名が既に登録されています(その家族コードですでに同じ名前が使われています)。';
            $msgColor = 'red';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt4 = $pdo->prepare("INSERT INTO users (username, password) VALUES (:username, :pw)");
            $stmt4->execute([
                'username' => $unique_username,
                'pw' => $hash
            ]);
            $msg = "登録しました。<a href='login.php'>ログイン</a>";
            $msgColor = 'green';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>新規登録 - 家事管理アプリ</title>
    <!-- Google Fonts / Font Awesomeでアイコンを利用可能に -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:700,400&display=swap" rel="stylesheet">
    <style>
        body {
            background: #1a1a1a;
            color: #f5f5f5;
            font-family: 'Roboto Slab', Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        header {
            background: #222831;
            padding: 20px 0;
            text-align: center;
        }
        header h1 {
            margin: 0;
            font-size: 2.2rem;
            letter-spacing: 2px;
            font-family: 'Roboto Slab', Arial, sans-serif;
            color: #00adb5;
            text-shadow: 1px 2px 4px #000;
        }
        h2 {
            margin-top: 32px;
            text-align: center;
            color: #00adb5;
        }
        form {
            background: #222831;
            max-width: 380px;
            margin: 32px auto;
            padding: 32px 32px 20px 32px;
            border-radius: 12px;
            box-shadow: 0 4px 18px #000a;
        }
        label {
            display: block;
            margin: 20px 0 10px 0;
            font-size: 1.07rem;
            color: #e4e4e4;
            letter-spacing: 1px;
        }
        input[type="text"], input[type="password"] {
            width: 94%;
            display: block;
            padding: 10px 8px;
            border: 1.5px solid #444;
            border-radius: 6px;
            background: #393e46;
            color: #f5f5f5;
            font-size: 1rem;
            margin-top: 4px;
        }
        input[type="checkbox"] {
            transform: scale(1.25);
            margin-right: 7px;
        }
        button {
            background: #00adb5;
            color: #212121;
            font-size: 1.08rem;
            border: none;
            padding: 11px 24px;
            margin: 17px auto 2px auto;
            display: block;
            border-radius: 7px;
            box-shadow: 0 2px 6px #0006;
            cursor: pointer;
            font-weight: bold;
            letter-spacing: 2px;
            transition: background 0.2s;
        }
        button:hover {
            background: #008891;
        }
        .msg {
            margin: 18px auto 8px auto;
            font-weight: bold;
            font-size: 1.05rem;
            padding: 9px 12px;
            border-radius: 6px;
            max-width: 380px;
            box-shadow: 0 2px 12px #0003;
        }
        .msg.green {background: #132c13;color: #aaffaa;}
        .msg.red {background: #441818;color: #ffaaaa;}
        .form-icon {
            margin-right: 8px;
            color: #00adb5;
            font-size: 1.2em;
            vertical-align: middle;
        }
        .back-link {
            display: block;
            text-align: center;
            color: #ddddff;
            margin: 38px auto 0 auto;
            font-size: 1.04rem;
            text-decoration: none;
        }
        .back-link:hover { color:#00adb5;}
    </style>
</head>
<body>
    <header>
        <h1><i class="fa-solid fa-person-digging"></i> 家事管理アプリ</h1>
    </header>
    <h2>「新規登録登録」フォーム</h2>

    <?php if ($msg): ?>
        <div class="msg <?= $msgColor ?>"><?= $msg ?></div>
    <?php endif; ?>

    <form action="register.php" method="POST" autocomplete="off">
        <label>
            <i class="fa-solid fa-users form-icon"></i>
            家族コード:
            <input name="family_code" required type="text" maxlength="10" placeholder="例：myhome01">
            <input type="checkbox" name="new_family" value="1"> <span style="font-size:0.97em;">新しい家族を作成</span>
        </label>
        <label>
            <i class="fa-solid fa-user form-icon"></i>
            名前:
            <input name="name" required type="text" maxlength="50" placeholder="あなたの名前">
        </label>
        <label>
            <i class="fa-solid fa-key form-icon"></i>
            パスワード:
            <input name="password" type="password" required placeholder="英数字8文字以上推奨">
        </label>
        <button><i class="fa-solid fa-plus"></i> 登録</button>
    </form>
    <a class="back-link" href="login.php"><i class="fa-solid fa-arrow-left"></i> ログインに戻る</a>
</body>
</html>