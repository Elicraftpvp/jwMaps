// site/script/login.js

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-login');
    const alertDiv = document.getElementById('alert-login');
    const submitButton = form.querySelector('button[type="submit"]');
    const spinner = submitButton.querySelector('.spinner-border');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Desativa o botão e mostra o spinner
        submitButton.disabled = true;
        spinner.classList.remove('d-none');
        alertDiv.classList.add('d-none');

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch('site/backend/auth_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Erro de autenticação.');
            }

            // Salva dados do usuário na sessionStorage
            sessionStorage.setItem('usuarioLogado', JSON.stringify(result.user));

            // Redireciona para o painel principal
            window.location.href = 'site/index.html';

        } catch (error) {
            alertDiv.textContent = error.message;
            alertDiv.classList.remove('d-none');
        } finally {
            // Reativa o botão e esconde o spinner
            submitButton.disabled = false;
            spinner.classList.add('d-none');
        }
    });
});