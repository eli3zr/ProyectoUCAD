$(document).ready(function() {
    console.log('El documento está listo.');

    // Selector para el botón "Nuevo Estudiante" (asegúrate de que este ID exista en tu HTML principal)
    $("#nuevoEstudianteButton").click(function() {
        console.log('Se hizo clic en el botón Nuevo Estudiante.');
        // Selector para mostrar la modal "Nuevo Estudiante" (coincide con el ID de tu modal)
        $('#nuevoEstudianteModal').modal('show');
        console.log('Se intentó mostrar la modal.');
    });

    // Evento submit del formulario dentro de la modal "Nuevo Estudiante"
    $("#formNuevoEstudiante").submit(function(event) {
        event.preventDefault();

        var nombreEstudiante = $("#nombreEstudiante").val().trim();
        var correoElectronico = $("#correoElectronico").val().trim();
        var carrera = $("#carrera").val().trim();
        var estado = $("#estado").val();

        if (nombreEstudiante === "") {
            Swal.fire({
                icon: 'warning',
                title: '¡Campo Requerido!',
                text: 'Por favor, ingrese el nombre del estudiante.',
                confirmButtonColor: '#F0C11A'
            });
            return;
        }

        if (correoElectronico === "") {
            Swal.fire({
                icon: 'warning',
                title: '¡Campo Requerido!',
                text: 'Por favor, ingrese el correo electrónico.',
                confirmButtonColor: '#F0C11A'
            });
            return;
        } else if (!isValidEmail(correoElectronico)) {
            Swal.fire({
                icon: 'warning',
                title: '¡Formato Incorrecto!',
                text: 'Por favor, ingrese un correo electrónico válido.',
                confirmButtonColor: '#F0C11A'
            });
            return;
        }

        if (carrera === "") {
            Swal.fire({
                icon: 'warning',
                title: '¡Campo Requerido!',
                text: 'Por favor, ingrese el nombre de la Carrera.',
                confirmButtonColor: '#F0C11A'
            });
            return;
        }

        if (estado === "") {
            Swal.fire({
                icon: 'warning',
                title: '¡Campo Requerido!',
                text: 'Por favor, seleccione el estado del estudiante.',
                confirmButtonColor: '#F0C11A'
            });
            return;
        }

        $.ajax({
            url: '../../app/models/guardar_estudiante.php',
            type: 'POST',
            data: {
                nombreEstudiante: nombreEstudiante,
                correoElectronico: correoElectronico,
                carrera: carrera,
                estado: estado
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Estudiante Creado!',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then((result) => {
                        // Selector para ocultar la modal "Nuevo Estudiante"
                        $('#nuevoEstudianteModal').modal('hide');
                        $("#formNuevoEstudiante")[0].reset();
                        console.log('Datos de estudiante guardados:', { nombreEstudiante: nombreEstudiante, correoElectronico: correoElectronico, carrera: carrera, estado: estado });
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '¡Error!',
                        text: response.message
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: '¡Error de Conexión!',
                    text: 'Ocurrió un error al enviar los datos: ' + error
                });
            }
        });
    });

    // Evento para resetear el formulario cuando se oculta la modal "Nuevo Estudiante"
    $('#nuevoEstudianteModal').on('hidden.bs.modal', function (e) {
        $("#formNuevoEstudiante")[0].reset();
    });
});

// Función de validación de correo electrónico (si no la tienes definida en otro lugar)
function isValidEmail(email) {
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}