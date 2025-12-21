// site/script/login.js

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-login');
    const alertDiv = document.getElementById('alert-login');
    const submitButton = form.querySelector('button[type="submit"]');
    const spinner = submitButton.querySelector('.spinner-border');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        submitButton.disabled = true;
        spinner.classList.remove('d-none');
        alertDiv.classList.add('d-none');

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            // ▼▼▼ CORREÇÃO PRINCIPAL AQUI ▼▼▼
            // O caminho para a API deve incluir o diretório 'site'
            const response = await fetch('site/backend/auth_api.php', {
            // ▲▲▲ FIM DA CORREÇÃO ▲▲▲
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            });
            
            // Se a resposta não for JSON, o erro será pego aqui
            if (!response.headers.get("content-type")?.includes("application/json")) {
                throw new Error("O servidor não respondeu com JSON. Verifique o caminho da API.");
            }

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Erro de autenticação.');
            }

            // Salva os dados na chave 'user' para ser consistente com o dashboard
            sessionStorage.setItem('user', JSON.stringify(result.user));

            // Redireciona para o painel principal dentro da pasta 'site'
            window.location.href = '/dashboard';

        } catch (error) {
            alertDiv.textContent = error.message;
            alertDiv.classList.remove('d-none');
        } finally {
            submitButton.disabled = false;
            spinner.classList.add('d-none');
        }
    });
});