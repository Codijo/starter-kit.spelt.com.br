import axios from 'axios';

/**
 * Store global com o contexto do usuário (/me). Carregado UMA vez pela shell e
 * consumido por header, sidebar e páginas (identidade + gating sem refetch por tela).
 * Espelha o `tenantMe` do App do Spelt.
 */
export default () => ({
  loading: true,
  loaded: false,
  error: null,

  user: null,
  account: null,
  subscription: null,
  entitlements: {},
  creditBalance: 0,
  hasAccess: false,

  async load() {
    if (this.loaded) return;
    try {
      const { data } = await axios.get('/api/me');
      const d = data.data || {};
      this.user = d.user;
      this.account = d.account;
      this.subscription = d.subscription;
      this.entitlements = d.entitlements || {};
      this.creditBalance = d.credit_balance ?? 0;
      this.hasAccess = !!d.has_access;
      this.loaded = true;
    } catch (e) {
      this.error = 'Falha ao carregar sua conta.';
    } finally {
      this.loading = false;
    }
  },

  /**
   * Abre o portal Customer do Spelt (gestão/conversão) via SSO reverso. `target` é um caminho
   * opcional dentro do portal (ex.: '/guard/me/billing/subscription') para deep-link — honrado
   * quando o portal suportar redirect pós-SSO; sem ele, cai na raiz do portal.
   */
  async managePortal(target = null) {
    try {
      const { data } = await axios.post('/api/spelt/portal-link', target ? { target } : {});
      window.location.href = data.data.url;
    } catch (e) {
      this.error = 'Não foi possível abrir o portal de assinatura.';
    }
  },

  // ── Conversão: o produto INSTIGA; a gestão/compra acontece no portal Customer ──

  trialEndsAt() {
    return this.subscription?.trial_ends_at ? new Date(this.subscription.trial_ends_at) : null;
  },
  isTrialing() {
    if (this.subscription?.status === 'trial') return true;
    const end = this.trialEndsAt();
    return !!end && end > new Date();
  },
  trialDaysLeft() {
    const end = this.trialEndsAt();
    if (!end) return null;
    return Math.max(0, Math.ceil((end - new Date()) / 86400000));
  },

  /** Caminho no portal Customer para cada tipo de nudge. */
  portalTarget(action) {
    return {
      subscription: '/guard/me/billing/subscription',
      credits: '/guard/me/billing/credit',
    }[action] || null;
  },

  /**
   * Nudge de conversão mais relevante para o estado atual (ou null). Puramente derivado do /me.
   * Limite de "crédito baixo" = 5 (ajuste por produto). Prioridade: bloqueio > trial > créditos.
   */
  nudge() {
    if (!this.loaded) return null;

    if (!this.hasAccess) {
      return { key: 'blocked', tone: 'danger', text: 'Sua assinatura não está ativa.', cta: 'Regularizar', action: 'subscription' };
    }
    if (this.isTrialing()) {
      const d = this.trialDaysLeft();
      const plan = this.subscription?.plan_name;
      // Últimos dias → urgência (tom de alerta). Antes disso → conversão por valor, não só o prazo.
      if (d <= 1) {
        const when = d === 0 ? 'termina hoje' : 'termina amanhã';
        return { key: 'trial', tone: 'warning', action: 'subscription', cta: 'Assinar agora',
          text: `Seu teste ${when} — assine${plan ? ` o ${plan}` : ''} e não perca o acesso ao que você já criou.` };
      }
      return { key: 'trial', tone: 'accent', action: 'subscription', cta: 'Assinar agora',
        text: `Gostou${plan ? ` do ${plan}` : ''}? Assine agora e garanta acesso contínuo — seu teste acaba em ${d} dias.` };
    }
    if (this.creditBalance <= 0) {
      return { key: 'no_credits', tone: 'warning', text: 'Seus créditos acabaram.', cta: 'Comprar créditos', action: 'credits' };
    }
    if (this.creditBalance <= 5) {
      const n = this.creditBalance;
      return { key: 'low_credits', tone: 'warning', text: `Você tem ${n} crédito${n === 1 ? '' : 's'} restante${n === 1 ? '' : 's'}.`, cta: 'Comprar créditos', action: 'credits' };
    }
    return null;
  },

  /** Entitlements como lista [ [chave, valor], ... ] para o front. */
  entitlementList() {
    return Object.entries(this.entitlements || {});
  },

  initials() {
    const name = (this.user?.name || '').trim();
    if (!name) return 'U';
    return name.split(/\s+/).slice(0, 2).map((w) => (w[0] || '').toUpperCase()).join('') || 'U';
  },
});
