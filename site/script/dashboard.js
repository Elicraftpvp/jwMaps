// site/script/dashboard.js
document.addEventListener('DOMContentLoaded', async () => {
    const countDisponiveis = document.getElementById('count-disponiveis');
    const countEmUso = document.getElementById('count-em-uso');
    const countDirigentes = document.getElementById('count-dirigentes');
    const listaMapasRecentes = document.getElementById('lista-mapas-recentes');
    const listaHistoricoRecente = document.getElementById('lista-historico-recente');

    // Verificar se API_BASE_URL está definida
    if (typeof API_BASE_URL === 'undefined') {
        console.error('API_BASE_URL não está definida');
        mostrarErro('Erro de configuração: API_BASE_URL não definida.');
        return;
    }

    const mostrarErro = (mensagem) => {
        const errorMsg = `<li class="list-group-item text-center text-danger">${mensagem}</li>`;
        [countDisponiveis, countEmUso, countDirigentes].forEach(el => el.textContent = '!');
        listaMapasRecentes.innerHTML = errorMsg;
        listaHistoricoRecente.innerHTML = errorMsg;
    };

    const carregarDados = async () => {
        try {
            // Mostrar loading
            listaMapasRecentes.innerHTML = '<li class="list-group-item text-center"><div class="spinner-border spinner-border-sm"></div> Carregando...</li>';
            listaHistoricoRecente.innerHTML = '<li class="list-group-item text-center"><div class="spinner-border spinner-border-sm"></div> Carregando...</li>';

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

            // Atualizar lista de mapas recentes
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
                listaMapasRecentes.innerHTML = '<li class="list-group-item text-center text-muted">Nenhum mapa em uso no momento.</li>';
            }
            
            // Atualizar histórico recente - CORREÇÃO: usando pessoas_faladas_total
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
                listaHistoricoRecente.innerHTML = '<li class="list-group-item text-center text-muted">Nenhum mapa foi devolvido ainda.</li>';
            }

        } catch (error) {
            console.error('Erro ao carregar dashboard:', error);
            mostrarErro(`Erro ao carregar dados: ${error.message}`);
        }
    };

    carregarDados();
});