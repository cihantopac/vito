import { cn } from '@/lib/utils';
import { type HTMLAttributes } from 'react';

export default function InputError({ message, className = '', ...props }: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
  return message ? (
    <p {...props} className={cn('whitespace-pre-line text-sm text-neutral-600 dark:text-neutral-400', className)}>
      {message}
    </p>
  ) : null;
}
