$(function () {
    $("form").on("submit", function (e) {
        e.preventDefault(); // Evita recargar la página

        let datos = {
            nombre: $("#nombre").val().trim(),
            apellido: $("#apellido").val().trim(),
            email: $("#email").val().trim(),
            fechaNacimiento: $("#fecha-nacimiento").val(),
            genero: $("#genero").val(),
            carrera: $("#carrera").val().trim(),
            clave: $("#clave").val(),
            repetirClave: $("#repetir-clave").val(),
            terminos: $("#terminos").prop("checked"),
            notificaciones: $("#notificaciones").prop("checked")
        };

        $.ajax({
            url: '../../app/models/registrar_estudiante.php',
            type: 'POST',
            dataType: 'json',
            data: datos,
            beforeSend: function () {
                Swal.showLoading();
            }
        })
        .done(function (response) {
            console.log("Respuesta del servidor:", response);
            Swal.close(); // Cierra el "Cargando..."

            if (response.success) {
                $("form")[0].reset(); // Limpia el formulario
                Swal.fire({
                    title: 'Éxito',
                    text: response.msg,
                    icon: 'success',
                    confirmButtonText: '¡Genial!'
                }).then((result) => {
                    // Redirigir al usuario a la página de inicio de sesión si lo deseas
                    if (result.isConfirmed) {
                        window.location.href = 'login.html';
                    }
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
        .fail(function () {
            Swal.close();
            Swal.fire({
                title: 'Error',
                text: 'No se pudo conectar con el servidor.',
                icon: 'error'
            });
        });
    });
});