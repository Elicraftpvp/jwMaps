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
    
    // ▼▼▼ NOVOS ELEMENTOS E VARIÁVEIS DE ESTADO ▼▼▼
    const filtroDirigenteMenu = document.getElementById('filtroDirigenteMenu');
    const filtroDirigenteBtnIcon = document.querySelector('#filtroDirigenteBtn i');
    let filtroDirigenteId = null; 
    let editMode = false;
    let editId = null;

    // ▼▼▼ NOVA FUNÇÃO PARA POPULAR O FILTRO ▼▼▼
    const popularFiltroDirigentes = (mapas) => {
        const dirigentesComMapas = [...new Map(mapas.filter(m => m.dirigente_id).map(m => [m.dirigente_id, { id: m.dirigente_id, nome: m.dirigente_nome }])).values()];
        dirigentesComMapas.sort((a, b) => a.nome.localeCompare(b.nome));

        filtroDirigenteMenu.innerHTML = '<li><a class="dropdown-item" href="#" data-id=""><strong>Mostrar Todos</strong></a></li><li><hr class="dropdown-divider"></li>';
        
        if (dirigentesComMapas.length === 0) {
            filtroDirigenteMenu.innerHTML += '<li><span class="dropdown-item-text text-muted">Nenhum mapa em uso</span></li>';
        } else {
            dirigentesComMapas.forEach(dirigente => {
                filtroDirigenteMenu.innerHTML += `<li><a class="dropdown-item" href="#" data-id="${dirigente.id}">${dirigente.nome}</a></li>`;
            });
        }
    };

    const carregarMapas = async () => {
        tableBody.innerHTML = `<tr><td colspan="7" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>`;
        try {
            const response = await fetch(`${API_BASE_URL}/mapas_api.php`);
            let mapas = await response.json(); // Alterado para 'let'

            // ▼▼▼ MUDANÇA: Popular filtro ANTES de filtrar os dados para exibição ▼▼▼
            popularFiltroDirigentes(mapas);

            // ▼▼▼ MUDANÇA: Aplicar o filtro se houver um selecionado ▼▼▼
            if (filtroDirigenteId) {
                mapas = mapas.filter(mapa => mapa.dirigente_id == filtroDirigenteId);
                filtroDirigenteBtnIcon.classList.remove('text-secondary');
                filtroDirigenteBtnIcon.classList.add('text-primary'); // Ícone ativo
            } else {
                filtroDirigenteBtnIcon.classList.add('text-secondary');
                filtroDirigenteBtnIcon.classList.remove('text-primary'); // Ícone inativo
            }
            
            tableBody.innerHTML = '';
            if (mapas.length === 0) {
                const mensagem = filtroDirigenteId ? 'Nenhum mapa encontrado para este dirigente.' : 'Nenhum mapa cadastrado.';
                tableBody.innerHTML = `<tr><td colspan="7" class="text-center">${mensagem}</td></tr>`; 
                return;
            }

            mapas.sort((a, b) => a.id - b.id);

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
                        <td>${quadraRange}</td>
                        <td>${status}</td>
                        <td>${mapa.dirigente_nome || '---'}</td>
                        <td class="text-center">${diasComDirigenteBadge}</td>
                        <td>
                            ${acaoEntregarResgatar}
                            <button class="btn btn-sm btn-warning btn-edit" data-id="${mapa.id}" title="Editar"><i class="fas fa-pencil-alt"></i></button>
                            <button class="btn btn-sm btn-info btn-history" data-id="${mapa.id}" data-identificador="${mapa.identificador}" title="Ver Histórico"><i class="fas fa-history"></i></button>
                            <button class="btn btn-sm btn-danger btn-delete" data-id="${mapa.id}" title="Excluir"><i class="fas fa-trash"></i></button>
                        </td></tr>`;
                tableBody.innerHTML += row;
            });
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
        } catch (error) { tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Erro ao carregar mapas.</td></tr>`; }
    };
    
    const prepararEdicao = async (id) => {
        try {
            const response = await fetch(`${API_BASE_URL}/mapas_api.php?id=${id}`);
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
        } catch (error) { alert('Não foi possível carregar os dados do mapa.'); }
    };
    
    const resetarModal = () => {
        document.getElementById("form-mapa").reset();
        editMode = false;
        editId = null;
        mapaModalLabel.textContent = "Adicionar Novo Mapa";
    };

    const historicoMapaModalElement = document.getElementById("historicoMapaModal");
    const historicoMapaModal = new bootstrap.Modal(historicoMapaModalElement);
    const historicoMapaIdentificadorSpan = document.getElementById("historico_mapa_identificador");
    const historicoTableBody = document.getElementById("historico-table-body");

    const carregarHistoricoMapa = async (mapaId, identificador) => {
        historicoMapaIdentificadorSpan.textContent = identificador;
        historicoTableBody.innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm"></div> Carregando histórico...</div></td></tr>`;
        try {
            const response = await fetch(`${API_BASE_URL}/mapas_api.php?recurso=history&id=${mapaId}`);
            const historico = await response.json();
            historicoTableBody.innerHTML = "";

            if (historico.length === 0) {
                historicoTableBody.innerHTML = `<tr><td colspan="5" class="text-center">Nenhum histórico encontrado para este mapa.</td></tr>`;
            } else {
                historico.forEach(item => {
                    const dataEntrega = new Date(item.data_entrega).toLocaleDateString();
                    const dataDevolucao = item.data_devolucao ? new Date(item.data_devolucao).toLocaleDateString() : "Em Uso";
                    const dadosQuadras = JSON.parse(item.dados_quadras || "[]");
                    let detalhesQuadrasHtml = "";
                    if (dadosQuadras.length > 0) {
                        detalhesQuadrasHtml = `<ul class="list-unstyled mb-0">`;
                        dadosQuadras.forEach(quadra => {
                            detalhesQuadrasHtml += `<li>Quadra ${quadra.numero}: ${quadra.pessoas_faladas} pessoas faladas</li>`;
                        });
                        detalhesQuadrasHtml += `</ul>`;
                    } else {
                        detalhesQuadrasHtml = "N/D";
                    }

                    const row = `<tr>
                                    <td>${item.dirigente_nome}</td>
                                    <td>${dataEntrega}</td>
                                    <td>${dataDevolucao}</td>
                                    <td>${item.pessoas_faladas_total}</td>
                                    <td>${detalhesQuadrasHtml}</td>
                                </tr>`;
                    historicoTableBody.innerHTML += row;
                });
            }
            historicoMapaModal.show();
        } catch (error) {
            historicoTableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Erro ao carregar histórico: ${error.message}</td></tr>`;
            console.error("Erro ao carregar histórico do mapa:", error);
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
        if (!data.identificador || !data.quadra_inicio || !data.quadra_fim) { alert('Todos os campos são obrigatórios.'); return; }
        if (parseInt(data.quadra_fim) < parseInt(data.quadra_inicio)) { alert('A quadra final deve ser maior ou igual à inicial.'); return; }
        
        const url = `${API_BASE_URL}/mapas_api.php`;
        const method = 'POST';

        if (editMode) {
            data.action = 'edit_details';
            data.id = editId;
        }

        try {
            await fetch(url, { method: method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
            mapaModal.hide();
            carregarMapas();
        } catch (error) { alert('Erro ao salvar o mapa: ' + error.message); }
    });

    const carregarDirigentesNoModal = async () => {
        try {
            const response = await fetch(`${API_BASE_URL}/mapas_api.php?recurso=dirigentes`);
            const dirigentes = await response.json();
            selectDirigentes.innerHTML = '<option value="">Selecione...</option>';
            dirigentes.forEach(d => {
                selectDirigentes.innerHTML += `<option value="${d.id}">${d.nome}</option>`;
            });
        } catch (error) { selectDirigentes.innerHTML = '<option value="">Erro ao carregar</option>'; }
    };

    document.getElementById('confirmar-entrega-btn').addEventListener('click', async () => {
        const data = {
            action: 'entregar',
            mapa_id: document.getElementById('entregar_mapa_id').value,
            dirigente_id: document.getElementById('entregar_dirigente_id').value,
            data_entrega: document.getElementById('entregar_data').value,
        };
        if (!data.dirigente_id || !data.data_entrega) { alert('Selecione um dirigente e uma data.'); return; }
        try {
            await fetch(`${API_BASE_URL}/mapas_api.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
            entregarModal.hide();
            carregarMapas();
        } catch (error) { alert('Erro ao entregar o mapa.'); }
    });

    tableBody.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return;
        const id = target.dataset.id;
        
        if (target.classList.contains('btn-delete')) {
            if (confirm('Deseja realmente excluir este mapa? O histórico e dados associados serão PERDIDOS permanentemente.')) {
                try { await fetch(`${API_BASE_URL}/mapas_api.php?id=${id}`, { method: 'DELETE' }); carregarMapas(); }
                catch (error) { alert('Não foi possível excluir o mapa.'); }
            }
        } 
        else if (target.classList.contains('btn-entregar')) {
            entregarModalLabel.textContent = `Entregar Mapa: ${target.dataset.identificador}`;
            document.getElementById('entregar_mapa_id').value = id;
            document.getElementById('entregar_data').valueAsDate = new Date();
            await carregarDirigentesNoModal();
            entregarModal.show();
        } 
        else if (target.classList.contains('btn-resgatar')) {
            if (confirm('Deseja resgatar este mapa? Ele ficará disponível para ser entregue a outro dirigente.')) {
                try {
                    const data = { action: 'resgatar', mapa_id: id };
                    await fetch(`${API_BASE_URL}/mapas_api.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                    carregarMapas();
                } catch (error) { alert('Não foi possível resgatar o mapa.'); }
            }
        } 
        else if (target.classList.contains("btn-edit")) {
            prepararEdicao(id);
        }
        else if (target.classList.contains("btn-history")) {
            const identificador = target.dataset.identificador;
            await carregarHistoricoMapa(id, identificador);
        }
    });

    // ▼▼▼ NOVO EVENT LISTENER PARA O DROPDOWN DE FILTRO ▼▼▼
    filtroDirigenteMenu.addEventListener('click', (e) => {
        e.preventDefault();
        const target = e.target.closest('a.dropdown-item');
        if (target) {
            const id = target.dataset.id;
            filtroDirigenteId = id ? parseInt(id, 10) : null;
            carregarMapas(); // Recarrega a lista com o filtro aplicado
        }
    });
    
    carregarMapas();
});