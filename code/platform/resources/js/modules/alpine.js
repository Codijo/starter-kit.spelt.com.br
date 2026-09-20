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
/**
 * x-row-menu — tira o menu de ações da linha de dentro dos overflows da tabela.
 *
 * O problema: o card da listagem tem `overflow-hidden` (pelos cantos arredondados) e o
 * embrulho da tabela tem `overflow-x-auto`. Pelo CSS, `overflow-y` não pode ser `visible`
 * quando o outro eixo não é — vira `auto`. Ou seja, o `overflow-x-auto` recorta na VERTICAL
 * também, e o menu da ÚLTIMA linha cai fora da caixa e simplesmente não aparece. Com poucas
 * linhas some em qualquer linha, porque não há altura para ele em lugar nenhum.
 *
 * Trocar os overflows por colunas responsivas resolveria numa tela e quebraria o scroll
 * horizontal das tabelas largas no mobile. Esta diretiva resolve em todas sem tocar no
 * layout: ao abrir, o menu passa a `position: fixed` — que escapa do recorte de qualquer
 * ancestral com overflow — ancorado ao botão que o abriu.
 *
 * Uso: `x-row-menu` no elemento do menu. O gatilho é o irmão anterior (o botão de "…").
 * As classes `absolute right-0 mt-2` continuam no HTML e são anuladas por estilo inline.
 *
 * 🔴 NÃO reintroduzir MutationObserver aqui. A primeira versão observava `style` para saber
 * quando o menu aparecia — e posicionar escreve em `style`, então cada posicionamento
 * disparava o observer, que posicionava de novo: laço infinito, 100% de CPU ao abrir o menu.
 * O gancho correto é o clique no próprio gatilho, que é o único jeito de abrir estes menus.
 *
 * Reposicionar enquanto está aberto (scroll/resize) é seguro porque nada aqui reage a estilo.
 */
Alpine.directive('row-menu', (el, {}, { cleanup }) => {
  const trigger = el.previousElementSibling;

  if (!trigger) return;

  const isVisible = () => el.style.display !== 'none' && el.offsetParent !== null;

  const place = () => {
    if (!isVisible()) return;

    // Lê tudo antes de escrever — o `w-44`/`w-56` já define a largura, então medir
    // no modo absoluto vale para o fixo.
    const rect = trigger.getBoundingClientRect();
    const width = el.offsetWidth;
    const height = el.offsetHeight;
    const gap = 8;

    // Alinhado à direita do botão (o que o `right-0` fazia), sem sair da tela.
    const left = Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8));
    // Abre para baixo; inverte quando não cabe até o fim da viewport e cabe acima.
    const below = rect.bottom + gap;
    const top = below + height > window.innerHeight && rect.top - gap - height > 0
      ? rect.top - gap - height
      : below;

    // Só escreve quando muda de fato. Tirar o menu do fluxo do container pode fazer o
    // navegador ajustar o scroll dele, o que dispara `scroll` e chama isto de novo —
    // com a comparação, a segunda passada não escreve nada e a coisa para aí.
    const styles = { position: 'fixed', margin: '0px', left: left + 'px', top: top + 'px' };

    for (const [prop, value] of Object.entries(styles)) {
      if (el.style[prop] !== value) {
        el.style[prop] = value;
      }
    }
  };

  /**
   * Posicionar exige o menu já no layout (`getBoundingClientRect`/`offsetHeight` não medem
   * elemento com `display:none`), e não dá para assumir em que frame o Alpine vai ter
   * aplicado o `x-show` — varia com transição e com a carga da página. Em vez de apostar num
   * frame só, tenta por alguns e para no primeiro em que o menu estiver visível.
   *
   * Quando o clique FECHA o menu, as tentativas simplesmente se esgotam sem fazer nada.
   */
  const placeWhenVisible = (framesRestantes = 6) => {
    requestAnimationFrame(() => {
      if (isVisible()) {
        place();

        return;
      }

      if (framesRestantes > 0) placeWhenVisible(framesRestantes - 1);
    });
  };

  const onTriggerClick = () => placeWhenVisible();

  // Enquanto fechado o menu não renderiza, então não há o que reposicionar nem o que limpar:
  // a cada abertura as coordenadas são recalculadas do zero.
  const onViewportChange = () => place();

  trigger.addEventListener('click', onTriggerClick);
  window.addEventListener('scroll', onViewportChange, true);
  window.addEventListener('resize', onViewportChange);

  cleanup(() => {
    trigger.removeEventListener('click', onTriggerClick);
    window.removeEventListener('scroll', onViewportChange, true);
    window.removeEventListener('resize', onViewportChange);
  });
});

Alpine.start();
