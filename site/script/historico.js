// site/script/historico.js
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('historico-table-body');
    const filtros = document.querySelectorAll('input[name="periodoFiltro"]');

    // Verificar se a API_BASE_URL está definida
    if (typeof API_BASE_URL === 'undefined') {
        console.error('API_BASE_URL não está definida');
        tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Erro de configuração: API_BASE_URL não definida.</td></tr>`;
        return;
    }

    const carregarHistorico = async (periodo = '6meses') => {
        tableBody.innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner-border"></div> Carregando...</td></tr>`;
        
        try {
            const response = await fetch(`${API_BASE_URL}/historico_api.php?periodo=${periodo}`);
            
            if (!response.ok) {
                throw new Error(`Erro ${response.status}: ${response.statusText}`);
            }
            
            const historico = await response.json();
            
            // Verificar se a resposta é um array
            if (!Array.isArray(historico)) {
                console.error('Resposta inválida:', historico);
                throw new Error('Formato de dados inválido');
            }
            
            tableBody.innerHTML = '';
            
            if (historico.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">Nenhum registro encontrado para o período selecionado.</td></tr>`;
                return;
            }

            historico.forEach(item => {
                // Formatar datas corretamente
                const dataEntrega = item.data_entrega 
                    ? new Date(item.data_entrega + 'T00:00:00').toLocaleDateString('pt-BR')
                    : 'N/A';
                    
                const dataDevolucao = item.data_devolucao 
                    ? new Date(item.data_devolucao + 'T00:00:00').toLocaleDateString('pt-BR')
                    : 'N/A';

                // Processar detalhes das quadras
                let detalhesQuadras = 'Detalhes não disponíveis.';
                if (item.dados_quadras) {
                    try {
                        const quadras = JSON.parse(item.dados_quadras);
                        if (Array.isArray(quadras) && quadras.length > 0) {
                            detalhesQuadras = quadras.map(q => 
                                `Quadra ${q.numero}: ${q.pessoas_faladas || 0} pessoas`
                            ).join('\n');
                        }
                    } catch (e) {
                        console.warn('Erro ao parsear dados_quadras:', e);
                    }
                }

                const row = `
                    <tr>
                        <td><strong>${item.mapa_identificador || 'N/A'}</strong></td>
                        <td>${item.dirigente_nome || 'N/A'}</td>
                        <td>${dataEntrega}</td>
                        <td>${dataDevolucao}</td>
                        <td class="text-center fw-bold" title="${detalhesQuadras.replace(/"/g, '&quot;')}" data-bs-toggle="tooltip">
                            ${item.pessoas_faladas_total || 0}
                        </td>
                    </tr>
                `;
                tableBody.innerHTML += row;
            });
            
            // Inicializar tooltips do Bootstrap
            if (typeof bootstrap !== 'undefined') {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
            }

        } catch (error) {
            console.error('Erro ao carregar histórico:', error);
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Falha ao carregar o relatório: ${error.message}</td></tr>`;
        }
    };

    // Adicionar event listeners aos filtros
    filtros.forEach(filtro => {
        filtro.addEventListener('change', (event) => {
            carregarHistorico(event.target.value);
        });
    });

    // Carregar histórico inicial
    carregarHistorico('6meses');
});