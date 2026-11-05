# Colocar em produção

Guia para um servidor Ubuntu 24.04 com Nginx, PHP-FPM 8.3 e MySQL 8.4. Os arquivos de apoio estão em `deploy/`.

## O que você precisa ter

- Servidor com 2 GB de memória ou mais.
- Domínio apontando para o servidor.
- Serviço de e-mail transacional. Sem ele, alunos não recebem o link de senha.
- Bucket no Cloudflare R2: um privado e um público.

## Primeira instalação

```bash
sudo apt install nginx mysql-server supervisor certbot python3-certbot-nginx php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-gd php8.3-zip php8.3-intl unzip
git clone https://github.com/almerondev/olavo-digital.git /var/www/olavo-digital
cd /var/www/olavo-digital/apps/api
cp .env.production.example .env
```

Preencha o `.env`, crie o banco e rode:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
sudo chown -R www-data:www-data storage bootstrap/cache
cd ../web && npm ci && npm run build
```

Em produção, o seed cria papéis, categorias e cursos, mas não os dados de demonstração.

Crie o primeiro administrador:

```bash
php artisan tinker
```

```php
$user = App\Modules\Auth\Models\User::create(['name' => 'Seu nome', 'email' => 'voce@exemplo.com', 'password' => 'troque-esta-senha', 'status' => 'active']);
$user->roles()->attach(App\Modules\Access\Models\Role::where('slug', 'admin')->value('id'));
```

Depois, ajuste o domínio em `deploy/nginx.conf` e instale os serviços:

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-enabled/olavo-digital
sudo certbot --nginx -d plataforma.exemplo.com.br
sudo cp deploy/supervisor.conf /etc/supervisor/conf.d/olavo-digital.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo crontab -u www-data deploy/crontab
```

## Atualizar

```bash
cd /var/www/olavo-digital && git pull
cd apps/api && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan queue:restart
cd ../web && npm ci && npm run build
```

## Antes de abrir para alunos

- [ ] `APP_DEBUG=false` e HTTPS funcionando.
- [ ] Teste de envio: redefinição de senha chega ao e-mail.
- [ ] Teste de upload: PDF e imagem aparecem para o aluno.
- [ ] Fila rodando: `sudo supervisorctl status`.
- [ ] Backup diário do banco e do bucket privado, com restauração testada.
- [ ] Termos e política de privacidade revisados pelo jurídico e publicados em Configurações.
- [ ] Antivírus de uploads avaliado (ainda não integrado).

## App Android e push

O app abre o site publicado, então precisa do endereço final com HTTPS.

1. No Android Studio, conclua a instalação padrão do SDK.
2. No console do Firebase, crie um projeto e um app Android com o pacote `br.com.olavo.tvplus`.
3. Copie `google-services.json` para `apps/web/android/app/`.
4. Em Contas de serviço, gere uma chave privada e salve em `apps/api/storage/app/private/firebase.json`.
5. No `.env` do servidor, defina `FCM_CREDENTIALS=storage/app/private/firebase.json`.
6. Gere o APK:

```powershell
powershell -ExecutionPolicy Bypass -File scriptsuild-apk.ps1 -Url https://plataforma.exemplo.com.br
```

O arquivo fica em `apps/web/public/downloads/olavo-tv-plus.apk`, e a tela de login oferece esse download. Guarde uma cópia de `olavo-tv-plus.keystore` e `keystore.properties`: sem elas, novas versões não instalam por cima da atual.

Para trocar o endereço do app, gere um novo APK. Atualizações do site chegam ao app sem novo APK.

## Ainda depende de decisão

- **Vídeos enviados por upload:** hoje os arquivos são servidos diretamente. Para muitos alunos simultâneos, use YouTube não listado ou contrate um provedor de vídeo.
- **Prévia de Word e PowerPoint:** a conversão com LibreOffice não está instalada. O aluno baixa o arquivo.
