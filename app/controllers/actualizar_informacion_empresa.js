// Jobtrack_Ucad-main/app/controllers/actualizar_informacion_empresa.js

$(function () {
    // Manejar el envío del formulario de Información de la Empresa
    $("#formInformacionEmpresa").on("submit", function (e) {
        e.preventDefault(); // Previene el envío tradicional del formulario

        let datos = {
            nombreEmpresa: $("#nombreEmpresa").val().trim(),
            descripcionEmpresa: $("#descripcionEmpresa").val().trim(),
            emailContacto: $("#emailContacto").val().trim(),
            telefonoContacto: $("#telefonoContacto").val().trim()
        };

        // Puedes añadir validaciones adicionales aquí si lo deseas
        if (!datos.nombreEmpresa || !datos.emailContacto) {
            Swal.fire('Advertencia', 'El Nombre de la Empresa y el Correo Electrónico son obligatorios.', 'warning');
            return;
        }
        if (datos.emailContacto && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(datos.emailContacto)) {
            Swal.fire('Advertencia', 'Por favor, ingrese un formato de correo electrónico válido.', 'warning');
            return;
        }

        $.ajax({
            url: '../models/actualizar_informacion_empresa.php', // RUTA CORREGIDA: ../models/
            type: 'POST',
            dataType: 'json', // Esperamos una respuesta JSON
            data: datos, // Los datos a enviar
            beforeSend: function () {
                Swal.showLoading(); // Muestra el indicador de carga de SweetAlert2
            }
        })
        .done(function (response) {
            Swal.close(); // Cierra el indicador de carga

            if (response.success) {
                Swal.fire({
                    title: 'Éxito',
                    text: response.msg,
                    icon: 'success',
                    confirmButtonText: '¡Genial!'
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: response.error,
                    icon: 'error',
                    confirmButtonText: 'Entendido'
                });
            }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            Swal.close(); // Cierra el indicador de carga
            Swal.fire({
                title: 'Error de Conexión',
                text: 'No se pudo conectar con el servidor. Por favor, intente de nuevo más tarde. Detalles: ' + textStatus + ' - ' + errorThrown,
                icon: 'error'
            });
            console.error("Error AJAX:", textStatus, errorThrown, jqXHR);
        });
    });

    // La función para carga inicial de datos (si la necesitas) no está aquí.
    // Se asume que los campos de información básica son pre-llenados directamente en el HTML/PHP.
});