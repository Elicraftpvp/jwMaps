// site/script/planilha_mapas_drag.js

function inicializarDragAndDrop() {
    const mapasDisponiveisContainer = document.getElementById('lista-mapas-disponiveis');
    const dirigentesContainers = document.querySelectorAll('.mapas-dirigente-container');

    const sortableConfig = {
        group: 'mapas_sistema', 
        animation: 150,
        draggable: '.mapa-item',
        ghostClass: 'bg-secondary',
        
        // --- OTIMIZAÇÃO DE PERFORMANCE E SCROLL ---
        forceFallback: true,      // Usa simulação de drag (mais fluido e evita travar em textos)
        fallbackTolerance: 3,     // Previne arrastar acidentalmente ao clicar (pixels)
        scroll: true,             // Habilita scroll automático
        scrollSensitivity: 150,   // Distância da borda para começar a rolar (px)
        scrollSpeed: 40,          // Velocidade da rolagem (px/frame)
        bubbleScroll: true,       // Garante que o scroll funcione dentro do iframe
        // ------------------------------------------

        onAdd: async function (evt) {
            const item = evt.item;
            const mapaId = item.getAttribute('data-mapa-id');
            const targetList = evt.to;
            const isDisponiveis = targetList.id === 'lista-mapas-disponiveis';
            
            try {
                if (isDisponiveis) {
                    // DEVOLUÇÃO (Resgatar)
                    await devolverMapaParaDisponiveis(mapaId);
                    
                    // Troca Visual de Classe: De Atribuído para Disponível
                    item.classList.remove('mapa-atribuido-badge');
                    item.classList.add('mapa-disponivel-badge');
                    
                } else {
                    // ATRIBUIÇÃO OU TRANSFERÊNCIA (Entregar)
                    const dirigenteId = targetList.getAttribute('data-dirigente-id');
                    await atribuirMapaAoDirigente(mapaId, dirigenteId);
                    
                    // Troca Visual de Classe: De Disponível para Atribuído
                    item.classList.remove('mapa-disponivel-badge');
                    item.classList.add('mapa-atribuido-badge');
                }
            } catch (error) {
                console.error("Erro na operação:", error);
                alert("Erro ao atualizar o mapa. Recarregando...");
                window.location.reload(); 
            }
        }
    };

    if (mapasDisponiveisContainer) {
        new Sortable(mapasDisponiveisContainer, sortableConfig);
    }

    dirigentesContainers.forEach(container => {
        new Sortable(container, sortableConfig);
    });
}

// Funções de API
async function atribuirMapaAoDirigente(mapaId, dirigenteId) {
    const hoje = new Date().toISOString().split('T')[0]; 
    // action: 'entregar' agora lida com transferência e histórico no backend
    const payload = {
        action: 'entregar',
        mapa_id: mapaId,
        dirigente_id: dirigenteId,
        data_entrega: hoje
    };

    const response = await fetch(`${API_BASE_URL_CONTROLE}/mapas_api.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    if (!response.ok) throw new Error("Erro na API");
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