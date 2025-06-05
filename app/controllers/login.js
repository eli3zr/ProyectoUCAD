$(function () {
    $("form").submit(function (event) {
        event.preventDefault(); // Evita el envío del formulario tradicional

        const email = $("#email").val();
        const password = $("#password").val();

        $.ajax({
            url: '../../app/models/login.php', 
            type: 'POST',
            dataType: 'json', 
            data: {
                email: email,
                password: password
            },
            success: function (response) {
                if (response.success) {
                    alert(response.msg); 

                    // --- CAMBIO CLAVE AQUÍ: Usar response.nombre_rol en lugar de response.tipo_usuario ---
                    // Los nombres de roles 'estudiante', 'empresa', 'administrador'
                    // deben coincidir exactamente con los de tu tabla 'rol'.
                    if (response.nombre_rol === 'estudiante') { 
                        window.location.href = 'dashboard_estudiante.html'; // Ajusta esta ruta si es necesario
                    } else if (response.nombre_rol === 'empresa') { 
                        window.location.href = 'dashboard_empresa.html'; // Ajusta esta ruta si es necesario
                    } else if (response.nombre_rol === 'administrador') { 
                        window.location.href = 'panel_admin.html'; // Ajusta esta ruta si es necesario
                    } else {
                        // Este alert debería dejar de aparecer si los roles son correctos y se manejan arriba
                        alert("Tipo de usuario no reconocido. Redirigiendo a página de inicio.");
                        window.location.href = 'index.html'; 
                    }
                } else {
                    alert(response.error);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Error en la petición AJAX:", textStatus, errorThrown);
                console.error("Respuesta del servidor:", jqXHR.responseText);
                alert("Ocurrió un error al intentar iniciar sesión. Por favor, intenta de nuevo.");
            }
        });
    });

    // Este es el script para mostrar/ocultar contraseña, que es parte de tu HTML original
    // y no interfiere con la lógica AJAX. Lo mantendremos aquí en login.js.
    const passwordInput = document.getElementById('password');
    const togglePasswordButton = document.getElementById('togglePassword');

    if (togglePasswordButton && passwordInput) { // Asegurarse de que los elementos existen
        togglePasswordButton.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }
});