<?php
declare(strict_types=1);

date_default_timezone_set('America/Bogota');
$startTime = microtime(true);

/* =========================
   CONFIG
========================= */
$config = [
    // Dominus OAuth
    'token_url' => 'https://dominus.iapropiada.com/oauth/v2/token',
    'sales_url' => 'https://dominus.iapropiada.com/integrations/administrative/dominus/sales',
    'client_id' => 'ZxTXZ6cnVRmEkweLEhjCgcyJD',
    'client_secret' => '3fWBCc2VwFFB3M4VZzfYGU4k3SMZRJ0KbRp4EHgJ',
    'scope' => 'dominus',

    // DB
    'db_host' => '127.0.0.1',
    'db_user' => 'dominus',
    'db_pass' => 'TuPasswordFuerte',
    'db_name' => 'dominus_ingest',
    'db_port' => 3306,

    // Tablas
    'table_sales' => 'dominus_sales',
    'table_runs'  => 'dominus_runs',

    // Branch mappings
    'branches' => [
        ['branch_id' => 635,  'id_eds' => 3],
        ['branch_id' => 2023, 'id_eds' => 4],
        ['branch_id' => 553,  'id_eds' => 5],
        ['branch_id' => 12,   'id_eds' => 6],
        ['branch_id' => 1740, 'id_eds' => 7],
        ['branch_id' => 975,  'id_eds' => 8],
        ['branch_id' => 744,  'id_eds' => 9],
        ['branch_id' => 1155, 'id_eds' => 10],
        ['branch_id' => 1948, 'id_eds' => 11],
        ['branch_id' => 497,  'id_eds' => 2],
    ],

    // Ventana de consulta
    'safety_lag_minutes' => 10,
    'lookback_minutes'   => 1440,
    'first_run_lookback_days' => 1,

    // Timeouts
    'token_timeout' => 30,
    'sales_timeout' => 60,   // conserva performance; sube solo si Dominus está lento
    'connect_timeout' => 15, // evita quedarse pegado conectando

    // Retries (suaves, sin volarte el tiempo)
    'sales_retries' => 1,    // 0 o 1 recomendado para mantener ~77s
    'sales_retry_sleep_ms' => 200,

    // Directorio (scripts)
    'dir' => __DIR__,
];

/* =========================
   FILE PATHS
========================= */
$paths = [
    'token'        => $config['dir'] . '/token.txt',
    'token_exp'    => $config['dir'] . '/token_expires.txt',
    'cursor'       => $config['dir'] . '/last_cursor.txt',
    'lock'         => $config['dir'] . '/dominus_ingest.lock',
    'log'          => $config['dir'] . '/dominus_ingest.log',
];

/* =========================
   LOGGING
========================= */
function logLine(string $msg, string $logFile): void {
    file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

/* =========================
   LOCK
========================= */
function acquireLock(string $lockFile, string $logFile) {
    $fp = fopen($lockFile, 'c+');
    if (!$fp) {
        logLine("ERROR: no se pudo abrir lockfile: $lockFile", $logFile);
        return false;
    }
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        logLine("INFO: proceso ya en ejecución. Saliendo.", $logFile);
        fclose($fp);
        return false;
    }
    ftruncate($fp, 0);
    fwrite($fp, (string)getmypid());
    fflush($fp);
    return $fp;
}
function releaseLock($fp): void {
    flock($fp, LOCK_UN);
    fclose($fp);
}

/* =========================
   TOKEN
========================= */
function getToken(array $config, array $paths): ?string {
    if (file_exists($paths['token']) && file_exists($paths['token_exp'])) {
        $exp = (int)trim((string)file_get_contents($paths['token_exp']));
        if (time() < $exp) {
            return trim((string)file_get_contents($paths['token']));
        }
    }

    $payload = json_encode([
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'grant_type' => 'client_credentials',
        'scope' => $config['scope'],
    ], JSON_UNESCAPED_SLASHES);

    $ch = curl_init($config['token_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $config['token_timeout'],
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $out  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($out === false || $code < 200 || $code >= 300) {
        logLine("ERROR token HTTP=$code err=$err", $paths['log']);
        return null;
    }

    $json = json_decode($out, true);
    if (!is_array($json) || empty($json['access_token'])) {
        logLine("ERROR token respuesta inválida", $paths['log']);
        return null;
    }

    $token = (string)$json['access_token'];
    $expiresIn = (int)($json['expires_in'] ?? 3600);

    $margin = 300;
    $expAt = time() + max(60, $expiresIn - $margin);

    file_put_contents($paths['token'], $token);
    file_put_contents($paths['token_exp'], (string)$expAt);

    return $token;
}

/* =========================
   CURSOR (VENTANA)
========================= */
function getWindow(array $config, array $paths): array {
    $hastaTs = time() - ($config['safety_lag_minutes'] * 60);
    $hasta = date('Y-m-d H:i:s', $hastaTs);

    $cursor = null;
    if (file_exists($paths['cursor'])) {
        $tmp = trim((string)file_get_contents($paths['cursor']));
        $cursor = ($tmp !== '') ? $tmp : null;
    }

    if ($cursor === null) {
        $desde = date('Y-m-d 00:00:00', strtotime('-' . $config['first_run_lookback_days'] . ' day'));
    } else {
        $desdeTs = strtotime($cursor) - ($config['lookback_minutes'] * 60);
        $desde = date('Y-m-d H:i:s', $desdeTs);
    }

    return [$desde, $hasta];
}
function saveCursor(string $hasta, array $paths): void {
    file_put_contents($paths['cursor'], $hasta);
}

/* =========================
   DB
========================= */
function dbConnect(array $config, array $paths): ?mysqli {
    $mysqli = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_pass'],
        $config['db_name'],
        $config['db_port']
    );
    if ($mysqli->connect_error) {
        logLine("ERROR DB connect: " . $mysqli->connect_error, $paths['log']);
        return null;
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

/* =========================
   RUNS
========================= */
function createRun(mysqli $db, array $config, string $runDateYmd, int $branchesCount): int {
    $sql = "
        INSERT INTO {$config['table_runs']}
        (date, started_at, status, branches, inserted, updated, skipped, error, created_at, updated_at)
        VALUES (?, NOW(), 'running', ?, 0, 0, 0, NULL, NOW(), NOW())
    ";
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException("Prepare RUN insert falló: " . $db->error);

    $stmt->bind_param('si', $runDateYmd, $branchesCount);
    if (!$stmt->execute()) throw new RuntimeException("RUN insert error: " . $stmt->error);

    $runId = (int)$db->insert_id;
    $stmt->close();
    return $runId;
}
function finishRunOk(mysqli $db, array $config, int $runId, int $ins, int $upd, int $skip): void {
    $sql = "
        UPDATE {$config['table_runs']}
        SET finished_at = NOW(),
            status = 'ok',
            inserted = ?,
            updated  = ?,
            skipped  = ?,
            updated_at = NOW()
        WHERE id = ?
    ";
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException("Prepare RUN ok falló: " . $db->error);

    $stmt->bind_param('iiii', $ins, $upd, $skip, $runId);
    if (!$stmt->execute()) throw new RuntimeException("RUN ok error: " . $stmt->error);
    $stmt->close();
}
function finishRunFail(mysqli $db, array $config, int $runId, string $errorMsg): void {
    $sql = "
        UPDATE {$config['table_runs']}
        SET finished_at = NOW(),
            status = 'failed',
            error = ?,
            updated_at = NOW()
        WHERE id = ?
    ";
    $stmt = $db->prepare($sql);
    if (!$stmt) return;

    $errorMsg = mb_substr($errorMsg, 0, 65000);
    $stmt->bind_param('si', $errorMsg, $runId);
    $stmt->execute();
    $stmt->close();
}

/* =========================
   FETCH SALES (optimizado, sin reventar tiempo)
========================= */
function fetchSales(string $token, int $branchId, string $dateYmd, array $config, array $paths): ?array {
    $payload = json_encode(['date' => $dateYmd, 'branch_id' => $branchId], JSON_UNESCAPED_SLASHES);

    $tries = 0;
    $maxTries = 1 + max(0, (int)($config['sales_retries'] ?? 0));

    while ($tries < $maxTries) {
        $tries++;

        $ch = curl_init($config['sales_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => (int)$config['connect_timeout'],
            CURLOPT_TIMEOUT => (int)$config['sales_timeout'],
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $token",
                "Content-Type: application/json",
            ],
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $out  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($out !== false && $code >= 200 && $code < 300) {
            $json = json_decode($out, true);
            $sales = $json['data']['sales'] ?? null;
            return is_array($sales) ? $sales : [];
        }

        // si fue timeout u otro error, reintenta 1 vez (máximo) y ya
        if ($tries < $maxTries) {
            usleep(((int)$config['sales_retry_sleep_ms']) * 1000);
            continue;
        }

        logLine("ERROR ventas branch=$branchId date=$dateYmd HTTP=$code err=$err", $paths['log']);
        return null;
    }

    return null;
}

/* =========================
   WINDOW CHECK (rápido)
========================= */
function inWindowTs(?string $fechaFactura, int $desdeTs, int $hastaTs): bool {
    if (!$fechaFactura) return false;
    $ts = strtotime($fechaFactura);
    return ($ts >= $desdeTs && $ts <= $hastaTs);
}

/* =========================
   PREPARE UPSERT (1 sola vez)
========================= */
function prepareUpsert(mysqli $db, array $config): mysqli_stmt {
    $table = $config['table_sales'];

    $sqlUpsert = "
        INSERT INTO {$table} (
            run_id, id_eds, no_pedido, no_factura, fecha_factura,
            doc_vend, nit_cliente, nombre_cliente, convenio,
            panel, cara, placa, km, otro,
            producto, referencia,
            cantidad, ppu, iva, ipoconsumo, total,
            created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
            placa = CASE
                WHEN (IFNULL(placa,'') = '' AND IFNULL(VALUES(placa),'') <> '') THEN VALUES(placa)
                ELSE placa
            END,
            cantidad   = CASE WHEN cantidad   <> VALUES(cantidad)   THEN VALUES(cantidad)   ELSE cantidad   END,
            ppu        = CASE WHEN ppu        <> VALUES(ppu)        THEN VALUES(ppu)        ELSE ppu        END,
            total      = CASE WHEN total      <> VALUES(total)      THEN VALUES(total)      ELSE total      END,
            iva        = CASE WHEN iva        <> VALUES(iva)        THEN VALUES(iva)        ELSE iva        END,
            ipoconsumo = CASE WHEN ipoconsumo <> VALUES(ipoconsumo) THEN VALUES(ipoconsumo) ELSE ipoconsumo END,
            km         = CASE WHEN km         <> VALUES(km)         THEN VALUES(km)         ELSE km         END,
            convenio = CASE
                WHEN IFNULL(convenio,'') <> IFNULL(VALUES(convenio),'') THEN VALUES(convenio)
                ELSE convenio
            END,
            run_id = run_id,
            updated_at = IF(
                (IFNULL(placa,'') = '' AND IFNULL(VALUES(placa),'') <> '')
                OR cantidad   <> VALUES(cantidad)
                OR ppu        <> VALUES(ppu)
                OR total      <> VALUES(total)
                OR iva        <> VALUES(iva)
                OR ipoconsumo <> VALUES(ipoconsumo)
                OR km         <> VALUES(km)
                OR IFNULL(convenio,'') <> IFNULL(VALUES(convenio),''),
                NOW(),
                updated_at
            )
    ";

    $stmt = $db->prepare($sqlUpsert);
    if (!$stmt) throw new RuntimeException("Prepare UPSERT falló: " . $db->error);
    return $stmt;
}

/* =========================
   INGESTA (misma esencia, menos skips tontos, sin logs extra)
   - Calcula strtotime UNA vez por venta
   - Producto: usa name si viene, si no, usa code (evita perder data)
========================= */
function ingestSales(mysqli_stmt $stmtUpsert, array $sales, int $runId, int $idEds, int $desdeTs, int $hastaTs): array {
    $inserted  = 0;
    $updated   = 0;
    $unchanged = 0;
    $skipped   = 0;

    foreach ($sales as $sale) {
        $no_pedido  = trim((string)($sale['document'] ?? ''));
        $fechaDoc   = $sale['date_sale'] ?? null;

        if ($no_pedido === '' || !$fechaDoc) { $skipped++; continue; }

        $ts = strtotime((string)$fechaDoc);
        if ($ts < $desdeTs || $ts > $hastaTs) { $skipped++; continue; }

        // Campos opcionales
        $no_factura = isset($sale['is_bill']) ? (string)$sale['is_bill'] : null;
        $doc_vend   = isset($sale['employee_document']) ? (string)$sale['employee_document'] : null;

        $customerDoc = isset($sale['customer_document']) ? (string)$sale['customer_document'] : null;
        $first = $sale['customer_first_name'] ?? '';
        $last  = $sale['customer_last_name'] ?? '';
        $nombreCliente = trim((string)$first . ' ' . (string)$last);
        $nombreCliente = ($nombreCliente !== '') ? $nombreCliente : null;

        $convenio = 'Efectivo';
        if (isset($sale['payment']) && is_array($sale['payment'])) {
            foreach ($sale['payment'] as $pago) {
                if (!empty($pago['name'])) { $convenio = (string)$pago['name']; break; }
            }
        }

        $panel = isset($sale['terminal_code']) ? (string)$sale['terminal_code'] : null;
        $cara  = isset($sale['face']) ? (string)$sale['face'] : null;
        $placa = isset($sale['plate']) ? (string)$sale['plate'] : '';
        $km    = isset($sale['odometer']) && $sale['odometer'] !== '' ? (int)$sale['odometer'] : 0;
        $otro  = isset($sale['wildcard']) ? (string)$sale['wildcard'] : null;

        $products = $sale['products'] ?? [];
        if (!is_array($products) || empty($products)) { $skipped++; continue; }

        foreach ($products as $product) {
            $nombreProd = trim((string)($product['product_name'] ?? ''));
            $codProd    = trim((string)($product['product_code'] ?? ''));

            $producto = ($nombreProd !== '') ? $nombreProd : $codProd;
            if ($producto === '') { $skipped++; continue; }

            $referencia = ($codProd !== '') ? $codProd : null;

            // Redondeo (evita updates fantasma por float)
            $cantidad = round((float)($product['quantity'] ?? 0), 3);
            $ppu      = round((float)($product['price'] ?? 0), 2);
            $iva      = round((float)($product['iva'] ?? 0), 2);
            $ipoc     = round((float)($product['impoconsumo'] ?? 0), 2);
            $total    = round((float)($product['total'] ?? 0), 2);

            $stmtUpsert->bind_param(
                'ii' . str_repeat('s', 10) . 'i' . 's' . 'ss' . str_repeat('d', 5),
                $runId,
                $idEds,
                $no_pedido,
                $no_factura,
                $fechaDoc,
                $doc_vend,
                $customerDoc,
                $nombreCliente,
                $convenio,
                $panel,
                $cara,
                $placa,
                $km,
                $otro,
                $producto,
                $referencia,
                $cantidad,
                $ppu,
                $iva,
                $ipoc,
                $total
            );

            if (!$stmtUpsert->execute()) {
                throw new RuntimeException("UPSERT error: " . $stmtUpsert->error);
            }

            $aff = $stmtUpsert->affected_rows; // 1 insert, 2 update real, 0 unchanged
            if ($aff === 1) $inserted++;
            elseif ($aff === 2) $updated++;
            else $unchanged++;
        }
    }

    return compact('inserted','updated','unchanged','skipped');
}

/* =========================
   MAIN
========================= */
$lockFp = acquireLock($paths['lock'], $paths['log']);
if ($lockFp === false) exit(0);

logLine("=== INICIO ingest ===", $paths['log']);

$runId = 0;
$db = null;

try {
    $token = getToken($config, $paths);
    if (!$token) throw new RuntimeException("No se pudo obtener token.");

    [$desde, $hasta] = getWindow($config, $paths);
    $desdeTs = strtotime($desde);
    $hastaTs = strtotime($hasta);

    logLine("Ventana: desde=$desde hasta=$hasta (lag={$config['safety_lag_minutes']}m lookback={$config['lookback_minutes']}m)", $paths['log']);

    $db = dbConnect($config, $paths);
    if (!$db) throw new RuntimeException("No se pudo conectar a BD.");

    $runDateYmd = substr($desde, 0, 10);
    $runId = createRun($db, $config, $runDateYmd, count($config['branches']));
    logLine("RUN creado id=$runId date=$runDateYmd", $paths['log']);

    $stmtUpsert = prepareUpsert($db, $config);
    $db->autocommit(false);

    $fromDay = new DateTime(substr($desde, 0, 10));
    $toDay   = new DateTime(substr($hasta, 0, 10));
    $toDay->setTime(0,0,0);

    $totIns = 0; $totUpd = 0; $totUnc = 0; $totSkip = 0;

    for ($day = clone $fromDay; $day <= $toDay; $day->modify('+1 day')) {
        $dateYmd = $day->format('Y-m-d');

        foreach ($config['branches'] as $mapping) {
            $branchId = (int)$mapping['branch_id'];
            $idEds    = (int)$mapping['id_eds'];

            $sales = fetchSales($token, $branchId, $dateYmd, $config, $paths);
            if ($sales === null) continue;
            if (empty($sales)) continue;

            $st = ingestSales($stmtUpsert, $sales, $runId, $idEds, $desdeTs, $hastaTs);

            $totIns  += $st['inserted'];
            $totUpd  += $st['updated'];
            $totUnc  += $st['unchanged'];
            $totSkip += $st['skipped'];

            logLine(
                "branch=$branchId eds=$idEds day=$dateYmd => ins={$st['inserted']} upd={$st['updated']} unchanged={$st['unchanged']} skipped={$st['skipped']}",
                $paths['log']
            );
        }
    }

    $db->commit();
    $db->autocommit(true);
    $stmtUpsert->close();

    finishRunOk($db, $config, $runId, $totIns, $totUpd, $totSkip);
    saveCursor($hasta, $paths);

    $elapsed = number_format(microtime(true) - $startTime, 2);
    logLine("=== FIN OK run_id=$runId ins=$totIns upd=$totUpd unchanged=$totUnc skipped=$totSkip t={$elapsed}s ===", $paths['log']);

} catch (Throwable $e) {
    logLine("=== FIN FAIL: " . $e->getMessage(), $paths['log']);

    if ($db instanceof mysqli) {
        @ $db->rollback();
        @ $db->autocommit(true);

        if ($runId > 0) finishRunFail($db, $config, $runId, $e->getMessage());
        @ $db->close();
    }
} finally {
    releaseLock($lockFp);
}
