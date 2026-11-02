<?php
/**
 * e-Mediqua Transparent Reverse Proxy for Apache Port 80
 * Menghubungkan http://localhost/e-Mediqua langsung ke backend e-Mediqua (Port 8088)
 */

$backendPort = 8088;

// 1. Pastikan backend server e-Mediqua di port 8088 aktif
$connection = @fsockopen('localhost', $backendPort, $errno, $errstr, 1);
if (!is_resource($connection)) {
    $vbs = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'e_mediqua' . DIRECTORY_SEPARATOR . 'start_server_silent.vbs';
    if (file_exists($vbs)) {
        pclose(popen('wscript.exe "' . $vbs . '"', 'r'));
        usleep(1500000);
    }
} else {
    fclose($connection);
}

// 2. Tentukan target path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
// Bersihkan prefix /e-Mediqua atau /e_mediqua
$targetPath = preg_replace('#^/(e-Mediqua|e_mediqua)/?#i', '/', $requestUri);
if ($targetPath === '' || $targetPath === false) {
    $targetPath = '/';
}

$backendUrl = 'http://localhost:' . $backendPort . $targetPath;

// 3. Konfigurasi cURL
$ch = curl_init($backendUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

// Forward request headers
$forwardHeaders = [];
if (function_exists('getallheaders')) {
    foreach (getallheaders() as $name => $value) {
        $lower = strtolower($name);
        if ($lower === 'host') {
            $forwardHeaders[] = 'Host: localhost:' . $backendPort;
        } elseif ($lower !== 'content-length' && $lower !== 'accept-encoding') {
            $forwardHeaders[] = "$name: $value";
        }
    }
}
$forwardHeaders[] = 'X-Forwarded-Host: localhost';
$forwardHeaders[] = 'X-Forwarded-Prefix: /e-Mediqua';
curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);

// Forward request body (POST, PUT, dsb.)
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'])) {
    $body = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$response = curl_exec($ch);
if ($response === false) {
    http_response_code(502);
    header('Content-Type: text/html; charset=UTF-8');
    echo "<div style='font-family:sans-serif;padding:2rem;text-align:center;'>";
    echo "<h3 style='color:#0284c7;'>Menghubungkan ke e-Mediqua...</h3>";
    echo "<p>Sedang mempersiapkan server aplikasi di port $backendPort. Silakan <a href='javascript:location.reload()'>muat ulang</a> dalam beberapa detik.</p>";
    echo "</div>";
    curl_close($ch);
    exit;
}

$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$headerContent = substr($response, 0, $headerSize);
$bodyContent   = substr($response, $headerSize);

http_response_code($httpCode);

// 4. Teruskan response headers
$lines = explode("\r\n", $headerContent);
foreach ($lines as $line) {
    if (empty($line) || stripos($line, 'HTTP/') === 0 || stripos($line, 'Transfer-Encoding:') === 0) {
        continue;
    }
    // Ganti redirect Location agar tetap di /e-Mediqua/
    if (stripos($line, 'Location:') === 0) {
        $line = preg_replace('#http://localhost:' . $backendPort . '/?#i', '/e-Mediqua/', $line);
        if (!preg_match('#^Location:\s*http#i', $line)) {
            $loc = trim(substr($line, 9));
            if (strpos($loc, '/e-Mediqua') !== 0) {
                $line = 'Location: /e-Mediqua' . (strpos($loc, '/') === 0 ? '' : '/') . $loc;
            }
        }
    }
    header($line, false);
}

// 5. Ganti referensi URL
$contentType = '';
foreach ($lines as $line) {
    if (stripos($line, 'Content-Type:') === 0) {
        $contentType = $line;
        break;
    }
}

// Hanya ubah teks jika respon bertipe html/json/xml/javascript
if (empty($contentType) || preg_match('#(text/|application/(json|javascript|xml))#i', $contentType)) {
    $bodyContent = str_replace('http://localhost:' . $backendPort . '/', '/e-Mediqua/', $bodyContent);
    $bodyContent = str_replace('http://localhost:' . $backendPort, '/e-Mediqua', $bodyContent);
}

echo $bodyContent;
