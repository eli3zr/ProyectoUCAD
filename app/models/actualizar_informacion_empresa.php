<?php
// app/models/actualizar_informacion_empresa.php

session_start(); // Inicia la sesión para poder acceder al ID de usuario

// Incluye el archivo de conexión a la base de datos
require_once __DIR__ . '/../config/conexion.php'; 

header('Content-Type: application/json'); // Establece el encabezado para respuestas JSON

$response = ['success' => false, 'msg' => '', 'error' => '', 'data' => null];

// 1. Verificar si la conexión a la base de datos fue exitosa
if (!$con || $con->connect_error) { 
    $response['error'] = 'Error al conectar con la base de datos. Detalles: ' . ($con ? $con->connect_error : 'Conexión no establecida');
    echo json_encode($response);
    exit();
}

// 2. Verificar si el usuario ha iniciado sesión y obtener su ID
if (!isset($_SESSION['ID_Usuario']) || !is_numeric($_SESSION['ID_Usuario'])) {
    $response['error'] = 'No autorizado. Por favor, inicie sesión.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit();
}

$id_usuario = $_SESSION['ID_Usuario'];

// --- Lógica para manejar solicitudes GET (Cargar Perfil) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Consulta para obtener los datos del usuario, su perfil de empresa (incluyendo ubicación)
        // y el teléfono de contacto desde contactos_empresa
        // NOMBRES DE COLUMNAS ACTUALIZADOS PARA COINCIDIR CON LA BD Y CORRECCIÓN EN EL JOIN
        $stmt_get_profile = $con->prepare("
            SELECT
                u.ID_Usuario,
                u.Nombre AS NombreEmpresa,
                u.Correo_Electronico AS EmailContacto,
                pe.Descripción AS DescripcionEmpresa,
                pe.id_pais_fk AS pais_id_pais,             
                pe.id_departamento_fk AS departamento_id_departamento, 
                pe.id_municipio_fk AS municipio_id_municipio,   
                pe.id_distrito_fk AS distrito_id_distrito,     
                ce.Teléfono AS TelefonoContacto
            FROM
                usuario u
            LEFT JOIN
                perfil_empresa pe ON u.ID_Usuario = pe.usuario_ID_Usuario
            LEFT JOIN
                contactos_empresa ce ON pe.ID_Perfil_Empresa = ce.ID_Empresa -- <<-- ¡CORRECCIÓN AQUÍ!
            WHERE
                u.ID_Usuario = ?
        ");

        if ($stmt_get_profile === false) {
            throw new Exception("Error en la preparación de la consulta GET: " . $con->error);
        }

        $stmt_get_profile->bind_param("i", $id_usuario);
        $stmt_get_profile->execute();
        $result_get_profile = $stmt_get_profile->get_result();

        if ($result_get_profile->num_rows > 0) {
            $data = $result_get_profile->fetch_assoc();
            
            $response['success'] = true;
            $response['data'] = [
                'ID_Usuario' => $data['ID_Usuario'],
                'NombreEmpresa' => $data['NombreEmpresa'],
                'DescripcionEmpresa' => $data['DescripcionEmpresa'],
                'EmailContacto' => $data['EmailContacto'],
                'TelefonoContacto' => $data['TelefonoContacto'],
                'pais_id_pais' => $data['pais_id_pais'], 
                'departamento_id_departamento' => $data['departamento_id_departamento'], 
                'municipio_id_municipio' => $data['municipio_id_municipio'], 
                'distrito_id_distrito' => $data['distrito_id_distrito'] 
            ];
        } else {
            $response['success'] = true; 
            $response['data'] = [ 
                'ID_Usuario' => $id_usuario,
                'NombreEmpresa' => '',
                'DescripcionEmpresa' => '',
                'EmailContacto' => '',
                'TelefonoContacto' => '',
                'pais_id_pais' => null,
                'departamento_id_departamento' => null,
                'municipio_id_municipio' => null,
                'distrito_id_distrito' => null
            ];
        }
        $stmt_get_profile->close();

    } catch (Exception $e) {
        error_log("Error al cargar perfil de empresa (GET): " . $e->getMessage());
        $response['error'] = 'Error interno del servidor al cargar el perfil: ' . $e->getMessage();
        http_response_code(500);
    } finally {
        if ($con) { $con->close(); }
    }
    echo json_encode($response);
    exit(); 
}

// --- Lógica para manejar solicitudes POST (Actualizar Perfil) ---
$nombreEmpresa = $_POST['nombreEmpresa'] ?? '';
$descripcionEmpresa = $_POST['descripcionEmpresa'] ?? '';
$emailContacto = $_POST['emailContacto'] ?? '';
$telefonoContacto = $_POST['telefonoContacto'] ?? '';
$paisId = $_POST['pais'] ?? null;
$departamentoId = $_POST['departamento'] ?? null;
$municipioId = $_POST['municipio'] ?? null;
$distritoId = $_POST['distrito'] ?? null;

// Asegurarse de que los IDs de ubicación sean enteros o NULL
$paisId = ($paisId !== null && $paisId !== '') ? (int)$paisId : null;
$departamentoId = ($departamentoId !== null && $departamentoId !== '') ? (int)$departamentoId : null;
$municipioId = ($municipioId !== null && $municipioId !== '') ? (int)$municipioId : null;
$distritoId = ($distritoId !== null && $distritoId !== '') ? (int)$distritoId : null;


if (empty($nombreEmpresa) || empty($emailContacto)) {
    $response['error'] = 'Los campos Nombre de la Empresa y Correo Electrónico son obligatorios.';
} elseif (!filter_var($emailContacto, FILTER_VALIDATE_EMAIL)) {
    $response['error'] = 'El correo electrónico no es válido.';
} else {
    try {
        $con->begin_transaction();

        // 1. Actualizar la tabla 'usuario' (solo Nombre y Correo_Electronico)
        $stmt_usuario = $con->prepare("
            UPDATE usuario 
            SET 
                Nombre = ?, 
                Correo_Electronico = ?
            WHERE 
                ID_Usuario = ?
        ");
        if ($stmt_usuario === false) {
            throw new Exception("Error en la preparación de la consulta de usuario: " . $con->error);
        }
        $stmt_usuario->bind_param("ssi", 
            $nombreEmpresa, 
            $emailContacto, 
            $id_usuario
        );
        $stmt_usuario->execute();
        $stmt_usuario->close(); 

        // 2. Actualizar o insertar en la tabla 'perfil_empresa' (incluyendo descripción y ubicación)
        // Usamos ID_Perfil_Empresa para la selección y ID_Empresa en contactos_empresa
        $stmt_check_perfil = $con->prepare("SELECT ID_Perfil_Empresa FROM perfil_empresa WHERE usuario_ID_Usuario = ?"); // <<-- ¡CORRECCIÓN AQUÍ!
        if ($stmt_check_perfil === false) {
            throw new Exception("Error al verificar perfil existente: " . $con->error);
        }
        $stmt_check_perfil->bind_param("i", $id_usuario);
        $stmt_check_perfil->execute();
        $result_check_perfil = $stmt_check_perfil->get_result();
        $perfil_existente = $result_check_perfil->fetch_assoc();
        $stmt_check_perfil->close(); 

        $id_empresa = null; 
        if ($perfil_existente) {
            $id_empresa = $perfil_existente['ID_Perfil_Empresa']; // <<-- ¡CORRECCIÓN AQUÍ!
            // Si el perfil existe, actualiza la descripción y los campos de ubicación
            $stmt_perfil = $con->prepare("
                UPDATE perfil_empresa 
                SET 
                    Descripción = ?,
                    id_pais_fk = ?,            
                    id_departamento_fk = ?,    
                    id_municipio_fk = ?,       
                    id_distrito_fk = ?          
                WHERE 
                    usuario_ID_Usuario = ?
            ");
            if ($stmt_perfil === false) {
                throw new Exception("Error en la preparación de la consulta de perfil (UPDATE): " . $con->error);
            }
            $stmt_perfil->bind_param("siiiii", 
                $descripcionEmpresa, 
                $paisId, 
                $departamentoId, 
                $municipioId, 
                $distritoId,
                $id_usuario
            );
            $stmt_perfil->execute();
            $stmt_perfil->close(); 
        } else {
            // Si el perfil NO existe, lo inserta (incluyendo descripción y ubicación)
            $id_categoria_placeholder = 1; 

            $stmt_perfil = $con->prepare("
                INSERT INTO perfil_empresa (usuario_ID_Usuario, Descripción, id_pais_fk, id_departamento_fk, id_municipio_fk, id_distrito_fk, ID_Categoria) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt_perfil === false) {
                throw new Exception("Error en la preparación de la consulta de perfil (INSERT): " . $con->error);
            }
            $stmt_perfil->bind_param("isiiiii", 
                $id_usuario, 
                $descripcionEmpresa, 
                $paisId, 
                $departamentoId, 
                $municipioId, 
                $distritoId,
                $id_categoria_placeholder 
            );
            $stmt_perfil->execute();
            $id_empresa = $con->insert_id; // Este insert_id es el ID_Perfil_Empresa del nuevo registro
            $stmt_perfil->close(); 
        }

        // 3. Actualizar o insertar en la tabla 'contactos_empresa'
        if ($id_empresa) { // $id_empresa ahora es el ID_Perfil_Empresa
            $stmt_check_contacto = $con->prepare("SELECT ID_Contacto FROM contactos_empresa WHERE ID_Empresa = ?");
            if ($stmt_check_contacto === false) {
                throw new Exception("Error al verificar contacto existente: " . $con->error);
            }
            $stmt_check_contacto->bind_param("i", $id_empresa); // <<-- ID_Empresa en contactos_empresa es la FK de ID_Perfil_Empresa
            $stmt_check_contacto->execute();
            $result_check_contacto = $stmt_check_contacto->get_result();
            $contacto_existente = $result_check_contacto->fetch_assoc();
            $stmt_check_contacto->close(); 

            if ($contacto_existente) {
                $stmt_contacto = $con->prepare("
                    UPDATE contactos_empresa 
                    SET 
                        Teléfono = ?
                    WHERE 
                        ID_Empresa = ?
                ");
                if ($stmt_contacto === false) {
                    throw new Exception("Error en la preparación de la consulta de contacto (UPDATE): " . $con->error);
                }
                $stmt_contacto->bind_param("si", $telefonoContacto, $id_empresa); 
                $stmt_contacto->execute();
                $stmt_contacto->close(); 
            } else {
                $stmt_contacto = $con->prepare("
                    INSERT INTO contactos_empresa (ID_Empresa, Teléfono) 
                    VALUES (?, ?)
                ");
                if ($stmt_contacto === false) {
                    throw new Exception("Error en la preparación de la consulta de contacto (INSERT): " . $con->error);
                }
                $stmt_contacto->bind_param("is", $id_empresa, $telefonoContacto); 
                $stmt_contacto->execute();
                $stmt_contacto->close(); 
            }
        } else {
            error_log("Advertencia: No se pudo obtener ID_Perfil_Empresa para actualizar/insertar contacto para usuario ID: " . $id_usuario);
        }

        $con->commit();
        $response['success'] = true;
        $response['msg'] = 'La información de la empresa ha sido actualizada correctamente.';

    } catch (Exception $e) {
        $con->rollback();
        error_log("Error al actualizar perfil de empresa: " . $e->getMessage()); 
        $response['error'] = 'Error interno del servidor al actualizar el perfil: ' . $e->getMessage();
        http_response_code(500);
    } finally {
        if ($con) { $con->close(); }
    }
}

echo json_encode($response);
?>