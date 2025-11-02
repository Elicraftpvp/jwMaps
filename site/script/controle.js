// site/script/controle.js

// Função para carregar a página no iframe e destacar o link ativo
function loadPage(page, clickedLink) {
    const iframe = document.getElementById('display-frame');
    const links = document.querySelectorAll('#controleTabs .nav-link');

    // Atualiza o iframe
    if (iframe) {
        iframe.src = page;
    }

    // Remove a classe 'active' de todos os links
    links.forEach(link => {
        link.classList.remove('active');
    });

    // Adiciona a classe 'active' apenas ao link clicado
    if (clickedLink) {
        clickedLink.classList.add('active');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Garante que o primeiro link esteja ativo e a página carregada ao iniciar
    const firstLink = document.querySelector('#controleTabs .nav-link');
    if (firstLink) {
        // Apenas adiciona a classe 'active' se não estiver presente (o HTML já define o primeiro como ativo)
        if (!firstLink.classList.contains('active')) {
            firstLink.classList.add('active');
        }
        // Carrega a página inicial
        const initialPage = firstLink.getAttribute('onclick').match(/'([^']+)'/)[1];
        document.getElementById('display-frame').src = initialPage;
    }
});
