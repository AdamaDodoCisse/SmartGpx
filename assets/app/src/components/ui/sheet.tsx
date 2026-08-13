import { type ComponentProps, forwardRef } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

export const Sheet = Dialog.Root;
export const SheetTrigger = Dialog.Trigger;
export const SheetClose = Dialog.Close;

export const SheetOverlay = forwardRef<HTMLDivElement, ComponentProps<typeof Dialog.Overlay>>(
    ({ className, ...props }, ref) => (
        <Dialog.Overlay
            ref={ref}
            className={cn('fixed inset-0 z-50 bg-black/40', className)}
            {...props}
        />
    ),
);
SheetOverlay.displayName = 'SheetOverlay';

interface SheetContentProps extends ComponentProps<typeof Dialog.Content> {
    closeLabel: string;
}

export const SheetContent = forwardRef<HTMLDivElement, SheetContentProps>(
    ({ className, children, closeLabel, ...props }, ref) => (
        <Dialog.Portal>
            <SheetOverlay />
            <Dialog.Content
                ref={ref}
                className={cn(
                    'fixed inset-y-0 right-0 z-50 w-3/4 max-w-sm border-l border-border bg-background p-6 shadow-lg',
                    className,
                )}
                {...props}
            >
                {children}
                <Dialog.Close
                    className="absolute right-4 top-4 rounded-md p-1 text-muted-foreground hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    aria-label={closeLabel}
                >
                    <X className="h-5 w-5" aria-hidden="true" />
                </Dialog.Close>
            </Dialog.Content>
        </Dialog.Portal>
    ),
);
SheetContent.displayName = 'SheetContent';

export const SheetTitle = Dialog.Title;
export const SheetDescription = Dialog.Description;
