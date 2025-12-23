// site/script/planilha_mapas_drag.js

function inicializarDragAndDrop() {
    const mapasDisponiveisContainer = document.getElementById('lista-mapas-disponiveis');
    // Seleciona tanto os containers de dirigentes quanto os de grupos
    const containersAtribuicao = document.querySelectorAll('.mapas-dirigente-container, .mapas-grupo-container');

    const sortableConfig = {
        group: 'mapas_sistema', 
        animation: 150,
        draggable: '.mapa-item',
        ghostClass: 'bg-secondary',
        
        // --- OTIMIZAÇÃO DE PERFORMANCE E SCROLL ---
        forceFallback: true,      
        fallbackTolerance: 3,     
        scroll: true,             
        scrollSensitivity: 150,   
        scrollSpeed: 40,          
        bubbleScroll: true,       
        // ------------------------------------------

        onAdd: async function (evt) {
            const item = evt.item;
            const mapaId = item.getAttribute('data-mapa-id');
            const targetList = evt.to;
            
            // Verifica o tipo de container onde o item caiu
            const isDisponiveis = targetList.id === 'lista-mapas-disponiveis';
            const isGrupo = targetList.classList.contains('mapas-grupo-container');
            const isDirigente = targetList.classList.contains('mapas-dirigente-container');
            
            try {
                if (isDisponiveis) {
                    // --- DEVOLUÇÃO (Resgatar) ---
                    await devolverMapaParaDisponiveis(mapaId);
                    
                    // Visual: Verde
                    item.className = 'badge mapa-disponivel-badge mapa-item';
                    
                } else if (isGrupo) {
                    // --- ATRIBUIÇÃO A GRUPO ---
                    const grupoId = targetList.getAttribute('data-grupo-id');
                    await atribuirMapa(mapaId, null, grupoId);
                    
                    // Visual: Azul (#4190be via CSS .mapa-grupo-badge)
                    item.className = 'badge mapa-grupo-badge mapa-item';

                } else if (isDirigente) {
                    // --- ATRIBUIÇÃO A DIRIGENTE ---
                    const dirigenteId = targetList.getAttribute('data-dirigente-id');
                    await atribuirMapa(mapaId, dirigenteId, null);
                    
                    // Visual: Amarelo/Padrão
                    item.className = 'badge mapa-atribuido-badge mapa-item';
                }

            } catch (error) {
                console.error("Erro na operação:", error);
                alert("Erro ao atualizar o mapa. A página será recarregada para garantir a integridade dos dados.");
                window.location.reload(); 
            }
        }
    };

    // Inicializa na área de disponíveis
    if (mapasDisponiveisContainer) {
        new Sortable(mapasDisponiveisContainer, sortableConfig);
    }

    // Inicializa em todos os containers de dirigentes e grupos
    containersAtribuicao.forEach(container => {
        new Sortable(container, sortableConfig);
    });
}

// Funções de API

/**
 * Atribui mapa a Dirigente OU Grupo
 * Se enviar dirigenteId, grupoId deve ser null, e vice-versa.
 */
async function atribuirMapa(mapaId, dirigenteId, grupoId) {
    const hoje = new Date().toISOString().split('T')[0]; 
    
    const payload = {
        action: 'entregar',
        mapa_id: mapaId,
        data_entrega: hoje
    };

    if (dirigenteId) {
        payload.dirigente_id = dirigenteId;
        payload.grupo_id = null; // Garante exclusividade
    } else if (grupoId) {
        payload.grupo_id = grupoId;
        payload.dirigente_id = null; // Garante exclusividade
    }

    const response = await fetch(`${API_BASE_URL_CONTROLE}/mapas_api.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    if (!response.ok) {
        const errTxt = await response.text();
        throw new Error("Erro na API: " + errTxt);
    }
}

async function devolverMapaParaDisponiveis(mapaId) {
    // action: 'resgatar' salva histórico e zera quadras
    const payload = {
        action: 'resgatar',
        mapa_id: mapaId
    };

    const response = await fetch(`${API_BASE_URL_CONTROLE}/mapas_api.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    if (!response.ok) throw new Error("Erro na API");
}

window.inicializarDragAndDrop = inicializarDragAndDrop;