$(function () {
    $("#btnRegistrarse").click(function () {
        enviar_registro_empresa();
    });
});

function enviar_registro_empresa() {
    // Captura de los datos del formulario de empresas
    let datos = {
        nombre: $("#nombre").val().trim(),
        telefono: $("#telefono").val().trim(),
        email: $("#email").val().trim(),
        categoria: $("#categoria").val(),
        pais: $("#pais").val(),
        departamento: $("#departamento").val(),
        clave: $("#clave").val(),
        repetirClave: $("#repetir-clave").val(),
        terminos: $("#terminos").prop('checked'),
        notificaciones: $("#notificaciones").prop('checked')
    };

    // Validación básica en el cliente
    let errores = [];

    if (datos.nombre === '') {
        errores.push('El nombre de la empresa es requerido.');
    }
    if (datos.telefono === '') {
        errores.push('El teléfono es requerido.');
    }
    if (datos.email === '') {
        errores.push('El correo electrónico es requerido.');
    }
    if (datos.categoria === '') {
        errores.push('La categoría es requerida.');
    }
    if (datos.pais === '') {
        errores.push('El país es requerido.');
    }
    if (datos.departamento === '') {
        errores.push('El departamento es requerido.');
    }
    if (datos.clave === '') {
        errores.push('La clave es requerida.');
    }
    if (datos.repetirClave === '') {
        errores.push('Debes repetir la clave.');
    }
    if (!datos.terminos) {
        errores.push('Debes aceptar los términos y condiciones.');
    }

    if (datos.email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(datos.email)) {
        errores.push('El formato del correo electrónico no es válido.');
    }

    if (datos.clave !== datos.repetirClave) {
        errores.push('Las claves no coinciden.');
    }

    if (errores.length > 0) {
        Swal.fire({
            icon: 'error',
            title: 'Error en el Registro',
            html: errores.join('<br>')
        });
    } else {
        // Si no hay errores, proceder con el envío AJAX
        let formData = new FormData();
        formData.append('nombre', datos.nombre);
        formData.append('telefono', datos.telefono);
        formData.append('email', datos.email);
        formData.append('categoria', datos.categoria);
        formData.append('pais', datos.pais);
        formData.append('departamento', datos.departamento);
        formData.append('clave', datos.clave);
        formData.append('repetir-clave', datos.repetirClave);
        formData.append('terminos', datos.terminos);
        formData.append('notificaciones', datos.notificaciones);

        $.ajax({
            url: '../models/registrar_empresa.php', // Ajusta la ruta a tu archivo PHP para empresas
            type: 'POST',
            dataType: 'json',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function () {
                Swal.showLoading();
            },
            complete: function () {
                Swal.close();
            }
        })
        .done(function (response) {
            if (response.success) {
                $("#registroEmpresaForm").trigger('reset');
                Swal.fire({
                    title: "Éxito",
                    text: response.message,
                    icon: "success"
                });
            } else {
                Swal.fire({
                    title: "Atención",
                    text: response.message,
                    icon: "info"
                });
            }
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
            console.log("Error en la petición AJAX:", textStatus, errorThrown);
            Swal.fire({
                title: "Error",
                text: "Hubo un problema al registrar la empresa.",
                icon: "error"
            });
        });
    }
}