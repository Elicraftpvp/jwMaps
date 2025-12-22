// site/script/gerenciar_grupos.js
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('grupos-table-body');
    const grupoModalElement = document.getElementById('grupoModal');
    const grupoModal = new bootstrap.Modal(grupoModalElement);
    const modalTitle = document.getElementById('grupoModalLabel');
    const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
    const confirmacaoModal = new bootstrap.Modal(document.getElementById('confirmacaoModal'));
    const btnConfirmarAcao = document.getElementById('btnConfirmarAcao');
    
    // Referência para o checkbox de mostrar inativos (deve ser adicionado ao HTML)
    const mostrarInativosCheck = document.getElementById('mostrar-inativos-grupos-check');

    let editMode = false;
    let editId = null;

    const mostrarFeedback = (titulo, mensagem, tipo = 'primary') => {
        document.getElementById('feedbackModalTitle').textContent = titulo;
        document.getElementById('feedbackModalBody').innerHTML = mensagem;
        const header = document.querySelector('#feedbackModal .modal-header');
        header.className = `modal-header bg-${tipo} text-white`;
        feedbackModal.show();
    };

    // Verifica se existem grupos inativos para habilitar ou não o filtro
    const verificarExistenciaDeInativos = async () => {
        if (!mostrarInativosCheck) return;
        try {
            const res = await fetch(`${API_BASE_URL}/grupos_api.php?show_inactive=true`);
            const grupos = await res.json();
            const inativos = grupos.filter(g => g.status === 'inativo');
            
            const label = document.querySelector('label[for="mostrar-inativos-grupos-check"]');
            if (inativos.length > 0) {
                mostrarInativosCheck.disabled = false;
                if(label) label.classList.remove('text-muted');
            } else {
                mostrarInativosCheck.disabled = true;
                if(label) label.classList.add('text-muted');
                if (mostrarInativosCheck.checked) {
                    mostrarInativosCheck.checked = false;
                    carregarGrupos();
                }
            }
        } catch (e) {
            console.error("Erro ao verificar grupos inativos", e);
        }
    };

    const carregarListasAuxiliares = async () => {
        try {
            const usersRes = await fetch(`${API_BASE_URL}/dirigentes_api.php`);
            const users = await usersRes.json();

            const containerUsers = document.getElementById('lista_usuarios_grupo');
            containerUsers.innerHTML = `
                <div class="row">
                    ${users.map(u => `
                        <div class="col-12 col-md-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input check-user" type="checkbox" value="${u.id}" id="user_${u.id}">
                                <label class="form-check-label" for="user_${u.id}">${u.nome}</label>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        } catch (e) { console.error("Erro ao carregar listas auxiliares", e); }
    };

    const carregarGrupos = async () => {
        const showInactive = mostrarInativosCheck ? mostrarInativosCheck.checked : false;
        tableBody.innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>`;
        
        try {
            const res = await fetch(`${API_BASE_URL}/grupos_api.php?show_inactive=${showInactive}`);
            const grupos = await res.json();
            
            if (grupos.length === 0) {
                const msg = showInactive ? "Nenhum grupo inativo encontrado." : "Nenhum grupo ativo encontrado.";
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center">${msg}</td></tr>`;
                return;
            }

            tableBody.innerHTML = grupos.map(g => {
                const isInactive = g.status === 'inativo';
                const rowClass = isInactive ? 'table-secondary text-muted' : '';
                
                const actionButtons = isInactive 
                    ? `<button class="btn btn-sm btn-success btn-reactivate-grupo" data-id="${g.id}" title="Reativar Grupo"><i class="fas fa-undo"></i></button>`
                    : `
                        <button class="btn btn-sm btn-info btn-copy-grupo" data-token="${g.token_acesso}" title="Copiar Link Público"><i class="fas fa-link"></i></button>
                        <button class="btn btn-sm btn-warning btn-edit-grupo" data-id="${g.id}"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger btn-deactivate-grupo" data-id="${g.id}" title="Desativar Grupo"><i class="fas fa-trash"></i></button>
                    `;

                return `
                    <tr class="${rowClass}">
                        <td data-label="Nome" class="fw-bold">${g.nome}</td>
                        <td data-label="Membros"><span class="badge bg-info">${g.total_membros} Membros</span></td>
                        <td data-label="Mapas"><span class="badge bg-secondary">${g.total_mapas} Mapas</span></td>
                        <td data-label="Status"><span class="badge bg-${isInactive ? 'danger' : 'success'}">${g.status}</span></td>
                        <td data-label="Ações">
                            <div class="d-flex gap-2">
                                ${actionButtons}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        } catch (e) { tableBody.innerHTML = `<tr><td colspan="5">Erro ao carregar.</td></tr>`; }
    };

    const prepararEdicao = async (id) => {
        await carregarListasAuxiliares();
        try {
            const res = await fetch(`${API_BASE_URL}/grupos_api.php?id=${id}`);
            const g = await res.json();
            
            document.getElementById('grupo_nome').value = g.nome;
            if (g.membros_ids) {
                g.membros_ids.forEach(uid => {
                    const cb = document.getElementById(`user_${uid}`);
                    if(cb) cb.checked = true;
                });
            }

            if(g.token_acesso) {
                document.getElementById('link-grupo-section').classList.remove('d-none');
                document.getElementById('grupo_public_link').value = `${window.location.origin}/grupo/${g.token_acesso}`;
            }

            editMode = true;
            editId = id;
            modalTitle.textContent = 'Editar Grupo';
            grupoModal.show();
        } catch (e) { mostrarFeedback('Erro', 'Falha ao buscar dados.'); }
    };

    document.getElementById('salvar-grupo-btn').addEventListener('click', async () => {
        const payload = {
            action: editMode ? 'update' : 'create',
            id: editId,
            nome: document.getElementById('grupo_nome').value,
            membros: Array.from(document.querySelectorAll('.check-user:checked')).map(c => c.value),
            mapas: []
        };

        if(!payload.nome) return mostrarFeedback('Atenção', 'Nome é obrigatório.', 'warning');

        try {
            const res = await fetch(`${API_BASE_URL}/grupos_api.php`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            if(res.ok) {
                grupoModal.hide();
                carregarGrupos();
                verificarExistenciaDeInativos();
                mostrarFeedback('Sucesso', 'Grupo salvo!', 'success');
            }
        } catch (e) { mostrarFeedback('Erro', 'Falha ao salvar.'); }
    });

    tableBody.addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if(!btn) return;
        const id = btn.dataset.id;

        if(btn.classList.contains('btn-edit-grupo')) prepararEdicao(id);
        
        if(btn.classList.contains('btn-copy-grupo')) {
            const url = `${window.location.origin}/grupo/${btn.dataset.token}`;
            navigator.clipboard.writeText(url).then(() => mostrarFeedback('Link Copiado', url, 'success'));
        }

        if(btn.classList.contains('btn-deactivate-grupo')) {
            document.getElementById('confirmacaoModalBody').textContent = 'Tem certeza que deseja desativar este grupo? Ele ficará oculto da lista principal.';
            btnConfirmarAcao.onclick = async () => {
                await fetch(`${API_BASE_URL}/grupos_api.php`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'deactivate', id: id})
                });
                confirmacaoModal.hide();
                carregarGrupos();
                verificarExistenciaDeInativos();
            };
            confirmacaoModal.show();
        }

        if(btn.classList.contains('btn-reactivate-grupo')) {
            document.getElementById('confirmacaoModalBody').textContent = 'Deseja reativar este grupo?';
            btnConfirmarAcao.onclick = async () => {
                await fetch(`${API_BASE_URL}/grupos_api.php`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'reactivate', id: id})
                });
                confirmacaoModal.hide();
                carregarGrupos();
                verificarExistenciaDeInativos();
            };
            confirmacaoModal.show();
        }
    });

    if (mostrarInativosCheck) {
        mostrarInativosCheck.addEventListener('change', carregarGrupos);
    }

    grupoModalElement.addEventListener('show.bs.modal', () => { if(!editMode) carregarListasAuxiliares(); });
    grupoModalElement.addEventListener('hidden.bs.modal', () => {
        document.getElementById('form-grupo').reset();
        document.getElementById('link-grupo-section').classList.add('d-none');
        editMode = false; editId = null; modalTitle.textContent = 'Novo Grupo';
        document.querySelectorAll('.check-user').forEach(c => c.checked = false);
    });

    carregarGrupos();
    verificarExistenciaDeInativos();
});