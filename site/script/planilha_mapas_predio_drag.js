// site/script/planilha_mapas_predio_drag.js

function inicializarDragAndDrop() {
    const mapasDisponiveisContainer = document.getElementById('lista-mapas-disponiveis');
    const containersAtribuicao = document.querySelectorAll('.mapas-dirigente-container');

    const sortableConfig = {
        group: 'mapas_predio_sistema', 
        animation: 150,
        draggable: '.mapa-item',
        ghostClass: 'bg-secondary',
        
        forceFallback: true,      
        fallbackTolerance: 3,     
        scroll: true,             
        scrollSensitivity: 150,   
        scrollSpeed: 40,          
        bubbleScroll: true,       

        onAdd: async function (evt) {
            const item = evt.item;
            const mapaId = item.getAttribute('data-mapa-id');
            const targetList = evt.to;
            
            const isDisponiveis = targetList.id === 'lista-mapas-disponiveis';
            const isDirigente = targetList.classList.contains('mapas-dirigente-container');
            
            try {
                if (isDisponiveis) {
                    await devolverMapaParaDisponiveis(mapaId);
                    item.className = 'badge mapa-disponivel-badge mapa-item';
                } else if (isDirigente) {
                    const dirigenteId = targetList.getAttribute('data-dirigente-id');
                    await atribuirMapa(mapaId, dirigenteId);
                    item.className = 'badge mapa-predio-badge mapa-item';
                }

            } catch (error) {
                console.error("Erro na operação:", error);
                alert("Erro ao atualizar o mapa de prédio. A página será recarregada.");
                window.location.reload(); 
            }
        }
    };

    if (mapasDisponiveisContainer) {
        new Sortable(mapasDisponiveisContainer, sortableConfig);
    }

    containersAtribuicao.forEach(container => {
        new Sortable(container, sortableConfig);
    });
}

async function atribuirMapa(mapaId, dirigenteId) {
    const hoje = new Date().toISOString().split('T')[0]; 
    const payload = {
        action: 'entregar',
        mapa_id: mapaId,
        data_entrega: hoje,
        dirigente_id: dirigenteId,
        grupo_id: null
    };

    const response = await fetch(`${API_BASE_URL_CONTROLE}/mapas_predio_api.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    if (!response.ok) throw new Error("Erro na API");
}

async function devolverMapaParaDisponiveis(mapaId) {
    const payload = {
        action: 'resgatar',
        mapa_id: mapaId
    };

    const response = await fetch(`${API_BASE_URL_CONTROLE}/mapas_predio_api.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    if (!response.ok) throw new Error("Erro na API");
}

window.inicializarDragAndDrop = inicializarDragAndDrop;
