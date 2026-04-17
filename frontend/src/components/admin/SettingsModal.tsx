import { 
    X, Shield, Users, Tag, FolderPlus, Settings as SettingsIcon, RotateCcw, Building2, ExternalLink, Activity, Rocket, CreditCard
} from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { useState } from 'react';
import api from '../../api/axios';

interface SettingsModalProps {
    isOpen: boolean;
    onClose: () => void;
    openModal: (modalName: string) => void;
}

const SettingsModal = ({ isOpen, onClose, openModal }: SettingsModalProps) => {
    const { user, edition } = useAuth();
    const [activeTab, setActiveTab] = useState('general');

    if (!isOpen) return null;

    const isAdmin = user?.rol === 'super_admin' || user?.rol === 'administrador';
    const isGlobal = user?.rol === 'super_admin' || user?.rol === 'admin_global';

    const handleResetTour = async () => {
        try {
            await api.post('/onboarding.php?action=reset');
            window.location.reload();
        } catch (error) {
            console.error('Error resetting tour:', error);
        }
    };

    const tabs = [
        { id: 'general', label: 'General', icon: <SettingsIcon size={18} /> },
        ...(isAdmin ? [{ id: 'workspace', label: 'Espacio de Trabajo', icon: <Building2 size={18} /> }] : []),
        ...(isAdmin ? [{ id: 'members', label: 'Miembros y Accesos', icon: <Users size={18} /> }] : []),
        ...(isAdmin ? [{ id: 'advanced', label: 'Seguridad e IA', icon: <Shield size={18} /> }] : []),
        ...(isAdmin ? [{ id: 'billing', label: 'Licencias y Facturación', icon: <CreditCard size={18} /> }] : []),
        ...(isGlobal ? [{ id: 'saas', label: 'Modo Dios (Global)', icon: <Rocket size={18} /> }] : []),
    ];

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md animate-fade-in">
            <div className="bg-white/90 dark:bg-zinc-950/90 w-full max-w-5xl h-[70vh] rounded-3xl shadow-2xl overflow-hidden flex border border-white/20 dark:border-white/5">
                
                {/* ── LEFT SIDEBAR (Tabs) ── */}
                <div className="w-1/3 max-w-[280px] bg-gray-50/50 dark:bg-zinc-900/50 border-r border-gray-200 dark:border-zinc-800 p-6 flex flex-col">
                    <div className="flex items-center gap-2 mb-8 px-2">
                        <SettingsIcon className="text-zinc-500" size={24} />
                        <h2 className="text-lg font-bold text-gray-800 dark:text-white">Configuración</h2>
                    </div>

                    <div className="flex-1 space-y-2">
                        {tabs.map(tab => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all ${
                                    activeTab === tab.id 
                                    ? 'bg-white dark:bg-zinc-800 text-purple-600 dark:text-purple-400 shadow-sm border border-gray-200 dark:border-zinc-700' 
                                    : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-100 dark:hover:bg-zinc-800/50 hover:text-gray-900 dark:hover:text-white'
                                }`}
                            >
                                {tab.icon} {tab.label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* ── RIGHT CONTENT (Values) ── */}
                <div className="flex-1 relative flex flex-col">
                    <div className="absolute top-4 right-4 z-10">
                        <button onClick={onClose} className="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-zinc-800 text-gray-500 transition-colors">
                            <X size={20} />
                        </button>
                    </div>

                    <div className="flex-1 p-8 overflow-y-auto custom-scrollbar">
                        
                        {activeTab === 'general' && (
                            <div className="animate-fade-in-up">
                                <h3 className="text-2xl font-bold text-gray-800 dark:text-white mb-6">Ajustes Generales</h3>
                                <div className="space-y-4">
                                    <div className="p-5 border border-gray-200 dark:border-zinc-800 rounded-2xl flex items-center justify-between hover:bg-gray-50 dark:hover:bg-zinc-900/30 transition-colors">
                                        <div>
                                            <h4 className="font-semibold text-gray-800 dark:text-white">Guía de Uso Rápido</h4>
                                            <p className="text-sm text-gray-500 dark:text-zinc-400">Ver nuevamente el tutorial interactivo.</p>
                                        </div>
                                        <button onClick={() => openModal('guide')} className="px-4 py-2 bg-purple-500/10 text-purple-600 dark:text-purple-400 rounded-lg hover:bg-purple-500/20 font-medium text-sm">Abrir Guía</button>
                                    </div>
                                    <div className="p-5 border border-gray-200 dark:border-zinc-800 rounded-2xl flex items-center justify-between hover:bg-gray-50 dark:hover:bg-zinc-900/30 transition-colors">
                                        <div>
                                            <h4 className="font-semibold text-gray-800 dark:text-white">Tour Inicial</h4>
                                            <p className="text-sm text-gray-500 dark:text-zinc-400">Reiniciar las burbujas explicativas de Onboarding.</p>
                                        </div>
                                        <button onClick={handleResetTour} className="px-4 py-2 bg-zinc-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-300 rounded-lg hover:bg-zinc-200 dark:hover:bg-zinc-700 font-medium text-sm flex items-center gap-2"><RotateCcw size={16}/> Reiniciar</button>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'workspace' && isAdmin && (
                            <div className="animate-fade-in-up">
                                <h3 className="text-2xl font-bold text-gray-800 dark:text-white mb-6">Espacio de Trabajo</h3>
                                <div className="grid grid-cols-1 gap-4">
                                    <div className="p-6 border border-gray-200 dark:border-zinc-800 rounded-2xl bg-gradient-to-r hover:from-purple-500/5 hover:to-indigo-500/5 transition-all group cursor-pointer" onClick={() => openModal('projects')}>
                                        <div className="flex items-center justify-between mb-2">
                                            <div className="flex items-center gap-3">
                                                <div className="p-2 bg-purple-500/20 rounded-xl text-purple-600 dark:text-purple-400"><FolderPlus size={20} /></div>
                                                <h4 className="font-bold text-gray-800 dark:text-white text-lg">Proyectos</h4>
                                            </div>
                                            <ExternalLink size={18} className="text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity" />
                                        </div>
                                        <p className="text-sm text-gray-500 dark:text-zinc-400 pl-11">Gestiona tableros, columnas Kanban y propiedades de proyectos.</p>
                                    </div>

                                    <div className="p-6 border border-gray-200 dark:border-zinc-800 rounded-2xl bg-gradient-to-r hover:from-blue-500/5 hover:to-cyan-500/5 transition-all group cursor-pointer" onClick={() => openModal('tags')}>
                                        <div className="flex items-center justify-between mb-2">
                                            <div className="flex items-center gap-3">
                                                <div className="p-2 bg-blue-500/20 rounded-xl text-blue-600 dark:text-blue-400"><Tag size={20} /></div>
                                                <h4 className="font-bold text-gray-800 dark:text-white text-lg">Etiquetas</h4>
                                            </div>
                                            <ExternalLink size={18} className="text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity" />
                                        </div>
                                        <p className="text-sm text-gray-500 dark:text-zinc-400 pl-11">Administra los tags para clasificar tareas y documentos.</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'members' && isAdmin && (
                            <div className="animate-fade-in-up">
                                <h3 className="text-2xl font-bold text-gray-800 dark:text-white mb-6">Identidad y Accesos</h3>
                                <div className="p-6 border border-gray-200 dark:border-zinc-800 rounded-2xl hover:bg-zinc-50 dark:hover:bg-zinc-900/30 group cursor-pointer transition-all" onClick={() => openModal('users')}>
                                    <div className="flex items-center justify-between mb-4">
                                        <div className="flex items-center gap-3">
                                            <div className="p-3 bg-emerald-500/20 rounded-xl text-emerald-600 dark:text-emerald-400"><Users size={24} /></div>
                                            <div>
                                                <h4 className="font-bold text-gray-800 dark:text-white text-lg">Directorio de Usuarios</h4>
                                                <p className="text-sm text-gray-500 dark:text-zinc-400">Invita, desactiva o resetéa contraseñas de tu red.</p>
                                            </div>
                                        </div>
                                        <button className="px-4 py-2 bg-emerald-500 text-white rounded-lg shadow-emerald-500/20 shadow-lg font-medium text-sm flex items-center gap-2">Abrir Panel <ExternalLink size={16}/></button>
                                    </div>
                                    <div className="bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 p-3 rounded-lg text-xs font-medium border border-emerald-500/20">
                                        💡 Tip: Usa el botón verde en este panel para enviar los accesos creados por WhatsApp instantáneamente.
                                    </div>
                                </div>
                            </div>
                        )}

                        {activeTab === 'advanced' && isAdmin && (
                            <div className="animate-fade-in-up">
                                <h3 className="text-2xl font-bold text-gray-800 dark:text-white mb-6">Seguridad e IA</h3>
                                
                                <div className="space-y-4">
                                    <div className="p-5 border border-gray-200 dark:border-zinc-800 rounded-2xl flex items-center justify-between hover:bg-gray-50 dark:hover:bg-zinc-900/30 transition-colors cursor-pointer" onClick={() => openModal('ai')}>
                                        <div className="flex items-center gap-3">
                                            <div className="p-2 bg-indigo-500/20 rounded-xl text-indigo-600 dark:text-indigo-400"><Activity size={20} /></div>
                                            <div>
                                                <h4 className="font-semibold text-gray-800 dark:text-white">Motor AI (Copiloto)</h4>
                                                <p className="text-sm text-gray-500 dark:text-zinc-400">Modelos lingüísticos, API Keys y comportamientos.</p>
                                            </div>
                                        </div>
                                        <ExternalLink size={16} className="text-gray-400" />
                                    </div>

                                    <div className="p-5 border border-gray-200 dark:border-zinc-800 rounded-2xl flex items-center justify-between hover:bg-gray-50 dark:hover:bg-zinc-900/30 transition-colors cursor-pointer" onClick={() => openModal('audit')}>
                                        <div className="flex items-center gap-3">
                                            <div className="p-2 bg-slate-500/20 rounded-xl text-slate-600 dark:text-slate-400"><Shield size={20} /></div>
                                            <div>
                                                <h4 className="font-semibold text-gray-800 dark:text-white">Auditoría Global</h4>
                                                <p className="text-sm text-gray-500 dark:text-zinc-400">Ver el rastro (Logs) de seguridad de todos los integrantes.</p>
                                            </div>
                                        </div>
                                        <ExternalLink size={16} className="text-gray-400" />
                                    </div>
                                    
                                </div>
                            </div>
                        )}

                        {activeTab === 'billing' && isAdmin && (
                            <div className="animate-fade-in-up">
                                <h3 className="text-2xl font-bold text-gray-800 dark:text-white mb-6">Suscripción y Licencias</h3>
                                <div className="p-8 border border-indigo-500/20 dark:border-indigo-500/20 rounded-3xl bg-gradient-to-b from-indigo-500/5 to-transparent text-center cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-900/50 transition-colors" onClick={() => openModal('license')}>
                                    <div className="w-16 h-16 mx-auto bg-indigo-500/10 rounded-2xl flex items-center justify-center mb-4">
                                        <CreditCard size={32} className="text-indigo-500" />
                                    </div>
                                    <h4 className="text-xl font-bold text-gray-800 dark:text-white mb-2">Administrar Plan Actual</h4>
                                    <p className="text-sm text-gray-500 dark:text-zinc-400 mb-6 max-w-md mx-auto">Conoce los detalles de facturación de tu Inquilino (Workspace), mejora tu plan para ampliar los límites y gestiona tú método de pago en línea.</p>
                                    <button className="px-6 py-3 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 rounded-xl font-bold hover:scale-105 transition-transform" onClick={(e) => { e.stopPropagation(); openModal('license'); }}>Abrir Portal de Facturación</button>
                                </div>
                            </div>
                        )}

                        {activeTab === 'saas' && isGlobal && (
                            <div className="animate-fade-in-up">
                                <h3 className="text-2xl font-bold text-gray-800 dark:text-white mb-6">Cuartel General (Software as a Service)</h3>
                                <div className="p-8 border border-amber-500/20 dark:border-amber-500/20 rounded-3xl bg-gradient-to-b from-amber-500/5 to-transparent text-center">
                                    <div className="w-20 h-20 mx-auto bg-amber-500 rounded-2xl shadow-xl shadow-amber-500/30 flex items-center justify-center mb-6">
                                        <Rocket size={40} className="text-white" />
                                    </div>
                                    <h4 className="text-2xl font-bold text-gray-800 dark:text-white mb-3">God Mode Activado</h4>
                                    <p className="text-sm text-gray-500 dark:text-zinc-400 mb-8 max-w-md mx-auto">
                                        Estás accediendo a la consola global del sistema. Desde allí podrás gestionar inquilinos, crear cuentas Lifetime y ver los ingresos de la plataforma centralizada.
                                    </p>
                                    <button 
                                        onClick={() => { onClose(); window.location.href = '#/backoffice'; }} 
                                        className="px-8 py-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl font-bold hover:scale-105 transition-transform"
                                    >
                                        Lanzar Backoffice
                                    </button>
                                </div>
                            </div>
                        )}

                    </div>
                </div>

            </div>
        </div>
    );
};

export default SettingsModal;
