import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import mask from '@alpinejs/mask';
import persist from '@alpinejs/persist';
import meStore from '@/stores/me';
import { flash } from '@/stores/flash';
import { confirm } from '@/stores/confirm';

Alpine.plugin(collapse);
Alpine.plugin(mask);
Alpine.plugin(persist);

// Stores GLOBAIS (disponíveis em toda página).
Alpine.store('me', meStore());    // contexto do usuário (a layout chama $store.me.load())
Alpine.store('flash', flash);     // mensagens transitórias (<x-ui.toast />)
Alpine.store('confirm', confirm); // modal de confirmação (<x-ui.confirm-modal />)

// Os COMPONENTES de página NÃO são registrados aqui. Cada tela coloca seu .js junto do
// blade (ex.: resources/views/web/studio/brands/index.js), carrega via @vite no rodapé do
// blade, e se auto-registra com document.addEventListener('alpine:init', () => Alpine.data(...)).
// Como o app.js roda no FIM do body (depois do content), o alpine:init já está registrado
// quando o Alpine.start() abaixo dispara. Sem lista central, sem conflito de merge.

window.Alpine = Alpine;
Alpine.start();
