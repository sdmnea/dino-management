<?php
// login.php - FIXED VERSION

// Include config file FIRST
require_once 'config/config.php';

// Redirect jika sudah login
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// Base URL untuk CSS/JS
$base_url = BASE_URL;

// Inisialisasi variabel
$error = '';
$username = '';

// Proses login jika form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validasi input
    if (empty($username) || empty($password)) {
        $error = 'Silakan isi semua field!';
    } else {
        // Koneksi database
        try {
            $database = new Database();
            $db = $database->getConnection();

            if ($db) {
                // Query untuk mencari admin
                $query = "SELECT id, username, password, nama_lengkap FROM admin WHERE username = :username";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);

                if ($stmt->execute() && $stmt->rowCount() > 0) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Password default: "password"
                    if (password_verify($password, $row['password'])) {
                        // Set session
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['nama_lengkap'] = $row['nama_lengkap'];
                        $_SESSION['login_time'] = time();
                        $_SESSION['last_activity'] = time();

                        // Update last login
                        $update_query = "UPDATE admin SET last_login = NOW() WHERE id = :id";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':id', $row['id']);
                        $update_stmt->execute();

                        // Redirect ke dashboard
                        redirect('dashboard.php');
                    } else {
                        $error = 'Username atau password salah!';
                    }
                } else {
                    $error = 'Username atau password salah!';
                }
            } else {
                $error = 'Koneksi database gagal!';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Dino Management</title>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #9ACD32;
            --primary-dark: #7cb305;
            --secondary: #FFD700;
            --accent: #8A2BE2;
            --dark: #2F1800;
            --light: #F5F5F5;
            --gray: #6B7280;
            --danger: #DC2626;
            --success: #10B981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            right: -50%;
            bottom: -50%;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.1) 1%, transparent 20%),
                radial-gradient(circle at 70% 70%, rgba(255, 255, 255, 0.05) 1%, transparent 20%);
        }

        .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            position: relative;
            z-index: 1;
        }

        .logo i {
            font-size: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .login-header p {
            font-size: 14px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .login-form {
            padding: 40px 30px;
        }

        .error-message {
            background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%);
            border: 2px solid #FCA5A5;
            color: var(--danger);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            display:
                <?php echo $error ? 'flex' : 'none'; ?>
            ;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease-out;
        }

        .error-message i {
            font-size: 20px;
            flex-shrink: 0;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label i {
            color: var(--accent);
        }

        .input-with-icon {
            position: relative;
            transition: transform 0.3s ease;
        }

        .input-with-icon:focus-within {
            transform: translateY(-2px);
        }

        .input-with-icon input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--light);
        }

        .input-with-icon input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(138, 43, 226, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 20px;
        }

        .login-button {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--accent) 0%, #6B21A8 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 10px;
        }

        .login-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(138, 43, 226, 0.3);
        }

        .login-button:active {
            transform: translateY(-1px);
        }

        .login-footer {
            text-align: center;
            padding: 25px 30px;
            color: var(--gray);
            font-size: 13px;
            border-top: 1px solid #E5E7EB;
            background: var(--light);
        }

        .login-footer p {
            margin: 5px 0;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-5px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(5px);
            }
        }

        .shake {
            animation: shake 0.5s ease-in-out;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
                border-radius: 15px;
            }

            .login-header {
                padding: 30px 20px;
            }

            .login-form {
                padding: 30px 20px;
            }

            .logo {
                width: 70px;
                height: 70px;
            }

            .logo i {
                font-size: 35px;
            }

            .login-header h1 {
                font-size: 24px;
            }
        }

        @media (max-width: 320px) {
            .login-header {
                padding: 25px 15px;
            }

            .login-form {
                padding: 25px 15px;
            }

            .input-with-icon input {
                padding: 12px 12px 12px 45px;
                font-size: 15px;
            }

            .login-button {
                padding: 15px;
                font-size: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-mug-hot"></i>
            </div>
            <h1>Es Teh Dino</h1>
            <p>Management System v1.0</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message" id="errorMessage">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="login-form" id="loginForm">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> Username
                </label>
                <div class="input-with-icon">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username" placeholder="Masukkan username" required
                        value="<?php echo htmlspecialchars($username); ?>" autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Password
                </label>
                <div class="input-with-icon">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" placeholder="Masukkan password" required
                        autocomplete="current-password">
                </div>
            </div>

            <button type="submit" class="login-button">
                <i class="fas fa-sign-in-alt"></i> Masuk ke Dashboard
            </button>
        </form>

        <div class="login-footer">
            <p>© <?php echo date('Y'); ?> Es Teh Dino Management System</p>
            <p>Hanya untuk penggunaan internal • v1.0.0</p>
            <p>Default: admin / password</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Focus pada input username
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                usernameInput.focus();
                usernameInput.select();
            }

            // Auto-hide error message setelah 5 detik
            const errorMessage = document.getElementById('errorMessage');
            if (errorMessage) {
                setTimeout(() => {
                    errorMessage.style.opacity = '0';
                    errorMessage.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        errorMessage.style.display = 'none';
                    }, 500);
                }, 5000);
            }

            // Form validation
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function (e) {
                    const username = document.getElementById('username').value.trim();
                    const password = document.getElementById('password').value.trim();

                    if (!username || !password) {
                        e.preventDefault();
                        alert('Silakan isi username dan password!');
                        return false;
                    }

                    // Disable button setelah submit
                    const submitBtn = loginForm.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
                    submitBtn.style.opacity = '0.7';

                    return true;
                });
            }

            // Animasi input focus
            const inputs = document.querySelectorAll('.input-with-icon input');
            inputs.forEach(input => {
                input.addEventListener('focus', function () {
                    this.parentElement.style.transform = 'scale(1.02)';
                });

                input.addEventListener('blur', function () {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });

            // Enter key untuk submit
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.target.matches('button')) {
                    const activeElement = document.activeElement;
                    if (activeElement.matches('input')) {
                        e.preventDefault();
                        loginForm.submit();
                    }
                }
            });

            // Debug info
            console.log('Login page loaded successfully');
            console.log('PHP Session ID:', '<?php echo session_id(); ?>');
        });

        // Handle browser back button
        window.onpageshow = function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
    </script>
</body>

</html>