import React, { useEffect, useState } from "react";
import { FloatingDots } from "./floating-dots";

const GradientBg = () => {
    const [isDark, setIsDark] = useState(false);

    useEffect(() => {
        // Init state
        setIsDark(document.documentElement.classList.contains('dark'));
        
        // Listen for standard tailwind dark mode toggles on the HTML element
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'class') {
                    setIsDark(document.documentElement.classList.contains('dark'));
                }
            });
        });

        observer.observe(document.documentElement, { attributes: true });
        
        return () => observer.disconnect();
    }, []);

    return (
        <div className="fixed inset-0 w-full h-full bg-tudu-bg-light dark:bg-tudu-bg-dark -z-50 overflow-hidden pointer-events-none isolate">
            <FloatingDots
                className="w-full absolute inset-0 opacity-100"
                maxRadius={1.5}
                maxSpeed={0.8}
                minSpeed={0.2}
                color={isDark ? "white" : "black"}
            />
            
            {/* Ambient gradients inspired by the DevsLoka snippet / Haute Couture */}
            <div
                className="absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-[80px] sm:-top-80 opacity-80 dark:opacity-40"
                aria-hidden="true"
            >
                {/* Purple/Indigo Shape */}
                <div
                    className="relative left-[calc(40%-11rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 rotate-[30deg] bg-gradient-to-tr from-indigo-500 to-sky-400 sm:left-[calc(70%-30rem)] sm:w-[72.1875rem]"
                    style={{
                        clipPath:
                            "polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)",
                    }}
                />
            </div>
            
            {/* Warm/Copper opposite corner shape */}
            <div
                className="absolute inset-x-0 top-[60vh] -z-10 transform-gpu overflow-hidden blur-[120px] opacity-40 dark:opacity-20"
                aria-hidden="true"
            >
                <div
                    className="relative left-[calc(10%-11rem)] aspect-[1155/678] w-[36.125rem] translate-x-1/2 rotate-[120deg] bg-gradient-to-tr from-[#d97941] to-[#f59e0b] sm:w-[72.1875rem]"
                    style={{
                        clipPath:
                            "polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)",
                    }}
                />
            </div>
        </div>
    );
};

export default GradientBg;
