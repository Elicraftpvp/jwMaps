// site/script/dashboard.js
document.addEventListener('DOMContentLoaded', () => {

    // --- LÓGICA ANTIGA PARA OS CARDS PRINCIPAIS (mantida) ---
    const carregarStatsERecentes = async () => {
        const countDisponiveis = document.getElementById('count-disponiveis');
        const countEmUso = document.getElementById('count-em-uso');
        const countDirigentes = document.getElementById('count-dirigentes');
        const listaMapasRecentes = document.getElementById('lista-mapas-recentes');
        const listaHistoricoRecente = document.getElementById('lista-historico-recente');

        if (!countDisponiveis || !listaMapasRecentes || !listaHistoricoRecente) {
            return;
        }

        try {
            const response = await fetch(`${API_BASE_URL}/dashboard_api.php`);
            if (!response.ok) throw new Error(`Erro na API principal: ${response.statusText}`);
            const data = await response.json();

            if (!data.stats || !data.recentes || !data.historico) {
                throw new Error('Estrutura de dados da API principal inválida');
            }

            countDisponiveis.textContent = data.stats.disponiveis || 0;
            countEmUso.textContent = data.stats.em_uso || 0;
            countDirigentes.textContent = data.stats.dirigentes || 0;

            listaMapasRecentes.innerHTML = '';
            if (data.recentes.length > 0) {
                data.recentes.forEach(mapa => {
                    const dataEntrega = mapa.data_entrega ? new Date(mapa.data_entrega + 'T00:00:00').toLocaleDateString('pt-BR') : 'N/A';
                    listaMapasRecentes.innerHTML += `<li class="list-group-item"><strong>${mapa.identificador || 'N/A'}</strong> com ${mapa.dirigente_nome || 'N/A'}<br><small class="text-muted">Entregue em: ${dataEntrega}</small></li>`;
                });
            } else {
                listaMapasRecentes.innerHTML = '<li class="list-group-item text-center text-muted p-3">Nenhum mapa em uso no momento.</li>';
            }

            listaHistoricoRecente.innerHTML = '';
            if (data.historico.length > 0) {
                data.historico.forEach(h => {
                    const dataDevolucao = h.data_devolucao ? new Date(h.data_devolucao + 'T00:00:00').toLocaleDateString('pt-BR') : 'N/A';
                    listaHistoricoRecente.innerHTML += `<li class="list-group-item"><strong>${h.mapa_identificador || 'N/A'}</strong> por ${h.dirigente_nome || 'N/A'}<br><small class="text-muted">Devolvido em: ${dataDevolucao} (${h.pessoas_faladas_total || 0} pessoas)</small></li>`;
                });
            } else {
                listaHistoricoRecente.innerHTML = '<li class="list-group-item text-center text-muted p-3">Nenhum mapa foi devolvido ainda.</li>';
            }
        } catch (error) {
            console.error('Erro ao carregar stats e recentes:', error);
            const errorMsg = `<li class="list-group-item text-center text-danger p-3">Erro ao carregar dados.</li>`;
            [countDisponiveis, countEmUso, countDirigentes].forEach(el => el.textContent = '!');
            listaMapasRecentes.innerHTML = errorMsg;
            listaHistoricoRecente.innerHTML = errorMsg;
        }
    };

    // --- LÓGICA NOVA PARA "MAPAS EM USO HÁ MAIS DE 30 DIAS" ---

    const mapasAgrupadosPorDirigente = {};
    let totalAtrasados = 0;

    // --- MODIFICAÇÃO 1: Renderização ---
    // A renderização agora exibirá a quantidade de dias.
    const renderizarMapasAgrupados = (elementoLista) => {
        elementoLista.innerHTML = '';
        
        if (Object.keys(mapasAgrupadosPorDirigente).length === 0) {
            elementoLista.innerHTML = '<li class="list-group-item text-center text-muted p-3">Nenhum mapa em uso há mais de 30 dias.</li>';
            return;
        }

        for (const dirigente in mapasAgrupadosPorDirigente) {
            const mapas = mapasAgrupadosPorDirigente[dirigente];
            
            const listaDeMapasHtml = mapas.map(mapa => 
                // Alterado para mostrar a quantidade de dias
                `<li><small><strong>Mapa ${mapa.id}:</strong> com o dirigente há <strong class="text-danger">${mapa.dias} dias</strong></small></li>`
            ).join('');

            const itemHtml = `
                <li class="list-group-item">
                    <strong>${dirigente}</strong> (${mapas.length} mapa${mapas.length > 1 ? 's' : ''})
                    <ul class="list-unstyled mt-2 mb-0 pl-3">
                        ${listaDeMapasHtml}
                    </ul>
                </li>
            `;
            elementoLista.innerHTML += itemHtml;
        }
    };

    const carregarMapasAtrasados = async (page = 1) => {
        const listaMapasEmUso = document.getElementById('lista-mapas-em-uso');
        
        if (!listaMapasEmUso) {
            console.warn("Elemento 'lista-mapas-em-uso' não encontrado nesta página.");
            return;
        }

        if (page === 1) {
            listaMapasEmUso.innerHTML = '<li class="list-group-item text-center p-3"><div class="spinner-border spinner-border-sm"></div> Carregando mapas em uso...</li>';
        }

        try {
            const response = await fetch(`${API_BASE_URL}/mapas_em_uso_api.php?page=${page}&limit=5`);
            if (!response.ok) throw new Error(`Erro na API de mapas em uso: ${response.statusText}`);
            
            const result = await response.json();
            const mapasDaPagina = result.data;
            
            // --- MODIFICAÇÃO 2: Lógica de Cálculo ---
            // Usa a data do servidor vinda da API para garantir precisão
            const dataServidor = new Date(result.serverDate + 'T00:00:00');
            const umDiaEmMs = 24 * 60 * 60 * 1000;
            let encontrouAtrasadoNestaPagina = false;

            mapasDaPagina.forEach(mapa => {
                if (!mapa.data_entrega) return;

                const dataEntrega = new Date(mapa.data_entrega + 'T00:00:00');
                const diferencaEmMs = dataServidor.getTime() - dataEntrega.getTime();
                const diasDeDiferenca = Math.floor(diferencaEmMs / umDiaEmMs); // Calcula os dias

                // Filtra para pegar apenas os com 30 dias ou mais
                if (diasDeDiferenca >= 30) {
                    encontrouAtrasadoNestaPagina = true;
                    totalAtrasados++;
                    const dirigente = mapa.dirigente_nome || 'Dirigente não identificado';

                    if (!mapasAgrupadosPorDirigente[dirigente]) {
                        mapasAgrupadosPorDirigente[dirigente] = [];
                    }

                    // Armazena a quantidade de dias em vez da data formatada
                    mapasAgrupadosPorDirigente[dirigente].push({
                        id: mapa.identificador,
                        dias: diasDeDiferenca 
                    });
                }
            });

            if (encontrouAtrasadoNestaPagina || (page === 1 && totalAtrasados === 0)) {
                 renderizarMapasAgrupados(listaMapasEmUso);
            }
           
            if (result.page < result.totalPages) {
                setTimeout(() => carregarMapasAtrasados(page + 1), 200);
            } else {
                 if (totalAtrasados === 0) {
                    renderizarMapasAgrupados(listaMapasEmUso);
                 }
            }

        } catch (error) {
            console.error('Erro ao carregar mapas em uso por mais de 30 dias:', error);
            if (listaMapasEmUso) {
                listaMapasEmUso.innerHTML = `<li class="list-group-item text-center text-danger p-3">Erro ao carregar dados: ${error.message}</li>`;
            }
        }
    };

    // --- INICIA O CARREGAMENTO DOS DADOS ---
    if (typeof API_BASE_URL === 'undefined') {
        console.error('API_BASE_URL não está definida');
        return;
    }
    
    carregarStatsERecentes();
    carregarMapasAtrasados();
});