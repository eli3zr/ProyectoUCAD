$(function () {
        $("#formInformacionEmpresa").on("submit", function (e) {
            e.preventDefault();

            let datos = {
                nombreEmpresa: $("#nombreEmpresa").val().trim(),
                descripcionEmpresa: $("#descripcionEmpresa").val().trim(),
                emailContacto: $("#emailContacto").val().trim(),
                telefonoContacto: $("#telefonoContacto").val().trim(),
                ubicacionEmpresa: $("#ubicacionEmpresa").val().trim()
            };

            $.ajax({
                url: '../../app/models/actualizar_informacion_empresa.php',
                type: 'POST',
                dataType: 'json',
                data: datos,
                beforeSend: function () {
                    Swal.showLoading();
                }
            })
            .done(function (response) {
                Swal.close();

                if (response.success) {
                    Swal.fire({
                        title: 'Éxito',
                        text: response.msg,
                        icon: 'success',
                        confirmButtonText: '¡Genial!'
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

