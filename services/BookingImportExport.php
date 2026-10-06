<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Session.php';

/**
 * Native CSV/XLSX import/export service. No Composer dependency is required.
 */
class BookingImportExport {
    public const HEADERS = [
        'Booking ID', 'Agent (Client)', 'Supplier / Vendor', 'Booking Date', 'Passenger Full Name',
        'Passport Number', 'Flight Number', 'Arrival Date (KSA)', 'Departure Date (Exit)', 'Duration / Package',
        'Visa Buy Cost (SAR)', 'Visa Sell Price (SAR)', 'Ticket Buy Cost (SAR)', 'Ticket Sell Price (SAR)',
        'Attach Hotel (Y/N)', 'Attach Transport (Y/N)', 'Notes', 'Status', 'Created By', 'Created At',
        'CustomField1', 'CustomField2'
    ];

    private const SYNONYMS = [
        'Booking ID' => ['booking id','booking_id','id','booking no','booking number','reference'],
        'Agent (Client)' => ['agent','client','agent client','agent name','client name'],
        'Supplier / Vendor' => ['supplier','vendor','supplier vendor','supplier / vendor','vendor name'],
        'Booking Date' => ['booking date','date','booking_date'],
        'Passenger Full Name' => ['passenger','passenger name','passenger full name','mutamer name','name'],
        'Passport Number' => ['passport','passport no','passport number'],
        'Flight Number' => ['flight','flight no','flight number'],
        'Arrival Date (KSA)' => ['arrival date','arrival date ksa','arrival','ksa arrival date'],
        'Departure Date (Exit)' => ['departure date','departure date exit','exit date','departure','return date'],
        'Duration / Package' => ['duration','package','duration package','stay days'],
        'Visa Buy Cost (SAR)' => ['visa buy','visa buy cost','visa cost','buy rate','buy cost'],
        'Visa Sell Price (SAR)' => ['visa sell','visa sell price','visa price','sell rate','sell price'],
        'Ticket Buy Cost (SAR)' => ['ticket buy','ticket buy cost','ticket cost'],
        'Ticket Sell Price (SAR)' => ['ticket sell','ticket sell price','ticket price'],
        'Attach Hotel (Y/N)' => ['attach hotel','hotel','hotel attached'],
        'Attach Transport (Y/N)' => ['attach transport','transport','transport attached'],
        'Notes' => ['notes','remarks','remark','comments'],
        'Status' => ['status','booking status'],
        'Created By' => ['created by','created_by','creator'],
        'Created At' => ['created at','created_at'],
        'CustomField1' => ['customfield1','custom field 1','custom1'],
        'CustomField2' => ['customfield2','custom field 2','custom2'],
    ];

    public static function export(array $filters, string $format): void {
        $rows = self::queryExportRows($filters);
        $format = strtolower($format);
        if ($format === 'csv') self::sendCsv($rows);
        else { http_response_code(400); exit('Unsupported export format.'); }
    }

    public static function queryExportRows(array $filters): array {
        $conditions = ['mb.deleted_at IS NULL'];
        $params = [];
        self::applyFilters($conditions, $params, $filters);
        $where = 'WHERE ' . implode(' AND ', $conditions);
        $sql = "SELECT mb.*, a.name AS agent_name, v.name AS vendor_name,
                       EXISTS(SELECT 1 FROM hotel_stays hs WHERE hs.booking_id = mb.id AND hs.deleted_at IS NULL) AS has_hotel,
                       EXISTS(SELECT 1 FROM transport_transfers tt WHERE tt.booking_id = mb.id AND tt.deleted_at IS NULL) AS has_transport
                FROM master_bookings mb
                LEFT JOIN agents a ON a.id = mb.agent_id
                LEFT JOIN vendors v ON v.id = mb.vendor_id
                {$where}
                ORDER BY mb.booking_date DESC, mb.id DESC";
        $records = Database::fetchAll($sql, $params);
        $out = [];
        foreach ($records as $r) {
            $custom = json_decode((string)($r['custom_fields_json'] ?? '{}'), true) ?: [];
            $out[] = [
                'Booking ID' => $r['booking_code'] ?: (string)$r['id'],
                'Agent (Client)' => $r['agent_name'] ?? '',
                'Supplier / Vendor' => $r['vendor_name'] ?? '',
                'Booking Date' => $r['booking_date'] ?? '',
                'Passenger Full Name' => $r['passenger_name'] ?? '',
                'Passport Number' => $r['passport_number'] ?? '',
                'Flight Number' => $r['flight_number'] ?? '',
                'Arrival Date (KSA)' => $r['arrival_date'] ?? '',
                'Departure Date (Exit)' => $r['departure_date'] ?? '',
                'Duration / Package' => $r['stay_days'] ?? '',
                'Visa Buy Cost (SAR)' => number_format((float)$r['buy_rate_sar'], 2, '.', ''),
                'Visa Sell Price (SAR)' => number_format((float)$r['sell_rate_sar'], 2, '.', ''),
                'Ticket Buy Cost (SAR)' => number_format((float)$r['ticket_buy_rate_sar'], 2, '.', ''),
                'Ticket Sell Price (SAR)' => number_format((float)$r['ticket_sell_rate_sar'], 2, '.', ''),
                'Attach Hotel (Y/N)' => !empty($r['has_hotel']) ? 'Y' : 'N',
                'Attach Transport (Y/N)' => !empty($r['has_transport']) ? 'Y' : 'N',
                'Notes' => $r['remarks'] ?? '',
                'Status' => $r['status'] ?? '',
                'Created By' => $r['created_by'] ?? '',
                'Created At' => $r['created_at'] ?? '',
                'CustomField1' => $r['custom_field1'] ?? ($custom['CustomField1'] ?? ''),
                'CustomField2' => $r['custom_field2'] ?? ($custom['CustomField2'] ?? ''),
                '_id' => (int)$r['id'],
            ];
        }
        return $out;
    }

    public static function stageUploadedFile(array $file): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Unable to upload import file.'];
        }
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv','xlsx'], true)) return ['success' => false, 'message' => 'Import accepts only .csv or .xlsx files.'];
        if ((int)($file['size'] ?? 0) > 10 * 1024 * 1024) return ['success'=>false,'message'=>'Import file exceeds the 10 MB maximum size.'];
        $token = bin2hex(random_bytes(24));
        $dir = __DIR__ . '/../storage/booking_imports';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return ['success'=>false,'message'=>'Unable to create import staging directory.'];
        $path = $dir . DIRECTORY_SEPARATOR . 'import_' . $token . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $path)) return ['success' => false, 'message' => 'Unable to stage import file.'];
        try { $preview=self::previewFile($path); } catch (Throwable $e) { @unlink($path); return ['success'=>false,'message'=>'Unable to preview import file: '.$e->getMessage()]; }
        $stage = ['path'=>$path, 'expires'=>time()+3600, 'filename'=>basename((string)$file['name'])];
        $_SESSION['_booking_imports'][$token] = $stage;
        @file_put_contents($dir . DIRECTORY_SEPARATOR . $token . '.json', json_encode($stage, JSON_UNESCAPED_SLASHES));
        return ['success'=>true,'token'=>$token,'preview'=>$preview['preview'],'headers'=>$preview['headers'],'mapping'=>$preview['mapping'],'validation'=>$preview['validation'],'filename'=>basename((string)$file['name'])];
    }

    public static function confirmImport(string $token, array $mapping): array {
        $staged = $_SESSION['_booking_imports'][$token] ?? null;
        if (!$staged) {
            $metaPath = __DIR__ . '/../storage/booking_imports/' . basename($token) . '.json';
            if (is_file($metaPath)) { $decoded = json_decode((string)@file_get_contents($metaPath), true); if (is_array($decoded)) $staged = $decoded; }
        }
        if (!$staged || (int)($staged['expires'] ?? 0) < time() || !is_file($staged['path'] ?? '')) {
            if ($staged && !empty($staged['path'])) @unlink($staged['path']);
            return ['success' => false, 'message' => 'Import preview has expired or could not be recovered. Please upload the file again.'];
        }
        $rows = self::readFile($staged['path']);
        $headers = $rows['headers'];
        $records = $rows['rows'];
        $normalizedMapping = self::sanitizeMapping($mapping, $headers);
        $errors = [];
        $prepared = [];

        foreach ($records as $line => $row) {
            $mapped = self::mapRow($headers, $row, $normalizedMapping);
            $parsed = self::prepareRow($mapped, $line + 2);
            if (!$parsed['success']) { $errors[] = ['line' => $line + 2, 'message' => implode('; ', $parsed['errors']), 'row' => $row]; continue; }
            $prepared[] = $parsed['data'];
        }

        if ($errors) {
            self::cleanupStage($token);
            return ['success' => false, 'message' => 'Import validation failed. No records were changed.', 'created' => 0, 'updated' => 0, 'failed' => count($errors), 'errors' => $errors, 'failed_csv_base64' => self::failedCsv($headers, $errors)];
        }

        $created = 0; $updated = 0;
        Database::beginTransaction();
        try {
            foreach ($prepared as $row) {
                $existing = null;
                if ($row['booking_id'] !== '') {
                    $existing = Database::fetchOne("SELECT id FROM master_bookings WHERE (booking_code = ? OR CAST(id AS CHAR) = ?) AND deleted_at IS NULL LIMIT 1", [$row['booking_id'], $row['booking_id']]);
                }
                $agentId = self::findAgentId($row['agent']);
                $vendorId = self::findVendorId($row['vendor']);
                $fields = [
                    $row['booking_date'], $agentId, $vendorId, $row['passenger_name'], $row['passport_number'], $row['flight_number'],
                    $row['arrival_date'], $row['departure_date'], $row['stay_days'], $row['visa_buy'], $row['visa_sell'],
                    $row['ticket_buy'], $row['ticket_sell'], $row['notes'], $row['status'], $row['custom_field1'], $row['custom_field2'],
                    json_encode($row['unknown'], JSON_UNESCAPED_UNICODE)
                ];
                if ($existing) {
                    Database::execute(
                        "UPDATE master_bookings SET booking_date=?, agent_id=?, vendor_id=?, passenger_name=?, passport_number=?, flight_number=?, arrival_date=?, departure_date=?, stay_days=?, buy_rate_sar=?, sell_rate_sar=?, ticket_buy_rate_sar=?, ticket_sell_rate_sar=?, remarks=?, status=?, custom_field1=?, custom_field2=?, custom_fields_json=?, updated_by=?, updated_at=NOW() WHERE id=?",
                        [...$fields, Session::getActor(), (int)$existing['id']]
                    );
                    self::audit((int)$existing['id'], 'import_updated', $row);
                    $updated++;
                } else {
                    $bookingCode = self::generateBookingCode();
                    Database::execute(
                        "INSERT INTO master_bookings (booking_code, booking_date, agent_id, vendor_id, passenger_name, passport_number, flight_number, arrival_date, departure_date, stay_days, buy_rate_sar, sell_rate_sar, ticket_buy_rate_sar, ticket_sell_rate_sar, remarks, status, custom_field1, custom_field2, custom_fields_json, created_by, created_at, updated_by, updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW())",
                        [$bookingCode, ...$fields, Session::getActor(), Session::getActor()]
                    );
                    $newId = Database::lastInsertId();
                    self::audit($newId, 'import_created', null, $row);
                    $created++;
                }
            }
            Database::commit();
            self::cleanupStage($token);
            return ['success' => true, 'message' => 'Import completed successfully.', 'created' => $created, 'updated' => $updated, 'failed' => 0];
        } catch (Throwable $e) {
            Database::rollBack();
            self::cleanupStage($token);
            return ['success' => false, 'message' => 'Import rolled back: ' . $e->getMessage(), 'created' => 0, 'updated' => 0, 'failed' => count($prepared)];
        }
    }

    private static function previewFile(string $path): array {
        $data = self::readFile($path, 5);
        $mapping = self::autoMap($data['headers']);
        $validation = [];
        foreach ($data['rows'] as $i => $row) {
            $mapped = self::mapRow($data['headers'], $row, $mapping);
            $p = self::prepareRow($mapped, $i + 2);
            if (!$p['success']) $validation[] = ['line' => $i + 2, 'errors' => $p['errors']];
        }
        return ['headers' => $data['headers'], 'mapping' => $mapping, 'preview' => $data['rows'], 'validation' => $validation];
    }

    private static function prepareRow(array $r, int $line): array {
        $errors = [];
        $dateFields = ['booking_date','arrival_date','departure_date'];
        $dates = [];
        foreach ($dateFields as $f) {
            [$ok,$val] = self::parseDate($r[$f] ?? '');
            if (!$ok && trim((string)($r[$f] ?? '')) !== '') $errors[] = ucfirst(str_replace('_',' ',$f)) . ' has invalid date format';
            $dates[$f] = $val;
        }
        $nums = [];
        foreach (['visa_buy','visa_sell','ticket_buy','ticket_sell'] as $f) {
            [$ok,$val] = self::parseNumber($r[$f] ?? '');
            if (!$ok && trim((string)($r[$f] ?? '')) !== '') $errors[] = ucfirst(str_replace('_',' ',$f)) . ' is not a valid number';
            $nums[$f] = $val;
        }
        $status = trim((string)($r['status'] ?? 'draft')) ?: 'draft';
        $allowed = ['draft','confirmed','issued','completed','cancelled'];
        if (!in_array(strtolower($status), $allowed, true)) $errors[] = 'Status must be draft, confirmed, issued, completed, or cancelled';
        if ($errors) return ['success'=>false,'errors'=>$errors];
        $unknown = (array)($r['_unknown'] ?? []);
        $unknownValues = array_values($unknown);
        $cf1 = trim((string)($r['custom_field1'] ?? ''));
        $cf2 = trim((string)($r['custom_field2'] ?? ''));
        if ($cf1 === '' && isset($unknownValues[0])) $cf1 = trim((string)$unknownValues[0]);
        if ($cf2 === '' && isset($unknownValues[1])) $cf2 = trim((string)$unknownValues[1]);
        return ['success'=>true,'data'=>[
            'booking_id'=>trim((string)($r['booking_id'] ?? '')),'agent'=>trim((string)($r['agent'] ?? '')),'vendor'=>trim((string)($r['vendor'] ?? '')),
            'booking_date'=>$dates['booking_date'],'passenger_name'=>trim((string)($r['passenger_name'] ?? '')),'passport_number'=>strtoupper(trim((string)($r['passport_number'] ?? ''))),
            'flight_number'=>strtoupper(trim((string)($r['flight_number'] ?? ''))),'arrival_date'=>$dates['arrival_date'],'departure_date'=>$dates['departure_date'],
            'stay_days'=>trim((string)($r['stay_days'] ?? '')) ?: null,'visa_buy'=>$nums['visa_buy'],'visa_sell'=>$nums['visa_sell'],'ticket_buy'=>$nums['ticket_buy'],'ticket_sell'=>$nums['ticket_sell'],
            'attach_hotel'=>self::yn($r['attach_hotel'] ?? ''),'attach_transport'=>self::yn($r['attach_transport'] ?? ''),'notes'=>trim((string)($r['notes'] ?? '')) ?: null,
            'status'=>strtolower($status),'custom_field1'=>$cf1 !== '' ? $cf1 : null,'custom_field2'=>$cf2 !== '' ? $cf2 : null,
            'unknown'=>$unknown
        ]];
    }

    private static function mapRow(array $headers, array $row, array $mapping): array {
        $mapped = array_fill_keys(['booking_id','agent','vendor','booking_date','passenger_name','passport_number','flight_number','arrival_date','departure_date','stay_days','visa_buy','visa_sell','ticket_buy','ticket_sell','attach_hotel','attach_transport','notes','status','created_by','created_at','custom_field1','custom_field2'], '');
        $unknown = [];
        foreach ($headers as $i => $header) {
            $value = $row[$i] ?? '';
            $canonical = $mapping[$header] ?? null;
            if ($canonical) $mapped[$canonical] = $value;
            elseif (trim((string)$header) !== '') $unknown[(string)$header] = $value;
        }
        $mapped['_unknown'] = $unknown;
        return $mapped;
    }

    private static function autoMap(array $headers): array {
        $map = [];
        foreach ($headers as $h) {
            $norm = self::norm($h); $best = null; $score = 0;
            foreach (self::SYNONYMS as $canonical => $syns) {
                foreach (array_merge([$canonical], $syns) as $syn) {
                    $s = 0; $n = self::norm($syn);
                    if ($norm === $n) $s = 100;
                    elseif (str_contains($norm, $n) || str_contains($n, $norm)) $s = 85;
                    else { similar_text($norm, $n, $pct); $s = (int)$pct; }
                    if ($s > $score) { $score = $s; $best = $canonical; }
                }
            }
            if ($score >= 70) $map[$h] = self::keyForCanonical($best);
        }
        return $map;
    }

    private static function sanitizeMapping(array $mapping, array $headers): array {
        $out = [];
        foreach ($mapping as $header => $canonical) {
            if (!in_array($header, $headers, true)) continue;
            $canonical = (string)$canonical;
            $key = in_array($canonical, ['booking_id','agent','vendor','booking_date','passenger_name','passport_number','flight_number','arrival_date','departure_date','stay_days','visa_buy','visa_sell','ticket_buy','ticket_sell','attach_hotel','attach_transport','notes','status','created_by','created_at','custom_field1','custom_field2'], true)
                ? $canonical : self::keyForCanonical($canonical);
            if ($key) $out[$header] = $key;
        }
        return $out;
    }

    private static function keyForCanonical(string $canonical): ?string {
        return match ($canonical) {
            'Booking ID' => 'booking_id','Agent (Client)' => 'agent','Supplier / Vendor' => 'vendor','Booking Date' => 'booking_date','Passenger Full Name' => 'passenger_name','Passport Number' => 'passport_number','Flight Number' => 'flight_number','Arrival Date (KSA)' => 'arrival_date','Departure Date (Exit)' => 'departure_date','Duration / Package' => 'stay_days','Visa Buy Cost (SAR)' => 'visa_buy','Visa Sell Price (SAR)' => 'visa_sell','Ticket Buy Cost (SAR)' => 'ticket_buy','Ticket Sell Price (SAR)' => 'ticket_sell','Attach Hotel (Y/N)' => 'attach_hotel','Attach Transport (Y/N)' => 'attach_transport','Notes' => 'notes','Status' => 'status','Created By' => 'created_by','Created At' => 'created_at','CustomField1' => 'custom_field1','CustomField2' => 'custom_field2', default => null
        };
    }

    private static function readFile(string $path, int $limit = PHP_INT_MAX): array {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $ext === 'xlsx' ? self::readXlsx($path, $limit) : self::readCsv($path, $limit);
    }

    private static function readCsv(string $path, int $limit): array {
        $fh = fopen($path, 'rb'); if (!$fh) throw new RuntimeException('Unable to read CSV file.');
        $headers = fgetcsv($fh); if (!$headers) { fclose($fh); throw new RuntimeException('CSV file has no header row.'); }
        $headers = array_map(fn($v)=>(string)$v, $headers);
        if(isset($headers[0])) $headers[0]=preg_replace('/^\xEF\xBB\xBF/','',$headers[0])??$headers[0];
        $delimiter=',';
        $sample=implode(',',array_slice($headers,0,5));
        if(substr_count($sample,';')>substr_count($sample,',')) $delimiter=';';
        elseif(substr_count($sample,"\t")>substr_count($sample,',')) $delimiter="\t";
        if($delimiter!==','){ rewind($fh); $headers=fgetcsv($fh,0,$delimiter)?:[]; $headers=array_map(fn($v)=>(string)$v,$headers); if(isset($headers[0]))$headers[0]=preg_replace('/^\xEF\xBB\xBF/','',$headers[0])??$headers[0]; }
        $rows=[]; $count=0;
        while (($row=fgetcsv($fh,0,$delimiter)) !== false) { $rows[] = array_pad($row, count($headers), ''); if (++$count >= $limit) break; }
        fclose($fh); return ['headers'=>$headers,'rows'=>$rows];
    }

    private static function readXlsx(string $path, int $limit): array {
        if (!class_exists('ZipArchive')) throw new RuntimeException('XLSX import requires the PHP Zip extension. Enable Zip in XAMPP PHP extensions.');
        $zip = new ZipArchive(); if ($zip->open($path)!==true) throw new RuntimeException('Unable to open XLSX file.');
        $shared=[]; $sharedXml=$zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml!==false) { $sx=simplexml_load_string($sharedXml); if ($sx) foreach ($sx->si as $si) $shared[]=(string)$si->t; }
        $sheet=$zip->getFromName('xl/worksheets/sheet1.xml'); if ($sheet===false) { $zip->close(); throw new RuntimeException('XLSX first worksheet was not found.'); }
        $xml=simplexml_load_string($sheet); if (!$xml) { $zip->close(); throw new RuntimeException('Invalid XLSX worksheet XML.'); }
        $ns=$xml->getNamespaces(true); $mainNs=$ns[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $rows=[]; foreach ($xml->children($mainNs)->sheetData->row as $r) { $cells=[]; foreach ($r->c as $c) { $ref=(string)$c['r']; preg_match('/([A-Z]+)\d+/', $ref, $m); $col=self::colIndex($m[1] ?? 'A'); $type=(string)$c['t']; $v=(string)$c->v; if ($type==='s') $value=$shared[(int)$v]??''; elseif ($type==='inlineStr') $value=(string)$c->is->t; else $value=$v; $cells[$col]=$value; } $max=$cells?max(array_keys($cells)):0; $row=array_fill(0,$max+1,''); foreach($cells as $i=>$v)$row[$i]=$v; $rows[]=$row; if(count($rows)>=$limit+1)break; }
        $zip->close(); if (!$rows) throw new RuntimeException('XLSX file contains no rows.');
        $headers=array_map('strval',$rows[0]); array_shift($rows); foreach($rows as &$r)$r=array_pad($r,count($headers),''); return ['headers'=>$headers,'rows'=>$rows];
    }

    private static function colIndex(string $letters): int { $n=0; for($i=0;$i<strlen($letters);$i++)$n=$n*26+(ord($letters[$i])-64); return $n-1; }
    private static function norm(string $s): string { return preg_replace('/[^a-z0-9]+/','',strtolower(trim($s))) ?? ''; }
    private static function parseDate(mixed $v): array {
        $v = trim((string)$v);
        if ($v === '') return [true, null];
        $v = preg_replace('/\s+/', ' ', $v) ?? $v;

        // Excel serial date.
        if (is_numeric($v)) {
            $n = (float)$v;
            if ($n >= 20000 && $n <= 60000) {
                try { return [true, (new DateTimeImmutable('1899-12-30'))->modify('+' . (int)round($n) . ' days')->format('Y-m-d')]; } catch (Throwable $e) {}
            }
        }

        // IMPORTANT: for travel-office imports, a three-part numeric date with
        // a two-digit year is ALWAYS treated as DD-MM-YY / DD/MM/YY.
        // Example: 03-09-26 => 2026-09-03, never year 0026.
        $twoDigit = preg_replace('/[.\/_–—]+/', '-', $v) ?? $v;
        $twoDigit = preg_replace('/\s*-\s*/', '-', $twoDigit) ?? $twoDigit;
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{2})$/', $twoDigit, $m)) {
            $day=(int)$m[1]; $month=(int)$m[2]; $year=2000+(int)$m[3];
            if ($day >= 1 && $day <= 31 && $month >= 1 && $month <= 12) {
                try {
                    $d = DateTimeImmutable::createFromFormat('!Y-n-j', "$year-$month-$day");
                    $errors = DateTimeImmutable::getLastErrors();
                    $bad = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
                    if ($d && !$bad && (int)$d->format('Y') === $year && (int)$d->format('n') === $month && (int)$d->format('j') === $day) {
                        return [true, $d->format('Y-m-d')];
                    }
                } catch (Throwable $e) {}
            }
        }

        // Four-digit numeric dates. Ambiguous values default to DMY because
        // this system is used with Pakistan/KSA travel-office spreadsheets.
        $numeric = str_replace(['.', '_', '–', '—'], ['/', '/', '-', '-'], $v);
        $numeric = preg_replace('/\s*[-\/]\s*/', '-', $numeric) ?? $numeric;
        if (preg_match('/^(\d{1,4})-(\d{1,2})-(\d{1,4})$/', $numeric, $m)) {
            $a=(int)$m[1]; $b=(int)$m[2]; $c=(int)$m[3];
            if ($a >= 1000 && $b >= 1 && $b <= 12 && $c >= 1 && $c <= 31) {
                return [true, sprintf('%04d-%02d-%02d', $a, $b, $c)];
            }
            if ($c >= 1000 && $a >= 1 && $a <= 31 && $b >= 1 && $b <= 12) {
                return [true, sprintf('%04d-%02d-%02d', $c, $b, $a)];
            }
            if ($c >= 1000 && $a >= 1 && $a <= 12 && $b >= 1 && $b <= 31) {
                return [true, sprintf('%04d-%02d-%02d', $c, $a, $b)];
            }
        }

        $clean = str_replace(['.', '_', '–', '—'], ['/', '/', '-', '-'], $v);
        $clean = preg_replace('/\s*\/\s*/', '/', $clean) ?? $clean;
        $clean = preg_replace('/\s*-\s*/', '-', $clean) ?? $clean;
        $formats = [
            'Y-m-d','Y/m/d','Y.m.d','Ymd',
            'd/m/Y','d-m-Y','d.m.Y','d/m/y','d-m-y','d.m.y',
            'm/d/Y','m-d-Y','m.d.Y','m/d/y','m-d-y','m.d.y',
            'd M Y','d-M-Y','d F Y','d-F-Y','M d Y','F d Y',
            'd M, Y','d F, Y','M d, Y','F d, Y',
        ];
        foreach ($formats as $f) {
            $d = DateTimeImmutable::createFromFormat('!' . $f, $clean);
            $errors = DateTimeImmutable::getLastErrors();
            $bad = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
            if ($d && !$bad) {
                // Never allow a two-digit year to become year 00xx.
                if ((int)$d->format('Y') < 100) $d = $d->modify('+2000 years');
                return [true, $d->format('Y-m-d')];
            }
        }
        return [false, null];
    }

    private static function parseNumber(mixed $v): array {
        $v = trim((string)$v);
        if ($v === '') return [true, 0.0];
        $v = str_replace([',', ' ', 'SAR', 'sar', '﷼'], '', $v);
        $v = preg_replace('/[^0-9.\-]/', '', $v) ?? $v;
        if ($v === '' || !is_numeric($v)) return [false, 0.0];
        return [true, round((float)$v, 2)];
    }
    private static function yn(mixed $v): string { return in_array(strtoupper(trim((string)$v)),['Y','YES','1','TRUE'],true)?'Y':'N'; }
    private static function findAgentId(string $name): ?int { if($name==='')return null; $r=Database::fetchOne("SELECT id FROM agents WHERE name=? OR company_name=? LIMIT 1",[$name,$name]); return $r?(int)$r['id']:null; }
    private static function findVendorId(string $name): ?int { if($name==='')return null; $r=Database::fetchOne("SELECT id FROM vendors WHERE name=? OR company_name=? LIMIT 1",[$name,$name]); return $r?(int)$r['id']:null; }
    private static function generateBookingCode(): string { do{$code='AHT-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(3)));$exists=Database::fetchValue("SELECT id FROM master_bookings WHERE booking_code=?",[$code]);}while($exists); return $code; }
    private static function applyFilters(array &$conditions,array &$params,array $f): void { if(!empty($f['booking_id'])){$conditions[]='mb.id=?';$params[]=(int)$f['booking_id'];} if(!empty($f['search'])){$q='%'.trim((string)$f['search']).'%';$conditions[]='(mb.booking_code LIKE ? OR mb.passenger_name LIKE ? OR mb.passport_number LIKE ? OR mb.flight_number LIKE ?)';array_push($params,$q,$q,$q,$q);} if(!empty($f['date_from'])){$conditions[]='mb.booking_date>=?';$params[]=$f['date_from'];} if(!empty($f['date_to'])){$conditions[]='mb.booking_date<=?';$params[]=$f['date_to'];} if(!empty($f['month'])){$conditions[]="DATE_FORMAT(mb.booking_date,'%Y-%m')=?";$params[]=$f['month'];} if(!empty($f['year'])){$conditions[]='YEAR(mb.booking_date)=?';$params[]=(int)$f['year'];} if(!empty($f['agent_id'])){$conditions[]='mb.agent_id=?';$params[]=(int)$f['agent_id'];} if(!empty($f['vendor_id'])){$conditions[]='mb.vendor_id=?';$params[]=(int)$f['vendor_id'];} if(!empty($f['status'])){$conditions[]='mb.status=?';$params[]=$f['status'];} }

    private static function sendCsv(array $rows): void { header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="aeroheights-master-bookings-'.date('Y-m-d').'.csv"'); $out=fopen('php://output','wb'); fputcsv($out,self::HEADERS); foreach($rows as $r)fputcsv($out,array_map(fn($h)=>$r[$h]??'',self::HEADERS)); fclose($out); exit; }

    private static function colName(int $n): string {$s='';do{$s=chr(65+($n%26)).$s;$n=intdiv($n,26)-1;}while($n>=0);return $s;}

    private static function cleanupStage(string $token): void { $s=$_SESSION['_booking_imports'][$token]??null; if(!$s){$meta=__DIR__.'/../storage/booking_imports/'.basename($token).'.json';if(is_file($meta))$s=json_decode((string)@file_get_contents($meta),true);} if($s&&is_file($s['path']??''))@unlink($s['path']); $meta=__DIR__.'/../storage/booking_imports/'.basename($token).'.json'; if(is_file($meta))@unlink($meta); unset($_SESSION['_booking_imports'][$token]); }
    private static function failedCsv(array $headers,array $errors): string {$fh=fopen('php://temp','w+');$out=array_merge($headers,['Error']);fputcsv($fh,$out);foreach($errors as $e){$row=$e['row'];$row[]=$e['message'];fputcsv($fh,$row);}rewind($fh);return base64_encode(stream_get_contents($fh));}
    private static function sendError(string $m): never {throw new RuntimeException($m);}
    private static function audit(int $bookingId,string $action,?array $old,?array $new):void{Database::execute("INSERT INTO audit_logs(entity_type,entity_id,action,old_values,new_values,created_by,created_at) VALUES('master_booking',?,?,?,?,?,NOW())",[$bookingId,$action,$old?json_encode($old):null,$new?json_encode($new):null,Session::getActor()]);}
}
