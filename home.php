<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db.php';
session_start();

// ▼1. ログイン認証確認。未ログインならリダイレクト。
if (!isset($_SESSION['user_id'], $_SESSION['family_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$family_id = $_SESSION['family_id'];

// ▼ファミリーコードを取得
$fam_stmt = $pdo->prepare('SELECT family_code FROM families WHERE id = :id');
$fam_stmt->execute(['id' => $family_id]);
$family = $fam_stmt->fetch();
if (!$family) {
    die('ファミリー情報が見つかりません');
}
$family_code = $family['family_code'];

// ▼2. 日付選択とナビ
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); // デフォルト今日
$prev_date = date('Y-m-d', strtotime($date . ' -1 day'));
$next_date = date('Y-m-d', strtotime($date . ' +1 day'));

// ▼3. 家事データの取得
$stmt = $pdo->prepare("
    SELECT c.id, c.description, c.is_completed, c.due_date, u.username AS assigned_name, c.assigned_user_id
    FROM chores c
    LEFT JOIN users u ON c.assigned_user_id = u.id
    WHERE c.family_id = :family_id AND DATE(c.due_date) = :date
    ORDER BY c.due_date ASC, c.id ASC
");
$stmt->execute(['family_id' => $family_id, 'date' => $date]);
$chores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ▼4. この家族のユーザー一覧（担当者選択用・prefixで判定）
$u_stmt = $pdo->prepare("
    SELECT id, username FROM users WHERE username LIKE :prefix ORDER BY id
");
$u_stmt->execute(['prefix' => $family_code . '_%']);
$users = $u_stmt->fetchAll(PDO::FETCH_ASSOC);

// ▼5. 家事追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_chore'])) {
    $desc = trim($_POST['description']);
    $assign = $_POST['assigned_user_id'] !== '' ? (int)$_POST['assigned_user_id'] : null;
    $due = $_POST['due_date'] ?: $date;
    if ($desc !== '') {
        $ins = $pdo->prepare("
            INSERT INTO chores (family_id, description, assigned_user_id, due_date)
            VALUES (:fid, :desc, :uid, :due)
        ");
        $ins->execute([
            'fid' => $family_id,
            'desc' => $desc,
            'uid' => $assign,
            'due' => $due
        ]);
        header("Location: home.php?date=" . urlencode($due));
        exit;
    }
}

// ▼家事編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_chore'])) {
    $chore_id = (int)$_POST['edit_id'];
    $desc = trim($_POST['description']);
    $assign = $_POST['assigned_user_id'] !== '' ? (int)$_POST['assigned_user_id'] : null;
    $due = $_POST['due_date'];

    if ($desc !== '') {
        $upd = $pdo->prepare("
            UPDATE chores
            SET description = :desc, assigned_user_id = :uid, due_date = :due, updated_at = NOW()
            WHERE id = :id AND family_id = :fid
        ");
        $upd->execute([
            'desc' => $desc,
            'uid' => $assign,
            'due' => $due,
            'id' => $chore_id,
            'fid' => $family_id
        ]);
        header("Location: home.php?date=" . urlencode($date));
        exit;
    }
}

// ▼家事削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = (int)$_POST['delete_id'];
    $del_stmt = $pdo->prepare("DELETE FROM chores WHERE id = ? AND family_id = ?");
    $del_stmt->execute([$del_id, $family_id]);
    // 削除後はリロード（dateパラメータ維持）
    header("Location: home.php?date=" . urlencode($date));
    exit;
}

// 曜日表示関数
function get_weekday_jp($y_m_d) {
    $weeks = ['日','月','火','水','木','金','土'];
    return $weeks[date('w', strtotime($y_m_d))];
}

// ユーザー名表示からfamily_code_部分を除く
function display_user_name($username, $family_code) {
    $prefix = $family_code . '_';
    if (strpos($username, $prefix) === 0) {
        return mb_substr($username, mb_strlen($prefix));
    }
    return $username;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>家事管理ホーム</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <style>
        body {
            font-family: 'Segoe UI', 'メイリオ', sans-serif;
            background: linear-gradient(135deg, #f6f6f6 60%, #cceafd 100%);
            min-height: 100vh;
            margin: 0;
        }
        /* ◎1. ログインボタン */
        .login-link-fixed {
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 100;
        }
        .login-link-fixed a {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.96em;
            background: rgba(80,120,210,0.125);
            color: #4581a7;
            border: 1.5px solid #4581a7;
            border-radius: 12px;
            padding: 2.5px 12px 2.5px 9px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.18s, color 0.18s;
            box-shadow: 0 2px 5px #abe2ff33;
        }
        .login-link-fixed a:hover {
            background: #4581a713;
            color: #1d506a;
            border-color: #1d506a;
        }
        /* ◎2. メイン枠をカード風に */
        .main-card {
            background: rgba(255,255,255,0.98);
            margin: 36px auto 18px auto;
            border-radius: 20px 40px 18px 38px/24px 20px 36px 22px;
            box-shadow: 0 7px 36px #49a5e044, 0 1.5px 0 #26689510 inset;
            padding: 36px 18px;
            width: 86vw;
            max-width: 950px;
        }
        /* ◎3. ヘッダー部: ナビ */
        .header {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            margin-top: 0;
            margin-bottom: 14px;
            font-size: 1.15em;
            gap: 20px;
        }
        .header button {
            font-size: 1.08em;
            padding: 5px 13px;
            border-radius: 9px;
            border: none;
            background: #adf;
            color: #234254;
            box-shadow: 0 2px 8px #30405018;
            transition: background 0.15s;
        }
        .header button:hover {
            cursor:pointer;
            background: #7ec6f8;
        }
        .date-title {
            background: linear-gradient(92deg,#caf2f7 30%, #c1d8f9 80%);
            border-radius: 1.2em;
            padding: 0.3em 1.5em;
            font-weight: bold;
            color: #1b3d56;
            font-size: 1.32em;
            letter-spacing: 2px;
            box-shadow: 0 1px 10px #abe2ff32;
        }
        h2 {
            font-family: 'Segoe Print','メイリオ',sans-serif;
            color: #63a4ff;
            text-align: center;
            font-weight: bold;
            margin-top: 0.7em;
            margin-bottom: 0.3em;
            font-size: 2.1em;
            background: linear-gradient(to right,#c7e7fe,#e9ebfa 60%,#e5e875 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        table {
            margin: 32px auto 17px auto;
            background: #f8fbff;
            border-collapse: separate;
            border-spacing: 0;
            width: 97%;
            box-shadow: 0 3px 15px #b7d2ff3a;
            border-radius: 14px;
            overflow: hidden;
        }
        th, td {
            border-bottom: 1.5px solid #e2f0fc;
            padding: 10px 6px;
            text-align: center;
        }
        th {
            background: #e8f0ff;
            color: #2775ae;
            font-weight: bold;
            letter-spacing:1px;
        }
        tr:last-child td { border-bottom: none; }
        .completed {
            color: #b4b4b4;
            text-decoration: line-through;
        }
        .due-today {
            color: #ff4444;
            font-weight: bold;
            background: #fff1f1;
            border-radius: 8px;
        }
        .due-soon {
            color: #e68a00;
        }
        /* ◎5. Add-Form */
        .add-form {
            margin: 2em auto 0.7em auto;
            width: 85%;
            border-radius: 16px;
            padding: 1.2em 1.5em;
            background: linear-gradient(93deg, #eaf7ff 50%, #ffe 100%);
            box-shadow: 0 3px 18px #59bef12a, 0 1px #f1eee8 inset;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1.2em;
        }
        .add-form b {
            font-size: 1.14em;
            margin-right: 0.7em;
            color: #2775ae;
        }
        .add-form label {
            margin-left: 0.2em;
        }
        .add-form input[type="text"] {
            border-radius: 7px;
            border: 1.2px solid #b4dfff;
            padding: 5px 11px;
            font-size: 1.06em;
        }
        .add-form select, .add-form input[type="date"] {
            border-radius: 7px;
            border: 1.1px solid #afe0dd;
            padding: 4px 10px 4px 11px;
            font-size: 1.03em;
            background: #fff;
        }
        .add-form button {
            background: #75e4a6;
            color: #196833;
            border: none;
            padding: 7px 23px;
            margin-left: 1em;
            border-radius: 11px;
            font-weight: bold;
            font-size: 1.1em;
            box-shadow: 0 2.5px 11px #56f37413;
            transition: background 0.14s, color 0.14s;
        }
        .add-form button:hover {
            background: #21c090;
            color: #e8fff0;
            cursor: pointer;
        }
        /* 他小物  */
        .notassigned { color:#e2584d;font-weight:bold;}

        /* 新しいCSSスタイルを追加 */
        .edit-form-container {
            margin-top: 1em;
            padding: 1em;
            background: #eaf7ff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none; /* 初期状態では非表示 */
        }
        .edit-form-container.active {
            display: block; /* activeクラスが追加されたら表示 */
        }
        .edit-form label {
            display: block;
            margin-bottom: 0.5em;
        }
        .edit-form input[type="text"],
        .edit-form input[type="date"],
        .edit-form select {
            width: calc(100% - 22px);
            padding: 8px;
            margin-bottom: 1em;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .edit-form button {
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }
        .edit-form .save-btn {
            background: #75e4a6;
            color: #196833;
        }
        .edit-form .cancel-btn {
            background: #ccc;
            color: #333;
        }
        @media (max-width:700px) {
            .main-card { padding:9px 2vw; width:99vw; }
            .add-form { width:98vw;flex-direction:column;align-items:flex-start;gap:0.8em; }
        }
    </style>
</head>
<body>
    <div class="login-link-fixed">
        <a href="login.php" title="ログイン画面へ">
            <i class="fa-solid fa-right-to-bracket"></i>
            <span style="font-size:0.99em;">ログアウト</span>
        </a>
    </div>
    <div class="main-card">
        <div class="header">
            <form style="display:inline;" method="GET" action="home.php">
                <input type="hidden" name="date" value="<?=htmlspecialchars($prev_date)?>">
                <button><i class="fa-solid fa-caret-left"></i> 前日</button>
            </form>
            <span class="date-title">
                <?=htmlspecialchars($date)?>（<?=get_weekday_jp($date)?>）
            </span>
            <form style="display:inline;" method="GET" action="home.php">
                <input type="hidden" name="date" value="<?=htmlspecialchars($next_date)?>">
                <button>翌日 <i class="fa-solid fa-caret-right"></i></button>
            </form>
        </div>
        <h2><i class="fa-solid fa-list-check"></i> ToDoリスト</h2>
        <table>
            <tr>
                <th>家事内容</th><th>担当者</th><th>状態</th><th>操作</th><th>期限</th><th>編集/削除</th>
            </tr>
            <?php if (empty($chores)): ?>
                <tr><td colspan="6" style="color:#859;">家事はありません</td></tr>
            <?php else: foreach($chores as $c):
                    $due_class = "";
                    $tdy = date('Y-m-d');
                    $tmr = date('Y-m-d', strtotime('tomorrow'));
                    if (!$c['is_completed']) {
                        if (substr($c['due_date'],0,10)===$tdy) $due_class='due-today';
                        elseif (substr($c['due_date'],0,10)===$tmr) $due_class='due-soon';
                    }
                ?>
                <tr<?= $c['is_completed'] ? ' class="completed"':'' ?>>
                    <td id="desc-<?=$c['id']?>"><?=htmlspecialchars($c['description'])?></td>
                    <td id="assigned-<?=$c['id']?>" data-assigned-id="<?=htmlspecialchars($c['assigned_user_id'])?>">
                    <?php
                    if ($c['assigned_name']) {
                        echo htmlspecialchars(display_user_name($c['assigned_name'], $family_code));
                    } else {
                        echo '<span class="notassigned">未割当</span>';
                    }
                    ?>
                    </td>
                    <td>
                        <?php if ($c['is_completed']): ?>
                            <span class="completed">完了</span>
                        <?php else: ?>
                            <span>未完了</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$c['is_completed']): ?>
                        <form method="POST" action="complete.php" style="display:inline;">
                            <input type="hidden" name="chore_id" value="<?=$c['id']?>">
                            <button style="background:#aaf4;">完了</button>
                        </form>
                        <?php else: ?> - <?php endif; ?>
                    </td>
                    <td id="due-<?=$c['id']?>" class="<?=$due_class?>">
                        <?=htmlspecialchars(substr($c['due_date'],0,10))?> (<?=get_weekday_jp($c['due_date'])?>)
                    </td>
                    <td>
                        <button onclick="showEditForm(<?=$c['id']?>)" style="background:#5bc0de;color:#fff;">編集</button>
                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('本当に削除しますか？');">
                            <input type="hidden" name="delete_id" value="<?=$c['id']?>">
                            <button style="background:#f77;color:#fff;">削除</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
        <div id="edit-form-container" class="edit-form-container">
            <h3>家事編集</h3>
            <form id="edit-form" class="edit-form" method="POST" action="">
                <input type="hidden" name="edit_id" id="edit-chore-id">
                <label>
                    内容:
                    <input type="text" name="description" id="edit-description" maxlength="200" required>
                </label>
                <label>
                    担当者:
                    <select name="assigned_user_id" id="edit-assigned-user">
                        <option value="">未割当</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?=$u['id']?>"><?=htmlspecialchars(display_user_name($u['username'], $family_code))?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    日付:
                    <input type="date" name="due_date" id="edit-due-date" required>
                </label>
                <input type="hidden" name="edit_chore" value="1">
                <button type="submit" class="save-btn"><i class="fa-solid fa-floppy-disk"></i> 保存</button>
                <button type="button" class="cancel-btn" onclick="hideEditForm()"><i class="fa-solid fa-ban"></i> キャンセル</button>
            </form>
        </div>

        <form class="add-form" method="POST" action="">
            <b><i class="fa-solid fa-plus"></i> 家事追加：</b>
            <label>内容:
                <input name="description" maxlength="200" required>
            </label>
            <label>担当者:
                <select name="assigned_user_id">
                    <option value="">未割当</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?=$u['id']?>"><?=htmlspecialchars(display_user_name($u['username'], $family_code))?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>日付:
                <input type="date" name="due_date" value="<?=htmlspecialchars($date)?>">
            </label>
            <input type="hidden" name="add_chore" value="1">
            <button><i class="fa-solid fa-plus"></i> 追加</button>
        </form>
    </div>

    <script>
        function showEditForm(choreId) {
            const container = document.getElementById('edit-form-container');
            const choreDescription = document.getElementById(`desc-${choreId}`).innerText;
            const assignedUserId = document.getElementById(`assigned-${choreId}`).dataset.assignedId;
            const dueDate = document.getElementById(`due-${choreId}`).innerText.substring(0, 10); // "YYYY-MM-DD (曜日)" から日付のみ抽出

            document.getElementById('edit-chore-id').value = choreId;
            document.getElementById('edit-description').value = choreDescription;
            document.getElementById('edit-assigned-user').value = assignedUserId;
            document.getElementById('edit-due-date').value = dueDate;

            container.classList.add('active'); // フォームを表示
            window.scrollTo(0, document.body.scrollHeight); // フォームまでスクロール
        }

        function hideEditForm() {
            document.getElementById('edit-form-container').classList.remove('active'); // フォームを非表示
        }
    </script>
</body>
</html>
