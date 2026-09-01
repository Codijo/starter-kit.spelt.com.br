# Starter Kit — Site

Template do **site de marketing** (Laravel 12 + Vite + Blade + Tailwind + Alpine) de um novo SaaS. Páginas públicas: Home, Preços, Plataforma, Sobre, Contato — mais um padrão para páginas específicas.

- **Sem banco, sem auth.** Só front público. CTAs apontam para a Plataforma (`config('site.app_url')`).
- **Conteúdo** em `config/site.php`. **Cor da marca** em `tailwind.config.js` (`brand`).
- **Padrão de página nova:** copie `resources/views/web/_page.blade.php` + registre em `routes/web/Page.php`.

Guia completo: **`CLAUDE.md`**.

## Boot (no container)

```
composer install && php artisan key:generate && npm install && npm run dev
```

(O `init-laravel.sh` já faz isso no boot do container.)
