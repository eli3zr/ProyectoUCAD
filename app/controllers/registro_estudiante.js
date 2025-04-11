$(function () {
    $("#registroEstudianteForm").submit(function (event) {
        event.preventDefault(); // Evita el envío tradicional del formulario

        enviar_registro();
    });
});

function enviar_registro() {
    let formData = new FormData();
    formData.append('nombre', $("#nombre").val());
    formData.append('apellido', $("#apellido").val());
    formData.append('email', $("#email").val());
    formData.append('fechaNacimiento', $("#fecha-nacimiento").val());
    formData.append('genero', $("#genero").val());
    formData.append('carrera', $("#carrera").val());
    formData.append('clave', $("#clave").val());
    formData.append('repetirClave', $("#repetir-clave").val());
    formData.append('terminos', $("#terminos").prop('checked'));
    formData.append('notificaciones', $("#notificaciones").prop('checked'));
    formData.append('cv', $("#cv")[0].files[0]); // Añade el archivo CV

    $.ajax({
        url: '/Jobtrack_ucad/app/models/registrar_estudiante.php',
        type: 'POST',
        dataType: 'json',
        data: formData,
        contentType: false, // Importante para FormData
        processData: false, // Importante para FormData
        beforeSend: function () {
            Swal.showLoading();
        },
        complete: function () {
            Swal.hideLoading();
        }
    })
        .done(function (response) {
            if (response.success) {
                $("#registroEstudianteForm").trigger('reset');
                Swal.fire({
                    title: "¡Registro Exitoso!",
                    text: response.message,
                    icon: "success"
                });
            } else {
                Swal.fire({
                    title: "Error",
                    text: response.error,
                    icon: "error"
                });
            }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            console.error("Error en la petición AJAX:", textStatus, errorThrown);
            Swal.fire({
                title: "Error",
                text: "Ocurrió un error al comunicarse con el servidor.",
                icon: "error"
            });
        });
}