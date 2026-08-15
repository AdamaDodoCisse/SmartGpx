import { type ComponentProps, forwardRef } from 'react';
import * as RadioGroupPrimitive from '@radix-ui/react-radio-group';
import { cn } from '@/lib/utils';

export const RadioGroup = forwardRef<HTMLDivElement, ComponentProps<typeof RadioGroupPrimitive.Root>>(
    ({ className, ...props }, ref) => (
        <RadioGroupPrimitive.Root ref={ref} className={cn('grid gap-2', className)} {...props} />
    ),
);
RadioGroup.displayName = 'RadioGroup';

export const RadioGroupItem = forwardRef<HTMLButtonElement, ComponentProps<typeof RadioGroupPrimitive.Item>>(
    ({ className, ...props }, ref) => (
        <RadioGroupPrimitive.Item
            ref={ref}
            className={cn(
                'flex size-5 shrink-0 items-center justify-center rounded-full border border-border bg-background',
                'data-[state=checked]:border-primary',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                'disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        >
            <RadioGroupPrimitive.Indicator className="flex items-center justify-center">
                <span className="size-2.5 rounded-full bg-primary" aria-hidden="true" />
            </RadioGroupPrimitive.Indicator>
        </RadioGroupPrimitive.Item>
    ),
);
RadioGroupItem.displayName = 'RadioGroupItem';
