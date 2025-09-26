// site/script/dirigentes.js

document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('users-table-body');
    const userModalElement = document.getElementById('userModal');
    const modal = new bootstrap.Modal(userModalElement);
    const modalTitle = document.getElementById('userModalLabel');
    const mostrarInativosCheck = document.getElementById('mostrar-inativos-check');
    let editMode = false;
    let editId = null;

    /**
     * Carrega os usuários da API e os exibe na tabela.
     */
    const carregarUsuarios = async () => {
        const showInactive = mostrarInativosCheck.checked;
        const apiUrl = `${API_BASE_URL}/dirigentes_api.php?show_inactive=${showInactive}`;
        
        tableBody.innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>`;
        try {
            const response = await fetch(apiUrl);
            if (!response.ok) throw new Error('Erro na requisição');
            const users = await response.json();
            
            tableBody.innerHTML = '';
            if (users.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center">Nenhum usuário encontrado.</td></tr>`;
                return;
            }
            users.forEach(u => {
                const isInactive = u.status === 'inativo';
                const statusBadge = isInactive 
                    ? `<span class="badge bg-danger">Inativo</span>` 
                    : `<span class="badge bg-success">Ativo</span>`;
                const rowClass = isInactive ? 'table-secondary text-muted' : '';
                
                const actionButton = isInactive 
                    ? `<button class="btn btn-sm btn-success btn-reactivate" data-id="${u.id}" title="Reativar"><i class="fas fa-undo"></i></button>`
                    : `<button class="btn btn-sm btn-danger btn-delete" data-id="${u.id}" title="Desativar"><i class="fas fa-trash-alt"></i></button>`;

                const linkButton = u.cargo === 'dirigente' && u.token_acesso
                    ? `<button class="btn btn-sm btn-info btn-copy-link" data-token="${u.token_acesso}" title="Copiar Link Público"><i class="fas fa-link"></i></button>`
                    : '';

                const row = `
                    <tr class="${rowClass}">
                        <td>${u.nome}</td>
                        <td>${u.login}</td>
                        <td><span class="badge bg-secondary">${u.cargo}</span></td>
                        <td>${statusBadge}</td>
                        <td>
                            ${linkButton}
                            <button class="btn btn-sm btn-warning btn-edit" data-id="${u.id}" title="Editar" ${isInactive ? 'disabled' : ''}><i class="fas fa-edit"></i></button>
                            ${actionButton}
                        </td>
                    </tr>`;
                tableBody.innerHTML += row;
            });
            
            // Ativar tooltips para os novos botões (essencial para a dica de "Copiado!")
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        } catch (error) {
            console.error("Erro ao carregar usuários:", error);
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Erro ao carregar usuários. Verifique o console.</td></tr>`;
        }
    };

    /**
     * Prepara o modal para edição de um usuário específico.
     * @param {number} id - O ID do usuário a ser editado.
     */
    const prepararEdicao = async (id) => {
        try {
            const response = await fetch(`${API_BASE_URL}/dirigentes_api.php?id=${id}`);
            if (!response.ok) throw new Error('Usuário não encontrado');
            const user = await response.json();

            document.getElementById('user_nome').value = user.nome;
            document.getElementById('user_login').value = user.login;
            document.getElementById('user_cargo').value = user.cargo;
            document.getElementById('user_senha').value = '';

            const linkSection = document.getElementById('link-publico-section');
            if (user.cargo === 'dirigente') {
                const linkInput = document.getElementById('user_public_link');
                const publicUrl = `${window.location.origin}${window.location.pathname.replace(/\/pages\/.*$/, '')}/backend/vista_publica.php?token=${user.token_acesso}`;
                linkInput.value = publicUrl;
                linkSection.classList.remove('d-none');
            } else {
                linkSection.classList.add('d-none');
            }

            editMode = true;
            editId = id;
            modalTitle.textContent = 'Editar Usuário';
            
            modal.show();
        } catch (error) {
            alert('Não foi possível carregar os dados do usuário.');
        }
    };

    /**
     * Reseta o formulário do modal para o estado inicial.
     */
    const resetarModal = () => {
        document.getElementById('form-user').reset();
        document.getElementById('link-publico-section').classList.add('d-none');
        editMode = false;
        editId = null;
        modalTitle.textContent = 'Adicionar Usuário';
    };

    // --- EVENT LISTENERS ---

    // Listener para o botão de salvar no modal (Adicionar/Editar)
    document.getElementById('salvar-user-btn').addEventListener('click', async () => {
        const data = {
            nome: document.getElementById('user_nome').value,
            login: document.getElementById('user_login').value,
            cargo: document.getElementById('user_cargo').value,
            senha: document.getElementById('user_senha').value
        };
        if (!data.nome || !data.login || !data.cargo) { alert('Nome, Login e Cargo são obrigatórios.'); return; }
        if (!editMode && !data.senha) { alert('A senha é obrigatória ao criar um novo usuário.'); return; }
        const url = editMode ? `${API_BASE_URL}/dirigentes_api.php?id=${editId}` : `${API_BASE_URL}/dirigentes_api.php`;
        const method = editMode ? 'PUT' : 'POST';
        try {
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            if (!response.ok) { const errorData = await response.json(); throw new Error(errorData.message || 'Erro ao salvar usuário.'); }
            modal.hide();
            carregarUsuarios();
        } catch (error) {
            alert(`Falha ao salvar usuário: ${error.message}`);
        }
    });

    // Listener para o botão de regerar token no modal
    document.getElementById('regenerar-token-btn').addEventListener('click', async () => {
        if (!editId) return;
        if (!confirm('Tem certeza que deseja gerar um novo link de acesso? O link antigo deixará de funcionar permanentemente.')) return;
        
        try {
            const response = await fetch(`${API_BASE_URL}/dirigentes_api.php?id=${editId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'regenerate_token' })
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Falha ao gerar novo token.');

            const linkInput = document.getElementById('user_public_link');
            const newPublicUrl = `${window.location.origin}${window.location.pathname.replace(/\/pages\/.*$/, '')}/backend/vista_publica.php?token=${result.novoToken}`;
            linkInput.value = newPublicUrl;
            
            carregarUsuarios(); // Recarrega a tabela para atualizar o botão de copiar na linha
            alert('Novo link gerado com sucesso!');

        } catch (error) {
            alert(`Erro: ${error.message}`);
        }
    });
    
    // Listener para o checkbox de mostrar inativos
    mostrarInativosCheck.addEventListener('change', carregarUsuarios);

    // Listener para o corpo da tabela (delegação de eventos)
    tableBody.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return;
        const id = target.dataset.id;

        if (target.classList.contains('btn-delete')) {
            if (confirm('Deseja realmente DESATIVAR este usuário? Ele não poderá mais acessar o sistema.')) {
                try { await fetch(`${API_BASE_URL}/dirigentes_api.php?id=${id}`, { method: 'DELETE' }); carregarUsuarios(); } 
                catch (error) { alert('Não foi possível desativar o usuário.'); }
            }
        } 
        else if (target.classList.contains('btn-reactivate')) {
            if (confirm('Deseja REATIVAR este usuário?')) {
                try {
                    await fetch(`${API_BASE_URL}/dirigentes_api.php?id=${id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'reactivate' }) });
                    carregarUsuarios();
                } catch (error) { alert('Não foi possível reativar o usuário.'); }
            }
        } 
        else if (target.classList.contains('btn-edit')) {
            prepararEdicao(id);
        }
        else if (target.classList.contains('btn-copy-link')) {
            const token = target.dataset.token;
            const publicUrl = `${window.location.origin}${window.location.pathname.replace(/\/pages\/.*$/, '')}/backend/vista_publica.php?token=${token}`;
            navigator.clipboard.writeText(publicUrl).then(() => {
                const tooltip = bootstrap.Tooltip.getInstance(target);
                target.setAttribute('data-bs-original-title', 'Copiado!');
                tooltip.show();
                setTimeout(() => { target.setAttribute('data-bs-original-title', 'Copiar Link Público'); tooltip.hide(); }, 2000);
            }).catch(() => { alert('Falha ao copiar o link.'); });
        }
    });

    // Limpa o modal sempre que ele é fechado
    userModalElement.addEventListener('hidden.bs.modal', resetarModal);

    // --- INICIALIZAÇÃO ---
    carregarUsuarios();
});