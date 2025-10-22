<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gerenciador</title>
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- 1. Barra Lateral de Navegação -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h3>Cloud Manager</h3>
        </div>
        <ul class="nav-list">
            <li>
                <a href="#" id="nav-dashboard" class="nav-link active">
                    <i data-feather="bar-chart-2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="#" id="nav-create" class="nav-link">
                    <i data-feather="plus-circle"></i>
                    <span>Criar Ambiente</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- 2. Conteúdo Principal -->
    <main class="main-content">

        <!-- ======================================================= -->
        <!-- Página 1: Dashboard (Performance & Lista) -->
        <!-- ======================================================= -->
        <section id="page-dashboard" class="page active">
            <h1>Dashboard de Performance</h1>

            <!-- [HTML ALTERADO] Cartões de Stats com Barras de Progresso -->
            <div class="stats-container">
                <!-- Cartão de CPU -->
                <div class="stat-card progress-card">
                    <h4><i data-feather="cpu" class="card-icon"></i>Uso Total de CPU</h4>
                    <div class="progress-info">
                        <span class="progress-percentage" id="cpu-percent">--%</span>
                        <div class="progress-bar-container">
                            <div class="progress-bar" id="cpu-bar" style="width: 0%;"></div>
                        </div>
                    </div>
                    <span class="stat-details" id="cpu-details">Uso total do processador da VM</span>
                </div>
                <!-- Cartão de Memória -->
                <div class="stat-card progress-card">
                    <h4><i data-feather="hard-drive" class="card-icon"></i>Uso Total de Memória</h4>
                    <div class="progress-info">
                        <span class="progress-percentage" id="mem-percent">--%</span>
                        <div class="progress-bar-container">
                            <div class="progress-bar" id="mem-bar" style="width: 0%;"></div>
                        </div>
                    </div>
                    <span class="stat-details" id="mem-details">-- / -- MB</span>
                </div>
            </div>
            <!-- [FIM DAS ALTERAÇÕES] -->


            <!-- Lista de Ambientes -->
            <div class="ambiente-list-container">
                <h2>Ambientes Ativos</h2>
                <div class="filter-bar">
                    <div class="form-group">
                        <label for="filter-status">Filtrar por Status:</label>
                        <select id="filter-status">
                            <option value="all">Todos</option>
                            <option value="Running">Em Execução (Running)</option>
                            <option value="Finished">Terminado (Finished)</option>
                            <option value="Error">Erro (Error)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="sort-by">Ordenar por:</label>
                        <select id="sort-by">
                            <option value="data_criacao_desc">Mais Recentes</option>
                            <option value="data_criacao_asc">Mais Antigos</option>
                            <option value="cpu_desc">Consumo de CPU (Maior)</option>
                            <option value="memoria_desc">Consumo de Memória (Maior)</option>
                        </select>
                    </div>
                </div>

                <div id="lista-ambientes-loading" class="loading">Carregando ambientes...</div>
                <div id="lista-ambientes-error" class="message error" style="display:none;"></div>
                <table id="tabela-ambientes">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>PID</th>
                            <th>Status</th>
                            <th>Comando</th>
                            <th>CPU (req)</th>
                            <th>Memória (req)</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="lista-ambientes-body">
                        <!-- Linhas serão inseridas dinamicamente -->
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ======================================================= -->
        <!-- Página 2: Formulário de Criação -->
        <!-- ======================================================= -->
        <section id="page-create" class="page">
            <h1>Criar Novo Ambiente</h1>
            
            <div class="card">
                <form id="form-criar-ambiente">
                    <div class="form-group">
                        <label for="nome">Nome do Ambiente:</label>
                        <input type="text" id="nome" name="nome" placeholder="Ex: Meu Servidor Web" required>
                    </div>
                    <div class="form-group">
                        <label for="comando">Comando a ser executado:</label>
                        <input type="text" id="comando" name="comando" placeholder="Ex: ping -c 30 google.com" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cpu">Limite de CPU (% - Opcional)</label>
                            <input type="text" id="cpu" name="cpu" placeholder="Ex: 50%" value="N/A">
                        </div>
                        <div class="form-group">
                            <label for="memoria">Limite de Memória (MB - Opcional)</label>
                            <input type="text" id="memoria" name="memoria" placeholder="Ex: 256M" value="N/A">
                        </div>
                    </div>
                    <small>Insira os limites de recursos (ex: 50% para CPU, 256M para Memória). Deixe "N/A" para sem limites.</small>
                    
                    <button type="submit" class="btn btn-primary">Criar e Executar</button>
                </form>
                <div id="create-message" class="message"></div>
            </div>
        </section>
    </main>

    <!-- Modal para Visualização de Log -->
    <div id="modal-log" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3>Log de Execução: <span id="log-nome-ambiente"></span></h3>
            <pre id="log-content" class="loading"></pre>
        </div>
    </div>

    <script src="app.js"></script>
    <script>
      feather.replace()
    </script>
</body>
</html>

