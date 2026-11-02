import { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export default function Container({ className, children }: { className?: string; children?: ReactNode }) {
  const filteredClassName = className
    ?.split(/\s+/)
    .filter((c) => !['max-w-4xl', 'max-w-5xl', 'max-w-6xl', 'max-w-7xl'].includes(c))
    .join(' ');

  return <div className={cn('flex w-full max-w-none min-w-0 flex-col gap-6 px-4 py-6 sm:px-6', filteredClassName)}>{children}</div>;
}
