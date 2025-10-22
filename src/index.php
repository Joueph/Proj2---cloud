<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF--8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Ambientes</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="container">
        <header>
            <h1>Gerenciador de Ambientes de Execução</h1>
            <p>Crie, monitore e gerencie a execução de seus programas via web.</p>
        </header>

        <main>
            <section class="form-container">
                <h2>Criar Novo Ambiente</h2>
                <form id="form-criar-ambiente">
                    <div class="form-group">
                        <label for="nome">Nome do Ambiente</label>
                        <input type="text" id="nome" placeholder="Ex: Meu Servidor Web" required>
                    </div>
                    <div class="form-group">
                        <label for="comando">Comando a ser executado</label>
                        <input type="text" id="comando" placeholder="Ex: python3 -m http.server 8000" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cpu">Limite de CPU (Opcional)</label>
                            <input type="text" id="cpu" placeholder="Ex: 50%">
                        </div>
                        <div class="form-group">
                            <label for="memoria">Limite de Memória (Opcional)</label>
                            <input type="text" id="memoria" placeholder="Ex: 256M">
                        </div>
                    </div>
                    <button type="submit" class="btn">Criar e Executar</button>
                </form>
            </section>

            <section class="ambientes-lista">
                <h2>Ambientes Ativos</h2>
                <div id="lista-ambientes">
                    <!-- A lista de ambientes será carregada aqui via JavaScript -->
                    <p class="loading">Carregando ambientes...</p>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal para visualização de logs -->
    <div id="modal-logs" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Logs do Ambiente: <span id="log-ambiente-nome"></span></h2>
            <pre id="log-content">Carregando logs...</pre>
        </div>
    </div>


    <script src="app.js"></script>
</body>
</html>
