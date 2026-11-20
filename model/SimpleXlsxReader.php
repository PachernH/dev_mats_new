<?php
class SimpleXlsxReader {
    /**
     * อ่านไฟล์ .xlsx และคืนค่าข้อมูลเป็น Array 2 มิติ (ระบุชื่อ Sheet ได้ เช่น 'master')
     */
    public static function parse(string $filePath, string $targetSheetName = 'master'): array {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception('ไม่พบไฟล์ Excel หรือไม่สามารถอ่านไฟล์ชั่วคราวได้');
        }

        $entries = self::extractZipEntries($filePath);
        if (empty($entries)) {
            throw new Exception('ไม่สามารถแตกไฟล์ Excel (.xlsx) ได้ กรุณาตรวจสอบว่าไฟล์ไม่เสียหายหรือไม่ได้ตั้งรหัสผ่านป้องกัน');
        }

        // 1. อ่าน Shared Strings (คลังข้อความทั้งหมดในไฟล์ Excel)
        $sharedStrings = [];
        if (isset($entries['xl/sharedStrings.xml'])) {
            $ssDom = self::loadDom($entries['xl/sharedStrings.xml']);
            if ($ssDom) {
                $siNodes = $ssDom->getElementsByTagName('si');
                foreach ($siNodes as $si) {
                    $text = '';
                    $tNodes = $si->getElementsByTagName('t');
                    foreach ($tNodes as $t) {
                        $text .= $t->nodeValue;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        // 2. ค้นหา Path ของ Worksheet ตามชื่อ Sheet (ค่าเริ่มต้น 'master' หากไม่พบจะใช้ Sheet แรก)
        $sheetPath = 'xl/worksheets/sheet1.xml';
        if (isset($entries['xl/workbook.xml']) && isset($entries['xl/_rels/workbook.xml.rels'])) {
            $wbDom   = self::loadDom($entries['xl/workbook.xml']);
            $relsDom = self::loadDom($entries['xl/_rels/workbook.xml.rels']);

            if ($wbDom && $relsDom) {
                $relMap = [];
                foreach ($relsDom->getElementsByTagName('Relationship') as $rel) {
                    $id     = $rel->getAttribute('Id');
                    $target = $rel->getAttribute('Target');
                    if ($id !== '' && $target !== '') {
                        $relMap[$id] = $target;
                    }
                }

                $foundRid = null;
                $firstRid = null;
                $nsRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

                foreach ($wbDom->getElementsByTagName('sheet') as $sheet) {
                    $name = $sheet->getAttribute('name');
                    $rid  = $sheet->getAttributeNS($nsRel, 'id');
                    if ($rid === '') {
                        $rid = $sheet->getAttribute('r:id');
                    }
                    if ($rid === '') {
                        $rid = $sheet->getAttribute('id');
                    }

                    if ($firstRid === null && $rid !== '') {
                        $firstRid = $rid;
                    }
                    if (strcasecmp(trim($name), trim($targetSheetName)) === 0 && $rid !== '') {
                        $foundRid = $rid;
                        break;
                    }
                }

                $useRid = $foundRid ?? $firstRid;
                if ($useRid !== null && isset($relMap[$useRid])) {
                    $target = ltrim(str_replace('\\', '/', $relMap[$useRid]), '/');
                    $sheetPath = (strpos($target, 'xl/') === 0) ? $target : 'xl/' . $target;
                }
            }
        }

        if (!isset($entries[$sheetPath])) {
            throw new Exception("ไม่พบข้อมูล Worksheet ($sheetPath) ภายในไฟล์ Excel");
        }

        $sheetDom = self::loadDom($entries[$sheetPath]);
        if (!$sheetDom) {
            throw new Exception('ไม่สามารถอ่านโครงสร้าง XML ของ Worksheet ได้');
        }

        // 3. อ่านข้อมูลทีละแถวและทีละเซลล์
        $rows = [];
        $autoRowNum = 0;

        foreach ($sheetDom->getElementsByTagName('row') as $row) {
            $autoRowNum++;
            $rAttr  = $row->getAttribute('r');
            $rowNum = ($rAttr !== '') ? (int)$rAttr : $autoRowNum;

            $rowData    = [];
            $maxColIdx  = -1;
            $autoColIdx = -1;

            foreach ($row->getElementsByTagName('c') as $c) {
                $cellRef = $c->getAttribute('r');
                if ($cellRef !== '') {
                    $colLetters = preg_replace('/[0-9]+/', '', $cellRef);
                    $colIdx     = self::colLettersToIndex($colLetters);
                    $autoColIdx = $colIdx;
                } else {
                    $autoColIdx++;
                    $colIdx = $autoColIdx;
                }

                if ($colIdx > $maxColIdx) {
                    $maxColIdx = $colIdx;
                }

                $type = $c->getAttribute('t');
                $val  = '';

                if ($type === 's') {
                    $vNodes = $c->getElementsByTagName('v');
                    if ($vNodes->length > 0) {
                        $idx = (int)$vNodes->item(0)->nodeValue;
                        $val = $sharedStrings[$idx] ?? '';
                    }
                } elseif ($type === 'inlineStr') {
                    $tNodes = $c->getElementsByTagName('t');
                    foreach ($tNodes as $t) {
                        $val .= $t->nodeValue;
                    }
                } elseif ($type === 'b') {
                    $vNodes = $c->getElementsByTagName('v');
                    $val = ($vNodes->length > 0 && $vNodes->item(0)->nodeValue === '1') ? 'TRUE' : 'FALSE';
                } else {
                    $vNodes = $c->getElementsByTagName('v');
                    if ($vNodes->length > 0) {
                        $val = $vNodes->item(0)->nodeValue;
                    }
                }

                $rowData[$colIdx] = $val;
            }

            $denseRow = [];
            for ($i = 0; $i <= $maxColIdx; $i++) {
                $denseRow[$i] = $rowData[$i] ?? '';
            }
            $rows[$rowNum] = $denseRow;
        }

        return $rows;
    }

    /**
     * แตกไฟล์ ZIP ของ .xlsx (รองรับทั้ง ZipArchive และ Pure-PHP gzinflate อัตโนมัติ)
     */
    private static function extractZipEntries(string $filePath): array {
        $entries = [];

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = str_replace('\\', '/', $zip->getNameIndex($i));
                    $entries[$name] = $zip->getFromIndex($i);
                }
                $zip->close();
                return $entries;
            }
        }

        $bin = @file_get_contents($filePath);
        if ($bin === false || strlen($bin) < 22) {
            return [];
        }

        // ค้นหา End of Central Directory (EOCD: 0x06054b50)
        $eocdPos = strrpos($bin, "\x50\x4b\x05\x06");
        if ($eocdPos === false) {
            return [];
        }

        $eocd  = unpack('Vsig/vdisk/vcdDisk/vcdEntries/vtotalEntries/VcdSize/VcdOffset/vcommentLen', substr($bin, $eocdPos, 22));
        $pos   = $eocd['cdOffset'];
        $total = $eocd['totalEntries'];
        $binLen = strlen($bin);

        for ($i = 0; $i < $total; $i++) {
            if ($pos + 46 > $binLen) break;

            $cd = unpack(
                'Vsig/vverMade/vverNeed/vflag/vmethod/vmtime/vmdate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen/vcommentLen/vdiskStart/vintAttr/VextAttr/VlocalOffset',
                substr($bin, $pos, 46)
            );
            if ($cd['sig'] !== 0x02014b50) break;

            $fileName = str_replace('\\', '/', substr($bin, $pos + 46, $cd['nameLen']));
            $pos += 46 + $cd['nameLen'] + $cd['extraLen'] + $cd['commentLen'];

            $localOffset = $cd['localOffset'];
            if ($localOffset + 30 > $binLen) continue;

            $lh = unpack('Vsig/vver/vflag/vmethod/vmtime/vmdate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen', substr($bin, $localOffset, 30));
            if ($lh['sig'] !== 0x04034b50) continue;

            $dataStart = $localOffset + 30 + $lh['nameLen'] + $lh['extraLen'];
            if ($dataStart + $cd['compSize'] > $binLen) continue;

            $compData = substr($bin, $dataStart, $cd['compSize']);

            if ($cd['method'] === 0) {
                $entries[$fileName] = $compData;
            } elseif ($cd['method'] === 8 && function_exists('gzinflate')) {
                $raw = @gzinflate($compData);
                if ($raw !== false) {
                    $entries[$fileName] = $raw;
                }
            }
        }

        return $entries;
    }

    /**
     * แปลงสตริง XML ด้วย DOMDocument (Built-in PHP Core)
     */
    private static function loadDom(string $xmlStr): ?DOMDocument {
        if (trim($xmlStr) === '') {
            return null;
        }
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = $dom->loadXML($xmlStr, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $ok ? $dom : null;
    }

    /**
     * แปลงตัวอักษรคอลัมน์ Excel (A -> 0, Z -> 25, AA -> 26, AV -> 47)
     */
    private static function colLettersToIndex(string $letters): int {
        $letters = strtoupper(trim($letters));
        $len = strlen($letters);
        if ($len === 0) return 0;

        $num = 0;
        for ($i = 0; $i < $len; $i++) {
            $num = $num * 26 + (ord($letters[$i]) - 64);
        }
        return $num - 1;
    }

    /**
     * แปลงค่าวันที่จาก Excel (รองรับทั้งเลข Serial Date เช่น 46335 และ String วันที่) เป็น Y-m-d
     */
    public static function parseExcelDate($raw): string {
        $val = trim((string)$raw);
        if ($val === '') return '';

        // กรณีเป็นตัวเลข Excel Serial Date (เช่น 46335)
        if (is_numeric($val) && (float)$val > 25569) {
            $unixTimestamp = (int)round(((float)$val - 25569) * 86400);
            return gmdate('Y-m-d', $unixTimestamp);
        }

        // กรณีเป็นรูปแบบวันที่ข้อความ เช่น 2026-11-09 00:00:00 หรือ 11/09/2026
        $clean = preg_replace('/\s+00:00:00$/', '', $val);
        $ts = strtotime($clean);
        return $ts ? date('Y-m-d', $ts) : '';
    }
}