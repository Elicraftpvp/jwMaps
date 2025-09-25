// site/script/main.js
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;

    // 1. Verifica se o usuário está logado
    const usuarioLogadoString = sessionStorage.getItem('usuarioLogado');
    if (!usuarioLogadoString) {
        window.location.href = '../login.html';
        return;
    }
    const usuario = JSON.parse(usuarioLogadoString);

    // 2. Monta o menu dinamicamente com base no cargo do usuário
    const menu = document.getElementById('menu-principal');
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
        // Link para a view pessoal do dirigente
        menuHTML += `
            <li class="nav-item">
                <a class="nav-link" href="backend/vista_dirigente.php?id=${usuario.id}" target="contentFrame">
                    <i class="fas fa-id-card"></i> Meus Mapas
                </a>
            </li>
        `;
    }

    menuHTML += `
        <li class="nav-item">
            <a class="nav-link" href="#" id="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </li>
    `;

    menu.innerHTML = menuHTML;

    // 3. Lógica para marcar o link do menu como 'ativo' ao ser clicado
    const navLinks = sidebar.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.id === 'logout-btn') {
                e.preventDefault();
                sessionStorage.removeItem('usuarioLogado');
                window.location.href = '../login.html';
                return;
            }
            // Aplica a classe 'active' apenas se o link abrir no iframe
            if (this.getAttribute('target') === 'contentFrame') {
                navLinks.forEach(l => l.classList.remove('active'));
                this.classList.add('active');
            }
        });
    });
});