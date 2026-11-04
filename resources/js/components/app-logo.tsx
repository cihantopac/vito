import { cn } from '@/lib/utils';

export default function AppLogo({ className }: { className?: string }) {
  return <span className={cn('whitespace-nowrap text-lg font-semibold tracking-tight text-foreground', className)}>Pure</span>;
}
