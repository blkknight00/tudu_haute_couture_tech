import React, { useState } from 'react';
import { Search, Filter, User, Calendar, Flag, ChevronDown, X } from 'lucide-react';

interface FilterBarProps {
    filters: {
        status: string;
        designer: string;
        priority: string;
        date: string;
        search: string;
    };
    setFilters: (filters: any) => void;
    users: any[];
}

const FilterBar: React.FC<FilterBarProps> = ({ filters, setFilters, users }) => {
    const [expanded, setExpanded] = useState(false);

    const handleChange = (name: string, value: string) => {
        setFilters((prev: any) => ({ ...prev, [name]: value }));
    };

    const hasActiveFilters = filters.status !== 'todos' || filters.designer !== '' || filters.priority !== 'todas' || filters.date !== '' || filters.search !== '';

    const clearFilters = () => setFilters({ status: 'todos', designer: '', priority: 'todas', date: '', search: '' });

    return (
        <div className="bg-white/85 dark:bg-tudu-content-dark/85 backdrop-blur-md border border-white/20 dark:border-white/10 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 mb-6">
            {/* Search + Mobile toggle always visible */}
            <div className="flex items-center gap-2">
                <div className="flex-1 relative">
                    <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Buscar tarea..."
                        value={filters.search}
                        onChange={(e) => handleChange('search', e.target.value)}
                        className="w-full pl-9 pr-4 py-2 bg-gray-50 dark:bg-gray-800 border-none rounded-lg text-sm focus:ring-2 focus:ring-tudu-accent transition-all dark:text-gray-200"
                    />
                </div>

                {/* Filter toggle button — mobile only */}
                <button
                    onClick={() => setExpanded(!expanded)}
                    className={`sm:hidden flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium border transition-colors ${hasActiveFilters
                            ? 'bg-tudu-accent text-white border-tudu-accent'
                            : 'bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700'
                        }`}
                >
                    <Filter size={15} />
                    <span>Filtrar</span>
                    {hasActiveFilters && <span className="bg-white/30 text-white text-[10px] px-1.5 py-0.5 rounded-full font-bold">!</span>}
                    <ChevronDown size={14} className={`transition-transform ${expanded ? 'rotate-180' : ''}`} />
                </button>

                {/* Clear on desktop */}
                {hasActiveFilters && (
                    <button onClick={clearFilters} className="hidden sm:flex items-center gap-1 text-xs text-tudu-accent hover:underline font-medium">
                        <X size={12} /> Limpiar
                    </button>
                )}
            </div>

            {/* Desktop: always show filters. Mobile: show when expanded */}
            <div className={`mt-3 flex flex-wrap items-center gap-3 ${expanded ? 'flex' : 'hidden sm:flex'}`}>
                {/* Status */}
                <div className="flex items-center gap-2 flex-1 min-w-[140px]">
                    <Filter size={14} className="text-gray-400 shrink-0" />
                    <select
                        value={filters.status}
                        onChange={(e) => handleChange('status', e.target.value)}
                        className="flex-1 bg-gray-50 dark:bg-gray-800 text-sm py-2 px-2 rounded-lg border-none focus:ring-2 focus:ring-tudu-accent transition-all dark:text-gray-200"
                    >
                        <option value="todos">Todos los estados</option>
                        <option value="pendiente">Pendientes</option>
                        <option value="en_progreso">En Progreso</option>
                        <option value="completado">Completados</option>
                    </select>
                </div>

                {/* Designer */}
                <div className="flex items-center gap-2 flex-1 min-w-[140px]">
                    <User size={14} className="text-gray-400 shrink-0" />
                    <select
                        value={filters.designer}
                        onChange={(e) => handleChange('designer', e.target.value)}
                        className="flex-1 bg-gray-50 dark:bg-gray-800 text-sm py-2 px-2 rounded-lg border-none focus:ring-2 focus:ring-tudu-accent transition-all dark:text-gray-200"
                    >
                        <option value="">Todos los usuarios</option>
                        {users.map(u => (
                            <option key={u.id} value={u.id}>{u.nombre}</option>
                        ))}
                    </select>
                </div>

                {/* Priority */}
                <div className="flex items-center gap-2 flex-1 min-w-[130px]">
                    <Flag size={14} className="text-gray-400 shrink-0" />
                    <select
                        value={filters.priority}
                        onChange={(e) => handleChange('priority', e.target.value)}
                        className="flex-1 bg-gray-50 dark:bg-gray-800 text-sm py-2 px-2 rounded-lg border-none focus:ring-2 focus:ring-tudu-accent transition-all dark:text-gray-200"
                    >
                        <option value="todas">Todas las prioridades</option>
                        <option value="alta">🔴 Alta</option>
                        <option value="media">🟡 Media</option>
                        <option value="baja">🟢 Baja</option>
                    </select>
                </div>

                {/* Date */}
                <div className="flex items-center gap-2 flex-1 min-w-[130px]">
                    <Calendar size={14} className="text-gray-400 shrink-0" />
                    <input
                        type="date"
                        value={filters.date}
                        onChange={(e) => handleChange('date', e.target.value)}
                        className="flex-1 bg-gray-50 dark:bg-gray-800 text-sm py-1.5 px-2 rounded-lg border-none focus:ring-2 focus:ring-tudu-accent transition-all dark:text-gray-200"
                    />
                </div>

                {/* Clear mobile */}
                {hasActiveFilters && (
                    <button onClick={clearFilters} className="sm:hidden flex items-center gap-1 text-xs text-tudu-accent hover:underline font-medium w-full justify-center mt-1">
                        <X size={12} /> Limpiar filtros
                    </button>
                )}
            </div>
        </div>
    );
};

export default FilterBar;
