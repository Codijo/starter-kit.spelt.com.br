import { defineConfig, loadEnv } from 'vite';
import { glob } from 'glob';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const host = env.VITE_HOST || '0.0.0.0';
  const port = Number(env.VITE_PORT) || 5173;

  return {
    plugins: [
      laravel({
        input: [
          'resources/css/app.css',
          'resources/js/app.js',
          // JS colocado com as views (padrão App): cada tela carrega o seu via @vite no
          // rodapé do blade. Sem lista manual, sem arquivo central que conflita em merge.
          ...glob.sync('resources/views/**/*.js'),
        ],
        refresh: true,
      }),
    ],
    resolve: {
      alias: { '@': path.resolve(__dirname, 'resources/js') },
    },
    server: {
      host,
      port,
      strictPort: true,
      origin: env.VITE_URL || undefined,
      hmr: {
        host: env.VITE_HMR_HOST || undefined,
        clientPort: env.VITE_HMR_CLIENT_PORT ? Number(env.VITE_HMR_CLIENT_PORT) : undefined,
        protocol: env.VITE_HMR_PROTOCOL || undefined,
        // O websocket do HMR sob /@vite/hmr (coberto pelo PathPrefix do Traefik) — senão
        // ele tenta ws://host/ e o Traefik não roteia (falha só o hot-reload).
        path: env.VITE_HMR_PATH || '/@vite/hmr',
      },
      watch: { usePolling: env.VITE_USE_POLLING === 'true' },
      cors: true,
      // DEV atrás do Traefik: aceitar o host público (ex.: platform.acme.test).
      allowedHosts: env.VITE_ALLOWED_HOSTS ? env.VITE_ALLOWED_HOSTS.split(',') : true,
    },
  };
});
