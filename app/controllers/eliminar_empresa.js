$(document).ready(function() {
    $(document).on('click', '.btn-outline-danger', function() {
        var userId = $(this).closest('tr').find('td:first-child').text();

        Swal.fire({
            title: '¿Estás seguro?',
            text: "¡No podrás revertir esto!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: '¡Sí, eliminar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '../../app/models/eliminar_usuario.php',
                    type: 'POST',
                    data: { id: userId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire(
                                '¡Eliminado!',
                                response.message,
                                'success'
                            ).then((result) => {
                                $(this).closest('tr').remove();
                            });
                        } else {
                            Swal.fire(
                                '¡Error!',
                                response.message,
                                'error'
                            );
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire(
                            '¡Error de Conexión!',
                            'Ocurrió un error al intentar eliminar el usuario: ' + error,
                            'error'
                        );
                    }
                });
            }
        });
    });
});