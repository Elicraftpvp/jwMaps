// site/script/dirigentes.js
document.addEventListener('DOMContentLoaded', () => {
    // Referências DOM Principais
    const tableBody = document.getElementById('users-table-body');
    const userModalElement = document.getElementById('userModal');
    const userModal = new bootstrap.Modal(userModalElement);
    const modalTitle = document.getElementById('userModalLabel');
    const mostrarInativosCheck = document.getElementById('mostrar-inativos-check');
    
    // Referências DOM Modais de Feedback
    const feedbackModalElement = document.getElementById('feedbackModal');
    const feedbackModal = new bootstrap.Modal(feedbackModalElement);
    const feedbackTitle = document.getElementById('feedbackModalTitle');
    const feedbackBody = document.getElementById('feedbackModalBody');
    
    const confirmacaoModalElement = document.getElementById('confirmacaoModal');
    const confirmacaoModal = new bootstrap.Modal(confirmacaoModalElement);
    const confirmacaoTitle = document.getElementById('confirmacaoModalTitle');
    const confirmacaoBody = document.getElementById('confirmacaoModalBody');
    // IMPORTANTE: Pegamos a referência uma vez e não destruímos o elemento depois
    const btnConfirmarAcao = document.getElementById('btnConfirmarAcao');

    let editMode = false;
    let editId = null;

    // --- FUNÇÕES AUXILIARES DE MODAL ---

    const mostrarFeedback = (titulo, mensagem, tipo = 'primary') => {
        feedbackTitle.textContent = titulo;
        feedbackBody.innerHTML = mensagem;
        const header = feedbackModalElement.querySelector('.modal-header');
        header.className = 'modal-header'; // Reseta classes
        header.classList.add(`bg-${tipo}`, 'text-white');
        
        const btnClose = header.querySelector('.btn-close');
        if (tipo !== 'light' && tipo !== 'warning') {
            btnClose.classList.add('btn-close-white');
        } else {
            btnClose.classList.remove('btn-close-white');
        }
        
        feedbackModal.show();
    };

    const mostrarConfirmacao = (titulo, mensagem, callbackConfirmacao) => {
        confirmacaoTitle.textContent = titulo;
        confirmacaoBody.innerHTML = mensagem;
        
        // CORREÇÃO: Em vez de clonar/substituir o nó (que quebrava a referência),
        // apenas sobrescrevemos a função onclick.
        btnConfirmarAcao.onclick = () => {
            confirmacaoModal.hide();
            // Pequeno delay para garantir que o modal feche antes de executar a ação
            // (especialmente útil se a ação abrir outro modal)
            setTimeout(() => {
                callbackConfirmacao();
            }, 200);
        };
        
        confirmacaoModal.show();
    };

    const handleApiError = async (response) => {
        let errorData;
        try { errorData = await response.json(); } catch (e) { errorData = await response.text(); }
        console.error("====== ERRO DA API ======");
        console.error("Status:", response.status);
        console.error("Msg:", errorData);
        return errorData?.message || `Erro ${response.status}.`;
    };

    // --- LÓGICA PRINCIPAL ---

    const verificarExistenciaDeInativos = async () => {
        try {
            const response = await fetch(`${API_BASE_URL}/dirigentes_api.php?show_inactive=true`);
            const inativos = await response.json();
            const label = document.querySelector('label[for="mostrar-inativos-check"]');
            if (inativos.length > 0) {
                mostrarInativosCheck.disabled = false;
                if(label) label.classList.remove('text-muted');
            } else {
                mostrarInativosCheck.disabled = true;
                if(label) label.classList.add('text-muted');
                if (mostrarInativosCheck.checked) {
                    mostrarInativosCheck.checked = false;
                    carregarUsuarios(); 
                }
            }
        } catch (error) {
            console.error("Erro ao verificar usuários inativos.", error);
            mostrarInativosCheck.disabled = true;
        }
    };

    const carregarUsuarios = async () => {
        const showInactive = mostrarInativosCheck.checked;
        tableBody.innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary"></div></td></tr>`;
        try {
            const response = await fetch(`${API_BASE_URL}/dirigentes_api.php?show_inactive=${showInactive}`);
            if (!response.ok) throw new Error(await handleApiError(response));
            
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
                const isDirigente = (u.permissoes & 1) === 1;
                const linkButton = isDirigente && u.token_acesso
                    ? `<button class="btn btn-sm btn-info btn-copy-link" data-token="${u.token_acesso}" title="Copiar Link Público"><i class="fas fa-link"></i></button>`
                    : '';

                const row = `<tr class="${rowClass}">
                        <td data-label="Nome">${u.nome}</td>
                        <td data-label="Login">${u.login}</td>
                        <td data-label="Cargo"><span class="badge bg-secondary">Permissões: ${u.permissoes}</span></td>
                        <td data-label="Status">${statusBadge}</td>
                        <td data-label="Ações">
                            ${linkButton}
                            <button class="btn btn-sm btn-warning btn-edit" data-id="${u.id}" title="Editar" ${isInactive ? 'disabled' : ''}><i class="fas fa-edit"></i></button>
                            ${actionButton}
                        </td>
                    </tr>`;
                tableBody.innerHTML += row;
            });
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
        } catch (error) { 
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Erro ao carregar usuários: ${error.message}</td></tr>`; 
        }
    };

    const prepararEdicao = async (id) => {
        try {
            const response = await fetch(`${API_BASE_URL}/dirigentes_api.php?id=${id}`);
            if (!response.ok) throw new Error(await handleApiError(response));
            const user = await response.json();
            
            document.getElementById('user_nome').value = user.nome;
            document.getElementById("user_login").value = user.login;
            const permissoesContainer = document.getElementById('user_permissoes_container');
            permissoesContainer.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                const bit = parseInt(checkbox.value);
                checkbox.checked = (user.permissoes & bit) === bit;
            });
            document.getElementById("user_senha").value = '';
            
            const linkSection = document.getElementById('link-publico-section');
            if ((user.permissoes & 1) === 1) {
                const linkInput = document.getElementById('user_public_link');
                const publicUrl = `${window.location.origin}${window.location.pathname.replace(/\/pages\/.*$/, '')}/backend/vista_publica.php?token=${user.token_acesso}`;
                linkInput.value = publicUrl;
                linkSection.classList.remove('d-none');
            } else { linkSection.classList.add('d-none'); }
            
            editMode = true;
            editId = id;
            modalTitle.textContent = 'Editar Usuário';
            userModal.show();
        } catch (error) { 
            mostrarFeedback('Erro', 'Não foi possível carregar os dados do usuário: ' + error.message, 'danger');
        }
    };

    const resetarModal = () => {
        document.getElementById('form-user').reset();
        document.getElementById('link-publico-section').classList.add('d-none');
        editMode = false; editId = null;
        modalTitle.textContent = 'Adicionar Usuário';
    };

    document.getElementById('salvar-user-btn').addEventListener('click', async () => {
        const getPermissoesBitmask = () => {
                let bitmask = 0;
                const permissoesContainer = document.getElementById('user_permissoes_container');
                permissoesContainer.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                    if (checkbox.checked) {
                        bitmask |= parseInt(checkbox.value);
                    }
                });
                return bitmask;
            };

            const data = {
            nome: document.getElementById('user_nome').value,
            login: document.getElementById('user_login').value,
            permissoes: getPermissoesBitmask(),
            senha: document.getElementById("user_senha").value,
        };
        
        if (!data.nome || !data.login) { 
            mostrarFeedback('Atenção', 'Nome e Login são obrigatórios.', 'warning'); 
            return; 
        }
        if (!editMode && !data.senha) { 
            mostrarFeedback('Atenção', 'A senha é obrigatória ao criar um novo usuário.', 'warning'); 
            return; 
        }
        
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
            
            if (!response.ok) throw new Error(await handleApiError(response));
            
            userModal.hide();
            carregarUsuarios();
            verificarExistenciaDeInativos();
            mostrarFeedback('Sucesso', `Usuário <b>${data.nome}</b> salvo com sucesso!`, 'success');
        } catch (error) { 
            mostrarFeedback('Erro ao Salvar', error.message, 'danger'); 
        }
    });

    // --- REGENERAÇÃO DE TOKEN (Com lógica específica de Cópia) ---
    document.getElementById('regenerar-token-btn').addEventListener('click', () => {
        if (!editId) return;
        
        // 1. Confirmação
        mostrarConfirmacao('Regenerar Link', 'O link antigo deixará de funcionar imediatamente. Deseja continuar?', async () => {
            try {
                // 2. Ação
                const response = await fetch(`${API_BASE_URL}/dirigentes_api.php?id=${editId}`, { 
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/json' }, 
                    body: JSON.stringify({ action: 'regenerate_token' }) 
                });
                
                if (!response.ok) throw new Error(await handleApiError(response));
                
                const result = await response.json();
                const newUrl = `${window.location.origin}${window.location.pathname.replace(/\/pages\/.*$/, '')}/backend/vista_publica.php?token=${result.novoToken}`;
                
                // Atualiza o input no modal que está embaixo
                document.getElementById('user_public_link').value = newUrl;
                
                // Atualiza a tabela
                carregarUsuarios(); 
                
                // 3. Feedback com botão de Cópia Personalizado
                const msg = `
                    <div class="text-center">
                        <p>Novo link gerado com sucesso!</p>
                        <button id="btn-copiar-feedback" class="btn btn-light border shadow-sm">
                            <i class="fas fa-copy"></i> Copiar Link
                        </button>
                    </div>
                `;
                
                mostrarFeedback('Sucesso', msg, 'success');

                // Adiciona evento ao botão criado dinamicamente dentro do modal de feedback
                // Pequeno delay para garantir que o elemento existe no DOM do modal
                setTimeout(() => {
                    const btnCopy = document.getElementById('btn-copiar-feedback');
                    if(btnCopy) {
                        btnCopy.onclick = () => {
                            navigator.clipboard.writeText(newUrl).then(() => {
                                btnCopy.innerHTML = '<i class="fas fa-check"></i> Copiado!';
                                btnCopy.classList.remove('btn-light');
                                btnCopy.classList.add('btn-success', 'text-white');
                            });
                        };
                    }
                }, 100);

            } catch (error) { 
                mostrarFeedback('Erro', error.message, 'danger'); 
            }
        });
    });
    
    mostrarInativosCheck.addEventListener('change', carregarUsuarios);

    tableBody.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return;
        const id = target.dataset.id;

        if (target.classList.contains('btn-delete')) {
            mostrarConfirmacao('Desativar Usuário', 'Tem certeza que deseja desativar este usuário?', async () => {
                try { 
                    const response = await fetch(`${API_BASE_URL}/dirigentes_api.php?id=${id}`, { 
                        method: 'POST', 
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete_user' })
                    }); 
                    if (!response.ok) throw new Error(await handleApiError(response));
                    
                    carregarUsuarios(); 
                    verificarExistenciaDeInativos();
                    mostrarFeedback('Sucesso', 'Usuário desativado.', 'success');
                } 
                catch (error) { 
                    mostrarFeedback('Erro', 'Não foi possível desativar: ' + error.message, 'danger'); 
                }
            });
        } else if (target.classList.contains('btn-reactivate')) {
            mostrarConfirmacao('Reativar Usuário', 'Deseja reativar este usuário?', async () => {
                 try {
                    const response = await fetch(`${API_BASE_URL}/dirigentes_api.php?id=${id}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'reactivate' }) 
                    });
                    if (!response.ok) throw new Error(await handleApiError(response));

                    if(!mostrarInativosCheck.checked) mostrarInativosCheck.checked = true;
                    carregarUsuarios();
                    verificarExistenciaDeInativos();
                    mostrarFeedback('Sucesso', 'Usuário reativado!', 'success');
                } catch (error) {
                    mostrarFeedback('Erro', 'Não foi possível reativar: ' + error.message, 'danger');
                }
            });
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
    
    carregarUsuarios();
    verificarExistenciaDeInativos();
});