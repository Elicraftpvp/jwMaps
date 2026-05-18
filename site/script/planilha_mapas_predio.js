// site/script/planilha_mapas_predio.js

document.addEventListener('DOMContentLoaded', () => {
    carregarDadosDaPlanilha();
});

async function carregarDadosDaPlanilha() {
    const dirigentesBody = document.getElementById('tabela-dirigentes-body');
    const mapasDisponiveisContainer = document.getElementById('lista-mapas-disponiveis');

    if (typeof API_BASE_URL_CONTROLE === 'undefined') {
        console.error('API_BASE_URL_CONTROLE não está definida');
        return;
    }

    const URL_MAPAS = `${API_BASE_URL_CONTROLE}/mapas_predio_api.php`;
    const URL_DIRIGENTES = `${API_BASE_URL_CONTROLE}/dirigentes_api.php?show_inactive=false`;

    try {
        const [mapasResponse, dirigentesResponse] = await Promise.all([
            fetch(URL_MAPAS),
            fetch(URL_DIRIGENTES)
        ]);

        if (!mapasResponse.ok || !dirigentesResponse.ok) {
            throw new Error('Erro na comunicação com a API');
        }

        const todosOsMapas = await mapasResponse.json();
        const todosOsDirigentes = await dirigentesResponse.json();

        renderizarTabelaDirigentes(todosOsMapas, todosOsDirigentes, dirigentesBody);
        renderizarMapasDisponiveis(todosOsMapas, mapasDisponiveisContainer);

        if (typeof inicializarDragAndDrop === 'function') {
            inicializarDragAndDrop();
        }

        // 5. Inicializar Tooltip de Hover
        inicializarTooltipHistorico();

    } catch (error) {
        console.error('Erro:', error);
        const msgErro = `<tr><td colspan="2" class="text-danger p-4 text-center">Erro ao carregar dados: ${error.message}</td></tr>`;
        dirigentesBody.innerHTML = msgErro;
        mapasDisponiveisContainer.innerHTML = `<div class="text-danger p-3">Erro ao carregar mapas de disponíveis.</div>`;
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
            const label = mapa.identificador;
            const historicoAttr = mapa.historico ? `data-historico='${JSON.stringify(mapa.historico).replace(/'/g, "&apos;")}'` : "data-historico='[]'";
            // Badge Rosa para Prédios
            html += `<span class="badge mapa-predio-badge mapa-item" data-mapa-id="${mapa.id}" ${historicoAttr}>${label}</span>`;
        });

        html += `</div></td>`;
        tr.innerHTML = html;
        tbody.appendChild(tr);
    });
}

function renderizarMapasDisponiveis(todosOsMapas, container) {
    const mapasDisponiveis = todosOsMapas.filter(mapa => !mapa.dirigente_id);

    container.innerHTML = '';
    container.setAttribute('id', 'lista-mapas-disponiveis');

    if (mapasDisponiveis.length === 0) {
        container.innerHTML = '<span class="text-muted small p-2">Nenhum mapa disponível no momento.</span>';
        return;
    }

    mapasDisponiveis.forEach(mapa => {
        const span = document.createElement('span');
        span.className = 'badge mapa-disponivel-badge mapa-item';
        span.setAttribute('data-mapa-id', mapa.id);
        if (mapa.historico) {
            span.setAttribute('data-historico', JSON.stringify(mapa.historico));
        } else {
            span.setAttribute('data-historico', '[]');
        }
        span.textContent = mapa.identificador;
        container.appendChild(span);
    });
}

// ==========================================
// LÓGICA DO TOOLTIP DE HISTÓRICO FLUTUANTE
// ==========================================
function inicializarTooltipHistorico() {
    let tooltip = document.getElementById('mapa-historico-tooltip');
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.id = 'mapa-historico-tooltip';
        tooltip.className = 'mapa-historico-tooltip';
        document.body.appendChild(tooltip);
    }

    document.body.addEventListener('mouseover', function(e) {
        const target = e.target.closest('.mapa-item');
        if (!target) return;

        const historicoStr = target.getAttribute('data-historico');
        if (!historicoStr) return;

        let historico = [];
        try {
            historico = JSON.parse(historicoStr);
        } catch (err) {
            return;
        }

        const compStyle = window.getComputedStyle(target);
        const bgColor = compStyle.backgroundColor;
        const borderColor = compStyle.borderColor;
        
        tooltip.style.borderColor = borderColor;
        tooltip.style.backgroundColor = bgColor;

        let html = `<div class="tooltip-header" style="border-bottom-color: ${borderColor}">Últimos Usos</div>`;
        
        if (historico.length === 0) {
            html += `<div class="tooltip-empty">Nenhum histórico recente</div>`;
        } else {
            historico.forEach(h => {
                html += `
                    <div class="tooltip-item">
                        <span class="tooltip-date">${h.data}</span>
                        <span class="tooltip-name text-truncate" style="max-width: 120px;" title="${h.nome}">${h.nome}</span>
                    </div>
                `;
            });
        }
        tooltip.innerHTML = html;

        tooltip.style.visibility = 'hidden';
        tooltip.classList.add('show');

        const rect = target.getBoundingClientRect();
        let topPos = rect.top + window.scrollY - tooltip.offsetHeight - 8;
        let leftPos = rect.left + window.scrollX + (rect.width / 2) - (tooltip.offsetWidth / 2);

        if (topPos < window.scrollY) {
            topPos = rect.bottom + window.scrollY + 8;
        }
        
        if (leftPos < 0) leftPos = 10;
        if (leftPos + tooltip.offsetWidth > window.innerWidth) {
            leftPos = window.innerWidth - tooltip.offsetWidth - 10;
        }

        tooltip.style.top = `${topPos}px`;
        tooltip.style.left = `${leftPos}px`;
        tooltip.style.visibility = 'visible';
    });

    document.body.addEventListener('mouseout', function(e) {
        const target = e.target.closest('.mapa-item');
        if (!target) return;
        
        if (e.relatedTarget && e.relatedTarget.closest('#mapa-historico-tooltip')) {
            return;
        }
        
        tooltip.classList.remove('show');
    });

    tooltip.addEventListener('mouseleave', function() {
        tooltip.classList.remove('show');
    });
}
