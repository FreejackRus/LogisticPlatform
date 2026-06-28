import InputError from '@/Components/InputError';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { forwardRef, useCallback, useImperativeHandle, useState } from 'react';
import { useDropzone } from 'react-dropzone';

interface FilePreview {
    url: string | null;
    isImage: boolean;
    fileName: string;
    fileType: string;
}

interface FileUploadProps {
    initialPreview?: string | null;
    onFileChange: (file: File | null) => void;
    onRemove?: () => void;
    accept?: Record<string, string[]>;
    maxFiles?: number;
    maxSize?: number;
    previewType?: 'avatar' | 'thumbnail' | 'none';
    label?: string;
    hint?: string;
    error?: string;
    className?: string;
    avatarClassName?: string;
    fallbackText?: string;
}

export interface FileUploadRef {
    reset: () => void;
}

export default forwardRef<FileUploadRef, FileUploadProps>(function FileUpload(
    {
        initialPreview = null,
        onFileChange,
        onRemove,
        accept = {},
        maxFiles = 1,
        maxSize = 10485760, // 10MB
        previewType = 'thumbnail',
        label = 'Перетащите файл сюда или нажмите для выбора',
        hint = 'Файлы до 10 МБ',
        error,
        className = '',
        avatarClassName = 'h-20 w-20',
        fallbackText = '',
    },
    ref,
) {
    const [filePreview, setFilePreview] = useState<FilePreview | null>(
        initialPreview
            ? { url: initialPreview, isImage: true, fileName: '', fileType: '' }
            : null,
    );

    // Expose reset method via ref
    useImperativeHandle(ref, () => ({
        reset: () => {
            setFilePreview(null);
            onFileChange(null);
            if (onRemove) {
                onRemove();
            }
        },
    }));

    const onDrop = useCallback(
        (acceptedFiles: File[]) => {
            const file = acceptedFiles[0];
            if (file) {
                onFileChange(file);

                // Create preview based on file type
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        setFilePreview({
                            url: e.target?.result as string,
                            isImage: true,
                            fileName: file.name,
                            fileType: file.type,
                        });
                    };
                    reader.readAsDataURL(file);
                } else {
                    // For non-image files, store file info without preview URL
                    setFilePreview({
                        url: null,
                        isImage: false,
                        fileName: file.name,
                        fileType: file.type,
                    });
                }
            }
        },
        [onFileChange],
    );

    const removeFile = () => {
        setFilePreview(null);
        onFileChange(null);
        if (onRemove) {
            onRemove();
        }
    };

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept,
        maxFiles,
        maxSize,
    });

    // Helper function to get file extension
    const getFileExtension = (fileName: string) => {
        return fileName.split('.').pop()?.toUpperCase() || '';
    };

    return (
        <div className={className}>
            <div className="mt-2 flex items-center gap-6">
                {previewType !== 'none' && filePreview && (
                    <div className="flex flex-col items-center gap-2">
                        {filePreview.isImage && filePreview.url ? (
                            // Image preview
                            previewType === 'avatar' ? (
                                <Avatar className={avatarClassName}>
                                    <AvatarImage
                                        src={filePreview.url}
                                        alt="Предпросмотр"
                                    />
                                    <AvatarFallback>
                                        {fallbackText}
                                    </AvatarFallback>
                                </Avatar>
                            ) : (
                                <div className="relative h-20 w-20 overflow-hidden rounded-md">
                                    <img
                                        src={filePreview.url}
                                        alt="Предпросмотр"
                                        className="h-full w-full object-cover"
                                    />
                                </div>
                            )
                        ) : (
                            // Non-image file preview
                            <div className="flex h-20 w-20 flex-col items-center justify-center rounded-md border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                                <div className="text-xl font-semibold text-gray-500 dark:text-gray-400">
                                    {getFileExtension(filePreview.fileName)}
                                </div>
                                <div className="mt-1 max-w-full truncate px-1 text-xs text-gray-500">
                                    {filePreview.fileName.length > 10
                                        ? `${filePreview.fileName.substring(0, 10)}...`
                                        : filePreview.fileName}
                                </div>
                            </div>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={removeFile}
                        >
                            Удалить файл
                        </Button>
                    </div>
                )}

                <div
                    {...getRootProps()}
                    className={`flex cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed p-6 ${
                        isDragActive
                            ? 'border-primary bg-primary/10'
                            : 'border-gray-300 dark:border-gray-700'
                    }`}
                >
                    <input {...getInputProps()} />
                    <div className="text-center">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            {isDragActive ? 'Отпустите файл здесь' : label}
                        </p>
                        {hint && (
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-500">
                                {hint}
                            </p>
                        )}
                    </div>
                </div>
            </div>

            {error && <InputError className="mt-2" message={error} />}
        </div>
    );
});
