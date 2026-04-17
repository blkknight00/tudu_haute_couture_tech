import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  CheckCircle2, Circle, Building2, Users, FolderKanban,
  Smartphone, Sparkles, ChevronRight, ChevronLeft, Loader2
} from 'lucide-react';
import axios from 'axios';

const API_BASE = import.meta.env.VITE_API_URL || 'http://localhost/tudu_haute_couture_tech/api';

// ── Pasos del onboarding ──────────────────────────────────────────────
const STEPS = [
  {
    id: 1, icon: Building2, emoji: '🏢',
    title: '¿Cómo se llama tu empresa?',
    desc: 'Personaliza TuDu con el nombre de tu organización.',
  },
  {
    id: 2, icon: FolderKanban, emoji: '📋',
    title: 'Crea tu primer proyecto',
    desc: 'Un proyecto es donde viven tus tareas. ¡Empieza con uno!',
  },
  {
    id: 3, icon: Users, emoji: '👥',
    title: 'Invita a tu equipo',
    desc: 'Agrega compañeros por teléfono, como WhatsApp.',
  },
  {
    id: 4, icon: Smartphone, emoji: '📱',
    title: 'Conecta tu WhatsApp',
    desc: 'Recibe notificaciones y responde tareas desde WhatsApp.',
  },
  {
    id: 5, icon: Sparkles, emoji: '🚀',
    title: '¡Todo listo!',
    desc: 'Tu espacio de trabajo está configurado.',
  },
];

interface OnboardingData {
  empresa: string;
  proyecto: string;
  proyecto_desc: string;
  teammates: string[];
  whatsapp: string;
}

export default function OnboardingPage() {
  const navigate = useNavigate();
  const [step, setStep]       = useState(1);
  const [loading, setLoading]  = useState(false);
  const [error, setError]     = useState('');
  const [done, setDone]       = useState<Set<number>>(new Set());

  const [data, setData] = useState<OnboardingData>({
    empresa: '', proyecto: 'Mi primer proyecto',
    proyecto_desc: '', teammates: ['', ''], whatsapp: '',
  });

  const totalSteps = STEPS.length;
  const currentStep = STEPS[step - 1];
  const progress = ((step - 1) / (totalSteps - 1)) * 100;

  const set = (field: keyof OnboardingData) => (val: string) =>
    setData(prev => ({ ...prev, [field]: val }));

  const setTeammate = (i: number, val: string) =>
    setData(prev => {
      const teammates = [...prev.teammates];
      teammates[i] = val;
      return { ...prev, teammates };
    });

  // ── Avanzar paso ─────────────────────────────────────────────────
  const next = async () => {
    setError('');

    if (step === 1 && !data.empresa.trim()) {
      setError('Ingresa el nombre de tu empresa'); return;
    }
    if (step === 2 && !data.proyecto.trim()) {
      setError('Ingresa el nombre del proyecto'); return;
    }

    setLoading(true);
    await saveStep(step);
    setLoading(false);
    setDone(prev => new Set([...prev, step]));

    if (step < totalSteps) {
      setStep(s => s + 1);
    } else {
      navigate('/');
    }
  };

  const prev = () => { setError(''); setStep(s => s - 1); };

  // ── Guardar datos en el servidor ──────────────────────────────────
  const saveStep = async (stepNum: number) => {
    try {
      if (stepNum === 1 && data.empresa.trim()) {
        await axios.post(`${API_BASE}/organizations.php?action=update_name`,
          { nombre: data.empresa }, { withCredentials: true });
      }
      if (stepNum === 2 && data.proyecto.trim()) {
        await axios.post(`${API_BASE}/projects.php?action=save`,
          { nombre: data.proyecto, descripcion: data.proyecto_desc }, { withCredentials: true });
      }
      if (stepNum === 3) {
        const phones = data.teammates.filter(t => t.trim());
        if (phones.length > 0) {
          await axios.post(`${API_BASE}/invitations.php`,
            { phones }, { withCredentials: true });
        }
      }
      if (stepNum === 4 && data.whatsapp.trim()) {
        await axios.post(`${API_BASE}/users.php?action=update_whatsapp`,
          { whatsapp: data.whatsapp }, { withCredentials: true });
      }
    } catch (_) {
      // No bloquear el onboarding si un paso falla
    }
  };

  return (
    <div style={{
      minHeight: '100vh',
      background: 'var(--color-background, #09090b)',
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      padding: '24px', fontFamily: 'var(--font-sans, system-ui)',
    }}>
      {/* Background */}
      <div style={{
        position: 'fixed', inset: 0, pointerEvents: 'none',
        background: 'radial-gradient(ellipse 60% 40% at 50% 0%, rgba(124,58,237,0.08) 0%, transparent 70%)',
      }} />

      <div style={{ width: '100%', maxWidth: '480px', position: 'relative' }}>

        {/* Logo */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '32px' }}>
          <img src="/tudu-logo-transparent.png" alt="TuDu Logo" style={{ height: '32px', width: 'auto', objectFit: 'contain' }} />
          <span style={{
            marginLeft: 'auto', fontSize: '12px', color: '#52525b',
          }}>Paso {step} de {totalSteps}</span>
        </div>

        {/* Progress bar */}
        <div style={{
          height: '4px', borderRadius: '4px', background: '#27272a', marginBottom: '32px', overflow: 'hidden',
        }}>
          <div style={{
            height: '100%', borderRadius: '4px',
            background: 'linear-gradient(90deg,#7c3aed,#4f46e5)',
            width: `${progress}%`,
            transition: 'width 0.4s ease',
          }} />
        </div>

        {/* Step indicators */}
        <div style={{ display: 'flex', gap: '8px', marginBottom: '28px', justifyContent: 'center' }}>
          {STEPS.map(s => (
            <div key={s.id} style={{
              display: 'flex', alignItems: 'center',
            }}>
              <div style={{
                width: '28px', height: '28px', borderRadius: '50%',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                border: '1.5px solid',
                borderColor: done.has(s.id) ? '#7c3aed' : step === s.id ? 'rgba(124,58,237,0.5)' : '#27272a',
                background: done.has(s.id) ? 'rgba(124,58,237,0.2)' : step === s.id ? 'rgba(124,58,237,0.08)' : 'transparent',
                fontSize: '11px', transition: 'all 0.2s',
              }}>
                {done.has(s.id)
                  ? <CheckCircle2 size={14} color="#a78bfa" />
                  : step === s.id
                    ? <span style={{ color: '#a78bfa', fontWeight: '600' }}>{s.id}</span>
                    : <Circle size={10} color="#3f3f46" />
                }
              </div>
              {s.id < STEPS.length && (
                <div style={{
                  width: '24px', height: '1px',
                  background: done.has(s.id) ? '#7c3aed' : '#27272a',
                  transition: 'background 0.3s',
                }} />
              )}
            </div>
          ))}
        </div>

        {/* Main card */}
        <div style={{
          background: '#18181b', border: '1px solid #27272a',
          borderRadius: '20px', padding: '32px',
          boxShadow: '0 25px 50px rgba(0,0,0,0.4)',
        }}>

          {/* Step header */}
          <div style={{ fontSize: '40px', marginBottom: '12px' }}>{currentStep.emoji}</div>
          <h2 style={{ fontSize: '20px', fontWeight: '700', color: '#fff', marginBottom: '6px' }}>
            {currentStep.title}
          </h2>
          <p style={{ fontSize: '13px', color: '#71717a', marginBottom: '24px' }}>
            {currentStep.desc}
          </p>

          {/* Error */}
          {error && (
            <div style={{
              background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.3)',
              borderRadius: '10px', padding: '10px 14px', marginBottom: '16px',
              fontSize: '13px', color: '#fca5a5',
            }}>{error}</div>
          )}

          {/* ── Contenido por paso ──────────────────────────────── */}

          {/* Paso 1: Nombre empresa */}
          {step === 1 && (
            <OnboardingInput
              label="Nombre de tu empresa"
              value={data.empresa}
              onChange={set('empresa')}
              placeholder="Empresa S.A. de C.V."
              autoFocus
            />
          )}

          {/* Paso 2: Proyecto */}
          {step === 2 && (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
              <OnboardingInput
                label="Nombre del proyecto"
                value={data.proyecto}
                onChange={set('proyecto')}
                placeholder="Ej: Rediseño de sitio web"
                autoFocus
              />
              <OnboardingInput
                label="Descripción (opcional)"
                value={data.proyecto_desc}
                onChange={set('proyecto_desc')}
                placeholder="¿De qué trata este proyecto?"
              />
            </div>
          )}

          {/* Paso 3: Invitar teammates */}
          {step === 3 && (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
              <p style={{ fontSize: '12px', color: '#52525b', marginBottom: '4px' }}>
                📲 Solo necesitas su número — como en WhatsApp. Si no están en TuDu, les llegará una invitación.
              </p>
              {data.teammates.map((t, i) => (
                <OnboardingInput
                  key={i}
                  label={`Compañero ${i + 1}`}
                  value={t}
                  onChange={val => setTeammate(i, val)}
                  placeholder="+52 55 1234 5678 o nombre@email.mx"
                />
              ))}
              <button
                onClick={() => setData(prev => ({ ...prev, teammates: [...prev.teammates, ''] }))}
                style={{
                  background: 'none', border: '1px dashed #3f3f46', borderRadius: '10px',
                  color: '#52525b', fontSize: '13px', padding: '8px', cursor: 'pointer',
                }}
              >
                + Agregar otro
              </button>
            </div>
          )}

          {/* Paso 4: WhatsApp */}
          {step === 4 && (
            <div>
              <div style={{
                background: 'rgba(5,150,105,0.08)', border: '1px solid rgba(5,150,105,0.2)',
                borderRadius: '12px', padding: '14px 16px', marginBottom: '16px',
                fontSize: '12px', color: '#34d399', lineHeight: '1.7',
              }}>
                ✅ Responde tareas directamente desde WhatsApp<br />
                ✅ Recibe recordatorios automáticos<br />
                ✅ Comparte tareas sin que el cliente tenga TuDu
              </div>
              <OnboardingInput
                label="Tu número de WhatsApp"
                value={data.whatsapp}
                onChange={set('whatsapp')}
                placeholder="+52 55 1234 5678"
                type="tel"
                autoFocus
              />
            </div>
          )}

          {/* Paso 5: Éxito */}
          {step === 5 && (
            <div style={{ textAlign: 'center', padding: '8px 0' }}>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                {[
                  '✅ Organización configurada',
                  '📋 Primer proyecto creado',
                  '👥 Invitaciones enviadas',
                  '📱 WhatsApp conectado',
                ].map((item, i) => (
                  done.has(i + 1)
                    ? <div key={i} style={{
                        background: 'rgba(5,150,105,0.08)', border: '1px solid rgba(5,150,105,0.2)',
                        borderRadius: '10px', padding: '10px 14px', fontSize: '13px', color: '#34d399',
                      }}>{item}</div>
                    : <div key={i} style={{
                        background: '#09090b', border: '1px solid #27272a',
                        borderRadius: '10px', padding: '10px 14px', fontSize: '13px', color: '#52525b',
                      }}>{item.replace(/^[^ ]+ /, '⏭️ ')}</div>
                ))}
              </div>
            </div>
          )}

          {/* Navigation buttons */}
          <div style={{ display: 'flex', gap: '8px', marginTop: '24px' }}>
            {step > 1 && (
              <button onClick={prev} style={{
                flex: '0 0 auto', padding: '11px 16px', borderRadius: '12px',
                background: '#09090b', border: '1px solid #27272a',
                color: '#71717a', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '4px',
                fontSize: '13px',
              }}>
                <ChevronLeft size={14} /> Atrás
              </button>
            )}
            <button onClick={next} disabled={loading} style={{
              flex: 1, padding: '12px', borderRadius: '12px',
              background: 'linear-gradient(135deg,#7c3aed,#4f46e5)',
              color: 'white', border: 'none', cursor: 'pointer',
              fontSize: '14px', fontWeight: '600',
              display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px',
              opacity: loading ? 0.7 : 1, transition: 'opacity 0.15s',
            }}>
              {loading
                ? <><Loader2 size={14} style={{ animation: 'spin 1s linear infinite' }} /> Guardando...</>
                : step === totalSteps
                  ? <><Sparkles size={14} /> ¡Entrar a TuDu!</>
                  : <>Continuar <ChevronRight size={14} /></>
              }
            </button>
          </div>

          {/* Skip */}
          {step < totalSteps && step >= 3 && (
            <button onClick={() => { setDone(prev => new Set([...prev, step])); setStep(s => s + 1); }} style={{
              display: 'block', width: '100%', marginTop: '10px', background: 'none',
              border: 'none', color: '#3f3f46', fontSize: '12px', cursor: 'pointer',
            }}>
              Omitir este paso →
            </button>
          )}
        </div>
      </div>

      <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
    </div>
  );
}

// ── Helper component ──────────────────────────────────────────────────
function OnboardingInput({ label, value, onChange, placeholder, type = 'text', autoFocus }: {
  label: string; value: string; onChange: (v: string) => void;
  placeholder?: string; type?: string; autoFocus?: boolean;
}) {
  return (
    <div>
      <label style={{
        display: 'block', fontSize: '11px', fontWeight: '500',
        letterSpacing: '0.06em', textTransform: 'uppercase',
        color: '#71717a', marginBottom: '6px',
      }}>{label}</label>
      <input
        type={type} value={value} placeholder={placeholder}
        autoFocus={autoFocus}
        onChange={e => onChange(e.target.value)}
        style={{
          width: '100%', padding: '11px 14px', borderRadius: '10px',
          background: '#09090b', border: '1px solid #3f3f46',
          color: '#e4e4e7', fontSize: '14px', outline: 'none',
          boxSizing: 'border-box', transition: 'border-color 0.15s',
        }}
        onFocus={e => e.target.style.borderColor = 'rgba(124,58,237,0.6)'}
        onBlur={e => e.target.style.borderColor = '#3f3f46'}
      />
    </div>
  );
}
