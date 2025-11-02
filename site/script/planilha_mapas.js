document.addEventListener('DOMContentLoaded', () => {
    carregarDadosDaPlanilha();
});

async function carregarDadosDaPlanilha() {
    const dirigentesBody = document.getElementById('tabela-dirigentes-body');
    const disponiveisBody = document.getElementById('tabela-disponiveis-body');

    const URL_MAPAS = '/jwMaps/site/backend/mapas_api.php';
    const URL_DIRIGENTES = '/jwMaps/site/backend/dirigentes_api.php?show_inactive=false';

    try {
        const [mapasResponse, dirigentesResponse] = await Promise.all([
            fetch(URL_MAPAS),
            fetch(URL_DIRIGENTES)
        ]);

        if (!mapasResponse.ok || !dirigentesResponse.ok) {
            throw new Error(`Falha na comunicação com a API.`);
        }

        const todosOsMapas = await mapasResponse.json();
        const todosOsDirigentes = await dirigentesResponse.json();

        renderizarTabelaDirigentes(todosOsMapas, todosOsDirigentes, dirigentesBody);
        renderizarTabelaDisponiveis(todosOsMapas, disponiveisBody);

    } catch (error) {
        console.error('Erro geral ao carregar dados da planilha:', error);
        dirigentesBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger p-4">Falha ao carregar os dados.</td></tr>`;
        disponiveisBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger p-4">Falha ao carregar os dados.</td></tr>`;
    }
}

function renderizarTabelaDirigentes(todosOsMapas, todosOsDirigentes, tbody) {
    const mapasPorDirigenteId = todosOsMapas
        .filter(mapa => mapa.dirigente_id)
        .reduce((acc, mapa) => {
            const id = mapa.dirigente_id;
            if (!acc[id]) acc[id] = [];
            acc[id].push(mapa.identificador);
            return acc;
        }, {});

    // === LÓGICA PRINCIPAL: FILTRAR DIRIGENTES QUE TÊM MAPAS ===
    const dirigentesComMapas = todosOsDirigentes.filter(d => mapasPorDirigenteId[d.id] && mapasPorDirigenteId[d.id].length > 0);

    tbody.innerHTML = ''; // Limpa o "carregando"

    if (dirigentesComMapas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center p-4">Nenhum dirigente com mapas atribuídos no momento.</td></tr>';
        return;
    }

    dirigentesComMapas.forEach(dirigente => {
        const tr = document.createElement('tr');
        const mapasDoDirigente = mapasPorDirigenteId[dirigente.id] || [];
        
        // Coluna "Quando usar" (baseado no exemplo da imagem)
        let quandoUsarTexto = 'Na sua saída';
        if (['Janiele', 'Cleyton'].includes(dirigente.nome)) {
            quandoUsarTexto = 'Mapa pessoal';
        }

        let htmlCelulas = `<td>${quandoUsarTexto}</td>`;
        htmlCelulas += `<td class="text-start fw-medium">${dirigente.nome}</td>`;

        // Adiciona 5 colunas de mapa para manter a estrutura da planilha
        for (let i = 0; i < 5; i++) {
            const mapa = mapasDoDirigente[i];
            if (mapa) {
                const classeDestaque = mapa === 'Mapa 30' ? 'mapa-destaque' : '';
                htmlCelulas += `<td class="${classeDestaque}">${mapa}</td>`;
            } else {
                htmlCelulas += '<td></td>'; // Célula vazia
            }
        }
        
        tr.innerHTML = htmlCelulas;
        tbody.appendChild(tr);
    });
}

function renderizarTabelaDisponiveis(todosOsMapas, tbody) {
    const mapasDisponiveis = todosOsMapas
        .filter(mapa => !mapa.dirigente_id)
        .map(mapa => mapa.identificador); // Pega apenas os nomes

    tbody.innerHTML = ''; // Limpa o "carregando"
    
    if (mapasDisponiveis.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center p-4">Nenhum mapa disponível no momento.</td></tr>';
        return;
    }

    const COLS = 4; // 4 mapas por linha, como no exemplo
    const totalRows = Math.ceil(mapasDisponiveis.length / COLS);

    for (let i = 0; i < totalRows; i++) {
        const tr = document.createElement('tr');
        let htmlCelulas = '';

        // Adiciona a célula "Quando usar" apenas na primeira linha, com rowspan
        if (i === 0) {
            const textoQuandoUsar = "Sempre que possível, por exemplo, no último domingo do mês";
            htmlCelulas += `<td class="quando-usar-disponiveis" rowspan="${totalRows}">${textoQuandoUsar}</td>`;
        }

        // Adiciona as células dos mapas para a linha atual
        for (let j = 0; j < COLS; j++) {
            const mapaIndex = i * COLS + j;
            if (mapaIndex < mapasDisponiveis.length) {
                htmlCelulas += `<td>${mapasDisponiveis[mapaIndex]}</td>`;
            } else {
                htmlCelulas += '<td></td>'; // Células vazias para completar a linha
            }
        }
        
        tr.innerHTML = htmlCelulas;
        tbody.appendChild(tr);
    }
}