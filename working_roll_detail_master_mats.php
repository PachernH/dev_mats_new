<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';
include 'dbcon_mats-new.php';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

// รับค่า RSET_NO (หรือ recipe_no)
$rset_no = isset($_GET['recipe_no']) ? trim($_GET['recipe_no']) : (isset($_GET['rset_no']) ? trim($_GET['rset_no']) : '');

// ดึงข้อมูลรายละเอียดของ Roll Set จากตาราง RDMTMSTR3 พร้อม LEFT JOIN RDMTMSTR2 เพื่อดึงข้อมูลประกอบของแต่ละ Work No.
$data = array();
if ($rset_no !== '') {
    try {
        $sql = "SELECT r3.RSET_NO, r3.RSET_REFERENCE, r3.WRL_RADIUS, r3.BRL_RADIUS, r3.RSET_STATUS,
                       
                       -- TWR Details (r3 + r2)
                       r3.TWR_WORKNO, r3.TWR_CAMBER, r3.TWR_CAMBERTYPE, r3.TWR_INDATETIME, r3.TWR_OUTDATETIME,
                       twr.ROLL_NO AS TWR_ROLL_NO, twr.WRK_STATUS AS TWR_WRK_STATUS,
                       twr.BFR_MAXDIAMETER AS TWR_BFR_MAX, twr.BFR_MINDIAMETER AS TWR_BFR_MIN,
                       
                       -- BWR Details (r3 + r2)
                       r3.BWR_WORKNO, r3.BWR_CAMBER, r3.BWR_CAMBERTYPE, r3.BWR_INDATETIME, r3.BWR_OUTDATETIME,
                       bwr.ROLL_NO AS BWR_ROLL_NO, bwr.WRK_STATUS AS BWR_WRK_STATUS,
                       bwr.BFR_MAXDIAMETER AS BWR_BFR_MAX, bwr.BFR_MINDIAMETER AS BWR_BFR_MIN,
                       
                       -- TBR Details (r3 + r2)
                       r3.TBR_WORKNO, r3.TBR_INDATETIME, r3.TBR_OUTDATETIME,
                       tbr.ROLL_NO AS TBR_ROLL_NO, tbr.WRK_STATUS AS TBR_WRK_STATUS,
                       tbr.AFT_MAXDIAMETER AS TBR_AFT_MAX, tbr.AFT_MINDIAMETER AS TBR_AFT_MIN,
                       
                       -- BBR Details (r3 + r2)
                       r3.BBR_WORKNO, r3.BBR_INDATETIME, r3.BBR_OUTDATETIME,
                       bbr.ROLL_NO AS BBR_ROLL_NO, bbr.WRK_STATUS AS BBR_WRK_STATUS,
                       bbr.AFT_MAXDIAMETER AS BBR_AFT_MAX, bbr.AFT_MINDIAMETER AS BBR_AFT_MIN

                FROM RDMTMSTR3 AS r3
                LEFT JOIN RDMTMSTR2 AS twr ON LTRIM(RTRIM(r3.TWR_WORKNO)) = LTRIM(RTRIM(twr.WORK_NO))
                LEFT JOIN RDMTMSTR2 AS bwr ON LTRIM(RTRIM(r3.BWR_WORKNO)) = LTRIM(RTRIM(bwr.WORK_NO))
                LEFT JOIN RDMTMSTR2 AS tbr ON LTRIM(RTRIM(r3.TBR_WORKNO)) = LTRIM(RTRIM(tbr.WORK_NO))
                LEFT JOIN RDMTMSTR2 AS bbr ON LTRIM(RTRIM(r3.BBR_WORKNO)) = LTRIM(RTRIM(bbr.WORK_NO))
                WHERE LTRIM(RTRIM(r3.RSET_NO)) = LTRIM(RTRIM(:rno))";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':rno' => $rset_no]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $data = array();
    }
}

// ฟังก์ชันแปลงวันที่ให้อ่านง่าย (ถ้าเป็น 1900-01-01 หรือไม่มีค่า ให้แสดง '-')
function formatDateDisplay($datetimeStr) {
    if (empty($datetimeStr)) return '-';
    $dt = new DateTime($datetimeStr);
    $formatted = $dt->format('Y-m-d H:i');
    if (strpos($formatted, '1900-01-01') !== false) {
        return '-';
    }
    return $formatted;
}

// ฟังก์ชันสำหรับจัดรูปแบบตัวเลข 2 ตำแหน่ง
function formatDecimal2($val) {
    return is_numeric($val) ? number_format((float)$val, 2, '.', '') : '0.00';
}

$v_rset_no    = $data['RSET_NO'] ?? $rset_no;
$v_ref_no     = $data['RSET_REFERENCE'] ?? '-';
$v_status     = trim($data['RSET_STATUS'] ?? '-');
$is_op        = ($v_status === 'OP');

$v_wrl_radius = formatDecimal2($data['WRL_RADIUS'] ?? 0);
$v_brl_radius = formatDecimal2($data['BRL_RADIUS'] ?? 0);
?>

<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        
        .display-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        .card-title-sub {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 0;
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label { font-weight: 600; color: #64748b; margin-bottom: 4px; font-size: 12px; text-transform: uppercase; }
        .info-value {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            min-height: 40px;
            padding: 8px 12px;
            font-size: 14px;
            color: #0f172a;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .info-value-highlight {
            color: #2563eb;
            font-size: 16px;
            font-weight: 700;
        }

        .btn-custom-back {
            background-color: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 20px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .btn-custom-back:hover { background-color: #f1f5f9; color: #1e293b; }

        .btn-action-edit {
            background-color: #f0fdf4;
            color: #15803d;
            font-weight: 600;
            font-size: 14px;
            padding: 9px 20px;
            border-radius: 8px;
            border: 1px solid #bbf7d0;
            transition: all 0.2s;
        }
        .btn-action-edit:hover { background-color: #dcfce7; color: #166534; }
        .btn-action-edit:disabled { background-color: #f1f5f9 !important; color: #94a3b8 !important; border-color: #e2e8f0 !important; cursor: not-allowed; }

        /* Status Badge */
        .badge-status-op {
            background-color: #f0fdf4;
            color: #15803d;
            font-weight: 700;
            font-size: 13px;
            padding: 4px 12px;
            border-radius: 6px;
            border: 1px solid #bbf7d0;
        }
        .badge-status-cl {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 13px;
            padding: 4px 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
        }

        /* Style สำหรับกล่องแสดง Info รายละเอียดของ Work No. */
        .workno-meta-box {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 15px;
            font-size: 12px;
        }
        .workno-meta-box .meta-item { display: inline-block; margin-right: 15px; }
        .workno-meta-box .meta-title { font-weight: 600; color: #64748b; }
        .workno-meta-box .meta-value { font-weight: 700; color: #1e293b; }
    </style>
</head>

<body>
<div class="wrapper">
    
    <?php $menu = 'gp3'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Working Roll Set Master Details</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <!-- Action Navigation Bar -->
            <div class="row" style="margin-bottom:20px;">
                <div class="col-xs-6">
                    <button class="btn btn-custom-back" onclick="window.location.assign('working_roll_master_mats.php?func=' + encodeURIComponent('<?php echo $folder_func; ?>'))">
                        ⬅️ Back
                    </button>
                </div>
                <div class="col-xs-6 text-right">
                    <button class="btn btn-action-edit" <?php echo !$is_op ? 'disabled title="Edit is only allowed for items with OP status"' : ''; ?> 
                            onclick="window.location.assign('working_roll_edit_master_mats.php?func=' + encodeURIComponent('<?php echo $folder_func; ?>') + '&recipe_no=' + encodeURIComponent('<?php echo addslashes($v_rset_no); ?>'))">
                        ✏️ Edit data (Edit Roll Set)
                    </button>
                </div>
            </div>

            <?php if (empty($data)): ?>
                <div class="display-card text-center" style="padding: 50px;">
                    <h4 style="color: #ef4444; font-weight: 700;">⚠️ ไม่พบข้อมูล Roll Set Number: <?php echo htmlspecialchars($rset_no, ENT_QUOTES, 'UTF-8'); ?></h4>
                </div>
            <?php else: ?>

            <!-- การ์ด 1: Header Key Details & Status (แสดงเรียง 1 บรรทัด 5 ช่อง) -->
            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">⚙️ Working Roll Set Header Information</h4>
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">Roll Set No (RSET_NO)</div>
                                <div class="info-value info-value-highlight"><?php echo htmlspecialchars($v_rset_no, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">Roll Ref No (RSET_REFERENCE)</div>
                                <div class="info-value" style="color: #2563eb; font-weight:700;"><?php echo htmlspecialchars($v_ref_no, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">WRL Radius (TWR+BWR / 8)</div>
                                <div class="info-value" style="color:#1d4ed8; font-weight:bold; background:#eff6ff;"><?php echo $v_wrl_radius; ?></div>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">BRL Radius (TBR+BBR / 8)</div>
                                <div class="info-value" style="color:#1d4ed8; font-weight:bold; background:#eff6ff;"><?php echo $v_brl_radius; ?></div>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">Roll Set Status (RSET_STATUS)</div>
                                <div class="info-value">
                                    <span class="<?php echo $is_op ? 'badge-status-op' : 'badge-status-cl'; ?>">
                                        <?php echo htmlspecialchars($v_status, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- การ์ด 2: Work Rolls Details (TWR & BWR) -->
            <div class="row">
                <!-- TWR -->
                <div class="col-md-6">
                    <div class="display-card">
                        <h4 class="card-title-sub">🔄 Top Work Roll (TWR) Details</h4>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="info-label">TWR Work No</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['TWR_WORKNO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <!-- Meta Details TWR จาก RDMTMSTR2 -->
                            <div class="col-md-12">
                                <div class="workno-meta-box">
                                    <span class="meta-item"><span class="meta-title">ROLL NO:</span> <span class="meta-value"><?php echo htmlspecialchars($data['TWR_ROLL_NO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></span>
                                    <span class="meta-item"><span class="meta-title">STATUS:</span> <span class="meta-value"><?php echo htmlspecialchars($data['TWR_WRK_STATUS'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></span>
                                    <span class="meta-item"><span class="meta-title">BFR (Max/Min):</span> <span class="meta-value"><?php echo formatDecimal2($data['TWR_BFR_MAX'] ?? 0) . ' / ' . formatDecimal2($data['TWR_BFR_MIN'] ?? 0); ?></span></span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">TWR Camber</div>
                                <div class="info-value"><?php echo formatDecimal2($data['TWR_CAMBER'] ?? 0); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">TWR Camber Type</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['TWR_CAMBERTYPE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">TWR In DateTime</div>
                                <div class="info-value"><?php echo formatDateDisplay($data['TWR_INDATETIME'] ?? ''); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">TWR Out DateTime</div>
                                <div class="info-value"><?php echo formatDateDisplay($data['TWR_OUTDATETIME'] ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BWR -->
                <div class="col-md-6">
                    <div class="display-card">
                        <h4 class="card-title-sub">🔄 Bottom Work Roll (BWR) Details</h4>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="info-label">BWR Work No</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['BWR_WORKNO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <!-- Meta Details BWR จาก RDMTMSTR2 -->
                            <div class="col-md-12">
                                <div class="workno-meta-box">
                                    <span class="meta-item"><span class="meta-title">ROLL NO:</span> <span class="meta-value"><?php echo htmlspecialchars($data['BWR_ROLL_NO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></span>
                                    <span class="meta-item"><span class="meta-title">STATUS:</span> <span class="meta-value"><?php echo htmlspecialchars($data['BWR_WRK_STATUS'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></span>
                                    <span class="meta-item"><span class="meta-title">BFR (Max/Min):</span> <span class="meta-value"><?php echo formatDecimal2($data['BWR_BFR_MAX'] ?? 0) . ' / ' . formatDecimal2($data['BWR_BFR_MIN'] ?? 0); ?></span></span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">BWR Camber</div>
                                <div class="info-value"><?php echo formatDecimal2($data['BWR_CAMBER'] ?? 0); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">BWR Camber Type</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['BWR_CAMBERTYPE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">BWR In DateTime</div>
                                <div class="info-value"><?php echo formatDateDisplay($data['BWR_INDATETIME'] ?? ''); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">BWR Out DateTime</div>
                                <div class="info-value"><?php echo formatDateDisplay($data['BWR_OUTDATETIME'] ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- การ์ด 3: Backup Rolls Details (TBR & BBR) -->
            <div class="row">
                <!-- TBR -->
                <div class="col-md-6">
                    <div class="display-card">
                        <h4 class="card-title-sub">🛡️ Top Backup Roll (TBR) Details</h4>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="info-label">TBR Work No</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['TBR_WORKNO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <!-- Meta Details TBR จาก RDMTMSTR2 -->
                            <div class="col-md-12">
                                <div class="workno-meta-box">
                                    <span class="meta-item"><span class="meta-title">ROLL NO:</span> <span class="meta-value"><?php echo htmlspecialchars($data['TBR_ROLL_NO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></span>
                                    <span class="meta-item"><span class="meta-title">STATUS:</span> <span class="meta-value"><?php echo htmlspecialchars($data['TBR_WRK_STATUS'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></span>
                                    <span class="meta-item"><span class="meta-title">AFT (Max/Min):</span> <span class="meta-value"><?php echo formatDecimal2($data['TBR_AFT_MAX'] ?? 0) . ' / ' . formatDecimal2($data['TBR_AFT_MIN'] ?? 0); ?></span></span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">TBR In DateTime</div>
                                <div class="info-value"><?php echo formatDateDisplay($data['TBR_INDATETIME'] ?? ''); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">TBR Out DateTime</div>
                                <div class="info-value"><?php echo formatDateDisplay($data['TBR_OUTDATETIME'] ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BBR -->
                <div class="col-md-6">
                    <div class="display-card">
                        <h4 class="card-title-sub">🛡️ Bottom Backup Roll (BBR) Details</h4>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="info-label">BBR Work No</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['BBR_WORKNO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <!-- Meta Details BBR จาก RDMTMSTR2 -->
                            <div class="col-md-12">
                                <div class="workno-meta-box">
                                    <span class="meta-item"><span class="meta-title">ROLL NO:</span> <span class="meta-value"><?php echo htmlspecialchars($data['BBR_ROLL_NO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></span>
                                    <span class="meta-item"><span class="meta-title">STATUS:</span> <span class="meta-value"><?php echo htmlspecialchars($data['BBR_WRK_STATUS'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></span>
                                    <span class="meta-item"><span class="meta-title">AFT (Max/Min):</span> <span class="meta-value"><?php echo formatDecimal2($data['BBR_AFT_MAX'] ?? 0) . ' / ' . formatDecimal2($data['BBR_AFT_MIN'] ?? 0); ?></span></span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">BBR In DateTime</div>
                                <div class="info-value"><?php echo formatDateDisplay($data['BBR_INDATETIME'] ?? ''); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">BBR Out DateTime</div>
                                <div class="info-value"><?php echo formatDateDisplay($data['BBR_OUTDATETIME'] ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php endif; ?>

        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>

<?php include 'include/footer.php';?>
</html>