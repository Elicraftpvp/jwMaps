// site/script/dashboard.js
document.addEventListener('DOMContentLoaded', async () => {
    const countDisponiveis = document.getElementById('count-disponiveis');
    const countEmUso = document.getElementById('count-em-uso');
    const countDirigentes = document.getElementById('count-dirigentes');
    const listaMapasRecentes = document.getElementById('lista-mapas-recentes');
    const listaHistoricoRecente = document.getElementById('lista-historico-recente');
    const listaMapasEmUso = document.getElementById('lista-mapas-em-uso'); // Novo elemento

    // Verificar se API_BASE_URL está definida
    if (typeof API_BASE_URL === 'undefined') {
        console.error('API_BASE_URL não está definida');
        mostrarErro('Erro de configuração: API_BASE_URL não definida.');
        return;
    }

    const mostrarErro = (mensagem) => {
        const errorMsg = `<li class="list-group-item text-center text-danger p-3">${mensagem}</li>`;
        [countDisponiveis, countEmUso, countDirigentes].forEach(el => el.textContent = '!');
        listaMapasRecentes.innerHTML = errorMsg;
        listaHistoricoRecente.innerHTML = errorMsg;
        listaMapasEmUso.innerHTML = errorMsg; // Exibir erro no novo card também
    };

    const carregarDados = async () => {
        try {
            // Mostrar loading
            const loadingMsg = '<li class="list-group-item text-center p-3"><div class="spinner-border spinner-border-sm"></div> Carregando...</li>';
            listaMapasRecentes.innerHTML = loadingMsg;
            listaHistoricoRecente.innerHTML = loadingMsg;
            listaMapasEmUso.innerHTML = loadingMsg; // Loading para o novo card

            const response = await fetch(`${API_BASE_URL}/dashboard_api.php`);
            
            if (!response.ok) {
                throw new Error(`Erro ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();

            // Verificar se a estrutura dos dados está correta
            if (!data.stats || !data.recentes || !data.historico) {
                throw new Error('Estrutura de dados inválida');
            }

            // Atualizar estatísticas
            countDisponiveis.textContent = data.stats.disponiveis || 0;
            countEmUso.textContent = data.stats.em_uso || 0;
            countDirigentes.textContent = data.stats.dirigentes || 0;

            // Atualizar lista de mapas recentes (Entregues)
            listaMapasRecentes.innerHTML = '';
            if (data.recentes.length > 0) {
                data.recentes.forEach(mapa => {
                    const dataEntrega = mapa.data_entrega 
                        ? new Date(mapa.data_entrega + 'T00:00:00').toLocaleDateString('pt-BR')
                        : 'Data não informada';
                    
                    const item = `
                        <li class="list-group-item">
                            <strong>${mapa.identificador || 'N/A'}</strong> com ${mapa.dirigente_nome || 'N/A'}
                            <br><small class="text-muted">Entregue em: ${dataEntrega}</small>
                        </li>`;
                    listaMapasRecentes.innerHTML += item;
                });
            } else {
                listaMapasRecentes.innerHTML = '<li class="list-group-item text-center text-muted p-3">Nenhum mapa em uso no momento.</li>';
            }
            
            // Atualizar histórico recente (Devolvidos)
            listaHistoricoRecente.innerHTML = '';
            if (data.historico.length > 0) {
                data.historico.forEach(h => {
                    const dataDevolucao = h.data_devolucao 
                        ? new Date(h.data_devolucao + 'T00:00:00').toLocaleDateString('pt-BR')
                        : 'Data não informada';
                    
                    const item = `
                        <li class="list-group-item">
                            <strong>${h.mapa_identificador || 'N/A'}</strong> por ${h.dirigente_nome || 'N/A'}
                            <br><small class="text-muted">Devolvido em: ${dataDevolucao} (${h.pessoas_faladas_total || 0} pessoas)</small>
                        </li>`;
                    listaHistoricoRecente.innerHTML += item;
                });
            } else {
                listaHistoricoRecente.innerHTML = '<li class="list-group-item text-center text-muted p-3">Nenhum mapa foi devolvido ainda.</li>';
            }

            // --- INÍCIO DA MODIFICAÇÃO ---
            // MODIFICADO: Atualizar lista de Mapas em Uso por mais de 30 dias
            listaMapasEmUso.innerHTML = '';
            const hoje = new Date();
            const trintaDiasEmMs = 30 * 24 * 60 * 60 * 1000; // 30 dias em milissegundos

            // Filtra a lista de mapas para incluir apenas aqueles entregues há mais de 30 dias
            const mapasAtrasados = data.recentes.filter(mapa => {
                if (!mapa.data_entrega) {
                    return false; // Ignora mapas que não têm data de entrega
                }
                const dataEntrega = new Date(mapa.data_entrega + 'T00:00:00');
                const diferencaEmMs = hoje.getTime() - dataEntrega.getTime();
                
                return diferencaEmMs > trintaDiasEmMs;
            });

            if (mapasAtrasados.length > 0) {
                mapasAtrasados.forEach(mapa => {
                    const dataEntregaFormatada = new Date(mapa.data_entrega + 'T00:00:00').toLocaleDateString('pt-BR');
                    
                    const item = `
                        <li class="list-group-item">
                            <strong>Mapa:</strong> ${mapa.identificador || 'N/A'}<br>
                            <strong>Dirigente:</strong> ${mapa.dirigente_nome || 'N/A'}<br>
                            <small class="text-muted">Entregue em: <strong class="text-danger">${dataEntregaFormatada}</strong></small>
                        </li>`;
                    listaMapasEmUso.innerHTML += item;
                });
            } else {
                listaMapasEmUso.innerHTML = '<li class="list-group-item text-center text-muted p-3">Nenhum mapa em uso há mais de 30 dias.</li>';
            }
            // --- FIM DA MODIFICAÇÃO ---

        } catch (error) {
            console.error('Erro ao carregar dashboard:', error);
            mostrarErro(`Erro ao carregar dados: ${error.message}`);
        }
    };

    carregarDados();
});