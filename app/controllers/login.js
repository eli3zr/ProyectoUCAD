$(function () {
    $("form").submit(function (event) {
        event.preventDefault();

        const email = $("#email").val();
        const password = $("#password").val();

        // Simulación para estudiantes
        if (email === "estudiante@ejemplo.com" && password === "12345") {
            window.location.href = "dashboard_estudiante.html";
            return;
        }

        // Simulación para empresas
        if (email === "empresa@ejemplo.com" && password === "contrasena") {
            window.location.href = "dashboard_empresa.html";
            return;
        }

        // Simulación para administradores
        if (email === "admin@ejemplo.com" && password === "admin123") {
            window.location.href = "panel_admin.html";
            return;
        }

        alert("Correo electrónico o contraseña incorrectos.");
    });
});