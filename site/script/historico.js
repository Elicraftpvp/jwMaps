// site/script/historico.js

document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('historico-table-body');
    const filtros = document.querySelectorAll('input[name="periodoFiltro"]');

    const carregarHistorico = async (periodo = '6meses') => {
        tableBody.innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary"></div><p class="mt-2">Carregando relatório...</p></td></tr>`;
        try {
            const response = await fetch(`${API_BASE_URL}/historico_api.php?periodo=${periodo}`);
            if (!response.ok) { throw new Error('Erro ao buscar dados do relatório.'); }
            const historico = await response.json();
            tableBody.innerHTML = '';
            if (historico.length === 0) { tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">Nenhum registro encontrado.</td></tr>`; return; }

            historico.forEach(item => {
                const dataEntrega = new Date(item.data_entrega + 'T00:00:00').toLocaleDateString('pt-BR');
                const dataDevolucao = new Date(item.data_devolucao + 'T00:00:00').toLocaleDateString('pt-BR');

                // NOVO: Processa o JSON para criar um tooltip com os detalhes
                let detalhesQuadras = 'Detalhes não disponíveis.';
                if (item.dados_quadras) {
                    try {
                        const quadras = JSON.parse(item.dados_quadras);
                        detalhesQuadras = quadras.map(q => `Quadra ${q.numero}: ${q.pessoas_faladas}`).join(' | ');
                    } catch (e) { /* falha silenciosa se o JSON for inválido */ }
                }

                // CORREÇÃO: Usa 'pessoas_faladas_total' e adiciona o 'title' para o tooltip
                const row = `
                    <tr>
                        <td><strong>${item.mapa_identificador}</strong></td>
                        <td>${item.dirigente_nome}</td>
                        <td>${dataEntrega}</td>
                        <td>${dataDevolucao}</td>
                        <td class="text-center fw-bold" title="${detalhesQuadras}" data-bs-toggle="tooltip">
                            ${item.pessoas_faladas_total}
                        </td>
                    </tr>
                `;
                tableBody.innerHTML += row;
            });
            
            // Ativa os novos tooltips do Bootstrap
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))

        } catch (error) {
            console.error('Falha ao carregar histórico:', error);
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Falha ao carregar o relatório.</td></tr>`;
        }
    };

    filtros.forEach(filtro => { filtro.addEventListener('change', (event) => { carregarHistorico(event.target.value); }); });
    carregarHistorico('6meses');
});