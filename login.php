<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $family_code = trim($_POST['family_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';

    // 入力チェック
    if ($family_code === '' || $name === '' || $password === '') {
        $error = '全ての項目を入力してください。';
    } else {
        // usernameを "{family_code}_{name}" で組み立て
        $username = $family_code . '_' . $name;

        // ユーザー取得
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // familyも確かめておく
        $fam_stmt = $pdo->prepare('SELECT * FROM families WHERE family_code = ?');
        $fam_stmt->execute([$family_code]);
        $family = $fam_stmt->fetch();

        if (!$family) {
            $error = '家族コードが存在しません。';
        } elseif (!$user || !password_verify($password, $user['password'])) {
            $error = 'ユーザー名またはパスワードが正しくありません。';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['family_id'] = $family['id'];
            header('Location: home.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログイン - 家事管理アプリ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:700,400&display=swap" rel="stylesheet">
    <style>
        body {
            background: #232932;
            color: #f5f5f5;
            font-family: 'Roboto Slab', Arial, sans-serif;
            min-height: 100vh;
            margin: 0;
        }
        header {
            text-align: center;
            padding: 23px 0 10px 0;
            background: #222831;
            border-bottom: 1px solid #18324b;
            box-shadow: 0 1px 6px #0005;
        }
        header h1 {
            margin: 0;
            font-size: 2.0rem;
            color: #00adb5;
            font-family: 'Roboto Slab', Arial, sans-serif;
            letter-spacing: 1.5px;
            text-shadow: 1px 2px 5px #000c;
        }
        .login-box {
            background: #282c34;
            max-width: 340px;
            margin: 44px auto 0 auto;
            border-radius: 13px;
            box-shadow: 0 5px 20px #0008;
            padding: 34px 32px 23px 32px;
        }
        .login-title {
            margin: 0 0 18px 0;
            text-align: center;
            color: #00adb5;
            font-size: 1.50rem;
            font-weight: bold;
        }
        label {
            display:block;
            margin: 22px 0 8px 0;
            letter-spacing: 1px;
            font-size: 1.12rem;
            color: #f5f5f5;
        }
        .form-icon {
            margin-right: 8px;
            color: #00adb5;
            font-size: 1rem;
            vertical-align: middle;
        }
        input[type=text], input[type=password] {
            width: 96%;
            display:block;
            padding:9px 8px;
            border-radius: 6px;
            border: 1.4px solid #444;
            background: #393e46;
            color: #f5f5f5;
            font-size: 1rem;
            margin-top: 3px;
        }
        button {
            background: #00adb5;
            color: #232932;
            font-size: 1.08rem;
            border: none;
            padding: 11px 24px;
            margin: 27px auto 0 auto;
            display: block;
            border-radius: 7px;
            box-shadow: 0 2px 7px #0005;
            cursor: pointer;
            font-weight: bold;
            letter-spacing: 2px;
            transition: background 0.2s;
        }
        button:hover {
            background: #008891;
        }
        .err {
            background: #441818;
            color: #ffaaaa;
            font-weight: bold;
            font-size: 1rem;
            padding: 10px 12px;
            border-radius: 6px;
            margin: 17px 0 8px 0;
            text-align: center;
            box-shadow: 0 2px 12px #0003;
        }
        .register-link {
            display: block;
            text-align: center;
            margin-top: 32px;
            color: #a6e5ff;
            font-size: 1.02rem;
        }
        .register-link a {
            color: #00adb5;
            text-decoration: none;
            font-weight: bold;
            margin-left: 2px;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            .login-box {padding: 22px 8px;}
            header {/* スマホ向け調整 */}
        }
    </style>
</head>
<body>
    <header>
        <h1><i class="fa-solid fa-person-digging"></i> 家事管理アプリ</h1>
    </header>
    
    <div class="login-box">
        <div class="login-title"><i class="fa-solid fa-right-to-bracket"></i> ログイン</div>
        <?php if ($error): ?>
            <div class="err"><?=htmlspecialchars($error)?></div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <label>
                <i class="fa-solid fa-users form-icon"></i>
                家族コード
                <input type="text" name="family_code" value="<?=htmlspecialchars($_POST['family_code'] ?? '')?>" required maxlength="10">
            </label>
            <label>
                <i class="fa-solid fa-user form-icon"></i>
                名前
                <input type="text" name="name" value="<?=htmlspecialchars($_POST['name'] ?? '')?>" required maxlength="50">
            </label>
            <label>
                <i class="fa-solid fa-key form-icon"></i>
                パスワード
                <input type="password" name="password" required>
            </label>
            <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> ログイン</button>
        </form>
        <div class="register-link">
            アカウントをお持ちでない方は
            <a href="register.php"><i class="fa-solid fa-user-plus"></i> 新規登録はこちら</a>
        </div>
    </div>
</body>
</html>