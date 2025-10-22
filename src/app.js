document.addEventListener('DOMContentLoaded', () => {

    // --- Seletores de Elementos ---
    const formCriarAmbiente = document.getElementById('form-criar-ambiente');
    const listaAmbientesDiv = document.getElementById('lista-ambientes');
    const modal = document.getElementById('modal-logs');
    const closeModalButton = document.querySelector('.close-button');
    const logContent = document.getElementById('log-content');
    const logAmbienteNome = document.getElementById('log-ambiente-nome');

    const API_URL = 'backend/api.php';

    // --- Funções da API ---

    /**
     * Busca e exibe todos os ambientes.
     */
    async function carregarAmbientes() {
        try {
            const response = await fetch(API_URL);
            if (!response.ok) {
                throw new Error('Falha ao carregar os ambientes.');
            }
            const ambientes = await response.json();
            renderizarAmbientes(ambientes);
        } catch (error) {
            listaAmbientesDiv.innerHTML = `<p class="error">${error.message}</p>`;
        }
    }

    /**
     * Envia os dados para criar um novo ambiente.
     */
    async function criarAmbiente(data) {
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.message || 'Erro ao criar ambiente.');
            }
            // Limpa o formulário e recarrega a lista
            formCriarAmbiente.reset();
            carregarAmbientes();
        } catch (error) {
            alert(`Erro: ${error.message}`);
        }
    }

    /**
     * Envia uma requisição para remover um ambiente.
     */
    async function removerAmbiente(id) {
        if (!confirm('Tem certeza que deseja remover e parar este ambiente?')) {
            return;
        }
        try {
            const response = await fetch(`${API_URL}?id=${id}`, {
                method: 'DELETE'
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.message || 'Erro ao remover ambiente.');
            }
            carregarAmbientes();
        } catch (error) {
            alert(`Erro: ${error.message}`);
        }
    }

    /**
     * Busca o conteúdo de um arquivo de log.
     */
    async function carregarLogs(id, nome) {
        logAmbienteNome.textContent = nome;
        logContent.textContent = 'Carregando logs...';
        modal.style.display = 'block';

        try {
            const response = await fetch(`${API_URL}?log_id=${id}`);
            if (!response.ok) {
                throw new Error('Arquivo de log não encontrado ou vazio.');
            }
            const logText = await response.text();
            logContent.textContent = logText || 'O arquivo de log está vazio.';
        } catch (error) {
            logContent.textContent = `Erro ao carregar logs: ${error.message}`;
        }
    }

    // --- Funções de Renderização ---

    /**
     * Cria o HTML para a lista de ambientes.
     */
    function renderizarAmbientes(ambientes) {
        if (ambientes.length === 0) {
            listaAmbientesDiv.innerHTML = '<p>Nenhum ambiente foi criado ainda.</p>';
            return;
        }

        listaAmbientesDiv.innerHTML = ambientes.map(ambiente => `
            <div class="ambiente-card">
                <h3>${escapeHTML(ambiente.nome)}</h3>
                <p><strong>Comando:</strong> <code>${escapeHTML(ambiente.comando)}</code></p>
                <p><strong>Status:</strong> <span class="status ${ambiente.status.toLowerCase()}">${ambiente.status}</span></p>
                <p><strong>PID:</strong> ${ambiente.pid || 'N/A'}</p>
                <div class="actions">
                    <button class="btn btn-log" data-id="${ambiente.id}" data-nome="${escapeHTML(ambiente.nome)}">Ver Logs</button>
                    <button class="btn btn-delete" data-id="${ambiente.id}">Remover</button>
                </div>
            </div>
        `).join('');
    }

    // --- Tratadores de Eventos ---

    formCriarAmbiente.addEventListener('submit', (e) => {
        e.preventDefault();
        const data = {
            nome: document.getElementById('nome').value,
            comando: document.getElementById('comando').value,
            cpu_limit: document.getElementById('cpu').value,
            memoria_limit: document.getElementById('memoria').value
        };
        criarAmbiente(data);
    });

    listaAmbientesDiv.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-delete')) {
            const id = e.target.dataset.id;
            removerAmbiente(id);
        }
        if (e.target.classList.contains('btn-log')) {
            const id = e.target.dataset.id;
            const nome = e.target.dataset.nome;
            carregarLogs(id, nome);
        }
    });

    closeModalButton.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    window.addEventListener('click', (e) => {
        if (e.target == modal) {
            modal.style.display = 'none';
        }
    });
    
    // --- Utilitários ---
    function escapeHTML(str) {
        const p = document.createElement("p");
        p.appendChild(document.createTextNode(str));
        return p.innerHTML;
    }

    // --- Inicialização ---
    carregarAmbientes();
    // Atualiza a lista a cada 10 segundos
    setInterval(carregarAmbientes, 10000);
});
