import { useState, useEffect } from 'react';
import { loadStripe, type Stripe } from '@stripe/stripe-js';
import { Elements, CardElement, useStripe, useElements } from '@stripe/react-stripe-js';
import { X, Lock, Shield, Loader2, CheckCircle2 } from 'lucide-react';
import api from '../../api/axios';
import { useNavigate } from 'react-router-dom';

interface StripeCheckoutModalProps {
    isOpen: boolean;
    onClose: () => void;
    plan: 'starter' | 'pro' | 'agency';
    edition: 'standalone' | 'corp';
    cycle: 'monthly' | 'annual';
    priceMXN: number;
    planName: string;
}

const CheckoutForm = ({ plan, edition, cycle, priceMXN, planName, onClose }: StripeCheckoutModalProps) => {
    const stripe = useStripe();
    const elements = useElements();
    const navigate = useNavigate();

    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [success, setSuccess] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!stripe || !elements) {
            return;
        }

        const cardElement = elements.getElement(CardElement);
        if (!cardElement) return;

        setLoading(true);
        setError(null);

        try {
            // 1. Create Payment Method via Stripe
            const { error: stripeError, paymentMethod } = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
            });

            if (stripeError) {
                setError(stripeError.message || 'Error validando la tarjeta');
                setLoading(false);
                return;
            }

            // 2. Send PaymentMethod ID to our backend to create Customer and Subscription
            const res = await api.post('/payments.php?action=subscribe', {
                edition,
                plan,
                billing_cycle: cycle,
                payment_method: 'card',
                payment_method_id: paymentMethod.id
            });

            if (res.data.status === 'trialing' || res.data.status === 'success') {
                setSuccess(true);
                // Redirect to dashboard after 3 seconds
                setTimeout(() => {
                    navigate('/admin');
                }, 3000);
            } else {
                setError(res.data.message || 'Error al iniciar la suscripción');
            }
        } catch (err: any) {
            setError(err.response?.data?.message || 'Error de red o conexión al servidor');
        } finally {
            setLoading(false);
        }
    };

    if (success) {
        return (
            <div className="flex flex-col items-center justify-center py-8 text-center animate-in fade-in zoom-in duration-300">
                <div className="w-16 h-16 bg-tudu-success/20 rounded-full flex items-center justify-center mb-4">
                    <CheckCircle2 size={32} className="text-tudu-success" />
                </div>
                <h3 className="text-2xl font-bold text-white mb-2">¡Suscripción Activada!</h3>
                <p className="text-tudu-text-muted mb-6">Tu período de prueba de 14 días ha comenzado con éxito.</p>
                <div className="text-sm text-tudu-accent loading loading-dots">
                    Redirigiendo a tu entorno...
                </div>
            </div>
        );
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="bg-white/5 border border-white/10 rounded-xl p-4">
                <div className="flex justify-between items-center mb-4">
                    <div>
                        <h4 className="text-white font-medium">{planName}</h4>
                        <p className="text-xs text-tudu-text-muted capitalize">{cycle === 'monthly' ? 'Pago Mensual' : 'Pago Anual'}</p>
                    </div>
                    <div className="text-right">
                        <p className="text-lg font-bold text-tudu-accent">${(priceMXN / 100).toLocaleString('en-US')} <span className="text-xs text-tudu-text-muted">MXN</span></p>
                    </div>
                </div>

                {/* Stripe Card Element Container */}
                <div className="bg-gray-900 border border-tudu-accent/30 focus-within:border-tudu-accent rounded-lg p-3 transition-colors">
                    <CardElement
                        options={{
                            style: {
                                base: {
                                    fontSize: '15px',
                                    color: '#fff',
                                    '::placeholder': { color: '#71717a' },
                                    iconColor: '#a78bfa',
                                },
                                invalid: {
                                    color: '#f87171',
                                    iconColor: '#f87171',
                                },
                            },
                            hidePostalCode: true,
                        }}
                    />
                </div>
            </div>

            {error && (
                <div className="bg-red-500/10 border-l-4 border-red-500 text-red-400 p-3 text-sm rounded">
                    {error}
                </div>
            )}

            <button
                type="submit"
                disabled={!stripe || loading}
                className="w-full relative group bg-tudu-accent hover:opacity-90 disabled:opacity-50 text-white font-medium py-3 px-4 rounded-xl transition-all shadow-lg shadow-tudu-accent/20 flex items-center justify-center gap-2 overflow-hidden"
            >
                {loading ? (
                    <><Loader2 size={18} className="animate-spin" /> Procesando...</>
                ) : (
                    <><Lock size={16} /> Iniciar prueba gratis de 14 días</>
                )}
            </button>

            <div className="text-center flex items-center justify-center gap-1.5 opacity-60">
                <Shield size={12} className="text-white" />
                <span className="text-[11px] text-white">Pagos procesados de forma segura por Stripe. Cero cargos hoy.</span>
            </div>
        </form>
    );
};

export default function StripeCheckoutModal(props: StripeCheckoutModalProps) {
    const [stripePromise, setStripePromise] = useState<Promise<Stripe | null> | null>(null);
    const [loadingKey, setLoadingKey] = useState(true);

    useEffect(() => {
        if (props.isOpen) {
            const fetchKey = async () => {
                try {
                    // Start by checking env first for local overrides
                    let pk = import.meta.env.VITE_STRIPE_PUBLIC_KEY;
                    if (!pk) {
                        // Fetch from backend settings
                        const res = await api.get('/payments.php?action=config');
                        pk = res.data.public_key;
                    }
                    if (pk) {
                        setStripePromise(loadStripe(pk));
                    }
                } catch (error) {
                    console.error('Failed to load Stripe public key', error);
                } finally {
                    setLoadingKey(false);
                }
            };
            fetchKey();
        }
    }, [props.isOpen]);

    if (!props.isOpen) return null;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={props.onClose} />
            <div className="relative bg-tudu-bg-dark border border-white/10 rounded-2xl shadow-2xl w-full max-w-md p-6 overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                
                {/* Header */}
                <div className="flex justify-between items-center mb-6">
                    <h2 className="text-lg font-bold text-white tracking-tight flex items-center gap-2">
                        <span className="bg-tudu-accent/20 text-tudu-accent p-1.5 rounded-lg"><Lock size={16} /></span>
                        Completar Suscripción
                    </h2>
                    <button onClick={props.onClose} className="text-gray-400 hover:text-white transition-colors">
                        <X size={20} />
                    </button>
                </div>

                {loadingKey ? (
                    <div className="py-8 flex justify-center text-tudu-accent">
                        <Loader2 size={24} className="animate-spin" />
                    </div>
                ) : stripePromise ? (
                    <Elements stripe={stripePromise}>
                        <CheckoutForm {...props} />
                    </Elements>
                ) : (
                    <div className="py-8 text-center text-tudu-text-muted text-sm">
                        ⚠️ No se ha detectado la clave pública de Stripe configurada en el sistema.
                    </div>
                )}
            </div>
        </div>
    );
}
