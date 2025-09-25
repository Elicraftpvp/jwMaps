// site/script/dirigentes.js

document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('users-table-body');
    const userModalElement = document.getElementById('userModal');
    const modal = new bootstrap.Modal(userModalElement);
    const modalTitle = document.getElementById('userModalLabel');
    let editMode = false;
    let editId = null;

    const carregarUsuarios = async () => {
        tableBody.innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>`;
        try {
            const response = await fetch(`${API_BASE_URL}/dirigentes_api.php`);
            if (!response.ok) throw new Error('Erro na requisição');
            const users = await response.json();
            
            tableBody.innerHTML = '';
            if (users.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center">Nenhum usuário cadastrado.</td></tr>`;
                return;
            }
            users.forEach(u => {
                const row = `
                    <tr>
                        <td>${u.id}</td>
                        <td>${u.nome}</td>
                        <td>${u.login}</td>
                        <td><span class="badge bg-${u.cargo === 'servo' ? 'success' : 'secondary'}">${u.cargo}</span></td>
                        <td>
                            <button class="btn btn-sm btn-warning btn-edit" data-id="${u.id}" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger btn-delete" data-id="${u.id}" title="Excluir"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>`;
                tableBody.innerHTML += row;
            });
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Erro ao carregar usuários.</td></tr>`;
        }
    };

    const prepararEdicao = async (id) => {
        try {
            const response = await fetch(`${API_BASE_URL}/dirigentes_api.php?id=${id}`);
            if (!response.ok) throw new Error('Usuário não encontrado');
            const user = await response.json();

            document.getElementById('user_nome').value = user.nome;
            document.getElementById('user_login').value = user.login;
            document.getElementById('user_cargo').value = user.cargo;
            document.getElementById('user_senha').value = ''; // Senha sempre vazia ao editar

            editMode = true;
            editId = id;
            modalTitle.textContent = 'Editar Usuário';
            
            modal.show();
        } catch (error) {
            alert('Não foi possível carregar os dados do usuário.');
        }
    };

    const resetarModal = () => {
        document.getElementById('form-user').reset();
        editMode = false;
        editId = null;
        modalTitle.textContent = 'Adicionar Usuário';
    };

    document.getElementById('salvar-user-btn').addEventListener('click', async () => {
        const data = {
            nome: document.getElementById('user_nome').value,
            login: document.getElementById('user_login').value,
            cargo: document.getElementById('user_cargo').value,
            senha: document.getElementById('user_senha').value // Envia senha (vazia ou não)
        };

        if (!data.nome || !data.login || !data.cargo) {
            alert('Nome, Login e Cargo são obrigatórios.');
            return;
        }

        if (!editMode && !data.senha) {
            alert('A senha é obrigatória ao criar um novo usuário.');
            return;
        }
        
        const url = editMode ? `${API_BASE_URL}/dirigentes_api.php?id=${editId}` : `${API_BASE_URL}/dirigentes_api.php`;
        const method = editMode ? 'PUT' : 'POST';

        try {
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                 const errorData = await response.json();
                 throw new Error(errorData.message || 'Erro ao salvar usuário.');
            }

            modal.hide();
            carregarUsuarios();
        } catch (error) {
            alert(`Falha ao salvar usuário: ${error.message}`);
        }
    });

    tableBody.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return;

        const id = target.dataset.id;

        if (target.classList.contains('btn-delete')) {
            if (confirm('Deseja realmente excluir este usuário?')) {
                try {
                    await fetch(`${API_BASE_URL}/dirigentes_api.php?id=${id}`, { method: 'DELETE' });
                    carregarUsuarios();
                } catch (error) {
                    alert('Não foi possível excluir o usuário.');
                }
            }
        } else if (target.classList.contains('btn-edit')) {
            prepararEdicao(id);
        }
    });

    userModalElement.addEventListener('hidden.bs.modal', resetarModal);

    carregarUsuarios();
});