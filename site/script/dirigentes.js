// site/script/dirigentes.js
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('users-table-body');
    const userModalElement = document.getElementById('userModal');
    const modal = new bootstrap.Modal(userModalElement);
    const modalTitle = document.getElementById('userModalLabel');
    const mostrarInativosCheck = document.getElementById('mostrar-inativos-check');
    let editMode = false;
    let editId = null;

    // CORREÇÃO 3: Função para verificar se existem usuários inativos e desabilitar o checkbox se não houver.
    const verificarExistenciaDeInativos = async () => {
        try {
            // Faz uma chamada para buscar apenas os inativos
            const response = await fetch(`${API_BASE_URL}/dirigentes_api.php?show_inactive=true`);
            const inativos = await response.json();

            const label = document.querySelector('label[for="mostrar-inativos-check"]');

            if (inativos.length > 0) {
                mostrarInativosCheck.disabled = false;
                if(label) label.classList.remove('text-muted');
            } else {
                mostrarInativosCheck.disabled = true;
                if(label) label.classList.add('text-muted');
                // Garante que se não há inativos, a caixa seja desmarcada e os ativos sejam exibidos.
                if (mostrarInativosCheck.checked) {
                    mostrarInativosCheck.checked = false;
                    carregarUsuarios(); 
                }
            }
        } catch (error) {
            console.error("Erro ao verificar usuários inativos.", error);
            mostrarInativosCheck.disabled = true; // Desabilita em caso de erro
        }
    };

    // CORREÇÃO 2: Simplificada a lógica de carregamento para mostrar ATIVOS OU INATIVOS, mas não ambos.
    const carregarUsuarios = async () => {
        const showInactive = mostrarInativosCheck.checked;
        tableBody.innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner-border"></div></td></tr>`;
        try {
            const response = await fetch(`${API_BASE_URL}/dirigentes_api.php?show_inactive=${showInactive}`);
            const users = await response.json();
            tableBody.innerHTML = '';
            if (users.length === 0) { 
                const message = showInactive ? "Nenhum usuário inativo." : "Nenhum usuário ativo.";
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center">${message}</td></tr>`; 
                return; 
            }
            
            users.forEach(u => {
                const isInactive = u.status === 'inativo';
                const statusBadge = isInactive ? `<span class="badge bg-danger">Inativo</span>` : `<span class="badge bg-success">Ativo</span>`;
                const rowClass = isInactive ? 'table-secondary text-muted' : '';
                const actionButton = isInactive 
                    ? `<button class="btn btn-sm btn-success btn-reactivate" data-id="${u.id}" title="Reativar"><i class="fas fa-undo"></i></button>`
                    : `<button class="btn btn-sm btn-danger btn-delete" data-id="${u.id}" title="Desativar"><i class="fas fa-trash-alt"></i></button>`;
                const linkButton = u.cargo === 'dirigente' && u.token_acesso
                    ? `<button class="btn btn-sm btn-info btn-copy-link" data-token="${u.token_acesso}" title="Copiar Link Público"><i class="fas fa-link"></i></button>`
                    : '';

                const row = `<tr class="${rowClass}">
                        <td>${u.nome}</td>
                        <td>${u.login}</td>
                        <td><span class="badge bg-secondary text-capitalize">${u.cargo}</span></td>
                        <td>${statusBadge}</td>
                        <td>
                            ${linkButton}
                            <button class="btn btn-sm btn-warning btn-edit" data-id="${u.id}" title="Editar" ${isInactive ? 'disabled' : ''}><i class="fas fa-edit"></i></button>
                            ${actionButton}
                        </td></tr>`;
                tableBody.innerHTML += row;
            });
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
        } catch (error) { tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Erro ao carregar usuários.</td></tr>`; }
    };

    const prepararEdicao = async (id) => {
        try {
            const response = await fetch(`${API_BASE_URL}/dirigentes_api.php?id=${id}`);
            const user = await response.json();
            document.getElementById('user_nome').value = user.nome;
            document.getElementById("user_login").value = user.login;
            document.getElementById("user_cargo").value = user.cargo;
            document.getElementById("user_senha").value = '';
            document.getElementById("user_telefone").value = user.telefone || '';
            document.getElementById("user_email_contato").value = user.email_contato || '';

            const linkSection = document.getElementById('link-publico-section');
            if (user.cargo === 'dirigente') {
                const linkInput = document.getElementById('user_public_link');
                const publicUrl = `${window.location.origin}${window.location.pathname.replace(/\/pages\/.*$/, '')}/backend/vista_publica.php?token=${user.token_acesso}`;
                linkInput.value = publicUrl;
                linkSection.classList.remove('d-none');
            } else { linkSection.classList.add('d-none'); }
            editMode = true;
            editId = id;
            modalTitle.textContent = 'Editar Usuário';
            modal.show();
        } catch (error) { alert('Não foi possível carregar os dados do usuário.'); }
    };

    const resetarModal = () => {
        document.getElementById('form-user').reset();
        document.getElementById('link-publico-section').classList.add('d-none');
        editMode = false; editId = null;
        modalTitle.textContent = 'Adicionar Usuário';
    };

    document.getElementById('salvar-user-btn').addEventListener('click', async () => {
        const data = {
            nome: document.getElementById('user_nome').value,
            login: document.getElementById('user_login').value,
            cargo: document.getElementById('user_cargo').value,
            senha: document.getElementById("user_senha").value,
            telefone: document.getElementById("user_telefone").value,
            email_contato: document.getElementById("user_email_contato").value
        };
        if (!data.nome || !data.login || !data.cargo) { alert('Nome, Login e Cargo são obrigatórios.'); return; }
        if (!editMode && !data.senha) { alert('A senha é obrigatória ao criar um novo usuário.'); return; }
        
        const url = editMode ? `${API_BASE_URL}/dirigentes_api.php?id=${editId}` : `${API_BASE_URL}/dirigentes_api.php`;
        const method = 'POST'; 
        
        try {
            if (editMode) {
                data.action = 'update'; 
            }
            
            const response = await fetch(url, { 
                method: method, 
                headers: { 'Content-Type': 'application/json' }, 
                body: JSON.stringify(data) 
            });
            
            if (!response.ok) { const err = await response.json(); throw new Error(err.message); }
            modal.hide();
            carregarUsuarios();
        } catch (error) { alert(`Falha ao salvar: ${error.message}`); }
    });

    document.getElementById('regenerar-token-btn').addEventListener('click', async () => {
        if (!editId || !confirm('O link antigo deixará de funcionar. Deseja continuar?')) return;
        try {
            const response = await fetch(`${API_BASE_URL}/dirigentes_api.php?id=${editId}`, { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/json' }, 
                body: JSON.stringify({ action: 'regenerate_token' }) 
            });
            
            const result = await response.json();
            if (!response.ok) throw new Error(result.message);
            const newUrl = `${window.location.origin}${window.location.pathname.replace(/\/pages\/.*$/, '')}/backend/vista_publica.php?token=${result.novoToken}`;
            document.getElementById('user_public_link').value = newUrl;
            carregarUsuarios();
            alert('Novo link gerado!');
        } catch (error) { alert(`Erro: ${error.message}`); }
    });
    
    mostrarInativosCheck.addEventListener('change', carregarUsuarios);

    tableBody.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return;
        const id = target.dataset.id;

        if (target.classList.contains('btn-delete')) {
            if (confirm('Desativar este usuário?')) {
                try { 
                    await fetch(`${API_BASE_URL}/dirigentes_api.php?id=${id}`, { 
                        method: 'POST', 
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete_user' })
                    }); 
                    carregarUsuarios(); 
                    verificarExistenciaDeInativos(); // Re-verifica após desativar
                } 
                catch (error) { alert('Não foi possível desativar.'); }
            }
        } else if (target.classList.contains('btn-reactivate')) {
            // CORREÇÃO 1: Botão de reativar agora envia um POST direto, sem abrir o modal.
            if (confirm('Reativar este usuário?')) {
                 try {
                    await fetch(`${API_BASE_URL}/dirigentes_api.php?id=${id}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        // OBS: Confirme se 'reactivate' é a ação esperada pela sua API
                        body: JSON.stringify({ action: 'reactivate' }) 
                    });
                    // Mostra a lista de inativos após reativar um (para que ele suma da lista de inativos)
                    if(!mostrarInativosCheck.checked) mostrarInativosCheck.checked = true;
                    carregarUsuarios();
                    verificarExistenciaDeInativos(); // Re-verifica após reativar
                } catch (error) {
                    alert('Não foi possível reativar o usuário.');
                }
            }
        } else if (target.classList.contains('btn-edit')) {
            prepararEdicao(id);
        } else if (target.classList.contains('btn-copy-link')) {
            const token = target.dataset.token;
            const url = `${window.location.origin}${window.location.pathname.replace(/\/pages\/.*$/, '')}/backend/vista_publica.php?token=${token}`;
            navigator.clipboard.writeText(url).then(() => {
                const tooltip = bootstrap.Tooltip.getInstance(target);
                target.setAttribute('data-bs-original-title', 'Copiado!');
                tooltip.show();
                setTimeout(() => { target.setAttribute('data-bs-original-title', 'Copiar Link Público'); tooltip.hide(); }, 2000);
            });
        }
    });

    userModalElement.addEventListener('hidden.bs.modal', resetarModal);
    
    // Carregamento inicial
    carregarUsuarios();
    verificarExistenciaDeInativos();
});