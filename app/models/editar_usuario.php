<?php
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'];
        $nombre = $_POST['nombre'];
        $correo = $_POST['correo'];
        $contrasena = $_POST['contrasena']; 
        $rol = $_POST['rol'];
        $estado = $_POST['estado'];


        $response = array(
            'success' => true,
            'message' => "Usuario con ID {$id} actualizado exitosamente (simulado)."
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