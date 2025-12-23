// site/script/planilha_mapas.js

document.addEventListener('DOMContentLoaded', () => {
    carregarDadosDaPlanilha();
});

async function carregarDadosDaPlanilha() {
    const dirigentesBody = document.getElementById('tabela-dirigentes-body');
    const gruposBody = document.getElementById('tabela-grupos-body');
    const mapasDisponiveisContainer = document.getElementById('lista-mapas-disponiveis');

    const URL_MAPAS = `${API_BASE_URL_CONTROLE}/mapas_api.php`;
    // show_inactive=false para trazer apenas quem pode receber mapas
    const URL_DIRIGENTES = `${API_BASE_URL_CONTROLE}/dirigentes_api.php?show_inactive=false`;
    const URL_GRUPOS = `${API_BASE_URL_CONTROLE}/grupos_api.php?show_inactive=false`;

    try {
        // Carrega tudo em paralelo
        const [mapasResponse, dirigentesResponse, gruposResponse] = await Promise.all([
            fetch(URL_MAPAS),
            fetch(URL_DIRIGENTES),
            fetch(URL_GRUPOS)
        ]);

        if (!mapasResponse.ok || !dirigentesResponse.ok || !gruposResponse.ok) {
            throw new Error('Erro na comunicação com a API');
        }

        const todosOsMapas = await mapasResponse.json();
        const todosOsDirigentes = await dirigentesResponse.json();
        const todosOsGrupos = await gruposResponse.json();

        // 1. Renderizar Dirigentes
        renderizarTabelaDirigentes(todosOsMapas, todosOsDirigentes, dirigentesBody);
        
        // 2. Renderizar Grupos (Nova função)
        renderizarTabelaGrupos(todosOsMapas, todosOsGrupos, gruposBody);
        
        // 3. Renderizar Disponíveis
        renderizarMapasDisponiveis(todosOsMapas, mapasDisponiveisContainer);

        // 4. Inicializar Drag and Drop
        if (typeof inicializarDragAndDrop === 'function') {
            inicializarDragAndDrop();
        }

    } catch (error) {
        console.error('Erro:', error);
        const msgErro = `<tr><td colspan="2" class="text-danger p-4 text-center">Erro ao carregar dados: ${error.message}</td></tr>`;
        dirigentesBody.innerHTML = msgErro;
        gruposBody.innerHTML = msgErro;
        mapasDisponiveisContainer.innerHTML = `<div class="text-danger p-3">Erro ao carregar mapas disponíveis.</div>`;
    }
}

function renderizarTabelaDirigentes(todosOsMapas, todosOsDirigentes, tbody) {
    // Agrupa mapas por dirigente_id
    const mapasPorDirigenteId = todosOsMapas.reduce((acc, mapa) => {
        if (mapa.dirigente_id) {
            if (!acc[mapa.dirigente_id]) acc[mapa.dirigente_id] = [];
            acc[mapa.dirigente_id].push(mapa);
        }
        return acc;
    }, {});

    tbody.innerHTML = '';

    if (todosOsDirigentes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">Nenhum dirigente ativo.</td></tr>';
        return;
    }

    todosOsDirigentes.forEach(dirigente => {
        const tr = document.createElement('tr');
        const mapasDoDirigente = mapasPorDirigenteId[dirigente.id] || [];
        
        let html = `<td><strong>${dirigente.nome}</strong></td>`;
        html += `<td>
            <div class="mapas-dirigente-container d-flex flex-wrap gap-1" data-dirigente-id="${dirigente.id}">`;

        mapasDoDirigente.forEach(mapa => {
            const label = mapa.tipo ? `${mapa.identificador} - ${mapa.tipo}` : mapa.identificador;
            // Badge Amarelo/Padrão para Dirigente
            html += `<span class="badge mapa-atribuido-badge mapa-item" data-mapa-id="${mapa.id}">${label}</span>`;
        });

        html += `</div></td>`;
        tr.innerHTML = html;
        tbody.appendChild(tr);
    });
}

function renderizarTabelaGrupos(todosOsMapas, todosOsGrupos, tbody) {
    // Agrupa mapas por grupo_id
    const mapasPorGrupoId = todosOsMapas.reduce((acc, mapa) => {
        if (mapa.grupo_id) {
            if (!acc[mapa.grupo_id]) acc[mapa.grupo_id] = [];
            acc[mapa.grupo_id].push(mapa);
        }
        return acc;
    }, {});

    tbody.innerHTML = '';

    if (todosOsGrupos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">Nenhum grupo ativo.</td></tr>';
        return;
    }

    todosOsGrupos.forEach(grupo => {
        const tr = document.createElement('tr');
        const mapasDoGrupo = mapasPorGrupoId[grupo.id] || [];
        
        // Nome do grupo com ícone
        let html = `<td><span class="text-info fw-bold"><i class="fas fa-users me-1"></i> ${grupo.nome}</span></td>`;
        
        // Container de Drop para Grupo
        html += `<td>
            <div class="mapas-grupo-container d-flex flex-wrap gap-1" data-grupo-id="${grupo.id}">`;

        mapasDoGrupo.forEach(mapa => {
            const label = mapa.tipo ? `${mapa.identificador} - ${mapa.tipo}` : mapa.identificador;
            // Badge Azul para Grupos
            html += `<span class="badge mapa-grupo-badge mapa-item" data-mapa-id="${mapa.id}">${label}</span>`;
        });

        html += `</div></td>`;
        tr.innerHTML = html;
        tbody.appendChild(tr);
    });
}

function renderizarMapasDisponiveis(todosOsMapas, container) {
    // Mapas disponíveis são aqueles que NÃO tem dirigente E NÃO tem grupo
    const mapasDisponiveis = todosOsMapas.filter(mapa => !mapa.dirigente_id && !mapa.grupo_id);

    container.innerHTML = '';
    // Atributo identificador para o Sortable saber que esta é a área de devolução
    container.setAttribute('id', 'lista-mapas-disponiveis');

    if (mapasDisponiveis.length === 0) {
        container.innerHTML = '<span class="text-muted small p-2">Nenhum mapa disponível no momento.</span>';
        return;
    }

    mapasDisponiveis.forEach(mapa => {
        const span = document.createElement('span');
        // Badge Verde para Disponível
        span.className = 'badge mapa-disponivel-badge mapa-item';
        span.setAttribute('data-mapa-id', mapa.id);
        span.textContent = mapa.tipo ? `${mapa.identificador} - ${mapa.tipo}` : mapa.identificador;
        container.appendChild(span);
    });
}