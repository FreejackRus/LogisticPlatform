import { cn } from '@/lib/utils';
import * as React from 'react';
import { Button, ButtonProps } from './button';
import { Popover, PopoverContent, PopoverTrigger } from './popover';

export interface ConfirmButtonProps extends ButtonProps {
    confirmText?: string;
    cancelText?: string;
    onConfirm?: () => void;
    popoverClassName?: string;
    popoverContentClassName?: string;
    align?: 'center' | 'start' | 'end';
}

const ConfirmButton = React.forwardRef<HTMLButtonElement, ConfirmButtonProps>(
    (
        {
            children,
            className,
            variant = 'default',
            size,
            confirmText = 'Подтвердить',
            cancelText = 'Отмена',
            onConfirm,
            popoverClassName,
            popoverContentClassName,
            align = 'center',
            ...props
        },
        ref,
    ) => {
        const [open, setOpen] = React.useState(false);

        const handleConfirm = (e: React.MouseEvent) => {
            e.preventDefault();
            e.stopPropagation();
            setOpen(false);
            if (onConfirm) onConfirm();
        };

        return (
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        ref={ref}
                        variant={variant}
                        size={size}
                        className={className}
                        {...props}
                    >
                        {children}
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className={cn('w-auto p-0', popoverContentClassName)}
                    align={align}
                >
                    <div
                        className={cn(
                            'flex flex-col gap-1 p-2',
                            popoverClassName,
                        )}
                    >
                        <Button
                            size="sm"
                            onClick={handleConfirm}
                            variant="destructive"
                        >
                            {confirmText}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            {cancelText}
                        </Button>
                    </div>
                </PopoverContent>
            </Popover>
        );
    },
);

ConfirmButton.displayName = 'ConfirmButton';

export { ConfirmButton };

// Usage example:
/*
  <ConfirmButton 
    onConfirm={() => handleDelete()}
    confirmText="Удалить"
    cancelText="Отмена"
    variant="destructive"
  >
    Удалить
  </ConfirmButton>
*/
