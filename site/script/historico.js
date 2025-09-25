// site/script/historico.js

document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('historico-table-body');
    const filtros = document.querySelectorAll('input[name="periodoFiltro"]');

    /**
     * Carrega os dados do histórico da API e preenche a tabela.
     * @param {string} periodo - O período a ser filtrado ('6meses' ou 'completo').
     */
    const carregarHistorico = async (periodo = '6meses') => {
        tableBody.innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary"></div><p class="mt-2">Carregando relatório...</p></td></tr>`;

        try {
            const response = await fetch(`${API_BASE_URL}/historico_api.php?periodo=${periodo}`);
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Erro ao buscar dados do relatório.');
            }
            const historico = await response.json();

            tableBody.innerHTML = ''; // Limpa a tabela

            if (historico.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">Nenhum registro encontrado para este período.</td></tr>`;
                return;
            }

            historico.forEach(item => {
                const dataEntrega = new Date(item.data_entrega + 'T00:00:00').toLocaleDateString('pt-BR');
                const dataDevolucao = new Date(item.data_devolucao + 'T00:00:00').toLocaleDateString('pt-BR');

                const row = `
                    <tr>
                        <td><strong>${item.mapa_identificador}</strong></td>
                        <td>${item.dirigente_nome}</td>
                        <td>${dataEntrega}</td>
                        <td>${dataDevolucao}</td>
                        <td class="text-center fw-bold">${item.pessoas_faladas}</td>
                    </tr>
                `;
                tableBody.innerHTML += row;
            });

        } catch (error) {
            console.error('Falha ao carregar histórico:', error);
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Falha ao carregar o relatório. Tente novamente.</td></tr>`;
        }
    };

    // Adiciona o listener para os botões de filtro
    filtros.forEach(filtro => {
        filtro.addEventListener('change', (event) => {
            carregarHistorico(event.target.value);
        });
    });

    // Carga inicial dos dados com o filtro padrão (últimos 6 meses)
    carregarHistorico('6meses');
});