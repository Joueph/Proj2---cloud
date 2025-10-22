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

    <main class="main-content">

        <section id="page-dashboard" class="page active">
            <h1>Dashboard de Performance</h1>

            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon cpu">
                        <i data-feather="cpu"></i>
                    </div>
                    <div class="stat-info">
                        <h4>Uso Total de CPU</h4>
                        <p id="stats-cpu">Carregando...</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon mem">
                        <i data-feather="hard-drive"></i>
                    </div>
                    <div class="stat-info">
                        <h4>Uso Total de Memória</h4>
                        <p id="stats-mem">Carregando...</p>
                    </div>
                </div>
            </div>

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
                        </tbody>
                </table>
            </div>
        </section>

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
                    <small>Nota: A aplicação de limites de CPU/Memória (cgroups) ainda precisa ser implementada no backend.</small>
                    
                    <button type="submit" class="btn btn-primary">Criar e Executar</button>
                </form>
                <div id="create-message" class="message"></div>
            </div>
        </section>
    </main>

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