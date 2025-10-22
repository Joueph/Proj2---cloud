#!/bin/bash

# --- Configurações do Banco de Dados ---
DB_ROOT_PASS="root"
DB_NAME="gerenciador"
DB_USER="webuser"
DB_PASS="webpass"

echo ">>> Iniciando o provisionamento da VM..."

# --- Atualiza o sistema ---
sudo apt-get update -y
sudo apt-get upgrade -y

# --- Instala o Apache2 ---
echo ">>> Instalando Apache2..."
sudo apt-get install -y apache2

# --- Instala o PHP e módulos necessários ---
echo ">>> Instalando PHP e módulos..."
sudo apt-get install -y php libapache2-mod-php php-mysql

# --- Configura o Apache ---
echo ">>> Configurando o Apache..."
# Corrigido: Usa /vagrant/scripts (caminho sincronizado)
sudo cp /vagrant/scripts/apache.conf /etc/apache2/sites-available/000-default.conf
sudo a2enmod rewrite
sudo systemctl restart apache2

# --- Instala o Servidor MySQL ---
echo ">>> Instalando e configurando o MySQL..."
sudo debconf-set-selections <<< "mysql-server mysql-server/root_password password $DB_ROOT_PASS"
sudo debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $DB_ROOT_PASS"
sudo apt-get install -y mysql-server

# --- Cria o Banco de Dados e o Usuário da Aplicação ---
echo ">>> Criando banco de dados e usuário..."
mysql -uroot -p"$DB_ROOT_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;"
mysql -uroot -p"$DB_ROOT_PASS" -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -uroot -p"$DB_ROOT_PASS" -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
mysql -uroot -p"$DB_ROOT_PASS" -e "FLUSH PRIVILEGES;"

# --- Importa a estrutura das tabelas ---
echo ">>> Importando a estrutura do banco de dados..."
# Corrigido: Usa /vagrant/scripts (caminho sincronizado)
mysql -uroot -p"$DB_ROOT_PASS" "$DB_NAME" < /vagrant/scripts/database.sql

# --- Garante que a pasta de logs pertence ao usuário do Apache ---
# A pasta /var/www/logs é mapeada do host, mas definimos a permissão
sudo chown -R www-data:www-data /var/www/logs

# --- [NOVO] Adiciona permissão para Cgroups (systemd-run) ---
echo ">>> Configurando permissões de sudo para systemd-run..."
# Permite que o utilizador 'www-data' execute 'systemd-run' como root sem senha.
# Isto é necessário para aplicar limites de cgroup (CPU/Memória).
echo "www-data ALL=(ALL) NOPASSWD: /usr/bin/systemd-run" > /etc/sudoers.d/www-data-systemd-run
# Define as permissões corretas para o ficheiro sudoers
sudo chmod 0440 /etc/sudoers.d/www-data-systemd-run

echo ">>> Provisionamento concluído com sucesso!"

