import { type ComponentProps, forwardRef } from 'react';
import * as ToggleGroupPrimitive from '@radix-ui/react-toggle-group';
import { cn } from '@/lib/utils';

export const ToggleGroup = forwardRef<HTMLDivElement, ComponentProps<typeof ToggleGroupPrimitive.Root>>(
    ({ className, ...props }, ref) => (
        <ToggleGroupPrimitive.Root
            ref={ref}
            className={cn('inline-flex flex-wrap gap-1.5', className)}
            {...props}
        />
    ),
);
ToggleGroup.displayName = 'ToggleGroup';

export const ToggleGroupItem = forwardRef<HTMLButtonElement, ComponentProps<typeof ToggleGroupPrimitive.Item>>(
    ({ className, ...props }, ref) => (
        <ToggleGroupPrimitive.Item
            ref={ref}
            className={cn(
                'inline-flex h-9 items-center gap-1.5 rounded-md border border-border px-3 text-sm font-medium text-muted-foreground transition-colors',
                'hover:text-foreground',
                'data-[state=on]:border-route data-[state=on]:bg-route/10 data-[state=on]:text-route',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                className,
            )}
            {...props}
        />
    ),
);
ToggleGroupItem.displayName = 'ToggleGroupItem';
