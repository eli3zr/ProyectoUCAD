<?php
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $params = $_POST;

        // Array para almacenar los errores del servidor
        $errores_servidor = [];

        // Validación del Nombre de la Empresa
        if (empty($params['nombre'])) {
            $errores_servidor['nombre'] = "El nombre de la empresa es requerido.";
        }

        // Validación del Teléfono
        if (empty($params['telefono'])) {
            $errores_servidor['telefono'] = "El teléfono es requerido.";
        }

        // Validación del Correo Electrónico
        if (empty($params['email'])) {
            $errores_servidor['email'] = "El correo electrónico es requerido.";
        } elseif (!filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
            $errores_servidor['email'] = "El formato del correo electrónico no es válido.";
        }
        // **TODO: Verificar si el correo electrónico ya existe en la base de datos de empresas**

        // Validación de la Categoría
        if (empty($params['categoria'])) {
            $errores_servidor['categoria'] = "La categoría es requerida.";
        }

        // Validación del País
        if (empty($params['pais'])) {
            $errores_servidor['pais'] = "El país es requerido.";
        }

        // Validación del Departamento
        if (empty($params['departamento'])) {
            $errores_servidor['departamento'] = "El departamento es requerido.";
        }

        // Validación de la Clave
        if (empty($params['clave'])) {
            $errores_servidor['clave'] = "La clave es requerida.";
        } elseif (strlen($params['clave']) < 6) {
            $errores_servidor['clave'] = "La clave debe tener al menos 6 caracteres.";
        }

        // Validación de Repetir Clave
        if (empty($params['repetir-clave'])) {
            $errores_servidor['repetir-clave'] = "Debes repetir la clave.";
        } elseif ($params['clave'] !== $params['repetir-clave']) {
            $errores_servidor['repetir-clave'] = "Las claves no coinciden.";
        }

        // Validación de Términos y Condiciones
        if (!isset($params['terminos']) || $params['terminos'] !== 'true') {
            $errores_servidor['terminos'] = "Debes aceptar los términos y condiciones.";
        }

        if (empty($errores_servidor)) {
            // **TODO: Conectar a la base de datos MySQL**
            // **TODO: Escapar los datos para prevenir inyección SQL**
            $nombre_empresa = trim($params['nombre']);
            $telefono = trim($params['telefono']);
            $email = filter_var($params['email'], FILTER_SANITIZE_EMAIL);
            $categoria = $params['categoria'];
            $pais = $params['pais'];
            $departamento = $params['departamento'];
            $clave = password_hash($params['clave'], PASSWORD_DEFAULT); // Encriptar la clave

            // **TODO: Insertar los datos en la tabla de empresas**
            // $sql = "INSERT INTO empresas (nombre_empresa, telefono, email, categoria, pais, departamento, clave, fecha_registro) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            // $stmt = $conn->prepare($sql);
            // $stmt->bind_param("sssssss", $nombre_empresa, $telefono, $email, $categoria, $pais, $departamento, $clave);

            // if ($stmt->execute()) {
                $respuesta = array(
                    'success' => true,
                    'message' => 'Registro de empresa exitoso.'
                );
            // } else {
            //     $respuesta = array(
            //         'success' => false,
            //         'message' => 'Error al registrar la empresa en la base de datos.'
            //     );
            //     // **TODO: Log del error para depuración**
            // }

            // **TODO: Cerrar la conexión a la base de datos**
            // $stmt->close();
            // $conn->close();

        } else {
            // Si hay errores de validación en el servidor
            $respuesta = array(
                'success' => false,
                'errores' => $errores_servidor,
                'message' => 'Por favor, corrige los errores en el formulario.'
            );
        }

        echo json_encode($respuesta);

    } else {
        $respuesta = array(
            'success' => false,
            'message' => 'Método de petición no válido.'
        );
        echo json_encode($respuesta);
    }
?>