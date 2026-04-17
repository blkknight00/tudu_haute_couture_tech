import { useState, useEffect } from 'react';
import { X, Plus, Trash2, Edit2, Tag, Save, Loader2 } from 'lucide-react';
import api from '../../api/axios';

interface TagsModalProps {
    isOpen: boolean;
    onClose: () => void;
}

const COLORS = [
    '#EF4444', // Red
    '#F97316', // Orange
    '#F59E0B', // Amber
    '#10B981', // Emerald
    '#3B82F6', // Blue
    '#6366F1', // Indigo
    '#8B5CF6', // Violet
    '#EC4899', // Pink
    '#6B7280', // Gray
];

const TagsModal = ({ isOpen, onClose }: TagsModalProps) => {
    const [tags, setTags] = useState<any[]>([]);
    const [loading, setLoading] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    // Form State
    const [name, setName] = useState('');
    const [color, setColor] = useState(COLORS[8]); // Default Gray

    useEffect(() => {
        if (isOpen) fetchTags();
    }, [isOpen]);

    const fetchTags = async () => {
        setLoading(true);
        try {
            console.log('Fetching tags...');
            const res = await api.get(`/tags.php?t=${Date.now()}`);
            console.log('Tags response:', res.data);
            if (res.data.status === 'success') {
                setTags(res.data.data);
            }
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            const payload = { id: editingId, nombre: name, color };
            const res = await api.post('/tags.php', payload);
            if (res.data.status === 'success') {
                fetchTags();
                resetForm();
            } else {
                alert(res.data.message);
            }
        } catch (e) {
            console.error(e);
            alert('Error al guardar etiqueta');
        }
    };

    const handleEdit = (tag: any) => {
        setEditingId(tag.id);
        setName(tag.nombre);
        setColor(tag.color);
    };

    const handleDelete = async (id: number) => {
        if (!confirm('¿Seguro que deseas eliminar esta etiqueta?')) return;
        try {
            await api.delete(`/tags.php?id=${id}`);
            fetchTags();
        } catch (e) {
            console.error(e);
            alert('Error al eliminar');
        }
    };

    const resetForm = () => {
        setEditingId(null);
        setName('');
        setColor(COLORS[8]);
    };

    if (!isOpen) return null;

    return (
        <div
            className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            onClick={onClose}
        >
            <div
                className="bg-white dark:bg-tudu-content-dark w-full max-w-md rounded-xl shadow-2xl flex flex-col max-h-[80vh]"
                onClick={(e) => e.stopPropagation()}
            >

                {/* Header */}
                <div className="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 className="text-lg font-bold text-tudu-text-light dark:text-tudu-text-dark flex items-center gap-2">
                        <Tag size={20} /> Gestión de Etiquetas
                    </h2>
                    <button onClick={onClose} className="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <X size={20} />
                    </button>
                </div>

                {/* Body */}
                <div className="p-4 flex-1 overflow-y-auto custom-scrollbar">

                    {/* Form */}
                    <form onSubmit={handleSubmit} className="mb-6 bg-gray-50 dark:bg-tudu-column-dark p-4 rounded-lg border border-gray-100 dark:border-gray-700">
                        <p className="text-xs font-semibold text-gray-500 mb-3 uppercase tracking-wider">
                            {editingId ? 'Editar Etiqueta' : 'Nueva Etiqueta'}
                        </p>
                        <div className="space-y-3">
                            <div>
                                <input
                                    type="text"
                                    value={name}
                                    onChange={e => setName(e.target.value)}
                                    placeholder="Nombre de la etiqueta..."
                                    className="w-full p-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-tudu-bg-dark text-sm outline-none focus:ring-2 focus:ring-tudu-accent"
                                    required
                                />
                            </div>

                            <div>
                                <label className="text-xs text-gray-500 mb-2 block">Color</label>
                                <div className="flex flex-wrap gap-2">
                                    {COLORS.map(c => (
                                        <button
                                            key={c}
                                            type="button"
                                            onClick={() => setColor(c)}
                                            className={`w-6 h-6 rounded-full transition-transform hover:scale-110 ${color === c ? 'ring-2 ring-offset-2 ring-tudu-accent' : ''}`}
                                            style={{ backgroundColor: c }}
                                        />
                                    ))}
                                </div>
                            </div>

                            <div className="flex justify-end gap-2 pt-2">
                                {editingId && (
                                    <button
                                        type="button"
                                        onClick={resetForm}
                                        className="text-xs text-gray-500 hover:underline px-2"
                                    >
                                        Cancelar
                                    </button>
                                )}
                                <button
                                    type="submit"
                                    className="flex items-center gap-1 bg-tudu-accent hover:bg-tudu-accent-hover text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
                                >
                                    {editingId ? <Save size={14} /> : <Plus size={14} />}
                                    {editingId ? 'Actualizar' : 'Crear'}
                                </button>
                            </div>
                        </div>
                    </form>

                    {/* List */}
                    <div className="space-y-2">
                        {loading ? (
                            <div className="flex justify-center py-4 text-tudu-accent">
                                <Loader2 size={24} className="animate-spin" />
                            </div>
                        ) : tags.length === 0 ? (
                            <p className="text-center text-gray-500 text-sm py-4">No hay etiquetas creadas.</p>
                        ) : (
                            tags.map(tag => (
                                <div key={tag.id} className="flex items-center justify-between p-3 bg-white dark:bg-tudu-column-dark border border-gray-100 dark:border-gray-700 rounded-lg group hover:shadow-sm transition-shadow">
                                    <div className="flex items-center gap-3">
                                        <div
                                            className="w-3 h-3 rounded-full"
                                            style={{ backgroundColor: tag.color }}
                                        />
                                        <span className="text-sm font-medium text-tudu-text-light dark:text-gray-200">
                                            {tag.nombre}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-1 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button
                                            onClick={() => handleEdit(tag)}
                                            className="p-1.5 text-gray-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded"
                                            title="Editar"
                                        >
                                            <Edit2 size={14} />
                                        </button>
                                        <button
                                            onClick={() => handleDelete(tag.id)}
                                            className="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded"
                                            title="Eliminar"
                                        >
                                            <Trash2 size={14} />
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                </div>
            </div>
        </div>
    );
};

export default TagsModal;
