import { cn } from '@/lib/utils';
import * as React from 'react';
import { Button } from './button';
import { DropdownMenuItem } from './dropdown-menu';
import { Popover, PopoverContent, PopoverTrigger } from './popover';

export interface ConfirmDropdownMenuItemProps
    extends React.ComponentPropsWithoutRef<typeof DropdownMenuItem> {
    confirmText?: string;
    cancelText?: string;
    onConfirm?: () => void;
    popoverClassName?: string;
    popoverContentClassName?: string;
    align?: 'center' | 'start' | 'end';
    inset?: boolean;
}

const ConfirmDropdownMenuItem = React.forwardRef<
    HTMLDivElement,
    ConfirmDropdownMenuItemProps
>(
    (
        {
            children,
            className,
            confirmText = 'Подтвердить',
            cancelText = 'Отмена',
            onConfirm,
            popoverClassName,
            popoverContentClassName,
            align = 'center',
            inset,
            ...props
        },
        ref,
    ) => {
        const [open, setOpen] = React.useState(false);
        const closeTimeoutRef = React.useRef<NodeJS.Timeout | null>(null);
        const popoverRef = React.useRef<HTMLDivElement>(null);

        // Clear any existing timeout when component unmounts
        React.useEffect(() => {
            return () => {
                if (closeTimeoutRef.current) {
                    clearTimeout(closeTimeoutRef.current);
                }
            };
        }, []);

        const handleOpenChange = (newOpen: boolean) => {
            if (closeTimeoutRef.current) {
                clearTimeout(closeTimeoutRef.current);
                closeTimeoutRef.current = null;
            }

            if (!newOpen) {
                // Delay closing to allow moving mouse to popover content
                closeTimeoutRef.current = setTimeout(() => {
                    setOpen(false);
                }, 200);
            } else {
                setOpen(true);
            }
        };

        const handleMouseEnter = () => {
            if (closeTimeoutRef.current) {
                clearTimeout(closeTimeoutRef.current);
                closeTimeoutRef.current = null;
            }
        };

        const handlePopoverClick = (e: React.MouseEvent) => {
            // Prevent clicks inside the popover from closing it
            e.stopPropagation();
        };

        const handleConfirm = (e: React.MouseEvent) => {
            e.preventDefault();
            e.stopPropagation();
            setOpen(false);
            if (onConfirm) onConfirm();
        };

        return (
            <Popover open={open} onOpenChange={handleOpenChange}>
                <PopoverTrigger asChild>
                    <DropdownMenuItem
                        ref={ref}
                        className={className}
                        inset={inset}
                        onSelect={(e) => {
                            // Prevent the dropdown from closing when clicking the item
                            e.preventDefault();
                            setOpen(true);
                        }}
                        {...props}
                    >
                        {children}
                    </DropdownMenuItem>
                </PopoverTrigger>
                <PopoverContent
                    ref={popoverRef}
                    className={cn(
                        'z-[100] w-auto p-0',
                        popoverContentClassName,
                    )}
                    align={align}
                    onMouseEnter={handleMouseEnter}
                    onClick={handlePopoverClick}
                    sideOffset={5}
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

ConfirmDropdownMenuItem.displayName = 'ConfirmDropdownMenuItem';

export { ConfirmDropdownMenuItem };

// Usage example:
/*
  <ConfirmDropdownMenuItem 
    onConfirm={() => handleDelete()}
    confirmText="Удалить"
    cancelText="Отмена"
  >
    Удалить
  </ConfirmDropdownMenuItem>
*/
