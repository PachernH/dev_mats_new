<?php

date_default_timezone_set("Asia/Bangkok");
$cur_d = date('Y-m-d H:i:s');

// ฟังก์ชั่นช่วยแสดงผลตัวเลขทศนิยม 2 ตำแหน่ง
function fmt2($val) {
    if ($val === null || $val === '') return '-';
    return number_format((float)$val, 2);
}

// ฟังก์ชั่นช่วยแสดงผลทศนิยม 3 ตำแหน่ง
function fmt3($val) {
    if ($val === null || $val === '') return '-';
    return number_format((float)$val, 3);
}

// ฟังก์ชั่นช่วยแสดงผลทศนิยม 4 ตำแหน่ง
function fmt4($val) {
    if ($val === null || $val === '') return '-';
    return number_format((float)$val, 4);
}

function generateProductNo()
{
    $prefix = "P" . date("ymd") . "-P-"; // รูปแบบ P261008-P-
        
    // ค้นหาเลข Running สูงสุดของวันนี้
    $sql = "SELECT MAX(PRODUCT_NO) AS max_no 
             FROM CRSHPROD1 
             WHERE PRODUCT_NO LIKE :prefix";
        
    $stmt = $this->db->prepare($sql);
    $stmt->execute([':prefix' => $prefix . '%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($row && !empty($row['max_no'])) {
          // ดึงตัวเลข 2 หลักสุดท้ายมา +1
          $lastSeq = (int)substr($row['max_no'], -2);
          $newSeq = $lastSeq + 1;
      } else {
          // เริ่มต้นที่ 01
          $newSeq = 1;
      }

    return $prefix . str_pad($newSeq, 2, '0', STR_PAD_LEFT);
}

function getParentCoil($targetCoil, $fetchRootOnly = false) 
{
    global $conn; // เรียกใช้งานตัวแปร $conn จากภายนอกฟังก์ชัน

    if (empty($targetCoil)) {
        return '-';
    }

    $sql = "WITH ParentTree AS (
                -- 1. Anchor: เริ่มจากคอยล์ที่ต้องการ
                SELECT 
                    c.COIL_NO, c.MATERIAL_IN, c.LINE_PROCESS, c.PRODUCT_REFERENCE, c.COIL_ENDDATE, 
                    c.COIL_ACTUALWEIGHT, c.CSTMSPPL_ID, c.ALLOY, c.THICKNESS, c.WIDTH, 
                    c.COIL_STATUS, c.COIL_OPERATEDATE,
                    0 AS Level
                FROM COILPROD1 AS c
                WHERE c.COIL_NO = :target_coil 

                UNION ALL

                -- 2. Recursive: ย้อนกลับหา Parent
                SELECT 
                    p.COIL_NO, p.MATERIAL_IN, p.LINE_PROCESS, p.PRODUCT_REFERENCE, p.COIL_ENDDATE, 
                    p.COIL_ACTUALWEIGHT, p.CSTMSPPL_ID, p.ALLOY, p.THICKNESS, p.WIDTH, 
                    p.COIL_STATUS, p.COIL_OPERATEDATE,
                    child.Level - 1 
                FROM COILPROD1 AS p
                INNER JOIN ParentTree AS child ON p.COIL_NO = child.PRODUCT_REFERENCE
            )
            SELECT " . ($fetchRootOnly ? "TOP 1 " : "") . "* 
            FROM ParentTree
            ORDER BY Level ASC";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([':target_coil' => $targetCoil]);

        if ($fetchRootOnly) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            // คืนค่าเฉพาะเลข COIL_NO หรือคืนค่า '-' ถ้าไม่พบข้อมูล
            return ($result && isset($result['COIL_NO'])) ? $result['COIL_NO'] : '-';
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return '-';
    }
}

function RT_REMELT_STATUS($code)
{
  include('dbcon_mats-new.php');
  $sql="SELECT a.REQUEST_NO FROM PRODRMLT1 as a join PRODRMLT2 as b ON  a.REQUEST_NO = b.REQUEST_NO
  WHERE a.REQUEST_NO ='" . $code . "' and b.REMELT_STATUS <> 'RM'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (is_null($row['REQUEST_NO']) || ($row['REQUEST_NO'] == '')) {
      $IDRef = false;
    } else {
      $IDRef = true;
    }
    return $IDRef;
  }
}


function RT_COIL_PRO($code)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT BATCH_NO FROM COILPROD1 WHERE BATCH_NO='" . $code . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (is_null($row['BATCH_NO']) || ($row['BATCH_NO'] == '')) {
      $IDRef = false;
    } else {
      $IDRef = true;
    }
    return $IDRef;
  }
}

function CHK_COIL_PRO($code)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT COIL_NO FROM COILPROD1 WHERE COIL_STATUS='OP' AND COIL_NO='" . $code . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (is_null($row['COIL_NO']) || ($row['COIL_NO'] == '')) {
      $IDRef = false;
    } else {
      $IDRef = true;
    }
    return $IDRef;
  }
}



function RT_BufferCoil()
{
  include('dbcon_mats-new.php');
  $sql = "SELECT Count(COIL_NEXTPROCESS) as total_buffer  FROM COILPROD1 Where COIL_STATUS='AC' or COIL_STATUS='OP'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (is_null($row['total_buffer']) || ($row['total_buffer'] == '')) {
      $buffer = 0;
    } else {
      $buffer = $row['total_buffer'];
    }
    return $buffer;
  }
}

function RT_CoilWorking()
{
  include('dbcon_mats-new.php');
  $sql = "SELECT Count(COIL_NO) as total_work  FROM COILWORK1";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (is_null($row['total_work']) || ($row['total_work'] == '')) {
      $cwork = 0;
    } else {
      $cwork = $row['total_work'];
    }
    return $cwork;
  }
}

function RT_DEF_Desc($TY,$ID)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT d.DESCRIPTION  FROM DFCTMSTR1 AS d
  Where d.PROCESS='" . $TY . "' and d.DEFECT_ID ='" . $ID . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();
  $d = '';
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d = $row['DESCRIPTION'];
  }
  return $d;
}

function RT_STRETCHER($ID)
{
    include('dbcon_mats-new.php');
    $sql = "SELECT * FROM STCHPROD1 WHERE PRODUCT_NO = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $ID]);

    $d = array();
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d[0]  = $row['PRODUCT_NO'];
        $d[1]  = $row['LINE_PROCESS'];
        $d[2]  = $row['STCH_TIME'];
        $d[3]  = $row['MATERIAL_CODE'];
        $d[4]  = $row['STCH_STARTDATE'];
        $d[5]  = $row['STCH_STARTTIME'];
        $d[6]  = $row['STCH_ENDDATE'];
        $d[7]  = $row['STCH_ENDTIME'];
        $d[8]  = $row['STCH_ACCEPTPIECE'];
        $d[9]  = $row['STCH_ACCEPTWEIGHT'];
        $d[10] = $row['STCH_REJECTPIECE'];
        $d[11] = $row['STCH_REJECTWEIGHT'];
        $d[12] = $row['FLATNESS_HIGH'];
        $d[13] = $row['FLATNESS_LENGTH'];

        // --- เพิ่มส่วนคำนวณ I.UNIT ตามสูตร VBA ---
        $flatnessH = (float) $row['FLATNESS_HIGH'];
        $flatnessL = (float) $row['FLATNESS_LENGTH'];
        $iUnit = 0;

        if ($flatnessH != 0 && $flatnessL != 0) {
            $calc = (M_PI * $flatnessH) / (2 * $flatnessL);
            $iUnit = ($calc * $calc) * 100000;
        }

        // จัดการฟอร์แมตทศนิยม 2 ตำแหน่ง
        $d[14] = number_format($iUnit, 2, '.', ''); 
        
        $d[15] = $row['STCH_OPERATOR1'];
        $d[16] = $row['STCH_OPERATOR2'];
        $d[17] = $row['STCH_OPERATEDATE'];
        $d[18] = $row['STCH_UPDATE'];
        $d[19] = $row['STCH_UPDATEDATE'];
    }
    return $d;
}

function RT_CoilData($ID)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT * FROM COILPROD1 WHERE COIL_NO='" . $ID . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  $d = array();
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d[0] = $row['COIL_NO'];
    $d[1] = $row['BATCH_NO'];
    $d[2] = $row['MATERIAL_IN'];
    $d[3] = $row['CSTMSPPL_ID'];
    $d[4] = $row['PRODUCT_ID'];
    $d[5] = $row['LINE_PROCESS'];
    $d[6] = $row['F_ALLOY'];
    $d[7] = $row['F_TEMPER'];
    $d[8] = $row['F_GRADE'];
    $d[9] = $row['F_THICKNESS'];
    $d[10] = $row['F_WIDTH'];
    $d[11] = $row['ALLOY'];
    $d[12] = $row['TEMPER'];
    $d[13] = $row['GRADE'];
    $d[14] = $row['SURFACE_GRADE'];
    $d[15] = $row['METALLURGICAL_GRADE'];
    $d[16] = $row['THICKNESS'];
    $d[17] = $row['WIDTH'];
    $d[18] = $row['ACC_SLITWIDTH'];
    $d[19] = $row['BLN_SLITWIDTH'];
    $d[20] = $row['CDML_STATUS'];
    $d[21] = $row['COIL_UOMDIMENSION'];
    $d[22] = $row['COIL_WORKPROCESS'];
    $d[23] = $row['COIL_ACTUALWEIGHT'];
    $d[24] = $row['COIL_CASTWEIGHT'];
    $d[25] = $row['COIL_FIRSTWEIGHT'];
    $d[26] = $row['COIL_BALANCEWEIGHT'];
    $d[27] = $row['COIL_UOMWEIGHT'];
    $d[28] = $row['COIL_EDGECURLA'];
    $d[29] = $row['COIL_EDGECURLB'];
    $d[30] = $row['COIL_EDGECURLC'];
    $d[31] = $row['COIL_EDGECURLD'];
    $d[32] = $row['COIL_EDGECURLE'];
    $d[33] = $row['TOTAL_TI'];
    $d[34] = $row['IN_TI'];
    $d[35] = $row['DELTA_TI'];
    $d[36] = $row['COIL_UPDATE'];
    $d[37] = $row['COIL_UPDATEDATE'];
    $d[38] = $row['COIL_STATUS'];
    $d[39] = $row['COIL_STARTTIME'];
    $d[40] = $row['COIL_ENDTIME'];
    $d[41] = $row['PRIMARY_SMELT'];
    $d[42] = $row['SECONDARY_SMELT'];
    $d[43] = $row['COUNTRY_MELT'];
    $d[44] = $row['COUNTRY_ORIGIN'];
  }
  return $d;
}

function RT_Cust_Supp($ID)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT * FROM CSSPMSTR1 WHERE CSTMSPPL_ID='" . $ID . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  $d = array();
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d[0] = $row['CSTMSPPL_ID'];
    $d[1] = $row['ACCOUNT_CODE'];
    $d[2] = $row['CSTMSPPL_BRANCH'];
    $d[3] = $row['CSTMSPPL_TAXID'];
    $d[4] = $row['CERTIFICATE_ADDRESS'];
    $d[5] = $row['CSTMSPPL_TYPE'];
    $d[6] = $row['CSTMSPPL_LOCATION'];
    $d[7] = $row['REGION_GROUP'];
    $d[8] = $row['COUNTRY_GROUP'];
    $d[9] = $row['ORG_REGION_GROUP'];
    $d[10] = $row['ORG_COUNTRY_GROUP'];
    $d[11] = $row['SUPPLIER_MATCODE'];
    $d[12] = $row['PAYMENT_ID'];
    $d[13] = $row['SHIPMENT_ID'];
    $d[14] = $row['CURRENCY_ID'];
    $d[15] = $row['CSSP_FREIGHT'];
    $d[16] = $row['CSSP_INSURANCE'];
    $d[17] = $row['CSSP_TAXPERCENT'];
    $d[18] = $row['CSSP_PORTLOADING'];
    $d[19] = $row['CSSP_PORTDISCHARGE'];
    $d[20] = $row['CSSP_PLACEDESTINATION'];
    $d[21] = $row['CSSP_COUNTRYDESTINATION'];
    $d[22] = $row['UOM_WEIGHT'];
    $d[23] = $row['UOM_DIMENSION'];
    $d[24] = $row['CONSIGNEE_COMPANY'];
    $d[25] = $row['CONSIGNEE_ADDRESS1'];
    $d[26] = $row['CONSIGNEE_ADDRESS2'];
    $d[27] = $row['CONSIGNEE_ADDRESS3'];
    $d[28] = $row['CONSIGNEE_TELEPHONE'];
    $d[29] = $row['CONSIGNEE_FAX'];
    $d[30] = $row['CONSIGNEE_EMAIL'];
    $d[31] = $row['CONSIGNEE_CONTACT'];
    $d[32] = $row['CONSIGNEE_POSITION'];
    $d[33] = $row['NOTIFY_COMPANY'];
    $d[34] = $row['NOTIFY_ADDRESS1'];
    $d[35] = $row['NOTIFY_ADDRESS2'];
    $d[36] = $row['NOTIFY_ADDRESS3'];
    $d[37] = $row['NOTIFY_TELEPHONE'];
    $d[38] = $row['NOTIFY_FAX'];
    $d[39] = $row['NOTIFY_EMAIL'];
    $d[40] = $row['NOTIFY_CONTACT'];
    $d[41] = $row['NOTIFY_POSITION'];
    $d[42] = $row['NOTIFY_TAXID'];
    $d[43] = $row['ALSONOTIFY_COMPANY'];
    $d[44] = $row['ALSONOTIFY_ADDRESS1'];
    $d[45] = $row['ALSONOTIFY_ADDRESS2'];
    $d[46] = $row['ALSONOTIFY_ADDRESS3'];
    $d[47] = $row['ALSONOTIFY_TELEPHONE'];
    $d[48] = $row['ALSONOTIFY_FAX'];
    $d[49] = $row['ALSONOTIFY_EMAIL'];
    $d[50] = $row['ALSONOTIFY_CONTACT'];
    $d[51] = $row['ALSONOTIFY_POSITION'];
    $d[52] = $row['ALLOY_PARTPOSITION'];
    $d[53] = $row['ALLOY_PARTSIZE'];
    $d[54] = $row['HARMONIZE_CODE'];
    $d[55] = $row['TEMPER_PARTPOSITION'];
    $d[56] = $row['TEMPER_PARTSIZE'];
    $d[57] = $row['THICKNESS_PARTPOSITION'];
    $d[58] = $row['THICKNESS_PARTSIZE'];
    $d[59] = $row['WIDTH_PARTPOSITION'];
    $d[60] = $row['WIDTH_PARTSIZE'];
    $d[61] = $row['LENGTH_PARTPOSITION'];
    $d[62] = $row['LENGTH_PARTSIZE'];
    $d[63] = $row['PACKAGE_TREATMENT'];
    $d[64] = $row['PACKAGE_HIGH'];
    $d[65] = $row['INCLUDE_HIGH'];
    $d[66] = $row['WEIGHTPERPACKAGE'];
    $d[67] = $row['INCLUDE_WEIGHT'];
    $d[68] = $row['PACKAGE_CONTROL'];
    $d[69] = $row['SERIAL_CODE'];
    $d[70] = $row['SPECIAL_LABEL1'];
    $d[71] = $row['SPECIAL_LABEL2'];
    $d[72] = $row['SPECIAL_LABEL3'];
    $d[73] = $row['SPECIAL_LABEL4'];
    $d[74] = $row['CITY'];
    $d[75] = $row['ORDER_BY'];
    $d[76] = $row['ORDER_STARTDATE'];
    $d[77] = $row['SHOW_PIECE'];
    $d[78] = $row['ADJUST_PIECE'];
    $d[79] = $row['SHOW_SCRAPWIRE'];
    $d[80] = $row['PRIMARY_SMELT'];
    $d[81] = $row['SECONDARY_SMELT'];
    $d[82] = $row['COUNTRY_MELT'];
    $d[83] = $row['COIL_TYPE'];
  }
  return $d;
}

function RT_Workprocess($CIW)
{
    include('dbcon_mats-new.php');

    // ใช้ LTRIM(RTRIM()) ป้องกันปัญหาช่องว่างแฝงในข้อความ
    $sql = "SELECT WORK_PROCESS, PRODUCT_ID, WORK_TYPE, DESCRIPTION 
            FROM WKPCMSTR1 
            WHERE LTRIM(RTRIM(WORK_PROCESS)) = LTRIM(RTRIM(:ciw))";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':ciw', trim($CIW));
    $stmt->execute();

    $d = array();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // คืนค่าออกมารองรับทั้งแบบ Index ตัวเลข และชื่อคอลัมน์
        $d[0] = trim($row['WORK_PROCESS'] ?? '');
        $d[1] = trim($row['PRODUCT_ID'] ?? '');
        $d[2] = trim($row['WORK_TYPE'] ?? '');
        $d[3] = trim($row['DESCRIPTION'] ?? '');
        
        $d['WORK_PROCESS'] = trim($row['WORK_PROCESS'] ?? '');
        $d['PRODUCT_ID']   = trim($row['PRODUCT_ID'] ?? '');
        $d['WORK_TYPE']    = trim($row['WORK_TYPE'] ?? '');
        $d['DESCRIPTION']  = trim($row['DESCRIPTION'] ?? '');
    }
    return $d;
}


function RT_Material_Package($ID)
{
    include('dbcon_mats-new.php');
    $sql = "SELECT 
                A.MATERIAL_CODE, A.ITEMNUM, A.DESCRIPTION, A.MATERIAL_TYPE, A.QTY_ONHAND,
                B.PACKAGE_TYPE, B.PACKAGE_TREATMENT, B.WIDTH_INCH, B.LENGTH_INCH, B.HIGH_INCH,
                B.WIDTH_MM, B.WIDTH_PLUS, B.WIDTH_MINUS, B.LENGTH_MM, B.LENGTH_PLUS,
                B.LENGTH_MINUS, B.HIGH_MM, B.PACKAGE_WEIGHT
            FROM MTRLMSTR1 A
            LEFT JOIN MTRLSPCT1 B ON A.MATERIAL_CODE = B.MATERIAL_CODE
            WHERE A.MATERIAL_CODE = :id";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $ID);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC); // ส่งกลับเป็น Associative Array เพื่อดูง่าย
}

function RT_Supp($ID)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT * FROM CSSPMSTR22 WHERE CSTMSPPL_ID='" . $ID . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  $d = array();
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d[0] = $row['CSTMSPPL_ID'];
    $d[1] = $row['ACCOUNT_CODE'];
    $d[2] = $row['TAX_REGISTRATION'];
    $d[3] = $row['TAX_CATEGORY'];
    $d[4] = $row['EN_COMPANY'];
    $d[5] = $row['EN_ADDRESS1'];
    $d[6] = $row['EN_ADDRESS2'];
    $d[7] = $row['TH_COMPANY'];
    $d[8] = $row['TH_ADDRESS1'];
    $d[9] = $row['TH_ADDRESS2'];
    $d[10] = $row['BRANCH'];
    $d[11] = $row['TH_TITLE_COMPANY'];
    $d[12] = $row['TH_COMPANY_NAME'];
    $d[13] = $row['TH_NAME_ADDRESS'];
    $d[14] = $row['TH_ROOM_ADDRESS'];
    $d[15] = $row['TH_CLASS_ADDRESS'];
    $d[16] = $row['TH_NUM_ADDRESS'];
    $d[17] = $row['TH_MOO'];
    $d[18] = $row['TH_SOI'];
    $d[19] = $row['TH_ROAD'];
    $d[20] = $row['TH_TUMBON'];
    $d[21] = $row['TH_AUMPER'];
    $d[22] = $row['TH_CITY'];
    $d[23] = $row['TH_POSTCODE'];
  }
  return $d;
}

function RT_properties_Spec($IDAL, $IDTE, $IDTHF, $IDTHT)
{
    include('dbcon_mats-new.php');

    $sql = "SELECT * FROM TCPS0201_6 
            WHERE ALLOY = :alloy 
              AND TEMPER_TARGET = :temper 
              AND THICKNESS_FROM = :thicknessfrom 
              AND THICKNESS_TO = :thicknessTo";
              
    $stmt = $conn->prepare($sql);
    
    // Bind ค่าตัวแปรให้ตรงกับเงื่อนไขการค้นหา
    $stmt->bindParam(':alloy', $IDAL);
    $stmt->bindParam(':temper', $IDTE);
    $stmt->bindParam(':thicknessfrom', $IDTHF);
    $stmt->bindParam(':thicknessTo', $IDTHT);

    $stmt->execute();
    
    $d = array();
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d[0] = $row['ALLOY'];
        $d[1] = $row['TEMPER_TARGET'];
        $d[2] = $row['THICKNESS_FROM'];
        $d[3] = $row['THICKNESS_TO'];
        $d[4] = $row['UTSFROM_KSI'];
        $d[5] = $row['UTSTO_KSI'];
        $d[6] = $row['UTSFROM_KGM'];
        $d[7] = $row['UTSTO_KGM'];
        $d[8] = $row['YSFROM_KSI'];
        $d[9] = $row['YSTO_KSI'];
        $d[10] = $row['YSFROM_KGM'];
        $d[11] = $row['YSTO_KGM'];
        $d[12] = $row['ELONGFROM'];
        $d[13] = $row['ELONGTO'];
    }
    return $d;
}

function RT_Product_Spec($IDAL, $IDTE, $IDTH, $IDWI, $IDLE)
{
    include('dbcon_mats-new.php');
    
    // 💡 แก้ไขคำสั่ง SQL ให้ใช้ Parameter Binding เพื่อความปลอดภัยและป้องกันเครื่องหมายโควทพัง
    $sql = "SELECT * FROM STNDMSTR1 
            WHERE ALLOY = :alloy 
              AND TEMPER = :temper 
              AND THICKNESS = :thickness 
              AND WIDTH = :width 
              AND LENGTH = :length";
              
    $stmt = $conn->prepare($sql);
    
    // Bind ค่าตัวแปรให้ตรงกับเงื่อนไขการค้นหา
    $stmt->bindParam(':alloy', $IDAL);
    $stmt->bindParam(':temper', $IDTE);
    $stmt->bindParam(':thickness', $IDTH);
    $stmt->bindParam(':width', $IDWI);
    $stmt->bindParam(':length', $IDLE);
    
    $stmt->execute();
    
    $d = array();
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d[0] = $row['ALLOY'];
        $d[1] = $row['TEMPER'];
        $d[2] = $row['THICKNESS'];
        $d[3] = $row['WIDTH'];
        $d[4] = $row['LENGTH'];
        $d[5] = $row['DESCRIPTION'];
        $d[6] = $row['STND_MAXTHICKNESS'];
        $d[7] = $row['STND_MINTHICKNESS'];
        $d[8] = $row['STND_MAXWIDTH'];
        $d[9] = $row['STND_MINWIDTH'];
        $d[10] = $row['STND_MAXLENGTH'];
        $d[11] = $row['STND_MINLENGTH'];
        $d[12] = $row['STND_MAXUTS'];
        $d[13] = $row['STND_MINUTS'];
        $d[14] = $row['STND_MAXYIELDSTRENGTH'];
        $d[15] = $row['STND_MINYIELDSTRENGTH'];
        $d[16] = $row['STND_MAXELONGATION'];
        $d[17] = $row['STND_MINELONGATION'];
        $d[18] = $row['STND_MAXEARING'];
        $d[19] = $row['STND_MINEARING'];
    }
    return $d;
}

function RT_Practice_Spec($IDPD, $IDAL, $IDRF, $IDRT, $IDTI, $IDTT)
{
    include('dbcon_mats-new.php');
    
    // 💡 แก้ไขคำสั่ง SQL ให้ใช้ Parameter Binding เพื่อความปลอดภัยและป้องกันเครื่องหมายโควทพัง
    $sql = "SELECT * FROM TPMAT1001 
            WHERE PRODUCT_ID = :proid 
              AND ALLOY = :alloy
              AND RANGE_FROM = :ran_f
              AND RANGE_TO = :ran_t 
              AND TEMPER_INITIAL = :tem_in 
              AND TEMPER_TARGET = :tem_ta";
              
    $stmt = $conn->prepare($sql);
    
    // Bind ค่าตัวแปรให้ตรงกับเงื่อนไขการค้นหา
    $stmt->bindParam(':proid', $IDPD);
    $stmt->bindParam(':alloy', $IDAL);
    $stmt->bindParam(':ran_f', $IDRF);
    $stmt->bindParam(':ran_t', $IDRT);
    $stmt->bindParam(':tem_in', $IDTI);
    $stmt->bindParam(':tem_ta', $IDTT);
    
    $stmt->execute();
    
    $d = array();
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d[0] = $row['PRODUCT_ID'];
        $d[1] = $row['ALLOY'];
        $d[2] = $row['RANGE_FROM'];
        $d[3] = $row['RANGE_TO'];
        $d[4] = $row['TEMPER_INITIAL'];
        $d[5] = $row['TEMPER_TARGET'];
        $d[6] = $row['RANGE_TYPE'];
        $d[7] = $row['MIN_WEIGHT'];
        $d[8] = $row['O2PURING_FROM'];
        $d[9] = $row['O2PURING_TO'];
        $d[10] = $row['HEATING_TEMPERATURE'];
        $d[11] = $row['HEATING_TIME'];
        $d[12] = $row['SOAKING_TEMPERATURE'];
        $d[13] = $row['SOAKING_TIME'];
        $d[14] = $row['WORKPIECE_HEATING'];
        $d[15] = $row['WORKPIECE_SOAKINGFROM'];
        $d[16] = $row['WORKPIECE_SOAKINGTO'];
        $d[17] = $row['O2_PERCENTAGEFROM'];
        $d[18] = $row['O2_PERCENTAGETO'];   
        $d[19] = $row['PROGRAM'];   
    }
    return $d;
}

function RT_Cust_Spec($IDCT, $IDPR, $IDAL, $IDTE, $IDTH, $IDWI, $IDLE)
{
    include('dbcon_mats-new.php');
    
    // 💡 แก้ไขคำสั่ง SQL ให้ใช้ Parameter Binding เพื่อความปลอดภัยและป้องกันเครื่องหมายโควทพัง
    $sql = "SELECT * FROM STNDMSTR6 
            WHERE CSTMSPPL_ID = :custid 
              AND PRODUCT_ID = :proid
              AND ALLOY = :alloy
              AND TEMPER = :temper 
              AND THICKNESS = :thickness 
              AND WIDTH = :width 
              AND LENGTH = :length";
              
    $stmt = $conn->prepare($sql);
    
    // Bind ค่าตัวแปรให้ตรงกับเงื่อนไขการค้นหา
    $stmt->bindParam(':custid', $IDCT);
    $stmt->bindParam(':proid', $IDPR);
    $stmt->bindParam(':alloy', $IDAL);
    $stmt->bindParam(':temper', $IDTE);
    $stmt->bindParam(':thickness', $IDTH);
    $stmt->bindParam(':width', $IDWI);
    $stmt->bindParam(':length', $IDLE);
    
    $stmt->execute();
    
    $d = array();
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d[0] = $row['CSTMSPPL_ID'];
        $d[1] = $row['PRODUCT_ID'];
        $d[2] = $row['ALLOY'];
        $d[3] = $row['TEMPER'];
        $d[4] = $row['THICKNESS'];
        $d[5] = $row['WIDTH'];
        $d[6] = $row['LENGTH'];
        $d[7] = $row['THICKNESS_PTOLERANCE'];
        $d[8] = $row['THICKNESS_MTOLERANCE'];
        $d[9] = $row['WIDTH_PTOLERANCE'];
        $d[10] = $row['WIDTH_MTOLERANCE'];
        $d[11] = $row['LENGTH_PTOLERANCE'];
        $d[12] = $row['LENGTH_MTOLERANCE'];
        $d[13] = $row['FLATNESS'];
        $d[14] = $row['EARING_TYPE'];
        $d[15] = $row['EARING_PERCENT'];
        $d[16] = $row['FIXED_PROCESS'];
    }
    return $d;
}

function RT_Composition($ID,$IDC)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT * FROM CMPSMSTR1 WHERE ALLOY= '". $ID ."' AND CSTMSPPL_ID='" . $IDC . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  $d = array();
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d[0] = $row['PRODUCT_ID'];
    $d[1] = $row['ALLOY'];
    $d[2] = $row['CSTMSPPL_ID'];
    $d[3] = $row['DESCRIPTION'];
    $d[4] = $row['DENSITY'];
    $d[5] = $row['AL'];
    $d[6] = $row['TI'];
    $d[7] = $row['AL_MIN'];
    $d[8] = $row['AL_MAX'];
    $d[9] = $row['FE_MIN'];
    $d[10] = $row['FE_AVG'];
    $d[11] = $row['FE_MAX'];
    $d[12] = $row['SI_MIN'];
    $d[13] = $row['SI_AVG'];
    $d[14] = $row['SI_MAX'];
    $d[15] = $row['MN_MIN'];
    $d[16] = $row['MN_AVG'];
    $d[17] = $row['MN_MAX'];
    $d[18] = $row['MG_MIN'];
    $d[19] = $row['MG_AVG'];
    $d[20] = $row['MG_MAX'];
    $d[21] = $row['CR_MIN'];
    $d[22] = $row['CR_AVG'];
    $d[23] = $row['CR_MAX'];
    $d[24] = $row['CU_MIN'];
    $d[25] = $row['CU_AVG'];
    $d[26] = $row['CU_MAX'];
    $d[27] = $row['ZN_MIN'];
    $d[28] = $row['ZN_AVG'];
    $d[29] = $row['ZN_MAX'];
    $d[30] = $row['PB_MIN'];
    $d[31] = $row['PB_AVG'];
    $d[32] = $row['PB_MAX'];
    $d[33] = $row['AS_MAX'];
    $d[34] = $row['NI_MAX'];
    $d[35] = $row['SN_MAX'];
    $d[36] = $row['SB_MAX'];
    $d[37] = $row['BE_MAX'];
    $d[38] = $row['BI_MAX'];
    $d[39] = $row['CD_MAX'];
    $d[40] = $row['IN_MAX'];
    $d[41] = $row['ACTIVE'];
  }
  return $d;
}

function RT_Company($ID)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT * FROM CMPNMSTR1 WHERE COMPANY_ID='" . $ID . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  $d = array();
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d[0] = $row['COMPANY_ID'];
    $d[1] = $row['COMPANY'];
    $d[2] = $row['ADDRESS1'];
    $d[3] = $row['ADDRESS2'];
    $d[4] = $row['ADDRESS3'];
    $d[5] = $row['TELEPHONE'];
    $d[6] = $row['FAX'];
    $d[7] = $row['EMAIL'];
    $d[8] = $row['CONTACT_PERSON'];
    $d[9] = $row['CONTACT_POSITION'];
  }
  return $d;
}

function RT_Payment($ID)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT PAYMENT_ID,DESCRIPTION FROM PMTMMSTR1 WHERE PAYMENT_ID='" . $ID . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  $d = '';
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d = $row['DESCRIPTION'];
  }
  return $d;
}

function RT_Shipment($ID)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT SHIPMENT_ID,DESCRIPTION,SHIPMENT_TYPE FROM SPTMMSTR1 WHERE SHIPMENT_ID='" . $ID . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  $d = array();
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d[0] = $row['SHIPMENT_ID'];
    $d[1] = $row['DESCRIPTION'];
    $d[2] = $row['SHIPMENT_TYPE'];
  }
  return $d;
}

function RT_Port($ID)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT PORT_ID,DESCRIPTION FROM PORTMSTR1 WHERE PORT_ID='" . $ID . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  $d = '';
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d = $row['DESCRIPTION'];
  }
  return $d;
}

function RT_Place($ID)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT PLACE_ID,DESCRIPTION FROM PLCEMSTR1 WHERE PLACE_ID='" . $ID . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  $d = '';
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d = $row['DESCRIPTION'];
  }
  return $d;
}

function RT_Region($ID)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT REGION_GROUP,DESCRIPTION,ACCOUNT_REGION,REPORT_GROUP,ORG_REGION_GROUP FROM RGNSMSTR1 WHERE REGION_GROUP='" . $ID . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  $d = array();
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d[0] = $row['REGION_GROUP'];
    $d[1] = $row['DESCRIPTION'];
    $d[2] = $row['ACCOUNT_REGION'];
    $d[3] = $row['REPORT_GROUP'];
    $d[4] = $row['ORG_REGION_GROUP'];
  }
  return $d;
}

function RT_Country($ID)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT COUNTRY_GROUP,DESCRIPTION,ORG_COUNTRY_GROUP FROM CNTRMSTR1 WHERE COUNTRY_GROUP='" . $ID . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  $d = array();
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d[0] = $row['COUNTRY_GROUP'];
    $d[1] = $row['DESCRIPTION'];
    $d[2] = $row['ORG_COUNTRY_GROUP'];
  }
  return $d;
}

function RT_Currency($ID)
{
  include('dbcon_mats-new.php');
  $sql = "SELECT CURRENCY_ID,DESCRIPTION,SUBCURRENCY,SUBCURRENCY_TYPE,CURRENCY_RATE FROM CRNCMSTR1 WHERE CURRENCY_ID='" . $ID . "'";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  $d = array();
  while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d[0] = $row['CURRENCY_ID'];
    $d[1] = $row['DESCRIPTION'];
    $d[2] = $row['SUBCURRENCY'];
    $d[3] = $row['SUBCURRENCY_TYPE'];
    $d[4] = $row['CURRENCY_RATE'];
  }
  return $d;
}
