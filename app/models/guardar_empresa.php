<?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombreEmpresa = $_POST['nombreEmpresa'] ?? '';
        $correoElectronico = $_POST['correoElectronico'] ?? '';
        $sitioWeb = $_POST['sitioWeb'] ?? '';
        $estado = $_POST['estado'] ?? '';

        if (empty($nombreEmpresa)) {
            $response = array('success' => false, 'message' => 'Error: El nombre de la empresa es requerido (simulado).');
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }

        if (empty($correoElectronico) || !filter_var($correoElectronico, FILTER_VALIDATE_EMAIL)) {
            $response = array('success' => false, 'message' => 'Error: El correo electrónico no es válido (simulado).');
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }

        if (empty($sitioWeb) || !filter_var($sitioWeb, FILTER_VALIDATE_URL)) {
            $response = array('success' => false, 'message' => 'Error: El sitio web no es una URL válida (simulado).');
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }

        if (empty($estado)) {
            $response = array('success' => false, 'message' => 'Error: El estado de la empresa es requerido (simulado).');
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }

        $response = array(
            'success' => true,
            'message' => 'Empresa guardada exitosamente (simulado).'
        );

        // Simulación de una respuesta con error (descomentar para probar el error en el cliente)
        // $response = array(
        //     'success' => false,
        //     'message' => 'Error al guardar la empresa (simulado).'
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