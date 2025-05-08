$(document).ready(function() {
    // Mostrar la modal al hacer clic en el botón "Nueva Empresa"
    $("#nuevaEmpresaButton").click(function() {
        $('#nuevaEmpresaModal').modal('show');
    });

    // Lógica para la validación y el envío del formulario
    $("#formNuevaEmpresa").submit(function(event) {
        event.preventDefault();

        var nombreEmpresa = $("#nombreEmpresa").val().trim();
        var correoElectronico = $("#correoElectronico").val().trim();
        var sitioWeb = $("#sitioWeb").val().trim();
        var estado = $("#estado").val();

        if (nombreEmpresa === "") {
            Swal.fire({
                icon: 'warning',
                title: '¡Campo Requerido!',
                text: 'Por favor, ingrese el nombre de la empresa.',
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

        if (sitioWeb === "") {
            Swal.fire({
                icon: 'warning',
                title: '¡Campo Requerido!',
                text: 'Por favor, ingrese el sitio web.',
                confirmButtonColor: '#F0C11A'
            });
            return;
        } else if (!isValidUrl(sitioWeb)) {
            Swal.fire({
                icon: 'warning',
                title: '¡Formato Incorrecto!',
                text: 'Por favor, ingrese una URL válida.',
                confirmButtonColor: '#F0C11A'
            });
            return;
        }

        if (estado === "") {
            Swal.fire({
                icon: 'warning',
                title: '¡Campo Requerido!',
                text: 'Por favor, seleccione el estado de la empresa.',
                confirmButtonColor: '#F0C11A'
            });
            return;
        }

        $.ajax({
            url: '../../app/models/guardar_empresa.php',
            type: 'POST',
            data: {
                nombreEmpresa: nombreEmpresa,
                correoElectronico: correoElectronico,
                sitioWeb: sitioWeb,
                estado: estado
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Empresa Creada!',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then((result) => {
                        $('#nuevaEmpresaModal').modal('hide');
                        $("#formNuevaEmpresa")[0].reset();
                        console.log('Datos de empresa guardados (simulado):', { nombreEmpresa: nombreEmpresa, correoElectronico: correoElectronico, sitioWeb: sitioWeb, estado: estado });
                        // Aquí podrías recargar la tabla de empresas o actualizar la vista de alguna manera
                        // location.reload();
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

    $('#nuevaEmpresaModal').on('hidden.bs.modal', function (e) {
        $("#formNuevaEmpresa")[0].reset();
    });

    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch (_) {
            return false;
        }
    }
});