<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Check session
$iduser_func = isset($_SESSION['ID']) ? $_SESSION['ID'] : '';
if (empty($iduser_func)) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized session expired.']);
    exit();
}

include("../dbcon_mats-new.php");

$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($action === 'update_po_by_invoice') {
    $invoice_no = isset($_POST['invoice_no']) ? trim($_POST['invoice_no']) : '';
    $po_no = isset($_POST['po_no']) ? trim($_POST['po_no']) : '';

    if (empty($invoice_no)) {
        echo json_encode(['status' => 'error', 'message' => 'Invoice Number is missing.']);
        exit();
    }

    try {
        // อัปเดต PO No ทุกรายการ (ทุก GR No) ที่มี invoice_no ตรงกัน
        $sql = "UPDATE RECEIVING_HEADER SET po_no = :po_no WHERE invoice_no = :invoice_no";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':po_no' => $po_no,
            ':invoice_no' => $invoice_no
        ]);

        $affectedRows = $stmt->rowCount();

        echo json_encode([
            'status' => 'success', 
            'message' => 'Updated PO No for Invoice ' . $invoice_no . ' (' . $affectedRows . ' records) successfully.'
        ]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
}
?>