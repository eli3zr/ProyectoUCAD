$(function () {
        $("form").eq(1).on("submit", function (e) {
            e.preventDefault();

            let datos = {
                sitioWeb: $("#sitioWeb").val().trim(),
                linkedinPerfil: $("#linkedinPerfil").val().trim()
            };

            $.ajax({
                url: '../../app/models/actualizar_enlaces_empresa.php',
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
                        confirmButtonText: 'Perfecto'
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.error,
                        icon: 'error',
                        confirmButtonText: 'Ok'
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

