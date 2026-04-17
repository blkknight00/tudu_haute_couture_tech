import { useEffect } from 'react';
import api from '../../api/axios';
import { useAuth } from '../../contexts/AuthContext';

const ReminderService = () => {
    const { user } = useAuth();

    useEffect(() => {
        if (!user) return;

        // Request permission for browser notifications on mount
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission();
        }

        const checkReminders = async () => {
            try {
                const response = await api.get('/reminders.php?action=list_pending');
                if (response.data.status === 'success' && response.data.data.length > 0) {
                    response.data.data.forEach((reminder: any) => {
                        triggerAlert(reminder);
                    });
                }
            } catch (err) {
                console.error("Error polling reminders:", err);
            }
        };

        const triggerAlert = (reminder: any) => {
            const title = "🔔 Recordatorio de TuDu";
            const options = {
                body: reminder.titulo,
                icon: "/logo192.png", // Ensure this path is correct
                vibrate: [200, 100, 200]
            };

            // 1. Browser Notification
            if ("Notification" in window && Notification.permission === "granted") {
                new Notification(title, options);
            }

            // 2. Fallback UI Alert (Traditional alert as back-up)
            // We use a timeout to not block the polling loop immediately
            setTimeout(() => {
                alert(`🔔 RECORDATORIO: ${reminder.titulo}`);
                // Mark as notified so we don't show it again
                markAsNotified(reminder.id);
            }, 100);
        };

        const markAsNotified = async (id: number) => {
            try {
                await api.post('/reminders.php?action=notified', { id });
            } catch (err) {
                console.error("Error marking reminder as notified:", err);
            }
        };

        // Poll every 30 seconds
        const interval = setInterval(checkReminders, 30000);

        // Initial check
        checkReminders();

        return () => clearInterval(interval);
    }, [user]);

    return null; // This service works in the background
};

export default ReminderService;
