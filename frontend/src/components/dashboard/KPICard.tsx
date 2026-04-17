import { type ReactNode } from 'react';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

interface KPICardProps {
    title: string;
    value: number | string;
    icon: ReactNode;
    colorClass: string; // e.g., "text-blue-500"
    bgClass: string; // e.g., "bg-blue-100"
    className?: string;
}

const KPICard = ({ title, value, icon, colorClass, bgClass, className }: KPICardProps) => {
    return (
        <div className={cn("bg-white/80 dark:bg-tudu-content-dark/80 backdrop-blur-md p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-between transition-transform hover:scale-[1.02]", className)}>
            <div>
                <p className="text-sm font-medium text-tudu-text-muted uppercase tracking-wider">{title}</p>
                <h3 className="text-3xl font-bold mt-1 text-tudu-text-light dark:text-tudu-text-dark">{value}</h3>
            </div>
            <div className={cn("p-4 rounded-xl", bgClass, colorClass)}>
                {icon}
            </div>
        </div>
    );
};

export default KPICard;
