// site/script/historico_mapas_predio.js
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('historico-table-body');
    const filtros = document.querySelectorAll('input[name="periodoFiltro"]');

    if (typeof API_BASE_URL_CONTROLE === 'undefined') {
        console.error('API_BASE_URL_CONTROLE não está definida');
        tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Erro de configuração: Base URL não definida.</td></tr>`;
        return;
    }

    const carregarHistorico = async (periodo = '6meses') => {
        tableBody.innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner-border text-danger"></div> Carregando Histórico de Prédios...</td></tr>`;
        
        try {
            const response = await fetch(`${API_BASE_URL_CONTROLE}/historico_mapas_predio_api.php?periodo=${periodo}`);
            
            if (!response.ok) throw new Error(`Erro ${response.status}: ${response.statusText}`);
            
            const historico = await response.json();
            
            if (!Array.isArray(historico)) throw new Error('Formato de dados inválido');
            
            tableBody.innerHTML = '';
            
            if (historico.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">Nenhum registro encontrado para o período selecionado.</td></tr>`;
                return;
            }

            historico.forEach(item => {
                const dataEntrega = item.data_entrega 
                    ? new Date(item.data_entrega + 'T00:00:00').toLocaleDateString('pt-BR')
                    : 'N/A';
                    
                const dataDevolucao = item.data_devolucao 
                    ? new Date(item.data_devolucao + 'T00:00:00').toLocaleDateString('pt-BR')
                    : 'N/A';

                // Processar detalhes dos blocos
                let detalhes = 'Detalhes não disponíveis.';
                if (item.dados_blocos) {
                    try {
                        const blocos = JSON.parse(item.dados_blocos);
                        if (Array.isArray(blocos) && blocos.length > 0) {
                            detalhes = blocos.map(b => 
                                `Bloco ${b.numero}: ${b.pessoas_faladas || 0} pessoas`
                            ).join('\n');
                        }
                    } catch (e) {
                        console.warn('Erro ao parsear dados_blocos:', e);
                    }
                }

                const row = `
                    <tr>
                        <td><strong style="color: #E91E63;">${item.mapa_identificador || 'N/A'}</strong></td>
                        <td>
                            ${item.dirigente_nome ? `<i class="fas fa-user text-secondary me-1"></i> ${item.dirigente_nome}` : ''}
                            ${!item.dirigente_nome && item.grupo_nome ? `<i class="fas fa-users text-info me-1"></i> ${item.grupo_nome}` : ''}
                            ${!item.dirigente_nome && !item.grupo_nome ? 'N/A' : ''}
                        </td>
                        <td>${dataEntrega}</td>
                        <td>${dataDevolucao}</td>
                        <td class="text-center fw-bold" title="${detalhes.replace(/"/g, '&quot;')}" data-bs-toggle="tooltip" style="cursor: help;">
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

    filtros.forEach(filtro => {
        filtro.addEventListener('change', (event) => {
            carregarHistorico(event.target.value);
        });
    });

    carregarHistorico('6meses');
});
