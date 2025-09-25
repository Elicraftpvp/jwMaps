// site/script/main.js
document.addEventListener('DOMContentLoaded', () => {
    // 1. Verifica se o usuário está logado
    const usuarioLogadoString = sessionStorage.getItem('usuarioLogado');
    if (!usuarioLogadoString) {
        window.location.href = '../login.html';
        return;
    }
    const usuario = JSON.parse(usuarioLogadoString);

    // 2. Monta o HTML do menu
    let menuHTML = `
        <li class="nav-item">
            <a class="nav-link active" href="pages/dashboard.html" target="contentFrame">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
    `;

    if (usuario.cargo === 'servo') {
        menuHTML += `
            <li class="nav-item">
                <a class="nav-link" href="pages/gerenciar_mapas.html" target="contentFrame">
                    <i class="fas fa-map"></i> Gerenciar Mapas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pages/dirigentes.html" target="contentFrame">
                    <i class="fas fa-users"></i> Gerenciar Dirigentes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pages/historico.html" target="contentFrame">
                    <i class="fas fa-history"></i> Relatórios
                </a>
            </li>
        `;
    }

    if (usuario.cargo === 'dirigente') {
        menuHTML += `
            <li class="nav-item">
                <a class="nav-link" href="backend/vista_dirigente.php?id=${usuario.id}" target="contentFrame">
                    <i class="fas fa-id-card"></i> Meus Mapas
                </a>
            </li>
        `;
    }

    menuHTML += `
        <li class="nav-item mt-auto">
            <a class="nav-link" href="#" id="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </li>
    `;

    // 3. Popula ambos os menus (desktop e mobile)
    const menuDesktop = document.getElementById('menu-principal-desktop');
    const menuMobile = document.getElementById('menu-principal-mobile');
    if(menuDesktop) menuDesktop.innerHTML = menuHTML;
    if(menuMobile) menuMobile.innerHTML = menuHTML;


    // 4. Lógica para marcar o link do menu como 'ativo' e fechar offcanvas
    const allNavLinks = document.querySelectorAll('.nav-link');
    const sidebarMobileElement = document.getElementById('sidebarMobile');
    const sidebarMobileInstance = bootstrap.Offcanvas.getInstance(sidebarMobileElement) || new bootstrap.Offcanvas(sidebarMobileElement);

    allNavLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.id === 'logout-btn') {
                e.preventDefault();
                sessionStorage.removeItem('usuarioLogado');
                window.location.href = '../login.html';
                return;
            }

            if (this.getAttribute('target') === 'contentFrame') {
                // Remove 'active' de todos os links em ambos os menus
                allNavLinks.forEach(l => l.classList.remove('active'));
                
                // Adiciona 'active' ao link clicado e seu correspondente no outro menu
                const href = this.getAttribute('href');
                document.querySelectorAll(`.nav-link[href="${href}"]`).forEach(matchingLink => {
                    matchingLink.classList.add('active');
                });

                // Fecha o menu offcanvas se estiver aberto (em mobile)
                if (sidebarMobileInstance) {
                    sidebarMobileInstance.hide();
                }
            }
        });
    });
});