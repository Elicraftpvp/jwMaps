// site/script/planilha_mapas.js

document.addEventListener('DOMContentLoaded', () => {
    carregarDadosDaPlanilha();
});

async function carregarDadosDaPlanilha() {
    const dirigentesBody = document.getElementById('tabela-dirigentes-body');
    const mapasDisponiveisContainer = document.getElementById('lista-mapas-disponiveis');

    const URL_MAPAS = `${API_BASE_URL_CONTROLE}/mapas_api.php`;
    const URL_DIRIGENTES = `${API_BASE_URL_CONTROLE}/dirigentes_api.php?show_inactive=false`;

    try {
        const [mapasResponse, dirigentesResponse] = await Promise.all([
            fetch(URL_MAPAS),
            fetch(URL_DIRIGENTES)
        ]);

        if (!mapasResponse.ok || !dirigentesResponse.ok) throw new Error('Erro na API');

        const todosOsMapas = await mapasResponse.json();
        const todosOsDirigentes = await dirigentesResponse.json();

        renderizarTabelaDirigentes(todosOsMapas, todosOsDirigentes, dirigentesBody);
        renderizarMapasDisponiveis(todosOsMapas, mapasDisponiveisContainer);

        if (typeof inicializarDragAndDrop === 'function') {
            inicializarDragAndDrop();
        }

    } catch (error) {
        console.error('Erro:', error);
        dirigentesBody.innerHTML = `<tr><td colspan="5" class="text-danger p-4">Erro ao carregar dados.</td></tr>`;
    }
}

function renderizarTabelaDirigentes(todosOsMapas, todosOsDirigentes, tbody) {
    const mapasPorDirigenteId = todosOsMapas.reduce((acc, mapa) => {
        if (mapa.dirigente_id) {
            if (!acc[mapa.dirigente_id]) acc[mapa.dirigente_id] = [];
            acc[mapa.dirigente_id].push(mapa);
        }
        return acc;
    }, {});

    tbody.innerHTML = '';

    todosOsDirigentes.forEach(dirigente => {
        const tr = document.createElement('tr');
        const mapasDoDirigente = mapasPorDirigenteId[dirigente.id] || [];
        
        let html = `<td><strong>${dirigente.nome}</strong></td>`;
        html += `<td>
            <div class="mapas-dirigente-container d-flex flex-wrap gap-1" data-dirigente-id="${dirigente.id}" style="min-height:40px;">`;

        mapasDoDirigente.forEach(mapa => {
            const label = mapa.tipo ? `${mapa.identificador} - ${mapa.tipo}` : mapa.identificador;
            // USANDO A CLASSE CORRETA: mapa-atribuido-badge
            html += `<span class="badge mapa-atribuido-badge mapa-item" data-mapa-id="${mapa.id}">${label}</span>`;
        });

        html += `</div></td>`;
        tr.innerHTML = html;
        tbody.appendChild(tr);
    });
}

function renderizarMapasDisponiveis(todosOsMapas, container) {
    const mapasDisponiveis = todosOsMapas.filter(mapa => !mapa.dirigente_id);

    container.innerHTML = '';
    container.setAttribute('data-zona-tipo', 'disponiveis');

    mapasDisponiveis.forEach(mapa => {
        const span = document.createElement('span');
        // USANDO A CLASSE CORRETA: mapa-disponivel-badge
        span.className = 'badge mapa-disponivel-badge mapa-item';
        span.setAttribute('data-mapa-id', mapa.id);
        span.textContent = mapa.tipo ? `${mapa.identificador} - ${mapa.tipo}` : mapa.identificador;
        container.appendChild(span);
    });
}