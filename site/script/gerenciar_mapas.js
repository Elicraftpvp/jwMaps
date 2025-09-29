// site/script/gerenciar_mapas.js
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('mapas-table-body');
    const mapaModalElement = document.getElementById('mapaModal');
    const mapaModal = new bootstrap.Modal(mapaModalElement);
    const mapaModalLabel = document.getElementById('mapaModalLabel');
    const entregarModalElement = document.getElementById('entregarModal');
    const entregarModal = new bootstrap.Modal(entregarModalElement);
    const entregarModalLabel = document.getElementById('entregarModalLabel');
    const selectDirigentes = document.getElementById('entregar_dirigente_id');
    const historicoMapaModalElement = document.getElementById("historicoMapaModal");
    const historicoMapaModal = new bootstrap.Modal(historicoMapaModalElement);
    const historicoMapaIdentificadorSpan = document.getElementById("historico_mapa_identificador");
    const historicoTableBody = document.getElementById("historico-table-body");
    const filtroOrdenacaoMenu = document.getElementById('filtroOrdenacaoBtn').nextElementSibling;
    const filtroOrdenacaoBtnIcon = document.querySelector('#filtroOrdenacaoBtn i');
    const filtroDirigenteMenu = document.getElementById('filtroDirigenteMenu');
    const filtroDirigenteBtnIcon = document.querySelector('#filtroDirigenteBtn i');
    const filtroRegiaoMenu = document.getElementById("filtroRegiaoMenu");
    const filtroRegiaoBtnIcon = document.querySelector("#filtroRegiaoBtn i");

    let filtroDirigenteId = null;
    let filtroRegiao = null;
    let sortOrder = 'id';
    let editMode = false;
    let editId = null;

    const handleApiError = async (response) => {
        let errorData;
        try {
            errorData = await response.json();
        } catch (e) {
            errorData = await response.text();
        }
        console.error("====== ERRO DA API ======");
        console.error("Status:", response.status, response.statusText);
        console.error("Resposta do Servidor:", errorData);
        console.error("=======================");
        return errorData?.message || `Erro ${response.status}. Verifique o console.`;
    };

    const popularFiltroRegioes = (mapas) => {
        const regioesComMapas = [...new Set(mapas.filter(m => m.regiao).map(m => m.regiao))].sort();
        filtroRegiaoMenu.innerHTML = '<li><a class="dropdown-item" href="#" data-regiao=""><strong>Mostrar Todas</strong></a></li><li><hr class="dropdown-divider"></li>';
        if (regioesComMapas.length === 0) {
            filtroRegiaoMenu.innerHTML += '<li><span class="dropdown-item-text text-muted">Nenhuma região</span></li>';
        } else {
            regioesComMapas.forEach(regiao => {
                filtroRegiaoMenu.innerHTML += `<li><a class="dropdown-item" href="#" data-regiao="${regiao}">${regiao}</a></li>`;
            });
        }
    };

    const popularFiltroDirigentes = (mapas) => {
        const dirigentesComMapas = [...new Map(mapas.filter(m => m.dirigente_id).map(m => [m.dirigente_id, { id: m.dirigente_id, nome: m.dirigente_nome }])).values()];
        dirigentesComMapas.sort((a, b) => a.nome.localeCompare(b.nome));
        filtroDirigenteMenu.innerHTML = '<li><a class="dropdown-item" href="#" data-id=""><strong>Mostrar Todos</strong></a></li><li><hr class="dropdown-divider"></li>';
        if (dirigentesComMapas.length === 0) {
            filtroDirigenteMenu.innerHTML += '<li><span class="dropdown-item-text text-muted">Nenhum em uso</span></li>';
        } else {
            dirigentesComMapas.forEach(dirigente => {
                filtroDirigenteMenu.innerHTML += `<li><a class="dropdown-item" href="#" data-id="${dirigente.id}">${dirigente.nome}</a></li>`;
            });
        }
    };

    const carregarMapas = async () => {
        tableBody.innerHTML = `<tr><td colspan="9" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>`;
        try {
            const response = await fetch(`${API_BASE_URL}/mapas_api.php`);
            
            if (!response.ok) {
                const errorMessage = await handleApiError(response);
                throw new Error(errorMessage);
            }
            
            let mapas = await response.json();
            let mapasOriginais = [...mapas];

            popularFiltroDirigentes(mapasOriginais);
            popularFiltroRegioes(mapasOriginais);

            if (filtroDirigenteId) mapas = mapas.filter(mapa => mapa.dirigente_id == filtroDirigenteId);
            if (filtroRegiao) mapas = mapas.filter(mapa => mapa.regiao === filtroRegiao);
            
            // ▼▼▼ CORREÇÃO AQUI: Chamadas .toggle() separadas ▼▼▼
            filtroDirigenteBtnIcon.classList.toggle("text-primary", !!filtroDirigenteId);
            filtroDirigenteBtnIcon.classList.toggle("text-secondary", !filtroDirigenteId);

            filtroRegiaoBtnIcon.classList.toggle("text-primary", !!filtroRegiao);
            filtroRegiaoBtnIcon.classList.toggle("text-secondary", !filtroRegiao);
            // ▲▲▲ FIM DA CORREÇÃO ▲▲▲
            
            if (sortOrder === 'asc') {
                mapas.sort((a, b) => a.identificador.localeCompare(b.identificador, undefined, {numeric: true}));
            } else if (sortOrder === 'desc') {
                mapas.sort((a, b) => b.identificador.localeCompare(a.identificador, undefined, {numeric: true}));
            } else {
                mapas.sort((a, b) => a.id - b.id);
            }

            // ▼▼▼ CORREÇÃO AQUI: Chamadas .toggle() separadas ▼▼▼
            filtroOrdenacaoBtnIcon.classList.toggle("text-primary", sortOrder !== 'id');
            filtroOrdenacaoBtnIcon.classList.toggle("text-secondary", sortOrder === 'id');
            // ▲▲▲ FIM DA CORREÇÃO ▲▲▲

            tableBody.innerHTML = '';
            if (mapas.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="9" class="text-center">Nenhum mapa encontrado com os filtros aplicados.</td></tr>`; 
                return;
            }

            mapas.forEach(mapa => {
                let status, acaoEntregarResgatar, diasComDirigenteBadge;
                if (mapa.dirigente_id) {
                    status = `<span class="badge bg-warning">Em Uso</span>`;
                    acaoEntregarResgatar = `<button class="btn btn-sm btn-info btn-resgatar" data-id="${mapa.id}" title="Resgatar Mapa"><i class="fas fa-undo-alt"></i></button>`;
                    const dias = mapa.dias_com_dirigente;
                    let corBadge = 'success';
                    if (dias > 30) corBadge = 'warning';
                    if (dias > 60) corBadge = 'danger';
                    diasComDirigenteBadge = `<span class="badge bg-${corBadge}">${dias !== null ? dias : '0'}</span>`;
                } else {
                    status = `<span class="badge bg-success">Disponível</span>`;
                    acaoEntregarResgatar = `<button class="btn btn-sm btn-primary btn-entregar" data-id="${mapa.id}" data-identificador="${mapa.identificador}" title="Entregar Mapa"><i class="fas fa-hand-holding-heart"></i></button>`;
                    diasComDirigenteBadge = `<span class="badge bg-secondary">---</span>`;
                }
                const quadraRange = mapa.quadra_inicio && mapa.quadra_fim ? `${mapa.quadra_inicio} - ${mapa.quadra_fim}` : 'N/D';
                const row = `<tr>
                        <td>${mapa.id}</td>
                        <td>${mapa.identificador}</td>
                        <td>${mapa.regiao || 'N/D'}</td>
                        <td>${mapa.tipo || 'N/D'}</td>
                        <td>${quadraRange}</td>
                        <td>${status}</td>
                        <td>${mapa.dirigente_nome || '---'}</td>
                        <td class="text-center">${diasComDirigenteBadge}</td>
                        <td>
                            ${acaoEntregarResgatar}
                            <button class="btn btn-sm btn-warning btn-edit" data-id="${mapa.id}" title="Editar"><i class="fas fa-pencil-alt"></i></button>
                            <button class="btn btn-sm btn-secondary btn-history" data-id="${mapa.id}" data-identificador="${mapa.identificador}" title="Ver Histórico"><i class="fas fa-history"></i></button>
                            <button class="btn btn-sm btn-danger btn-delete" data-id="${mapa.id}" title="Excluir"><i class="fas fa-trash"></i></button>
                        </td></tr>`;
                tableBody.innerHTML += row;
            });
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        } catch (error) { 
            console.error("Falha ao carregar mapas:", error.message);
            tableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger"><b>Erro ao carregar mapas.</b><br><small>Verifique o console (F12) para detalhes técnicos.</small></td></tr>`; 
        }
    };
    
    const prepararEdicao = async (id) => {
        try {
            const response = await fetch(`${API_BASE_URL}/mapas_api.php?id=${id}`);
            if (!response.ok) throw new Error(await handleApiError(response));
            const mapa = await response.json();
            document.getElementById('mapa_identificador').value = mapa.identificador;
            document.getElementById("mapa_quadra_inicio").value = mapa.quadra_inicio;
            document.getElementById("mapa_quadra_fim").value = mapa.quadra_fim;
            document.getElementById("mapa_regiao").value = mapa.regiao || "";
            document.getElementById("mapa_tipo").value = mapa.tipo || "";
            editMode = true;
            editId = id;
            mapaModalLabel.textContent = 'Editar Mapa';
            mapaModal.show();
        } catch (error) { alert('Não foi possível carregar os dados do mapa: ' + error.message); }
    };
    const resetarModal = () => {
        document.getElementById("form-mapa").reset();
        editMode = false;
        editId = null;
        mapaModalLabel.textContent = "Adicionar Novo Mapa";
    };
    const carregarHistoricoMapa = async (mapaId, identificador) => {
        historicoMapaIdentificadorSpan.textContent = identificador;
        historicoTableBody.innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm"></div> Carregando...</div></td></tr>`;
        historicoMapaModal.show();
        try {
            const response = await fetch(`${API_BASE_URL}/mapas_api.php?recurso=history&id=${mapaId}`);
            if (!response.ok) throw new Error(await handleApiError(response));
            const historico = await response.json();
            historicoTableBody.innerHTML = "";
            if (!Array.isArray(historico) || historico.length === 0) {
                historicoTableBody.innerHTML = `<tr><td colspan="5" class="text-center">Nenhum histórico encontrado para este mapa.</td></tr>`;
                return;
            }
            historico.forEach(item => {
                const dataEntrega = new Date(item.data_entrega + 'T00:00:00').toLocaleDateString();
                const dataDevolucao = item.data_devolucao ? new Date(item.data_devolucao + 'T00:00:00').toLocaleDateString() : "Em Uso";
                const dadosQuadras = JSON.parse(item.dados_quadras || "[]");
                let detalhesQuadrasHtml = dadosQuadras.length > 0
                    ? `<ul class="list-unstyled mb-0">${dadosQuadras.map(q => `<li>Q.${q.numero}: ${q.pessoas_faladas}</li>`).join('')}</ul>`
                    : "N/D";
                const row = `<tr><td>${item.dirigente_nome}</td><td>${dataEntrega}</td><td>${dataDevolucao}</td><td class="text-center" colspan="2">${item.pessoas_faladas_total}</td></tr>`;
                historicoTableBody.innerHTML += row;
            });
        } catch (error) {
            historicoTableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Erro ao carregar histórico: ${error.message}</td></tr>`;
        }
    };
    mapaModalElement.addEventListener('hidden.bs.modal', resetarModal);
    document.getElementById('salvar-mapa-btn').addEventListener('click', async () => {
        const data = {
            identificador: document.getElementById('mapa_identificador').value,
            quadra_inicio: document.getElementById('mapa_quadra_inicio').value,
            quadra_fim: document.getElementById("mapa_quadra_fim").value,
            regiao: document.getElementById("mapa_regiao").value,
            tipo: document.getElementById("mapa_tipo").value,
        };
        if (!data.identificador || !data.quadra_inicio || !data.quadra_fim) { alert('Identificador e Quadras são obrigatórios.'); return; }
        if (parseInt(data.quadra_fim) < parseInt(data.quadra_inicio)) { alert('A quadra final deve ser maior ou igual à inicial.'); return; }
        const url = `${API_BASE_URL}/mapas_api.php`;
        if (editMode) {
            data.action = 'edit_details';
            data.id = editId;
        }
        try {
            const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
            if (!response.ok) throw new Error(await handleApiError(response));
            mapaModal.hide();
            carregarMapas();
        } catch (error) { alert('Erro ao salvar o mapa: ' + error.message); }
    });
    const carregarDirigentesNoModal = async () => {
        try {
            const response = await fetch(`${API_BASE_URL}/mapas_api.php?recurso=dirigentes`);
            if (!response.ok) throw new Error(await handleApiError(response));
            const dirigentes = await response.json();
            selectDirigentes.innerHTML = '<option value="">Selecione...</option>';
            dirigentes.forEach(d => selectDirigentes.innerHTML += `<option value="${d.id}">${d.nome}</option>`);
        } catch (error) { selectDirigentes.innerHTML = '<option value="">Erro ao carregar</option>'; alert(error.message); }
    };
    document.getElementById('confirmar-entrega-btn').addEventListener('click', async () => {
        const data = { action: 'entregar', mapa_id: document.getElementById('entregar_mapa_id').value, dirigente_id: document.getElementById('entregar_dirigente_id').value, data_entrega: document.getElementById('entregar_data').value, };
        if (!data.dirigente_id || !data.data_entrega) { alert('Selecione um dirigente e uma data.'); return; }
        try {
            const response = await fetch(`${API_BASE_URL}/mapas_api.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
            if (!response.ok) throw new Error(await handleApiError(response));
            entregarModal.hide();
            carregarMapas();
        } catch (error) { alert('Erro ao entregar o mapa: ' + error.message); }
    });
    tableBody.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return;
        const id = target.dataset.id;
        try {
            if (target.classList.contains('btn-delete')) {
                if (confirm('Deseja realmente excluir este mapa? O histórico e dados associados serão PERDIDOS permanentemente.')) {
                    const response = await fetch(`${API_BASE_URL}/mapas_api.php?id=${id}`, { method: 'DELETE' });
                    if (!response.ok) throw new Error(await handleApiError(response));
                    carregarMapas();
                }
            } else if (target.classList.contains('btn-entregar')) {
                entregarModalLabel.textContent = `Entregar Mapa: ${target.dataset.identificador}`;
                document.getElementById('entregar_mapa_id').value = id;
                document.getElementById('entregar_data').valueAsDate = new Date();
                await carregarDirigentesNoModal();
                entregarModal.show();
            } else if (target.classList.contains('btn-resgatar')) {
                if (confirm('Deseja resgatar este mapa? Ele ficará disponível para ser entregue a outro dirigente.')) {
                    const data = { action: 'resgatar', mapa_id: id };
                    const response = await fetch(`${API_BASE_URL}/mapas_api.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                    if (!response.ok) throw new Error(await handleApiError(response));
                    carregarMapas();
                }
            } else if (target.classList.contains("btn-edit")) {
                prepararEdicao(id);
            } else if (target.classList.contains("btn-history")) {
                const identificador = target.dataset.identificador;
                await carregarHistoricoMapa(id, identificador);
            }
        } catch (error) {
            alert(`Ocorreu um erro na ação: ${error.message}`);
        }
    });
    filtroOrdenacaoMenu.addEventListener("click", (e) => { e.preventDefault(); const target = e.target.closest("a.dropdown-item"); if (target && target.dataset.sort) { sortOrder = target.dataset.sort; carregarMapas(); } });
    filtroDirigenteMenu.addEventListener("click", (e) => { e.preventDefault(); const target = e.target.closest("a.dropdown-item"); if (target) { filtroDirigenteId = target.dataset.id ? parseInt(target.dataset.id, 10) : null; carregarMapas(); } });
    filtroRegiaoMenu.addEventListener("click", (e) => { e.preventDefault(); const target = e.target.closest("a.dropdown-item"); if (target) { filtroRegiao = target.dataset.regiao || null; carregarMapas(); } });
    carregarMapas();
});