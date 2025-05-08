<?php
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'];
        $nombreEmpresa = $_POST['nombreEmpresa'];
        $correoEmpresa = $_POST['correoEmpresa'];
        $sitioWebEmpresa = $_POST['sitioWebEmpresa'];
        $estadoEmpresa = $_POST['estadoEmpresa'];


        $response = array(
            'success' => true,
            'message' => "Empresa con ID {$id} actualizada exitosamente."
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