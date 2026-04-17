import React, { useEffect, useRef } from "react";

interface FloatingDotsProps {
    className?: string;
    maxRadius?: number;
    maxSpeed?: number;
    minSpeed?: number;
    color?: string;
}

export const FloatingDots: React.FC<FloatingDotsProps> = ({
    className = "",
    maxRadius = 1.5,
    maxSpeed = 0.5,
    minSpeed = 0.1,
    color = "white"
}) => {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        const ctx = canvas.getContext("2d");
        if (!ctx) return;

        let animationFrameId: number;
        let particles: Particle[] = [];
        
        let width = canvas.width = window.innerWidth;
        let height = canvas.height = window.innerHeight;

        const handleResize = () => {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
            initParticles();
        };

        window.addEventListener('resize', handleResize);

        class Particle {
            x: number;
            y: number;
            radius: number;
            vx: number;
            vy: number;
            opacity: number;

            constructor() {
                this.x = Math.random() * width;
                this.y = Math.random() * height;
                this.radius = Math.random() * maxRadius + 0.5;
                // Random speeds
                const angle = Math.random() * Math.PI * 2;
                const speed = Math.random() * (maxSpeed - minSpeed) + minSpeed;
                this.vx = Math.cos(angle) * speed;
                this.vy = Math.sin(angle) * speed;
                this.opacity = Math.random() * 0.5 + 0.1;
            }

            update() {
                this.x += this.vx;
                this.y += this.vy;

                // Wrap around edges
                if (this.x < 0) this.x = width;
                if (this.x > width) this.x = 0;
                if (this.y < 0) this.y = height;
                if (this.y > height) this.y = 0;
            }

            draw() {
                if (!ctx) return;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
                ctx.fillStyle = color === 'white' 
                    ? `rgba(255, 255, 255, ${this.opacity})` 
                    : `rgba(0, 0, 0, ${this.opacity})`;
                ctx.fill();
            }
        }

        const initParticles = () => {
            particles = [];
            // responsive particle count
            const count = Math.floor((width * height) / 8000); 
            for (let i = 0; i < count; i++) {
                particles.push(new Particle());
            }
        };

        const render = () => {
            ctx.clearRect(0, 0, width, height);
            particles.forEach((p) => {
                p.update();
                p.draw();
            });
            animationFrameId = requestAnimationFrame(render);
        };

        initParticles();
        render();

        return () => {
            window.removeEventListener('resize', handleResize);
            cancelAnimationFrame(animationFrameId);
        };
    }, [color, maxRadius, maxSpeed, minSpeed]);

    return (
        <canvas
            ref={canvasRef}
            className={`pointer-events-none opacity-40 ${className}`}
        />
    );
};
