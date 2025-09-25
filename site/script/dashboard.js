// site/script/dashboard.js

document.addEventListener('DOMContentLoaded', async () => {
    const countDisponiveis = document.getElementById('count-disponiveis');
    const countEmUso = document.getElementById('count-em-uso');
    const countDirigentes = document.getElementById('count-dirigentes');
    const listaMapasRecentes = document.getElementById('lista-mapas-recentes');
    const listaHistoricoRecente = document.getElementById('lista-historico-recente');

    const carregarDados = async () => {
        try {
            const response = await fetch(`${API_BASE_URL}/dashboard_api.php`);
            if (!response.ok) throw new Error('Falha ao carregar dados do dashboard.');
            const data = await response.json();

            // Atualiza os cards
            countDisponiveis.textContent = data.stats.disponiveis;
            countEmUso.textContent = data.stats.em_uso;
            countDirigentes.textContent = data.stats.dirigentes;

            // Atualiza lista de mapas recentes
            listaMapasRecentes.innerHTML = '';
            if (data.recentes.length > 0) {
                data.recentes.forEach(mapa => {
                    const dataEntrega = new Date(mapa.data_entrega + 'T00:00:00').toLocaleDateString('pt-BR');
                    const item = `
                        <li class="list-group-item">
                            <strong>${mapa.identificador}</strong> com ${mapa.dirigente_nome}
                            <br><small class="text-muted">Entregue em: ${dataEntrega}</small>
                        </li>`;
                    listaMapasRecentes.innerHTML += item;
                });
            } else {
                listaMapasRecentes.innerHTML = '<li class="list-group-item text-center">Nenhum mapa em uso no momento.</li>';
            }
            
            // Atualiza lista de histórico recente
            listaHistoricoRecente.innerHTML = '';
            if (data.historico.length > 0) {
                data.historico.forEach(h => {
                    const dataDevolucao = new Date(h.data_devolucao + 'T00:00:00').toLocaleDateString('pt-BR');
                    const item = `
                        <li class="list-group-item">
                            <strong>${h.mapa_identificador}</strong> por ${h.dirigente_nome}
                            <br><small class="text-muted">Devolvido em: ${dataDevolucao} (${h.pessoas_faladas} pessoas)</small>
                        </li>`;
                    listaHistoricoRecente.innerHTML += item;
                });
            } else {
                listaHistoricoRecente.innerHTML = '<li class="list-group-item text-center">Nenhum mapa foi devolvido ainda.</li>';
            }


        } catch (error) {
            console.error('Erro:', error);
            const errorMsg = '<li class="list-group-item text-center text-danger">Erro ao carregar dados.</li>';
            [countDisponiveis, countEmUso, countDirigentes].forEach(el => el.textContent = '!');
            listaMapasRecentes.innerHTML = errorMsg;
            listaHistoricoRecente.innerHTML = errorMsg;
        }
    };

    carregarDados();
});