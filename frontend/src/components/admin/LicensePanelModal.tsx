import { useState, useEffect } from 'react';
import api from '../../api/axios';
import { X, CreditCard, ShieldCheck, CheckCircle2, AlertCircle, Building2, ChevronRight, Users, FolderPlus } from 'lucide-react';
import { loadStripe } from '@stripe/stripe-js';
import { Elements, CardElement, useStripe, useElements } from '@stripe/react-stripe-js';

// ---- STRIPE FORM COMPONENT ----
const CheckoutForm = ({ planId, cycle, edition, onComplete, onCancel }: any) => {
    const stripe = useStripe();
    const elements = useElements();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!stripe || !elements) return;

        setLoading(true);
        setError(null);

        const cardElement = elements.getElement(CardElement);
        if (!cardElement) return;

        try {
            const { error: stripeError, paymentMethod } = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
            });

            if (stripeError) {
                setError(stripeError.message || 'Error con la tarjeta');
                setLoading(false);
                return;
            }

            // Manda a backend
            const res = await api.post('/payments.php?action=subscribe', {
                edition: edition,
                plan: planId,
                billing_cycle: cycle,
                payment_method: 'card',
                payment_method_id: paymentMethod.id
            });

            if (res.data.status === 'trialing' || res.data.status === 'active') {
                onComplete();
            } else if (res.data.status === 'requires_action') {
                // Autenticacion 3D Secure
                const { error: confirmError } = await stripe.confirmCardPayment(res.data.client_secret);
                if (confirmError) setError(confirmError.message || 'Pago rechazado');
                else onComplete();
            }
        } catch (err: any) {
            setError(err.response?.data?.message || 'Error de conexión');
        } finally {
            setLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
                <CardElement options={{
                    style: {
                        base: {
                            fontSize: '16px',
                            color: '#424770',
                            '::placeholder': { color: '#aab7c4' },
                        },
                        invalid: { color: '#9e2146' },
                    },
                }} />
            </div>
            {error && <div className="text-red-500 text-sm font-bold bg-red-50 dark:bg-red-900/10 p-2 rounded-lg">{error}</div>}
            
            <div className="flex gap-2 pt-2">
                <button type="button" onClick={onCancel} className="flex-1 py-3 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 rounded-xl font-bold hover:bg-gray-200 dark:hover:bg-gray-700 transition">Cancelar</button>
                <button type="submit" disabled={!stripe || loading} className="flex-1 py-3 bg-indigo-500 text-white rounded-xl font-bold hover:bg-indigo-600 transition disabled:opacity-50">
                    {loading ? 'Procesando...' : 'Iniciar Suscripción'}
                </button>
            </div>
        </form>
    );
};

// ---- MAIN MODAL ----
interface LicensePanelModalProps {
    isOpen: boolean;
    onClose: () => void;
}

const LicensePanelModal = ({ isOpen, onClose }: LicensePanelModalProps) => {
    const [stripePromise, setStripePromise] = useState<any>(null);
    const [status, setStatus] = useState<any>(null);
    const [plans, setPlans] = useState<any[]>([]);
    
    // Checkout state
    const [selectedPlan, setSelectedPlan] = useState<any>(null);
    const [cycle, setCycle] = useState<'monthly'|'annual'>('monthly');

    useEffect(() => {
        if (isOpen) {
            initData();
        } else {
            setSelectedPlan(null);
        }
    }, [isOpen]);

    const initData = async () => {
        try {
            const cfg = await api.get('/payments.php?action=config');
            if (cfg.data.public_key) {
                setStripePromise(loadStripe(cfg.data.public_key));
            }

            const pRes = await api.get('/payments.php?action=plans');
            setPlans(pRes.data.data);

            const sRes = await api.get('/payments.php?action=status');
            setStatus(sRes.data.data);
        } catch (e) {
            console.error('Error info', e);
        }
    };

    const handleCancelSubscription = async () => {
        if (!confirm('¿Estás seguro de cancelar tu suscripción? Mantendrás el acceso a tu plan actual hasta que termine el periodo facturado o de prueba.')) return;
        try {
            const res = await api.post('/payments.php?action=cancel');
            if (res.data.status === 'success') {
                alert('Suscripción cancelada exitosamente.');
                initData();
            } else {
                alert('Error al cancelar: ' + res.data.message);
            }
        } catch (e: any) {
            alert('Error: ' + (e.response?.data?.message || e.message));
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in" onClick={onClose}>
            <div className="bg-white dark:bg-tudu-content-dark w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col" onClick={e => e.stopPropagation()}>
                
                {/* HEAD */}
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-indigo-500/10 to-transparent">
                    <h2 className="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <ShieldCheck className="text-indigo-500" /> Facturación
                    </h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-white"><X size={24} /></button>
                </div>

                <div className="p-6 overflow-y-auto max-h-[80vh] custom-scrollbar">
                    {!status ? (
                        <div className="text-center py-20 animate-pulse text-gray-400">Cargando pasarela cifrada...</div>
                    ) : selectedPlan ? (
                        // --- CHECKOUT VIEW ---
                        <div className="max-w-md mx-auto space-y-6">
                            <div className="text-center">
                                <h3 className="text-2xl font-bold text-gray-800 dark:text-white mb-2">Completar Suscripción</h3>
                                <div className="bg-indigo-500/10 text-indigo-500 font-bold px-4 py-2 rounded-lg inline-block">Plan {selectedPlan.name} ({cycle === 'monthly' ? 'Mensual' : 'Anual'})</div>
                            </div>
                            
                            <div className="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700 text-sm space-y-2 text-gray-600 dark:text-gray-300">
                                <div className="flex justify-between"><span>Precio de Renovación</span> <span className="font-bold">${selectedPlan.amount_mxn?.[cycle]} MXN</span></div>
                                <div className="flex justify-between text-indigo-500 font-bold border-t border-indigo-500/20 pt-2"><span className="flex items-center gap-1"><CheckCircle2 size={16}/> Prueba Gratuita Hoy</span> <span>$0.00 MXN</span></div>
                            </div>
                            
                            {stripePromise ? (
                                <Elements stripe={stripePromise}>
                                    <CheckoutForm 
                                        planId={selectedPlan.plan} 
                                        edition={selectedPlan.edition}
                                        cycle={cycle}
                                        onCancel={() => setSelectedPlan(null)}
                                        onComplete={() => { setSelectedPlan(null); initData(); }}
                                    />
                                </Elements>
                            ) : (
                                <div className="text-center text-red-500">Error: Pasarela no configurada en el servidor (Faltan Public Keys).</div>
                            )}
                        </div>
                    ) : (
                        // --- SUBSCRIPTION DASHBOARD ---
                        <div className="space-y-6">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="p-5 border border-indigo-500/20 bg-indigo-500/5 rounded-xl text-center">
                                    <p className="text-xs text-gray-500 font-bold uppercase mb-1">Estado del Servicio</p>
                                    <div className="text-lg font-bold text-indigo-500 uppercase flex flex-col justify-center items-center gap-1">
                                        <div className="flex items-center gap-2">
                                            <div className={`w-2 h-2 rounded-full ${status.plan_status==='active' || status.plan_status==='lifetime' || status.usage?.has_subscription ? 'bg-green-500' : 'bg-amber-500'}`}></div>
                                            <span>{status.plan_status}</span>
                                        </div>
                                        {status.usage?.has_subscription && status.plan_status === 'trialing' && (
                                            <span className="text-[10px] bg-green-500 text-white px-2 py-0.5 rounded-full font-bold">Tarjeta Vinculada</span>
                                        )}
                                    </div>
                                    {status.usage?.has_subscription && status.plan_status !== 'cancelled' && (
                                        <button onClick={handleCancelSubscription} className="mt-3 text-[10px] font-bold text-red-400 hover:text-red-500 underline underline-offset-2 transition opacity-70 hover:opacity-100">
                                            Cancelar Suscripción
                                        </button>
                                    )}
                                </div>
                                <div className="p-5 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 rounded-xl text-center">
                                    <p className="text-xs text-gray-500 font-bold uppercase mb-1">Plan Actual</p>
                                    <p className="text-lg font-bold text-gray-800 dark:text-white capitalize">{status.edition} {status.plan}</p>
                                </div>
                            </div>

                            <div>
                                <div className="flex justify-between items-center mb-4">
                                    <h4 className="font-bold text-gray-800 dark:text-gray-200">Mejorar Workspace</h4>
                                    <div className="flex bg-gray-100 dark:bg-gray-800 p-1 rounded-lg">
                                        <button disabled={status.plan_status === 'active' || status.usage?.has_subscription} onClick={()=>setCycle('monthly')} className={`px-3 py-1 text-xs font-bold rounded-md ${cycle==='monthly'?'bg-white dark:bg-gray-700 shadow':''} ${(status.plan_status === 'active' || status.usage?.has_subscription) ? 'opacity-50 cursor-not-allowed' : ''}`}>Mensual</button>
                                        <button disabled={status.plan_status === 'active' || status.usage?.has_subscription} onClick={()=>setCycle('annual')} className={`px-3 py-1 text-xs font-bold rounded-md ${cycle==='annual'?'bg-white dark:bg-gray-700 shadow':''} ${(status.plan_status === 'active' || status.usage?.has_subscription) ? 'opacity-50 cursor-not-allowed' : ''}`}>Anual (2 meses Gratis)</button>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    {plans.filter(p => p.edition === status.edition).map(p => {
                                        const monthlyPrice = (p.price_mxn || 0) / 100;
                                        const annualPrice = monthlyPrice > 0 ? monthlyPrice * 10 : 0;
                                        const displayPrice = cycle === 'annual' ? annualPrice : monthlyPrice;

                                        return (
                                        <div key={p.plan} className={`p-4 border rounded-xl flex flex-col ${status.plan === p.plan ? 'border-indigo-500 bg-indigo-500/5' : 'border-gray-200 dark:border-gray-700'}`}>
                                            <p className="font-bold text-gray-800 dark:text-white mb-1">{p.label}</p>
                                            <p className="text-xl font-black text-indigo-500 mb-4">${displayPrice} <span className="text-xs text-gray-400 font-normal">MXN</span></p>
                                            
                                            <div className="text-xs text-gray-500 space-y-2 mb-4 flex-1">
                                                <p className="flex items-center gap-1"><Users size={12}/> Hasta {p.members_limit > 900000 ? 'Ilimitados' : p.members_limit} Miembros</p>
                                                <p className="flex items-center gap-1"><FolderPlus size={12}/> Hasta {p.projects_limit > 900000 ? 'Ilimitados' : p.projects_limit} Proyectos</p>
                                                <p className="flex items-center gap-1"><CheckCircle2 size={12}/> {p.tasks_limit > 900000 ? 'Ilimitadas' : p.tasks_limit} Tareas</p>
                                            </div>

                                            {status.plan === p.plan && (status.plan_status === 'active' || status.usage?.has_subscription) ? (
                                                <button disabled className="w-full py-2 bg-indigo-500/20 text-indigo-500 rounded-lg font-bold text-xs cursor-not-allowed">Suscripción Activa</button>
                                            ) : (status.plan_status === 'active' || status.usage?.has_subscription) ? (
                                                <button disabled className="w-full py-2 border border-gray-200 dark:border-gray-700 text-gray-400 rounded-lg font-bold text-xs cursor-not-allowed">Protegido</button>
                                            ) : (
                                                <button onClick={() => setSelectedPlan({...p, name: p.label, amount_mxn: { monthly: monthlyPrice, annual: annualPrice }})} className="w-full py-2 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-lg font-bold text-xs hover:scale-105 transition">
                                                    {status.plan === p.plan ? 'Pagar Ahora' : 'Aplicar Upgrade'}
                                                </button>
                                            )}
                                        </div>
                                    )})}
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};
export default LicensePanelModal;
