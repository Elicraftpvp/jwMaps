// site/script/gerenciar_grupos.js
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('grupos-table-body');
    const grupoModalElement = document.getElementById('grupoModal');
    const grupoModal = new bootstrap.Modal(grupoModalElement);
    const modalTitle = document.getElementById('grupoModalLabel');
    const feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
    const confirmacaoModal = new bootstrap.Modal(document.getElementById('confirmacaoModal'));
    const btnConfirmarAcao = document.getElementById('btnConfirmarAcao');

    let editMode = false;
    let editId = null;

    const mostrarFeedback = (titulo, mensagem, tipo = 'primary') => {
        document.getElementById('feedbackModalTitle').textContent = titulo;
        document.getElementById('feedbackModalBody').innerHTML = mensagem;
        const header = document.querySelector('#feedbackModal .modal-header');
        header.className = `modal-header bg-${tipo} text-white`;
        feedbackModal.show();
    };

    const carregarListasAuxiliares = async () => {
        try {
            const [usersRes, mapasRes] = await Promise.all([
                fetch(`${API_BASE_URL}/dirigentes_api.php`),
                fetch(`${API_BASE_URL}/mapas_api.php`)
            ]);
            const users = await usersRes.json();
            const mapas = await mapasRes.json();

            const containerUsers = document.getElementById('lista_usuarios_grupo');
            containerUsers.innerHTML = users.map(u => `
                <div class="form-check">
                    <input class="form-check-input check-user" type="checkbox" value="${u.id}" id="user_${u.id}">
                    <label class="form-check-label" for="user_${u.id}">${u.nome}</label>
                </div>
            `).join('');

            const containerMapas = document.getElementById('lista_mapas_grupo');
            containerMapas.innerHTML = mapas.map(m => `
                <div class="form-check">
                    <input class="form-check-input check-mapa" type="checkbox" value="${m.id}" id="mapa_${m.id}">
                    <label class="form-check-label" for="mapa_${m.id}">${m.identificador}</label>
                </div>
            `).join('');
        } catch (e) { console.error("Erro ao carregar listas auxiliares", e); }
    };

    const carregarGrupos = async () => {
        tableBody.innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>`;
        try {
            const res = await fetch(`${API_BASE_URL}/grupos_api.php`);
            const grupos = await res.json();
            tableBody.innerHTML = grupos.map(g => `
                <tr>
                    <td data-label="Nome" class="fw-bold">${g.nome}</td>
                    <td data-label="Membros"><span class="badge bg-info">${g.total_membros} Membros</span></td>
                    <td data-label="Mapas"><span class="badge bg-secondary">${g.total_mapas} Mapas</span></td>
                    <td data-label="Status"><span class="badge bg-${g.status === 'ativo' ? 'success' : 'danger'}">${g.status}</span></td>
                    <td data-label="Ações">
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-info btn-copy-grupo" data-token="${g.token_acesso}" title="Copiar Link Público"><i class="fas fa-link"></i></button>
                            <button class="btn btn-sm btn-warning btn-edit-grupo" data-id="${g.id}"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger btn-delete-grupo" data-id="${g.id}"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `).join('');
        } catch (e) { tableBody.innerHTML = `<tr><td colspan="5">Erro ao carregar.</td></tr>`; }
    };

    const prepararEdicao = async (id) => {
        await carregarListasAuxiliares();
        try {
            const res = await fetch(`${API_BASE_URL}/grupos_api.php?id=${id}`);
            const g = await res.json();
            
            document.getElementById('grupo_nome').value = g.nome;
            g.membros_ids.forEach(uid => {
                const cb = document.getElementById(`user_${uid}`);
                if(cb) cb.checked = true;
            });
            g.mapas_ids.forEach(mid => {
                const cb = document.getElementById(`mapa_${mid}`);
                if(cb) cb.checked = true;
            });

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
            mapas: Array.from(document.querySelectorAll('.check-mapa:checked')).map(c => c.value)
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
        if(btn.classList.contains('btn-delete-grupo')) {
            btnConfirmarAcao.onclick = async () => {
                await fetch(`${API_BASE_URL}/grupos_api.php`, {
                    method: 'POST',
                    body: JSON.stringify({action: 'delete', id: id})
                });
                confirmacaoModal.hide();
                carregarGrupos();
            };
            confirmacaoModal.show();
        }
    });

    grupoModalElement.addEventListener('show.bs.modal', () => { if(!editMode) carregarListasAuxiliares(); });
    grupoModalElement.addEventListener('hidden.bs.modal', () => {
        document.getElementById('form-grupo').reset();
        document.getElementById('link-grupo-section').classList.add('d-none');
        editMode = false; editId = null; modalTitle.textContent = 'Novo Grupo';
    });

    carregarGrupos();
});