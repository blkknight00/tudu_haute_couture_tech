import { X, Download, FileText, Image as ImageIcon } from 'lucide-react';

interface FilePreviewModalProps {
    isOpen: boolean;
    onClose: () => void;
    fileUrl: string;
    fileName: string;
    fileType: string;
}

const FilePreviewModal = ({ isOpen, onClose, fileUrl, fileName, fileType }: FilePreviewModalProps) => {
    if (!isOpen) return null;

    const isImage = fileType.startsWith('image/');
    const isPdf = fileType === 'application/pdf';

    return (
        <div
            className="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 animate-in fade-in duration-200"
            onClick={onClose}
        >
            <div
                className="bg-white dark:bg-tudu-content-dark w-full max-w-5xl h-[85vh] rounded-xl shadow-2xl flex flex-col relative overflow-hidden"
                onClick={(e) => e.stopPropagation()}
            >

                {/* Header */}
                <div className="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <div className="flex items-center gap-3 overflow-hidden">
                        <div className="p-2 bg-white dark:bg-gray-700 rounded-lg shadow-sm">
                            {isImage ? <ImageIcon size={20} className="text-blue-500" /> : <FileText size={20} className="text-orange-500" />}
                        </div>
                        <h3 className="font-semibold text-gray-800 dark:text-white truncate" title={fileName}>
                            {fileName}
                        </h3>
                    </div>
                    <div className="flex items-center gap-2">
                        <a
                            href={fileUrl}
                            download={fileName}
                            className="p-2 text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 hover:bg-white dark:hover:bg-gray-700 rounded-lg transition-all"
                            title="Descargar"
                        >
                            <Download size={20} />
                        </a>
                        <button
                            onClick={onClose}
                            className="p-2 text-gray-500 hover:text-red-500 dark:text-gray-400 dark:hover:text-red-400 hover:bg-white dark:hover:bg-gray-700 rounded-lg transition-all"
                        >
                            <X size={20} />
                        </button>
                    </div>
                </div>

                {/* Content */}
                <div className="flex-1 bg-gray-100 dark:bg-gray-900 flex items-center justify-center overflow-auto p-4">
                    {isImage ? (
                        <img
                            src={fileUrl}
                            alt={fileName}
                            className="max-w-full max-h-full object-contain shadow-lg rounded-md"
                        />
                    ) : isPdf ? (
                        <iframe
                            src={fileUrl}
                            className="w-full h-full rounded-md shadow-lg bg-white"
                            title="PDF Preview"
                        />
                    ) : (
                        <div className="text-center p-10">
                            <div className="bg-gray-200 dark:bg-gray-800 p-6 rounded-full inline-flex mb-4">
                                <FileText size={48} className="text-gray-400" />
                            </div>
                            <p className="text-lg text-gray-600 dark:text-gray-300 mb-2">Vista previa no disponible</p>
                            <p className="text-sm text-gray-500 mb-6">Este tipo de archivo no se puede visualizar aquí directly.</p>
                            <a
                                href={fileUrl}
                                download={fileName}
                                className="inline-flex items-center gap-2 bg-tudu-accent hover:bg-tudu-accent-hover text-white px-6 py-2 rounded-lg font-medium transition-colors shadow-md"
                            >
                                <Download size={18} /> Descargar Archivo
                            </a>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default FilePreviewModal;
