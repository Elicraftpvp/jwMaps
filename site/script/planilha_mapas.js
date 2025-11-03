document.addEventListener('DOMContentLoaded', () => {
    carregarDadosDaPlanilha();
});

async function carregarDadosDaPlanilha() {
    const dirigentesBody = document.getElementById('tabela-dirigentes-body');
    const mapasDisponiveisContainer = document.getElementById('lista-mapas-disponiveis');

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
        renderizarMapasDisponiveis(todosOsMapas, mapasDisponiveisContainer);

        // Chama a função de inicialização do drag-and-drop
        if (typeof inicializarDragAndDrop === 'function') {
            inicializarDragAndDrop();
        }

    } catch (error) {
        console.error('Erro geral ao carregar dados da planilha:', error);
        dirigentesBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger p-4">Falha ao carregar os dados.</td></tr>`;
        mapasDisponiveisContainer.innerHTML = `<p class="text-center text-danger m-0">Falha ao carregar os mapas disponíveis.</p>`;
    }
}

function renderizarTabelaDirigentes(todosOsMapas, todosOsDirigentes, tbody) {
    const mapasPorDirigenteId = todosOsMapas
        .filter(mapa => mapa.dirigente_id)
        .reduce((acc, mapa) => {
            const id = mapa.dirigente_id;
            if (!acc[id]) acc[id] = [];
            acc[id].push({ id: mapa.id, identificador: mapa.identificador, regiao: mapa.regiao });
            return acc;
        }, {});

    const dirigentesComMapas = todosOsDirigentes.filter(d => mapasPorDirigenteId[d.id] && mapasPorDirigenteId[d.id].length > 0);

    tbody.innerHTML = '';

    if (dirigentesComMapas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center p-4">Nenhum dirigente com mapas atribuídos.</td></tr>';
        return;
    }

    dirigentesComMapas.forEach(dirigente => {
        const tr = document.createElement('tr');
        const mapasDoDirigente = mapasPorDirigenteId[dirigente.id] || [];
        
        let htmlCelulas = `<td>${dirigente.nome}</td>`;
        htmlCelulas += `<td colspan="4"><div class="mapas-dirigente-container" data-dirigente-id="${dirigente.id}">`;

        mapasDoDirigente.forEach(mapa => {
            const textoMapa = mapa.regiao ? `${mapa.identificador} - ${mapa.regiao}` : mapa.identificador;
            htmlCelulas += `<span class="badge mapa-atribuido-badge" data-mapa-id="${mapa.id}">${textoMapa}</span>`;
        });

        htmlCelulas += `</div></td>`;
        
        tr.innerHTML = htmlCelulas;
        tbody.appendChild(tr);
    });
}

function renderizarMapasDisponiveis(todosOsMapas, container) {
    const mapasDisponiveis = todosOsMapas.filter(mapa => !mapa.dirigente_id);

    container.innerHTML = '';
    
    if (mapasDisponiveis.length === 0) {
        container.innerHTML = '<p class="text-center m-0">Nenhum mapa disponível no momento.</p>';
        return;
    }

    mapasDisponiveis.forEach(mapa => {
        const span = document.createElement('span');
        
        // USA AS CLASSES 'badge' + 'mapa-disponivel-badge', EXATAMENTE COMO NO SEU EXEMPLO
        span.className = 'badge mapa-disponivel-badge mapa-item';
        span.setAttribute('data-mapa-id', mapa.id);
        
        span.textContent = mapa.regiao ? `${mapa.identificador} - ${mapa.regiao}` : mapa.identificador;
        
        container.appendChild(span);
    });
}