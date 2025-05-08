$(document).ready(function() {

    $(document).on('click', '.btn-outline-primary', function() {
        var empresaId = $(this).closest('tr').find('td:first-child').text();
        var nombreEmpresa = $(this).closest('tr').find('td:nth-child(2)').text();
        var correoEmpresa = $(this).closest('tr').find('td:nth-child(3)').text();
        var sitioWebEmpresa = $(this).closest('tr').find('td:nth-child(4)').text();
        var estadoTextoEmpresa = $(this).closest('tr').find('td:nth-child(5)').text();
        var estadoEmpresa = (estadoTextoEmpresa === 'Activo') ? 'Activo' : (estadoTextoEmpresa === 'Pendiente') ? 'Pendiente' : 'Inactivo';

        $('#editar_id_empresa').val(empresaId);
        $('#editar_nombre_empresa').val(nombreEmpresa);
        $('#editar_correo_empresa').val(correoEmpresa);
        $('#editar_sitio_web_empresa').val(sitioWebEmpresa);
        $('#editar_estado_empresa').val(estadoEmpresa);

        $('#editarEmpresaModal').modal('show');
    });

    $("#formEditarEmpresa").submit(function(event) {
        event.preventDefault();

        var idEmpresa = $("#editar_id_empresa").val();
        var nombreEmpresa = $("#editar_nombre_empresa").val();
        var correoEmpresa = $("#editar_correo_empresa").val();
        var sitioWebEmpresa = $("#editar_sitio_web_empresa").val();
        var estadoEmpresa = $("#editar_estado_empresa").val();

        $.ajax({
            url: '../../app/models/editar_empresa.php',
            type: 'POST',
            data: {
                id: idEmpresa,
                nombreEmpresa: nombreEmpresa,
                correoEmpresa: correoEmpresa,
                sitioWebEmpresa: sitioWebEmpresa,
                estadoEmpresa: estadoEmpresa
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Empresa Actualizada!',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then((result) => {
                        $('#editarEmpresaModal').modal('hide');
                        console.log('Datos de empresa actualizados (simulado):', { id: idEmpresa, nombreEmpresa: nombreEmpresa, correoEmpresa: correoEmpresa, sitioWebEmpresa: sitioWebEmpresa, estadoEmpresa: estadoEmpresa });
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

    $('#editarEmpresaModal').on('hidden.bs.modal', function (e) {
        $("#formEditarEmpresa")[0].reset();
    });

});