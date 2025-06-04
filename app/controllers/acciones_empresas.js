// Este es el contenido de tu archivo acciones_empresas.js
$(document).ready(function() {
    // --- Lógica para Cambiar Contraseña ---
    $('#formCambiarContrasena').submit(function(e) {
        e.preventDefault(); 

        const form = $(this);
        const messageDiv = $('#changePasswordMessage');
        if (messageDiv.length) { 
            messageDiv.empty().removeClass('alert alert-success alert-danger'); 
        }

        const currentPassword = $('#currentPassword').val(); 
        const newPassword = $('#newPassword').val();       
        const confirmNewPassword = $('#confirmNewPassword').val(); 

        // Validaciones del lado del cliente
        if (newPassword !== confirmNewPassword) {
            if (messageDiv.length) {
                messageDiv.addClass('alert alert-danger').text('Las nuevas contraseñas no coinciden.');
            } else { 
                Swal.fire({ icon: 'error', title: 'Error de validación', text: 'Las nuevas contraseñas no coinciden.' });
            }
            return; 
        }
        if (newPassword.length < 8) {
            if (messageDiv.length) {
                messageDiv.addClass('alert alert-danger').text('La nueva contraseña debe tener al menos 8 caracteres.');
            } else { 
                Swal.fire({ icon: 'error', title: 'Contraseña débil', text: 'La nueva contraseña debe tener al menos 8 caracteres.' });
            }
            return; 
        }

        $.ajax({
            url: '../models/cambiar_contrasena.php', 
            type: 'POST',
            data: form.serialize(), 
            dataType: 'json', 
            success: function(response) {
                if (response.success) {
                    if (messageDiv.length) {
                        messageDiv.addClass('alert alert-success').text(response.msg);
                    }
                    form[0].reset(); 
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: response.msg,
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        $('#modalCambiarContrasena').modal('hide');
                        if (messageDiv.length) {
                            messageDiv.empty().removeClass('alert alert-success alert-danger'); 
                        }
                    });
                } else {
                    if (messageDiv.length) {
                        messageDiv.addClass('alert alert-danger').text(response.msg + (response.error ? ' (' + response.error + ')' : ''));
                    }
                    Swal.fire({
                        icon: 'error',
                        title: '¡Error!',
                        text: response.msg + (response.error ? ' (Detalle: ' + response.error + ')' : ''), 
                        confirmButtonText: 'Entendido'
                    });
                }
            },
            error: function(xhr, status, error) {
                if (messageDiv.length) {
                    messageDiv.addClass('alert alert-danger').text('Error de comunicación con el servidor. Intente de nuevo.');
                }
                Swal.fire({
                    icon: 'error',
                    title: '¡Error de Conexión!',
                    text: 'No se pudo comunicar con el servidor. Por favor, intente de nuevo más tarde.',
                    confirmButtonText: 'Entendido'
                });
            }
        });
    });

    // --- Lógica para Eliminar Cuenta ---
    $('#deleteAccountForm').submit(function(e) {
        e.preventDefault(); 

        const form = $(this);
        const messageDiv = $('#deleteAccountMessage'); 
        messageDiv.empty().removeClass('alert alert-success alert-danger'); 

        Swal.fire({
            title: '¿Está realmente seguro?',
            text: "Esta acción es irreversible y eliminará todos sus datos. ¡No podrá revertirlo!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar mi cuenta',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '../models/eliminar_perfil_empresa.php', 
                    type: 'POST',
                    data: form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            messageDiv.addClass('alert alert-success').text(response.msg);
                            form[0].reset();
                            Swal.fire({
                                icon: 'success',
                                title: '¡Cuenta Eliminada!',
                                text: response.msg,
                                showConfirmButton: false,
                                timer: 2000
                            }).then(() => {
                                window.location.href = '../../index.html'; 
                            });
                        } else {
                            messageDiv.addClass('alert alert-danger').text(response.msg + (response.error ? ' (' + response.error + ')' : ''));
                            Swal.fire({
                                icon: 'error',
                                title: '¡Error al Eliminar!',
                                text: response.msg + (response.error ? ' (Detalle: ' + response.error + ')' : ''),
                                confirmButtonText: 'Entendido'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        messageDiv.addClass('alert alert-danger').text('Error de comunicación con el servidor. Intente de nuevo.');
                        Swal.fire({
                            icon: 'error',
                            title: '¡Error de Conexión!',
                            text: 'No se pudo comunicar con el servidor. Por favor, intente de nuevo más tarde.',
                            confirmButtonText: 'Entendido'
                        });
                    }
                });
            }
        });
    });
});