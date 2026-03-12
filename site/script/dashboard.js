// site/script/dashboard.js
document.addEventListener('DOMContentLoaded', () => {
    const PERM_DIRIGENTE = 1;
    const PERM_ADMIN = 2;
    const PERM_CAMPANHA = 16;
    const PERM_PUBLICADOR = 8;
    
    const adminView = document.getElementById('admin-view');
    const dirigenteView = document.getElementById('dirigente-view');
    
    const countDisponiveis = document.getElementById('count-disponiveis');
    const countEmUso = document.getElementById('count-em-uso');
    const countDirigentes = document.getElementById('count-dirigentes');

    let mapasAgrupadosPorDirigente = {};
    let totalAtrasados = 0;

    const initDashboard = () => {
        const user = JSON.parse(sessionStorage.getItem('user'));
        
        if (!user || typeof user.permissoes === 'undefined') {
            console.error('Usuário não logado ou sem permissões.');
            return;
        }

        const userPermissoes = parseInt(user.permissoes, 10);

        carregarStatsGerais();

        if ((userPermissoes & PERM_ADMIN) === PERM_ADMIN || (userPermissoes & PERM_CAMPANHA) === PERM_CAMPANHA) {
            mostrarVisaoAdmin();
        } 
        else if ((userPermissoes & PERM_DIRIGENTE) === PERM_DIRIGENTE || (userPermissoes & PERM_PUBLICADOR) === PERM_PUBLICADOR) {
            mostrarVisaoDirigente();
        }
        else {
            mostrarVisaoAdmin();
        }
    };

    const carregarStatsGerais = async () => {
        try {
            const response = await fetch(`${API_BASE_URL}/dashboard_api.php`);
            if (!response.ok) throw new Error('Falha ao buscar dados do dashboard.');
            const data = await response.json();

            countDisponiveis.textContent = data.stats.disponiveis || 0;
            countEmUso.textContent = data.stats.em_uso || 0;
            countDirigentes.textContent = data.stats.dirigentes || 0;

        } catch (error) {
            console.error("Erro ao carregar estatísticas:", error);[countDisponiveis, countEmUso, countDirigentes].forEach(el => el.textContent = '!');
        }
    };

    const mostrarVisaoAdmin = () => {
        adminView.classList.remove('d-none');
        dirigenteView.classList.add('d-none');
        carregarListasAdmin();

        mapasAgrupadosPorDirigente = {};
        totalAtrasados = 0;
        
        const listaMapasEmUso = document.getElementById('lista-mapas-em-uso');
        listaMapasEmUso.innerHTML = '<li class="list-group-item text-center"><div class="spinner-border spinner-border-sm"></div> Carregando mapas em uso...</li>';
        
        carregarMapasAtrasadosAdmin(1); 
    };

    const carregarListasAdmin = async () => {
        const listaMapasRecentes = document.getElementById('lista-mapas-recentes');
        const listaHistoricoRecente = document.getElementById('lista-historico-recente');
        listaMapasRecentes.innerHTML = '<li class="list-group-item text-center"><div class="spinner-border spinner-border-sm"></div></li>';
        listaHistoricoRecente.innerHTML = '<li class="list-group-item text-center"><div class="spinner-border spinner-border-sm"></div></li>';

        try {
            const response = await fetch(`${API_BASE_URL}/dashboard_api.php`);
            const data = await response.json();

            listaMapasRecentes.innerHTML = '';
            if (data.recentes && data.recentes.length > 0) {
                data.recentes.forEach(mapa => {
                    const dataEntrega = new Date(mapa.data_entrega + 'T00:00:00').toLocaleDateString('pt-BR');
                    // Verifica se é mapa de grupo ou individual
                    const responsavel = mapa.grupo_nome ? `<span class="badge bg-info text-dark">Grupo</span> ${mapa.grupo_nome}` : mapa.dirigente_nome;
                    
                    listaMapasRecentes.innerHTML += `<li class="list-group-item"><strong>${mapa.identificador}</strong> com ${responsavel}<br><small class="text-muted">Entregue em: ${dataEntrega}</small></li>`;
                });
            } else {
                listaMapasRecentes.innerHTML = '<li class="list-group-item text-muted text-center">Nenhum mapa entregue recentemente.</li>';
            }

            listaHistoricoRecente.innerHTML = '';
             if (data.historico && data.historico.length > 0) {
                data.historico.forEach(h => {
                    const dataDevolucao = new Date(h.data_devolucao + 'T00:00:00').toLocaleDateString('pt-BR');
                    listaHistoricoRecente.innerHTML += `<li class="list-group-item"><strong>${h.mapa_identificador}</strong> por ${h.dirigente_nome}<br><small class="text-muted">Devolvido em: ${dataDevolucao}</small></li>`;
                });
            } else {
                listaHistoricoRecente.innerHTML = '<li class="list-group-item text-muted text-center">Nenhum histórico de devolução.</li>';
            }
        } catch (error) {
            console.error("Erro ao carregar listas de admin:", error);
            listaMapasRecentes.innerHTML = '<li class="list-group-item text-danger text-center">Erro ao carregar.</li>';
            listaHistoricoRecente.innerHTML = '<li class="list-group-item text-danger text-center">Erro ao carregar.</li>';
        }
    };
    
    const renderizarMapasAgrupados = (elementoLista) => {
        elementoLista.innerHTML = '';
        
        if (Object.keys(mapasAgrupadosPorDirigente).length === 0) {
            elementoLista.innerHTML = '<li class="list-group-item text-center text-muted p-3">Nenhum mapa em uso há mais de 30 dias.</li>';
            return;
        }

        for (const dirigente in mapasAgrupadosPorDirigente) {
            const mapas = mapasAgrupadosPorDirigente[dirigente];
            
            const listaDeMapasHtml = mapas.map(mapa => 
                `<li>
                    <small>
                        Mapa ${mapa.identificador}: em uso há <strong class="text-danger">${mapa.dias} dias</strong>
                    </small>
                 </li>`
            ).join('');

            const itemHtml = `
                <li class="list-group-item">
                    <strong>${dirigente}</strong> (${mapas.length} mapa${mapas.length > 1 ? 's' : ''})
                    <ul class="list-unstyled mt-2 mb-0 pl-3" style="padding-left: 15px; border-left: 2px solid #eee;">
                        ${listaDeMapasHtml}
                    </ul>
                </li>
            `;
            elementoLista.innerHTML += itemHtml;
        }
    };

    const carregarMapasAtrasadosAdmin = async (page = 1) => {
        const listaMapasEmUso = document.getElementById('lista-mapas-em-uso');
        
        try {
            const response = await fetch(`${API_BASE_URL}/mapas_em_uso_api.php?page=${page}&limit=5`);
            if (!response.ok) throw new Error('API de mapas em uso falhou.');
            const result = await response.json();

            if (page === 1) {
                listaMapasEmUso.innerHTML = '';
            }

            const mapasDaPagina = result.data ||[];
            
            const dataServidor = new Date(result.serverDate + 'T00:00:00');
            const umDiaEmMs = 24 * 60 * 60 * 1000;
            let encontrouAtrasadoNestaPagina = false;

            mapasDaPagina.forEach(mapa => {
                if (!mapa.data_entrega) return;

                const dataEntrega = new Date(mapa.data_entrega + 'T00:00:00');
                const diferencaEmMs = dataServidor.getTime() - dataEntrega.getTime();
                const diasDeDiferenca = Math.floor(diferencaEmMs / umDiaEmMs);

                if (diasDeDiferenca >= 30) {
                    encontrouAtrasadoNestaPagina = true;
                    totalAtrasados++;
                    // Se o mapa tiver grupo, mostra o nome do grupo, senão o dirigente
                    const dirigente = mapa.grupo_nome ? `[Grupo] ${mapa.grupo_nome}` : (mapa.dirigente_nome || 'Desconhecido');

                    if (!mapasAgrupadosPorDirigente[dirigente]) {
                        mapasAgrupadosPorDirigente[dirigente] = [];
                    }

                    mapasAgrupadosPorDirigente[dirigente].push({
                        identificador: mapa.identificador,
                        dias: diasDeDiferenca 
                    });
                }
            });

            if (encontrouAtrasadoNestaPagina || (page === 1 && totalAtrasados === 0)) {
                renderizarMapasAgrupados(listaMapasEmUso);
            }

            if (result.page < result.totalPages) {
                setTimeout(() => carregarMapasAtrasadosAdmin(result.page + 1), 100);
            } else {
                if (totalAtrasados === 0) {
                    renderizarMapasAgrupados(listaMapasEmUso);
                }
            }

        } catch (error) {
            console.error("Erro ao carregar mapas atrasados:", error);
            listaMapasEmUso.innerHTML = '<li class="list-group-item text-danger text-center">Erro ao carregar mapas em uso.</li>';
        }
    };

    const mostrarVisaoDirigente = () => {
        adminView.classList.add('d-none');
        dirigenteView.classList.remove('d-none');
        carregarMapasDirigente();
    };

    const carregarMapasDirigente = async () => {
        const listaMeusMapas = document.getElementById('lista-meus-mapas');
        listaMeusMapas.innerHTML = '<li class="list-group-item text-center"><div class="spinner-border spinner-border-sm"></div></li>';
        
        try {
            const response = await fetch(`${API_BASE_URL}/dashboard_api.php`);
            const data = await response.json();

            listaMeusMapas.innerHTML = '';
            if (data.meus_mapas && data.meus_mapas.length > 0) {
                data.meus_mapas.forEach(mapa => {
                    let corBadge = 'bg-success';
                    if (mapa.dias_comigo >= 30) corBadge = 'bg-warning';
                    if (mapa.dias_comigo >= 60) corBadge = 'bg-danger';

                    // Identifica se é do Grupo do dirigente
                    const nomeExibicao = mapa.grupo_nome ? `Mapa ${mapa.identificador} <span class="badge bg-info text-dark ms-1" style="font-size: 0.7em;">Grupo ${mapa.grupo_nome}</span>` : `Mapa ${mapa.identificador}`;

                    listaMeusMapas.innerHTML += `
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <strong>${nomeExibicao}</strong>
                                <br>
                                <small class="text-muted">Entregue em: ${new Date(mapa.data_entrega + 'T00:00:00').toLocaleDateString('pt-BR')}</small>
                            </span>
                            <span class="badge ${corBadge} rounded-pill">${mapa.dias_comigo} dias comigo</span>
                        </li>
                    `;
                });
            } else {
                listaMeusMapas.innerHTML = '<li class="list-group-item text-muted text-center">Você não tem nenhum mapa em mãos no momento.</li>';
            }
        } catch (error) {
            console.error("Erro ao carregar mapas do dirigente:", error);
            listaMeusMapas.innerHTML = '<li class="list-group-item text-danger text-center">Erro ao carregar seus mapas.</li>';
        }
    };

    initDashboard();
});