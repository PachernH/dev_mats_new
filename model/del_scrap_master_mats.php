<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

include('../dbcon_mats-new.php');

$response = array('status' => 'error', 'message' => false, 'error' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data_tag = isset($_POST['data_tag']) ? $_POST['data_tag'] : '';

    if (!empty($data_tag)) {
        $params = explode('*', $data_tag);
        $id = isset($params[0]) ? trim($params[0]) : '';

        if (!empty($id)) {
            try {
                $sql = "DELETE FROM STNDMSTR14 WHERE ID = :id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $response['status'] = 'success';
                    $response['message'] = true;
                } else {
                    $response['error'] = 'Unable to delete the record.';
                }
            } catch (PDOException $e) {
                $response['error'] = 'Database Error: ' . $e->getMessage();
            }
        } else {
            $response['error'] = 'Invalid ID provided.';
        }
    } else {
        $response['error'] = 'No parameters received.';
    }
} else {
    $response['error'] = 'Invalid Request Method.';
}

echo json_encode($response);
exit;