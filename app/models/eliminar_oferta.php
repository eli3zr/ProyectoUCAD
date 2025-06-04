<?php
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'];

        $response = array(
            'success' => true,
            'message' => "Usuario con ID {$id} eliminado exitosamente"
        );
        echo json_encode($response);

    } else {
        $response = array(
            'success' => false,
            'message' => 'Acceso no permitido.'
        );
        echo json_encode($response);
    }
?>