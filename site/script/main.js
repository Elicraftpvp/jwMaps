document.addEventListener('DOMContentLoaded', () => {
    // 1. Definição das permissões
    const PERM_DIRIGENTE = 1;
    const PERM_ADMIN = 2;
    const PERM_CARRINHO = 4;
    const PERM_PUBLICADOR = 8;
    const PERM_CAMPANHA = 16;

    // 2. Verifica o usuário logado
    const userString = sessionStorage.getItem('user');
    if (!userString) {
        window.location.href = '/login';
        return;
    }
    const user = JSON.parse(userString);
    const userPermissoes = parseInt(user.permissoes, 10);

    // 3. Monta o menu principal
    let menuItems = [];
    let hasAccess = false;

    if ((userPermissoes & PERM_ADMIN) === PERM_ADMIN) {
        hasAccess = true;
        menuItems = [
            { href: 'pages/dashboard.html', icon: 'fas fa-tachometer-alt', text: 'Dashboard', active: true },
            { href: 'pages/gerenciar_mapas.html', icon: 'fas fa-map', text: 'Gerenciar Mapas' },
            { href: 'pages/dirigentes.html', icon: 'fas fa-user-cog', text: 'Gerenciar Usuarios' },
            { href: 'pages/gerenciar_grupos.html', icon: 'fas fa-users-cog', text: 'Gerenciar Grupos' },
            { href: 'backend/vista_dirigente.php', icon: 'fas fa-id-card', text: 'Meus Mapas' },
            { href: 'pages/controle.html', icon: 'fas fa-history', text: 'Controle' }
        ];
    }
    else {
        if ((userPermissoes & PERM_DIRIGENTE) === PERM_DIRIGENTE) {
            hasAccess = true;
            menuItems.push({ href: 'backend/vista_dirigente.php', icon: 'fas fa-id-card', text: 'Meus Mapas' });
        }

        if ((userPermissoes & PERM_PUBLICADOR) === PERM_PUBLICADOR) {
            hasAccess = true;
            if (!menuItems.some(item => item.href.includes('vista_dirigente'))) {
                menuItems.push({ href: 'backend/vista_dirigente.php', icon: 'fas fa-id-card', text: 'Meus Mapas' });
            }
        }

        if ((userPermissoes & PERM_CAMPANHA) === PERM_CAMPANHA) {
            hasAccess = true;
            if (!menuItems.some(item => item.href.includes('gerenciar_mapas'))) {
                 menuItems.push({ href: 'pages/gerenciar_mapas.html', icon: 'fas fa-map', text: 'Gerenciar Mapas' });
            }
            if (!menuItems.some(item => item.href.includes('controle'))) {
                 menuItems.push({ href: 'pages/controle.html', icon: 'fas fa-history', text: 'Controle' });
            }
        }

        if (hasAccess) {
            menuItems.unshift({ href: 'pages/dashboard.html', icon: 'fas fa-tachometer-alt', text: 'Dashboard', active: true });
        }
    }

    // 4. Bloqueia acesso
    if (!hasAccess) {
        alert('Você não tem permissão para acessar o sistema.');
        sessionStorage.removeItem('user');
        window.location.href = '/login';
        return;
    }

    // 5. Gera HTML do Menu Principal
    const menuHTML = menuItems.map(item => `
        <li class="nav-item">
            <a class="nav-link ${item.active ? 'active' : ''}" href="${item.href}" target="contentFrame">
                <i class="${item.icon}"></i> ${item.text}
            </a>
        </li>
    `).join('');

    // 6. Gera HTML do Menu de Sair (Separado e com a borda forçada via style inline para garantir)
    const logoutHTML = `
        <li class="nav-item">
            <a class="nav-link" href="#" id="logout-btn" style="border-top: 1px solid #495057;">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </li>
    `;

    // 7. Injeta no HTML
    const menuDesktop = document.getElementById('menu-principal-desktop');
    const menuLogoutDesktop = document.getElementById('menu-logout-desktop');
    
    const menuMobile = document.getElementById('menu-principal-mobile');
    const menuLogoutMobile = document.getElementById('menu-logout-mobile');

    if (menuDesktop) menuDesktop.innerHTML = menuHTML;
    if (menuMobile) menuMobile.innerHTML = menuHTML;

    if (menuLogoutDesktop) menuLogoutDesktop.innerHTML = logoutHTML;
    if (menuLogoutMobile) menuLogoutMobile.innerHTML = logoutHTML;
    
    // 8. Eventos de clique
    const allNavLinks = document.querySelectorAll('.nav-link');
    const sidebarMobileElement = document.getElementById('sidebarMobile');
    const sidebarMobileInstance = bootstrap.Offcanvas.getInstance(sidebarMobileElement) || new bootstrap.Offcanvas(sidebarMobileElement);
    
    allNavLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.id === 'logout-btn') {
                e.preventDefault();
                sessionStorage.removeItem('user');
                window.location.href = '/login';
                return;
            }
            
            if (this.getAttribute('target') === 'contentFrame') {
                // Remove active de todos
                allNavLinks.forEach(l => l.classList.remove('active'));
                
                // Adiciona active no link correspondente
                const href = this.getAttribute('href');
                document.querySelectorAll(`.nav-link[href="${href}"]`).forEach(matchingLink => matchingLink.classList.add('active'));

                // Fecha mobile
                if (sidebarMobileInstance && window.getComputedStyle(sidebarMobileElement).visibility === 'visible') {
                    sidebarMobileInstance.hide();
                }
            }
        });
    });

    const contentFrame = document.getElementById('contentFrame');
    if (contentFrame && hasAccess) {
        contentFrame.src = "pages/dashboard.html";
    }
});