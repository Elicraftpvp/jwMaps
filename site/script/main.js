// site/script/main.js
document.addEventListener('DOMContentLoaded', () => {
    // 1. Definição das permissões (sem alteração)
    const PERM_DIRIGENTE = 1;
    const PERM_ADMIN = 2;
    const PERM_CARRINHO = 4;
    const PERM_PUBLICADOR = 8;
    const PERM_CAMPANHA = 16;

    // 2. Verifica o usuário logado (sem alteração)
    const userString = sessionStorage.getItem('user');
    if (!userString) {
        window.location.href = '../login.html';
        return;
    }
    const user = JSON.parse(userString);
    const userPermissoes = parseInt(user.permissoes, 10);

    // 3. Monta o menu com lógica aditiva ("de baixo para cima")
    let menuItems = [];
    let hasAccess = false;

    // A permissão de Admin (Servo) é um caso especial, pois vê tudo.
    // Se o usuário for admin, construímos o menu completo e encerramos.
    if ((userPermissoes & PERM_ADMIN) === PERM_ADMIN) {
        hasAccess = true;
        menuItems = [
            // Dashboard é o primeiro e ativo por padrão
            { href: 'pages/dashboard.html', icon: 'fas fa-tachometer-alt', text: 'Dashboard', active: true },
            { href: 'pages/gerenciar_mapas.html', icon: 'fas fa-map', text: 'Gerenciar Mapas' },
            { href: 'pages/dirigentes.html', icon: 'fas fa-users', text: 'Gerenciar Dirigentes' },
            // MODIFICAÇÃO: Adicionado "Meus Mapas" para o Admin
            { href: 'backend/vista_dirigente.php', icon: 'fas fa-id-card', text: 'Meus Mapas' },
            { href: 'pages/controle.html', icon: 'fas fa-history', text: 'Controle' }
        ];
    }
    // Para todos os outros usuários, construímos o menu adicionando os itens de cada permissão
    else {
        // PERMISSÃO MÍNIMA: Dirigente (1)
        if ((userPermissoes & PERM_DIRIGENTE) === PERM_DIRIGENTE) {
            hasAccess = true;
            menuItems.push({ href: 'backend/vista_dirigente.php', icon: 'fas fa-id-card', text: 'Meus Mapas' });
        }

        // MODIFICAÇÃO: Nova permissão para Publicador ver "Meus Mapas"
        if ((userPermissoes & PERM_PUBLICADOR) === PERM_PUBLICADOR) {
            hasAccess = true;
            // Adiciona "Meus Mapas" apenas se ainda não existir (evita duplicar com a permissão de Dirigente)
            if (!menuItems.some(item => item.href.includes('vista_dirigente'))) {
                menuItems.push({ href: 'backend/vista_dirigente.php', icon: 'fas fa-id-card', text: 'Meus Mapas' });
            }
        }

        // PERMISSÃO MÉDIA: Campanha (16)
        if ((userPermissoes & PERM_CAMPANHA) === PERM_CAMPANHA) {
            hasAccess = true;
            // Adiciona apenas se ainda não existirem, para evitar duplicatas caso uma futura permissão os inclua
            if (!menuItems.some(item => item.href.includes('gerenciar_mapas'))) {
                 menuItems.push({ href: 'pages/gerenciar_mapas.html', icon: 'fas fa-map', text: 'Gerenciar Mapas' });
            }
            if (!menuItems.some(item => item.href.includes('controle'))) {
                 menuItems.push({ href: 'pages/controle.html', icon: 'fas fa-history', text: 'Controle' });
            }
        }

        // Adiciona o Dashboard no início da lista se o usuário tiver qualquer acesso válido
        if (hasAccess) {
            menuItems.unshift({ href: 'pages/dashboard.html', icon: 'fas fa-tachometer-alt', text: 'Dashboard', active: true });
        }
    }

    // 4. Bloqueia acesso se nenhuma permissão válida foi encontrada (apenas Carrinho, por exemplo)
    if (!hasAccess) {
        alert('Você não tem permissão para acessar o sistema.');
        sessionStorage.removeItem('user');
        window.location.href = '../login.html';
        return;
    }

    // 5. Constrói o HTML final a partir do array de itens do menu
    let menuHTML = menuItems.map(item => `
        <li class="nav-item">
            <a class="nav-link ${item.active ? 'active' : ''}" href="${item.href}" target="contentFrame">
                <i class="${item.icon}"></i> ${item.text}
            </a>
        </li>
    `).join('');

    // Adiciona o botão de Sair no final
    menuHTML += `
        <li class="nav-item mt-auto">
            <a class="nav-link" href="#" id="logout-btn"><i class="fas fa-sign-out-alt"></i> Sair</a>
        </li>
    `;

    // 6. Popula os menus e adiciona eventos (sem alterações na lógica de eventos)
    const menuDesktop = document.getElementById('menu-principal-desktop');
    const menuMobile = document.getElementById('menu-principal-mobile');
    if (menuDesktop) menuDesktop.innerHTML = menuHTML;
    if (menuMobile) menuMobile.innerHTML = menuHTML;
    
    // Lógica de UI para links ativos e logout
    const allNavLinks = document.querySelectorAll('.nav-link');
    const sidebarMobileElement = document.getElementById('sidebarMobile');
    const sidebarMobileInstance = bootstrap.Offcanvas.getInstance(sidebarMobileElement) || new bootstrap.Offcanvas(sidebarMobileElement);
    
    allNavLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.id === 'logout-btn') {
                e.preventDefault();
                sessionStorage.removeItem('user');
                window.location.href = '../login.html';
                return;
            }
            if (this.getAttribute('target') === 'contentFrame') {
                // Remove 'active' de todos
                allNavLinks.forEach(l => l.classList.remove('active'));
                
                // Adiciona 'active' no link clicado (tanto no desktop quanto no mobile)
                const href = this.getAttribute('href');
                document.querySelectorAll(`.nav-link[href="${href}"]`).forEach(matchingLink => matchingLink.classList.add('active'));

                // Fecha o menu mobile se estiver aberto
                if (sidebarMobileInstance && window.getComputedStyle(sidebarMobileElement).visibility === 'visible') {
                    sidebarMobileInstance.hide();
                }
            }
        });
    });

    // Inicia o iframe com a página do dashboard por padrão
    const contentFrame = document.getElementById('contentFrame');
    if (contentFrame && hasAccess) {
        contentFrame.src = "pages/dashboard.html";
    }
});