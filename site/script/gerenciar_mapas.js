// site/script/gerenciar_mapas.js

document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('mapas-table-body');
    const mapaModal = new bootstrap.Modal(document.getElementById('mapaModal'));
    const entregarModal = new bootstrap.Modal(document.getElementById('entregarModal'));
    const entregarModalLabel = document.getElementById('entregarModalLabel');
    const selectDirigentes = document.getElementById('entregar_dirigente_id');

    const carregarMapas = async () => {
        tableBody.innerHTML = `<tr><td colspan="6" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>`;
        try {
            const response = await fetch(`${API_BASE_URL}/mapas_api.php`);
            const mapas = await response.json();
            
            tableBody.innerHTML = '';
            if (mapas.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center">Nenhum mapa cadastrado.</td></tr>`;
                return;
            }

            mapas.forEach(mapa => {
                let status, acoes;
                if (mapa.dirigente_id) {
                    status = `<span class="badge bg-warning">Em Uso</span>`;
                    acoes = `<button class="btn btn-sm btn-info btn-recolher" data-id="${mapa.id}" title="Forçar Devolução (Admin)" disabled><i class="fas fa-undo"></i></button>`;
                } else {
                    status = `<span class="badge bg-success">Disponível</span>`;
                    acoes = `<button class="btn btn-sm btn-primary btn-entregar" data-id="${mapa.id}" data-identificador="${mapa.identificador}" title="Entregar Mapa"><i class="fas fa-hand-holding-heart"></i></button>`;
                }

                const row = `
                    <tr>
                        <td>${mapa.id}</td>
                        <td>${mapa.identificador}</td>
                        <td>${status}</td>
                        <td>${mapa.dirigente_nome || '---'}</td>
                        <td>${mapa.data_entrega ? new Date(mapa.data_entrega + 'T00:00:00').toLocaleDateString('pt-BR') : '---'}</td>
                        <td>
                            ${acoes}
                            <button class="btn btn-sm btn-danger btn-delete" data-id="${mapa.id}" title="Excluir"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>`;
                tableBody.innerHTML += row;
            });
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Erro ao carregar mapas.</td></tr>`;
        }
    };
    
    const carregarDirigentesNoModal = async () => {
        try {
            const response = await fetch(`${API_BASE_URL}/mapas_api.php?recurso=dirigentes`);
            const dirigentes = await response.json();
            selectDirigentes.innerHTML = '<option value="">Selecione...</option>';
            dirigentes.forEach(d => {
                selectDirigentes.innerHTML += `<option value="${d.id}">${d.nome}</option>`;
            });
        } catch (error) {
            selectDirigentes.innerHTML = '<option value="">Erro ao carregar</option>';
        }
    };
    
    // Salvar novo mapa
    document.getElementById('salvar-mapa-btn').addEventListener('click', async () => {
        const identificador = document.getElementById('mapa_identificador').value;
        if (!identificador) {
            alert('O identificador do mapa é obrigatório.');
            return;
        }

        try {
            await fetch(`${API_BASE_URL}/mapas_api.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ identificador })
            });
            mapaModal.hide();
            document.getElementById('form-add-mapa').reset();
            carregarMapas();
        } catch (error) {
            alert('Erro ao salvar o mapa.');
        }
    });

    // Confirmar entrega de mapa
    document.getElementById('confirmar-entrega-btn').addEventListener('click', async () => {
        const data = {
            action: 'entregar',
            mapa_id: document.getElementById('entregar_mapa_id').value,
            dirigente_id: document.getElementById('entregar_dirigente_id').value,
            data_entrega: document.getElementById('entregar_data').value,
        };

        if (!data.dirigente_id || !data.data_entrega) {
            alert('Selecione um dirigente e uma data.');
            return;
        }

        try {
            await fetch(`${API_BASE_URL}/mapas_api.php`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            entregarModal.hide();
            carregarMapas();
        } catch (error) {
            alert('Erro ao entregar o mapa.');
        }
    });
    
    // Delegação de eventos na tabela
    tableBody.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return;
        const id = target.dataset.id;

        if (target.classList.contains('btn-delete')) {
            if (confirm('Deseja realmente excluir este mapa? O histórico associado a ele também será perdido.')) {
                try {
                    await fetch(`${API_BASE_URL}/mapas_api.php?id=${id}`, { method: 'DELETE' });
                    carregarMapas();
                } catch (error) {
                    alert('Não foi possível excluir o mapa.');
                }
            }
        } else if (target.classList.contains('btn-entregar')) {
            const identificador = target.dataset.identificador;
            entregarModalLabel.textContent = `Entregar Mapa: ${identificador}`;
            document.getElementById('entregar_mapa_id').value = id;
            document.getElementById('entregar_data').valueAsDate = new Date();
            entregarModal.show();
        }
    });
    
    // Inicialização
    carregarMapas();
    carregarDirigentesNoModal();
});