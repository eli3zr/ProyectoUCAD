$(document).ready(function() {

    $(document).on('click', '.btn-outline-primary', function() {
        var userId = $(this).closest('tr').find('td:first-child').text(); 
        var nombre = $(this).closest('tr').find('td:nth-child(2)').text(); 
        var correo = $(this).closest('tr').find('td:nth-child(3)').text();
        var rol = $(this).closest('tr').find('td:nth-child(5)').text(); 
        var estadoTexto = $(this).closest('tr').find('td:nth-child(6)').text(); 
        var estado = (estadoTexto === 'Activo') ? 'Activo' : 'Inactivo';


        $('#editar_id').val(userId);
        $('#editar_nombre').val(nombre);
        $('#editar_correo').val(correo);
        $('#editar_rol').val(rol);
        $('#editar_estado').val(estado);

        $('#editarUsuarioModal').modal('show');
    });


    $("#formEditarUsuario").submit(function(event) {
        event.preventDefault();

        var id = $("#editar_id").val();
        var nombre = $("#editar_nombre").val();
        var correo = $("#editar_correo").val();
        var contrasena = $("#editar_contrasena").val(); 
        var rol = $("#editar_rol").val();
        var estado = $("#editar_estado").val();

        $.ajax({
            url: '../../app/models/editar_usuario.php', 
            type: 'POST',
            data: {
                id: id,
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
                        title: '¡Usuario Actualizado!',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then((result) => {
                        $('#editarUsuarioModal').modal('hide');
                        console.log('Usuario actualizado (simulado):', { id: id, nombre: nombre, correo: correo, rol: rol, estado: estado });
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '¡Error al Editar!',
                        text: response.message
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: '¡Error de Conexión!',
                    text: 'Ocurrió un error al enviar los datos de edición: ' + error
                });
            }
        });
    });
    $('#nuevoUsuarioModal').on('hidden.bs.modal', function (e) {
        $("#formNuevoUsuario")[0].reset();
    });

});