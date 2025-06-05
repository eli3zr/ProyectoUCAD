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
            url: '../../app/models/guardar_oferta.php', 
            type: 'POST',
            dataType: 'json',
            data: datos, 
            beforeSend: function () {
                Swal.showLoading(); 
            }
        })
        .done(function (response) {
            Swal.close(); 

            if (response.success) {
                $("form")[0].reset(); // Limpia el formulario
                Swal.fire({
                    title: '¡Éxito!',
                    text: response.message, 
                    icon: 'success'
                }).then(() => {
                    // Redirigir o actualizar la vista, por ejemplo a la lista de ofertas
                    window.location.href = './administrar_ofertas.html';
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: response.message || 'Ocurrió un error desconocido al publicar la oferta.', 
                    icon: 'error' 
                });
            }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            Swal.close(); // Cierra el spinner
            console.error("Error AJAX:", textStatus, errorThrown, jqXHR); 
            Swal.fire({
                title: 'Error de Conexión',
                text: 'No se pudo comunicar con el servidor. Por favor, revisa tu conexión a internet o inténtalo más tarde.',
                icon: 'error'
            });
        });
    });
});