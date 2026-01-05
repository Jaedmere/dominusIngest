<?php
declare(strict_types=1);

/**
 * Dominus Ingest (PHP CLI) - QAP
 * - Token cacheado
 * - Ventana con margen de seguridad (evita perder ventas del final del día)
 * - Dedupe por (no_pedido, producto, fecha_factura) + UNIQUE en BD
 * - Logs a archivo propio
 * - Lock file para evitar doble ejecución
 * - Crea dominus_runs y amarra dominus_sales.run_id
 */

date_default_timezone_set('America/Bogota');
$startTime = microtime(true);

/* =========================
   CONFIG (AJUSTA ESTO)
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
    'safety_lag_minutes' => 10,     // hasta = ahora - 10 min
    'lookback_minutes'   => 30,     // retroceso adicional por si API llega tarde
    'first_run_lookback_days' => 1, // si no existe cursor, arranca desde ayer 00:00

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
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
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
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $out = curl_exec($ch);
    $err = curl_error($ch);
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

    // margen: 5 min
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

    // recortamos para que no reviente la columna text igual aguanta, pero mejor limpio:
    $errorMsg = mb_substr($errorMsg, 0, 65000);
    $stmt->bind_param('si', $errorMsg, $runId);
    $stmt->execute();
    $stmt->close();
}

/* =========================
   FETCH SALES
========================= */
function fetchSales(string $token, int $branchId, string $dateYmd, array $config, array $paths): ?array {
    $payload = json_encode(['date' => $dateYmd, 'branch_id' => $branchId], JSON_UNESCAPED_SLASHES);

    $ch = curl_init($config['sales_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Type: application/json",
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $out = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($out === false || $code < 200 || $code >= 300) {
        logLine("ERROR ventas branch=$branchId date=$dateYmd HTTP=$code err=$err", $paths['log']);
        return null;
    }

    $json = json_decode($out, true);
    $sales = $json['data']['sales'] ?? null;

    if (!is_array($sales)) {
        logLine("INFO sin ventas branch=$branchId date=$dateYmd", $paths['log']);
        return [];
    }
    return $sales;
}

/* =========================
   FILTRO POR VENTANA
========================= */
function inWindow(?string $fechaFactura, string $desde, string $hasta): bool {
    if (!$fechaFactura) return false;
    $ts = strtotime($fechaFactura);
    return ($ts >= strtotime($desde) && $ts <= strtotime($hasta));
}

/* =========================
   INGESTA (AHORA CON run_id)
========================= */
function ingestSales(mysqli $db, array $sales, int $runId, int $idEds, string $desde, string $hasta, array $config): array {
    $inserted = 0;
    $updated = 0;
    $skipped = 0;

    // OJO: ya NO va NULL, va run_id real
    $sqlInsert = "
        INSERT IGNORE INTO {$config['table_sales']}
        (run_id, id_eds, no_pedido, no_factura, fecha_factura, doc_vend, nit_cliente, nombre_cliente, convenio, panel, cara, placa, km, otro, producto, referencia, cantidad, ppu, iva, ipoconsumo, total, created_at, updated_at)
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";
    $stmtInsert = $db->prepare($sqlInsert);
    if (!$stmtInsert) throw new RuntimeException("Prepare INSERT falló: " . $db->error);

    $sqlUpdatePlaca = "
        UPDATE {$config['table_sales']}
        SET placa = ?, updated_at = NOW(), run_id = COALESCE(run_id, ?)
        WHERE no_pedido = ? AND producto = ? AND fecha_factura = ?
          AND (placa IS NULL OR placa = '' OR placa <> ?)
    ";
    $stmtUpdate = $db->prepare($sqlUpdatePlaca);
    if (!$stmtUpdate) throw new RuntimeException("Prepare UPDATE falló: " . $db->error);

    foreach ($sales as $sale) {
        $no_pedido  = (string)($sale['document'] ?? '');
        $no_factura = isset($sale['is_bill']) ? (string)$sale['is_bill'] : null;
        $fechaDoc   = $sale['date_sale'] ?? null;

        if ($no_pedido === '' || !$fechaDoc) { $skipped++; continue; }
        if (!inWindow($fechaDoc, $desde, $hasta)) { $skipped++; continue; }

        $doc_vend = isset($sale['employee_document']) ? (string)$sale['employee_document'] : null;

        $customerDoc = isset($sale['customer_document']) ? (string)$sale['customer_document'] : null;
        $first = $sale['customer_first_name'] ?? '';
        $last  = $sale['customer_last_name'] ?? '';
        $nombreCliente = trim($first . ' ' . $last);
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
            $producto = $product['product_name'] ?? null;
            if (!$producto) { $skipped++; continue; }
            $producto = (string)$producto;

            $referencia = isset($product['product_code']) ? (string)$product['product_code'] : null;

            $cantidad = (float)($product['quantity'] ?? 0);
            $ppu      = (float)($product['price'] ?? 0);
            $iva      = (float)($product['iva'] ?? 0);
            $ipoc     = (float)($product['impoconsumo'] ?? 0);
            $total    = (float)($product['total'] ?? 0);

            // 21 valores -> types deben coincidir
            $stmtInsert->bind_param(
                'ii' . str_repeat('s', 10) . 'i' . 's' . 'ss' . str_repeat('d', 5),
                $runId,         // i
                $idEds,         // i
                $no_pedido,     // s
                $no_factura,    // s
                $fechaDoc,      // s
                $doc_vend,      // s
                $customerDoc,   // s
                $nombreCliente, // s
                $convenio,      // s
                $panel,         // s
                $cara,          // s
                $placa,         // s
                $km,            // i
                $otro,          // s
                $producto,      // s
                $referencia,    // s
                $cantidad,      // d
                $ppu,           // d
                $iva,           // d
                $ipoc,          // d
                $total          // d
            );

            if (!$stmtInsert->execute()) {
                throw new RuntimeException("INSERT error: " . $stmtInsert->error);
            }

            if ($stmtInsert->affected_rows === 1) {
                $inserted++;
            } else {
                // existía: si placa cambió, actualizamos placa y garantizamos run_id si estaba null
                $stmtUpdate->bind_param('sissss', $placa, $runId, $no_pedido, $producto, $fechaDoc, $placa);
                if (!$stmtUpdate->execute()) {
                    throw new RuntimeException("UPDATE error: " . $stmtUpdate->error);
                }
                $updated += ($stmtUpdate->affected_rows > 0) ? 1 : 0;
            }
        }
    }

    $stmtInsert->close();
    $stmtUpdate->close();

    return compact('inserted', 'updated', 'skipped');
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
    logLine("Ventana: desde=$desde hasta=$hasta (lag={$config['safety_lag_minutes']}m lookback={$config['lookback_minutes']}m)", $paths['log']);

    $db = dbConnect($config, $paths);
    if (!$db) throw new RuntimeException("No se pudo conectar a BD.");

    // 1) crear RUN (se deja persistido)
    $runDateYmd = substr($desde, 0, 10);
    $runId = createRun($db, $config, $runDateYmd, count($config['branches']));
    logLine("RUN creado id=$runId date=$runDateYmd", $paths['log']);

    // 2) transacción SOLO para ventas
    $db->autocommit(false);

    $fromDay = new DateTime(substr($desde, 0, 10));
    $toDay   = new DateTime(substr($hasta, 0, 10));
    $toDay->setTime(0,0,0);

    $totalInserted = 0;
    $totalUpdated  = 0;
    $totalSkipped  = 0;

    for ($day = clone $fromDay; $day <= $toDay; $day->modify('+1 day')) {
        $dateYmd = $day->format('Y-m-d');

        foreach ($config['branches'] as $mapping) {
            $branchId = (int)$mapping['branch_id'];
            $idEds    = (int)$mapping['id_eds'];

            $sales = fetchSales($token, $branchId, $dateYmd, $config, $paths);
            if ($sales === null || empty($sales)) continue;

            $stats = ingestSales($db, $sales, $runId, $idEds, $desde, $hasta, $config);
            $totalInserted += $stats['inserted'];
            $totalUpdated  += $stats['updated'];
            $totalSkipped  += $stats['skipped'];

            logLine("branch=$branchId eds=$idEds day=$dateYmd => ins={$stats['inserted']} upd={$stats['updated']} skip={$stats['skipped']}", $paths['log']);
        }
    }

    $db->commit();
    $db->autocommit(true);

    // 3) cerrar RUN ok
    finishRunOk($db, $config, $runId, $totalInserted, $totalUpdated, $totalSkipped);

    // 4) guardar cursor
    saveCursor($hasta, $paths);

    $elapsed = number_format(microtime(true) - $startTime, 2);
    logLine("=== FIN OK run_id=$runId ins=$totalInserted upd=$totalUpdated skip=$totalSkipped t={$elapsed}s ===", $paths['log']);

} catch (Throwable $e) {
    logLine("=== FIN FAIL: " . $e->getMessage(), $paths['log']);

    if ($db instanceof mysqli) {
        // rollback ventas si estaba en transacción
        @ $db->rollback();
        @ $db->autocommit(true);

        // marcar run como failed si alcanzó a crearse
        if ($runId > 0) {
            finishRunFail($db, $config, $runId, $e->getMessage());
        }

        @ $db->close();
    }
} finally {
    releaseLock($lockFp);
}
