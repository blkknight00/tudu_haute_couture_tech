import { Link } from 'react-router-dom';
import { useState, useEffect } from 'react';
import { 
  MessageSquare, Zap, Shield, LayoutDashboard, 
  ArrowRight, Menu, X, Smartphone, BrainCircuit, Activity 
} from 'lucide-react';
import { Trans, useTranslation } from 'react-i18next';
import LanguageSwitcher from '../components/LanguageSwitcher';

export default function LandingPage() {
  const { t } = useTranslation();
  const [scrolled, setScrolled] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);

  useEffect(() => {
    // Force dark mode for landing page elegance
    document.documentElement.classList.add('dark');
    
    // Header scroll detection
    const handleScroll = () => setScrolled(window.scrollY > 20);
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  return (
    <div className="min-h-screen bg-transparent text-gray-200 font-sans overflow-x-hidden selection:bg-purple-500/30">
      
      {/* ── NAV ───────────────────────────────────────────────────── */}
      <nav className={`fixed top-0 left-0 right-0 z-50 px-6 transition-all duration-300 ${
        scrolled ? 'bg-zinc-950/80 backdrop-blur-xl border-b border-white/5' : 'bg-transparent'
      }`}>
        <div className="max-w-6xl mx-auto flex items-center justify-between h-20">
          
          {/* Logo */}
          <Link to="/" className="flex items-center gap-3">
            <img src="/tudu-logo-transparent.png" alt="TuDu Logo" className="h-10 w-auto object-contain drop-shadow-md" />
          </Link>

          {/* Desktop Nav */}
          <div className="hidden md:flex items-center gap-8">
            <a href="#problema" className="text-sm font-medium text-zinc-400 hover:text-white transition-colors">{t('landing.nav.problem')}</a>
            <a href="#solucion" className="text-sm font-medium text-zinc-400 hover:text-white transition-colors">{t('landing.nav.solution')}</a>
            <a href="#roi" className="text-sm font-medium text-zinc-400 hover:text-white transition-colors">{t('landing.nav.benefits')}</a>
          </div>

          {/* Actions */}
          <div className="hidden md:flex items-center gap-6">
            <LanguageSwitcher />
            <Link to="/login" className="text-sm font-medium text-zinc-300 hover:text-white transition-colors">
              {t('landing.nav.login')}
            </Link>
            <Link to="/register" className="group relative px-6 py-2.5 rounded-xl font-semibold text-sm text-white overflow-hidden shadow-lg shadow-purple-500/20 hover:shadow-purple-500/40 transition-all">
              <div className="absolute inset-0 bg-gradient-to-r from-purple-600 to-indigo-600 transition-all duration-300 group-hover:scale-105" />
              <div className="relative flex items-center gap-2">
                {t('landing.nav.start_free')}
                <ArrowRight size={16} className="group-hover:translate-x-1 transition-transform" />
              </div>
            </Link>
          </div>

          {/* Mobile Menu Toggle */}
          <button className="md:hidden text-zinc-400 hover:text-white" onClick={() => setMenuOpen(!menuOpen)}>
            {menuOpen ? <X size={24} /> : <Menu size={24} />}
          </button>
        </div>

        {/* Mobile Menu Dropdown */}
        {menuOpen && (
          <div className="md:hidden absolute top-20 left-0 right-0 bg-zinc-950/95 backdrop-blur-xl border-b border-white/5 p-6 flex flex-col gap-4 shadow-2xl">
            <a href="#problema" onClick={() => setMenuOpen(false)} className="text-base font-medium text-zinc-300">{t('landing.nav.problem')}</a>
            <a href="#solucion" onClick={() => setMenuOpen(false)} className="text-base font-medium text-zinc-300">{t('landing.nav.solution')}</a>
            <a href="#roi" onClick={() => setMenuOpen(false)} className="text-base font-medium text-zinc-300">{t('landing.nav.benefits')}</a>
            <hr className="border-white/10 my-2" />
            <div className="flex items-center justify-between">
              <span className="text-sm text-zinc-500">Idioma / Language</span>
              <LanguageSwitcher />
            </div>
            <Link to="/login" className="w-full text-center py-3 mt-2 rounded-xl border border-white/10 font-medium text-white hover:bg-white/5 transition-colors">
              {t('landing.nav.login')}
            </Link>
            <Link to="/register" className="w-full text-center py-3 rounded-xl bg-purple-600 font-medium text-white hover:bg-purple-700 transition-colors">
              {t('landing.nav.start_free')}
            </Link>
          </div>
        )}
      </nav>

      {/* ── HERO SECTION ──────────────────────────────────────────── */}
      <section className="relative pt-40 pb-20 px-6 max-w-5xl mx-auto text-center z-10">
        
        {/* Glow Effects (Local to Hero content) */}
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-3/4 h-3/4 bg-purple-600/20 blur-[120px] rounded-full point-events-none -z-10" />

        <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-purple-500/30 bg-purple-500/10 backdrop-blur-md mb-8">
          <span className="flex h-2 w-2 rounded-full bg-purple-500 animate-pulse"></span>
          <span className="text-xs font-semibold uppercase tracking-wider text-purple-300">{t('landing.hero.tag')}</span>
        </div>

        <h1 className="text-5xl md:text-7xl font-extrabold text-white tracking-tight leading-[1.1] mb-8 drop-shadow-2xl">
          {t('landing.hero.title_1')} <br className="hidden md:block" />
          <span className="bg-clip-text text-transparent bg-gradient-to-r from-purple-400 via-pink-500 to-orange-400">
            {t('landing.hero.title_2')}
          </span>
        </h1>
        
        <p className="text-lg md:text-xl text-zinc-400 mb-12 max-w-2xl mx-auto leading-relaxed">
          <Trans i18nKey="landing.hero.subtitle" components={{ 1: <strong className="text-zinc-200 font-semibold" /> }}>
            Gestión de proyectos, tareas y vida personal, <strong className="text-zinc-200 font-semibold">directamente donde ya están tus conversaciones.</strong>
          </Trans>
        </p>

        <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
          <Link to="/register" className="w-full sm:w-auto px-8 py-4 rounded-xl font-bold text-white bg-gradient-to-br from-purple-600 to-indigo-600 shadow-xl shadow-purple-500/25 hover:shadow-purple-500/40 hover:-translate-y-0.5 transition-all flex items-center justify-center gap-2">
            {t('landing.hero.cta')} <ArrowRight size={20} />
          </Link>
          <a href="#problema" className="w-full sm:w-auto px-8 py-4 rounded-xl font-semibold text-zinc-300 bg-zinc-800/50 backdrop-blur-md border border-zinc-700/50 hover:bg-zinc-800 transition-all flex items-center justify-center">
            {t('landing.hero.cta_secondary')}
          </a>
        </div>

        <div className="mt-8 animate-fade-in">
          <Link to="/login" className="text-zinc-400 hover:text-white transition-colors text-sm sm:text-base font-medium">
            ¿Ya tienes cuenta? <span className="text-purple-400 underline decoration-purple-500/30 underline-offset-4">Inicia sesión aquí</span>
          </Link>
        </div>
      </section>

      {/* ── EL PROBLEMA (Embudo de la Muerte) ───────────────────── */}
      <section id="problema" className="py-24 px-6 relative z-10">
        <div className="max-w-6xl mx-auto grid lg:grid-cols-2 gap-16 items-center">
          
          <div>
            <h2 className="text-3xl md:text-4xl font-bold text-white mb-6">
              <Trans i18nKey="landing.problem.title" components={{ 1: <span className="text-zinc-500" /> }}>
                Vivimos en el chat, pero administramos en el <span className="text-zinc-500">"software gris"</span>.
              </Trans>
            </h2>
            <p className="text-zinc-400 text-lg mb-8 leading-relaxed">
              {t('landing.problem.desc')}
            </p>
            
            <div className="space-y-6">
              {[
                { title: t('landing.problem.card1_title'), desc: t('landing.problem.card1_desc') },
                { title: t('landing.problem.card2_title'), desc: t('landing.problem.card2_desc') },
                { title: t('landing.problem.card3_title'), desc: t('landing.problem.card3_desc') }
              ].map((item, i) => (
                <div key={i} className="flex gap-4">
                  <div className="mt-1 bg-red-500/10 p-2 rounded-lg h-fit border border-red-500/20">
                    <X size={20} className="text-red-400" />
                  </div>
                  <div>
                    <h4 className="text-white font-semibold text-lg">{item.title}</h4>
                    <p className="text-zinc-500 text-sm mt-1">{item.desc}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Visual Representation of the problem */}
          <div className="relative">
            <div className="absolute inset-0 bg-gradient-to-tr from-zinc-900 to-zinc-800 rounded-3xl transform rotate-3 scale-105 opacity-50 border border-zinc-700" />
            <div className="relative bg-zinc-950/80 backdrop-blur-xl border border-white/10 rounded-3xl p-8 shadow-2xl">
              <div className="flex items-center justify-between border-b border-white/5 pb-4 mb-6">
                <div className="flex gap-2">
                  <div className="w-3 h-3 rounded-full bg-red-500/50" />
                  <div className="w-3 h-3 rounded-full bg-yellow-500/50" />
                  <div className="w-3 h-3 rounded-full bg-green-500/50" />
                </div>
                <div className="text-xs text-zinc-600 font-mono">{t('landing.problem.visual_title')}</div>
              </div>
              <div className="space-y-4 opacity-50 grayscale">
                <div className="h-10 bg-zinc-800 rounded-lg w-full" />
                <div className="h-10 bg-zinc-800 rounded-lg w-3/4" />
                <div className="h-10 bg-zinc-800 rounded-lg w-5/6" />
                <div className="h-10 bg-zinc-800 rounded-lg w-1/2" />
                <div className="text-center text-xs text-zinc-500 mt-8">{t('landing.problem.visual_desc')}</div>
              </div>
            </div>
          </div>

        </div>
      </section>

      {/* ── LA SOLUCION (Grid) ────────────────────────────────────── */}
      <section id="solucion" className="py-24 px-6 relative z-10 border-t border-white/5 bg-zinc-950/50">
        <div className="max-w-6xl mx-auto">
          <div className="text-center max-w-3xl mx-auto mb-16">
            <h2 className="text-3xl md:text-5xl font-bold text-white mb-6">{t('landing.solution.title')}</h2>
            <p className="text-xl text-zinc-400">
              {t('landing.solution.subtitle')}
            </p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {/* Card 1 */}
            <div className="bg-white/5 backdrop-blur-md border border-white/10 rounded-3xl p-8 hover:bg-white/10 transition-colors group">
              <div className="w-14 h-14 bg-green-500/20 text-green-400 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                <MessageSquare size={28} />
              </div>
              <h3 className="text-xl font-bold text-white mb-3">{t('landing.solution.feat1_title')}</h3>
              <p className="text-zinc-400 text-sm leading-relaxed">
                {t('landing.solution.feat1_desc')}
              </p>
            </div>

            {/* Card 2 */}
            <div className="bg-white/5 backdrop-blur-md border border-white/10 rounded-3xl p-8 hover:bg-white/10 transition-colors group">
              <div className="w-14 h-14 bg-purple-500/20 text-purple-400 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                <Smartphone size={28} />
              </div>
              <h3 className="text-xl font-bold text-white mb-3">{t('landing.solution.feat2_title')}</h3>
              <p className="text-zinc-400 text-sm leading-relaxed">
                {t('landing.solution.feat2_desc')}
              </p>
            </div>

            {/* Card 3 */}
            <div className="bg-white/5 backdrop-blur-md border border-white/10 rounded-3xl p-8 hover:bg-white/10 transition-colors group">
              <div className="w-14 h-14 bg-orange-500/20 text-orange-400 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                <BrainCircuit size={28} />
              </div>
              <h3 className="text-xl font-bold text-white mb-3">{t('landing.solution.feat3_title')}</h3>
              <p className="text-zinc-400 text-sm leading-relaxed">
                {t('landing.solution.feat3_desc')}
              </p>
            </div>

            {/* Card 4 - Wide Span */}
            <div className="md:col-span-2 lg:col-span-3 bg-gradient-to-br from-indigo-900/40 to-purple-900/20 backdrop-blur-md border border-indigo-500/20 rounded-3xl p-8 md:p-12 flex flex-col md:flex-row items-center gap-12">
              <div className="flex-1">
                <div className="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-indigo-500/20 text-indigo-300 text-xs font-bold mb-4">
                  <Shield size={14} /> {t('landing.solution.feat4_tag')}
                </div>
                <h3 className="text-2xl md:text-3xl font-bold text-white mb-4">{t('landing.solution.feat4_title')}</h3>
                <p className="text-zinc-300">
                  {t('landing.solution.feat4_desc')}
                </p>
              </div>
              <div className="w-full md:w-1/3 flex justify-center">
                <div className="relative w-48 h-48">
                  <div className="absolute inset-0 border-4 border-indigo-500/30 rounded-full animate-spin-slow"></div>
                  <div className="absolute inset-4 border-4 border-purple-500/40 rounded-full animate-reverse-spin"></div>
                  <div className="absolute inset-0 flex items-center justify-center">
                    <Activity size={48} className="text-indigo-400" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ── CTA FINAL ─────────────────────────────────────────────── */}
      <section id="roi" className="py-32 px-6 text-center relative z-10">
        <div className="max-w-4xl mx-auto">
          <h2 className="text-4xl md:text-5xl font-extrabold text-white mb-8">
            <Trans i18nKey="landing.cta_final.title" components={{ br: <br/> }} />
          </h2>
          <p className="text-xl text-zinc-400 mb-12">
            <Trans i18nKey="landing.cta_final.desc" components={{ br: <br/> }} />
          </p>
          <Link to="/register" className="inline-flex px-10 py-5 rounded-2xl font-bold text-white bg-white/10 hover:bg-white/20 border border-white/20 backdrop-blur-lg shadow-2xl hover:scale-105 transition-all text-lg items-center gap-3">
            {t('landing.cta_final.button')} <ArrowRight size={24} />
          </Link>
        </div>
      </section>

      {/* ── FOOTER ────────────────────────────────────────────────── */}
      <footer className="border-t border-white/10 bg-zinc-950/80 backdrop-blur-xl py-12 px-6 relative z-10">
        <div className="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
          <div className="flex items-center gap-2">
            <img src="/tudu-logo-transparent.png" alt="TuDu Logo" className="h-8 w-auto object-contain opacity-90" />
          </div>
          <div className="text-center md:text-right text-sm text-zinc-500">
            <p className="mb-2">{t('landing.footer.community')} <br/> contacto@interdata.mx • 55 5401 3300</p>
            <p>© 2015-2026 Interdata. {t('landing.footer.rights')} Av. Armando Birlain Shaffler 2001, Querétaro.</p>
          </div>
        </div>
      </footer>

    </div>
  );
}
