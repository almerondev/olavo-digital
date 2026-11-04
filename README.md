# Olavo Digital

Plataforma educacional do Olavo Pré-Vestibular.

- `apps/api`: API Laravel 13 (PHP 8.3+, MySQL 8.4, Sanctum).
- `apps/web`: SPA React 19 (Vite, Tailwind, shadcn/ui, TanStack Query).
- `docs/arquitetura.html`: arquitetura aprovada.

## Ambiente local (Windows, sem Docker)

Usa os binários do Laragon em `C:\laragon\bin`. Não é preciso abrir o Laragon.

```powershell
powershell -ExecutionPolicy Bypass -File scripts\dev.ps1
```

Abra http://localhost:5173. O Vite encaminha `/api` e `/sanctum` para a porta 8000, e por isso SPA e API usam a mesma origem, como em produção.

Dados de demonstração, com senha definida em `SEED_DEMO_PASSWORD` no `apps/api/.env`:

| Perfil | E-mail |
| --- | --- |
| Administrador | admin@olavo.test |
| Professor | professora@olavo.test |
| Aluno | aluno@olavo.test |

## Comandos

```powershell
php artisan migrate:fresh --seed   # em apps/api: recria o banco com dados de demonstração
php artisan test                   # em apps/api: testes no banco olavo_digital_test
npm run build                      # em apps/web: checagem de tipos e build
```

## Decisões locais

- Uploads usam disco local em desenvolvimento (`UPLOADS_DRIVER=local`) e R2 em produção (`s3`). O fluxo da API é o mesmo. O driver `s3` ainda não foi testado contra um bucket real.
- Sem o processo `queue:work`, os arquivos enviados ficam parados em "Validando". Sem `schedule:work`, os agendamentos não publicam. `scriptsdev.ps1` inicia os dois.
- Filas, cache e sessões usam MySQL em desenvolvimento. Em produção, filas e cache vão para Redis.
- Sessões ficam no MySQL também em produção, pois a tela de sessões ativas consulta essa tabela.
- O Laravel 13 aceita escritas sem token CSRF quando o navegador envia `Sec-Fetch-Site: same-origin`. Pedidos de outros sites continuam recusados com 419.
