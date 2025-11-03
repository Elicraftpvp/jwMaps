// Arquivo: jwMaps/site/script/planilha_mapas_drag.js

// O Sortable.js foi incluído via CDN no HTML, então ele está disponível globalmente.
// O arquivo planilha_mapas.js chama window.inicializarDragAndDrop() após a renderização.

function inicializarDragAndDrop() {
    const mapasDisponiveisContainer = document.getElementById('lista-mapas-disponiveis');
    const dirigentesContainers = document.querySelectorAll('.mapas-dirigente-container');

    // 1. Tornar os Mapas Disponíveis arrastáveis
    if (mapasDisponiveisContainer) {
        new Sortable(mapasDisponiveisContainer, {
            group: {
                name: 'mapas',
                pull: 'clone', // Permite arrastar, mas mantém o original
                put: false // Não permite soltar aqui
            },
            sort: false, // Não permite reordenar
            animation: 150,
            // Adiciona a classe 'mapa-item' aos itens arrastáveis
            draggable: '.mapa-item',
            // Função para clonar o elemento arrastado
            onStart: function (evt) {
                // Adiciona um atributo para identificar que este é um clone
                evt.item.setAttribute('data-is-clone', 'true');
            }
        });
    }

    // 2. Tornar os containers dos dirigentes soltáveis (droppable)
    dirigentesContainers.forEach(container => {
        new Sortable(container, {
            group: {
                name: 'mapas',
                put: true // Permite soltar aqui
            },
            animation: 150,
            // Adiciona a classe 'mapa-item' aos itens arrastáveis dentro do container
            draggable: '.mapa-item',
            // O mapa é movido para o dirigente
            onAdd: function (evt) {
                const mapaId = evt.item.getAttribute('data-mapa-id');
                const dirigenteId = evt.to.getAttribute('data-dirigente-id');
                
                // Remove o atributo de clone se for um clone
                if (evt.item.getAttribute('data-is-clone') === 'true') {
                    evt.item.removeAttribute('data-is-clone');
                    // O clone precisa ser transformado em um item real do dirigente
                    // e o item original precisa ser removido do container de mapas disponíveis
                    // (Isso será tratado pela lógica de persistência no backend)
                    
                    // Por enquanto, apenas remove o clone para simular a transferência
                    // evt.item.remove();
                    
                    // Se for um clone, precisamos criar um novo elemento no destino
                    // e remover o original do container de mapas disponíveis
                    // Mas como o Sortable.js já move o elemento, vamos apenas garantir que ele tenha a classe correta
                    evt.item.classList.remove('mapa-disponivel-badge');
                    evt.item.classList.add('mapa-atribuido-badge');
                }

                // **Lógica de Persistência (Backend)**
                // Aqui você chamaria uma API para atribuir o mapa ao dirigente
                // Exemplo: atribuirMapa(mapaId, dirigenteId);
                console.log(`Mapa ${mapaId} atribuído ao Dirigente ${dirigenteId}`);
                
                // Recarrega os dados para refletir a mudança (idealmente, apenas atualiza o DOM)
                // carregarDadosDaPlanilha(); 
            },
            // O mapa é removido do dirigente
            onRemove: function (evt) {
                const mapaId = evt.item.getAttribute('data-mapa-id');
                const dirigenteId = evt.from.getAttribute('data-dirigente-id');
                
                // **Lógica de Persistência (Backend)**
                // Aqui você chamaria uma API para remover o mapa do dirigente
                // Exemplo: removerMapa(mapaId, dirigenteId);
                console.log(`Mapa ${mapaId} removido do Dirigente ${dirigenteId}`);
                
                // Se o mapa foi movido para o container de mapas disponíveis, ele precisa ter a classe correta
                if (evt.to.id === 'lista-mapas-disponiveis') {
                    evt.item.classList.remove('mapa-atribuido-badge');
                    evt.item.classList.add('mapa-disponivel-badge');
                }
                
                // Recarrega os dados para refletir a mudança (idealmente, apenas atualiza o DOM)
                // carregarDadosDaPlanilha();
            }
        });
    });
}

// Exporta a função para ser chamada pelo planilha_mapas.js
window.inicializarDragAndDrop = inicializarDragAndDrop;
