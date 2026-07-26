<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$host       = "meteoservis.cz";
$port       = "5432";
$dbname     = "meteoserver";
$user       = "VOJVODIK";
$password   = "PH3C47b";
$stanice_id = "SKLARE01";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Výpočet času v čtvrtvteřinách od 2000-01-01 00:00:00 UTC+1
    $epoch_2000_utc1   = 946681200;
    $aktualni_cas_utc1 = time() + 3600;

    $cas_do_num = ($aktualni_cas_utc1 - $epoch_2000_utc1) * 4;
    $cas_od_num = $cas_do_num - 345600; // 24h zpět

    $cas_do = sprintf("%.0f", $cas_do_num);
    $cas_od = sprintf("%.0f", $cas_od_num);

    $query = "SELECT * FROM meteoservis_data_viewer.station_records('$stanice_id', $cas_od, $cas_do)";
    $stmt  = $pdo->query($query);
    $rows  = $stmt->fetchAll();

    $aktualni_teplota     = null;
    $min_teplota          = null;
    $max_teplota          = null;
    $celkove_srazky       = 0.0;
    $posledni_aktualizace = null;
    $nejnovejsi_cas       = -1;

    foreach ($rows as $row) {
        $cas_zaznamu = isset($row['Cas']) ? (int)$row['Cas'] : 0;

        if ($cas_zaznamu > $nejnovejsi_cas) {
            $nejnovejsi_cas       = $cas_zaznamu;
            $aktualni_teplota     = (float)$row['Tep2m'];
            $cas_vse_vteriny      = (int)($cas_zaznamu / 4) + $epoch_2000_utc1;
            $posledni_aktualizace = date("d.m.Y H:i:s", $cas_vse_vteriny);
        }

        $temp_curr = (float)$row['Tep2m'];
        $temp_min  = isset($row['Tep2m_I']) ? (float)$row['Tep2m_I'] : $temp_curr;
        $temp_max  = isset($row['Tep2m_X']) ? (float)$row['Tep2m_X'] : $temp_curr;

        $lokalni_min = min($temp_curr, $temp_min);
        if ($min_teplota === null || $lokalni_min < $min_teplota) {
            $min_teplota = $lokalni_min;
        }

        $lokalni_max = max($temp_curr, $temp_max);
        if ($max_teplota === null || $lokalni_max > $max_teplota) {
            $max_teplota = $lokalni_max;
        }

        if (isset($row['Srazky'])) {
            $celkove_srazky += (float)$row['Srazky'];
        }
    }

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'aktualni_teplota'     => $aktualni_teplota,
            'min_teplota'          => $min_teplota,
            'max_teplota'          => $max_teplota,
            'celkove_srazky'       => round($celkove_srazky, 1),
            'posledni_aktualizace' => $posledni_aktualizace
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}