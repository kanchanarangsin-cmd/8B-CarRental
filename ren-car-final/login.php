<?php
session_start();

if (isset($_SESSION["logged_in"]) && $_SESSION["logged_in"] === true) {
    header("Location: index.php");
    exit();
}

$correct_user = "Mona Jaidee";
$correct_pass = "P@ssw0rd";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user = trim($_POST["username"] ?? "");
    $pass = trim($_POST["password"] ?? "");

    if ($user === $correct_user && $pass === $correct_pass) {
        $_SESSION["logged_in"] = true;
        $_SESSION["user"] = $correct_user;
        header("Location: index.php");
        exit();
    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบเช่ารถ</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Prompt:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/design_tokens.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: var(--font-body);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 100%);
            padding: 24px;
            color: #0f172a;
        }
        .card {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 28px;
            padding: 36px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12);
        }
        .logo {
            width: 96px;
            height: 96px;
            border-radius: 28px;
            margin: 0 auto 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #4f46e5, #3b82f6);
            color: white;
            font-size: 42px;
        }
        h1 {
            margin: 0 0 10px;
            text-align: center;
            font-size: 28px;
            font-weight: 800;
        }
        .sub {
            margin: 0 0 28px;
            text-align: center;
            color: #64748b;
            font-size: 16px;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            border-radius: 18px;
            padding: 14px 16px;
            margin-bottom: 20px;
            font-weight: 700;
        }
        label {
            display: block;
            margin: 16px 0 8px;
            font-size: 16px;
            font-weight: 700;
            color: #334155;
        }
        input {
            width: 100%;
            border: 1px solid #dbe2ea;
            border-radius: 18px;
            padding: 16px 18px;
            font-size: 18px;
            outline: none;
        }
        input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        }
        button {
            width: 100%;
            margin-top: 26px;
            border: none;
            border-radius: 20px;
            padding: 16px 18px;
            font-size: 18px;
            font-weight: 800;
            color: #fff;
            cursor: pointer;
            background: linear-gradient(135deg, #4f46e5, #3b82f6);
        }
        .hint {
            margin-top: 16px;
            color: #94a3b8;
            text-align: center;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo" aria-hidden="true">
    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M3 13L4.6 8.9C4.9 8.15 5.62 7.65 6.43 7.65H13.9C14.6 7.65 15.26 7.99 15.67 8.55L18 11.7H19.05C19.85 11.7 20.5 12.35 20.5 13.15V15.2C20.5 15.64 20.14 16 19.7 16H18.9C18.63 17.13 17.62 17.95 16.4 17.95C15.18 17.95 14.17 17.13 13.9 16H9.1C8.83 17.13 7.82 17.95 6.6 17.95C5.38 17.95 4.37 17.13 4.1 16H3.8C3.36 16 3 15.64 3 15.2V13Z" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M5.2 11.7H16.9" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
        <circle cx="6.6" cy="15.2" r="1.35" stroke="white" stroke-width="1.8"/>
        <circle cx="16.4" cy="15.2" r="1.35" stroke="white" stroke-width="1.8"/>
    </svg>
</div>
        <h1>เข้าสู่ระบบเช่ารถ</h1>
        <p class="sub">กรอกชื่อผู้ใช้และรหัสผ่านเพื่อเข้าใช้งานระบบ</p>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <label for="username">ชื่อผู้ใช้</label>
            <input type="text" id="username" name="username" placeholder="กรอกชื่อผู้ใช้" required>

            <label for="password">รหัสผ่าน</label>
            <input type="password" id="password" name="password" placeholder="กรอกรหัสผ่าน" required>

            <button type="submit" name="login">เข้าสู่ระบบ</button>
        </form>
    </div>
</body>
</html>
