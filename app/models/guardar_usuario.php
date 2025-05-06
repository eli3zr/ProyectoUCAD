<?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = $_POST['nombre'];
        $correo = $_POST['correo'];
        $contrasena = $_POST['contrasena'];
        $rol = $_POST['rol'];
        $estado = $_POST['estado'];

        $response = array(
            'success' => true,
            'message' => 'Usuario creado exitosamente'
        );

        // Simulación de una respuesta con error (podrías tener validaciones aquí)
        // $response = array(
        //     'success' => false,
        //     'message' => 'Error: El correo electrónico ya existe (simulado).'
        // );

        header('Content-Type: application/json');
        echo json_encode($response);
    } else {

        $response = array(
            'success' => false,
            'message' => 'Acceso no permitido.'
        );
        header('Content-Type: application/json');
        echo json_encode($response);
    }
?>