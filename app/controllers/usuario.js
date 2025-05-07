$(document).ready(function() {
    $("#formNuevoUsuario").submit(function(event) {
        event.preventDefault();

        var nombre = $("#nombre").val().trim();
        var correo = $("#correo").val().trim();
        var contrasena = $("#contrasena").val().trim();
        var rol = $("#rol").val();
        var estado = $("#estado").val();

        if (nombre === "") {
            Swal.fire({
                icon: 'warning',
                title: '¡Campo Requerido!',
                text: 'Por favor, ingrese el nombre del usuario.',
                confirmButtonColor: '#ff5722'
            });
            return; 
        }

        if (correo === "") {
            Swal.fire({
                icon: 'warning',
                title: '¡Campo Requerido!',
                text: 'Por favor, ingrese el correo electrónico.',
                confirmButtonColor: '#ff5722'
            });
            return;
        } else if (!isValidEmail(correo)) {
            Swal.fire({
                icon: 'warning',
                title: '¡Formato Incorrecto!',
                text: 'Por favor, ingrese un correo electrónico válido.',
                confirmButtonColor: '#ff5722'
            });
            return;
        }

        if (contrasena === "") {
            Swal.fire({
                icon: 'warning',
                title: '¡Campo Requerido!',
                text: 'Por favor, ingrese la contraseña.',
                confirmButtonColor: '#ff5722'
            });
            return;
        }

        if (rol === "") {
            Swal.fire({
                icon: 'warning',
                title: '¡Campo Requerido!',
                text: 'Por favor, seleccione un rol para el usuario.',
                confirmButtonColor: '#ff5722'
            });
            return;
        }

        if (estado === "") {
            Swal.fire({
                icon: 'warning',
                title: '¡Campo Requerido!',
                text: 'Por favor, seleccione el estado del usuario.',
                confirmButtonColor: '#ff5722'
            });
            return;
        }


        $.ajax({
            url: '../../app/models/guardar_usuario.php',
            type: 'POST',
            data: {
                nombre: nombre,
                correo: correo,
                contrasena: contrasena,
                rol: rol,
                estado: estado
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Usuario Creado!',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then((result) => {
                        $('#nuevoUsuarioModal').modal('hide');
                        $("#formNuevoUsuario")[0].reset();
                        console.log('Datos guardados (simulado):', { nombre: nombre, correo: correo, rol: rol, estado: estado });
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

    $('#nuevoUsuarioModal').on('hidden.bs.modal', function (e) {
        $("#formNuevoUsuario")[0].reset();
    });

    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});