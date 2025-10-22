Gerenciador de Ambientes de Execução

Este projeto implementa uma interface web para criar, gerenciar e monitorar a execução de programas e scripts dentro de um ambiente controlado por Vagrant, conforme especificado na disciplina de Computação em Nuvem.

Pré-requisitos

VirtualBox

Vagrant

Como Iniciar o Ambiente

Clone o repositório:

git clone <url-do-seu-repositorio>
cd <nome-da-pasta-do-projeto>


Crie as pastas src e logs:
Certifique-se de que as pastas src e logs existam na raiz do seu projeto antes de iniciar a VM.

Inicie a Máquina Virtual:
No terminal, dentro da pasta do projeto (onde o Vagrantfile se encontra), execute o comando:

vagrant up


Este comando irá baixar a imagem do sistema operacional, criar a VM, configurar hardware e rede, e rodar o script de provisionamento (bootstrap.sh) para instalar Apache, PHP e MySQL. O processo pode levar alguns minutos na primeira vez.

Acesse a Aplicação:
Após o vagrant up ser concluído com sucesso, abra seu navegador e acesse o seguinte endereço:
http://localhost:8080

Como Funciona

Frontend (index.php, style.css, app.js): Uma página web simples que permite ao usuário submeter um formulário para criar um novo ambiente e visualizar os ambientes existentes. As interações são feitas via AJAX, chamando a API do backend.

Backend (api.php): Um script PHP que serve como um endpoint RESTful. Ele recebe as requisições, interage com o banco de dados e chama o process_manager.php para executar as ações.

Gerenciador de Processos (process_manager.php): Contém a lógica para iniciar comandos em background no servidor (nohup ... &), obter seus PIDs, pará-los (kill) e verificar se ainda estão em execução (ps).

Banco de Dados (database.sql): Um schema simples no MySQL para persistir as informações sobre os ambientes criados.

Vagrant (Vagrantfile, scripts/): Automatiza a criação e configuração de todo o ambiente de desenvolvimento, garantindo que tudo funcione de forma consistente.

Integrantes do Grupo

(Adicionar Nome do Integrante 1)

(Adicionar Nome do Integrante 2)

(Adicionar Nome do Integrante 3)