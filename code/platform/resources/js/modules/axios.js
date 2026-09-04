/**
 * axios do Platform. Fala com a API do produto (window.App.apiUrl) usando Bearer token
 * lido do window.App (injetado server-side do cookie). 401 → limpa e vai pro /logout.
 * Espelha o padrão do App do Spelt, sem a delegação/headers de tenant (single-Seller).
 */
import axios from 'axios';
import NProgress from 'nprogress';
import 'nprogress/nprogress.css';

NProgress.configure({ showSpinner: false });

let active = 0;
const start = () => { if (active++ === 0) NProgress.start(); };
const stop = () => { active = Math.max(active - 1, 0); if (active === 0) NProgress.done(); };

axios.defaults.baseURL = window.App?.apiUrl || '';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

axios.interceptors.request.use(
  (config) => {
    start();
    const token = window.App?.getToken?.();
    if (token) config.headers['Authorization'] = `Bearer ${token}`;
    return config;
  },
  (error) => { stop(); return Promise.reject(error); }
);

axios.interceptors.response.use(
  (response) => { stop(); return response; },
  (error) => {
    stop();
    if (error.response?.status === 401) {
      window.App?.clearToken?.();
      window.location.href = '/logout';
    }
    return Promise.reject(error);
  }
);

window.axios = axios;
