$(function () {
    $("form").on("submit", function (e) {
        e.preventDefault(); // Evita recargar la página

        let datos = {
            nombrePuesto: $("#nombrePuesto").val().trim(),
            descripcion: $("#descripcion").val().trim(),
            requisitos: $("#requisitos").val().trim(),
            salarioMinimo: $("#salarioMinimo").val(), // No trim aquí, pues vacío es NULL
            salarioMaximo: $("#salarioMaximo").val(), // No trim aquí, pues vacío es NULL
            modalidad: $("#modalidad").val(),
            ubicacion: $("#ubicacion").val().trim() // Asegúrate de que este ID exista en tu HTML
        };

        // Validación de campos obligatorios en el frontend
        if (
            datos.nombrePuesto === "" ||
            datos.descripcion === "" ||
            datos.requisitos === "" ||
            datos.modalidad === ""
        ) {
            Swal.fire({
                icon: 'error',
                title: 'Campos Obligatorios',
                text: 'Por favor, completa todos los campos marcados como obligatorios.',
            });
            return;
        }

        $.ajax({
            url: '../../app/models/guardar_oferta.php', // Ruta correcta a tu script PHP
            type: 'POST',
            dataType: 'json',
            data: datos, // jQuery serializa esto como x-www-form-urlencoded
            beforeSend: function () {
                Swal.showLoading(); // Muestra el spinner de carga
            }
        })
        .done(function (response) {
            Swal.close(); // Cierra el spinner

            if (response.success) {
                $("form")[0].reset(); // Limpia el formulario
                Swal.fire({
                    title: '¡Éxito!',
                    text: response.message, // Mensaje de éxito del backend
                    icon: 'success'
                }).then(() => {
                    // Redirigir o actualizar la vista, por ejemplo a la lista de ofertas
                    window.location.href = './administrar_ofertas.html';
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: response.message || 'Ocurrió un error desconocido al publicar la oferta.', // Mensaje de error del backend
                    icon: 'error' // Usar 'error' para errores de procesamiento en el backend
                });
            }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            Swal.close(); // Cierra el spinner
            console.error("Error AJAX:", textStatus, errorThrown, jqXHR); // Para depuración en consola del navegador
            Swal.fire({
                title: 'Error de Conexión',
                text: 'No se pudo comunicar con el servidor. Por favor, revisa tu conexión a internet o inténtalo más tarde.',
                icon: 'error'
            });
        });
    });
});