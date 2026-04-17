import { useState, useEffect, useCallback } from 'react';
import api from '../api/axios';

/**
 * Converts a Base64URL-encoded string to a Uint8Array.
 * Required by the PushManager.subscribe() applicationServerKey parameter.
 */
function base64UrlToUint8Array(base64Url: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/') + padding;
    const raw = atob(base64);
    const bytes = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
    return bytes;
}

interface UsePushNotificationsReturn {
    isSupported: boolean;
    permission: NotificationPermission;
    isSubscribed: boolean;
    isLoading: boolean;
    subscribe: () => Promise<void>;
    unsubscribe: () => Promise<void>;
}

export function usePushNotifications(): UsePushNotificationsReturn {
    const [isSupported, setIsSupported] = useState(false);
    const [permission, setPermission] = useState<NotificationPermission>('default');
    const [isSubscribed, setIsSubscribed] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [vapidKey, setVapidKey] = useState<string | null>(null);

    // Check browser support and current state on mount
    useEffect(() => {
        const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
        setIsSupported(supported);

        if (supported) {
            setPermission(Notification.permission);

            // Load VAPID public key from settings
            api.get('/settings.php').then(res => {
                if (res.data.status === 'success') {
                    setVapidKey(res.data.data?.vapid_public_base64 || null);
                }
            }).catch(() => { });

            // Check if already subscribed
            navigator.serviceWorker.ready.then(reg => {
                reg.pushManager.getSubscription().then(sub => {
                    setIsSubscribed(!!sub);
                });
            }).catch(() => { });
        }
    }, []);

    const subscribe = useCallback(async () => {
        if (!isSupported || !vapidKey) {
            alert('Las notificaciones push no son compatibles con este navegador, o la configuración VAPID no está lista.');
            return;
        }

        setIsLoading(true);
        try {
            // 1. Request notification permission
            const result = await Notification.requestPermission();
            setPermission(result);

            if (result !== 'granted') {
                alert('Permiso denegado. Puedes habilitarlo desde la configuración del navegador.');
                return;
            }

            // 2. Get SW registration
            const registration = await navigator.serviceWorker.ready;

            // 3. Subscribe to push
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64UrlToUint8Array(vapidKey),
            });

            // 4. Send subscription to backend
            const subJson = subscription.toJSON();
            await api.post('/push_subscribe.php', {
                endpoint: subJson.endpoint,
                keys: {
                    p256dh: subJson.keys?.p256dh,
                    auth: subJson.keys?.auth,
                },
            });

            setIsSubscribed(true);
        } catch (err: any) {
            console.error('Push subscribe error:', err);
            alert('No se pudo activar las notificaciones. ' + (err.message || ''));
        } finally {
            setIsLoading(false);
        }
    }, [isSupported, vapidKey]);

    const unsubscribe = useCallback(async () => {
        setIsLoading(true);
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await subscription.unsubscribe();
                setIsSubscribed(false);
            }
        } catch (err) {
            console.error('Unsubscribe error:', err);
        } finally {
            setIsLoading(false);
        }
    }, []);

    return { isSupported, permission, isSubscribed, isLoading, subscribe, unsubscribe };
}
