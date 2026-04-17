import { useState, useEffect } from 'react';
import { X, Save, Calendar as CalIcon, Lock, Globe, Sparkles, Settings, Loader2, Paperclip, Trash2, Tag, ChevronDown, Check, Send, ExternalLink } from 'lucide-react'; // Added Send, ExternalLink, and ImageIcon
import api, { BASE_URL } from '../../api/axios';
import AISettingsModal from '../settings/AISettingsModal';
import FilePreviewModal from '../common/FilePreviewModal';
import { useProjectFilter } from '../../contexts/ProjectFilterContext';

interface TaskModalProps {
    isOpen: boolean;
    onClose: () => void;
    task?: any; // If task is provided, we are editing
    onSave: () => void;
}

const TaskModal = ({ isOpen, onClose, task, onSave }: TaskModalProps) => {
    const { projectType, selectedProjectId } = useProjectFilter();

    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [projectId, setProjectId] = useState('');
    const [priority, setPriority] = useState('media');
    const [status, setStatus] = useState('pendiente');
    const [dueDate, setDueDate] = useState('');
    const [visibility, setVisibility] = useState('public');
    const [assignees, setAssignees] = useState<string[]>([]);

    const [projects, setProjects] = useState<any[]>([]);
    const [users, setUsers] = useState<any[]>([]);
    const [availableTags, setAvailableTags] = useState<any[]>([]);

    const [selectedTags, setSelectedTags] = useState<string[]>([]);

    const [saving, setSaving] = useState(false);
    const [aiLoading, setAiLoading] = useState<'suggest' | 'estimate' | null>(null);
    const [existingFiles, setExistingFiles] = useState<any[]>([]); // Files from DB
    const [files, setFiles] = useState<File[]>([]); // New files to upload
    const [uploadingFiles, setUploadingFiles] = useState(false);
    const [showAiSettings, setShowAiSettings] = useState(false);
    const [previewFile, setPreviewFile] = useState<{ url: string, name: string, type: string } | null>(null);

    const [isTagDropdownOpen, setIsTagDropdownOpen] = useState(false);

    // Comments State
    const [comments, setComments] = useState<any[]>([]);
    const [newComment, setNewComment] = useState('');
    const [sendingComment, setSendingComment] = useState(false);
    const [commentFile, setCommentFile] = useState<File | null>(null);
    // Public-share uploaded files (archivos_adjuntos)
    const [taskAdjuntos, setTaskAdjuntos] = useState<any[]>([]);

    // Mention State
    const [isMentioning, setIsMentioning] = useState(false);
    const [filteredUsers, setFilteredUsers] = useState<any[]>([]);

    useEffect(() => {
        if (isOpen) {
            fetchOptions();
            if (task && Object.keys(task).length > 0) {
                // Populate fields
                setTitle(task.titulo || '');
                setDescription(task.descripcion || '');
                setProjectId(task.proyecto_id || '');
                setPriority(task.prioridad || 'media');
                setStatus(task.estado || 'pendiente');
                setVisibility(task.visibility || 'public');

                setDueDate(task.fecha_termino || '');

                // Fetch tags
                if (task.id) {
                    fetchTaskTags(task.id);
                }

                // Fetch existing files
                if (task.id) {
                    fetchExistingFiles(task.id);
                }

                // Fetch comments
                if (task.id) {
                    fetchComments(task.id);
                }

                // Fetch public-share uploaded files
                if (task.id) {
                    fetchTaskAdjuntos(task.id);
                }

                // Handle assignees mapping if task.asignados exists
                if (task.asignados) {
                    setAssignees(task.asignados.map((u: any) => u.usuario_id || u.id));
                } else {
                    setAssignees([]);
                }
            } else {
                resetForm();
            }
        }
    }, [isOpen, task]);

    const resetForm = () => {
        setTitle('');
        setDescription('');
        // Pre-fill project and visibility from the current UI context
        setProjectId(selectedProjectId || '');
        setVisibility(projectType === 'private' ? 'private' : 'public');
        setPriority('media');
        setStatus('pendiente');
        setDueDate('');
        setAssignees([]);
        setFiles([]);
        setExistingFiles([]);
        setSelectedTags([]);
        setAiLoading(null);
        setUploadingFiles(false);
    };

    const fetchOptions = async () => {
        try {
            const res = await api.get('/get_options.php');
            if (res.data.status === 'success') {
                setProjects(res.data.projects);

                setUsers(res.data.users);
                setAvailableTags(res.data.tags || []);
            }
        } catch (e) {
            console.error(e);
        }
    };

    const fetchExistingFiles = async (taskId: number) => {
        try {
            const res = await api.get(`/resources.php?tarea_id=${taskId}`);
            if (res.data.status === 'success') {
                setExistingFiles(res.data.data || []);
            }
        } catch (e) {
            console.error('Error fetching files:', e);
        }
    };

    const fetchTaskAdjuntos = async (taskId: number) => {
        try {
            const res = await api.get(`/comments.php?action=files&tarea_id=${taskId}`);
            if (res.data.status === 'success' && Array.isArray(res.data.data)) {
                setTaskAdjuntos(res.data.data);
            }
        } catch (e) {
            console.error('Error fetching adjuntos:', e);
        }
    };

    const fetchTaskTags = async (taskId: number) => {
        try {
            const res = await api.get(`/get_task_tags.php?tarea_id=${taskId}`);
            if (res.data.status === 'success') {
                setSelectedTags(res.data.tags.map(String)); // Ensure strings
            }
        } catch (e) {
            console.error("Error fetching tags", e);
        }
    };

    const handleDeleteExistingFile = async (fileId: number) => {
        if (!confirm('¿Seguro que deseas eliminar este archivo?')) return;
        try {
            const res = await api.delete(`/resources.php?id=${fileId}`);
            if (res.data.status === 'success') {
                setExistingFiles(prev => prev.filter(f => f.id !== fileId));
            } else {
                alert('Error al eliminar archivo');
            }
        } catch (e) {
            console.error(e);
            alert('Error de conexión');
        }
    };

    const handleAiSuggest = async () => {
        if (!title && !description) {
            alert('Por favor escribe un título o descripción primero.');
            return;
        }
        setAiLoading('suggest');
        try {
            const res = await api.post('/ai_assistant.php', {
                action: 'suggest',
                title,
                description
            });
            if (res.data.status === 'success') {
                setDescription(prev => prev + res.data.suggestion);
            }
        } catch (e) {
            console.error(e);
            alert('Error al obtener sugerencias');
        } finally {
            setAiLoading(null);
        }
    };

    const handleAiEstimate = async () => {
        if (!title && !description) {
            alert('Por favor escribe un título o descripción primero.');
            return;
        }
        setAiLoading('estimate');
        try {
            const res = await api.post('/ai_assistant.php', {
                action: 'estimate',
                title,
                description
            });
            if (res.data.status === 'success') {
                setPriority(res.data.priority);
                setDueDate(res.data.due_date);
                // Append reasoning to description instead of alert
                setDescription(prev => prev + "\n\n🤖 " + res.data.reasoning);
            }
        } catch (e) {
            console.error(e);
            alert('Error al estimar');
        } finally {
            setAiLoading(null);
        }
    };

    const fetchComments = async (taskId: number) => {
        try {
            const res = await api.get(`/comments.php?tarea_id=${taskId}`);
            if (res.data.status === 'success') {
                setComments(res.data.data);
            }
        } catch (e) {
            console.error(e);
        }
    };


    const handleSendComment = async (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        if (!newComment.trim() || !task?.id) return;

        setSendingComment(true);
        try {
            const formData = new FormData();
            formData.append('tarea_id', String(task.id));
            formData.append('nota', newComment);
            if (commentFile) {
                formData.append('file', commentFile);
            }

            const res = await api.post('/comments.php', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            if (res.data.status === 'success') {
                setComments(prev => [...prev, res.data.data]);
                setNewComment('');
                setCommentFile(null);
            }
        } catch (e) {
            console.error(e);
            alert('Error al enviar comentario');
        } finally {
            setSendingComment(false);
        }
    };

    // Handle text change to detect @mentions
    const handleCommentChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
        const val = e.target.value;
        setNewComment(val);

        const textBeforeCursor = val.slice(0, e.target.selectionEnd);
        const lastWord = textBeforeCursor.split(/\s/).pop();

        if (lastWord && lastWord.startsWith('@')) {
            const query = lastWord.slice(1).toLowerCase();
            setIsMentioning(true);

            // Filter users
            const matched = users.filter(u =>
                u.nombre.toLowerCase().includes(query) ||
                (u.username && u.username.toLowerCase().includes(query))
            );
            setFilteredUsers(matched);

            // Rough position calculation (can be improved with a library but simple estimation for now)
            // We'll just show it near the textarea bottom-left for simplicity or use a fixed position relative to container
        } else {
            setIsMentioning(false);
        }
    };

    const insertMention = (username: string) => {
        const selectionEnd = (document.querySelector('textarea[name="commentInput"]') as HTMLTextAreaElement)?.selectionEnd || newComment.length;
        const textBeforeCursor = newComment.slice(0, selectionEnd);
        const textAfterCursor = newComment.slice(selectionEnd);

        const lastSpaceIndex = textBeforeCursor.lastIndexOf(' ');
        const lastNewlineIndex = textBeforeCursor.lastIndexOf('\n');
        const startOfWordIndex = Math.max(lastSpaceIndex, lastNewlineIndex) + 1; // start of the @word

        const newText = newComment.slice(0, startOfWordIndex) + '@' + username + ' ' + textAfterCursor;

        setNewComment(newText);
        setIsMentioning(false);

        // Refocus (optional, might need setTimeout)
    };

    // Formatter logic for comments
    const formatComment = (text: string) => {
        // 1. Split by new lines to handle paragraphs
        return text.split('\n').map((line, lineIdx) => {
            // 2. Split words to find URLs
            const words = line.split(' ');
            const formattedWords = words.map((word, wordIdx) => {
                // Regex for URL (simple)
                // Hits: http://..., https://..., www...., domain.com/..., etc.
                const urlRegex = /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/i;

                if (urlRegex.test(word) || word.startsWith('http')) {
                    let href = word;
                    if (!href.startsWith('http')) href = 'https://' + href;

                    // Check if image
                    const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(href);

                    if (isImage) {
                        return (
                            <div key={`${lineIdx}-${wordIdx}`} className="mt-2 mb-2">
                                <button
                                    type="button"
                                    onClick={() => setPreviewFile({
                                        url: href,
                                        name: 'Imagen',
                                        type: 'image/jpeg'
                                    })}
                                    className="block w-fit focus:outline-none"
                                >
                                    <img
                                        src={href}
                                        alt="Preview"
                                        className="max-h-60 rounded-lg border border-gray-200 dark:border-gray-700 hover:opacity-90 transition-opacity shadow-sm"
                                        onError={(e) => e.currentTarget.style.display = 'none'}
                                    />
                                </button>
                            </div>
                        );
                    }

                    return (
                        <a
                            key={`${lineIdx}-${wordIdx}`}
                            href={href}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-blue-500 hover:text-blue-600 hover:underline break-all"
                        >
                            {word} <ExternalLink size={10} className="inline mb-1" />
                        </a>
                    );
                }
                return word + ' ';
            });

            return <div key={lineIdx} className="min-h-[1.2em]">{formattedWords}</div>;
        });
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files) {
            const newFiles = Array.from(e.target.files);
            setFiles(prev => {
                // Filter out duplicates based on name and size
                const uniqueNewFiles = newFiles.filter(nf =>
                    !prev.some(pf => pf.name === nf.name && pf.size === nf.size)
                );
                return [...prev, ...uniqueNewFiles];
            });
            // Clear input so same file can be selected again if removed
            e.target.value = '';
        }
    };

    const removeFile = (index: number) => {
        setFiles(prev => prev.filter((_, i) => i !== index));
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = {
                id: task?.id || null,
                titulo: title,
                descripcion: description,
                proyecto_id: projectId,
                prioridad: priority,
                estado: status,
                visibility: visibility,
                fecha_termino: dueDate,

                assignees: assignees,
                tags: selectedTags
            };

            const res = await api.post('/save_task.php', payload);
            if (res.data.status === 'success') {
                const taskId = res.data.id;

                // Handle file uploads if any
                if (files.length > 0) {
                    setUploadingFiles(true);
                    const uploadPromises = files.map(file => {
                        const formData = new FormData();
                        formData.append('file', file);
                        formData.append('tarea_id', taskId);
                        // Optional: Append user_id if needed separately
                        return api.post('/resources.php', formData, {
                            headers: { 'Content-Type': 'multipart/form-data' }
                        });
                    });

                    await Promise.all(uploadPromises);
                    setUploadingFiles(false);
                }

                onSave();
                onClose();
            } else {
                alert('Error: ' + res.data.message);
            }
        } catch (e) {
            console.error(e);
            alert('Error al guardar tarea o archivos');
        } finally {
            setSaving(false);
            setUploadingFiles(false);
        }
    };

    if (!isOpen) return null;

    return (
        <div
            className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            onClick={onClose}
        >
            <div
                className="bg-white dark:bg-tudu-content-dark w-full max-w-2xl rounded-xl shadow-2xl flex flex-col max-h-[90vh]"
                onClick={(e) => e.stopPropagation()}
            >

                {/* Header */}
                <div className="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 className="text-xl font-bold text-tudu-text-light dark:text-tudu-text-dark">
                        {task?.id ? 'Editar Tarea' : 'Nueva Tarea'}
                    </h2>
                    <button onClick={onClose} className="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <X size={24} />
                    </button>
                </div>

                {/* Body */}
                <form onSubmit={handleSubmit} className="p-6 pb-20 sm:pb-6 overflow-y-auto flex-1 custom-scrollbar">

                    <div className="space-y-4">
                        {/* Title */}
                        <div id="tour-task-title">
                            <label className="block text-sm font-medium text-tudu-text-muted mb-1">Título</label>
                            <input
                                type="text"
                                value={title}
                                onChange={e => setTitle(e.target.value)}
                                className="w-full p-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-tudu-bg-dark text-tudu-text-light dark:text-white focus:ring-2 focus:ring-tudu-accent outline-none transition-all"
                                placeholder="Escribe el título de la tarea..."
                                required
                            />
                        </div>

                        {/* Description */}
                        <div>
                            <label className="block text-sm font-medium text-tudu-text-muted mb-1">Descripción</label>
                            <textarea
                                value={description}
                                onChange={e => setDescription(e.target.value)}
                                className="w-full p-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-tudu-bg-dark text-tudu-text-light dark:text-white focus:ring-2 focus:ring-tudu-accent outline-none transition-all resize-none h-32"
                                placeholder="Detalles de la tarea..."
                            />

                            {/* AI Buttons */}
                            <div id="tour-task-ai" className="flex flex-col gap-3 mb-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            onClick={handleAiSuggest}
                                            disabled={!!aiLoading || saving}
                                            className="flex items-center gap-1 text-xs font-medium text-purple-600 bg-purple-50 hover:bg-purple-100 px-3 py-1.5 rounded-lg transition-colors border border-purple-200 dark:bg-purple-900/20 dark:border-purple-800 dark:text-purple-300 disabled:opacity-70 disabled:cursor-wait"
                                        >
                                            {aiLoading === 'suggest' ? <Loader2 size={14} className="animate-spin" /> : <Sparkles size={14} />}
                                            {aiLoading === 'suggest' ? 'Pensando...' : 'Sugerir Contenido'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={handleAiEstimate}
                                            disabled={!!aiLoading || saving}
                                            className="flex items-center gap-1 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors border border-blue-200 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300 disabled:opacity-70 disabled:cursor-wait"
                                        >
                                            {aiLoading === 'estimate' ? <Loader2 size={14} className="animate-spin" /> : <CalIcon size={14} />}
                                            {aiLoading === 'estimate' ? 'Calculando...' : 'Estimar Esfuerzo'}
                                        </button>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setShowAiSettings(true)}
                                        className="p-1.5 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
                                        title="Configurar IA"
                                    >
                                        <Settings size={16} />
                                    </button>
                                </div>

                                {/* Attachments */}
                                <div>
                                    <label className="flex items-center gap-2 text-sm font-medium text-tudu-text-muted cursor-pointer hover:text-tudu-accent transition-colors w-fit">
                                        <Paperclip size={16} />
                                        <span>Adjuntar Archivos</span>
                                        <input
                                            type="file"
                                            multiple
                                            onChange={handleFileChange}
                                            className="hidden"
                                        />
                                    </label>

                                    {existingFiles.length > 0 && (
                                        <div className="mt-2 flex flex-col gap-1">
                                            <p className="text-xs font-semibold text-gray-500 mb-1">Archivos Adjuntos:</p>
                                            {existingFiles.map((file) => {
                                                // Correct URI: uploads is in root, api is in root.
                                                // filepath is ../uploads/file.png relative to api/
                                                // so web path is /tudu_development/uploads/file.png
                                                const fileUrl = `${BASE_URL}/${file.filepath.replace('../', '')}`;
                                                return (
                                                    <div key={file.id} className="flex items-center justify-between gap-2 bg-blue-50 dark:bg-blue-900/10 px-3 py-1.5 rounded-lg text-sm text-blue-700 dark:text-blue-300 border border-blue-100 dark:border-blue-900/30">
                                                        <button
                                                            type="button"
                                                            onClick={() => setPreviewFile({
                                                                url: fileUrl,
                                                                name: file.filename,
                                                                type: file.filetype
                                                            })}
                                                            className="truncate hover:underline text-left flex-1"
                                                        >
                                                            {file.filename}
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleDeleteExistingFile(file.id)}
                                                            className="text-gray-400 hover:text-red-500 transition-colors p-1"
                                                            title="Eliminar archivo"
                                                        >
                                                            <Trash2 size={14} />
                                                        </button>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}

                                    {files.length > 0 && (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {files.map((file, idx) => (
                                                <div key={idx} className="flex items-center gap-2 bg-gray-100 dark:bg-gray-700 px-3 py-1 rounded-full text-xs text-gray-700 dark:text-gray-200">
                                                    <span className="truncate max-w-[150px]">{file.name}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeFile(idx)}
                                                        className="text-gray-400 hover:text-red-500"
                                                    >
                                                        <X size={12} />
                                                    </button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                {/* Tags */}
                                {/* Tags Section */}
                                <div id="tour-task-tags" className="relative">
                                    <label className="block text-sm font-medium text-tudu-text-muted mb-1 flex items-center gap-2">
                                        <Tag size={16} /> Etiquetas
                                    </label>

                                    {/* Selected Tags Pills */}
                                    <div className="flex flex-wrap gap-2 mb-2">
                                        {selectedTags.map(tagId => {
                                            const tag = availableTags.find(t => String(t.id) === tagId);
                                            if (!tag) return null;
                                            return (
                                                <div
                                                    key={tag.id}
                                                    className="px-2 py-1 rounded-full text-xs font-medium text-white flex items-center gap-1 shadow-sm"
                                                    style={{ backgroundColor: tag.color || '#6B7280' }}
                                                >
                                                    {tag.nombre}
                                                    <button
                                                        type="button"
                                                        onClick={() => setSelectedTags(prev => prev.filter(t => t !== tagId))}
                                                        className="hover:text-red-200 transition-colors"
                                                    >
                                                        <X size={12} />
                                                    </button>
                                                </div>
                                            );
                                        })}

                                        {/* Dropdown Toggle Button */}
                                        <button
                                            type="button"
                                            onClick={() => setIsTagDropdownOpen(!isTagDropdownOpen)}
                                            className="px-2 py-1 rounded-full text-xs font-medium border border-dashed border-gray-400 text-gray-500 hover:border-gray-600 hover:text-gray-700 dark:border-gray-600 dark:text-gray-400 dark:hover:text-gray-200 flex items-center gap-1 transition-colors"
                                        >
                                            <ChevronDown size={12} /> Seleccionar
                                        </button>
                                    </div>

                                    {/* Dropdown Menu */}
                                    {isTagDropdownOpen && (
                                        <>
                                            <div
                                                className="fixed inset-0 z-10"
                                                onClick={() => setIsTagDropdownOpen(false)}
                                            />
                                            <div className="absolute z-20 top-full left-0 mt-1 w-64 bg-white dark:bg-tudu-column-dark border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl overflow-hidden animate-in fade-in zoom-in-95 duration-100">
                                                <div className="max-h-48 overflow-y-auto custom-scrollbar p-1">
                                                    {availableTags.length === 0 ? (
                                                        <p className="p-2 text-xs text-gray-500 text-center">No hay etiquetas disponibles</p>
                                                    ) : (
                                                        availableTags.map(tag => {
                                                            const isSelected = selectedTags.includes(String(tag.id));
                                                            return (
                                                                <button
                                                                    key={tag.id}
                                                                    type="button"
                                                                    onClick={() => {
                                                                        const idStr = String(tag.id);
                                                                        setSelectedTags(prev =>
                                                                            prev.includes(idStr)
                                                                                ? prev.filter(t => t !== idStr)
                                                                                : [...prev, idStr]
                                                                        );
                                                                    }}
                                                                    className="w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors text-left"
                                                                >
                                                                    <div
                                                                        className="w-3 h-3 rounded-full flex-shrink-0"
                                                                        style={{ backgroundColor: tag.color || '#6B7280' }}
                                                                    />
                                                                    <span className="flex-1 text-tudu-text-light dark:text-gray-200 truncate">{tag.nombre}</span>
                                                                    {isSelected && <Check size={14} className="text-tudu-accent" />}
                                                                </button>
                                                            );
                                                        })
                                                    )}
                                                </div>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </div>

                            <hr className="border-gray-100 dark:border-gray-700 mb-4" />

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {/* Project */}
                                <div>
                                    <label className="block text-sm font-medium text-tudu-text-muted mb-1">Proyecto</label>
                                    <select
                                        value={projectId}
                                        onChange={e => setProjectId(e.target.value)}
                                        className="w-full p-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-tudu-bg-dark text-tudu-text-light dark:text-white focus:ring-2 focus:ring-tudu-accent outline-none"
                                        required
                                    >
                                        <option value="">Seleccionar Proyecto...</option>
                                        {projects
                                            .filter(p => {
                                                if (visibility === 'private') return p.user_id !== 0 && p.user_id !== null;
                                                return p.user_id === 0 || p.user_id === null;
                                            })
                                            .map(p => (
                                                <option key={p.id} value={p.id}>{p.nombre}</option>
                                            ))}
                                    </select>
                                </div>

                                {/* Priority */}
                                <div>
                                    <label className="block text-sm font-medium text-tudu-text-muted mb-1">Prioridad</label>
                                    <select
                                        value={priority}
                                        onChange={e => setPriority(e.target.value)}
                                        className="w-full p-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-tudu-bg-dark text-tudu-text-light dark:text-white focus:ring-2 focus:ring-tudu-accent outline-none"
                                    >
                                        <option value="baja">Baja 🟢</option>
                                        <option value="media">Media 🟡</option>
                                        <option value="alta">Alta 🔴</option>
                                    </select>
                                </div>
                            </div>

                            {/* Status */}
                            <div className="mt-4">
                                <label className="block text-sm font-medium text-tudu-text-muted mb-1">Estado</label>
                                <select
                                    value={status}
                                    onChange={e => setStatus(e.target.value)}
                                    className="w-full p-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-tudu-bg-dark text-tudu-text-light dark:text-white focus:ring-2 focus:ring-tudu-accent outline-none"
                                >
                                    <option value="pendiente">⏳ Pendiente</option>
                                    <option value="en_progreso">🔄 En Progreso</option>
                                    <option value="completado">✅ Completado</option>
                                </select>
                            </div>

                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Visibility */}
                            <div>
                                <label className="block text-sm font-medium text-tudu-text-muted mb-1">Visibilidad</label>
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setVisibility('public')}
                                        className={`flex-1 p-2 rounded-lg border flex items-center justify-center gap-2 transition-all ${visibility === 'public'
                                            ? 'bg-blue-50 border-blue-200 text-blue-600 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-400'
                                            : 'bg-white dark:bg-tudu-bg-dark border-gray-300 dark:border-gray-600 text-gray-500'
                                            }`}
                                    >
                                        <Globe size={18} />
                                        <span>Público</span>
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setVisibility('private')}
                                        className={`flex-1 p-2 rounded-lg border flex items-center justify-center gap-2 transition-all ${visibility === 'private'
                                            ? 'bg-purple-50 border-purple-200 text-purple-600 dark:bg-purple-900/20 dark:border-purple-800 dark:text-purple-400'
                                            : 'bg-white dark:bg-tudu-bg-dark border-gray-300 dark:border-gray-600 text-gray-500'
                                            }`}
                                    >
                                        <Lock size={18} />
                                        <span>Privado</span>
                                    </button>
                                </div>
                            </div>

                            {/* Due Date */}
                            <div>
                                <label className="block text-sm font-medium text-tudu-text-muted mb-1">Fecha de Término</label>
                                <div className="relative">
                                    <input
                                        type="date"
                                        value={dueDate}
                                        onChange={e => setDueDate(e.target.value)}
                                        className="w-full p-2 pl-9 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-tudu-bg-dark text-tudu-text-light dark:text-white focus:ring-2 focus:ring-tudu-accent outline-none"
                                    />
                                    <CalIcon size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                                </div>
                            </div>
                        </div>

                        <div id="tour-task-assignees">
                            {/* Assignees */}
                            <div>
                                <label className="block text-sm font-medium text-tudu-text-muted mb-1">Asignar a</label>
                                <select
                                    multiple
                                    value={assignees}
                                    onChange={e => setAssignees(Array.from(e.target.selectedOptions, option => option.value))}
                                    className="w-full p-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-tudu-bg-dark text-tudu-text-light dark:text-white focus:ring-2 focus:ring-tudu-accent outline-none h-24"
                                >
                                    {users.map(u => (
                                        <option key={u.id} value={u.id}>{u.nombre}</option>
                                    ))}
                                </select>
                                <p className="text-xs text-gray-400 mt-1">Mantén Ctrl para seleccionar varios</p>
                            </div>
                        </div>


                        <div>
                            <hr className="border-gray-100 dark:border-gray-700 my-6" />

                            {/* Task Attachments from public share uploads */}
                            {Array.isArray(taskAdjuntos) && taskAdjuntos.length > 0 && (
                                <div className="mb-6">
                                    <h3 className="text-sm font-semibold text-tudu-text-muted mb-3 uppercase tracking-wider flex items-center gap-2">
                                        <Paperclip size={16} /> Archivos Adjuntos ({taskAdjuntos.length})
                                    </h3>
                                    <div className="space-y-2">
                                        {taskAdjuntos.map((f: any) => {
                                            const fileType = f.tipo_archivo || 'application/octet-stream';
                                            const isImage = fileType.startsWith('image/');
                                            const isPdf = fileType === 'application/pdf';
                                            const fileUrl = `${BASE_URL}/uploads/${f.nombre_archivo}`;
                                            return (
                                                <button
                                                    key={f.id}
                                                    type="button"
                                                    onClick={() => setPreviewFile({ url: fileUrl, name: f.nombre_original, type: fileType })}
                                                    className="w-full flex items-center gap-3 p-2 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 hover:border-tudu-accent hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-left cursor-pointer"
                                                >
                                                    {isImage ? (
                                                        <img
                                                            src={fileUrl}
                                                            alt={f.nombre_original}
                                                            className="w-10 h-10 rounded object-cover flex-shrink-0"
                                                            onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                                                        />
                                                    ) : (
                                                        <div className={`w-10 h-10 rounded flex items-center justify-center flex-shrink-0 ${isPdf ? 'bg-red-100 dark:bg-red-900/30' : 'bg-gray-200 dark:bg-gray-700'}`}>
                                                            <Paperclip size={18} className={isPdf ? 'text-red-500' : 'text-gray-500'} />
                                                        </div>
                                                    )}
                                                    <div className="flex-1 min-w-0">
                                                        <p className="text-xs font-medium text-gray-700 dark:text-gray-200 truncate">{f.nombre_original}</p>
                                                        <p className="text-[10px] text-gray-400">{f.tamano ? `${Math.round(f.tamano / 1024)} KB` : ''} · Click para ver</p>
                                                    </div>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            <h3 id="tour-task-comments" className="text-sm font-semibold text-tudu-text-muted mb-4 uppercase tracking-wider flex items-center gap-2">
                                <Send size={16} /> Comentarios
                            </h3>

                            {/* Comment List */}
                            <div className="space-y-4 mb-4 max-h-60 overflow-y-auto custom-scrollbar p-1">
                                {comments.length === 0 ? (
                                    <p className="text-sm text-gray-400 text-center italic py-2">No hay comentarios aún.</p>
                                ) : (
                                    comments.map((comment, idx) => (
                                        <div key={idx} className="flex gap-3 animate-in fade-in slide-in-from-bottom-2 duration-300">
                                            <div className="flex-shrink-0">
                                                {comment.user_avatar ? (
                                                    <img
                                                        src={(() => {
                                                            const av = comment.user_avatar.replace(/^\.\.\//, '');
                                                            return av.startsWith('http') ? av : `${BASE_URL}/${av}`;
                                                        })()}
                                                        alt={comment.user_name}
                                                        className="w-8 h-8 rounded-full object-cover"
                                                        onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                                                    />
                                                ) : (
                                                    <div className="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-bold text-gray-500 dark:text-gray-300">
                                                        {(comment.display_name || comment.user_name || 'I')[0].toUpperCase()}
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex-1 bg-gray-50 dark:bg-tudu-column-dark p-3 rounded-lg rounded-tl-none border border-gray-100 dark:border-gray-700">
                                                <div className="flex justify-between items-start mb-1">
                                                    <span className="text-xs font-bold text-gray-700 dark:text-gray-200">
                                                        {comment.display_name || comment.user_name || 'Invitado'}
                                                        {!comment.user_name && <span className="ml-1 text-[10px] font-normal text-gray-400">(externo)</span>}
                                                    </span>
                                                    <span className="text-[10px] text-gray-400">{new Date(comment.fecha_creacion).toLocaleString()}</span>
                                                </div>
                                                <div className="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-wrap">
                                                    {formatComment(comment.nota)}
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>

                            {/* Comment Input */}
                            {task?.id && (
                                <div className="space-y-2">
                                    {commentFile && (
                                        <div className="relative w-fit group">
                                            <button
                                                type="button"
                                                onClick={() => setPreviewFile({
                                                    url: URL.createObjectURL(commentFile),
                                                    name: commentFile.name,
                                                    type: commentFile.type
                                                })}
                                                className="block focus:outline-none"
                                            >
                                                <img
                                                    src={URL.createObjectURL(commentFile)}
                                                    alt="Preview"
                                                    className="h-20 w-auto rounded-lg border border-gray-200 dark:border-gray-700 object-cover hover:opacity-90 transition-opacity"
                                                />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setCommentFile(null)}
                                                className="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 shadow-md hover:bg-red-600 transition-colors"
                                                title="Eliminar imagen"
                                            >
                                                <X size={12} />
                                            </button>
                                        </div>
                                    )}

                                    <div className="flex gap-2 items-start">
                                        <div className="flex-shrink-0 pt-1">
                                            <div className="w-8 h-8 rounded-full bg-tudu-accent flex items-center justify-center text-white text-xs font-bold">
                                                Yo
                                            </div>
                                        </div>
                                        <div className="flex-1 relative">
                                            <textarea
                                                name="commentInput"
                                                value={newComment}
                                                onChange={handleCommentChange}
                                                onKeyDown={e => {
                                                    if (e.key === 'Enter' && !e.shiftKey) {
                                                        if (isMentioning) {
                                                            e.preventDefault();
                                                            if (filteredUsers.length > 0) {
                                                                insertMention(filteredUsers[0].username || filteredUsers[0].nombre.replace(/\s+/g, '').toLowerCase());
                                                            }
                                                        } else {
                                                            e.preventDefault();
                                                            handleSendComment();
                                                        }
                                                    }
                                                    if (e.key === 'Escape' && isMentioning) {
                                                        setIsMentioning(false);
                                                    }
                                                }}
                                                placeholder="Escribe un comentario... (@ para mencionar)"
                                                className="w-full p-2 pr-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-tudu-bg-dark text-tudu-text-light dark:text-white focus:ring-2 focus:ring-tudu-accent outline-none resize-none overflow-hidden min-h-[40px]"
                                                rows={1}
                                                style={{ height: 'auto', minHeight: '40px' }}
                                                onInput={(e) => {
                                                    const target = e.target as HTMLTextAreaElement;
                                                    target.style.height = 'auto';
                                                    target.style.height = target.scrollHeight + 'px';
                                                }}
                                            />

                                            {/* Mention Dropdown */}
                                            {isMentioning && filteredUsers.length > 0 && (
                                                <div className="absolute bottom-full left-0 mb-1 w-64 bg-white dark:bg-tudu-content-dark border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl overflow-hidden max-h-48 overflow-y-auto z-10">
                                                    {filteredUsers.map(u => (
                                                        <button
                                                            key={u.id}
                                                            type="button"
                                                            onClick={() => insertMention(u.username || u.nombre.replace(/\s+/g, '').toLowerCase())}
                                                            className="w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-left"
                                                        >
                                                            <div className="w-6 h-6 rounded-full bg-tudu-accent flex items-center justify-center text-white text-[10px] font-bold">
                                                                {u.nombre[0]}
                                                            </div>
                                                            <div>
                                                                <p className="font-bold text-gray-800 dark:text-gray-200 text-xs">{u.nombre}</p>
                                                                <p className="text-gray-500 text-[10px]">@{u.username || u.nombre.replace(/\s+/g, '').toLowerCase()}</p>
                                                            </div>
                                                        </button>
                                                    ))}
                                                </div>
                                            )}

                                            <div className="absolute right-2 bottom-1.5 flex items-center gap-1">
                                                <label className="cursor-pointer text-gray-400 hover:text-tudu-accent transition-colors p-1">
                                                    <Paperclip size={16} />
                                                    <input
                                                        type="file"
                                                        className="hidden"
                                                        accept="image/*"
                                                        onChange={e => {
                                                            if (e.target.files?.[0]) {
                                                                setCommentFile(e.target.files[0]);
                                                            }
                                                        }}
                                                    />
                                                </label>
                                                <button
                                                    type="button"
                                                    onClick={() => handleSendComment()}
                                                    disabled={(!newComment.trim() && !commentFile) || sendingComment}
                                                    className="text-tudu-accent hover:text-tudu-accent-hover disabled:opacity-50 transition-colors p-1"
                                                >
                                                    {sendingComment ? <Loader2 size={16} className="animate-spin" /> : <Send size={16} />}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>

                    </div>

                    {/* Footer */}
                    <div className="flex justify-end gap-3 mt-8 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={saving || uploadingFiles}
                            className="flex items-center gap-2 bg-tudu-accent hover:bg-tudu-accent-hover text-white px-6 py-2 rounded-lg font-medium shadow-md transition-all transform active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {saving ? 'Guardando...' : uploadingFiles ? 'Subiendo archivos...' : <><Save size={18} /> Guardar</>}
                        </button>
                    </div>

                </form>
            </div >
            <AISettingsModal isOpen={showAiSettings} onClose={() => setShowAiSettings(false)} />
            {previewFile && (
                <FilePreviewModal
                    isOpen={!!previewFile}
                    onClose={() => setPreviewFile(null)}
                    fileUrl={previewFile.url}
                    fileName={previewFile.name}
                    fileType={previewFile.type}
                />
            )}
        </div >
    );
};

export default TaskModal;
