<?php
header('Content-Type: application/json');

$response = [];

if (isset($_POST['contacto']) && !empty($_POST['mensaje'])) {
    $contacto = $_POST['contacto'];
    $mensaje = $_POST['mensaje'];

    // Simulación de procesamiento del mensaje (sin guardar en BD)
    // Aquí iría la lógica para guardar en la base de datos cuando la tengas

    $response = [
        'success' => true,
        'msg' => 'Mensaje enviado a ' . htmlspecialchars($contacto) . ': ' . htmlspecialchars($mensaje)
    ];
} else {
    $response = [
        'success' => false,
        'error' => 'No se recibieron el contacto o el mensaje.'
    ];
}

echo json_encode($response);
?>