document.addEventListener('DOMContentLoaded', () => {

    // --- Seletores de Navegação ---
    const navLinks = document.querySelectorAll('.nav-link');
    const pages = document.querySelectorAll('.page');
    const navDashboard = document.getElementById('nav-dashboard');
    const navCreate = document.getElementById('nav-create');
    const pageDashboard = document.getElementById('page-dashboard');
    const pageCreate = document.getElementById('page-create');

    // --- Seletores do Dashboard ---
    const statsCpu = document.getElementById('stats-cpu');
    const statsMem = document.getElementById('stats-mem');
    const filterStatus = document.getElementById('filter-status');
    const sortBy = document.getElementById('sort-by');
    const listaLoading = document.getElementById('lista-ambientes-loading');
    const listaError = document.getElementById('lista-ambientes-error');
    const listaBody = document.getElementById('lista-ambientes-body');
    const tabelaAmbientes = document.getElementById('tabela-ambientes');

    // --- Seletores do Formulário de Criação ---
    const formCriar = document.getElementById('form-criar-ambiente');
    const createMessage = document.getElementById('create-message');

    // --- Seletores do Modal de Log ---
    const modal = document.getElementById('modal-log');
    const modalClose = document.querySelector('.modal-close');
    const logContent = document.getElementById('log-content');
    const logAmbienteNome = document.getElementById('log-nome-ambiente');

    const API_URL = 'backend/api.php';
    let listUpdateInterval;

    // --- 1. Lógica de Navegação (Sidebar) ---

    function navigateTo(pageId) {
        // Esconde todas as páginas
        pages.forEach(page => page.classList.remove('active'));
        // Remove a classe ativa de todos os links
        navLinks.forEach(link => link.classList.remove('active'));

        if (pageId === 'dashboard') {
            // Mostra a página do dashboard
            pageDashboard.classList.add('active');
            navDashboard.classList.add('active');
            // Inicia o auto-refresh do dashboard
            startDashboardRefresh();
        } else if (pageId === 'create') {
            // Mostra a página de criação
            pageCreate.classList.add('active');
            navCreate.classList.add('active');
            // Para o auto-refresh do dashboard
            stopDashboardRefresh();
        }
    }

    navDashboard.addEventListener('click', (e) => {
        e.preventDefault();
        navigateTo('dashboard');
    });

    navCreate.addEventListener('click', (e) => {
        e.preventDefault();
        navigateTo('create');
    });

    function startDashboardRefresh() {
        // Para qualquer intervalo anterior para evitar duplicatas
        stopDashboardRefresh();
        
        // Carrega imediatamente
        carregarAmbientes();
        carregarStats();
        
        // Define um novo intervalo
        listUpdateInterval = setInterval(() => {
            carregarAmbientes();
            carregarStats();
        }, 5000); // Atualiza a cada 5 segundos
    }

    function stopDashboardRefresh() {
        if (listUpdateInterval) {
            clearInterval(listUpdateInterval);
        }
    }

    // --- 2. Lógica do Dashboard (Stats e Filtros) ---

    /**
     * Busca e exibe as estatísticas gerais da VM (CPU/Memória)
     */
    async function carregarStats() {
        try {
            const response = await fetch(`${API_URL}?action=get_stats`);
            if (!response.ok) throw new Error('Falha ao carregar estatísticas.');
            
            const stats = await response.json();
            statsCpu.textContent = `${stats.cpu_usage}%`;
            statsMem.textContent = stats.mem_usage;

        } catch (error) {
            statsCpu.textContent = 'Erro';
            statsMem.textContent = 'Erro';
            console.error(error.message);
        }
    }

    /**
     * Busca e exibe a lista de ambientes, aplicando filtros e ordenação
     */
    async function carregarAmbientes() {
        listaLoading.style.display = 'block';
        tabelaAmbientes.style.display = 'none';
        listaError.style.display = 'none';

        // Pega os valores dos filtros
        const filter_status = filterStatus.value;
        const sort_by = sortBy.value;

        try {
            const response = await fetch(`${API_URL}?filter_status=${filter_status}&sort_by=${sort_by}`);
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Falha ao carregar ambientes.');
            }

            const ambientes = await response.json();
            renderizarAmbientes(ambientes);
            
            listaLoading.style.display = 'none';
            tabelaAmbientes.style.display = 'table';

        } catch (error) {
            listaLoading.style.display = 'none';
            listaError.textContent = `Erro: ${error.message}`;
            listaError.style.display = 'block';
        }
    }

    /**
     * Renderiza a tabela de ambientes
     */
    function renderizarAmbientes(ambientes) {
        if (ambientes.length === 0) {
            listaBody.innerHTML = '<tr><td colspan="8" style="text-align:center;">Nenhum ambiente encontrado.</td></tr>';
            return;
        }

        listaBody.innerHTML = ambientes.map(ambiente => {
            const statusClass = ambiente.status ? ambiente.status.toLowerCase() : 'error';
            return `
                <tr>
                    <td>${escapeHTML(ambiente.nome)}</td>
                    <td>${ambiente.pid || 'N/A'}</td>
                    <td><span class="status ${statusClass}">${escapeHTML(ambiente.status)}</span></td>
                    <td><code>${escapeHTML(ambiente.comando)}</code></td>
                    <td>${escapeHTML(ambiente.cpu_limit)}</td>
                    <td>${escapeHTML(ambiente.memoria_limit)}</td>
                    <td>${new Date(ambiente.data_criacao).toLocaleString('pt-BR')}</td>
                    <td class="actions">
                        <button class="btn btn-log" data-id="${ambiente.id}" data-nome="${escapeHTML(ambiente.nome)}">Log</button>
                        <button class="btn btn-delete" data-id="${ambiente.id}" ${ambiente.status !== 'Running' ? 'disabled' : ''}>Parar</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // Adiciona listeners para os filtros
    filterStatus.addEventListener('change', carregarAmbientes);
    sortBy.addEventListener('change', carregarAmbientes);


    // --- 3. Lógica do Formulário de Criação ---

    formCriar.addEventListener('submit', async (e) => {
        e.preventDefault();
        createMessage.textContent = 'Criando ambiente...';
        createMessage.className = 'message';

        const data = {
            nome: document.getElementById('nome').value,
            comando: document.getElementById('comando').value,
            cpu_limit: document.getElementById('cpu').value,
            memoria_limit: document.getElementById('memoria').value
        };

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Erro ao criar ambiente.');

            createMessage.textContent = `Ambiente criado com sucesso (ID: ${result.id})!`;
            createMessage.className = 'message success';
            formCriar.reset();
            // Volta para o dashboard para ver o novo ambiente
            navigateTo('dashboard');

        } catch (error) {
            createMessage.textContent = `Erro: ${error.message}`;
            createMessage.className = 'message error';
        }
    });

    // --- 4. Lógica do Modal e Ações da Tabela ---

    listaBody.addEventListener('click', async (e) => {
        const target = e.target;

        // --- Botão Parar (DELETE) ---
        if (target.classList.contains('btn-delete')) {
            const id = target.dataset.id;
            if (!id || !confirm(`Tem certeza que deseja parar e remover este ambiente (ID: ${id})?`)) {
                return;
            }

            target.textContent = '...';
            target.disabled = true;

            try {
                const response = await fetch(`${API_URL}?id=${id}`, { method: 'DELETE' });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message);
                
                carregarAmbientes(); // Atualiza a lista

            } catch (error) {
                alert(`Erro ao remover: ${error.message}`);
                target.textContent = 'Parar';
                target.disabled = false;
            }
        }

        // --- Botão Ver Log (GET) ---
        if (target.classList.contains('btn-log')) {
            const id = target.dataset.id;
            const nome = target.dataset.nome;

            logNomeAmbiente.textContent = nome;
            logContent.textContent = 'Carregando log...';
            logContent.className = 'loading';
            modal.style.display = 'block';
            
            try {
                const response = await fetch(`${API_URL}?log_id=${id}`);
                if (!response.ok) {
                     const errorData = await response.json();
                     throw new Error(errorData.message);
                }
                const logText = await response.text();
                logContent.textContent = logText || '(Arquivo de log vazio)';

            } catch (error) {
                logContent.textContent = `Erro ao carregar log: ${error.message}`;
            } finally {
                logContent.className = '';
            }
        }
    });

    // Fechar Modal
    modalClose.onclick = () => {
        modal.style.display = 'none';
    };
    window.onclick = (e) => {
        if (e.target == modal) {
            modal.style.display = 'none';
        }
    };

    // --- 5. Utilitários e Inicialização ---

    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        return str.toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Inicialização
    navigateTo('dashboard'); // Começa na página do dashboard
});