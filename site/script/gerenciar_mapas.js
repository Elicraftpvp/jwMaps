// site/script/gerenciar_mapas.js
document.addEventListener('DOMContentLoaded', () => {
    // Referências do DOM
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
    
    // Filtros DOM
    const filtroOrdenacaoMenu = document.getElementById('filtroOrdenacaoBtn').nextElementSibling;
    const filtroOrdenacaoBtnIcon = document.querySelector('#filtroOrdenacaoBtn i');
    const filtroDirigenteMenu = document.getElementById('filtroDirigenteMenu');
    const filtroDirigenteBtnIcon = document.querySelector('#filtroDirigenteBtn i');
    const filtroRegiaoMenu = document.getElementById("filtroRegiaoMenu");
    const filtroRegiaoBtnIcon = document.querySelector("#filtroRegiaoBtn i");
    
    // Novo Filtro Tipo
    const filtroTipoBtnIcon = document.querySelector("#filtroTipoBtn i");
    const filtroTipoMenu = document.getElementById("filtroTipoMenu");
    const listaCheckboxesTipos = document.getElementById("listaCheckboxesTipos");

    // Modais Gerais
    const feedbackModalElement = document.getElementById('feedbackModal');
    const feedbackModal = new bootstrap.Modal(feedbackModalElement);
    const feedbackTitle = document.getElementById('feedbackModalTitle');
    const feedbackBody = document.getElementById('feedbackModalBody');
    const confirmacaoModalElement = document.getElementById('confirmacaoModal');
    const confirmacaoModal = new bootstrap.Modal(confirmacaoModalElement);
    const confirmacaoTitle = document.getElementById('confirmacaoModalTitle');
    const confirmacaoBody = document.getElementById('confirmacaoModalBody');
    const btnConfirmarAcao = document.getElementById('btnConfirmarAcao');

    // Estado da Aplicação
    let filtroDirigenteId = null;
    let filtroRegiao = null;
    let filtrosTipoSelecionados = new Set(); // Armazena os tipos marcados (checkbox)
    
    let sortOrder = 'id'; // id, asc, desc, tipo_asc, tipo_desc
    let editMode = false;
    let editId = null;

    // --- FUNÇÕES AUXILIARES DE MODAL ---

    const mostrarFeedback = (titulo, mensagem, tipo = 'primary') => {
        feedbackTitle.textContent = titulo;
        feedbackBody.innerHTML = mensagem;
        const header = feedbackModalElement.querySelector('.modal-header');
        header.className = 'modal-header';
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
        btnConfirmarAcao.onclick = () => {
            confirmacaoModal.hide();
            callbackConfirmacao();
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

    // --- POPULAÇÃO DOS FILTROS ---

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

    const popularFiltroTipos = (mapas) => {
        // Extrair tipos únicos
        const tiposUnicos = [...new Set(mapas.filter(m => m.tipo).map(m => m.tipo))].sort();
        
        // Limpar lista atual
        listaCheckboxesTipos.innerHTML = "";

        if (tiposUnicos.length === 0) {
            listaCheckboxesTipos.innerHTML = '<span class="dropdown-item-text text-muted">Nenhum tipo</span>';
            return;
        }

        // Criar checkboxes
        tiposUnicos.forEach(tipo => {
            const isChecked = filtrosTipoSelecionados.has(tipo) ? 'checked' : '';
            const div = document.createElement('div');
            div.className = 'dropdown-item-checkbox';
            div.innerHTML = `
                <input class="form-check-input mt-0" type="checkbox" value="${tipo}" ${isChecked}>
                <span class="ms-2">${tipo}</span>
            `;
            
            // Event Listener para clicar na div ou no checkbox
            div.addEventListener('click', (e) => {
                e.stopPropagation(); // Impede o fechamento do dropdown
                // Se clicou na div mas não no input, inverte o input
                let checkbox = div.querySelector('input');
                if (e.target !== checkbox) {
                    checkbox.checked = !checkbox.checked;
                }
                
                // Atualiza o Set de filtros
                if (checkbox.checked) {
                    filtrosTipoSelecionados.add(tipo);
                } else {
                    filtrosTipoSelecionados.delete(tipo);
                }
                
                // Recarrega a tabela (modo visual apenas, sem fetch novo)
                carregarMapas(true, false); 
            });

            listaCheckboxesTipos.appendChild(div);
        });
    };

    // --- CARREGAMENTO PRINCIPAL ---

    let cacheMapas = null; // Armazena dados brutos para filtragem local rápida

    /**
     * @param {boolean} manterVisual - Se true, não mostra "Carregando" na tabela.
     * @param {boolean} fetchNovo - Se true, busca do servidor. Se false, usa cacheMapas e aplica filtros locais.
     */
    const carregarMapas = async (manterVisual = false, fetchNovo = true) => {
        const scrollPos = window.scrollY;

        if (fetchNovo && !manterVisual) {
            tableBody.innerHTML = `<tr><td colspan="8" class="text-center"><div class="spinner-border spinner-border-sm"></div> Carregando...</td></tr>`;
        }

        try {
            let mapas;

            if (fetchNovo) {
                const response = await fetch(`${API_BASE_URL}/mapas_api.php`);
                if (!response.ok) throw new Error(await handleApiError(response));
                cacheMapas = await response.json();
                
                // Popula os menus apenas quando busca dados novos
                popularFiltroDirigentes(cacheMapas);
                popularFiltroRegioes(cacheMapas);
                popularFiltroTipos(cacheMapas); 
            }

            mapas = [...cacheMapas];

            // 1. Aplicar Filtro Região
            if (filtroRegiao) {
                mapas = mapas.filter(mapa => mapa.regiao === filtroRegiao);
            }

            // 2. Aplicar Filtro Dirigente
            if (filtroDirigenteId) {
                mapas = mapas.filter(mapa => mapa.dirigente_id == filtroDirigenteId);
            }

            // 3. Aplicar Filtro Tipo (Multi-seleção)
            if (filtrosTipoSelecionados.size > 0) {
                mapas = mapas.filter(mapa => filtrosTipoSelecionados.has(mapa.tipo));
            }

            // Atualizar Ícones de Filtro
            filtroDirigenteBtnIcon.classList.toggle("text-primary", !!filtroDirigenteId);
            filtroDirigenteBtnIcon.classList.toggle("text-secondary", !filtroDirigenteId);

            filtroRegiaoBtnIcon.classList.toggle("text-primary", !!filtroRegiao);
            filtroRegiaoBtnIcon.classList.toggle("text-secondary", !filtroRegiao);

            filtroTipoBtnIcon.classList.toggle("text-primary", filtrosTipoSelecionados.size > 0 || sortOrder.includes('tipo'));
            filtroTipoBtnIcon.classList.toggle("text-secondary", filtrosTipoSelecionados.size === 0 && !sortOrder.includes('tipo'));

            filtroOrdenacaoBtnIcon.classList.toggle("text-primary", sortOrder !== 'id' && !sortOrder.includes('tipo'));
            filtroOrdenacaoBtnIcon.classList.toggle("text-secondary", sortOrder === 'id' || sortOrder.includes('tipo'));

            // 4. Ordenação
            if (sortOrder === 'asc') {
                mapas.sort((a, b) => a.identificador.localeCompare(b.identificador, undefined, {numeric: true}));
            } else if (sortOrder === 'desc') {
                mapas.sort((a, b) => b.identificador.localeCompare(a.identificador, undefined, {numeric: true}));
            } else if (sortOrder === 'tipo_asc') {
                // Ordena por Tipo A-Z, depois por Identificador
                mapas.sort((a, b) => {
                    const tipoA = a.tipo || "";
                    const tipoB = b.tipo || "";
                    return tipoA.localeCompare(tipoB) || a.identificador.localeCompare(b.identificador, undefined, {numeric: true});
                });
            } else if (sortOrder === 'tipo_desc') {
                // Ordena por Tipo Z-A, depois por Identificador
                mapas.sort((a, b) => {
                    const tipoA = a.tipo || "";
                    const tipoB = b.tipo || "";
                    return tipoB.localeCompare(tipoA) || a.identificador.localeCompare(b.identificador, undefined, {numeric: true});
                });
            } else {
                // ID (Padrão)
                mapas.sort((a, b) => a.id - b.id);
            }

            // Renderização
            let newHtml = '';
            if (mapas.length === 0) {
                newHtml = `<tr><td colspan="8" class="text-center">Nenhum mapa encontrado com os filtros aplicados.</td></tr>`; 
            } else {
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
                    
                    // Nota: Removemos a coluna ID. Total 8 colunas agora.
                    const row = `<tr>
                            <td data-label="Identificador" class="card-title-cell fw-bold">${mapa.identificador}</td>
                            <td data-label="Região">${mapa.regiao || 'N/D'}</td>
                            <td data-label="Tipo">${mapa.tipo || 'N/D'}</td>
                            <td data-label="Quadras">${quadraRange}</td>
                            <td data-label="Status">${status}</td>
                            <td data-label="Dirigente">${mapa.dirigente_nome || '---'}</td>
                            <td data-label="Tempo" class="text-center">${diasComDirigenteBadge}</td>
                            <td data-label="Ações">
                                ${acaoEntregarResgatar}
                                <button class="btn btn-sm btn-warning btn-edit" data-id="${mapa.id}" title="Editar"><i class="fas fa-pencil-alt"></i></button>
                                <button class="btn btn-sm btn-secondary btn-history" data-id="${mapa.id}" data-identificador="${mapa.identificador}" title="Ver Histórico"><i class="fas fa-history"></i></button>
                            </td>
                        </tr>`;
                    newHtml += row;
                });
            }

            tableBody.innerHTML = newHtml;

            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

            if (manterVisual) {
                window.scrollTo(0, scrollPos);
            }

        } catch (error) { 
            console.error("Falha ao carregar mapas:", error.message);
            tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger"><b>Erro ao carregar mapas.</b><br><small>${error.message}</small></td></tr>`; 
        }
    };
    
    // --- LÓGICA DE EVENTOS DE FILTRO ---

    filtroOrdenacaoMenu.addEventListener("click", (e) => { 
        e.preventDefault(); 
        const target = e.target.closest("a.dropdown-item"); 
        if (target && target.dataset.sort) { 
            sortOrder = target.dataset.sort; 
            carregarMapas(true, false); 
        } 
    });

    filtroDirigenteMenu.addEventListener("click", (e) => { 
        e.preventDefault(); 
        const target = e.target.closest("a.dropdown-item"); 
        if (target) { 
            filtroDirigenteId = target.dataset.id ? parseInt(target.dataset.id, 10) : null; 
            carregarMapas(true, false); 
        } 
    });

    filtroRegiaoMenu.addEventListener("click", (e) => { 
        e.preventDefault(); 
        const target = e.target.closest("a.dropdown-item"); 
        if (target) { 
            filtroRegiao = target.dataset.regiao || null; 
            carregarMapas(true, false); 
        } 
    });

    filtroTipoMenu.addEventListener("click", (e) => {
        // Verifica se clicou num botão de ordenação dentro do menu de Tipo
        const targetSort = e.target.closest("a.dropdown-item");
        if (targetSort && targetSort.dataset.sortType) {
            e.preventDefault();
            sortOrder = targetSort.dataset.sortType;
            carregarMapas(true, false);
        }
        // Checkboxes são tratados na criação do elemento (popularFiltroTipos) para stopPropagation
    });

    // --- AÇÕES DO CRUD ---

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
        } catch (error) { 
            mostrarFeedback('Erro', 'Não foi possível carregar os dados: ' + error.message, 'danger'); 
        }
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
                historicoTableBody.innerHTML = `<tr><td colspan="5" class="text-center">Nenhum histórico encontrado.</td></tr>`;
                return;
            }
            historico.forEach(item => {
                const dataEntrega = new Date(item.data_entrega + 'T00:00:00').toLocaleDateString();
                const dataDevolucao = item.data_devolucao ? new Date(item.data_devolucao + 'T00:00:00').toLocaleDateString() : "Em Uso";
                const row = `<tr><td>${item.dirigente_nome}</td><td>${dataEntrega}</td><td>${dataDevolucao}</td><td class="text-center" colspan="2">${item.pessoas_faladas_total}</td></tr>`;
                historicoTableBody.innerHTML += row;
            });
        } catch (error) {
            historicoTableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${error.message}</td></tr>`;
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
        
        if (!data.identificador || !data.quadra_inicio || !data.quadra_fim) { 
            mostrarFeedback('Atenção', 'Preencha identificador e quadras.', 'warning'); 
            return; 
        }
        if (parseInt(data.quadra_fim) < parseInt(data.quadra_inicio)) { 
            mostrarFeedback('Atenção', 'A quadra final deve ser maior ou igual à inicial.', 'warning'); 
            return; 
        }
        
        const url = `${API_BASE_URL}/mapas_api.php`;
        if (editMode) {
            data.action = 'edit_details';
            data.id = editId;
        }
        try {
            const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
            if (!response.ok) throw new Error(await handleApiError(response));
            mapaModal.hide();
            carregarMapas(true); // Recarrega do servidor
            mostrarFeedback('Sucesso', `Mapa <b>${data.identificador}</b> salvo!`, 'success');
        } catch (error) { 
            mostrarFeedback('Erro', error.message, 'danger'); 
        }
    });

    const carregarDirigentesNoModal = async () => {
        try {
            const response = await fetch(`${API_BASE_URL}/mapas_api.php?recurso=dirigentes`);
            if (!response.ok) throw new Error(await handleApiError(response));
            const dirigentes = await response.json();
            selectDirigentes.innerHTML = '<option value="">Selecione...</option>';
            dirigentes.forEach(d => selectDirigentes.innerHTML += `<option value="${d.id}">${d.nome}</option>`);
        } catch (error) { 
            selectDirigentes.innerHTML = '<option value="">Erro ao carregar</option>'; 
            mostrarFeedback('Erro', error.message, 'danger'); 
        }
    };

    document.getElementById('confirmar-entrega-btn').addEventListener('click', async () => {
        const data = { 
            action: 'entregar', 
            mapa_id: document.getElementById('entregar_mapa_id').value, 
            dirigente_id: document.getElementById('entregar_dirigente_id').value, 
            data_entrega: document.getElementById('entregar_data').value, 
        };
        if (!data.dirigente_id || !data.data_entrega) { mostrarFeedback('Atenção', 'Preencha todos os campos.', 'warning'); return; }
        
        try {
            const response = await fetch(`${API_BASE_URL}/mapas_api.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
            if (!response.ok) throw new Error(await handleApiError(response));
            entregarModal.hide();
            carregarMapas(true);
            mostrarFeedback('Sucesso', 'Mapa entregue!', 'success');
        } catch (error) { mostrarFeedback('Erro', error.message, 'danger'); }
    });

    tableBody.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return;
        const id = target.dataset.id;
        try {
            // Nota: Botão excluir removido do HTML mas mantido a lógica caso precise reativar
            if (target.classList.contains('btn-delete')) {
                // Lógica de exclusão (oculta no layout atual)
            } else if (target.classList.contains('btn-entregar')) {
                entregarModalLabel.textContent = `Entregar Mapa: ${target.dataset.identificador}`;
                document.getElementById('entregar_mapa_id').value = id;
                document.getElementById('entregar_data').valueAsDate = new Date();
                await carregarDirigentesNoModal();
                entregarModal.show();
            } else if (target.classList.contains('btn-resgatar')) {
                mostrarConfirmacao('Resgatar Mapa', 'Confirmar devolução forçada?', async () => {
                    try {
                        const data = { action: 'resgatar', mapa_id: id };
                        const response = await fetch(`${API_BASE_URL}/mapas_api.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                        if (!response.ok) throw new Error(await handleApiError(response));
                        carregarMapas(true);
                        mostrarFeedback('Sucesso', 'Mapa resgatado!', 'success');
                    } catch (err) { mostrarFeedback('Erro', err.message, 'danger'); }
                });
            } else if (target.classList.contains("btn-edit")) {
                prepararEdicao(id);
            } else if (target.classList.contains("btn-history")) {
                const identificador = target.dataset.identificador;
                await carregarHistoricoMapa(id, identificador);
            }
        } catch (error) { mostrarFeedback('Erro', error.message, 'danger'); }
    });

    // Iniciar
    carregarMapas();
});