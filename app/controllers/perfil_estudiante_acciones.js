// public/controllers/perfil_estudiante_acciones.js

$(function() {
    console.log('Script perfil_estudiante_acciones.js cargado y ejecutándose.');

    // --- Lógica para CAMBIAR CONTRASEÑA ---
    // Este evento se dispara cuando se envía el formulario dentro del modal.
    $("#formCambiarContrasena").on("submit", function(e) {
        e.preventDefault(); // Previene el envío tradicional del formulario.
        console.log('Formulario de cambio de contraseña enviado.');

        const currentPassword = $("#currentPassword").val();
        const newPassword = $("#newPassword").val();
        const confirmNewPassword = $("#confirmNewPassword").val();

        // Validaciones de la nueva contraseña en el cliente
        if (newPassword !== confirmNewPassword) {
            Swal.fire({
                title: 'Error',
                text: 'Las nuevas contraseñas no coinciden.',
                icon: 'error',
                confirmButtonText: 'Ok'
            });
            console.log('Error: Las nuevas contraseñas no coinciden.');
            return;
        }

        if (newPassword.length < 8) { // Ejemplo: mínimo 8 caracteres
            Swal.fire({
                title: 'Error',
                text: 'La nueva contraseña debe tener al menos 8 caracteres.',
                icon: 'error',
                confirmButtonText: 'Ok'
            });
            console.log('Error: La nueva contraseña es demasiado corta.');
            return;
        }

        // Confirmación con SweetAlert2 antes de enviar la petición AJAX
        Swal.fire({
            title: '¿Estás seguro?',
            text: "¿Deseas cambiar tu contraseña?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#112852',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, cambiar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                console.log('Usuario confirmó el cambio de contraseña. Enviando AJAX...');
                // Petición AJAX para cambiar la contraseña
                $.ajax({
                    url: '../../app/models/cambiar_contrasena.php', // Ruta al script PHP
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        currentPassword: currentPassword,
                        newPassword: newPassword
                    },
                    beforeSend: function() {
                        Swal.showLoading(); // Muestra el ícono de carga de SweetAlert2
                    }
                })
                .done(function(response) {
                    Swal.close(); // Cierra el ícono de carga
                    console.log('Respuesta del servidor (cambiar contraseña):', response);
                    if (response.success) {
                        Swal.fire({
                            title: 'Éxito',
                            text: response.msg,
                            icon: 'success',
                            confirmButtonText: 'Perfecto'
                        }).then(() => {
                            $('#modalCambiarContrasena').modal('hide'); // Oculta el modal
                            $('#formCambiarContrasena')[0].reset(); // Limpia el formulario
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.error || response.msg || 'Ha ocurrido un error desconocido.',
                            icon: 'error',
                            confirmButtonText: 'Ok'
                        });
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    Swal.close();
                    console.error('Error AJAX (cambiar contraseña):', textStatus, errorThrown, jqXHR);
                    Swal.fire({
                        title: 'Error',
                        text: 'No se pudo conectar con el servidor para cambiar la contraseña. ' + textStatus + ': ' + errorThrown,
                        icon: 'error'
                    });
                });
            }
        });
    });

    // --- Lógica para ELIMINAR CUENTA ---
    // Este evento se dispara cuando se envía el formulario del modal de eliminar cuenta.
    $("#formEliminarCuenta").on("submit", function(e) {
        e.preventDefault(); // Previene el envío tradicional del formulario.
        console.log('Formulario de eliminar cuenta enviado.');

        const confirmDeletePassword = $("#confirmDeletePassword").val();

        if (!confirmDeletePassword) {
            Swal.fire({
                title: 'Advertencia',
                text: 'Por favor, introduce tu contraseña para confirmar la eliminación.',
                icon: 'warning',
                confirmButtonText: 'Ok'
            });
            return;
        }

        Swal.fire({
            title: '¡ADVERTENCIA!',
            text: "Esta acción eliminará tu cuenta de forma permanente. ¿Estás absolutamente seguro de que deseas continuar?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar mi cuenta',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                console.log('Usuario confirmó la eliminación de cuenta. Enviando AJAX...');
                $.ajax({
                    url: '../../app/models/eliminar_cuenta.php', // Ruta al script PHP
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        confirmDeletePassword: confirmDeletePassword
                    },
                    beforeSend: function() {
                        Swal.showLoading();
                    }
                })
                .done(function(response) {
                    Swal.close();
                    console.log('Respuesta del servidor (eliminar cuenta):', response);
                    if (response.success) {
                        Swal.fire({
                            title: '¡Cuenta Eliminada!',
                            text: response.msg,
                            icon: 'success',
                            confirmButtonText: 'Ok'
                        }).then(() => {
                            // Redirigir al usuario, por ejemplo, a la página de login
                            if (response.redirect) {
                                window.location.href = response.redirect;
                            } else {
                                // Fallback por si no viene la URL de redirección
                                window.location.href = '../auth/login.php'; 
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.error || response.msg || 'Ha ocurrido un error desconocido al eliminar la cuenta.',
                            icon: 'error',
                            confirmButtonText: 'Ok'
                        });
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    Swal.close();
                    console.error('Error AJAX (eliminar cuenta):', textStatus, errorThrown, jqXHR);
                    Swal.fire({
                        title: 'Error',
                        text: 'No se pudo conectar con el servidor para eliminar la cuenta. ' + textStatus + ': ' + errorThrown,
                        icon: 'error'
                    });
                });
            }
        });
    });

    // --- Lógica para ACTUALIZAR CV (la que acabamos de revisar) ---
    $("#formActualizarCV").on("submit", function(e) {
        e.preventDefault();
        console.log('Formulario de actualización de CV enviado.');

        const cvInput = $("#cvNuevo")[0];
        if (!cvInput.files || cvInput.files.length === 0) {
            Swal.fire({ title: 'Advertencia', text: 'Por favor, selecciona un archivo CV para subir.', icon: 'warning', confirmButtonText: 'Ok' });
            return;
        }

        const file = cvInput.files[0];
        const maxFileSize = 2 * 1024 * 1024;
        if (file.size > maxFileSize) {
            Swal.fire({ title: 'Error', text: 'El archivo es demasiado grande. El tamaño máximo permitido para el CV es 2MB.', icon: 'error', confirmButtonText: 'Ok' });
            return;
        }

        const allowedMimeTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!allowedMimeTypes.includes(file.type)) {
            Swal.fire({ title: 'Error', text: 'Formato de archivo no permitido. Formatos de archivo permitidos: PDF, DOC, DOCX.', icon: 'error', confirmButtonText: 'Ok' });
            return;
        }

        const formData = new FormData(this);

        Swal.fire({
            title: '¿Estás seguro?',
            text: "¿Deseas actualizar tu CV?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#112852',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, actualizar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                console.log('Usuario confirmó la actualización de CV. Enviando AJAX...');
                $.ajax({
                    url: '../../app/models/actualizar_cv_estudiante.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    beforeSend: function() {
                        Swal.showLoading();
                    }
                })
                .done(function(response) {
                    Swal.close();
                    console.log('Respuesta del servidor (actualizar CV):', response);
                    if (response.success) {
                        Swal.fire({
                            title: 'Éxito',
                            text: response.msg,
                            icon: 'success',
                            confirmButtonText: 'Perfecto'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.error || response.msg || 'Ha ocurrido un error desconocido al actualizar el CV.',
                            icon: 'error',
                            confirmButtonText: 'Ok'
                        });
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    Swal.close();
                    console.error('Error AJAX (actualizar CV):', textStatus, errorThrown, jqXHR);
                    Swal.fire({
                        title: 'Error',
                        text: 'No se pudo conectar con el servidor para actualizar el CV. ' + textStatus + ': ' + errorThrown,
                        icon: 'error'
                    });
                });
            }
        });
    });
});