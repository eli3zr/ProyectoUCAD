$(function () {
    // Escucha el evento submit del formulario de CV
    // Es importante usar el ID del formulario para que sea específico
    $("#formActualizarCV").on("submit", function (e) {
        e.preventDefault(); // Previene el envío tradicional del formulario

        const cvInput = $("#cvNuevo")[0]; // Accede al elemento DOM nativo del input file
        const cvFile = cvInput.files[0]; // Obtiene el primer archivo seleccionado

        // Validaciones del lado del cliente (opcional pero recomendado)
        if (!cvFile) {
            Swal.fire({
                title: 'Atención',
                text: 'Por favor, selecciona un archivo CV para subir.',
                icon: 'warning',
                confirmButtonText: 'Ok'
            });
            return; // Detiene la ejecución si no hay archivo
        }

        // Validar tamaño del archivo (2MB)
        const maxFileSize = 2 * 1024 * 1024; // 2 MB en bytes
        if (cvFile.size > maxFileSize) {
            Swal.fire({
                title: 'Error',
                text: 'El tamaño máximo permitido para el CV es 2MB.',
                icon: 'error',
                confirmButtonText: 'Ok'
            });
            return;
        }

        // Validar tipo de archivo
        const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!allowedTypes.includes(cvFile.type)) {
            Swal.fire({
                title: 'Error',
                text: 'Formatos de archivo permitidos: PDF, DOC, DOCX.',
                icon: 'error',
                confirmButtonText: 'Ok'
            });
            return;
        }

        // Crear un objeto FormData
        // Esto es crucial para enviar archivos vía AJAX
        const formData = new FormData();
        formData.append('cvNuevo', cvFile); // 'cvNuevo' es el nombre que tu script PHP esperará para el archivo

        // Puedes añadir otros datos si los necesitas, por ejemplo, el ID del estudiante
        // formData.append('id_estudiante', ID_DEL_ESTUDIANTE_AQUI); // Necesitarías obtener este ID de alguna manera (ej. de una variable JS, un input hidden, etc.)

        $.ajax({
            url: '../../app/models/actualizar_cv_estudiante.php', // **¡Importante! Nueva URL para tu script PHP de CV**
            type: 'POST',
            dataType: 'json', // Esperamos una respuesta JSON desde el PHP
            data: formData, // Envía el objeto FormData
            processData: false, // ¡No procesar los datos! jQuery normalmente convierte objetos a query strings
            contentType: false, // ¡No establecer el tipo de contenido! FormData lo hace automáticamente
            beforeSend: function () {
                Swal.showLoading();
            }
        })
        .done(function (response) {
            Swal.close();

            if (response.success) {
                Swal.fire({
                    title: 'Éxito',
                    text: response.msg,
                    icon: 'success',
                    confirmButtonText: 'Perfecto'
                }).then(() => {
                    // Opcional: Recargar la página o actualizar la sección del CV en el HTML
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: response.error,
                    icon: 'error',
                    confirmButtonText: 'Ok'
                });
            }
        })
        .fail(function () {
            Swal.close();
            Swal.fire({
                title: 'Error',
                text: 'No se pudo conectar con el servidor. Por favor, inténtalo de nuevo más tarde.',
                icon: 'error'
            });
        });
    });

    // Si tienes otros formularios en la página, asegúrate de que sus selectores no colisionen.
    // Tu selector original "form").eq(1)" es muy frágil. Es mejor usar IDs específicos.
    // Si tu otro formulario es el de enlaces, es mejor que tenga su propio ID como "#formActualizarEnlaces"
    // y lo manejes por separado:
    // $("#formActualizarEnlaces").on("submit", function (e) {
    //     e.preventDefault();
    //     // ... tu código AJAX para enlaces ...
    // });
});