<?php

$response = [];

// Captura los datos enviados
$datos = $_POST;

// Validación básica
if (
    empty($datos['sitioWeb']) &&
    empty($datos['linkedinPerfil'])
) {
    $response = [
        'success' => false,
        'error' => 'Debes ingresar al menos un enlace (sitio web o perfil de LinkedIn).'
    ];
} else {
    // Validaciones individuales opcionales
    if (!empty($datos['sitioWeb']) && !filter_var($datos['sitioWeb'], FILTER_VALIDATE_URL)) {
        $response = [
            'success' => false,
            'error' => 'El sitio web no tiene un formato válido.'
        ];
    } elseif (!empty($datos['linkedinPerfil']) && !filter_var($datos['linkedinPerfil'], FILTER_VALIDATE_URL)) {
        $response = [
            'success' => false,
            'error' => 'El perfil de LinkedIn no tiene un formato válido.'
        ];
    } else {
        // Simulación de actualización
        /*
        $stmt = $pdo->prepare("UPDATE empresas SET sitio_web = :sitioWeb, linkedin = :linkedin WHERE id = :id");
        $stmt->bindParam(':sitioWeb', $datos['sitioWeb']);
        $stmt->bindParam(':linkedin', $datos['linkedinPerfil']);
        $stmt->bindParam(':id', $empresaId); // Reemplaza con tu lógica de sesión o parámetro
        $stmt->execute();
        */

        $response = [
            'success' => true,
            'msg' => 'Los enlaces han sido actualizados correctamente.'
        ];
    }
}

// Responder con JSON
header('Content-Type: application/json');
echo json_encode($response);
