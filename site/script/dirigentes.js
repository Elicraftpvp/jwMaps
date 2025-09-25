// site/script/dirigentes.js
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('users-table-body');
    const userModalElement = document.getElementById('userModal');
    const modal = new bootstrap.Modal(userModalElement);
    const modalTitle = document.getElementById('userModalLabel');
    const mostrarInativosCheck = document.getElementById('mostrar-inativos-check');
    let editMode = false;
    let editId = null;

    const carregarUsuarios = async () => { /* ... (código completo da resposta anterior, está correto) ... */ };
    const prepararEdicao = async (id) => { /* ... (código completo da resposta anterior, está correto) ... */ };
    const resetarModal = () => { /* ... (código completo da resposta anterior, está correto) ... */ };

    document.getElementById('salvar-user-btn').addEventListener('click', async () => { /* ... (código completo da resposta anterior, está correto) ... */ });
    document.getElementById('regenerar-token-btn').addEventListener('click', async () => { /* ... (código completo da resposta anterior, está correto) ... */ });
    mostrarInativosCheck.addEventListener('change', carregarUsuarios);
    tableBody.addEventListener('click', async (e) => { /* ... (código completo da resposta anterior, está correto) ... */ });
    userModalElement.addEventListener('hidden.bs.modal', resetarModal);

    carregarUsuarios();
});