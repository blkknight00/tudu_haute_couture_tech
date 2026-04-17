import { Link } from 'react-router-dom';
import { useState, useEffect } from 'react';
import {
  CheckCircle2, X, Zap, CreditCard, Building2,
  User, ChevronDown, ChevronUp, Shield, Star
} from 'lucide-react';

// ── Plan Data ─────────────────────────────────────────────────────────
const editions = {
  standalone: {
    label: 'Stand Alone', icon: '👤',
    desc: 'Para freelancers y profesionales independientes',
    plans: [
      {
        id: 'starter', name: 'Starter',
        prices: { MXN: { monthly: 0, annual: 0 }, USD: { monthly: 0, annual: 0 }, EUR: { monthly: 0, annual: 0 } },
        color: '#52525b', borderColor: '#27272a',
        badge: null,
        features: [
          { text: '1 usuario', included: true },
          { text: '3 proyectos', included: true },
          { text: 'Tareas ilimitadas', included: false, limit: '50 tareas' },
          { text: 'WhatsApp Bot', included: false },
          { text: 'IA Assistant', included: false },
          { text: 'Soporte email', included: true },
        ],
        cta: 'Empezar gratis',
        ctaVariant: 'outline',
      },
      {
        id: 'pro', name: 'Pro',
        prices: { MXN: { monthly: 199, annual: 166 }, USD: { monthly: 10, annual: 8 }, EUR: { monthly: 9, annual: 7 } },
        color: '#7c3aed', borderColor: 'rgba(124,58,237,0.5)',
        badge: '⭐ Popular',
        features: [
          { text: '3 usuarios', included: true },
          { text: '10 proyectos', included: true },
          { text: 'Tareas ilimitadas', included: true },
          { text: 'WhatsApp Bot', included: true },
          { text: 'IA Assistant', included: false },
          { text: 'Soporte prioritario', included: true },
        ],
        cta: 'Comenzar 14 días gratis',
        ctaVariant: 'filled',
      },
    ],
  },
  corp: {
    label: 'Corp', icon: '🏢',
    desc: 'Para empresas y equipos de trabajo',
    plans: [
      {
        id: 'starter', name: 'Corp Starter',
        prices: { MXN: { monthly: 399, annual: 332 }, USD: { monthly: 20, annual: 16 }, EUR: { monthly: 18, annual: 15 } },
        color: '#52525b', borderColor: '#27272a',
        badge: null,
        features: [
          { text: '5 usuarios', included: true },
          { text: '10 proyectos', included: true },
          { text: 'Tareas ilimitadas', included: true },
          { text: 'WhatsApp Bot', included: true },
          { text: 'IA Assistant', included: false },
          { text: 'Soporte email', included: true },
        ],
        cta: 'Comenzar 14 días gratis',
        ctaVariant: 'outline',
      },
      {
        id: 'pro', name: 'Corp Pro',
        prices: { MXN: { monthly: 999, annual: 832 }, USD: { monthly: 50, annual: 41 }, EUR: { monthly: 45, annual: 38 } },
        color: '#7c3aed', borderColor: 'rgba(124,58,237,0.5)',
        badge: '⭐ Más popular',
        features: [
          { text: '15 usuarios', included: true },
          { text: '50 proyectos', included: true },
          { text: 'Tareas ilimitadas', included: true },
          { text: 'WhatsApp Bot', included: true },
          { text: 'IA Assistant', included: true },
          { text: 'Soporte prioritario', included: true },
        ],
        cta: 'Comenzar 14 días gratis',
        ctaVariant: 'filled',
      },
      {
        id: 'agency', name: 'Corp Agency',
        prices: { MXN: { monthly: 2499, annual: 2082 }, USD: { monthly: 125, annual: 104 }, EUR: { monthly: 110, annual: 92 } },
        color: '#f59e0b', borderColor: 'rgba(245,158,11,0.4)',
        badge: '🚀 Para agencias',
        features: [
          { text: '50 usuarios', included: true },
          { text: 'Proyectos ilimitados', included: true },
          { text: 'Tareas ilimitadas', included: true },
          { text: 'WhatsApp Bot', included: true },
          { text: 'IA Assistant', included: true },
          { text: 'Soporte dedicado 24/7', included: true },
        ],
        cta: 'Comenzar 14 días gratis',
        ctaVariant: 'gold',
      },
    ],
  },
};

const comparisonFeatures = [
  { label: 'Usuarios', standalone_starter: '1', standalone_pro: '3', corp_starter: '5', corp_pro: '15', corp_agency: '50' },
  { label: 'Proyectos', standalone_starter: '3', standalone_pro: '10', corp_starter: '10', corp_pro: '50', corp_agency: 'Ilimitados' },
  { label: 'Tareas', standalone_starter: '50', standalone_pro: 'Ilimitadas', corp_starter: 'Ilimitadas', corp_pro: 'Ilimitadas', corp_agency: 'Ilimitadas' },
  { label: 'WhatsApp Bot', standalone_starter: false, standalone_pro: true, corp_starter: true, corp_pro: true, corp_agency: true },
  { label: 'IA Assistant', standalone_starter: false, standalone_pro: false, corp_starter: false, corp_pro: true, corp_agency: true },
  { label: 'Kanban & Calendario', standalone_starter: true, standalone_pro: true, corp_starter: true, corp_pro: true, corp_agency: true },
  { label: 'OXXO Pay', standalone_starter: true, standalone_pro: true, corp_starter: true, corp_pro: true, corp_agency: true },
  { label: 'Soporte', standalone_starter: 'Email', standalone_pro: 'Prioritario', corp_starter: 'Email', corp_pro: 'Prioritario', corp_agency: '24/7 Dedicado' },
];

const faqs = [
  {
    q: '¿Necesito tarjeta de crédito para el trial?',
    a: 'No. Puedes iniciar los 14 días de prueba gratuita sin tarjeta. Solo la necesitas cuando decidas continuar.',
  },
  {
    q: '¿Puedo pagar en OXXO?',
    a: 'Sí. Generamos una referencia de pago que puedes pagar en efectivo en cualquier OXXO de México. El plan se activa en minutos.',
  },
  {
    q: '¿Puedo cancelar en cualquier momento?',
    a: 'Sí, sin penalizaciones. Tu plan se mantiene activo hasta el fin del período pagado y después no se cobra más.',
  },
  {
    q: '¿Puedo cambiar de plan?',
    a: 'Sí. Puedes subir o bajar de plan en cualquier momento desde tu panel. Los cambios aplican en el próximo ciclo.',
  },
  {
    q: '¿Qué pasa cuando termina el trial?',
    a: 'Te avisamos 3 días antes por WhatsApp y correo. Si no agregas un método de pago, pasas al plan Starter gratuito con límites básicos.',
  },
];

export default function PricingPage() {
  const [billing, setBilling] = useState<'monthly' | 'annual'>('monthly');
  const [activeEdition, setActiveEdition] = useState<'standalone' | 'corp'>('corp');
  const [openFaq, setOpenFaq] = useState<number | null>(null);
  const [showTable, setShowTable] = useState(false);

  const [currency, setCurrency] = useState<'MXN' | 'USD' | 'EUR'>('MXN');

  useEffect(() => {
    // Detect region for pricing
    try {
      const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
      if (tz.includes('Mexico')) {
        setCurrency('MXN');
      } else if (tz.includes('Europe')) {
        setCurrency('EUR');
      } else {
        setCurrency('USD');
      }
    } catch {
      setCurrency('USD');
    }
  }, []);

  const currentEdition = editions[activeEdition];

  const getPriceData = (plan: typeof currentEdition.plans[0]) => plan.prices[currency];

  const getPrice = (plan: typeof currentEdition.plans[0]) => {
    const prices = getPriceData(plan);
    const p = billing === 'annual' ? prices.annual : prices.monthly;
    if (p === 0) return 'Gratis';
    const symbol = currency === 'EUR' ? '€' : '$';
    return `${symbol}${p.toLocaleString()}`;
  };

  return (
    <div style={{ background: 'transparent', color: '#e4e4e7', fontFamily: 'system-ui, sans-serif', minHeight: '100vh', position: 'relative' }}>

      {/* Nav mini */}
      <nav style={{
        position: 'sticky', top: 0, zIndex: 50,
        padding: '0 24px', height: '60px',
        display: 'flex', alignItems: 'center',
        background: 'rgba(9,9,11,0.8)', backdropFilter: 'blur(20px)',
        borderBottom: '1px solid #1c1c1f',
      }}>
        <div style={{ maxWidth: '1100px', margin: '0 auto', width: '100%', display: 'flex', alignItems: 'center', gap: '16px' }}>
          <Link to="/" style={{ display: 'flex', alignItems: 'center', gap: '8px', textDecoration: 'none', color: 'white' }}>
            <img src="/tudu-logo-transparent.png" alt="TuDu Logo" style={{ height: '28px', width: 'auto', objectFit: 'contain' }} />
          </Link>
          <span style={{ color: '#3f3f46' }}>·</span>
          <span style={{ fontSize: '13px', color: '#71717a' }}>Planes y Precios</span>
          <div style={{ marginLeft: 'auto', display: 'flex', gap: '8px' }}>
            <Link to="/login" style={{ fontSize: '13px', color: '#71717a', textDecoration: 'none', padding: '6px 12px' }}>Iniciar sesión</Link>
            <Link to="/register" style={{ fontSize: '13px', fontWeight: '600', color: 'white', textDecoration: 'none', padding: '6px 16px', borderRadius: '8px', background: 'linear-gradient(135deg,#7c3aed,#4f46e5)' }}>Empezar gratis</Link>
          </div>
        </div>
      </nav>

      <div style={{ maxWidth: '1100px', margin: '0 auto', padding: '60px 24px', position: 'relative' }}>

        {/* Header */}
        <div style={{ textAlign: 'center', marginBottom: '48px' }}>
          <h1 style={{ fontSize: 'clamp(32px,5vw,56px)', fontWeight: '900', letterSpacing: '-2px', marginBottom: '12px' }}>
            Planes y{' '}
            <span style={{ background: 'linear-gradient(135deg,#a78bfa,#7c3aed)', WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent' }}>
              Precios
            </span>
          </h1>
          <p style={{ fontSize: '16px', color: '#71717a', marginBottom: '28px' }}>
            14 días de prueba gratuita · Sin tarjeta de crédito · Cancela cuando quieras
          </p>

          {/* Billing toggle */}
          <div style={{ display: 'inline-flex', alignItems: 'center', gap: '12px', padding: '6px', borderRadius: '14px', background: '#18181b', border: '1px solid #27272a' }}>
            {(['monthly', 'annual'] as const).map(b => (
              <button key={b} onClick={() => setBilling(b)} style={{
                padding: '8px 20px', borderRadius: '10px', border: 'none',
                fontSize: '13px', fontWeight: '600', cursor: 'pointer',
                transition: 'all 0.2s',
                background: billing === b ? 'linear-gradient(135deg,#7c3aed,#4f46e5)' : 'transparent',
                color: billing === b ? 'white' : '#71717a',
              }}>
                {b === 'monthly' ? 'Mensual' : 'Anual'}
                {b === 'annual' && (
                  <span style={{
                    marginLeft: '6px', fontSize: '10px', padding: '1px 6px', borderRadius: '10px',
                    background: billing === 'annual' ? 'rgba(255,255,255,0.2)' : 'rgba(34,197,94,0.15)',
                    color: billing === 'annual' ? 'white' : '#4ade80',
                  }}>2 meses gratis</span>
                )}
              </button>
            ))}
          </div>

          <div style={{ marginTop: '24px', display: 'flex', justifyContent: 'center', gap: '8px' }}>
            {(['MXN', 'USD', 'EUR'] as const).map(c => (
              <button key={c} onClick={() => setCurrency(c)} style={{
                padding: '4px 12px', borderRadius: '20px', border: '1px solid',
                borderColor: currency === c ? 'rgba(124,58,237,0.5)' : '#27272a',
                background: currency === c ? 'rgba(124,58,237,0.1)' : 'transparent',
                color: currency === c ? '#a78bfa' : '#52525b',
                fontSize: '12px', cursor: 'pointer', transition: 'all 0.2s',
              }}>
                {c}
              </button>
            ))}
          </div>
        </div>

        {/* Edition tabs */}
        <div style={{ display: 'flex', justifyContent: 'center', gap: '10px', marginBottom: '36px' }}>
          {(Object.entries(editions) as [string, typeof editions.corp][]).map(([key, ed]) => (
            <button key={key} onClick={() => setActiveEdition(key as 'standalone' | 'corp')} style={{
              display: 'flex', alignItems: 'center', gap: '8px',
              padding: '10px 24px', borderRadius: '12px', border: '1px solid',
              borderColor: activeEdition === key ? 'rgba(124,58,237,0.5)' : '#27272a',
              background: activeEdition === key ? 'rgba(124,58,237,0.1)' : '#111113',
              color: activeEdition === key ? '#a78bfa' : '#71717a',
              fontWeight: '600', fontSize: '14px', cursor: 'pointer',
              transition: 'all 0.2s',
            }}>
              <span>{ed.icon}</span>
              <span>{ed.label}</span>
            </button>
          ))}
        </div>

        <p style={{ textAlign: 'center', fontSize: '13px', color: '#52525b', marginBottom: '32px' }}>
          {currentEdition.desc}
        </p>

        {/* Pricing cards */}
        <div style={{
          display: 'grid',
          gridTemplateColumns: `repeat(${currentEdition.plans.length}, 1fr)`,
          gap: '16px', marginBottom: '48px',
          alignItems: 'start',
        }}>
          {currentEdition.plans.map(plan => (
            <div key={plan.id} style={{
              borderRadius: '20px', padding: '28px',
              background: plan.ctaVariant === 'filled' ? 'linear-gradient(135deg,rgba(124,58,237,0.12),rgba(79,70,229,0.08))' : '#111113',
              border: `1px solid ${plan.borderColor}`,
              position: 'relative',
              boxShadow: plan.ctaVariant === 'filled' ? '0 0 40px rgba(124,58,237,0.15)' : 'none',
              transition: 'transform 0.2s',
            }}
            onMouseEnter={e => (e.currentTarget as HTMLElement).style.transform = 'translateY(-4px)'}
            onMouseLeave={e => (e.currentTarget as HTMLElement).style.transform = ''}
            >
              {/* Badge */}
              {plan.badge && (
                <div style={{
                  position: 'absolute', top: '-12px', left: '50%', transform: 'translateX(-50%)',
                  padding: '3px 14px', borderRadius: '20px', fontSize: '11px', fontWeight: '700',
                  whiteSpace: 'nowrap',
                  background: plan.ctaVariant === 'gold'
                    ? 'linear-gradient(135deg,#f59e0b,#d97706)'
                    : 'linear-gradient(135deg,#7c3aed,#4f46e5)',
                  color: 'white',
                }}>{plan.badge}</div>
              )}

              <div style={{ fontSize: '15px', fontWeight: '700', marginBottom: '4px', color: plan.ctaVariant === 'filled' ? '#a78bfa' : '#e4e4e7' }}>
                {plan.name}
              </div>

              {/* Price */}
              <div style={{ marginBottom: '24px' }}>
                <div style={{ display: 'flex', alignItems: 'flex-end', gap: '4px', lineHeight: 1 }}>
                  <span style={{ fontSize: '38px', fontWeight: '900', letterSpacing: '-2px', color: plan.ctaVariant === 'filled' ? '#c4b5fd' : '#fff' }}>
                    {getPrice(plan)}
                  </span>
                  {getPriceData(plan).monthly > 0 && (
                    <span style={{ fontSize: '13px', color: '#52525b', paddingBottom: '6px' }}>
                      {currency}/{billing === 'annual' ? 'mes' : 'mes'}
                    </span>
                  )}
                </div>
                {billing === 'annual' && getPriceData(plan).monthly > 0 && (
                  <div style={{ fontSize: '11px', color: '#4ade80', marginTop: '4px' }}>
                    vs {currency === 'EUR' ? '€' : '$'}{getPriceData(plan).monthly.toLocaleString()} mensual (ahorras {currency === 'EUR' ? '€' : '$'}{((getPriceData(plan).monthly - getPriceData(plan).annual) * 12).toLocaleString()}/año)
                  </div>
                )}
              </div>

              {/* Features */}
              <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginBottom: '24px' }}>
                {plan.features.map(f => (
                  <div key={f.text} style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
                    {f.included
                      ? <CheckCircle2 size={14} color="#22c55e" />
                      : <X size={14} color="#3f3f46" />
                    }
                    <span style={{ fontSize: '13px', color: f.included ? '#a1a1aa' : '#3f3f46' }}>
                      {(f as any).limit ?? f.text}
                    </span>
                  </div>
                ))}
              </div>

              {/* CTA */}
              <Link to={`/register?edition=${activeEdition}&plan=${plan.id}&cycle=${billing}`} style={{
                display: 'block', textAlign: 'center', padding: '12px',
                borderRadius: '12px', textDecoration: 'none',
                fontSize: '13px', fontWeight: '700',
                transition: 'opacity 0.15s',
                ...(plan.ctaVariant === 'filled' ? {
                  background: 'linear-gradient(135deg,#7c3aed,#4f46e5)',
                  color: 'white',
                  boxShadow: '0 4px 20px rgba(124,58,237,0.4)',
                } : plan.ctaVariant === 'gold' ? {
                  background: 'linear-gradient(135deg,#f59e0b,#d97706)',
                  color: 'white',
                } : {
                  background: 'transparent',
                  color: '#a1a1aa',
                  border: '1px solid #27272a',
                }),
              }}
              onMouseEnter={e => (e.currentTarget as HTMLElement).style.opacity = '0.85'}
              onMouseLeave={e => (e.currentTarget as HTMLElement).style.opacity = '1'}
              >
                {plan.cta}
              </Link>
            </div>
          ))}
        </div>

        {/* Payment methods */}
        <div style={{ textAlign: 'center', marginBottom: '64px' }}>
          <p style={{ fontSize: '12px', color: '#52525b', marginBottom: '14px', textTransform: 'uppercase', letterSpacing: '1px' }}>Métodos de pago aceptados</p>
          <div style={{ display: 'flex', justifyContent: 'center', gap: '12px', flexWrap: 'wrap' }}>
            {[
              { label: '💳 Visa', color: '#1a1f71' },
              { label: '💳 Mastercard', color: '#eb001b' },
              { label: '💳 AMEX', color: '#2e77bc' },
              { label: '🏪 OXXO Pay', color: '#e2252b' },
              { label: '🔒 Stripe', color: '#635bff' },
            ].map(pm => (
              <div key={pm.label} style={{
                padding: '8px 16px', borderRadius: '8px',
                background: '#111113', border: '1px solid #27272a',
                fontSize: '13px', color: '#71717a',
              }}>{pm.label}</div>
            ))}
          </div>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px', marginTop: '12px' }}>
            <Shield size={12} color="#52525b" />
            <span style={{ fontSize: '11px', color: '#52525b' }}>Pagos encriptados y seguros · Cumplimiento PCI DSS</span>
          </div>
        </div>

        {/* Comparison table toggle */}
        <div style={{ marginBottom: '64px' }}>
          <button onClick={() => setShowTable(!showTable)} style={{
            display: 'flex', alignItems: 'center', gap: '8px',
            margin: '0 auto', padding: '10px 24px', borderRadius: '12px',
            background: '#111113', border: '1px solid #27272a',
            color: '#a1a1aa', fontSize: '13px', fontWeight: '600', cursor: 'pointer',
          }}>
            {showTable ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
            {showTable ? 'Ocultar' : 'Ver'} comparación completa de planes
          </button>

          {showTable && (
            <div style={{ marginTop: '20px', overflowX: 'auto' }}>
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
                <thead>
                  <tr>
                    <th style={{ padding: '12px 16px', textAlign: 'left', color: '#52525b', fontWeight: '500', border: '1px solid #1c1c1f', background: '#0d0d0f' }}>Característica</th>
                    {['Stand Alone Starter', 'Stand Alone Pro', 'Corp Starter', 'Corp Pro', 'Corp Agency'].map(h => (
                      <th key={h} style={{ padding: '12px 16px', textAlign: 'center', color: '#a1a1aa', fontWeight: '600', border: '1px solid #1c1c1f', background: '#0d0d0f', whiteSpace: 'nowrap' }}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {comparisonFeatures.map((row, i) => (
                    <tr key={row.label} style={{ background: i % 2 === 0 ? '#0a0a0c' : '#0d0d0f' }}>
                      <td style={{ padding: '12px 16px', color: '#71717a', border: '1px solid #1c1c1f' }}>{row.label}</td>
                      {(['standalone_starter', 'standalone_pro', 'corp_starter', 'corp_pro', 'corp_agency'] as const).map(key => {
                        const val = row[key];
                        return (
                          <td key={key} style={{ padding: '12px 16px', textAlign: 'center', border: '1px solid #1c1c1f' }}>
                            {typeof val === 'boolean'
                              ? val ? <CheckCircle2 size={14} color="#22c55e" style={{ margin: '0 auto' }} /> : <X size={14} color="#3f3f46" style={{ margin: '0 auto' }} />
                              : <span style={{ color: '#a1a1aa' }}>{val}</span>
                            }
                          </td>
                        );
                      })}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* FAQ */}
        <div style={{ maxWidth: '680px', margin: '0 auto 80px' }}>
          <h2 style={{ fontSize: '24px', fontWeight: '800', textAlign: 'center', marginBottom: '32px', letterSpacing: '-0.5px' }}>
            Preguntas frecuentes
          </h2>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
            {faqs.map((faq, i) => (
              <div key={i} style={{
                borderRadius: '12px', overflow: 'hidden',
                border: '1px solid', borderColor: openFaq === i ? 'rgba(124,58,237,0.3)' : '#1c1c1f',
                transition: 'border-color 0.2s',
              }}>
                <button onClick={() => setOpenFaq(openFaq === i ? null : i)} style={{
                  width: '100%', padding: '16px 20px',
                  display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                  background: openFaq === i ? 'rgba(124,58,237,0.06)' : '#111113',
                  border: 'none', cursor: 'pointer', textAlign: 'left',
                }}>
                  <span style={{ fontSize: '14px', fontWeight: '600', color: openFaq === i ? '#a78bfa' : '#e4e4e7' }}>
                    {faq.q}
                  </span>
                  {openFaq === i ? <ChevronUp size={14} color="#7c3aed" /> : <ChevronDown size={14} color="#52525b" />}
                </button>
                {openFaq === i && (
                  <div style={{ padding: '4px 20px 16px', background: 'rgba(124,58,237,0.04)', fontSize: '13px', color: '#71717a', lineHeight: 1.7 }}>
                    {faq.a}
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>

        {/* Bottom CTA */}
        <div style={{
          textAlign: 'center', padding: '48px',
          borderRadius: '20px',
          background: 'linear-gradient(135deg,rgba(124,58,237,0.1),rgba(79,70,229,0.06))',
          border: '1px solid rgba(124,58,237,0.2)',
        }}>
          <div style={{ fontSize: '32px', marginBottom: '12px' }}>🚀</div>
          <h3 style={{ fontSize: '24px', fontWeight: '800', marginBottom: '8px', letterSpacing: '-0.5px' }}>
            ¿Listo para empezar?
          </h3>
          <p style={{ color: '#71717a', fontSize: '14px', marginBottom: '24px' }}>
            Únete a más de 500 equipos que ya usan TuDu para gestionar sus proyectos.
          </p>
          <Link to="/register" style={{
            display: 'inline-flex', alignItems: 'center', gap: '8px',
            padding: '14px 32px', borderRadius: '14px',
            background: 'linear-gradient(135deg,#7c3aed,#4f46e5)',
            color: 'white', textDecoration: 'none',
            fontSize: '15px', fontWeight: '700',
            boxShadow: '0 0 40px rgba(124,58,237,0.3)',
          }}>
            <Zap size={16} /> Empieza gratis — 14 días sin cargo
          </Link>
          <p style={{ fontSize: '11px', color: '#3f3f46', marginTop: '12px' }}>
            Sin tarjeta · OXXO Pay disponible · Soporte en español
          </p>
        </div>
      </div>
    </div>
  );
}
