import { type ComponentProps, forwardRef } from 'react';
import * as TooltipPrimitive from '@radix-ui/react-tooltip';
import { cn } from '@/lib/utils';

export const TooltipProvider = TooltipPrimitive.Provider;
export const Tooltip = TooltipPrimitive.Root;
export const TooltipTrigger = TooltipPrimitive.Trigger;

export const TooltipContent = forwardRef<HTMLDivElement, ComponentProps<typeof TooltipPrimitive.Content>>(
    ({ className, sideOffset = 6, ...props }, ref) => (
        <TooltipPrimitive.Portal>
            <TooltipPrimitive.Content
                ref={ref}
                sideOffset={sideOffset}
                className={cn(
                    'z-50 max-w-64 rounded-md border border-border bg-background px-3 py-1.5 text-xs text-foreground shadow-lg',
                    className,
                )}
                {...props}
            />
        </TooltipPrimitive.Portal>
    ),
);
TooltipContent.displayName = 'TooltipContent';
