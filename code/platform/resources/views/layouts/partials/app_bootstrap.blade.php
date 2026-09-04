{{--
  Bootstrap de `window.App`. Injeta o token (lido server-side do cookie HttpOnly) e a
  URL da API para o axios usar Bearer direto. Mesmo padrão do App do Spelt.
--}}
<script>
  window.App = {
    apiUrl: @json(rtrim((string) config('services.api.url'), '/')),
    apiToken: @json(request()->cookie(\App\Support\TokenCookie::NAME)),
    productName: @json(config('services.product.name')),
    getToken() { return this.apiToken || null; },
    clearToken() { this.apiToken = null; },
    isAuthenticated() { return this.getToken() !== null; },
  };
</script>
