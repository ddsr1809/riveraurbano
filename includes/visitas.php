<?php
declare(strict_types=1);

/**
 * riveraurbano.com — Registro de visitas: de dónde vienen y si son personas o bots.
 * Sin cookies: el "visitante" es una huella diaria anónima (IP + navegador + día + valor secreto).
 */

require_once __DIR__ . '/bootstrap.php';

const TIPOS_VISITA = [
    'humano'       => 'Persona',
    'probable'     => 'Probable persona',
    'sospechoso'   => 'Sospechoso',
    'bot'          => 'Bot',
    'vista_previa' => 'Vista previa',
    'herramienta'  => 'Herramienta',
    'interno'      => 'Interno',
];

/* ------------------------------------------------------------------ user agent */

/** Detecta bots conocidos. Devuelve [tipo, nombre] o null si parece navegador. */
function detectar_bot(string $ua): ?array
{
    if (trim($ua) === '') return ['herramienta', 'Sin identificar'];

    // Previsualizaciones de enlaces: alguien compartió tu link
    $previas = [
        'WhatsApp' => 'WhatsApp', 'facebookexternalhit' => 'Facebook', 'Facebot' => 'Facebook', 'meta-externalagent' => 'Meta',
        'Twitterbot' => 'X (Twitter)', 'TelegramBot' => 'Telegram', 'LinkedInBot' => 'LinkedIn', 'Slackbot' => 'Slack',
        'Discordbot' => 'Discord', 'SkypeUriPreview' => 'Skype', 'Pinterestbot' => 'Pinterest', 'redditbot' => 'Reddit',
        'Iframely' => 'Iframely', 'Embedly' => 'Embedly', 'vkShare' => 'VK', 'Viber' => 'Viber', 'Snapchat' => 'Snapchat',
        'Google-PageRenderer' => 'Google (vista previa)', 'Applebot' => 'Apple', 'bitlybot' => 'Bitly',
    ];
    foreach ($previas as $aguja => $nombre) if (stripos($ua, $aguja) !== false) return ['vista_previa', $nombre];

    $bots = [
        'Googlebot' => 'Googlebot', 'AdsBot-Google' => 'Google Ads', 'Mediapartners-Google' => 'Google AdSense',
        'Google-InspectionTool' => 'Google Search Console', 'GoogleOther' => 'Google', 'Storebot-Google' => 'Google',
        'bingbot' => 'Bingbot', 'BingPreview' => 'Bing', 'YandexBot' => 'Yandex', 'DuckDuckBot' => 'DuckDuckGo',
        'Baiduspider' => 'Baidu', 'Sogou' => 'Sogou', 'SeznamBot' => 'Seznam', 'Qwantify' => 'Qwant', 'Yahoo! Slurp' => 'Yahoo',
        'AhrefsBot' => 'Ahrefs', 'SemrushBot' => 'Semrush', 'MJ12bot' => 'Majestic', 'DotBot' => 'Moz', 'rogerbot' => 'Moz',
        'PetalBot' => 'Petal (Huawei)', 'Bytespider' => 'ByteDance (TikTok)', 'Amazonbot' => 'Amazon',
        'GPTBot' => 'OpenAI', 'ChatGPT-User' => 'ChatGPT', 'OAI-SearchBot' => 'OpenAI', 'ClaudeBot' => 'Anthropic',
        'Claude-User' => 'Anthropic', 'anthropic-ai' => 'Anthropic', 'PerplexityBot' => 'Perplexity', 'CCBot' => 'Common Crawl',
        'DataForSeoBot' => 'DataForSEO', 'BLEXBot' => 'BLEXBot', 'serpstatbot' => 'Serpstat', 'Barkrowler' => 'Babbar',
        'CensysInspect' => 'Censys', 'Expanse' => 'Palo Alto Expanse', 'zgrab' => 'ZGrab (escáner)', 'masscan' => 'Masscan (escáner)',
        'Nuclei' => 'Nuclei (escáner)', 'Nmap' => 'Nmap (escáner)', 'sqlmap' => 'sqlmap (ataque)', 'WPScan' => 'WPScan (escáner)',
    ];
    foreach ($bots as $aguja => $nombre) if (stripos($ua, $aguja) !== false) return ['bot', $nombre];

    $herramientas = [
        'curl/' => 'curl', 'Wget' => 'wget', 'python-requests' => 'Python', 'python-urllib' => 'Python', 'aiohttp' => 'Python',
        'httpx' => 'Python', 'Go-http-client' => 'Go', 'Java/' => 'Java', 'okhttp' => 'OkHttp', 'axios' => 'Node.js',
        'node-fetch' => 'Node.js', 'undici' => 'Node.js', 'libwww-perl' => 'Perl', 'Ruby' => 'Ruby', 'PostmanRuntime' => 'Postman',
        'Insomnia' => 'Insomnia', 'Apache-HttpClient' => 'Java', 'Guzzle' => 'PHP', 'Scrapy' => 'Scrapy',
        'UptimeRobot' => 'UptimeRobot (monitor)', 'Pingdom' => 'Pingdom (monitor)', 'StatusCake' => 'StatusCake (monitor)',
        'Better Uptime' => 'Better Stack (monitor)', 'Site24x7' => 'Site24x7 (monitor)', 'HetrixTools' => 'HetrixTools (monitor)',
        'HeadlessChrome' => 'Chrome automatizado', 'PhantomJS' => 'PhantomJS', 'Puppeteer' => 'Puppeteer', 'Playwright' => 'Playwright',
        'Selenium' => 'Selenium', 'Lighthouse' => 'Lighthouse', 'Chrome-Lighthouse' => 'Lighthouse', 'PageSpeed' => 'PageSpeed',
    ];
    foreach ($herramientas as $aguja => $nombre) if (stripos($ua, $aguja) !== false) return ['herramienta', $nombre];

    if (preg_match('/bot\b|crawl|spider|scrap|fetch|monitor|scanner|checker|preview|http[-_ ]?client/i', $ua)) {
        preg_match('/([A-Za-z0-9._-]*(?:bot|crawler|spider)[A-Za-z0-9._-]*)/i', $ua, $m);
        return ['bot', mb_substr($m[1] ?? 'Bot desconocido', 0, 60)];
    }
    // Un navegador real siempre dice Mozilla/…; si no, es un programa
    if (!str_starts_with($ua, 'Mozilla/') && !str_starts_with($ua, 'Opera/')) return ['herramienta', mb_substr(strtok($ua, ' /'), 0, 60)];
    return null;
}

/** Navegador, sistema y tipo de dispositivo a partir del user agent. */
function analizar_ua(string $ua): array
{
    $nav = match (true) {
        (bool) preg_match('/Edg(e|A|iOS)?\//', $ua)  => 'Edge',
        (bool) preg_match('/OPR\/|Opera/', $ua)      => 'Opera',
        (bool) preg_match('/SamsungBrowser/', $ua)   => 'Samsung Internet',
        (bool) preg_match('/FBAN|FBAV|FB_IAB/', $ua) => 'Facebook (app)',
        (bool) preg_match('/Instagram/', $ua)        => 'Instagram (app)',
        (bool) preg_match('/CriOS|Chrome\//', $ua)   => 'Chrome',
        (bool) preg_match('/FxiOS|Firefox\//', $ua)  => 'Firefox',
        (bool) preg_match('/Safari\//', $ua)         => 'Safari',
        default => 'Otro',
    };
    $so = match (true) {
        (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
        (bool) preg_match('/Android/', $ua)          => 'Android',
        (bool) preg_match('/Windows/', $ua)          => 'Windows',
        (bool) preg_match('/Mac OS X|Macintosh/', $ua) => 'macOS',
        (bool) preg_match('/CrOS/', $ua)             => 'ChromeOS',
        (bool) preg_match('/Linux/', $ua)            => 'Linux',
        default => 'Otro',
    };
    $disp = match (true) {
        (bool) preg_match('/iPad|Tablet|(Android(?!.*Mobile))/', $ua) => 'Tableta',
        (bool) preg_match('/Mobi|iPhone|Android/', $ua)                => 'Celular',
        default => 'Computadora',
    };
    return [$nav, $so, $disp];
}

/* ------------------------------------------------------------------ IP */

/** Proveedores de servidores en la nube: una "persona" no suele navegar desde ahí. */
function es_centro_datos(string $org): bool
{
    // Nombres de proveedores de nube/hosting + palabras genéricas (hosting, servers, vps, cloud, colocation…).
    // Ampliada con los proveedores detectados en el tráfico real de d2600.com.
    return (bool) preg_match('/amazon|aws|google cloud|google llc|microsoft|azure|digitalocean|ovh|hetzner|linode|akamai|vultr|choopa|'
        . 'oracle|alibaba|tencent|huawei|contabo|scaleway|leaseweb|m247|datacamp|cloudflare|fastly|hostinger|ionos|godaddy|'
        . 'hurricane electric|colocrossing|psychz|quadranet|servers\.com|constant company|zenlayer|g-?core|stark industries|'
        . 'frantech|buyvm|packethub|datapacket|hostroyale|kamatera|upcloud|hostwinds|interserver|netcup|limestone|'
        . 'performive|tzulo|cdn77|bunny|shock hosting|pq hosting|aeza|serverion|iptp|nforce|worldstream|clouvider|'
        . 'techoff|storm industries|aceville|ucloud|ip volume|miteflux|blix solutions|omegatech|unmanaged ltd|biterika|'
        . 'petersburg internet network|applied privacy|kaopu|capitalonline|pfcloud|flyservers|carinet|censys|strato|'
        . 'hostpapa|glesys|hydra communications|maxihost|vdsina|new dream network|dreamhost|'
        . '\\bhost(ings?)?\\b|\\bservers?\\b|\\bvps\\b|\\bcloud\\b|\\bcolo(cation)?\\b|data ?cent(er|re)|dedicated/i', $org);
}

/** Sitios que mandan visitas falsas para aparecer en tus estadísticas (spam de referencia). */
function es_spam_referencia(string $referente): bool
{
    return $referente !== '' && (bool) preg_match('~//([a-z0-9-]+\.)*(chordmp3\.net|addurl\.in|semalt\.com|buttons-for-website\.com|'
        . 'best-seo-offer\.com|darodar\.com|ilovevitaly\.|priceg\.com|hulfingtonpost\.com|free-social-buttons|get-free-traffic)~i', $referente);
}

function lector_geoip(string $tipo): ?\MaxMind\Db\Reader
{
    static $cache = [], $cargador = false;
    if (array_key_exists($tipo, $cache)) return $cache[$tipo];
    if (!$cargador) {
        spl_autoload_register(function ($c) {
            if (str_starts_with($c, 'MaxMind\\Db\\')) require_once __DIR__ . '/' . str_replace('\\', '/', $c) . '.php';
        });
        $cargador = true;
    }
    $dir = getenv('GEOIP_DIR') ?: '/usr/share/GeoIP';
    $candidatos = $tipo === 'ciudad'
        ? ['GeoLite2-City', 'dbip-city-lite', 'GeoLite2-City-Test']
        : ['GeoLite2-ASN', 'dbip-asn-lite', 'GeoLite2-ASN-Test'];
    foreach ($candidatos as $base) {
        $archivo = "$dir/$base.mmdb";
        if (!is_file($archivo)) continue;
        try {
            $GLOBALS['FUENTE_GEOIP'] ??= str_starts_with($base, 'dbip') ? 'dbip' : 'maxmind';
            return $cache[$tipo] = new \MaxMind\Db\Reader($archivo);
        } catch (Throwable $e) {
            error_log("[visitas] GeoIP $base: " . $e->getMessage());
        }
    }
    return $cache[$tipo] = null;
}

/** Ubicación aproximada y dueño de la IP (bases GeoLite2 locales; no se envía la IP a nadie). */
function geolocalizar(string $ip): array
{
    $r = ['pais_codigo' => '', 'pais' => '', 'region' => '', 'ciudad' => '', 'codigo_postal' => '', 'latitud' => null,
          'longitud' => null, 'radio_km' => null, 'zona_horaria' => '', 'asn' => null, 'organizacion' => '', 'centro_datos' => 0];
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $r['organizacion'] = 'Red local';
        return $r;
    }
    $nombre = fn($n) => trim((string) preg_replace('/\s*\((el|la|los|las)\)$/u', '', $n['names']['es'] ?? $n['names']['en'] ?? ''));
    try {
        if ($c = lector_geoip('ciudad')?->get($ip)) {
            $r['pais_codigo'] = $c['country']['iso_code'] ?? '';
            $r['pais']        = isset($c['country']) ? $nombre($c['country']) : '';
            $r['region']      = isset($c['subdivisions'][0]) ? $nombre($c['subdivisions'][0]) : '';
            $r['ciudad']      = isset($c['city']) ? $nombre($c['city']) : '';
            $r['codigo_postal'] = $c['postal']['code'] ?? '';
            $r['latitud']     = $c['location']['latitude'] ?? null;
            $r['longitud']    = $c['location']['longitude'] ?? null;
            $r['radio_km']    = $c['location']['accuracy_radius'] ?? null;
            $r['zona_horaria'] = $c['location']['time_zone'] ?? '';
        }
        if ($a = lector_geoip('asn')?->get($ip)) {
            $r['asn'] = $a['autonomous_system_number'] ?? null;
            $r['organizacion'] = mb_substr($a['autonomous_system_organization'] ?? '', 0, 150);
            $r['centro_datos'] = es_centro_datos($r['organizacion']) ? 1 : 0;
        }
    } catch (Throwable $e) {
        error_log('[visitas] GeoIP: ' . $e->getMessage());
    }
    return $r;
}

/* ------------------------------------------------------------------ fuente */

function fuente_de(string $referente, string $utm): string
{
    if ($utm !== '') return mb_substr(ucfirst($utm), 0, 100);
    if ($referente === '') return 'Directo';
    $host = strtolower((string) parse_url($referente, PHP_URL_HOST));
    $host = preg_replace('/^(www\.|m\.|l\.|lm\.|mobile\.)/', '', $host);
    $mapa = [
        '/(^|\.)google\./' => 'Google', '/bing\.com$/' => 'Bing', '/duckduckgo\.com$/' => 'DuckDuckGo', '/yahoo\./' => 'Yahoo',
        '/facebook\.com$|fb\.me$/' => 'Facebook', '/instagram\.com$/' => 'Instagram', '/(^|\.)t\.co$|twitter\.com$|x\.com$/' => 'X (Twitter)',
        '/linkedin\.com$|lnkd\.in$/' => 'LinkedIn', '/whatsapp\.com$|wa\.me$/' => 'WhatsApp', '/t\.me$|telegram/' => 'Telegram',
        '/tiktok\.com$/' => 'TikTok', '/youtube\.com$|youtu\.be$/' => 'YouTube', '/chatgpt\.com$|openai\.com$/' => 'ChatGPT',
        '/perplexity\.ai$/' => 'Perplexity', '/claude\.ai$/' => 'Claude', '/inmuebles24|vivanuncios|lamudi|mercadolibre|icasas|propiedades\.com/' => 'Portales inmobiliarios',
    ];
    foreach ($mapa as $re => $nombre) if (preg_match($re, $host)) return $nombre;
    return mb_substr($host ?: 'Otro', 0, 100);
}

/* ------------------------------------------------------------------ registrar */

/**
 * Registra la visita a una página pública. Devuelve el token para el JavaScript, o null si no se registró.
 * Nunca rompe la página: cualquier error se anota en el log y se sigue.
 */
function registrar_visita(string $idioma): ?string
{
    try {
        $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($metodo, ['GET', 'HEAD'], true)) return null;
        // Precargas del navegador: no son visitas reales
        $proposito = strtolower(($_SERVER['HTTP_SEC_PURPOSE'] ?? '') . ($_SERVER['HTTP_PURPOSE'] ?? '') . ($_SERVER['HTTP_X_MOZ'] ?? ''));
        if (str_contains($proposito, 'prefetch') || str_contains($proposito, 'prerender')) return null;

        $db  = db();
        $cfg = $GLOBALS['CONFIG'] ?? [];
        $ip  = ip_cliente();
        $ua  = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $ref = mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
        $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        if ($ref !== '' && strtolower((string) parse_url($ref, PHP_URL_HOST)) === $host) $ref = '';   // navegación interna
        $acepta = mb_substr((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 100);
        $utm = fn($k) => mb_substr(trim((string) ($_GET[$k] ?? '')), 0, 100);

        $geo = geolocalizar($ip);
        [$nav, $so, $disp] = analizar_ua($ua);
        $bot = detectar_bot($ua);
        if (!$bot && es_spam_referencia($ref)) $bot = ['bot', 'Spam de referencia'];
        $motivo = '';
        if ($bot) {
            [$tipo, $nombreBot] = $bot;
            $disp = 'Bot';
            // ¿Dice ser Google o Bing pero viene de otra red? Entonces es un impostor.
            if ($geo['asn'] && preg_match('/^(Googlebot|Google Ads|Google Search Console)$/', $nombreBot) && $geo['asn'] !== 15169) {
                $tipo = 'sospechoso'; $motivo = "Dice ser $nombreBot pero no viene de la red de Google";
            } elseif ($geo['asn'] && $nombreBot === 'Bingbot' && $geo['asn'] !== 8075) {
                $tipo = 'sospechoso'; $motivo = 'Dice ser Bingbot pero no viene de la red de Microsoft';
            } else {
                $motivo = match ($tipo) {
                    'vista_previa' => "Vista previa del enlace ($nombreBot): alguien lo compartió o pegó",
                    'herramienta'  => "Programa automático ($nombreBot)",
                    default        => "Robot identificado ($nombreBot)",
                };
            }
        } else {
            $nombreBot = '';
            $senales = [];
            if ($geo['centro_datos']) $senales[] = 'la IP es de un centro de datos (' . $geo['organizacion'] . '), no de un hogar o celular';
            if ($acepta === '') $senales[] = 'el navegador no envía idioma';
            if (preg_match('/Chrome\/([0-9]+)\./', $ua, $m) && (int) $m[1] < 90) $senales[] = "usa Chrome $m[1], una versión muy antigua";
            $tipo = $senales ? 'sospechoso' : 'probable';
            $motivo = $senales ? 'Sospechoso: ' . implode('; ', $senales) : 'Navegador normal; se confirma como persona al interactuar';
        }
        $excluidas = array_filter(array_map('trim', explode(',', (string) ($cfg['visitas_ips_excluidas'] ?? ''))));
        if (in_array($ip, $excluidas, true)) { $tipo = 'interno'; $motivo = 'IP marcada como propia'; }

        $token = bin2hex(random_bytes(16));
        $sal = (string) ($cfg['visitas_sal'] ?? 'riveraurbano');
        $visitante = substr(hash('sha256', "$ip|$ua|" . date('Y-m-d') . "|$sal"), 0, 16);
        $ruta = mb_substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), 0, 255);

        $db->prepare('INSERT INTO visitas (fecha, token, visitante, ip, metodo, ruta, idioma, idioma_navegador, referente, fuente,
                utm_source, utm_medium, utm_campaign, ua, navegador, sistema, dispositivo, pais_codigo, pais, region, ciudad,
                codigo_postal, latitud, longitud, radio_km, zona_horaria, asn, organizacion, centro_datos, tipo, nombre_bot, motivo)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
           ->execute([date('Y-m-d H:i:s'), $token, $visitante, $ip, $metodo, $ruta, $idioma, $acepta, $ref,
                      fuente_de($ref, $utm('utm_source')), $utm('utm_source'), $utm('utm_medium'), $utm('utm_campaign'),
                      $ua, $bot ? '' : $nav, $bot ? '' : $so, $disp, $geo['pais_codigo'], $geo['pais'], $geo['region'],
                      $geo['ciudad'], $geo['codigo_postal'], $geo['latitud'], $geo['longitud'], $geo['radio_km'],
                      $geo['zona_horaria'], $geo['asn'], $geo['organizacion'], $geo['centro_datos'], $tipo, $nombreBot,
                      mb_substr($motivo, 0, 255)]);

        // Limpieza ocasional según los días configurados
        if (random_int(1, 300) === 1) {
            $dias = max(7, (int) ($cfg['visitas_dias'] ?? 180));
            $db->prepare('DELETE FROM visitas WHERE fecha < NOW() - INTERVAL ? DAY')->execute([$dias]);
        }
        return $token;
    } catch (Throwable $e) {
        error_log('[visitas] ' . $e->getMessage());
        return null;
    }
}

/* ------------------------------------------------------------------ señales del navegador */

const EVENTOS_VISITA = [
    'whatsapp' => 'WhatsApp', 'llamar' => 'Llamada', 'correo' => 'Correo', 'mapa' => 'Mapa',
    'video' => 'Reprodujo el video', 'formulario' => 'Envió el formulario', 'idioma' => 'Cambió de idioma', 'lote' => 'Vio un lote',
];

/** Procesa una señal enviada por sitio.js. */
function registrar_senal(string $token, string $senal, string $detalle): void
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return;
    $db = db();
    $st = $db->prepare('SELECT id, tipo, nombre_bot FROM visitas WHERE token = ? AND fecha > NOW() - INTERVAL 6 HOUR');
    $st->execute([$token]);
    $v = $st->fetch();
    if (!$v) return;
    $id = (int) $v['id'];

    switch ($senal) {
        case 'js':
            $pantalla = preg_match('/^\d{2,5}x\d{2,5}$/', $detalle) ? $detalle : '';
            $db->prepare('UPDATE visitas SET js = 1, pantalla = IF(? = "", pantalla, ?) WHERE id = ?')->execute([$pantalla, $pantalla, $id]);
            break;
        case 'interaccion':
            // Movió el mouse, tocó, desplazó o tecleó: se confirma como persona (si no era un bot declarado)
            $db->prepare("UPDATE visitas SET js = 1, interaccion = 1,
                    tipo = IF(tipo IN ('probable','sospechoso'), 'humano', tipo),
                    motivo = IF(tipo = 'humano' AND motivo LIKE 'Sospechoso:%', CONCAT('Interactuó como persona. Antes: ', motivo), 
                             IF(tipo = 'humano', 'Interactuó con la página (movió, tocó o desplazó)', motivo))
                WHERE id = ? AND interaccion = 0")->execute([$id]);
            break;
        case 'salida':
            $seg = max(0, min(3600, (int) $detalle));
            $db->prepare('UPDATE visitas SET duracion_seg = GREATEST(duracion_seg, ?) WHERE id = ?')->execute([$seg, $id]);
            break;
        default:
            if (!isset(EVENTOS_VISITA[$senal])) return;
            $n = $db->prepare('SELECT COUNT(*) FROM visitas_eventos WHERE visita_id = ?');
            $n->execute([$id]);
            if ((int) $n->fetchColumn() >= 50) return;
            $db->prepare('INSERT INTO visitas_eventos (visita_id, fecha, evento, detalle) VALUES (?, ?, ?, ?)')
               ->execute([$id, date('Y-m-d H:i:s'), $senal, mb_substr($detalle, 0, 255)]);
    }
}
