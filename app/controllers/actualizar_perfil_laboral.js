$(function () {
    // Referencias a los elementos del formulario
    const noExperienciaRadio = $('#noExperiencia');
    const siExperienciaRadio = $('#siExperiencia');
    const descripcionLaboralContainer = $('#descripcionLaboralContainer');
    const descripcionLaboralTextarea = $('#descripcion_laboral_resumen');
    const formInformacionLaboral = $('#formInformacionLaboral');

    // Función para mostrar/ocultar el campo de descripción laboral
    function toggleDescripcionField() {
        if (siExperienciaRadio.is(':checked')) {
            descripcionLaboralContainer.show(); // Muestra el contenedor
            descripcionLaboralTextarea.attr('required', 'required'); // Hace el textarea obligatorio
        } else {
            descripcionLaboralContainer.hide(); // Oculta el contenedor
            descripcionLaboralTextarea.removeAttr('required'); // Remueve el atributo obligatorio
            descripcionLaboralTextarea.val(''); // Limpia el contenido si se oculta
        }
    }

    // Ejecutar la función al cargar la página para establecer el estado inicial
    toggleDescripcionField();

    // Asignar el evento 'change' a los radio buttons para actualizar la visibilidad
    noExperienciaRadio.on('change', toggleDescripcionField);
    siExperienciaRadio.on('change', toggleDescripcionField);


    // Manejador del evento 'submit' del formulario
    formInformacionLaboral.on("submit", function (e) {
        e.preventDefault(); // Prevenir el envío de formulario por defecto

        // Crear un objeto FormData para capturar todos los datos del formulario,
        // incluyendo los radio buttons y el textarea.
        // Esto es más robusto que construir un objeto 'datos' manualmente.
        let formData = new FormData(this); // 'this' se refiere al formulario

        // Si se seleccionó "No tengo experiencia laboral", asegúrate de que el campo de descripción no se envíe o sea null.
        // FormData automáticamente incluye solo los campos con 'name',
        // y como el textarea se limpia y no es 'required' cuando no hay experiencia, esto debería funcionar bien.
        // Sin embargo, si quieres ser explícito, puedes ajustar el valor antes de enviar.
        if (noExperienciaRadio.is(':checked')) {
            formData.set('descripcion_laboral_resumen', ''); // Asegura que el valor sea vacío si "no experiencia"
        }

        $.ajax({
            url: '../../app/models/actualizar_perfil_laboral.php', // Asegúrate de que esta URL sea correcta
            type: 'POST',
            dataType: 'json',
            processData: false, // Necesario al usar FormData
            contentType: false, // Necesario al usar FormData
            data: formData, // Enviar el objeto FormData
            beforeSend: function () {
                Swal.showLoading(); // Mostrar el indicador de carga de SweetAlert2
            }
        })
        .done(function (response) {
            Swal.close(); // Cerrar el indicador de carga

            if (response.success) {
                Swal.fire({
                    title: 'Éxito',
                    text: response.msg,
                    icon: 'success',
                    confirmButtonText: '¡Genial!'
                });
                // Opcional: Actualizar la UI si es necesario, o recargar parte de la página
            } else {
                Swal.fire({
                    title: 'Error',
                    text: response.error,
                    icon: 'error',
                    confirmButtonText: 'Entendido'
                });
            }
        })
        .fail(function () {
            Swal.close(); // Cerrar el indicador de carga en caso de fallo
            Swal.fire({
                title: 'Error de Conexión',
                text: 'No se pudo conectar con el servidor. Por favor, inténtalo de nuevo.',
                icon: 'error'
            });
        });
    });
});